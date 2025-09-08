<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Nombre: PiiScrubber
 * Descripción de la funcionalidad:
 *   Detector prudente de PII antes de enviar payload a IA. Reglas acotadas para reducir falsos positivos.
 *   Detecta: emails, teléfonos, NIF/NIE/CIF, IBAN (país válido), IPv4.
 *
 * Parámetros de entrada (métodos):
 *   - __construct(bool $strict = true)
 *   - scanArray(array $data): array{ok:bool, matches:array<int,string>}
 *
 * Salida:
 *   - Estructura con estado y descriptores de coincidencias.
 *
 * Método de uso:
 *   - $r = (new PiiScrubber())->scanArray($payload);
 *
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class PiiScrubber
{
    /** @var array<int, array{label:string, rx:string}> */
    private array $patterns;

    public function __construct(private bool $strict = true)
    {
        // IBAN restringido a prefijos de país comunes para evitar coincidir con UUID/hex aleatorio
        $ibanCountries = '(?:ES|PT|FR|DE|IT|NL|BE|GB|IE|LU|AT|CH|PL|SE|NO|FI|DK|CZ|SK|HU|RO|BG|HR|SI|LT|LV|EE|GR|CY|MT)';
        $this->patterns = [
            ['label' => 'email',   'rx' => '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i'],
            ['label' => 'teléfono','rx' => '/(?<!\d)(?:\+?\d{1,3}[ \-]?)?(?:\(?\d{2,3}\)?[ \-]?)?\d{3,4}[ \-]?\d{3,4}(?!\d)/'],
            ['label' => 'NIF',     'rx' => '/\b[0-9]{8}[A-HJ-NP-TV-Z]\b/i'],
            ['label' => 'NIE',     'rx' => '/\b[XYZ][0-9]{7}[A-HJ-NP-TV-Z]\b/i'],
            ['label' => 'CIF',     'rx' => '/\b[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[0-9A-J]\b/i'],
            // IBAN: dos letras de país válidas + 2 dígitos de control + resto
            ['label' => 'IBAN',    'rx' => '/\b' . $ibanCountries . '\d{2}[A-Z0-9]{10,30}\b/i'],
            ['label' => 'IPv4',    'rx' => '/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d?\d)(?:\.|$)){4}\b/'],
            // ⚠️ Sin heurísticos genéricos de números largos para evitar falsos positivos con UUIDs.
        ];
    }

    /**
     * Nombre: scanArray
     * Descripción: Recorre recursivamente el array buscando coincidencias de PII.
     * @param array<string|int,mixed> $data
     * @return array{ok:bool, matches:array<int,string>}
     * Método de uso: $r = (new PiiScrubber())->scanArray($payload);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function scanArray(array $data): array
    {
        $matches = [];
        $this->walk($data, $matches);
        return ['ok' => count($matches) === 0, 'matches' => $matches];
    }

    /**
     * Nombre: walk
     * Descripción: Recorrido recursivo de valores para buscar PII.
     * @param array<int|string,mixed> $data
     * @param array<int,string> $matches
     * @return void
     */
    private function walk(array $data, array &$matches): void
    {
        foreach ($data as $v) {
            if (is_array($v)) {
                $this->walk($v, $matches);
            } elseif (is_string($v)) {
                $text = $this->strict ? $v : mb_substr($v, 0, 10000); // límite defensivo opcional
                foreach ($this->patterns as $p) {
                    if (preg_match($p['rx'], $text) === 1) {
                        $matches[] = $p['label'];
                        break;
                    }
                }
            }
        }
    }
}
