<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class ImageType extends Model
{
    use HasFactory;

    /**
     * list image types
     */
    protected static function getTypes(bool $checkDomain = true, array $fields = ['type'])
    {
        return self::select($fields)->when($checkDomain, function ($query) {
            if (Session::has('domain') && Session::get('domain') == 'private') {
                $query->where('user_id', auth('web')->id());
            } else {
                $query->whereNull('user_id');
            }
            return $query;
        })->orderBy('type', 'asc')->get();
    }

    /**
     * Add type
     */
    protected static function addType(string $type)
    {
        $data = [
            'type' => $type,
            'user_id' => (Session::has('domain') && Session::get('domain') == 'private') ? auth('web')->id() : NULL
        ];
        if (!self::where($data)->exists()) {
            self::create($data);
        }
    }
}
