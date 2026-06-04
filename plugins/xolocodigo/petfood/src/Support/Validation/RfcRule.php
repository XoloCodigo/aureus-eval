<?php

namespace XoloCodigo\PetFood\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RFC (Registro Federal de Contribuyentes) validator.
 *
 * Accepts both formats:
 *  - Persona física: 13 chars (4 letters + 6 digits YYMMDD + 3 alphanumeric homoclave)
 *  - Persona moral:  12 chars (3 letters + 6 digits YYMMDD + 3 alphanumeric homoclave)
 *
 * Length + alphabet are validated. Homoclave checksum is NOT verified
 * (requires the full SAT algorithm; deferred to a later pass when needed).
 */
class RfcRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El :attribute debe ser una cadena de texto.');

            return;
        }

        $normalized = strtoupper(trim($value));

        // Persona moral: 3 letters + 6 digits + 3 alphanumeric
        $personaMoral = '/^[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}$/';

        // Persona física: 4 letters + 6 digits + 3 alphanumeric
        $personaFisica = '/^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/';

        if (! preg_match($personaMoral, $normalized) && ! preg_match($personaFisica, $normalized)) {
            $fail('El :attribute no tiene el formato válido de un RFC (12 caracteres para persona moral o 13 para persona física).');
        }
    }
}
