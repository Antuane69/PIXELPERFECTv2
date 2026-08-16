<?php

return [
    'images' => [
        'max_dimension' => (int) env('IMAGE_MAX_DIMENSION', 2400),
        'jpeg_quality' => (int) env('IMAGE_JPEG_QUALITY', 82),
        'png_compression' => (int) env('IMAGE_PNG_COMPRESSION', 6),
        'webp_quality' => (int) env('IMAGE_WEBP_QUALITY', 82),
    ],
];
