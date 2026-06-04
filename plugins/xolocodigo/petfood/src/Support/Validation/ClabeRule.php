<?php

namespace XoloCodigo\PetFood\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CLABE (Clave Bancaria Estandarizada) validator.
 *
 * 18 digits with a checksum (mod 10 weighted) on the last digit.
 * The first 3 digits identify the bank, next 3 the branch, next 11 the
 * account, and the 18th is the verification digit.
 */
class ClabeRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El :attribute debe ser una cadena de texto.');

            return;
        }

        $clabe = preg_replace('/\s+/', '', $value);

        if (! preg_match('/^[0-9]{18}$/', $clabe)) {
            $fail('El :attribute debe contener exactamente 18 dígitos.');

            return;
        }

        if (! $this->checksumValid($clabe)) {
            $fail('El :attribute tiene un dígito verificador inválido.');
        }
    }

    /**
     * Validate CLABE checksum: weighted sum mod 10.
     * Weights: 3, 7, 1 repeating for the first 17 digits.
     * The 18th digit must equal (10 - sum_mod_10) mod 10.
     */
    private function checksumValid(string $clabe): bool
    {
        $weights = [3, 7, 1];
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += ((int) $clabe[$i]) * $weights[$i % 3] % 10;
        }

        $expected = (10 - ($sum % 10)) % 10;

        return $expected === (int) $clabe[17];
    }
}
