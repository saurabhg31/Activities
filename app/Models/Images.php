<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class Images extends Model
{
    protected $table = 'images';
    protected $fillable = ['type', 'image', 'imageType', 'tags', 'user_id', 'lastSearchCount', 'created_at'];

    /**
     * get images
     */
    protected static function list(array &$types = null)
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
    protected static function search(array &$params = null, bool $useIndexing = true)
    {
        $tags = [];
        $search = self::when(isset($params['types']), function ($query) use ($params) {
            return $query->where('type', $params['types']);
        })->when(isset($params['tags']), function ($conditionalQuery) use ($params, $useIndexing, &$tags) {
            $tags = preg_split('/[\ \n\,]+/', $params['tags']);
            if ($useIndexing) {
                return $conditionalQuery->join('image_search_indexing', function ($join) use ($tags) {
                    $join->on('image_search_indexing.image_id', '=', 'images.id')->whereIn('tag', $tags);
                });
            } else {
                return $conditionalQuery->where(function ($query) use ($tags) {
                    foreach ($tags as $tag) {
                        $query->where('tags', 'like', '%' . $tag . '%');
                    }
                    return $query;
                });
            }
        });
        if (Session::has('domain') && Session::get('domain') == 'private') {
            $search->where('user_id', auth('web')->id());
        } else {
            $search->whereNull('user_id');
        }
        if (!empty($tags) && $useIndexing) {
            $search->groupBy('images.id')->havingRaw('COUNT(DISTINCT image_search_indexing.tag) = ' . count($tags));
        }
        DB::statement('SET sql_mode=""');
        $search = $search->orderBy('images.id', 'desc')->paginate(env('PAGINATION', 20));
        DB::statement('SET sql_mode="only_full_group_by"');
        $search->response = 'Search complete';
        return $search;
    }

    /**
     * list image types
     * @param boolean $checkDomain
     */
    protected static function imageTypes(bool $checkDomain = true)
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
    protected static function deleteImages(array &$imageIds)
    {
        return self::whereIn('id', $imageIds)->when(env('IMGDEL') === 'allow', function ($query) {
            return $query->where('user_id', NULL)->orWhere('user_id', Auth::id());
        })->delete();
    }

    /**
     * update image info
     */
    protected static function updateImageInfo(array &$params)
    {
        $check = self::select('user_id')->where('id', $params['imageId'])->first();
        if (is_null($check->user_id) || $check->user_id === Auth::id()) {
            return self::where('id', $params['imageId'])->update([
                'type' => $params['type'],
                'tags' => $params['tags']
            ]);
        }
        return false;
    }

    /**
     * remove duplicate images
     * @param self $imageData
     * @param array $exceptIds (ids of images whose duplicate search is already completed)
     * @param array $fields (required fields)
     */
    protected static function listDuplicatesOf(self &$imageData, array &$exceptIds = null, array &$fields = ['id'])
    {
        return self::select($fields)->when($exceptIds, function ($query) use ($exceptIds) {
            return $query->whereNotIn('id', $exceptIds);
        })->where('image', $imageData->image)->where('id', '!=', $imageData->id)->get();
    }
}
