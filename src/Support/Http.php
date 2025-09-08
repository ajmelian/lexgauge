<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Nombre: Http
 * Descripción de la funcionalidad:
 *   Utilidades HTTP mínimas: cabeceras no-cache, redirecciones seguras,
 *   helpers de entrada (GET/POST) y detección de AJAX.
 *
 * Parámetros de entrada:
 *   - redirect(string $location, int $status = 303)
 *   - json(mixed $data, int $status = 200)
 *   - get(string $key, mixed $default = null), post(string $key, mixed $default = null)
 *
 * Salida:
 *   - Cabeceras/flujo de respuesta; valores saneados de entrada.
 *
 * Método de uso:
 *   - Http::noCache();
 *   - Http::redirect('index.php');
 *   - $value = Http::post('field', '');
 *
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class Http
{
    /**
     * Nombre: noCache
     * Descripción: Emite cabeceras para evitar cacheo de la respuesta actual.
     * @return void
     */
    public static function noCache(): void
    {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Nombre: redirect
     * Descripción: Redirige de forma segura y finaliza el script.
     * @param string $location Ruta/URL destino.
     * @param int $status Código HTTP (303 por defecto para POST→GET).
     * @return void
     */
    public static function redirect(string $location, int $status = 303): void
    {
        if (!headers_sent()) {
            header('Location: ' . $location, true, $status);
        }
        exit;
    }

    /**
     * Nombre: json
     * Descripción: Envía JSON con estado indicado y finaliza.
     * @param mixed $data Datos serializables a JSON.
     * @param int $status Código HTTP.
     * @return void
     */
    public static function json(mixed $data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Nombre: method
     * Descripción: Obtiene el método HTTP de la petición.
     * @return string 'GET' | 'POST' | etc.
     */
    public static function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * Nombre: isAjax
     * Descripción: Detecta si la petición se marcó como AJAX.
     * @return bool
     */
    public static function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Nombre: get
     * Descripción: Helper para leer de $_GET con valor por defecto.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Nombre: post
     * Descripción: Helper para leer de $_POST con valor por defecto.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }
}
