<?php

namespace RiseTechApps\Media\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de resposta do endpoint /uploads (upload temporário).
 *
 * O `id` é o model_id do MediaUploadTemporary (UUID) — é o que o frontend guarda
 * e reenvia depois para o attach no model final. `name` e `collection` podem ser
 * informados explicitamente (nome original do arquivo / coleção do request);
 * na ausência, caem para os valores da própria mídia.
 */
class TemporaryUploadResource extends JsonResource
{
    public function __construct($resource, protected ?string $name = null, protected ?string $collection = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->resource->model_id,
            'name'       => $this->name ?? $this->resource->name,
            'type'       => $this->resource->mime_type,
            'size'       => $this->resource->size,
            'preview'    => $this->resource->getFullUrlTemporaryUpload(),
            'collection' => $this->collection ?? $this->resource->collection_name,
        ];
    }
}
