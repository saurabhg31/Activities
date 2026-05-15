<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class Images extends Model
{
    protected $table = 'images';
    protected $fillable = ['type', 'image', 'imageType', 'tags', 'user_id', 'lastSearchCount', 'length', 'isAnimated'];

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
        if (str_contains($params['tags'], 'x:')) {
            // checking for tags to be excluded
            $tagsToExclude = array_filter(preg_split('/[\ \n\,]+/', Str::after($params['tags'], 'x:')));
            $params['tags'] = Str::before($params['tags'], 'x:');
            $idsToExclude = array_filter($tagsToExclude, function ($str) {
                return (int)$str == $str;
            });
        }
        $tags = array_filter(preg_split('/[\ \n\,]+/', $params['tags']));
        $ids = array_filter($tags, function ($str) {
            return (int)$str == $str;
        });
        $tags = array_filter(array_diff($tags, $ids));
        $extensionTags = [];
        $gifDataPresent = false;
        $animationsOnly = false;
        $nullTagsOnly = false; // if true, show images with null tags only
        $compressedImagesOnly = false;
        if (!empty($tags)) {
            $availableExtensions = self::select('imageType')->distinct()->pluck('imageType')->toArray();
            $extensionTags = array_intersect($availableExtensions, $tags);
            $tags = array_filter(array_diff($tags, $extensionTags));
            $gifDataPresent = !empty(array_intersect(config('constants.ANIMATED_IMG_EXTENSIONS'), $extensionTags));
        }
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
        })->when(!empty($tags), function ($conditionalQuery) use ($useIndexing, $tags) {
            if ($useIndexing) {
                $conditionalQuery->join('image_search_indexing', function ($join) use ($tags) {
                    $join->on('image_search_indexing.image_id', '=', 'images.id');
                    if (count($tags) === 1) {
                        $join->where('image_search_indexing.tag', 'like', '%' . reset($tags) . '%');
                    } else {
                        $join->whereIn('image_search_indexing.tag', $tags);
                    }
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
        });
        if (Session::has('domain') && Session::get('domain') == 'private') {
            $search->where('images.user_id', auth('web')->id());
        } else {
            $search->whereNull('images.user_id');
        }
        if ($useIndexing && count($tags) > 1) {
            $search->groupBy('images.id')->havingRaw('COUNT(DISTINCT image_search_indexing.tag) = ' . count($tags));
        }
        if (!empty($ids)) {
            $search->whereIn('images.id', $ids);
        }
        if (isset($params['page'])) {
            if (!is_numeric($params['page'])) {
                throw new Exception('Pagination page number not numeric.');
            }
        }
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
        return self::whereIn('id', $imageIds)->when(env('IMGDEL') === 'allow', function ($query) {
            return $query->where('user_id', NULL)->orWhere('user_id', Auth::id());
        })->delete();
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
        $imageLength = self::selectRaw('length(image) as length')->where('id', $imageId)->first()->length;
        self::where('id', $imageId)->update([
            'length' => $imageLength,
            'updated_at' => now()
        ]);
        ImageDimensions::where('image_id', $imageId)->update(['length' => $imageLength]);
    }

    /**
     * show duplicates
     * TODO: find an efficient way to order the images in the order the database provides, refer: https://gemini.google.com/app/affdc22adabc199e
     */
    public static function showDuplicates()
    {
        $userIdSubstr = 'user_id is null';
        if (Session::has('domain') && Session::get('domain') === 'private') {
            $userIdSubstr = 'user_id=' . Auth::id();
        }
        $duplicateImageIdsData = DB::select('select d_hash,GROUP_CONCAT(image_id, ",") as imageIds from images_difference_hash join images on images.id=images_difference_hash.image_id where ' . $userIdSubstr . ' group by d_hash having count(image_id)>1;');
        $imageIds = [];
        foreach ($duplicateImageIdsData as $data) {
            $imageIds = array_merge($imageIds, array_filter(explode(',', $data->imageIds)));
        }
        return self::whereIn('id', $imageIds)
            ->orderByRaw("FIELD(id, " . implode(',', $imageIds) . ")")
            ->paginate(env('PAGINATION', 20));
    }
}
