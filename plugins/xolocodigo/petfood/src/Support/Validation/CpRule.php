<?php

namespace XoloCodigo\PetFood\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mexican Código Postal (postal code) validator.
 *
 * Exactly 5 digits. Range 01000-99999 (SEPOMEX uses 01000-16999 for CDMX,
 * 20000+ for other states); full range validation is not performed since
 * the catalog updates regularly. Length + digits-only is enough for a
 * form-level check.
 */
class CpRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('El :attribute debe ser un código postal numérico.');

            return;
        }

        if (! preg_match('/^[0-9]{5}$/', (string) $value)) {
            $fail('El :attribute debe contener exactamente 5 dígitos.');
        }
    }
}
