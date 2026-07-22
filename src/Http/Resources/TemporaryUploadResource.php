<?php

namespace RiseTechApps\Media\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RiseTechApps\Media\Models\Media;

/**
 * Resposta do endpoint de upload temporário.
 *
 * O `id` devolvido é o do dono provisório (MediaUploadTemporary), não o da
 * mídia: é esse valor que o cliente guarda e reenvia ao salvar o formulário,
 * para que o arquivo seja movido ao model definitivo.
 *
 * @property Media $resource
 */
class TemporaryUploadResource extends JsonResource
{
    public function __construct($resource, protected ?string $name = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->model_id,
            'name' => $this->name ?? $this->resource->name,
            'type' => $this->resource->mime_type,
            'size' => $this->resource->size,
            'preview' => $this->resource->getFullUrl(),
            'collection' => $this->resource->collection_name,
        ];
    }
}
