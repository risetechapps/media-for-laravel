<?php

namespace RiseTechApps\Media\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RiseTechApps\Media\Traits\HasConversionsMedia\HasConversionsMedia;
use Spatie\MediaLibrary\HasMedia;

/**
 * Model host de teste. Usa chave UUID para casar com o uuidMorphs('model')
 * da tabela `media` (model_id é coluna uuid).
 */
class TestModel extends Model implements HasMedia
{
    use HasConversionsMedia;

    protected $table = 'test_models';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'name'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->id ??= (string) Str::uuid();
        });
    }
}
