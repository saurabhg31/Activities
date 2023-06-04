<?php

namespace App\Helpers;

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
