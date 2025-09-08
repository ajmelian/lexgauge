<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Http;
use App\Domain\ComplianceEngine;
use App\Domain\PiiScrubber;
use App\Ai\ChatGptClient;
use App\Ai\ClaudeClient;

/**
 * Nombre: ReportController
 * Descripción de la funcionalidad: Controlador de la página de informe.
 *   Valida CSRF, lee el formulario de sesión, calcula métricas a partir de las respuestas
 *   y (opcionalmente) solicita un análisis a un proveedor de IA (BYOK). Finalmente
 *   renderiza el informe en 5 bloques en HTML5 (con soporte Bootstrap local).
 * Parámetros de entrada:
 *   - Entrada HTTP POST: _csrf (string), answers (array<string,mixed>) opcional.
 *   - Variables de sesión: $_SESSION['form'] (array con metadatos del formulario).
 * Salida:
 *   - HTML completo del informe enviado al navegador (echo).
 * Método de uso:
 *   - Instanciar y ejecutar: (new ReportController())->run();
 * Fecha de desarrollo: 2025-09-07
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class ReportController
{
    /**
     * Nombre: run
     * Descripción: Orquesta la validación, cálculo y renderizado del informe.
     * @return void
     * Método de uso: (new ReportController())->run();
     * Fecha de desarrollo: 2025-09-07 | Autor: Aythami Melián Perdomo
     * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function run(): void
    {
        Http::noCache();
        $this->assertValidCsrfOrFail();

        /** @var array<string,mixed>|null $form */
        $form = $_SESSION['form'] ?? null;
        if ($form === null) {
            Http::redirect('index.php');
        }

        /** @var array<string,mixed> $answers */
        $answers = (isset($_POST['answers']) && is_array($_POST['answers'])) ? $_POST['answers'] : [];

        $engine = $this->loadEngine();

        [$scores, $todo] = $this->computeScoresAndTodo($engine, $form, $answers);

        $analysis = $this->buildAnalysisIfEnabled($engine, $form, $answers, $scores);

        $this->render($form, $answers, $scores, $todo, $analysis, $engine);
    }

    /**
     * Nombre: assertValidCsrfOrFail
     * Descripción: Valida el token CSRF del POST y, si es inválido, devuelve 400 y finaliza.
     * @return void
     * Método de uso: $this->assertValidCsrfOrFail();
     * Fecha de desarrollo: 2025-09-07 | Autor: Aythami Melián Perdomo
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
     * Nombre: loadEngine
     * Descripción: Carga el motor de preguntas desde el JSON si existe.
     * @return ComplianceEngine|null Instancia del motor o null si no fue posible cargarla.
     * Método de uso: $engine = $this->loadEngine();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function loadEngine(): ?ComplianceEngine
    {
        $file = CONFIG['app']['questionsFile'];
        if (!is_string($file) || !is_file($file)) {
            return null;
        }
        try {
            $json = (string)file_get_contents($file);
            return ComplianceEngine::fromJson($json);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Nombre: computeScoresAndTodo
     * Descripción: Calcula métricas de cumplimiento y genera TO-DO priorizado.
     * @param ComplianceEngine|null $engine Motor de evaluación (puede ser null).
     * @param array<string,mixed> $form Datos del formulario en sesión.
     * @param array<string,mixed> $answers Respuestas del cuestionario.
     * @return array{0: array{normatives: array<string,float>, blocks: array<string,array<string,float>>}, 1: array<int,array<string,mixed>>}
     * Método de uso: [$scores,$todo] = $this->computeScoresAndTodo($engine,$form,$answers);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function computeScoresAndTodo(?ComplianceEngine $engine, array $form, array $answers): array
    {
        $scores = ['normatives' => [], 'blocks' => []];
        $todo   = [];

        if ($engine !== null && $engine->hasQuestions()) {
            /** @var array{normatives: array<string,float>, blocks: array<string,array<string,float>>} $scores */
            $scores = $engine->scoreAnswers($answers);
            /** @var array<int,array<string,mixed>> $todo */
            $todo   = $engine->buildTodo($answers);
        } else {
            // Sin preguntas: todas las métricas a 0 para las normativas seleccionadas
            /** @var array<int,string> $normatives */
            $normatives = (array)($form['normatives'] ?? []);
            foreach ($normatives as $n) {
                if (is_string($n)) {
                    $scores['normatives'][$n] = 0.0;
                    $scores['blocks'][$n] = [];
                }
            }
        }
        return [$scores, $todo];
    }

    /**
     * Nombre: buildAnalysisIfEnabled
     * Descripción: Ejecuta el análisis de IA si el usuario lo activó; en caso contrario devuelve mensaje por defecto.
     * @param ComplianceEngine|null $engine Motor de evaluación (puede ser null).
     * @param array<string,mixed> $form Datos del formulario (incluye flags de IA).
     * @param array<string,mixed> $answers Respuestas del cuestionario.
     * @param array{normatives: array<string,float>, blocks: array<string,array<string,float>>} $scores Métricas calculadas.
     * @return string Texto del análisis (redactado sin numeraciones).
     * Método de uso: $text = $this->buildAnalysisIfEnabled($engine,$form,$answers,$scores);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function buildAnalysisIfEnabled(?ComplianceEngine $engine, array $form, array $answers, array $scores): string
    {
        $useAi    = (bool)($form['useAi'] ?? false);
        $provider = is_string($form['provider'] ?? null) ? (string)$form['provider'] : null;
        $token    = is_string($form['token'] ?? null) ? (string)$form['token'] : null;

        if (!$useAi || $provider === null || $token === null || $engine === null) {
            return 'No se ha ejecutado análisis con IA.';
        }

        $prompt = $engine->buildAiPrompt(
            [
                'companyUuid'        => (string)$form['companyUuid'],
                'companyType'        => (string)$form['companyType'],
                'companySize'        => (int)$form['companySize'],
                'selectedNormatives' => (array)$form['normatives'],
            ],
            $scores,
            $answers
        );

        // Scrubber de PII (versión ajustada para reducir falsos positivos)
        $scrubber = new PiiScrubber();
        $check = $scrubber->scanArray(['prompt' => $prompt]);

        if (!$check['ok']) {
            $causas = implode(', ', array_unique($check['matches']));
            return 'Se detectaron posibles datos personales (patrones: ' . htmlspecialchars($causas) . '); se omitió el envío a IA.';
        }

        try {
            return $this->callAiProvider($provider, $token, $prompt);
        } catch (Throwable $e) {
            return 'No fue posible conectar con la IA: ' . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Nombre: callAiProvider
     * Descripción: Abstracta la invocación al proveedor de IA seleccionado.
     * @param string $provider Identificador del proveedor ('chatgpt'|'claude').
     * @param string $token Token BYOK del usuario final.
     * @param string $prompt Prompt ya anonimizado.
     * @return string Respuesta textual del proveedor de IA.
     * Método de uso: $text = $this->callAiProvider('chatgpt', $token, $prompt);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function callAiProvider(string $provider, string $token, string $prompt): string
    {
        switch ($provider) {
            case 'chatgpt':
                $client = new ChatGptClient(CONFIG['ai']['providers']['chatgpt']['endpoint']);
                return $client->analyze($token, $prompt, ['model' => CONFIG['ai']['providers']['chatgpt']['defaultModel']]);
            case 'claude':
                $client = new ClaudeClient(
                    CONFIG['ai']['providers']['claude']['endpoint'],
                    CONFIG['ai']['providers']['claude']['anthropicVersion']
                );
                return $client->analyze($token, $prompt, ['model' => CONFIG['ai']['providers']['claude']['defaultModel']]);
            default:
                return 'Proveedor IA no soportado.';
        }
    }

    /**
     * Nombre: render
     * Descripción: Construye y envía al navegador el HTML del informe (5 bloques).
     * @param array<string,mixed> $form Datos del formulario (empresa y selección).
     * @param array<string,mixed> $answers Respuestas suministradas por el usuario.
     * @param array{normatives: array<string,float>, blocks: array<string,array<string,float>>} $scores Métricas por normativa/bloque.
     * @param array<int,array<string,mixed>> $todo Acciones priorizadas.
     * @param string $analysis Texto del bloque de análisis técnico.
     * @param ComplianceEngine|null $engine Motor para resolver textos de preguntas al mostrar trazabilidad.
     * @return void
     * Método de uso: $this->render($form,$answers,$scores,$todo,$analysis,$engine);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function render(array $form, array $answers, array $scores, array $todo, string $analysis, ?ComplianceEngine $engine): void
    {
        /** @var string $title */
        $title = (string)CONFIG['app']['title'];

        echo '<!doctype html><html lang="es"><head>';
        echo '<meta charset="utf-8"><title>Informe — ' . htmlspecialchars($title) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<link rel="stylesheet" href="assets/bootstrap/bootstrap.min.css">';
        echo '<link rel="stylesheet" href="assets/css/app.css">';
        echo '</head><body><div class="container">';
        echo '<h1>Informe de Cumplimiento</h1>';

        // 1) Datos de la empresa
        echo '<div class="card">';
        echo '<h2>1) Datos de la empresa</h2>';
        echo '<p><strong>Identificación:</strong> ' . htmlspecialchars((string)$form['name']) . ' (' . htmlspecialchars((string)$form['nif']) . ')</p>';
        echo '<p><strong>Alias para IA:</strong> ' . htmlspecialchars((string)$form['companyUuid']) . '</p>';
        echo '<p><strong>Tipo / Tamaño:</strong> ' . htmlspecialchars((string)$form['companyType']) . ' · ' . (int)$form['companySize'] . ' empleados</p>';
        echo '</div>';

        // 2) Gráficos por normativa y bloques
        echo '<div class="card"><h2>2) Cumplimiento por normativa y bloques</h2>';
        if (!empty($scores['normatives'])) {
            foreach ($scores['normatives'] as $normative => $pct) {
                echo '<h3>' . htmlspecialchars((string)$normative) . '</h3>';
                echo $this->progressBar((float)$pct);
                if (!empty($scores['blocks'][$normative])) {
                    echo '<div class="row">';
                    foreach ($scores['blocks'][$normative] as $block => $bpct) {
                        echo '<div class="col-6"><div class="small"><strong>' . htmlspecialchars((string)$block) . '</strong></div>' . $this->progressBar((float)$bpct) . '</div>';
                    }
                    echo '</div>';
                }
                echo '<hr>';
            }
        } else {
            echo '<p>No hay datos de cumplimiento porque no se cargaron preguntas.</p>';
        }
        echo '</div>';

        // 3) Análisis técnico
        echo '<div class="card"><h2>3) Análisis técnico</h2>';
        echo '<p>' . nl2br(htmlspecialchars($analysis)) . '</p>';
        echo '</div>';

        // 4) TO-DO priorizado
        echo '<div class="card"><h2>4) TO-DO priorizado</h2>';
        if (!empty($todo)) {
            foreach ($todo as $item) {
                $norm = htmlspecialchars((string)($item['normative'] ?? ''));
                $blk  = htmlspecialchars((string)($item['block'] ?? ''));
                $pri  = (int)($item['priority'] ?? 0);
                $qtxt = htmlspecialchars((string)($item['question'] ?? ''));
                $act  = htmlspecialchars((string)($item['action'] ?? ''));
                echo '<div style="margin-bottom:8px"><span class="badge">'.$norm.'</span> <strong>'.$blk.'</strong> — Prioridad '.$pri.'<br><em>'.$qtxt.'</em><br>'.$act.'</div>';
            }
        } else {
            echo '<p>No hay acciones porque no se cargaron preguntas o no hay brechas detectadas.</p>';
        }
        echo '</div>';

        // 5) Trazabilidad de preguntas y respuestas
        echo '<div class="card"><h2>5) Cuestionario y respuestas</h2>';
        if (!empty($answers) && $engine !== null) {
            /** Obtener banco de preguntas para presentar texto + respuesta */
            $bank = $this->getQuestionBank($engine);
            echo '<ul>';
            foreach ($bank as $q) {
                $id = (string)($q['id'] ?? '');
                if ($id === '' || !array_key_exists($id, $answers)) {
                    continue;
                }
                $ans = $answers[$id];
                $disp = is_numeric($ans)
                    ? (string)$ans
                    : ((filter_var($ans, FILTER_VALIDATE_BOOLEAN)) ? 'Sí' : 'No');

                $norm = htmlspecialchars((string)($q['normative'] ?? ''));
                $blk  = htmlspecialchars((string)($q['block'] ?? ''));
                $txt  = htmlspecialchars((string)($q['text'] ?? ''));
                echo '<li><strong>'.$norm.' · '.$blk.'</strong>: '.$txt.'<br><span class="small">Respuesta: '.htmlspecialchars($disp).'</span></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No hay respuestas que mostrar.</p>';
        }
        echo '</div>';

        echo '<div class="card"><a class="btn" href="index.php">Nuevo análisis</a></div>';
        echo '</div></body></html>';
    }

    /**
     * Nombre: progressBar
     * Descripción: Genera una barra de progreso HTML con porcentaje numérico.
     * @param float $pct Porcentaje entre 0 y 100.
     * @return string HTML listo para incrustar.
     * Método de uso: echo $this->progressBar(75.0);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function progressBar(float $pct): string
    {
        $pct = max(0.0, min(100.0, $pct));
        $w = number_format($pct, 2, '.', '');
        return '<div class="progress"><div class="progress-bar" style="width: '.$w.'%"></div></div><div class="small">'.$w.'%</div>';
    }

    /**
     * Nombre: getQuestionBank
     * Descripción: Recupera el banco de preguntas del motor para poder mostrar el texto en la trazabilidad.
     * @param ComplianceEngine $engine Motor de evaluación.
     * @return array<int,array<string,mixed>> Array de preguntas materializadas.
     * Método de uso: $bank = $this->getQuestionBank($engine);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    private function getQuestionBank(ComplianceEngine $engine): array
    {
        try {
            $ref = new ReflectionClass($engine);
            $prop = $ref->getProperty('questionBank');
            $prop->setAccessible(true);
            /** @var array<int,array<string,mixed>> $bank */
            $bank = (array)$prop->getValue($engine);
            return $bank;
        } catch (Throwable $e) {
            return [];
        }
    }
}

// Punto de entrada
(new ReportController())->run();
