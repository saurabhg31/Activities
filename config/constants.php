<?php

return [
    'DB_DATE_TIME' => 'Y-m-d H:i:s',
    'LANDSCAPE_ASPECT_RATIO_FLOAT_RANGE' => [1.7, 1.9],
    'PRIVATE_DASHBOARD_WALLPAPER_EXTENSIONS' => ['gif', 'webp', 'avif', 'png'],
    'ANIMATED_IMG_EXTENSIONS' => ['gif', 'webp', 'avif', 'apng', 'svg', 'fli', 'flc', 'ico', 'heic'],
    'ANIMATION_ONLY_TAG' => '#animationsOnly',
    'NO_TAGS_SEARCH_TAG' => '#noTags',
    'COMPRESSION_TAG' => '#compressedOnly',
    'MAX_IMG_SIZE' => 16 * 1024 * 1024, // 16 MB. Value is in bytes.
    'TMP_STORED_PREFIX' => '<fileStoredInTempDir>',
    'SQL_MAX_BIGINT_VAL' => '18446744073709551615',
    // image hamming distance threshold for duplicate image detection
    'DUPLICATE_IMG_SEARCH_THRESHOLD' => 7,
    'MAX_DAILY_CIGARETTE_GOAL' => 2,
    'CIGARETTE_TARGET_GOAL_DATE' => '2026-09-30', // target date in Y-m-d
];
