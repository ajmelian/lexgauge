<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Http;
use App\Support\Validator;

/**
 * Nombre: ConsentController
 * Descripción de la funcionalidad: Controlador de la página de consentimiento previo.
 *   Valida CSRF y los datos del formulario inicial, normaliza entradas,
 *   persiste únicamente en sesión los metadatos necesarios y presenta
 *   el resumen de datos que, en su caso, se enviarían a la IA (BYOK).
 *   No almacena información en BBDD ni envía NIF/NIE/CIF ni nombre real a la IA.
 * Parámetros de entrada:
 *   - HTTP POST:
 *       _csrf (string),
 *       nif (string),
 *       name (string),
 *       companyType (string),
 *       companySize (string|int),
 *       normatives (array<string>),
 *       useAi ('1'|null),
 *       provider (string|null),
 *       token (string|null)
 * Salida:
 *   - HTML de la página de consentimiento con resumen y checkbox de autorización.
 * Método de uso:
 *   - (new ConsentController())->run();
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class ConsentController
{
    /**
     * Nombre: run
     * Descripción: Orquesta validaciones, prepara la sesión y renderiza la vista de consentimiento.
     * @return void
     * Método de uso: (new ConsentController())->run();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function run(): void
    {
        Http::noCache();
        $this->assertValidCsrfOrFail();

        [$data, $errors] = $this->parseAndValidateInput($_POST);

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            Http::redirect('index.php');
        }

        $uuid = bin2hex(random_bytes(16));
        $this->persistFormToSession($uuid, $data);

        Csrf::generate();
        $this->render($uuid, $data['companyType'], (int)$data['companySize'], $data['normatives']);
    }

    /**
     * Nombre: assertValidCsrfOrFail
     * Descripción: Valida el token CSRF del POST; si es inválido, responde 400 y termina.
     * @return void
     * Método de uso: $this->assertValidCsrfOrFail();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function assertValidCsrfOrFail(): void
    {
        $token = (string)($_POST['_csrf'] ?? '');
        if (!Csrf::validate($token)) {
            http_response_code(400);
            exit('CSRF token inválido.');
        }
    }

    /**
     * Nombre: parseAndValidateInput
     * Descripción: Extrae, normaliza y valida las entradas del formulario inicial.
     * @param array<string,mixed> $post Input de $_POST.
     * @return array{0: array<string,mixed>, 1: array<int,string>} Data normalizada y lista de errores.
     * Método de uso: [$data,$errors] = $this->parseAndValidateInput($_POST);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function parseAndValidateInput(array $post): array
    {
        $errors = [];

        $nif         = (string)($post['nif'] ?? '');
        $name        = (string)($post['name'] ?? '');
        $companyType = (string)($post['companyType'] ?? '');
        $companySize = (string)($post['companySize'] ?? '');
        $normatives  = (isset($post['normatives']) && is_array($post['normatives'])) ? $post['normatives'] : [];

        $useAi       = (isset($post['useAi']) && $post['useAi'] === '1');
        $provider    = Validator::aiProvider($post['provider'] ?? null);
        $token       = Validator::apiToken($post['token'] ?? null);

        if (!Validator::isNifNieCif($nif)) {
            $errors[] = 'NIF/NIE/CIF inválido.';
        }
        if (!Validator::isCompanyName($name)) {
            $errors[] = 'Nombre de empresa inválido.';
        }
        if (!Validator::isCompanyType($companyType)) {
            $errors[] = 'Tipo de empresa no permitido.';
        }
        if (!Validator::isEmployeeSize($companySize)) {
            $errors[] = 'Tamaño de empresa inválido.';
        }
        $normatives = Validator::normatives($normatives);
        if (!$normatives) {
            $errors[] = 'Debe seleccionar al menos una normativa.';
        }

        if ($useAi) {
            if ($provider === null) {
                $errors[] = 'Proveedor IA no válido.';
            }
            if ($token === null) {
                $errors[] = 'API Token IA inválido.';
            }
        } else {
            $provider = null;
            $token = null;
        }

        $data = [
            'nif'         => $nif,
            'name'        => $name,
            'companyType' => $companyType,
            'companySize' => (int)$companySize,
            'normatives'  => $normatives,
            'useAi'       => $useAi,
            'provider'    => $provider,
            'token'       => $token,
        ];

        return [$data, $errors];
    }

    /**
     * Nombre: persistFormToSession
     * Descripción: Guarda en sesión los metadatos del formulario necesarios para el flujo.
     * @param string $uuid Alias anónimo de la empresa para IA.
     * @param array<string,mixed> $data Datos validados del formulario.
     * @return void
     * Método de uso: $this->persistFormToSession($uuid,$data);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function persistFormToSession(string $uuid, array $data): void
    {
        $_SESSION['form'] = [
            'companyUuid' => $uuid,
            'nif'         => (string)$data['nif'],
            'name'        => (string)$data['name'],
            'companyType' => (string)$data['companyType'],
            'companySize' => (int)$data['companySize'],
            'normatives'  => (array)$data['normatives'],
            'useAi'       => (bool)$data['useAi'],
            'provider'    => $data['provider'], // string|null
            'token'       => $data['token'],    // string|null
        ];
    }

    /**
     * Nombre: render
     * Descripción: Renderiza la página de consentimiento con el detalle de datos a enviar a la IA.
     * @param string $uuid Alias anónimo de la empresa.
     * @param string $companyType Tipo de empresa.
     * @param int $companySize Número de empleados.
     * @param array<int,string> $normatives Normativas seleccionadas.
     * @return void
     * Método de uso: $this->render($uuid,$companyType,$companySize,$normatives);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function render(string $uuid, string $companyType, int $companySize, array $normatives): void
    {
        $title = (string)CONFIG['app']['title'];

        echo '<!doctype html><html lang="es"><head>';
        echo '<meta charset="utf-8"><title>Consentimiento — ' . htmlspecialchars($title) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<link rel="stylesheet" href="assets/bootstrap/bootstrap.min.css">';
        echo '<link rel="stylesheet" href="assets/css/app.css">';
        echo '</head><body><div class="container">';
        echo '<h1>Consentimiento previo al análisis</h1>';

        echo '<div class="card">';
        echo '  <h2>Datos que se enviarán a la IA (si activaste IA)</h2>';
        echo '  <blockquote>';
        echo '    <strong>Alias de empresa:</strong> ' . htmlspecialchars($uuid) . '<br>';
        echo '    <strong>Tipo:</strong> ' . htmlspecialchars($companyType) . '<br>';
        echo '    <strong>Tamaño:</strong> ' . (int)$companySize . ' empleados<br>';
        echo '    <strong>Normativas:</strong> ';
        foreach ($normatives as $n) {
            echo '<span class="badge">' . htmlspecialchars((string)$n) . '</span> ';
        }
        echo '    <br>';
        echo '    <strong>Respuestas:</strong> Solo opciones cerradas (sí/no/escala). <em>No se enviarán NIF/NIE/CIF ni nombre real.</em>';
        echo '  </blockquote>';
        echo '  <p class="text-muted small">Este proceso se ejecuta en su equipo. El token y la conexión con el proveedor de IA son responsabilidad del usuario.</p>';
        echo '</div>';

        echo '<form method="post" action="questionnaire.php">';
        echo '  <input type="hidden" name="_csrf" value="' . htmlspecialchars(Csrf::token()) . '">';
        echo '  <div class="card"><label><input type="checkbox" id="consentCheckbox" name="consent" value="1"> Confirmo que he leído y acepto el envío de los datos anteriores a mi proveedor de IA (si he activado IA).</label></div>';
        echo '  <div class="card"><button id="btnProceed" type="submit" class="btn">Iniciar test</button> <a class="btn secondary" href="index.php">Volver</a></div>';
        echo '</form>';

        echo '</div><script src="assets/js/app.js"></script></body></html>';
    }
}

// Punto de entrada
(new ConsentController())->run();
