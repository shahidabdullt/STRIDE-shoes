<?php

namespace App\Support;

/**
 * Masks sensitive details before a message is stored (BLUEPRINT §5.3): card-like numbers that pass the Luhn
 * check always, email addresses when assistant.redact_emails is on. Order numbers, sizes and prices are left alone.
 */
class Redactor
{
    public static function redact(string $text): string
    {
        // 13–19 digits, optionally separated by single spaces or dashes.
        $text = preg_replace_callback('/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/', function (array $m): string {
            $digits = preg_replace('/\D/', '', $m[0]);

            return self::passesLuhn($digits) ? '[card number removed]' : $m[0];
        }, $text);

        if (config('assistant.redact_emails')) {
            $text = preg_replace('/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.\p{L}{2,}/u', '[email removed]', $text);
        }

        return $text;
    }

    public static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];

            if ($double) {
                $d *= 2;
                $d = $d > 9 ? $d - 9 : $d;
            }

            $sum += $d;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
