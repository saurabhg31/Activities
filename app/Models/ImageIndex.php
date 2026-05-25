<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

use function App\Helpers\cacheImageSearchPrompts;

class ImageIndex extends Model
{
    use HasFactory;
    protected $table = 'image_search_indexing';
    protected $fillable = [
        'tag',
        'image_id'
    ];
    const UPDATED_AT = null;

    /**
     * Add image indices
     * @param integer $imageId
     * @param string $tags
     * @return integer - number or indices (rows) created
     */
    public static function addIndices(int $imageId, string $tags)
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
        return $insertedIndexCount;
    }

    /**
     * Update image indices
     * @param integer $imageId
     * @param string $tags
     * @return true
     */
    public static function updateIndices(int $imageId, string $tags)
    {
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
        self::where('image_id', $imageId)->whereNotIn('tag', array_column($imageIndexData, 'tag'))->delete();
        if (!empty($imageIndexData)) {
            foreach ($imageIndexData as $index) {
                self::where($index)->firstOrNew()->fill($index)->save();
            }
        }
        return true;
    }

    /**
     * Get image tags
     * @param integer $userId
     * @return \Illuminate\Support\Collection
     */
    public static function getImageTags(?int $userId = null, ?string $domain = null)
    {
        if (auth()->check()) {
            $userId = auth('web')->id();
        }
        $query = self::selectRaw('distinct(tag)')->join('images', 'image_search_indexing.image_id', '=', 'images.id');
        if (
            (Session::has('domain') && Session::get('domain') === 'private' || $userId) &&
            ($domain && $domain === 'private')
        ) {
            $query->where('images.user_id', $userId);
        } else {
            $query->whereNull('images.user_id');
        }
        return $query->get()->pluck('tag');
    }

    /**
     * Get cached image tags
     * @return \Illuminate\Support\Collection
     */
    public static function getCachedImageTags(string $domain = 'public', ?int $userId = null)
    {
        if (!$userId && auth('web')->check()) {
            $userId = auth('web')->id();
        }
        $cacheName = "image_tags_{$domain}_" . $userId;
        if (!Cache::has($cacheName)) {
            cacheImageSearchPrompts($domain, $userId);
        }
        $tags = Cache::get($cacheName)->toArray();
        $extensions = Images::select('imageType')->distinct()->pluck('imageType')->toArray();
        return array_merge($tags, $extensions, [
            config('constants.ANIMATION_ONLY_TAG'),
            config('constants.NO_TAGS_SEARCH_TAG'),
            config('constants.COMPRESSION_TAG')
        ]);
    }
}
