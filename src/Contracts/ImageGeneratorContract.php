<?php

namespace RiseTechApps\Media\Contracts;

use RiseTechApps\Media\Support\Conversions\Conversion;

/**
 * Produz uma imagem base a partir de um arquivo, para que a conversão possa
 * então redimensionar e formatar.
 *
 * Cada tipo de arquivo tem sua estratégia: imagem usa o próprio arquivo, PDF
 * rasteriza uma página, vídeo extrai um quadro, e o restante recebe um ícone
 * representativo.
 */
interface ImageGeneratorContract
{
    /**
     * Este gerador sabe lidar com o tipo informado?
     */
    public function canHandle(?string $mimeType, string $extension): bool;

    /**
     * Devolve o caminho local da imagem base gerada, ou null em caso de falha.
     *
     * @param  string  $sourcePath  arquivo original já em disco local
     * @param  string  $workingDirectory  diretório temporário de trabalho
     */
    public function generate(string $sourcePath, string $workingDirectory, Conversion $conversion): ?string;

    /**
     * A imagem base deve caber inteira no destino (Contain, com folga) em vez de
     * ser cortada para preencher (Crop)?
     *
     * Ícones e logos não podem ser cortados — perderiam as bordas. Fotos e
     * quadros de vídeo, sim, para render uniforme em grid.
     */
    public function fitInside(): bool;
}
