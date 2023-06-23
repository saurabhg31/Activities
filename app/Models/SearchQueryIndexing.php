<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;

class SearchQueryIndexing extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'image_type_id', 'tag_query', 'domain', 'times_searched'
    ];

    /**
     * Log image search request for search indexing
     * @param array $requestData
     * @param string $domain
     * @return boolean
     */
    public static function logSearchQuery(array &$requestData, string $domain): bool
    {
        if (empty($requestData['tags'])) {
            return false;
        }
        $data = Arr::only($requestData, ['types', 'tags']);
        if (!empty($data['types'])) {
            $data['image_type_id'] = ImageType::where('type', $data['types'])->first()->id;
        }
        $data['tag_query'] = $data['tags'];
        $data['domain'] = $domain;
        $data = Arr::only($data, (new self)->getFillable());
        $record = self::where(['tag_query' => $data['tag_query']])->first();
        if ($record) {
            $record->increment('times_searched');
            return true;
        }
        self::insert($data);
        return true;
    }

    /**
     * Get popular search prompts
     * @param integer $minimumSearchHits - The minimum number of times, it must be searched before appearing.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPopularSearchPrompts(int $minimumSearchHits = 3, string $domain = 'public')
    {
        return self::select('tag_query')
            ->where('domain', $domain)
            ->where('times_searched', '>=', $minimumSearchHits)
            ->distinct('tag_query')
            ->orderBy('tag_query')
            ->get();
    }
}
