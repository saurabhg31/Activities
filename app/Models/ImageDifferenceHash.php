<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function App\Helpers\generateImageDifferenceHash;
use function App\Helpers\generateImageDifferenceHashOfAnimated;
use function App\Helpers\splitBinaryStringToFourInts;

class ImageDifferenceHash extends Model
{
    use HasFactory;
    protected $table = 'images_difference_hash';
    protected $fillable = ['image_id', 'hash_1', 'hash_2', 'hash_3', 'hash_4'];
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
            $hash = generateImageDifferenceHashOfAnimated($imageData);
            if (is_string($hash)) {
                $hashes = splitBinaryStringToFourInts($hash);
                unset($hash);
            } elseif (is_array($hash)) {
                $hashes = $hash;
            } else {
                throw new Exception('Invalid hash data type returned for animated image with ID: ' . $imageData->id);
            }
        } else {
            $hashes = splitBinaryStringToFourInts(generateImageDifferenceHash($imageData));
        }
        $data = array_merge(['image_id' => $imageData->id], $hashes);
        return self::create($data);
    }
}
