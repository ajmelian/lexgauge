<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Nombre: Csrf
 * Descripción de la funcionalidad:
 *   Gestión de token CSRF almacenado en sesión. Permite generar/recuperar el token
 *   y validar el recibido desde formularios. Usa clave configurable CONFIG['security']['csrfKey'].
 *
 * Parámetros de entrada:
 *   - generate(): N/A
 *   - token(): N/A
 *   - validate(string $token): $token recibido desde el formulario.
 *
 * Salida:
 *   - generate(): void
 *   - token(): string (token actual en sesión)
 *   - validate(): bool (true si coincide de forma segura)
 *
 * Método de uso:
 *   - Csrf::generate();            // antes de renderizar el formulario
 *   - $t = Csrf::token();          // valor para input hidden
 *   - Csrf::validate($_POST['_csrf'] ?? '') // al procesar POST
 *
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class Csrf
{
    /**
     * Nombre: generate
     * Descripción: Genera el token si no existe ya en la sesión actual.
     * @return void
     */
    public static function generate(): void
    {
        /** @var string $key */
        $key = (string)CONFIG['security']['csrfKey'];
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || $_SESSION[$key] === '') {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Nombre: token
     * Descripción: Devuelve el token CSRF actual (generándolo si no existe).
     * @return string Token CSRF.
     */
    public static function token(): string
    {
        /** @var string $key */
        $key = (string)CONFIG['security']['csrfKey'];
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || $_SESSION[$key] === '') {
            self::generate();
        }
        return (string)$_SESSION[$key];
    }

    /**
     * Nombre: validate
     * Descripción: Valida de forma segura que el token recibido coincide con el de sesión.
     * @param string $token Token recibido desde el formulario.
     * @return bool true si coincide; false en caso contrario.
     */
    public static function validate(string $token): bool
    {
        /** @var string $key */
        $key = (string)CONFIG['security']['csrfKey'];
        $sessionToken = (string)($_SESSION[$key] ?? '');
        if ($sessionToken === '' || $token === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
}
