<?php

namespace RiseTechApps\Media\Contracts;

use DateTimeInterface;
use RiseTechApps\Media\Models\MediaFile;

/**
 * Resolve a URL de um arquivo físico de mídia.
 *
 * Trocável via config('media.url_generator'). Uma implementação de CDN, por
 * exemplo, montaria a URL a partir do host do CDN em vez de assinar no S3.
 */
interface UrlGeneratorContract
{
    /**
     * URL crua do disco, sem garantias de acesso público.
     */
    public function getUrl(MediaFile $file): string;

    /**
     * URL temporária assinada, válida até $expiresAt.
     */
    public function getTemporaryUrl(MediaFile $file, DateTimeInterface $expiresAt): string;

    /**
     * URL pronta para exibição — a escolha certa para o front consumir.
     */
    public function getFullUrl(MediaFile $file): string;
}
