<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Images extends Model
{
    protected $table='images';
    protected $fillable = ['type', 'image', 'imageType', 'tags', 'user_id'];
    protected $hidden = ['id'];

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
            foreach($tags as $tag){
                $query2->where('tags', 'like', '%'.$tag.'%');
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
        return self::whereIn('id', $imageIds)->where('user_id', Auth::id())->delete();
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
}
