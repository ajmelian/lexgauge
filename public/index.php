<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Http;

/**
 * Nombre: HomeController
 * Descripción de la funcionalidad: Controlador de la página inicial.
 *   Genera el formulario principal para capturar datos de empresa, normativas a analizar
 *   y configuración opcional de IA BYOK. Carga errores "flash" desde sesión y
 *   garantiza cabeceras de no caché y token CSRF.
 * Parámetros de entrada:
 *   - N/A (entrada directa del usuario vía formulario; errores previos por $_SESSION['flash_errors'])
 * Salida:
 *   - HTML del formulario de inicio.
 * Método de uso:
 *   - (new HomeController())->run();
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class HomeController
{
    /**
     * Nombre: run
     * Descripción: Prepara la respuesta (no-cache, CSRF) y renderiza la vista con errores flash si existen.
     * @return void
     * Método de uso: (new HomeController())->run();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function run(): void
    {
        Http::noCache();
        Csrf::generate();
        $flashErrors = $this->consumeFlashErrors();
        $this->render($flashErrors);
    }

    /**
     * Nombre: consumeFlashErrors
     * Descripción: Recupera y elimina de la sesión los errores "flash" para mostrarlos una vez.
     * @return array<int,string> Lista de mensajes de error.
     * Método de uso: $errors = $this->consumeFlashErrors();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function consumeFlashErrors(): array
    {
        /** @var array<int,string> $errors */
        $errors = (array)($_SESSION['flash_errors'] ?? []);
        if (!empty($_SESSION['flash_errors'])) {
            unset($_SESSION['flash_errors']);
        }
        return array_values(array_filter($errors, 'is_string'));
    }

    /**
     * Nombre: render
     * Descripción: Construye el HTML del formulario principal con Bootstrap local.
     * @param array<int,string> $flashErrors Errores de validación a mostrar (si los hay).
     * @return void
     * Método de uso: $this->render($errors);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function render(array $flashErrors): void
    {
        $title = (string)CONFIG['app']['title'];
        $maxCompanyNameLen = (int)CONFIG['security']['maxCompanyNameLen'];
        /** @var array<int,string> $companyTypes */
        $companyTypes = (array)CONFIG['security']['allowedCompanyTypes'];
        /** @var array<int,string> $allowedNormatives */
        $allowedNormatives = (array)CONFIG['security']['allowedNormatives'];

        echo '<!doctype html><html lang="es"><head>';
        echo '<meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<link rel="stylesheet" href="assets/bootstrap/bootstrap.min.css">';
        echo '<link rel="stylesheet" href="assets/css/app.css">';
        echo '</head><body><div class="container">';
        echo '<h1>Assessment Rápido de Cumplimiento</h1>';
        echo '<p class="text-muted">GDPR · NIS2 · DORA · ENS — Ejecución local, sin almacenamiento ni terceros.</p>';

        if (!empty($flashErrors)) {
            echo '<div class="card" style="border-color:#dc3545"><strong>Corrige los siguientes errores:</strong><ul>';
            foreach ($flashErrors as $e) {
                echo '<li>' . htmlspecialchars($e) . '</li>';
            }
            echo '</ul></div>';
        }

        echo '<form method="post" action="consent.php" autocomplete="off" novalidate>';
        echo '<input type="hidden" name="_csrf" value="' . htmlspecialchars(Csrf::token()) . '">';

        // 1) Datos de la empresa
        echo '<div class="card">';
        echo '  <h2>1) Datos de la empresa</h2>';
        echo '  <div class="row">';
        echo '    <div class="col-6">';
        echo '      <label for="nif">NIF/NIE/CIF <span class="text-muted small">(no se enviará a la IA)</span></label>';
        echo '      <input type="text" id="nif" name="nif" placeholder="A12345678" ';
        echo '             pattern="^(?:[0-9]{8}[A-HJ-NP-TV-Z]|[XYZ][0-9]{7}[A-HJ-NP-TV-Z]|[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[0-9A-J])$" required>';
        echo '    </div>';
        echo '    <div class="col-6">';
        echo '      <label for="name">Nombre de la Empresa <span class="text-muted small">(no se enviará a la IA)</span></label>';
        echo '      <input type="text" id="name" name="name" placeholder="Mi Empresa SL" maxlength="' . $maxCompanyNameLen . '" ';
        echo '             pattern="^[A-Za-zÀ-ÖØ-öø-ÿ0-9,\\-\\s]{2,}$" required>';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="row">';
        echo '    <div class="col-6">';
        echo '      <label for="ctype">Tipo de Empresa</label>';
        echo '      <select id="ctype" name="companyType" required>';
        foreach ($companyTypes as $t) {
            echo '<option value="' . htmlspecialchars((string)$t) . '">' . htmlspecialchars((string)$t) . '</option>';
        }
        echo '      </select>';
        echo '    </div>';
        echo '    <div class="col-6">';
        echo '      <label for="size">Tamaño (Nº Empleados)</label>';
        echo '      <input type="number" id="size" name="companySize" placeholder="50" pattern="^[1-9][0-9]{0,5}$" min="1" step="1" required>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';

        // 2) Normativas a analizar
        echo '<div class="card">';
        echo '  <h2>2) Normativas a analizar</h2>';
        echo '  <div class="row">';
        foreach ($allowedNormatives as $n) {
            $val = htmlspecialchars((string)$n);
            echo '    <label style="display:block"><input type="checkbox" name="normatives[]" value="' . $val . '"> ' . $val . '</label>';
        }
        echo '  </div>';
        echo '  <p class="small text-muted">Debe seleccionar al menos una normativa.</p>';
        echo '</div>';

        // 3) IA opcional
        echo '<div class="card">';
        echo '  <h2>3) Opcional: Análisis con IA (BYOK)</h2>';
        echo '  <label><input type="checkbox" id="useAi" name="useAi" value="1"> Usar IA para el análisis técnico (sin numeraciones)</label>';
        echo '  <div id="aiBlock" style="display:none; margin-top:8px">';
        echo '    <div class="row">';
        echo '      <div class="col-6">';
        echo '        <label for="provider">Proveedor</label>';
        echo '        <select id="provider" name="provider">';
        echo '          <option value="">-- Seleccione --</option>';
        foreach (CONFIG['ai']['providers'] as $key => $p) {
            echo '<option value="' . htmlspecialchars((string)$key) . '">' . htmlspecialchars((string)$p['label']) . '</option>';
        }
        echo '        </select>';
        echo '      </div>';
        echo '      <div class="col-6">';
        echo '        <label for="token">API Token (no se almacena)</label>';
        echo '        <input type="text" id="token" name="token" placeholder="••••••••••••••••••" pattern="^[A-Za-z0-9_\\-]{20,200}$">';
        echo '      </div>';
        echo '    </div>';
        echo '  </div>';
        echo '  <p class="small text-muted">El token es propiedad del usuario y no se envía a terceros distintos de su proveedor de IA.</p>';
        echo '</div>';

        // Acciones
        echo '<div class="card">';
        echo '  <button type="submit" class="btn">Continuar</button>';
        echo '  <a class="btn secondary" href="#" onclick="history.back();return false;">Cancelar</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
        echo '<script src="assets/js/app.js"></script>';
        echo '</body></html>';
    }
}

// Punto de entrada
(new HomeController())->run();
