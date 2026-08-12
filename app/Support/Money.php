<?php

namespace App\Support;

class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-+');

        if (str_contains($value, '.')) {
            [$whole, $fraction] = explode('.', $value, 2);
        } else {
            $whole = $value;
            $fraction = '0';
        }

        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        $formatted = intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0');

        return $negative ? '-'.$formatted : $formatted;
    }

    /**
     * Distribute a total (in cents) into N parts where the last part absorbs
     * exclusively the remainder. Returns exact string amounts of two decimals.
     *
     * @return list<string>
     */
    public static function distribute(int $totalCents, int $parts): array
    {
        if ($parts < 1) {
            return [];
        }

        $base = intdiv($totalCents, $parts);
        $remainder = $totalCents % $parts;

        $result = [];

        for ($i = 0; $i < $parts; $i++) {
            $cents = $i === $parts - 1 ? $base + $remainder : $base;
            $result[] = self::fromCents($cents);
        }

        return $result;
    }
}
