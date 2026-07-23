# Laravel Media

[![Latest Version on Packagist](https://img.shields.io/packagist/v/risetechapps/media-for-laravel.svg?style=flat-square)](https://packagist.org/packages/risetechapps/media-for-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/risetechapps/media-for-laravel.svg?style=flat-square)](https://packagist.org/packages/risetechapps/media-for-laravel)
[![License](https://img.shields.io/github/license/risetechapps/media-for-laravel.svg?style=flat-square)](LICENSE)

## 📌 Sobre

Gerenciamento de mídia para Laravel com **contabilidade exata de bytes**.

Diferente de bibliotecas que só registram o tamanho do arquivo original, este pacote registra **cada arquivo físico** — original, conversões e variantes responsivas — numa tabela `media_files`, e mantém em `media.total_size` a soma real ocupada em disco. É isso que torna possível reportar e cobrar storage com precisão.

Traz ainda dois recursos montados sobre essa contabilidade:

- **Escopo por contexto** (multi-tenant desacoplado) — particiona a mídia por um contexto que o pacote **não precisa conhecer** (sem coluna `tenant_id`, sem depender de nenhum pacote de tenancy).
- **Cota de storage** — barra o upload antes de gravar quando o contexto estouraria o limite.

---

## ✨ Funcionalidades

- 📤 **Upload** direto ou por request/disco/URL, com validação por coleção.
- 🗂 **Coleções** com regras (arquivo único, mime types, fallback).
- 🖼 **Conversões** (miniaturas) para imagem, PDF, vídeo e ícone por tipo de arquivo — enfileiráveis.
- 📱 **Imagens responsivas** (`srcset`), desligáveis por config.
- 🔗 **URL trocável** (CDN) com cache de URL assinada S3.
- 📊 **Relatórios de storage** por dono, coleção e total.
- 🏢 **Escopo por contexto** (tenancy desacoplado) com filtro *fail-closed*.
- 🚦 **Cota de storage** por contexto ou fixa.
- ♻️ **Uploads temporários** e **prune** automático.
- ☁️ **Compatível com S3** e serviços compatíveis (iDrive e2, etc).

---

## 🚀 Instalação

### Requisitos

- PHP >= 8.4
- Laravel >= 12
- **Imagick** (com Ghostscript p/ PDF; com libheif p/ HEIC) — opcional, cai em ícone sem ele
- **ffmpeg + ffprobe** no PATH — opcional, p/ miniatura de vídeo
- Binários otimizadores (`jpegoptim`, `optipng`, `pngquant`, `gifsicle`, `cwebp`) — opcional, p/ `optimize()`

### Pacote

```bash
composer require risetechapps/media-for-laravel
php artisan migrate
```

Publicar a config (opcional):

```bash
php artisan vendor:publish --tag=config
```

---

## 🧩 Configuração do Model

Implemente o contrato e use a trait:

```php
use Illuminate\Database\Eloquent\Model;
use RiseTechApps\Media\Contracts\MediaContract;
use RiseTechApps\Media\Traits\InteractsWithMedia\InteractsWithMedia;
use RiseTechApps\Media\Support\Collections\MediaCollection;
use RiseTechApps\Media\Support\Conversions\Conversion;
use Spatie\Image\Enums\Fit;

class Client extends Model implements MediaContract
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl('/img/sem-foto.png');

        $this->addMediaCollection('documentos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 368, 232)
            ->format('webp')
            ->quality(80)
            ->optimize()
            ->orientation()
            ->queued();
    }
}
```

### Atalho: `HasMediaSuite`

Para o caso comum (uma coleção padrão + uma conversão `thumb`), a trait `HasMediaSuite` já traz tudo pronto — sem repetir o boilerplate:

```php
use RiseTechApps\Media\Contracts\MediaContract;
use RiseTechApps\Media\Traits\HasMediaSuite\HasMediaSuite;

class Client extends Model implements MediaContract
{
    use HasMediaSuite;
}
```

Os defaults saem de `config('media.defaults')` (coleção `uploads`, thumb 368×232 webp q80, `orientation()` + `optimize()`, enfileirada). Sem prender você:

```php
class Client extends Model implements MediaContract
{
    use HasMediaSuite;

    // Adiciona sem perder os defaults:
    protected function additionalMediaCollections(): void
    {
        $this->addMediaCollection('documentos')->singleFile();
    }

    protected function additionalMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('preview')->width(1024)->queued();
    }

    // Ou ajusta um default pontual:
    protected function defaultConversionFormat(): string { return 'png'; }
}
```

> Sem worker? Defina `MEDIA_DEFAULT_CONVERSION_QUEUED=false` (ou sobrescreva `defaultConversionQueued()`) — a conversão roda no próprio request. Para trocar tudo, sobrescreva `registerMediaCollections()`/`registerMediaConversions()` normalmente.

---

## 📤 Adicionando mídia

```php
// De um UploadedFile, caminho local ou RemoteFile
$client->addMedia($request->file('foto'))->toMediaCollection('avatar');

// Direto do request
$client->addMediaFromRequest('foto')->toMediaCollection('avatar');

// De um disco já existente
$client->addMediaFromDisk('caminho/arquivo.pdf', 's3')->toMediaCollection('documentos');

// De uma URL (streaming; veja aviso de SSRF no docblock)
$client->addMediaFromUrl('https://exemplo.com/foto.jpg')->toMediaCollection('avatar');
```

O `FileAdder` é fluente:

```php
$client->addMedia($file)
    ->usingName('Contrato 2026')
    ->usingFileName('contrato.pdf')
    ->withCustomProperties(['origem' => 'importacao'])
    ->withProperty('lote', 42)
    ->preservingOriginal()                 // não remove a origem
    ->storingConversionsOnDisk('s3')
    ->withResponsiveImages()
    ->toMediaCollection('documentos', 's3'); // (coleção, disco)
```

---

## 📥 Lendo mídia

```php
$client->getMedia('documentos');                    // Collection<Media>
$client->getFirstMedia('avatar');                   // ?Media
$client->hasMedia('avatar');                        // bool
$client->getFirstMediaUrl('avatar', 'thumb');       // string (fallback se vazio)
$client->getFirstMediaPath('avatar');               // ?string

$media = $client->getFirstMedia('avatar');
$media->getUrl('thumb');            // URL crua do disco
$media->getFullUrl('thumb');        // URL de exibição (S3 assinada + cache)
$media->getTemporaryUrl(now()->addHour(), 'thumb');
$media->total_size;                 // bytes reais (original + derivados)
```

Removendo:

```php
$client->clearMediaCollection('documentos');
$client->clearMediaCollectionExcept('documentos', $mediaParaManter);
$client->deleteAllMedia();
```

---

## 🖼 Conversões

Definidas em `registerMediaConversions()` via `addMediaConversion()`:

| Método | Efeito |
|---|---|
| `width(int)` / `height(int)` | Dimensão alvo |
| `fit(Fit, w, h)` | Modo de encaixe (`Crop`, `Contain`, `Max`, …) |
| `format(string)` | `webp`, `png`, `jpg`, … |
| `quality(int)` | Qualidade 0–100 |
| `sharpen(float)` | Nitidez |
| `background(string)` | Cor de folga (vazio = transparente) |
| `pdfPageNumber(int)` | Página do PDF |
| `optimize()` | Passa por otimizadores (corta bytes) |
| `orientation()` | Corrige EXIF (foto deitada) |
| `queued()` / `nonQueued()` | Fila ou na hora |
| `performOnCollections(...)` | Restringe a coleções |

Geradores consultados em ordem (config `media.conversions.generators`): **imagem → PDF → vídeo → ícone**. O ícone aceita qualquer coisa e é o último recurso, garantindo miniatura para qualquer tipo.

---

## 📱 Imagens responsivas (srcset)

**Desligado por padrão.** Cada largura é outro arquivo ocupando storage.

```env
MEDIA_RESPONSIVE_IMAGES=true
```

```php
// Opt-in por coleção
$this->addMediaCollection('fotos')->withResponsiveImages();
```

Precisa das **duas** chaves (config global + opt-in na coleção). Gera larguras menores que a original (nunca amplia), cada uma contabilizada.

```php
$media->getSrcset();       // "url-1024.jpg 1024w, url-768.jpg 768w, ..."
$media->getSrcsetArray();  // [['width'=>1024,'url'=>'...'], ...]
$media->hasResponsiveImages();
```

```blade
<img src="{{ $media->getFullUrl() }}"
     srcset="{{ $media->getSrcset() }}"
     sizes="(max-width: 768px) 100vw, 768px">
```

---

## 🔗 URL de exibição, CDN e endpoint

`getFullUrl()` é a URL que o front consome. O comportamento depende da config:

### Padrão — disco (S3 assinado + cache)
Sem CDN, discos S3 assinam a URL e a reaproveitam por alguns minutos (`media.url.signed_cache_minutes`). É a entrega segura para **conteúdo privado**: a URL expira, ninguém acessa sem ela.

O domínio do bucket vem do próprio driver S3 — para usar um endpoint/domínio branded, configure o disco (`AWS_ENDPOINT`), não o CDN:

```env
AWS_ENDPOINT=https://s3.seu-dominio.com   # URLs assinadas saem nesse host
```

### CDN público (built-in)
Com `media.cdn.base` preenchido, `getFullUrl()` passa a montar a **URL pública do CDN** (sem assinatura), sem trocar de gerador:

```env
MEDIA_URL_GENERATOR_CDN_BASE=https://cdn.seu-dominio.com
# Chave do objeto conforme para onde o CDN aponta:
#  true  = raiz do bucket  → chave = root do disco + path
#  false = raiz do disco   → chave = só o path
MEDIA_CDN_INCLUDE_DISK_ROOT=true
```

> ⚠️ **CDN serve URL pública, sem assinatura.** Só use para conteúdo que **pode** ser público (catálogo, banner). Para mídia privada de tenant, deixe `cdn.base` vazio e sirva assinado. Apontar `cdn.base` para o endpoint S3 privado gera URL que **não abre** (bucket exige assinatura). CDN de conteúdo privado exige assinatura na borda (CloudFront/Cloudflare com signed URL) — nesse caso, implemente um `UrlGeneratorContract` próprio que assina.

### Gerador próprio
Para lógica de URL totalmente custom, implemente `UrlGeneratorContract` e aponte em `config('media.url_generator')`:

```php
'url_generator' => App\Media\CdnUrlGenerator::class,
```

---

## 📊 Relatórios de storage

Contabilidade **global** (ignora o escopo/partição). Via facade `Media`:

```php
use RiseTechApps\Media\MediaFacade as Media;

Media::storage()->total();               // bytes de tudo
Media::storage()->byCollection();        // ['avatar' => 1234, ...]
Media::storage()->byModelType();         // ['App\Models\Client' => ...]
Media::storage()->forModel($client);     // bytes de um dono
Media::storage()->forModel($client, 'avatar');
Media::storage()->forModelByCollection($client);
Media::storage()->forModelType(Client::class);
Media::storage()->humanize(1536);        // "1.5 KB"
```

Atalhos no model:

```php
$client->mediaStorageUsage();            // total do dono
$client->mediaStorageUsage('avatar');    // só a coleção
$client->mediaStorageByCollection();     // quebrado por coleção
```

Por padrão inclui mídia na lixeira (ainda ocupa disco). Passe `false` para só ativo:

```php
Media::storage()->total(false);
```

### Formatação com `Size`

```php
use RiseTechApps\Media\Support\Reports\Size;

Size::of($bytes)->gb();          // 2.34
Size::of($bytes)->mb(1);         // 2396.4  (1 casa)
Size::of($bytes)->forHumans();   // "2.3 GB" (unidade automática)
(string) Size::of($bytes);       // "2.3 GB"

Size::parse('10GB');             // 10737418240
Size::parse('500 MB');           // 524288000
```

---

## 🏢 Escopo por contexto (tenancy desacoplado)

Particiona a mídia por um contexto que **o pacote não conhece**. Sem coluna `tenant_id`: o contexto é carimbado em `custom_properties._scope` na criação e filtrado em toda consulta por um *global scope* **fail-closed**.

### Registrando o resolver

Implemente `MediaScopeResolver` (o consumidor sabe o que é o contexto):

```php
use RiseTechApps\Media\Contracts\MediaScopeResolver;

class TenancyMediaScope implements MediaScopeResolver
{
    public function resolve(): array
    {
        return SubTenant::current()
            ? ['sub_tenant_id' => SubTenant::current()->id]
            : [];   // [] = sem contexto (fail-closed)
    }
}
```

```php
// config/media.php
'scope' => ['resolver' => App\Media\TenancyMediaScope::class],

// ou em runtime:
Media::resolveScopeUsing(fn () => ['sub_tenant_id' => 42]);
```

### Comportamento

- **Com contexto** → só a mídia carimbada com aquele contexto.
- **Sem contexto** (`resolve()` vazio) → **fail-closed**: só mídia sem escopo. Nunca vaza mídia de outro contexto.
- **Sem resolver** → o pacote roda sem particionar nada.

Ignorar a partição (admin/relatório):

```php
use RiseTechApps\Media\Models\Media as MediaModel;

MediaModel::unscoped()->get();
```

> ⚠️ **Segurança.** Mantenha o **tipo** consistente no resolver: `sub_tenant_id => 42` (int) carimba número; consultar com `'42'` (string) **não casa** no `jsonb`. E `unscoped()` fura a partição de propósito — use só em contexto administrativo.

---

## 🚦 Cota de storage

Barra o upload **antes de gravar** quando `uso + tamanho > limite`. O uso é o total do contexto atual (mesmo escopo acima). Duas formas de definir o limite, nesta prioridade:

**1. Resolver** (dinâmico, por contexto) — vence sempre:

```php
use RiseTechApps\Media\Contracts\QuotaResolver;

class PlanQuota implements QuotaResolver
{
    public function limitInBytes(): ?int
    {
        return SubTenant::current()?->plan->storage_bytes; // null = ilimitado
    }
}
```

```php
'quota' => ['resolver' => App\Media\PlanQuota::class],
```

**2. Fixo** (config/env) — usado quando não há resolver. Aceita bytes ou string legível:

```env
MEDIA_QUOTA_DEFAULT=10GB
```

```php
'quota' => ['default' => '10GB'], // ou 10737418240; null = ilimitado
```

### Consultando

```php
Media::quota()->usage();              // bytes usados no contexto
Media::quota()->limit();              // ?int
Media::quota()->remaining();          // ?int
Media::quota()->exceeded();           // bool
Media::quota()->percentUsed();        // float (pode passar de 100)
Media::quota()->percentUsed(clamp: true); // teto 100 p/ barra de progresso
Media::quota()->usageSize()->gb();
Media::quota()->remainingSize()?->forHumans() ?? 'ilimitado';
```

### Tratando o estouro

```php
use RiseTechApps\Media\Exceptions\StorageQuotaExceeded;

try {
    $client->addMedia($file)->toMediaCollection('documentos');
} catch (StorageQuotaExceeded $e) {
    // $e->limit, $e->usage, $e->attempted (bytes)
    return response()->json(['erro' => 'Cota de armazenamento excedida'], 413);
}
```

> A cota é checada no **original**. Conversões e variantes responsivas entram depois (async) e não falham retroativamente — são o custo do original aceito.

---

## ♻️ Uploads temporários

Registre a rota (middleware/auth por sua conta via `$options`):

```php
use RiseTechApps\Media\MediaFacade as Media;

Media::routes(['middleware' => ['auth:sanctum']]);
// expõe POST /uploads → cria um upload temporário e devolve o recurso
```

Depois vincule ao model definitivo:

```php
Media::syncUploads($client, $uploadIds, 'documentos');    // em fila
Media::syncUploadsNow($client, $uploadIds, 'documentos'); // imediato
```

Uploads temporários expiram em **2 dias**; mídia em lixeira é removida após **180 dias** (config `media.expiration`). A limpeza remove os registros **e os arquivos em disco**.

### Agendamento (prune)

O package agenda um `model:prune` diário para os seus models (`Media` e `MediaUploadTemporary`) — como são models de package, o `model:prune` não os descobre sozinho, então as classes são passadas explicitamente.

```php
// config/media.php
'prune' => [
    'enabled' => true,     // MEDIA_PRUNE_ENABLED
    'time'    => '02:00',  // MEDIA_PRUNE_TIME
],
```

Requer o cron do Laravel ativo:

```cron
* * * * * cd /caminho && php artisan schedule:run >> /dev/null 2>&1
```

### ⚠️ Prune em multi-tenant

O agendamento do package roda na conexão **central**. Em setups **database-per-tenant**, os registros vivem no banco de cada tenant — o `model:prune` na central **não os alcança**.

Nesse caso, **desligue o agendamento do package** e rode o prune **dentro do contexto de cada tenant**, pelo mecanismo do seu pacote de tenancy:

```env
MEDIA_PRUNE_ENABLED=false
```

```php
// exemplo — dentro do loop por tenant do seu tenancy
Artisan::call('model:prune', ['--model' => [
    \RiseTechApps\Media\Models\Media::class,
    \RiseTechApps\Media\Models\MediaUploadTemporary::class,
]]);
```

---

## ⚙️ Referência de configuração

| Chave | Env | Padrão | Descrição |
|---|---|---|---|
| `disk.name` | `MEDIA_DISK` | `local` | Disco de armazenamento |
| `disk.prefix` | `STORAGE_PREFIX` | `''` | Prefixo de root (disco isolado) |
| `path_generator` | — | `DefaultPathGenerator` | Layout dos arquivos |
| `url_generator` | — | `DefaultUrlGenerator` | Resolução de URL (trocável p/ CDN) |
| `url.signed_cache_minutes` | `MEDIA_URL_SIGNED_CACHE_MINUTES` | `55` | Cache da URL assinada S3 |
| `url.signed_ttl_minutes` | `MEDIA_URL_SIGNED_TTL_MINUTES` | `60` | Validade da assinatura |
| `cdn.base` | `MEDIA_URL_GENERATOR_CDN_BASE` | `null` | Host do CDN público; vazio = serve do disco |
| `cdn.include_disk_root` | `MEDIA_CDN_INCLUDE_DISK_ROOT` | `true` | Inclui o root do disco na chave do CDN |
| `conversions.generators` | — | Image, Pdf, Video, FileIcon | Cadeia de geradores |
| `conversions.video_frame_second` | `MEDIA_VIDEO_FRAME_SECOND` | `1` | Segundo do quadro do vídeo |
| `defaults.collection` | `MEDIA_DEFAULT_COLLECTION` | `uploads` | Coleção padrão do `HasMediaSuite` |
| `defaults.conversion.*` | `MEDIA_DEFAULT_CONVERSION_*` | thumb 368×232 webp q80 | Conversão padrão do `HasMediaSuite` |
| `defaults.conversion.queued` | `MEDIA_DEFAULT_CONVERSION_QUEUED` | `true` | `false` roda a conversão no request |
| `responsive_images.enabled` | `MEDIA_RESPONSIVE_IMAGES` | `false` | Master switch do srcset |
| `responsive_images.widths` | — | `[1920…320]` | Larguras alvo |
| `scope.resolver` | — | `null` | `MediaScopeResolver` (tenancy) |
| `quota.resolver` | — | `null` | `QuotaResolver` |
| `quota.default` | `MEDIA_QUOTA_DEFAULT` | `null` | Limite fixo (bytes ou `'10GB'`) |
| `upload.max_size` | `MEDIA_UPLOAD_MAX_SIZE` | `51200` | KB máx. no endpoint |
| `download.timeout` | `MEDIA_DOWNLOAD_TIMEOUT` | `30` | Timeout de `addMediaFromUrl` |
| `expiration.temporary_uploads` | `MEDIA_TEMPORARY_UPLOADS_EXPIRATION_DAYS` | `2` | Dias p/ prune de temporários |
| `expiration.soft_deleted` | `MEDIA_SOFT_DELETED_EXPIRATION_DAYS` | `180` | Dias p/ prune de lixeira |
| `prune.enabled` | `MEDIA_PRUNE_ENABLED` | `true` | Agenda o `model:prune` diário (desligue em multi-tenant) |
| `prune.time` | `MEDIA_PRUNE_TIME` | `02:00` | Horário do prune diário |

---

## 🧠 Como a contabilidade funciona

Todo byte entra e sai por um único ponto (`MediaFilesystem`). Cada escrita **grava o arquivo + registra a linha em `media_files` + atualiza `total_size`**; cada remoção inverte. Escrever direto no `Storage`, contornando esse serviço, fura a contagem.

`media_files` guarda cada variante: `original`, `conversion:{nome}`, `responsive:{largura}`. `media.total_size` é o cache da soma — a fonte da verdade é `media_files`.

---

## 📄 Licença

MIT. Veja [LICENSE](LICENSE).
