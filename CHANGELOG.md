# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.
O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto segue o [Versionamento Semântico](https://semver.org/lang/pt-BR/) (SemVer).

## [3.3.1] - 2026-08-24

Corrida entre a geração de derivados em fila e a exclusão definitiva da mídia.

### Corrigido
- **`PerformConversionsJob` estourava `SQLSTATE[23503]` (`media_files_media_id_foreign`) quando a mídia era apagada enquanto o job rodava.** Coleção `singleFile` remove a anterior com `forceDelete` (`FileAdder::removePreviousMedia`), e `MediaUploadTemporary::deleteAllMedia` faz o mesmo — a linha some de `media` de verdade, não vai para a lixeira. O job já havia carregado a mídia e, depois de baixar o original e converter (segundos), tentava inserir a linha do derivado apontando para uma mídia inexistente. A contabilidade de bytes não foi afetada — o derivado já tinha subido para o disco, mas o tratamento de falha de `register()` o removia — o custo era o log de erro a cada troca de arquivo durante uma conversão, um `report()` para uma condição esperada.

  Agora o caminho de escrita reconfere a mídia no banco (`Media::stillExists()`, ignorando escopo e lixeira — mídia na lixeira mantém os arquivos e a conversão continua válida) antes de registrar a linha, e a corrida restante (exclusão entre o SELECT e o INSERT) é reconhecida pelo SQLSTATE e pelo nome da constraint. Nos dois casos o arquivo recém-gravado é removido do disco e sobe uma `MediaNoLongerExists`, que `ConversionEngine` e `ResponsiveImageGenerator` tratam como no-op silencioso — não é falha, é trabalho que perdeu o destino, e `report()` só encheria o log.
- **`ConversionEngine::performOne()` podia estourar `ModelNotFoundException` no evento de conclusão.** `$media->refresh()` usa `findOrFail`; com a mídia apagada logo após a gravação, o evento derrubava a conversão. Passa a recarregar com `find`, e o `ConversionHasBeenCompleted` só dispara se a mídia ainda estiver lá.

### Alterado
- **`PerformConversionsJob` e `GenerateResponsiveImagesJob` marcam `$deleteWhenMissingModels = true`.** Mídia apagada antes de o job sair da fila descarta o job em vez de acumular jobs falhos no Horizon. `SyncUploadsJob` fica de fora de propósito: lá o model ausente significa upload que nunca será vinculado — tem de falhar visível.
- Guarda antecipada em `ConversionEngine::perform()`, `ResponsiveImageGenerator::generate()` e `PerformConversionsJob::handle()`: mídia inexistente sai antes de baixar o original do storage.

### Testes
- `DeletedMediaDuringConversionTest`: `stillExists()` distingue lixeira de exclusão definitiva; `storeConversion()` em mídia apagada lança `MediaNoLongerExists` e não deixa o derivado no disco; `perform()`/`handle()` viram no-op; troca em coleção `singleFile` de fato apaga a anterior em definitivo.

## [3.3.0] - 2026-08-21

Correções no vínculo de uploads e no caminho de exclusão, mais a ferramenta que faltava para ligar o escopo de tenancy em base já existente.

### Corrigido
- **`MediaUploadService::sync()` estourava `SQLSTATE[22P02]` quando a seleção ficava vazia.** `removeUnselected()` usava `['-']` como sentinela em `whereNotIn('id', ...)`; em PostgreSQL a coluna é `uuid` e `'-'` derruba a query com `invalid input syntax for type uuid`. A sentinela era desnecessária — o Laravel já compila `whereNotIn` vazio como `1 = 1`, que é a semântica correta. O bug só aparecia em PostgreSQL: a suíte roda em SQLite, onde `'-'` é texto aceito.
- **`sync()` só aceitava lista de itens.** Campo de arquivo único (`{"photo": {"id": ..., "file_name": ...}}`) chegava como objeto associativo; o `foreach` iterava os *valores*, `$upload['id'] ?? null` devolvia `null` silenciosamente em cada um e a seleção terminava vazia — o que, além de cair na sentinela acima, teria apagado a coleção inteira. Agora `normalize()` aceita lista, objeto único ou id solto, descarta o que não é uuid antes de tocar na query, e distingue `null` (campo ausente: preserva a coleção) de `[]` (seleção vazia: esvazia).

### Corrigido (contabilidade de bytes)
- **Exclusão de mídia deixou de passar pelo global scope de escopo.** `deleteAllMedia()` (no trait e no `MediaUploadTemporary`), `FileAdder::removePreviousMedia()` e `MediaUploadService::removeUnselected()` montavam a lista do que apagar por query sobre a relação `media()`, que carrega o `MediaScope`. Com escopo ativo e contexto divergente a query devolvia menos linhas, o `delete()` nunca era chamado nelas, o hook que limpa o disco não rodava e **o arquivo ficava no storage, pago, para sempre** — furando a invariante de bytes do pacote. O caso garantido era o prune: roda pelo scheduler, sem contexto, e sob fail-closed apagaria a linha do upload temporário deixando o arquivo. Os quatro caminhos agora usam `unscoped()`: na exclusão o recorte já vem da posse (a relação é de uma instância específica), então o filtro por cima não protegia nada e só podia esconder linha.

### Adicionado
- **Comando `media:backfill-scope`**: carimba `custom_properties._scope` na mídia criada antes de existir um `MediaScopeResolver`. Necessário porque o global scope é fail-closed — no instante em que o resolver é registrado, mídia sem `_scope` desaparece de todas as queries. Aceita `--scope=chave=valor` (ou usa o contexto resolvido no momento), `--collection`, `--model`, `--dry-run` e `--force`. Roda em `unscoped()->withTrashed()` (mídia na lixeira ainda ocupa disco), só toca em linha com `_scope` nulo, preserva `updated_at` e não escreve em disco.

  > O contexto de origem **não é recuperável do banco**: o prefixo de sub-tenant das URLs vem do root do disco em tempo de execução, não do `path` gravado em `media_files`. Se um mesmo banco guarda mídia de mais de um contexto sem escopo, não rode sem filtro — carimbaria tudo para o contexto informado.

### Alterado
- **`Media` passou a ocultar atributos internos na serialização** (`$hidden`): `model_type`, `model_id`, `name`, `manipulations`, `disk`, `conversions_disk`, `total_size`, `custom_properties`, `responsive_images`, `order_column` e timestamps. O payload fica com `id`, `collection_name`, `file_name`, `mime_type`, `size` e as URLs de `$appends` (`preview`/`thumb`) — o que a tela usa, já que o consumidor devolve o mesmo objeto no PUT. Quem precisar de um campo específico usa `makeVisible('total_size')` na chamada.

  > **Atenção:** quem lia `total_size` ou `custom_properties` direto do JSON da mídia deixa de recebê-los.

### Testes
- `MediaUploadServiceTest`: objeto único, id solto como string, `null` preserva a coleção, id fora do formato uuid é descartado, mídia já vinculada não é recriada.
- `ScopeTest`: exclusão do dono alcança mídia de outro contexto, `singleFile()` remove a anterior com o contexto trocado, `removeUnselected()` limpa sem contexto.

## [3.2.1] - 2026-08-14

### Adicionado
- **Atributos `preview` e `thumb` no model `Media`**, serializados por padrão (`$appends`): `preview` devolve a URL do original e `thumb` a da conversão `thumb`, com fallback para o original quando ela ainda não foi gerada. Ambos são `null` quando a mídia não tem arquivo em disco — serializar uma mídia inconsistente não estoura mais exceção.

### Alterado
- **`fileForVariant()` usa a relação `files` quando já carregada**, em vez de consultar o banco sempre. Sem isso os atributos serializados gerariam N+1 mesmo com `with('media.files')`. `hasGeneratedConversion()` passou a reutilizar o mesmo caminho.

> Ao serializar listas de mídia, carregue a relação (`with('media.files')` ou `$media->load('files')`); caso contrário cada mídia custa consultas extras para montar `preview`/`thumb`.

## [3.2.0] - 2026-07-26

### Corrigido
- **Disco `media_prefixed_disk` não registrado**: o disco dinâmico só era criado quando `STORAGE_PREFIX` estava setado, mas registros legados no banco já referenciavam o nome `media_prefixed_disk`. Isso causava `InvalidArgumentException: Disk [media_prefixed_disk] does not have a configured driver` ao gerar URLs de mídia. O disco agora é registrado sempre que o disco base existe, independentemente do prefixo.

## [3.1.0] - 2026-07-23

Correção do caminho de atualização a partir da 1.x/2.x e ferramenta de reconciliação de storage.

### Corrigido
- **Upgrade da 1.x/2.x quebrado**: o schema v3 da tabela `media` havia sido reescrito *por dentro* da migration `2024_09_30_170815_create_media_table`, mantendo o nome do arquivo. Como o migrator identifica migrations pelo nome (não pelo conteúdo), instalações existentes já tinham esse arquivo marcado como executado e o **pulavam**, ficando presas no schema Spatie antigo (PK `bigint`) — e a criação de `media_files` falhava com `SQLSTATE[42804] Datatype mismatch: uuid vs bigint`. Adicionadas migrations novas, com nomes próprios, que fazem o upgrade in-place **preservando os dados**:
  - `2026_07_21_000000_upgrade_media_table_to_v3` — promove a coluna `uuid` legada a chave primária `id` (com isso os caminhos físicos existentes batem com o novo layout, sem mover nada no bucket), remove `generated_conversions`, adiciona `total_size`, converte `json` → `jsonb` e cria os índices. **No-op** em instalações novas (detecta se `id` já é uuid).
  - `2026_07_22_000000_backfill_media_original_files` — registra o arquivo `original` de cada mídia herdada em `media_files` (caminho determinístico `{coleção}/{id}/{arquivo}`, tamanho de `media.size`) e recalcula `total_size`.

### Adicionado
- **Comando `media:reconcile`**: varre o disco de cada mídia e registra em `media_files` os arquivos físicos ainda não contabilizados — conversões e variantes responsivas herdadas da 1.x, cujo tamanho só existe em disco — recalculando `total_size`. Idempotente, com `--dry-run` e `--media=<uuid>`. Não move nem apaga arquivos; serve também como reconciliação geral.

### Documentação
- README: seção **Atualizando da 1.x / 2.x** com o fluxo de migração e o uso do `media:reconcile`.

## [3.0.0] - 2026-07-23

Reescrita completa do pacote, **removendo o `spatie/laravel-medialibrary`**. O motivo central: o Spatie contabiliza apenas o arquivo original (`media.size`) — conversões e imagens responsivas ocupam storage mas escapam da conta. Esta versão registra **cada arquivo físico** e soma os bytes de verdade.

> **BREAKING CHANGE.** API nova e incompatível com a 1.x. Migração obrigatória dos models consumidores.

### Adicionado
- **Contabilidade exata de bytes**: tabela `media_files` (uma linha por arquivo físico — `original`, `conversion:{nome}`, `responsive:{largura}`) e coluna `media.total_size` com a soma real ocupada. `MediaFilesystem` é o único caminho de bytes: toda escrita registra e contabiliza, toda remoção reverte.
- **Trait `InteractsWithMedia`** + contrato `MediaContract`: `addMedia`/`addMediaFromRequest`/`addMediaFromDisk`/`addMediaFromUrl`, coleções e conversões declarativas.
- **Trait `HasMediaSuite`**: atalho com coleção e conversão `thumb` padrão (config `media.defaults`), extensível via `additionalMediaCollections()`/`additionalMediaConversions()` ou sobrescrita dos `default*()`, sem prender aos defaults.
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
