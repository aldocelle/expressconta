<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Rut implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $s = strtoupper(str_replace(['.', '-', ' '], '', (string) $value));
        if (! preg_match('/^([0-9]{7,8})([0-9K])$/', $s, $m)) {
            $fail('El RUT no tiene un formato válido.');

            return;
        }
        $sum = 0;
        $factor = 2;
        foreach (str_split(strrev($m[1])) as $digit) {
            $sum += (int) $digit * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $dv = 11 - $sum % 11;
        $expected = $dv === 11 ? '0' : ($dv === 10 ? 'K' : (string) $dv);
        if ($m[2] !== $expected) {
            $fail('El dígito verificador del RUT es incorrecto.');
        }
    }

    public static function normalize(string $rut): string
    {
        return strtoupper(str_replace(['.', ' '], '', $rut));
    }
}
