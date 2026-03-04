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
