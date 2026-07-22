<?php

namespace RiseTechApps\Media\Events;

use Illuminate\Queue\SerializesModels;
use RiseTechApps\Media\Models\Media;
use RiseTechApps\Media\Support\Conversions\Conversion;

/**
 * Disparado após o arquivo derivado ser gravado e contabilizado.
 */
class ConversionHasBeenCompleted
{
    use SerializesModels;

    public function __construct(
        public Media $media,
        public Conversion $conversion,
    ) {
    }
}
