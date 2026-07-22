<?php

namespace RiseTechApps\Media\Support\Disk;

/**
 * Resolve qual disco a mídia usa.
 *
 * Quando há prefixo configurado, o package registra em memória um clone do
 * disco base com o prefixo aplicado ao root (`media_prefixed_disk`) e passa a
 * usá-lo. Sem prefixo, usa o disco base direto.
 *
 * Fonte única dessa decisão — nem o FileAdder nem o Filesystem a duplicam.
 */
class MediaDisk
{
    public const PREFIXED = 'media_prefixed_disk';

    public static function name(): string
    {
        return static::hasPrefix() ? static::PREFIXED : static::baseName();
    }

    public static function baseName(): string
    {
        return config('media.disk.name') ?: config('filesystems.default');
    }

    public static function prefix(): string
    {
        return trim((string) config('media.disk.prefix', ''), '/');
    }

    public static function hasPrefix(): bool
    {
        return static::prefix() !== '';
    }
}
