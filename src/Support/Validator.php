<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Nombre: Validator
 * Descripción de la funcionalidad:
 *   Conjunto de validaciones de entrada utilizadas por los formularios de la aplicación.
 *   Incluye validaciones de NIF/NIE/CIF, nombre de empresa, tipo de empresa, tamaño,
 *   selección de normativas y proveedor/credencial de IA.
 *
 * Parámetros de entrada: varias funciones estáticas (ver firmas).
 * Salida: bool/valores saneados.
 * Método de uso: Validator::isCompanyName($s)
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class Validator
{
    /**
     * Nombre: isCompanyName
     * Descripción: Valida nombre de empresa (alfanumérico + espacios, comas y guiones). Respeta longitud máxima de CONFIG.
     * @param string $s
     * @return bool
     */
    public static function isCompanyName(string $s): bool
    {
        $s = trim($s);
        $max = (int)CONFIG['security']['maxCompanyNameLen'];
        if ($s === '' || mb_strlen($s, 'UTF-8') > $max) {
            return false;
        }
        return (bool)preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ0-9,\-\s]{2,}$/u', $s);
    }

    /**
     * Nombre: isCompanyType
     * Descripción: Verifica que el tipo de empresa está permitido en la configuración.
     * @param string $s
     * @return bool
     */
    public static function isCompanyType(string $s): bool
    {
        /** @var array<int,string> $allowed */
        $allowed = (array)CONFIG['security']['allowedCompanyTypes'];
        return in_array($s, $allowed, true);
    }

    /**
     * Nombre: isEmployeeSize
     * Descripción: Valida el número de empleados (entero positivo, hasta 6 dígitos).
     * @param string $s
     * @return bool
     */
    public static function isEmployeeSize(string $s): bool
    {
        return (bool)preg_match('/^[1-9][0-9]{0,5}$/', $s);
    }

    /**
     * Nombre: isNifNieCif
     * Descripción: Valida NIF/NIE/CIF con expresiones regulares (sin cálculo de dígito/letra de control).
     * @param string $s
     * @return bool
     */
    public static function isNifNieCif(string $s): bool
    {
        $s = strtoupper(trim($s));
        $rx = '/^(?:[0-9]{8}[A-HJ-NP-TV-Z]|[XYZ][0-9]{7}[A-HJ-NP-TV-Z]|[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[0-9A-J])$/';
        return (bool)preg_match($rx, $s);
    }

    /**
     * Nombre: normatives
     * Descripción: Filtra y normaliza la selección a las normativas permitidas.
     * @param array<int,string> $a
     * @return array<int,string> Selección válida y única.
     */
    public static function normatives(array $a): array
    {
        /** @var array<int,string> $allowed */
        $allowed = (array)CONFIG['security']['allowedNormatives'];
        $out = [];
        foreach ($a as $n) {
            if (is_string($n) && in_array($n, $allowed, true)) {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }

    /**
     * Nombre: aiProvider
     * Descripción: Valida el identificador de proveedor de IA contra la configuración.
     * @param string|null $s
     * @return string|null Devuelve la clave válida o null.
     */
    public static function aiProvider(?string $s): ?string
    {
        if ($s === null || $s === '') {
            return null;
        }
        /** @var array<string,mixed> $providers */
        $providers = (array)CONFIG['ai']['providers'];
        return array_key_exists($s, $providers) ? $s : null;
    }

    /**
     * Nombre: apiToken
     * Descripción: Valida mínimo de seguridad para tokens de API (20-200 chars alfanumérico,_,-).
     * @param string|null $s
     * @return string|null Token si válido; null si inválido.
     */
    public static function apiToken(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = trim($s);
        return (bool)preg_match('/^[A-Za-z0-9_\-]{20,200}$/', $s) ? $s : null;
    }
}
