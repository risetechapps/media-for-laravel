<?php

namespace RiseTechApps\Media\Contracts;

use RiseTechApps\Media\Models\Media;

/**
 * Define onde cada arquivo de uma mídia vive no disco.
 *
 * Os caminhos devem terminar com barra e ser relativos ao root do disco.
 */
interface PathGeneratorContract
{
    /** Diretório do arquivo original. */
    public function getPath(Media $media): string;

    /** Diretório das conversões. */
    public function getPathForConversions(Media $media): string;

    /** Diretório das variantes responsivas. */
    public function getPathForResponsiveImages(Media $media): string;
}
