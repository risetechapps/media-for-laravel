# Laravel Media

[![Latest Version on Packagist](https://img.shields.io/packagist/v/risetechapps/media-for-laravel.svg?style=flat-square)](https://packagist.org/packages/risetechapps/media-for-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/risetechapps/media-for-laravel.svg?style=flat-square)](https://packagist.org/packages/risetechapps/media-for-laravel)
[![License](https://img.shields.io/github/license/risetechapps/media-for-laravel.svg?style=flat-square)](LICENSE)

## 📌 Sobre o Projeto
O **Media For Laravel** é um pacote que **estende as funcionalidades do [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary)**, simplificando o gerenciamento de uploads e arquivos temporários em aplicações Laravel.

---

## ✨ Funcionalidades
- 🏷 **Upload de arquivos**: Faça upload de arquivos facilmente, sem burocracia.
- 🏷 **Uploads temporários**: Evite sobrecarregar seu armazenamento com uploads descartáveis.
- 🏷 **Compatibilidade S3**: Totalmente compatível com qualquer serviço compatível com S3.
- 🏷 **Prune automático**: Uploads temporários expiram em 2 dias e arquivos marcados para exclusão são removidos após 180 dias.

---

## 🚀 Instalação

### 1️⃣ Requisitos
Certifique-se de que seu projeto atende aos seguintes requisitos:
- PHP >= 8.4
- Laravel >= 12
- Composer instalado

### 2️⃣ Instalação do pacote
```bash
composer require risetechapps/media-for-laravel
```

### 3️⃣ Configuração do Model
```php
use Spatie\MediaLibrary\HasMedia;
use RiseTechApps\Media\Traits\HasConversionsMedia\HasConversionsMedia;
use RiseTechApps\Media\Traits\HasPhotoProfile\HasPhotoProfile;

class Client extends Model implements HasMedia
{
    use HasFactory, HasUuid;
    use HasConversionsMedia, HasPhotoProfile;
}
```

### 💎 Alternativa: Trait Unificada (`HasMediaSuite`)

Para models que precisam de **todas** as funcionalidades de mídia (uploads gerais + foto de perfil), use a trait unificada:

```php
use Spatie\MediaLibrary\HasMedia;
use RiseTechApps\Media\Traits\HasMediaSuite\HasMediaSuite;

class User extends Model implements HasMedia
{
    use HasFactory, HasUuid;
    use HasMediaSuite; // Inclui HasConversionsMedia + HasPhotoProfile
}
```

> **Diferença:**
> - `HasConversionsMedia` → Uploads gerais, ícones, coleções
> - `HasPhotoProfile` → Apenas helpers para foto de perfil
> - **`HasMediaSuite`** → **Ambas juntas** (recomendado para User, Company, Employee)

### 4️⃣ Registro das rotas
```php
use Illuminate\Support\Facades\Route;

Media::routes();
```

### 5️⃣ Exemplo de uso no Controller
```php
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RiseTechApps\Media\Services\MediaUploadService;

class ClientController extends Controller
{
    protected MediaUploadService $mediaUploadService;

    public function __construct(MediaUploadService $mediaUploadService)
    {
        $this->mediaUploadService = $mediaUploadService;
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $uploads = $request->file('uploads');

            $client = Client::create($data);
            $this->mediaUploadService->handleUploadsJob($client, $uploads);

            return response()->json(['success' => true, 'message' => 'Cliente criado com sucesso!']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
```

---

## 📡 Rotas

### Upload de Arquivo
- **Endpoint:** `/upload`
- **Método:** `POST`

#### Exemplo de Requisição
```json
{
  "file": "example.txt",
  "collection": "uploads"
}
```

#### Exemplo de Resposta
```json
{
  "success": true,
  "data": {
    "id": "xxxxxxxxx",
    "name": "example",
    "type": "application/text",
    "size": 100,
    "preview": "https://preview/xxxxxx",
    "collection": "uploads"
  }
}
```

---

### Exemplo de uso no Resource
```php
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uploads' => $this->getUploads(),
            'photo' => $this->getPhotoProfile()?->jsonSerialize(),
        ];
    }
}
```

---

## 🎨 Customização Avançada

### Trait Personalizada (Adicionando Coleções/Conversões)

Quando você precisa adicionar coleções ou conversões extras **sem perder** as definições padrão das traits, use os métodos `additional*`. Agora fica muito mais simples:

```php
<?php

namespace App\Traits;

use RiseTechApps\Media\Traits\HasMediaSuite\HasMediaSuite;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasUserMedia
{
    use HasMediaSuite;

    // Usando additionalMediaCollections (muito mais limpo!)
    public function additionalMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf', 'application/msword']);
    }

    // Usando additionalMediaConversions (muito mais limpo!)
    public function additionalMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('avatar')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->format('png')
            ->queued();
    }
}
```

Uso no Model:

```php
<?php

namespace App\Models;

use App\Traits\HasUserMedia;
use Spatie\MediaLibrary\HasMedia;

class User extends Authenticatable implements HasMedia
{
    use HasUserMedia; // Só isso!
}
```

### ✅ Método Recomendado: `additionalMediaConversions()` e `additionalMediaCollections()`

A forma **mais limpa** de adicionar conversões/coleções sem copiar código:

```php
<?php

namespace App\Models;

use RiseTechApps\Media\Traits\HasMediaSuite\HasMediaSuite;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasMediaSuite;

    // Adiciona conversões extras (mantém 'thumb' da trait)
    public function additionalMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('large')
            ->width(1200)
            ->height(800)
            ->sharpen(10)
            ->format('png')
            ->queued();

        $this->addMediaConversion('small')
            ->width(100)
            ->height(100)
            ->queued();
    }

    // Adiciona coleções extras (mantém 'profile', 'icon_system', 'uploads' da trait)
    public function additionalMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->withResponsiveImages();

        $this->addMediaCollection('manual')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }
}
```

> ✅ **Vantagem:** Você só escreve o que é **adicional**, sem duplicar código!

---

### Sobrescrevendo no Model (quando precisa de controle total)

Se precisar **remover ou alterar** conversões/coleções padrão, sobrescreva completamente:

```php
<?php

namespace App\Models;

use RiseTechApps\Media\Traits\HasConversionsMedia\HasConversionsMedia;
use RiseTechApps\Media\Traits\HasPhotoProfile\HasPhotoProfile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasConversionsMedia, HasPhotoProfile;

    public function registerMediaCollections(): void
    {
        // Copie da trait
        $this->addMediaCollection('profile')
            ->withResponsiveImages()
            ->singleFile();
        $this->addMediaCollection('icon_system')
            ->withResponsiveImages()
            ->singleFile();
        $this->addMediaCollection('uploads')
            ->withResponsiveImages();

        // Adicione suas coleções
        $this->addMediaCollection('gallery')
            ->withResponsiveImages();
        $this->addMediaCollection('manual')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Copie da trait
        $this->addMediaConversion('thumb')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->pdfPageNumber(1)
            ->format('png')
            ->nonOptimized()
            ->queued();

        // Adicione suas conversões
        $this->addMediaConversion('large')
            ->width(1200)
            ->height(800)
            ->sharpen(10)
            ->format('png')
            ->queued();
    }
}
```

> ⚠️ **Importante:** `parent::registerMediaConversions()` não funciona com traits. > > **Prefira usar `additionalMediaConversions()` e `additionalMediaCollections()`** - não precisa copiar nada!

---

## ⚙️ Configurações Opcionais
Para habilitar **S3**, configure o seu `.env`:
```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=seu_id
AWS_SECRET_ACCESS_KEY=sua_chave
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=seu_bucket
```

Para controlar o prefixo e o disco utilizados pelo pacote, publique e ajuste no arquivo `config/media.php`:
```php
return [
    'disk' => [
        'name' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
        'prefix' => env('STORAGE_PREFIX', ''),
        'exclude' => [],
    ],
];
```

---

## 🛠 Contribuindo
1. Faça um fork do repositório
2. Crie uma branch (`feature/nova-funcionalidade`)
3. Commit suas alterações
4. Envie um Pull Request

---

## 📜 Licença
Distribuído sob a licença MIT. Veja [LICENSE](LICENSE) para mais detalhes.

---

💡 **Desenvolvido por [Rise Tech](https://risetech.com.br)**
