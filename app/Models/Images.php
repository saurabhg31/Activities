<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Images extends Model
{
    protected $table='images';
    protected $fillable = ['type', 'image', 'imageType', 'tags'];
    protected $hidden = ['id'];

    /**
     * get images
     */
    protected static function list(array &$types = null){
        return self::when($types, function($query) use ($types){
            return $query->whereIn('type', $types);
        })->orderBy('created_at', 'desc')->paginate(env('PAGINATION', 20));
    }

    /**
     * search images
     * TODO: change type search to multiple
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
        })->orderBy('created_at', 'desc')->paginate(env('PAGINATION', 20));
        $search->response = 'Search complete';
        return $search;
    }

    /**
     * list image types
     */
    protected static function imageTypes(){
        return self::select('type')->get()->unique();
    }
}
