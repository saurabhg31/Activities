<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Images extends Model
{
    protected $table = 'images';
    protected $fillable = ['type', 'image', 'imageType', 'tags', 'user_id', 'length', 'isAnimated'];

    /**
     * Access image data accessor
     * @param string $value
     * @return string
     */
    protected function getImageAttribute(string $value): string
    {
        if (str_starts_with($value, config('constants.TMP_STORED_PREFIX'))) {
            $filePath = trim(str_replace(config('constants.TMP_STORED_PREFIX'), '', $value));
            return base64_encode(Storage::get($filePath));
        }
        return $value;
    }

    /**
     * Set image data mutator
     * @param string $value
     * @return void
     */
    protected function setImageAttribute(string $value): void
    {
        if (strlen($value) > config('constants.MAX_IMG_SIZE')) {
            $pathVal = $this->getRawOriginal('image');
            if (is_null($pathVal)) {
                // new image
                $pathVal = 'images' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . Str::random() . '_' . time();
                Storage::put($pathVal, base64_decode($value, true));
                $pathVal = config('constants.TMP_STORED_PREFIX') . ' ' . $pathVal;
            } else {
                // updating image
                $filePath = trim(str_replace(config('constants.TMP_STORED_PREFIX'), '', $pathVal));
                Storage::put($filePath, base64_decode($value, true));
            }
            $this->attributes['image'] = $pathVal;
        } else {
            $this->attributes['image'] = $value;
        }
    }

    /**
     * get images
     */
    public static function list(?array $types = null)
    {
        $query = self::select('id')->distinct('id')->when($types, function ($typeQuery) use ($types) {
            return $typeQuery->whereIn('type', $types);
        });
        if (Session::has('domain') && Session::get('domain') == 'private') {
            $query->where('user_id', auth('web')->id());
        } else {
            $query->whereNull('user_id');
        }
        return $query->orderBy('id', 'desc')->paginate(env('PAGINATION', 20));
    }

    /**
     * search images
     */
    public static function search(?array $params = null, bool $useIndexing = true)
    {
        $tagsToExclude = [];
        $idsToExclude = [];
        $onlyTags = [];
        $likeTags = [];
        $anyTagFlag = false; // flag to match any of the given tags
        if (str_contains($params['tags'], 'only:')) {
            if (str_contains($params['tags'], 'x:') || str_contains($params['tags'], 'any:') || str_contains($params['tags'], 'like:')) {
                throw new Exception('Cannot combine only: with other search params.');
            }
            // searches for images which has exactly only tags and no more, tags before only: are ignored
            $tags = [];
            $ids = [];
            $onlyTags = self::getSearchTagsFromTagString('only:', $params['tags']);
        } else {
            if (str_contains($params['tags'], 'x:')) {
                // checking for tags to be excluded
                $tagsToExclude = self::getSearchTagsFromTagString('x:', $params['tags']);
                $idsToExclude = array_filter($tagsToExclude, function ($str) {
                    return (int)$str == $str;
                });
                $tagsToExclude = array_diff($tagsToExclude, $idsToExclude);
            }
            if (str_contains($params['tags'], 'like:')) {
                if (str_contains($params['tags'], 'any:')) {
                    throw new Exception('Cannot combine like: search param with any:.');
                }
                $likeTags = self::getSearchTagsFromTagString('like:', $params['tags']);
            }
            if (str_contains($params['tags'], 'any:')) {
                // tags before any: will be ignored
                $anyTagFlag = true;
                $tags = self::getSearchTagsFromTagString('any:', $params['tags']);
            }
            $tags = !empty($tags) ? $tags : array_filter(preg_split('/[\ \n\,]+/', $params['tags']));
            $ids = array_filter($tags, function ($str) {
                return (int)$str == $str;
            });
            $tags = array_filter(array_diff($tags, $ids));
        }
        // Debug code
        /*
        dd([
            'tags' => $tags,
            'ids' => $ids,
            'onlyTags' => $onlyTags,
            'tagsToExclude' => $tagsToExclude,
            'idsToExclude' => $idsToExclude,
            'likeTags' => $likeTags,
            'anyTagFlag' => $anyTagFlag
        ]);
        */
        $extensionTags = [];
        $gifDataPresent = false;
        $animationsOnly = false;
        $nullTagsOnly = false; // if true, show images with null tags only
        $indexingTableJoined = false;
        $compressedImagesOnly = false;
        if (!empty($tags)) {
            $availableExtensions = self::selectRaw('distinct imageType')->pluck('imageType')->toArray();
            $extensionTags = array_intersect($availableExtensions, $tags);
            $tags = array_filter(array_diff($tags, $extensionTags));
            $gifDataPresent = !empty(array_intersect(config('constants.ANIMATED_IMG_EXTENSIONS'), $extensionTags));
            if (in_array(config('constants.ANIMATION_ONLY_TAG'), $tags)) {
                $animationsOnly = true;
                $tags = array_filter(array_diff($tags, [config('constants.ANIMATION_ONLY_TAG')]));
                $gifDataPresent = true;
            }
            if (in_array(config('constants.NO_TAGS_SEARCH_TAG'), $tags)) {
                $nullTagsOnly = true;
                $tags = array_filter(array_diff($tags, [config('constants.NO_TAGS_SEARCH_TAG')]));
            }
            if (in_array(config('constants.COMPRESSION_TAG'), $tags)) {
                $compressedImagesOnly = true;
                $tags = array_filter(array_diff($tags, [config('constants.COMPRESSION_TAG')]));
            }
        }
        $search = self::select('images.id')->distinct('images.id')->when(isset($params['types']), function ($query) use ($params) {
            return $query->where('images.type', $params['types']);
        })->when($animationsOnly, function ($animationsOnlyQuery) {
            return $animationsOnlyQuery->where('images.isAnimated', true);
        })->when($nullTagsOnly, function ($nullTagsQuery) {
            return $nullTagsQuery->whereNull('images.tags');
        })->when($compressedImagesOnly, function ($compressedOnlyQuery) {
            return $compressedOnlyQuery->join('image_compression_logs', function ($query) {
                $query->on('image_compression_logs.image_id', '=', 'images.id')
                    ->where('image_compression_logs.file_update_accepted', true);
            });
        })->when(!empty($extensionTags), function ($extensionsQuery) use ($extensionTags) {
            if (count($extensionTags) === 1) {
                $extensionsQuery->where('images.imageType', reset($extensionTags));
            } else {
                $extensionsQuery->where(function ($query) use ($extensionTags) {
                    $query->where('images.imageType', reset($extensionTags));
                    array_shift($extensionTags);
                    foreach ($extensionTags as $extensionTag) {
                        $query->orWhere('images.imageType', $extensionTag);
                    }
                });
            }
            return $extensionsQuery;
        })->when(!empty($tags), function ($conditionalQuery) use ($useIndexing, $tags, &$indexingTableJoined) {
            if ($useIndexing) {
                $conditionalQuery->join('image_search_indexing', function ($join) use ($tags, &$indexingTableJoined) {
                    $join->on('image_search_indexing.image_id', '=', 'images.id')
                        ->whereIn('image_search_indexing.tag', $tags);
                    $indexingTableJoined = true;
                });
            } else {
                $conditionalQuery->where(function ($query) use ($tags) {
                    foreach ($tags as $tag) {
                        $query->where('images.tags', 'like', '%' . $tag . '%');
                    }
                    return $query;
                });
            }
            return $conditionalQuery;
        })->when(!empty($tagsToExclude), function ($excludeTagsQuery) use ($tagsToExclude, $useIndexing) {
            if ($useIndexing) {
                // Use whereNotExists to ensure the image is removed if ANY excluded tag matches
                // @source: https://gemini.google.com/app/724f83370c0c3c20
                $excludeTagsQuery->whereNotExists(function ($subQuery) use ($tagsToExclude) {
                    $subQuery->selectRaw(1)
                        ->from('image_search_indexing')
                        ->whereRaw('image_search_indexing.image_id = images.id')
                        ->whereIn('tag', $tagsToExclude);
                });
            } else {
                // For the flat string search, multiple 'not like' clauses work correctly
                $excludeTagsQuery->where(function ($q) use ($tagsToExclude) {
                    foreach ($tagsToExclude as $tag) {
                        $q->where('images.tags', 'not like', '%' . $tag . '%');
                    }
                });
            }
            return $excludeTagsQuery;
        })->when(!empty($idsToExclude), function ($excludeIdsQuery) use ($idsToExclude) {
            return $excludeIdsQuery->whereNotIn('images.id', $idsToExclude);
        })->when(!empty($onlyTags), function ($onlyTagsQuery) use ($useIndexing, $onlyTags, &$indexingTableJoined) {
            if (!$useIndexing) {
                throw new Exception('Indexing is disabled, unable to use only: search param.');
            }
            $tagsCount = count($onlyTags);
            if (!$indexingTableJoined) {
                $onlyTagsQuery->join('image_search_indexing', 'image_search_indexing.image_id', '=', 'images.id');
                $indexingTableJoined = true;
            }
            return $onlyTagsQuery->groupBy('images.id')->havingRaw("COUNT(DISTINCT image_search_indexing.tag) = " . $tagsCount . " AND SUM(image_search_indexing.tag IN (" . implode(',', array_map(function ($str) {
                return '\'' . $str . '\'';
            }, $onlyTags)) . ")) = " . $tagsCount);
        })->when(!empty($likeTags), function ($likeQuery) use ($useIndexing, $likeTags) {
            if (!$useIndexing) {
                throw new Exception('Indexing is disabled, unable to use like: search param.');
            }
            /**
             * like query matching
             * @source: https://gemini.google.com/app/927731503e26c4a6
             */
            $likeQuery->whereExists(function ($subQuery) use ($likeTags) {
                $subQuery->selectRaw(1)
                    ->from('image_search_indexing')
                    ->whereColumn('image_search_indexing.image_id', 'images.id')
                    ->where(function ($q) use ($likeTags) {
                        // Apply the OR condition to filter relevant rows
                        foreach ($likeTags as $tag) {
                            $q->orWhere('tag', 'like', $tag);
                        }
                    })
                    ->groupBy('image_id')
                    // Count how many unique criteria were satisfied
                    ->havingRaw('COUNT(DISTINCT CASE ' .
                        collect($likeTags)->map(function ($tag, $index) {
                            return "WHEN tag LIKE ? THEN $index ";
                        })->implode(' ') .
                        'END) = ?', [...$likeTags, count($likeTags)]);
            });
            return $likeQuery;
        });
        if (Session::has('domain') && Session::get('domain') == 'private') {
            $search->where('images.user_id', auth('web')->id());
        } else {
            $search->whereNull('images.user_id');
        }
        $tagsCount = count($tags);
        if (!$anyTagFlag && $useIndexing && $tagsCount > 1) {
            $search->groupBy('images.id')->havingRaw('COUNT(DISTINCT image_search_indexing.tag) = ' . $tagsCount);
        }
        if (!empty($ids)) {
            $search->whereIn('images.id', $ids);
        }
        if (isset($params['page'])) {
            if (!is_numeric($params['page'])) {
                throw new Exception('Pagination page number not numeric.');
            }
        }
        // Debug code
        // $search->orderBy('images.id', 'desc')->dd();
        $search = $search->orderBy('images.id', 'desc')->paginate($gifDataPresent ? 8 : env('PAGINATION', 20));
        $search->response = 'Search complete';
        return $search;
    }

    /**
     * list image types
     * @param boolean $checkDomain
     */
    public static function imageTypes(bool $checkDomain = true)
    {
        return ImageType::getTypes($checkDomain);
    }

    /**
     * delete a image(s)
     */
    public static function deleteImages(array $imageIds)
    {
        // getting ids that can be deleted
        $deletableImageIds = self::select('id')->whereIn('id', $imageIds);
        if (env('IMGDEL', 'allow') === 'allow') {
            $deletableImageIds->where(function ($query) {
                return $query->where('user_id', NULL)->orWhere('user_id', Auth::id());
            });
        } else {
            $deletableImageIds->where('user_id', Auth::id());
        }
        $deletableImageIds = $deletableImageIds->pluck('id')->toArray();

        // segregating images with image data stored in temp folder
        $tempStoredImages = self::select(['id', 'image'])->whereIn('id', $deletableImageIds)
            ->where('image', 'like', config('constants.TMP_STORED_PREFIX') . '%')->get();
        $tempStoredImageIds = $tempStoredImages->pluck('id')->toArray();
        $tableStoredImageIds = array_diff($deletableImageIds, $tempStoredImageIds);

        // deleting table stored images
        $deletedImagesCount = self::whereIn('id', $tableStoredImageIds)->delete();
        unset($deletableImageIds, $tableStoredImageIds);

        // deleting temp folder stored images & related files
        $filesToDelete = [];
        foreach ($tempStoredImages as $imageData) {
            $filesToDelete[] = trim(str_replace(config('constants.TMP_STORED_PREFIX'), '', $imageData->getRawOriginal('image')));
        }
        Storage::delete($filesToDelete);
        $deletedImagesCount += self::whereIn('id', $tempStoredImageIds)->delete();
        return $deletedImagesCount;
    }

    /**
     * update image info
     */
    public static function updateImageInfo(array $params, ?int $userId = null)
    {
        if (is_null($userId)) {
            $userId = Auth::id();
        }
        $check = self::select('user_id')->where('id', $params['imageId'])->first();
        if (is_null($check->user_id) || $check->user_id === $userId) {
            self::where('id', $params['imageId'])->update([
                'type' => $params['type'],
                'tags' => $params['tags']
            ]);
            return ImageIndex::updateIndices($params['imageId'], $params['tags']);
        }
        return false;
    }

    /**
     * remove duplicate images
     * @param self $imageData
     * @param array $exceptIds (ids of images whose duplicate search is already completed)
     * @param array $fields (required fields)
     */
    public static function listDuplicatesOf(self $imageData, ?array $exceptIds = null, array $fields = ['id'])
    {
        return self::select($fields)->when($exceptIds, function ($query) use ($exceptIds) {
            return $query->whereNotIn('id', $exceptIds);
        })->where('image', $imageData->image)->where('id', '!=', $imageData->id)->get();
    }

    /**
     * Calculate and store length of image based on id
     * @param integer $imageId
     * @return void
     */
    public static function logImageLength(int $imageId)
    {
        $imageLength = strlen(self::find($imageId)->image);
        self::where('id', $imageId)->update([
            'length' => $imageLength,
            'updated_at' => now()
        ]);
        ImageDimensions::where('image_id', $imageId)->update(['length' => $imageLength]);
    }

    /**
     * show duplicates, code based on 4 d hashes
     * show duplicates using 256-bit Hamming Distance
     */
    public static function showDuplicates()
    {
        // 1. Determine user scope cleanly
        $userCondition = 'ia.user_id IS NULL AND ib.user_id IS NULL';

        if (Session::has('domain') && Session::get('domain') === 'private') {
            $userId = Auth::id();
            $userCondition = "ia.user_id = {$userId} AND ib.user_id = {$userId}";
        }

        // Set your precision tolerance
        $threshold = config('constants.DUPLICATE_IMG_SEARCH_THRESHOLD');

        // 2. The Cleaned Self-Join Query
        // Note: We use a.image_id to return the actual core image IDs, not the hash table row IDs.
        // @source: https://gemini.google.com/app/a5e238154af2be90
        $sql = "SELECT a.image_id AS id1, b.image_id AS id2
        FROM images_difference_hash a
        JOIN images_difference_hash b ON a.image_id < b.image_id
        JOIN images ia ON ia.id = a.image_id
        JOIN images ib ON ib.id = b.image_id
        WHERE a.hash_1 IS NOT NULL AND b.hash_1 IS NOT NULL
          AND {$userCondition}
          AND (
              BIT_COUNT(a.hash_1 ^ b.hash_1) + 
              BIT_COUNT(a.hash_2 ^ b.hash_2) + 
              BIT_COUNT(a.hash_3 ^ b.hash_3) + 
              BIT_COUNT(a.hash_4 ^ b.hash_4)
          ) <= ?";

        $pairs = DB::select($sql, [$threshold]);

        // Fast return if no matching duplicates are found
        if (empty($pairs)) {
            return self::where('id', -1)->paginate(env('PAGINATION', 20));
        }

        // ordering duplicates based on first image id and then second image id
        $idsOrderString = '';
        $duplicateIds = [];
        foreach ($pairs as $pair) {
            if (!in_array($pair->id1, $duplicateIds)) {
                $duplicateIds[] = $pair->id1;
            }
            if (!in_array($pair->id2, $duplicateIds)) {
                $index = array_search($pair->id1, $duplicateIds);
                if ($index !== false) {
                    array_splice($duplicateIds, $index + 1, 0, [$pair->id2]);
                }
            }
        }
        unset($pairs);
        $idsOrderString = implode(',', $duplicateIds);
        unset($duplicateIds);

        return self::whereRaw("id in ({$idsOrderString})")
            ->orderByRaw("FIELD(id, $idsOrderString)")
            ->paginate(env('PAGINATION', 20));
    }

    /**
     * get search tags from entire search tag string
     * @param string $searchTag
     * @param string &$completeSearchString
     * @return array
     */
    private static function getSearchTagsFromTagString(string $searchTag, string &$completeSearchString): array
    {
        $searchTagString = Str::after($completeSearchString, $searchTag);
        $semiColonFirstPos = strpos($searchTagString, ';');
        $searchTagString = substr($searchTagString, 0, $semiColonFirstPos);
        $completeSearchString = str_replace($searchTag . $searchTagString . ';', '', $completeSearchString);
        return array_filter(preg_split('/[\ \n\,]+/', $searchTagString));
    }
}
