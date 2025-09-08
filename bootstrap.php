<?php
declare(strict_types=1);

/**
 * Nombre: bootstrap.php
 * Descripción de la funcionalidad:
 *   Inicializa la aplicación: define constantes de rutas, aplica directivas de PHP,
 *   arranca la sesión de forma segura, emite cabeceras de seguridad y registra
 *   el autoload PSR-4 para el espacio de nombres App\. Finalmente carga la configuración.
 * Parámetros de entrada: N/A
 * Salida: N/A (efectos: defines/headers/sesión/autoload)
 * Método de uso: Incluir desde los puntos de entrada públicos (p.ej., public/index.php).
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */

//
// =========================
// 1) Constantes y entorno
// =========================
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}
if (!defined('SRC_PATH')) {
    define('SRC_PATH', APP_ROOT . '/src');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', APP_ROOT . '/config');
}
if (!defined('DATA_PATH')) {
    define('DATA_PATH', APP_ROOT . '/data');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', APP_ROOT . '/public');
}
if (!defined('APP_ENV')) {
    // prod | dev (puedes exportar APP_ENV=dev en tu entorno local si lo prefieres)
    define('APP_ENV', getenv('APP_ENV') ?: 'prod');
}

/**
 * Nombre: applyPhpIniDefaults
 * Descripción: Configura directivas de PHP seguras y adecuadas al entorno.
 * @return void
 * Método de uso: applyPhpIniDefaults();
 * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
function applyPhpIniDefaults(): void
{
    $isDev = (APP_ENV === 'dev');

    // Visibilidad de errores
    ini_set('display_errors', $isDev ? '1' : '0');
    ini_set('log_errors', '1');

    // Endurecimiento de sesión
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    // Ajusta secure según HTTPS detectado (útil si sirves por TLS)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
}

/**
 * Nombre: startSessionSafely
 * Descripción: Inicia la sesión si no está activa, con parámetros seguros.
 * @return void
 * Método de uso: startSessionSafely();
 * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
function startSessionSafely(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Refuerza parámetros a nivel de función (complemento a ini_set)
        $options = [
            'cookie_httponly' => true,
            'cookie_secure'   => (ini_get('session.cookie_secure') === '1'),
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => 1,
        ];
        // A partir de PHP 7.3+, session_start acepta array de opciones
        @session_start($options);
    }
}

/**
 * Nombre: sendSecurityHeaders
 * Descripción: Emite cabeceras de seguridad si aún no se han enviado.
 * @return void
 * Método de uso: sendSecurityHeaders();
 * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Política CSP: permite recursos locales; CSS inline (Bootstrap); bloquea conexiones externas desde el navegador.
    // Nota: Las llamadas a IA se realizan en servidor (cURL), no afectan a CSP del navegador.
    $csp = "default-src 'self'; "
         . "img-src 'self' data:; "
         . "style-src 'self' 'unsafe-inline'; "
         . "script-src 'self'; "
         . "connect-src 'self'; "
         . "frame-ancestors 'self';";
    header("Content-Security-Policy: " . $csp);
}

/**
 * Nombre: registerPsr4Autoload
 * Descripción: Registra un autoloader PSR-4 mínimo para el namespace base App\.
 * @return void
 * Método de uso: registerPsr4Autoload();
 * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
function registerPsr4Autoload(): void
{
    spl_autoload_register(
        /**
         * @param string $class Nombre completo de la clase (FQCN).
         * @return void
         */
        static function (string $class): void {
            $prefix   = 'App\\';
            $baseDir  = SRC_PATH . '/';
            $len      = strlen($prefix);

            if (strncmp($prefix, $class, $len) !== 0) {
                return; // No es de nuestro namespace
            }

            $relativeClass = substr($class, $len);
            $file          = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                /** @noinspection PhpIncludeInspection */
                require $file;
            }
        }
    );
}

/**
 * Nombre: requireConfig
 * Descripción: Carga el archivo de configuración principal de la aplicación.
 * @return void
 * Método de uso: requireConfig();
 * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
function requireConfig(): void
{
    $configFile = CONFIG_PATH . '/config.php';
    if (!is_file($configFile)) {
        http_response_code(500);
        exit('Fichero de configuración no encontrado: config/config.php');
    }
    require_once $configFile;
}

// ===============
// Secuencia boot
// ===============
applyPhpIniDefaults();
startSessionSafely();
sendSecurityHeaders();
registerPsr4Autoload();
requireConfig();
