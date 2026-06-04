<?php

namespace XoloCodigo\PetFood\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CURP (Clave Única de Registro de Población) validator.
 *
 * Format (18 chars):
 *   - 4 letters (initials)
 *   - 6 digits (YYMMDD birth date)
 *   - 1 letter (sex: H/M)
 *   - 2 letters (state of birth, RENAPO codes)
 *   - 3 letters (consonants from name)
 *   - 1 alphanumeric (homoclave: digit for <2000, letter for >=2000)
 *   - 1 digit (verification digit)
 *
 * Length + alphabet are validated. Verification-digit algorithm is NOT
 * computed (deferred — requires the RENAPO formula).
 */
class CurpRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El :attribute debe ser una cadena de texto.');

            return;
        }

        $normalized = strtoupper(trim($value));

        $pattern = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/';

        if (! preg_match($pattern, $normalized)) {
            $fail('El :attribute no tiene el formato válido de una CURP (18 caracteres).');
        }
    }
}
