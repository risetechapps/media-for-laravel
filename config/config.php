<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'disk' => [
        'name' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
        'prefix' => env('STORAGE_PREFIX', ''),
        'exclude' => [

        ]
    ]
];
