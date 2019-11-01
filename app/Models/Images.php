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
    protected static function list(array &$types = ['WALLPAPER']){
        return self::whereIn('type', $types)->orderBy('created_at', 'desc')->paginate(env('PAGINATION', 20));
    }
}
