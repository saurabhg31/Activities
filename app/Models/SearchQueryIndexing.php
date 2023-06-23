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
        'image_type_id', 'tag_query', 'domain'
    ];

    /**
     * Log image search request for search indexing
     * @param array $requestData
     * @param string $domain
     * @return boolean
     */
    public static function logSearchQuery(array &$requestData, string $domain): bool
    {
        $data = Arr::only($requestData, ['types', 'tags']);
        if (!empty($data['types'])) {
            $data['image_type_id'] = ImageType::where('type', $data['types'])->first()->id;
        }
        $data['tag_query'] = $data['tags'];
        $data['domain'] = $domain;
        self::insert(Arr::only($data, (new self)->getFillable()));
        return true;
    }

    /**
     * Get popular search prompts
     * @param integer $minimumSearchHits - The minimum number of times, it must be searched before appearing.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPopularSearchPrompts(int $minimumSearchHits = 3)
    {
        $domain = Session::get('domain') ?? 'public';
        return self::select('tag_query')->where('domain', $domain)->distinct('tag_query')->orderBy('tag_query')->get();
    }
}
