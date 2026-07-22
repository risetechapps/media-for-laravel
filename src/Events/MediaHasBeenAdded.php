<?php

namespace RiseTechApps\Media\Events;

use Illuminate\Queue\SerializesModels;
use RiseTechApps\Media\Models\Media;

/**
 * Disparado após o arquivo original ser gravado e registrado.
 *
 * É o gancho onde a geração de derivados (conversões e variantes responsivas)
 * se conecta, sem que o FileAdder precise conhecer o motor de conversão.
 */
class MediaHasBeenAdded
{
    use SerializesModels;

    public function __construct(public Media $media)
    {
    }
}
