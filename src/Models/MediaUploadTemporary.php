<?php

namespace RiseTechApps\Media\Models;

use Illuminate\Database\Eloquent\Model;
use RiseTechApps\HasUuid\Traits\HasUuid\HasUuid;
use RiseTechApps\Media\Traits\HasConversionsMedia\HasConversionsMedia;
use Spatie\MediaLibrary\HasMedia;

class MediaUploadTemporary extends Model implements HasMedia
{
    use HasConversionsMedia, HasUuid;
}
