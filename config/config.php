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
    | Gerador de caminhos
    |--------------------------------------------------------------------------
    |
    | Define o layout dos arquivos em disco. Substitua por uma implementação
    | de PathGeneratorContract para usar outro esquema de diretórios.
    |
    */
    'path_generator' => RiseTechApps\Media\Support\PathGenerator\DefaultPathGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Gerador de URLs
    |--------------------------------------------------------------------------
    |
    | Resolve a URL de exibição de cada arquivo. Substitua por uma implementação
    | de UrlGeneratorContract para servir via CDN em vez de assinar no S3.
    |
    */
    'url_generator' => RiseTechApps\Media\Support\Urls\DefaultUrlGenerator::class,

    'url' => [
        // Discos S3: por quanto tempo a URL assinada fica em cache (min) e por
        // quanto a assinatura em si é válida (min). O cache deve ser menor que
        // a validade, senão serve assinatura já expirada.
        'signed_cache_minutes' => env('MEDIA_URL_SIGNED_CACHE_MINUTES', 55),
        'signed_ttl_minutes' => env('MEDIA_URL_SIGNED_TTL_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN
    |--------------------------------------------------------------------------
    |
    | Com `base` preenchido, getFullUrl() passa a servir a URL pública do CDN em
    | vez de assinar no S3 — sem trocar o url_generator. Vazio = desligado.
    |
    | include_disk_root define a montagem da chave conforme para onde o CDN
    | aponta:
    |   true  → raiz do bucket: chave = root do disco + path da mídia.
    |   false → raiz do disco:  chave = só o path da mídia.
    |
    */
    'cdn' => [
        'base' => env('MEDIA_URL_GENERATOR_CDN_BASE'),
        'include_disk_root' => env('MEDIA_CDN_INCLUDE_DISK_ROOT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversões
    |--------------------------------------------------------------------------
    |
    | Geradores consultados em ordem para produzir a imagem base de cada tipo
    | de arquivo. O primeiro que aceitar o tipo é usado, então o gerador de
    | ícones deve permanecer por último — ele aceita qualquer coisa e serve de
    | último recurso.
    |
    */
    'conversions' => [
        'generators' => [
            RiseTechApps\Media\Support\Conversions\Generators\ImageFileGenerator::class,
            RiseTechApps\Media\Support\Conversions\Generators\PdfGenerator::class,
            RiseTechApps\Media\Support\Conversions\Generators\VideoGenerator::class,
            RiseTechApps\Media\Support\Conversions\Generators\FileIconGenerator::class,
        ],

        // Segundo do vídeo usado para extrair o quadro da miniatura.
        'video_frame_second' => env('MEDIA_VIDEO_FRAME_SECOND', 1),

        // Caminhos dos binários, quando não estiverem no PATH.
        'ffmpeg' => [
            // 'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
            // 'ffprobe.binaries' => '/usr/bin/ffprobe',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Imagens responsivas (srcset)
    |--------------------------------------------------------------------------
    |
    | Gera versões reduzidas da imagem original em várias larguras, para o front
    | montar srcset e o navegador baixar só o tamanho que precisa.
    |
    | DESLIGADO por padrão: cada largura é outro arquivo ocupando (e custando)
    | storage. Só compensa quando o front de fato consome srcset e o tráfego
    | justifica trocar armazenamento por economia de banda. Mesmo ligado aqui,
    | a coleção ainda precisa optar via withResponsiveImages().
    |
    */
    'responsive_images' => [
        'enabled' => env('MEDIA_RESPONSIVE_IMAGES', false),

        // Larguras alvo (px). Só as menores que a original são geradas — nunca
        // amplia. Ordenadas da maior para a menor na montagem do srcset.
        'widths' => [1920, 1440, 1024, 768, 480, 320],
    ],

    /*
    |--------------------------------------------------------------------------
    | Download remoto
    |--------------------------------------------------------------------------
    |
    | Tempo limite (segundos) ao anexar mídia a partir de uma URL.
    |
    */
    'download' => [
        'timeout' => env('MEDIA_DOWNLOAD_TIMEOUT', 30),
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
    | Escopo (tenancy desacoplado)
    |--------------------------------------------------------------------------
    |
    | Resolver de contexto para particionar a mídia sem o package conhecer nada
    | de tenancy. Implemente MediaScopeResolver: devolve um mapa chave→valor
    | (ex.: ['sub_tenant_id' => 42]) que é carimbado em custom_properties._scope
    | na criação e filtrado em toda consulta (global scope fail-closed).
    |
    | null = desligado: o package roda sem particionar nada.
    |
    */
    'scope' => [
        'resolver' => null, // ex.: App\Media\TenancyMediaScope::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Cota de storage
    |--------------------------------------------------------------------------
    |
    | Duas formas de definir o limite, nesta prioridade:
    |
    |   1. resolver — dinâmico, por contexto. Implemente QuotaResolver; devolve
    |      o limite em bytes (ou null p/ ilimitado). Vence sempre quando setado.
    |   2. default  — limite fixo em bytes, igual para todos. Usado quando não
    |      há resolver. null = ilimitado.
    |
    | O upload é barrado antes de gravar quando uso + tamanho do arquivo
    | ultrapassaria o limite.
    |
    */
    'quota' => [
        'resolver' => null, // ex.: App\Media\PlanQuota::class

        // Limite fixo (fallback do resolver). Aceita bytes (5368709120) ou
        // string legível ('5GB', '500 MB'). null = ilimitado.
        'default' => env('MEDIA_QUOTA_DEFAULT'),
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

    /*
    |--------------------------------------------------------------------------
    | Limpeza automática (prune)
    |--------------------------------------------------------------------------
    |
    | Agenda o model:prune diário para os models do package: uploads temporários
    | abandonados e mídia na lixeira além do prazo de `expiration`. Como são
    | models de package, o model:prune não os descobre sozinho — por isso o
    | agendamento é registrado aqui, com as classes explícitas.
    |
    | Requer o cron do Laravel ativo (`php artisan schedule:run`). Desligue para
    | agendar por conta própria.
    |
    */
    'prune' => [
        'enabled' => env('MEDIA_PRUNE_ENABLED', true),

        // Horário do prune diário (formato HH:MM).
        'time' => env('MEDIA_PRUNE_TIME', '02:00'),
    ],
];
