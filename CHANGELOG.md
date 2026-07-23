# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.
O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto segue o [Versionamento Semântico](https://semver.org/lang/pt-BR/) (SemVer).

## [3.0.0] - 2026-07-23

Reescrita completa do pacote, **removendo o `spatie/laravel-medialibrary`**. O motivo central: o Spatie contabiliza apenas o arquivo original (`media.size`) — conversões e imagens responsivas ocupam storage mas escapam da conta. Esta versão registra **cada arquivo físico** e soma os bytes de verdade.

> **BREAKING CHANGE.** API nova e incompatível com a 1.x. Migração obrigatória dos models consumidores.

### Adicionado
- **Contabilidade exata de bytes**: tabela `media_files` (uma linha por arquivo físico — `original`, `conversion:{nome}`, `responsive:{largura}`) e coluna `media.total_size` com a soma real ocupada. `MediaFilesystem` é o único caminho de bytes: toda escrita registra e contabiliza, toda remoção reverte.
- **Trait `InteractsWithMedia`** + contrato `MediaContract`: `addMedia`/`addMediaFromRequest`/`addMediaFromDisk`/`addMediaFromUrl`, coleções e conversões declarativas.
- **Coleções** (`MediaCollection`): `singleFile`, `acceptsMimeTypes`, `acceptsFile`, `useDisk`, fallback URL/path, `withResponsiveImages`.
- **Conversões** com cadeia de geradores (imagem, PDF, vídeo, ícone), enfileiráveis. `Conversion` fluente com `fit`, `format`, `quality`, `sharpen`, `background`, `optimize()`, `orientation()`, `pdfPageNumber`.
- **Ícones dedicados** por tipo, incluindo `svg`/`ico` (IMG) e código (`json`, `xml`, `html`, `php`, …). Suporte a **HEIC/HEIF** (rasteriza via Imagick).
- **Imagens responsivas** (`srcset`), desligáveis por config: `getSrcset()`/`getSrcsetArray()`, variante `responsive:{largura}` contabilizada.
- **URL trocável** (`UrlGeneratorContract` + `DefaultUrlGenerator`) com cache de URL assinada S3 e **suporte a CDN built-in** via `media.cdn.base`.
- **Relatórios de storage** (`StorageReport`, facade `Media::storage()`): `total`, `byCollection`, `byModelType`, `forModel`, `humanize`. Value object `Size` (`of`/`parse`/`kb/mb/gb`/`forHumans`).
- **Escopo por contexto** (tenancy desacoplado): `MediaScopeResolver`, carimbo em `custom_properties._scope`, global scope **fail-closed**, `Media::unscoped()`, índice GIN. Sem coluna `tenant_id` e sem depender de nenhum pacote de tenancy.
- **Cota de storage**: `QuotaResolver` ou `media.quota.default` (bytes ou string legível `'10GB'`), barrando o upload antes de gravar (`StorageQuotaExceeded`). Facade `Media::quota()` (`usage`/`limit`/`remaining`/`exceeded`/`percentUsed`).
- **Agendamento de prune** dos models do pacote (uploads temporários e mídia em lixeira), configurável (`media.prune`).
- **Suíte de testes** (Pest): invariante de bytes, cota, escopo fail-closed, prune, uploads temporários e validação.

### Alterado
- **Chave primária UUID** e soft delete em `media`; `total_size` denormalizado.
- **Dependências**: removido `spatie/laravel-medialibrary`; `spatie/image`, `spatie/temporary-directory` e `symfony/mime` promovidos a diretos.
- **Config** reorganizada: `disk`, `path_generator`, `url_generator`/`url`/`cdn`, `conversions`, `responsive_images`, `scope`, `quota`, `expiration`, `prune`.

### Removido
- **`spatie/laravel-medialibrary`** e toda a camada baseada nele.
- Traits `HasConversionsMedia`, `HasPhotoProfile`, `HasMediaSuite` — substituídas por `InteractsWithMedia`.
- `DownloadImageUrlService` e serviços/controllers do fluxo antigo.
- Coluna `generated_conversions` (derivada agora de `media_files`).

### Documentação
- README reescrito para a API nova, incluindo escopo, cota, CDN built-in e prune em multi-tenant.

## [1.4.0] - 2026-04-29

### Adicionado
- **Trait `HasMediaSuite`**: Combina `HasConversionsMedia` e `HasPhotoProfile` em uma única trait para facilitar o uso em models que precisam de todas as funcionalidades de mídia (User, Company, Employee).
- **Métodos `additionalMediaConversions()` e `additionalMediaCollections()`**: Permitem adicionar conversões e coleções extras sem precisar copiar o código da trait original. Chama automaticamente se existir no model.
- **Configuração de expiração dinâmica**: Adicionado `config('media.expiration.temporary_uploads')` e `config('media.expiration.soft_deleted')` para controle via arquivo de configuração ou variáveis de ambiente.
  - `MEDIA_TEMPORARY_UPLOADS_EXPIRATION_DAYS` (padrão: 2 dias)
  - `MEDIA_SOFT_DELETED_EXPIRATION_DAYS` (padrão: 180 dias)

### Alterado
- **Atualização de dependências**:
  - `risetechapps/has-uuid-for-laravel`: ^1.0 → ^1.2
  - `risetechapps/monitoring-for-laravel`: ^2.1.2 → ^3.0.0
  - `risetechapps/risetools`: ^1.8.2 → ^2.0.0
- **Refatoração do `DownloadImageUrlService`**:
  - Simplificação da lógica com menos níveis de aninhamento
  - Redução do timeout de 30s para 10s
  - Uso de UUID ao invés de `uniqid()` para nomes de arquivos
  - Melhoria nas mensagens de erro no loggly
- **Models `Media` e `MediaUploadTemporary`**: Agora utilizam configuração dinâmica para expiração de registros no método `prunable()`.

### Corrigido
- **UploadController**: Removida chamada duplicada de `withRequest()` nos métodos de log (`logglyWarning` e `logglyError`).
- **MediaFacade**: Corrigida referência de `@see` no PHPDoc de `SkeletonClass` para `Media`.

### Documentação
- Atualizado README.md com:
  - Documentação da trait `HasMediaSuite`
  - Exemplos de uso dos métodos `additionalMediaConversions()` e `additionalMediaCollections()`
  - Simplificação dos exemplos de traits personalizadas
  - Reorganização da seção de customização avançada

## [1.3.4] - 2026-03-29
- Atualizado package monitoring

## [1.3.3] - 2026-03-17
- Atualizado package monitoring
 
## [1.3.2] - 2026-03-14
- Atualizado package monitoring

## [1.3.1] - 2026-03-13
- Atualizado package risetools

## [1.3.0] - 2026-02-03
- Atualizado package spatie/pdf-to-image
- Implementado suporte ao php8.4

## [1.2.0] - 2026-02-03
- Aplicado melhorias no service provider
- Implementado package risetools

## [1.1.0] - 2026-02-03

- Corrigido incompatibilidade de variável


## [1.0.0] - 2025-12-10
### Added
- Lançamento inicial (Primeira versão estável).
