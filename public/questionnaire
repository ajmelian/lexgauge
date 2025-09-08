<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Http;
use App\Domain\ComplianceEngine;

/**
 * Nombre: QuestionnaireController
 * Descripción de la funcionalidad: Controlador de la página de cuestionario.
 *   Valida CSRF y consentimiento, recupera el formulario en sesión,
 *   carga el banco de preguntas y presenta el cuestionario por bloques
 *   (o un aviso si no hay preguntas). Mantiene el flujo seguro y sin
 *   almacenamiento de datos personales fuera de sesión.
 * Parámetros de entrada:
 *   - HTTP POST: _csrf (string), consent (string '1').
 *   - Variables de sesión: $_SESSION['form'] (array con datos de empresa y selección).
 * Salida:
 *   - HTML renderizado con el formulario del cuestionario o aviso de ausencia de preguntas.
 * Método de uso:
 *   - (new QuestionnaireController())->run();
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class QuestionnaireController
{
    /**
     * Nombre: run
     * Descripción: Orquesta validaciones, carga el motor y genera la vista HTML.
     * @return void
     * Método de uso: (new QuestionnaireController())->run();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function run(): void
    {
        Http::noCache();
        $this->assertValidCsrfOrFail();
        $this->assertConsentOrFail();

        /** @var array<string,mixed>|null $form */
        $form = $_SESSION['form'] ?? null;
        if ($form === null) {
            Http::redirect('index.php');
        }

        $engine = $this->loadEngine();
        $selected = $this->selectQuestions($engine, (array)($form['normatives'] ?? []), (int)CONFIG['app']['questionCount']);

        Csrf::generate();
        $this->renderPage($selected);
    }

    /**
     * Nombre: assertValidCsrfOrFail
     * Descripción: Comprueba el token CSRF del POST; si es inválido responde 400 y termina.
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
     * Nombre: assertConsentOrFail
     * Descripción: Verifica que el usuario marcó el checkbox de consentimiento.
     * @return void
     * Método de uso: $this->assertConsentOrFail();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function assertConsentOrFail(): void
    {
        if (empty($_POST['consent'])) {
            http_response_code(400);
            exit('Debe marcar el consentimiento para continuar.');
        }
    }

    /**
     * Nombre: loadEngine
     * Descripción: Intenta cargar el motor de cumplimiento desde el JSON configurado.
     * @return ComplianceEngine|null Instancia válida o null si no hay preguntas.
     * Método de uso: $engine = $this->loadEngine();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function loadEngine(): ?ComplianceEngine
    {
        $file = CONFIG['app']['questionsFile'];
        if (!is_string($file) || !is_file($file)) {
            return null;
        }
        try {
            $json = (string)file_get_contents($file);
            $engine = ComplianceEngine::fromJson($json);
            return $engine->hasQuestions() ? $engine : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Nombre: selectQuestions
     * Descripción: Selecciona preguntas por normativas hasta el límite indicado.
     * @param ComplianceEngine|null $engine Motor de cumplimiento (puede ser null).
     * @param array<int,string> $normatives Normativas seleccionadas por el usuario.
     * @param int $limit Límite de preguntas a presentar.
     * @return array<int,array<string,mixed>> Lista de preguntas materializadas o array vacío.
     * Método de uso: $selected = $this->selectQuestions($engine,$normatives,25);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function selectQuestions(?ComplianceEngine $engine, array $normatives, int $limit): array
    {
        if ($engine === null) {
            return [];
        }
        return $engine->selectQuestions($normatives, $limit);
    }

    /**
     * Nombre: renderPage
     * Descripción: Renderiza la página del cuestionario (con o sin preguntas).
     * @param array<int,array<string,mixed>> $selected Preguntas seleccionadas.
     * @return void
     * Método de uso: $this->renderPage($selected);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function renderPage(array $selected): void
    {
        $title = (string)CONFIG['app']['title'];

        echo '<!doctype html><html lang="es"><head>';
        echo '<meta charset="utf-8"><title>Cuestionario — ' . htmlspecialchars($title) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<link rel="stylesheet" href="assets/bootstrap/bootstrap.min.css">';
        echo '<link rel="stylesheet" href="assets/css/app.css">';
        echo '</head><body><div class="container">';
        echo '<h1>Cuestionario</h1>';

        if (empty($selected)) {
            $this->renderNoQuestions();
        } else {
            $this->renderQuestionsForm($selected);
        }

        echo '</div><script src="assets/js/app.js"></script></body></html>';
    }

    /**
     * Nombre: renderNoQuestions
     * Descripción: Muestra aviso cuando no se encuentran preguntas y permite continuar.
     * @return void
     * Método de uso: $this->renderNoQuestions();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function renderNoQuestions(): void
    {
        echo '<div class="card"><p><strong>Atención:</strong> No se encontraron preguntas. Aún no existe <code>data/questions.json</code> o está vacío.</p>';
        echo '<p>Puede continuar para generar un informe básico sin preguntas (todos los porcentajes serán 0%).</p></div>';
        echo '<form method="post" action="report.php">';
        echo '<input type="hidden" name="_csrf" value="' . htmlspecialchars(Csrf::token()) . '">';
        echo '<button class="btn" type="submit">Continuar sin preguntas</button> <a class="btn secondary" href="index.php">Volver</a>';
        echo '</form>';
    }

    /**
     * Nombre: renderQuestionsForm
     * Descripción: Imprime el formulario con las preguntas seleccionadas (soporta yes/no y escala 0–5).
     * @param array<int,array<string,mixed>> $selected Preguntas seleccionadas.
     * @return void
     * Método de uso: $this->renderQuestionsForm($selected);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function renderQuestionsForm(array $selected): void
    {
        echo '<form method="post" action="report.php">';
        echo '<input type="hidden" name="_csrf" value="' . htmlspecialchars(Csrf::token()) . '">';

        foreach ($selected as $q) {
            $norm = htmlspecialchars((string)($q['normative'] ?? ''));
            $blk  = htmlspecialchars((string)($q['block'] ?? ''));
            $txt  = htmlspecialchars((string)($q['text'] ?? ''));
            $id   = htmlspecialchars((string)($q['id'] ?? ''));
            $type = (string)($q['answerType'] ?? 'yes_no');

            echo '<div class="card">';
            echo '<div class="small text-muted">' . $norm . ' · ' . $blk . '</div>';
            echo '<label>' . $txt . '</label>';
            echo '<div style="margin-top:8px">';
            if ($type === 'scale_0_5') {
                echo '<select name="answers[' . $id . ']" required>';
                echo '<option value="0">0 - No implementado</option>';
                echo '<option value="1">1</option>';
                echo '<option value="2">2</option>';
                echo '<option value="3">3</option>';
                echo '<option value="4">4</option>';
                echo '<option value="5">5 - Completamente implementado</option>';
                echo '</select>';
            } else {
                echo '<label><input type="radio" name="answers[' . $id . ']" value="1" required> Sí</label> ';
                echo '<label><input type="radio" name="answers[' . $id . ']" value="0"> No</label>';
            }
            echo '</div></div>';
        }

        echo '<div class="card"><button type="submit" class="btn">Generar informe</button> <a class="btn secondary" href="index.php">Cancelar</a></div>';
        echo '</form>';
    }
}

// Punto de entrada
(new QuestionnaireController())->run();
