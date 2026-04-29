# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.
O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto segue o [Versionamento Semântico](https://semver.org/lang/pt-BR/) (SemVer).

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
