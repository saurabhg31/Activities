<?php

namespace App\Models;

use Error;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class Images extends Model
{
    protected $table = 'images';
    protected $fillable = ['type', 'image', 'imageType', 'tags', 'user_id', 'lastSearchCount', 'length', 'created_at'];
    protected $duplicateDataResultFile = 'data/duplicatesSearchResult.jsonl';

    /**
     * get images
     */
    public static function list(array $types = null)
    {
        $query = self::when($types, function ($typeQuery) use ($types) {
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
    public static function search(array $params = null, bool $useIndexing = true)
    {
        $tags = preg_split('/[\ \n\,]+/', $params['tags']);
        $tags = array_map(function ($str) {
            return trim($str);
        }, $tags);
        $ids = array_filter($tags, function ($str) {
            return is_numeric($str);
        });
        $tags = array_diff($tags, $ids);
        $gifDataPresent = false;
        $search = self::select('images.*')->when(isset($params['types']), function ($query) use ($params) {
            return $query->where('images.type', $params['types']);
        })->when(!empty($tags), function ($conditionalQuery) use ($useIndexing, $tags, &$gifDataPresent) {
            if (array_search('gif', $tags) !== false) {
                $gifDataPresent = true;
            }
            if ($useIndexing) {
                $conditionalQuery->join('image_search_indexing', function ($join) use ($tags) {
                    $join->on('image_search_indexing.image_id', '=', 'images.id');
                    if (count($tags) == 1) {
                        $join->where('tag', 'like', '%' . reset($tags) . '%');
                    } else {
                        $join->whereIn('tag', $tags);
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
            $search->whereIn('images.id', $ids)->where('images.user_id', auth()->id());
        }
        DB::statement('SET sql_mode=""');
        $search = $search->orderBy('images.id', 'desc')->paginate($gifDataPresent ? 12 : env('PAGINATION', 20));
        DB::statement('SET sql_mode="only_full_group_by"');
        $search->response = 'Search complete';
        return $search;
    }

    /**
     * list image types
     * @param boolean $checkDomain
     */
    public static function imageTypes(bool $checkDomain = true)
    {
        /* Obsolete code. TODO: Remove
        return self::select('type')->distinct('type')->when($checkDomain, function ($query) {
            if (Session::has('domain') && Session::get('domain') == 'private') {
                $query->where('user_id', auth('web')->id());
            } else {
                $query->whereNull('user_id');
            }
            return $query;
        })->orderBy('type', 'asc')->get();
        */
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
    public static function updateImageInfo(array $params)
    {
        $check = self::select('user_id')->where('id', $params['imageId'])->first();
        if (is_null($check->user_id) || $check->user_id === Auth::id()) {
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
    public static function listDuplicatesOf(self $imageData, array $exceptIds = null, array $fields = ['id'])
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
        $img = self::find($imageId);
        $img->length = strlen($img->image);
        $img->save();
    }

    /**
     * show duplicates
     */
    public static function showDuplicates(int $page = 1)
    {
        $duplicatesMapping = json_decode(Storage::read((new self)->duplicateDataResultFile));
        if (!isset($duplicatesMapping->duplicatesSearchResult->result)) {
            throw new Error('No duplicate data found!');
        }
        if (empty($duplicatesMapping->duplicatesSearchResult->result)) {
            return new Collection();
        }
        $duplicatesMappingCollection = new Collection($duplicatesMapping->duplicatesSearchResult->result);
        $ids = array_unique($duplicatesMappingCollection->pluck('original')->toArray());
        foreach ($duplicatesMappingCollection->pluck('duplicates')->toArray() as $duplicateIdArrays) {
            $ids = array_merge($ids, $duplicateIdArrays);
        }
        $ids = array_unique($ids);
        asort($ids);
        return self::whereIn('id', $ids)->paginate(env('PAGINATION', 20));
    }
}
