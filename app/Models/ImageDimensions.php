<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageDimensions extends Model
{
    use HasFactory;
    const UPDATED_AT = null;
    protected $fillable = [
        'image_id', 'x_axis', 'y_axis', 'is_portrait', 'is_square'
    ];

    public static function addImageDimensionInfo(int $imageId, int $x, int $y)
    {
        return self::create([
            'image_id' => $imageId,
            'x_axis' => $x,
            'y_axis' => $y,
            'is_portrait' => $y > $x,
            'is_square' => $x == $y
        ]);
    }

    /**
     * @param array $imagesDimensionsBag - [[id1, x1, y1], [id2, x2, y2] ... [idn, xn, yn]]
     */
    public static function addMultipleImagesDimensionsInfo(array &$imagesDimensionsBag)
    {
        return self::insert(array_map(function ($imageDimension) {
            return [
                'image_id' => $imageDimension[0],
                'x_axis' => $imageDimension[1],
                'y_axis' => $imageDimension[2],
                'is_portrait' => $imageDimension[2] > $imageDimension[1],
                'is_square' => $imageDimension[1] == $imageDimension[2]
            ];
        }, $imagesDimensionsBag));
    }
}
