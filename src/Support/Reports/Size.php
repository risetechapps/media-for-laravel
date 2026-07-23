<?php

namespace RiseTechApps\Media\Support\Reports;

use InvalidArgumentException;
use Stringable;

/**
 * Uma quantidade de bytes com conversão e formatação.
 *
 *   Size::of(1536)->kb();          // 1.5
 *   Size::of(1536)->toUnit('MB');  // 0.0
 *   Size::of(1536)->forHumans();   // "1.5 KB"  (unidade automática)
 *   (string) Size::of(1536);       // "1.5 KB"
 */
class Size implements Stringable
{
    /** Potência de 1024 de cada unidade. */
    protected const UNITS = ['B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4, 'PB' => 5];

    public function __construct(protected int $bytes)
    {
    }

    public static function of(int $bytes): self
    {
        return new self($bytes);
    }

    /**
     * Interpreta um tamanho legível em bytes:
     *
     *   Size::parse('10GB');    // 10737418240
     *   Size::parse('500 MB');  // 524288000
     *   Size::parse('1,5gb');   // 1610612736  (vírgula ou ponto)
     *   Size::parse('2G');      // 2147483648  (unidade curta)
     *   Size::parse('1024');    // 1024        (sem unidade = bytes)
     *   Size::parse(2048);      // 2048        (int passa direto)
     *   Size::parse(null);      // null
     *
     * @throws \InvalidArgumentException Valor ou unidade inválidos.
     */
    public static function parse(int|string|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (! preg_match('/^([\d.,]+)\s*([a-z]+)$/i', $value, $matches)) {
            throw new InvalidArgumentException("Tamanho inválido: [{$value}]. Use algo como '10GB', '500 MB' ou bytes.");
        }

        $number = (float) str_replace(',', '.', $matches[1]);
        $unit = strtoupper($matches[2]);

        // Unidades curtas (G, M, ...) viram as completas.
        $unit = match ($unit) {
            'K' => 'KB', 'M' => 'MB', 'G' => 'GB', 'T' => 'TB', 'P' => 'PB',
            default => $unit,
        };

        if (! isset(self::UNITS[$unit])) {
            throw new InvalidArgumentException("Unidade [{$unit}] inválida. Use uma de: " . implode(', ', array_keys(self::UNITS)) . '.');
        }

        return (int) round($number * (1024 ** self::UNITS[$unit]));
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function kilobytes(int $precision = 2): float
    {
        return $this->toUnit('KB', $precision);
    }

    public function megabytes(int $precision = 2): float
    {
        return $this->toUnit('MB', $precision);
    }

    public function gigabytes(int $precision = 2): float
    {
        return $this->toUnit('GB', $precision);
    }

    public function terabytes(int $precision = 2): float
    {
        return $this->toUnit('TB', $precision);
    }

    /** Atalhos curtos. */
    public function kb(int $precision = 2): float
    {
        return $this->kilobytes($precision);
    }

    public function mb(int $precision = 2): float
    {
        return $this->megabytes($precision);
    }

    public function gb(int $precision = 2): float
    {
        return $this->gigabytes($precision);
    }

    public function tb(int $precision = 2): float
    {
        return $this->terabytes($precision);
    }

    /**
     * Converte para a unidade pedida (B, KB, MB, GB, TB, PB).
     */
    public function toUnit(string $unit, int $precision = 2): float
    {
        $unit = strtoupper($unit);

        if (! isset(self::UNITS[$unit])) {
            throw new InvalidArgumentException("Unidade [{$unit}] inválida. Use uma de: " . implode(', ', array_keys(self::UNITS)) . '.');
        }

        return round($this->bytes / (1024 ** self::UNITS[$unit]), $precision);
    }

    /**
     * Escolhe sozinho a maior unidade que ainda deixa a parte inteira >= 1
     * e formata: 1536 => "1.5 KB", 0 => "0 B".
     */
    public function forHumans(int $precision = 2): string
    {
        $bytes = max($this->bytes, 0);
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count(self::UNITS) - 1);

        $unit = array_search($power, self::UNITS, true);
        $value = round($bytes / (1024 ** $power), $precision);

        return "{$value} {$unit}";
    }

    public function __toString(): string
    {
        return $this->forHumans();
    }
}
