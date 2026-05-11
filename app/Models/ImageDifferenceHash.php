<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function App\Helpers\generateImageDifferenceHash;
use function App\Helpers\generateImageDifferenceHashOfAnimated;

class ImageDifferenceHash extends Model
{
    use HasFactory;
    protected $table = 'images_difference_hash';
    protected $fillable = ['image_id', 'd_hash'];
    const UPDATED_AT = null;

    /**
     * Add image difference hash
     * @param integer|\App\Models\Images $imageData
     * @return self|false
     */
    public static function storeImageDifferenceHash(int|Images $imageData): self|false
    {
        if (is_int($imageData)) {
            $imageData = Images::select(['id', 'image', 'isAnimated', 'imageType'])->where('id', $imageData)->first();
        }
        if ($imageData->isAnimated) {
            return self::create([
                'image_id' => $imageData->id,
                'd_hash' => generateImageDifferenceHashOfAnimated($imageData)
            ]);
        }
        return self::create([
            'image_id' => $imageData->id,
            'd_hash' => generateImageDifferenceHash($imageData)
        ]);
    }
}
