<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Images extends Model
{
    protected $table='images';
    protected $fillable = ['type', 'image', 'imageType', 'tags', 'user_id', 'lastSearchCount'];
    // protected $hidden = ['id'];

    /**
     * get images
     */
    protected static function list(array &$types = null){
        return self::when($types, function($query) use ($types){
            return $query->whereIn('type', $types);
        })->when(Session::has('domain'), function($query2){
            $domain = Session::get('domain');
            if($domain === 'private'){
                return $query2->where('user_id', Auth::id());
            }
            else{
                return $query2->where('user_id', NULL);
            }
        })->when(!Session::has('domain'), function($query3){
            return $query3->where('user_id', NULL);
        })->orderBy('created_at', 'desc')->paginate(env('PAGINATION', 20));
    }

    /**
     * search images
     * TODO: change type search to multiple
     * TODO: solve multiple pagination issue
     */
    protected static function search(array &$params = null){
        $search = self::when(isset($params['types']), function($query) use ($params){
            return $query->where('type', $params['types']);
        })->when(isset($params['tags']), function($query2) use ($params){
            $tags = preg_split('/[\ \n\,]+/', $params['tags']);
            $query2->where('tags', 'like', '%'.array_first($tags).'%');
            if(isset($tags[1])){
                foreach(array_except($tags, [0]) as $tag){
                    $query2->orWhere('tags', 'like', '%'.$tag.'%');
                }
            }
            return $query2;
        })->when(Session::has('domain'), function($query3){
            $domain = Session::get('domain');
            if($domain === 'private'){
                return $query3->where('user_id', Auth::id());
            }
            else{
                return $query3->where('user_id', NULL);
            }
        })->when(!Session::has('domain'), function($query4){
            return $query4->where('user_id', NULL);
        })->orderBy('created_at', 'desc')->paginate(env('PAGINATION', 20));
        $search->response = 'Search complete';
        return $search;
    }

    /**
     * list image types
     */
    protected static function imageTypes(){
        return self::select('type')->distinct('type')->orderBy('type', 'asc')->get();
    }

    /**
     * delete a image(s)
     */
    protected static function deleteImages(array &$imageIds){
        return self::whereIn('id', $imageIds)->when(env('IMGDEL') === 'allow', function($query){
            return $query->where('user_id', NULL)->orWhere('user_id', Auth::id());
        })->delete();
    }

    /**
     * update image info
     */
    protected static function updateImageInfo(array &$params){
        $check = self::select('user_id')->where('id', $params['imageId'])->first();
        if(is_null($check->user_id) || $check->user_id === Auth::id()){
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
    protected static function listDuplicatesOf(self &$imageData, array &$exceptIds = null, array &$fields = ['id']){
        return self::select($fields)->when($exceptIds, function($query) use ($exceptIds){
            return $query->whereNotIn('id', $exceptIds);
        })->where('image', $imageData->image)->where('id', '!=', $imageData->id)->get();
    }
}
