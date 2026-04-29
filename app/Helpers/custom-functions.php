<?php

namespace App\Helpers;

use App\Models\ImageIndex;
use App\Models\SearchQueryIndexing;
use Illuminate\Support\Facades\Cache;

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
    function cacheImageSearchPrompts(string $domain, int $userId = null)
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
