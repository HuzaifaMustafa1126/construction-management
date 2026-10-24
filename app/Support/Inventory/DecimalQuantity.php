<?php

namespace App\Support\Inventory;

use DomainException;

final class DecimalQuantity
{
    public static function compare(string $left, string $right): int
    {
        return self::scaled($left) <=> self::scaled($right);
    }

    public static function add(string $left, string $right): string
    {
        $sum = self::scaled($left) + self::scaled($right);
        $sign = $sum < 0 ? '-' : '';
        $absolute = abs($sum);

        return $sign.intdiv($absolute, 10000).'.'.str_pad((string) ($absolute % 10000), 4, '0', STR_PAD_LEFT);
    }

    private static function scaled(string $value): int
    {
        if (! preg_match('/^(-?)(\d{1,14})(?:\.(\d{1,4}))?$/', $value, $matches)) {
            throw new DomainException('Invalid decimal quantity.');
        }

        $scaled = ((int) $matches[2] * 10000) + (int) str_pad($matches[3] ?? '', 4, '0');

        return $matches[1] === '-' ? -$scaled : $scaled;
    }
}
