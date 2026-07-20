<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'disk' => [
        'name' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
        'prefix' => env('STORAGE_PREFIX', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload
    |--------------------------------------------------------------------------
    |
    | Tamanho máximo (em KB) aceito pelo endpoint de upload. O tipo de arquivo
    | permitido é controlado por coleção via acceptsMimeTypes() no model.
    |
    */
    'upload' => [
        'max_size' => env('MEDIA_UPLOAD_MAX_SIZE', 51200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tempo de expiração
    |--------------------------------------------------------------------------
    |
    | Define o número de dias para expiração de uploads temporários e
    | arquivos de mídia excluídos (soft delete).
    |
    */
    'expiration' => [
        'temporary_uploads' => env('MEDIA_TEMPORARY_UPLOADS_EXPIRATION_DAYS', 2),
        'soft_deleted' => env('MEDIA_SOFT_DELETED_EXPIRATION_DAYS', 180),
    ],
];
