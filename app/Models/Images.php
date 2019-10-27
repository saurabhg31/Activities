<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Images extends Model
{
    protected $table='images';
    protected $fillable = ['type', 'image'];
    protected $hidden = ['id'];

    /**
     * get images
     */
    protected static function list(array &$types = ['WALLPAPER']){
        return self::whereIn('type', $types)->get();
    }
}
