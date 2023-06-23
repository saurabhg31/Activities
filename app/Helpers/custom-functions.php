<?php

namespace App\Helpers;

use App\Models\ImageIndex;
use App\Models\SearchQueryIndexing;
use Illuminate\Support\Facades\Cache;

if (!function_exists('translate')) {
    /**
     * Translate function to compensate for octane config caching issue
     * @param string $key
     * @param array $replace
     * @return \Illuminate\Contracts\Translation\Translator|string|array|null
     */
    function translate(string $key, array $replace = [])
    {
        $locale = request()->header('Accept-Language');
        if (!$locale) {
            $locale = app()->getLocale();
        }
        return trans($key, $replace, $locale);
    }
}

if (!function_exists('getAvailableLocales')) {
    /**
     * Get available locales
     * @return array
     */
    function getAvailableLocales()
    {
        return array_filter(scandir(base_path('lang')), function ($item) {
            return !in_array($item, ['.', '..']);
        });
    }
}

if (!function_exists('cacheImageSearchPrompts')) {
    /**
     * Function to cache image searches
     * @param string $domain
     * @return boolean
     */
    function cacheImageSearchPrompts(string $domain, int $userId = null)
    {
        $cacheName = "image_tags_{$domain}";
        if ($userId) {
            $cacheName .= "_{$userId}";
        }
        $popularSearches = SearchQueryIndexing::getPopularSearchPrompts(3, $domain)->pluck('tag_query');
        return Cache::put($cacheName, ImageIndex::getImageTags($userId, $domain)->merge($popularSearches)->sort(function ($prompt1, $prompt2) {
            return $prompt1 > $prompt2 ? 1 : 0;
        })->unique());
    }
}
