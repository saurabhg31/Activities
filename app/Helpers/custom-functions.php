<?php

namespace App\Helpers;

use App\Models\ImageCompressionLog;
use App\Models\ImageDimensions;
use App\Models\ImageIndex;
use App\Models\Images;
use App\Models\SearchQueryIndexing;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
// use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Drivers\Imagick\Driver; // faster for .avif conversion
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Throwable;

if (!function_exists('translate')) {
    /**
     * Translate function to compensate for octane config caching issue
     * @param string $key
     * @param array $replace
     * @return \Illuminate\Contracts\Translation\Translator|string|array|null
     */
    function translate(string $key, array $replace = [])
    {
        $locale = request()->header('Accept-Language');
        if (!$locale) {
            $locale = app()->getLocale();
        }
        return trans($key, $replace, $locale);
    }
}

if (!function_exists('getAvailableLocales')) {
    /**
     * Get available locales
     * @return array
     */
    function getAvailableLocales()
    {
        return array_filter(scandir(base_path('lang')), function ($item) {
            return !in_array($item, ['.', '..']);
        });
    }
}

if (!function_exists('cacheImageSearchPrompts')) {
    /**
     * Function to cache image searches
     * @param string $domain
     * @return boolean
     */
    function cacheImageSearchPrompts(string $domain, ?int $userId = null)
    {
        $cacheName = "image_tags_{$domain}";
        if ($userId) {
            $cacheName .= "_{$userId}";
        }
        $popularSearches = SearchQueryIndexing::getPopularSearchPrompts(3, $domain)->pluck('tag_query');
        return Cache::put($cacheName, ImageIndex::getImageTags($userId, $domain)->merge($popularSearches)->sort(function ($prompt1, $prompt2) {
            return $prompt1 > $prompt2 ? 1 : 0;
        })->unique());
    }
}

if (!function_exists('getBase64StringFromImageData')) {
    /**
     * Return a base64 formed string from image data
     * @param \App\Models\Images $imageData
     * @return string
     */
    function getBase64StringFromImageData(Images &$imageData): string
    {
        return 'data:image/' . $imageData->imageType . ';base64,' . $imageData->image;
    }
}

if (!function_exists('isAnimatedComplete')) {
    /**
     * Detect if images are animations
     * @source: https://gemini.google.com/app/6a1fb4ae7ac0e109
     * @param string $base64String
     * @return boolean
     */
    function isAnimatedComplete(string $base64String)
    {
        if ($commaPos = strpos($base64String, ',')) {
            $base64String = substr($base64String, $commaPos + 1);
        }

        $fp = fopen('data://text/plain;base64,' . $base64String, 'r');
        if (!$fp) return false;

        $header = fread($fp, 12);
        $isAnimated = false;

        // 1. GIF89a
        if (strpos($header, "GIF89a") === 0) {
            $count = 0;
            while (!feof($fp) && $count < 2) {
                $chunk = fread($fp, 8192);
                $count += preg_match_all('#\x21\xf9\x04#', $chunk, $matches);
            }
            $isAnimated = ($count > 1);
        }
        // 2. WebP (RIFF....WEBP)
        elseif (strpos($header, "RIFF") === 0 && strpos($header, "WEBP", 8) !== false) {
            while (!feof($fp)) {
                if (strpos(fread($fp, 8192), "ANIM") !== false) {
                    $isAnimated = true;
                    break;
                }
            }
        }
        // 3. APNG (Look for "acTL" - Animation Control Chunk)
        elseif (strpos($header, "\x89PNG") === 0) {
            while (!feof($fp)) {
                if (strpos(fread($fp, 8192), "acTL") !== false) {
                    $isAnimated = true;
                    break;
                }
            }
        }
        // 4. AVIF (Look for "moov" with "trak" items or "pitm" sequence)
        // Note: AVIF is basically a HEIF container (MP4-based)
        elseif (strpos($header, "ftypavif", 4) !== false) {
            // AVIF animations are technically "AVIS" (AV1 Image Sequence)
            // We look for the track count or the avis brand
            fseek($fp, 0);
            $fullHeader = fread($fp, 100);
            if (strpos($fullHeader, "avis") !== false) $isAnimated = true;
        }
        // 5. SVG (Look for <animate> or <set> tags)
        elseif (strpos($header, "<?xml") === 0 || strpos($header, "<svg") === 0) {
            while (!feof($fp)) {
                $chunk = fread($fp, 8192);
                if (preg_match('/<(animate|set|animateMotion|animateTransform)/i', $chunk)) {
                    $isAnimated = true;
                    break;
                }
            }
        }
        // 6. FLI / FLC (Autodesk Animator)
        elseif (strlen($header) >= 12) {
            $data = unpack('vsize/vmagic/vframes', $header);
            // Magic: 0xAF11 (FLI), 0xAF12 (FLC)
            if (($data['magic'] == 0xAF11 || $data['magic'] == 0xAF12) && $data['frames'] > 1) {
                $isAnimated = true;
            }
        }

        fclose($fp);
        return $isAnimated;
    }
}

if (!function_exists('compressImage')) {
    /**
     * Attempt to do lossless compression of image to most compressible format
     * @source: https://gemini.google.com/app/b173ef1bc062842b
     * @param integer $imageId
     * @return boolean|null (true on success, false on failure, null if compression resulted in bigger/equal filesize)
     */
    function compressImage(int $imageId)
    {
        $imageData = Images::find($imageId);
        if (!$imageData) {
            return true;
        }
        $oldExtension = $imageData->imageType;
        $oldFilesize = $imageData->length;
        if (!$oldFilesize) {
            throw new Exception('Image id: ' . $imageId . ' does not have length parameter in table. Please run process:images command first.');
        }
        // TODO: Add compression logic for animated images
        if (isAnimatedComplete(getBase64StringFromImageData($imageData))) {
            return compressAnimatedImage($imageData);
        }
        try {
            // 1. Initialize Image Manager
            $manager = new ImageManager(new Driver());

            // 2. Decode the Base64 data directly
            // v4's decode() is highly versatile and handles raw base64 strings well
            $image = $manager->decode(base64_decode($imageData->image));

            // Define our target lossless encoders
            $encoders = [
                'avif' => new AvifEncoder(quality: 100),
                'webp' => new WebpEncoder(quality: 100),
                // We include PNG in the brute force because v4's PngEncoder might beat original source if the original was poorly optimized.
                'png'  => new PngEncoder(),
            ];

            $bestLength = $oldFilesize; // taking old file size as best length at first
            $bestBase64 = null;
            $bestType = null;
            $wasCompressed = false;

            foreach ($encoders as $type => $encoder) {
                // Skip encoding if we are already in that format (to save CPU)
                if ($type === $oldExtension && $type !== 'png') continue;

                $encoded = $image->encode($encoder);
                $currentBase64 = $encoded->toBase64();
                $currentLength = strlen($currentBase64);

                // 4. Comparison Logic
                if ($currentLength < $bestLength) {
                    $bestLength = $currentLength;
                    $bestBase64 = $currentBase64;
                    $bestType = $type;
                    $wasCompressed = true;
                }
            }
            unset($image);

            // adding log if compression failed
            if (!$wasCompressed) {
                ImageCompressionLog::addLog($imageId, $oldExtension, $oldExtension, $oldFilesize, $oldFilesize);
                return null;
            }

            // Saving image
            DB::transaction(function () use (
                &$imageData,
                &$bestType,
                &$bestLength,
                &$bestBase64,
                &$imageId,
                &$oldExtension,
                &$oldFilesize
            ) {
                $imageData->imageType = $bestType;
                $imageData->image = $bestBase64;
                $imageData->length = $bestLength;
                $imageData->save();
                ImageDimensions::where('image_id', $imageId)->update(['length' => $bestLength]);
                ImageCompressionLog::addLog($imageId, $oldExtension, $bestType, $oldFilesize, $bestLength);
            });
            return true;
        } catch (Throwable $error) {
            Log::channel('imageCompressionErrors')->error($error->getMessage(), $error->getTrace());
            ImageCompressionLog::addLog($imageId, $oldExtension, $oldExtension, $oldFilesize, $oldFilesize, $error->getMessage());
            return false;
        }
    }
}

if (!function_exists('compressAnimatedImage')) {
    /**
     * compress animated image
     * @source: https://gemini.google.com/app/b173ef1bc062842b
     * @param \App\Models\Images $imageData
     * @return boolean|null
     */
    function compressAnimatedImage(Images &$imageData)
    {
        // 1. MUST use Imagick for animation support
        $manager = new ImageManager(new Driver());

        if (!$imageData->isAnimated) {
            Images::where('id', $imageData->id)->update(['isAnimated' => true]);
        }

        $oldExtension = $imageData->imageType;
        $oldFilesize = $imageData->length;
        if (!$oldFilesize) {
            throw new Exception('Image id: ' . $imageData->id . ' does not have length parameter in table. Please run process:images command first.');
        }

        try {
            // decode() in v4 with Imagick preserves animation frames
            $image = $manager->decode(base64_decode($imageData->image));

            $bestBase64 = null;
            $bestLength = $oldFilesize;
            $bestType = $oldExtension;
            $wasCompressed = false;

            // 2. Animation-Compatible Encoders
            $encoders = [
                'webp' => new WebpEncoder(quality: 100), // Lossless Animated WebP
                'avif' => new AvifEncoder(quality: 100), // Lossless Animated AVIF
            ];

            foreach ($encoders as $type => $encoder) {
                if ($type === $oldExtension) continue;

                // encode() processes all frames when using the Imagick driver
                $encoded = $image->encode($encoder);
                $currentBase64 = $encoded->toBase64();

                // Calculate length as it would be stored (Base64)
                $currentLength = strlen($currentBase64);

                if ($currentLength < $bestLength) {
                    $bestLength = $currentLength;
                    $bestBase64 = $currentBase64;
                    $bestType = $type;
                    $wasCompressed = true;
                }
            }
            unset($image);

            if (!$wasCompressed) {
                ImageCompressionLog::addLog($imageData->id, $oldExtension, $oldExtension, $oldFilesize, $oldFilesize);
                return null;
            }

            DB::transaction(function () use (
                &$imageData,
                &$bestType,
                &$bestBase64,
                &$bestLength,
                &$oldExtension,
                &$oldFilesize
            ) {
                $imageData->imageType = $bestType;
                $imageData->image = $bestBase64;
                $imageData->length = $bestLength;
                $imageData->save();
                ImageDimensions::where('image_id', $imageData->id)->update(['length' => $bestLength]);
                ImageCompressionLog::addLog($imageData->id, $oldExtension, $bestType, $oldFilesize, $bestLength);
            });
            return true;
        } catch (\Throwable $error) {
            Log::channel('imageCompressionErrors')->error($error->getMessage(), $error->getTrace());
            ImageCompressionLog::addLog($imageData->id, $oldExtension, $oldExtension, $oldFilesize, $oldFilesize, $error->getMessage());
            return false;
        }
    }
}
