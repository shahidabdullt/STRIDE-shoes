<?php

namespace App\Support;

use NumberFormatter;

class Money
{
    /** Formats integer minor units in the store currency, e.g. 11900 => "$119.00". */
    public static function format(int $cents): string
    {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($cents / 100, config('store.currency')) ?: number_format($cents / 100, 2);
    }

    /** Sizes are stored as decimal(3,1); show 9.0 as "9" and 9.5 as "9.5". */
    public static function size(float|string $size): string
    {
        return rtrim(rtrim(number_format((float) $size, 1, '.', ''), '0'), '.');
    }
}
