<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class ImageIndex extends Model
{
    use HasFactory;
    protected $table = 'image_search_indexing';
    protected $fillable = [
        'tag', 'image_id'
    ];

    /**
     * Add image indices
     * @param integer $imageId
     * @param string $tags
     * @return integer - number or indices (rows) created
     */
    protected static function addIndices(int &$imageId, string &$tags)
    {
        $insertedIndexCount = 0;
        $imageIndexData = [];
        foreach (explode(',', $tags) as $tag) {
            $tag = trim(str_replace('#', '', $tag));
            if ($tag) {
                array_push($imageIndexData, [
                    'tag' => $tag,
                    'image_id' => $imageId
                ]);
            }
        }
        if (!empty($imageIndexData)) {
            $insertedIndexCount = self::insertOrIgnore($imageIndexData);
        }
        Cache::put('image_tags', self::getImageTags());
        return $insertedIndexCount;
    }

    /**
     * Get image tags
     * @return \Illuminate\Support\Collection
     */
    protected static function getImageTags()
    {
        $query = self::selectRaw('distinct(tag)')->join('images', 'image_search_indexing.image_id', '=', 'images.id');
        if (Session::has('domain') && Session::get('domain') == 'private') {
            $query->where('images.user_id', auth('web')->id());
        } else {
            $query->whereNull('images.user_id');
        }
        return $query->get()->pluck('tag');
    }

    /**
     * Get cached image tags
     * @return \Illuminate\Support\Collection
     */
    protected static function getCachedImageTags()
    {
        if (!Cache::has('image_tags')) {
            Cache::put('image_tags', self::getImageTags());
        }
        return Cache::get('image_tags');
    }
}
