<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Nombre: ComplianceEngine
 * Descripción de la funcionalidad:
 *   Motor de evaluación para el assessment de cumplimiento (GDPR, NIS2, DORA, ENS).
 *   Carga el banco de preguntas desde JSON, selecciona preguntas por normativa,
 *   calcula porcentajes de cumplimiento por normativa y por bloque, construye
 *   un TO-DO priorizado y genera el prompt para la IA (sin PII).
 *
 * Parámetros de entrada (a través de métodos):
 *   - fromJson(string $json): self
 *   - selectQuestions(array $normatives, int $limit): array
 *   - scoreAnswers(array $answers): array{normatives: array<string,float>, blocks: array<string,array<string,float>>}
 *   - buildTodo(array $answers): array<int,array<string,mixed>>
 *   - buildAiPrompt(array $meta, array $scores, array $answers): string
 *
 * Salida:
 *   - Estructuras con métricas y acciones, y un prompt de IA listo para enviar.
 *
 * Método de uso:
 *   - $engine = ComplianceEngine::fromJson($json);
 *   - $selected = $engine->selectQuestions(['GDPR','NIS2'], 25);
 *   - [$scores, $todo] = [$engine->scoreAnswers($_POST['answers'] ?? []), $engine->buildTodo($_POST['answers'] ?? [])];
 *
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class ComplianceEngine
{
    /**
     * @var array<int,array{id:string,normative:string,block:string,text:string,weight:int,answerType:string}>
     */
    private array $questionBank = [];

    /**
     * Nombre: fromJson
     * Descripción: Construye una instancia a partir del contenido JSON del banco de preguntas.
     * @param string $json Contenido JSON válido con claves "version" y "questions".
     * @return self Instancia de motor con preguntas cargadas.
     * Método de uso: $engine = ComplianceEngine::fromJson(file_get_contents('data/questions.json'));
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public static function fromJson(string $json): self
    {
        $engine = new self();
        /** @var array<string,mixed> $data */
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $engine;
        }
        $questions = $data['questions'] ?? null;
        if (!is_array($questions)) {
            return $engine;
        }

        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $id         = isset($q['id']) && is_string($q['id']) ? $q['id'] : null;
            $normative  = isset($q['normative']) && is_string($q['normative']) ? $q['normative'] : null;
            $block      = isset($q['block']) && is_string($q['block']) ? $q['block'] : null;
            $text       = isset($q['text']) && is_string($q['text']) ? $q['text'] : null;
            $weight     = isset($q['weight']) && is_numeric($q['weight']) ? (int)$q['weight'] : 1;
            $answerType = isset($q['answerType']) && is_string($q['answerType']) ? $q['answerType'] : 'yes_no';

            if ($id && $normative && $block && $text) {
                $engine->questionBank[] = [
                    'id'         => $id,
                    'normative'  => $normative,
                    'block'      => $block,
                    'text'       => $text,
                    'weight'     => max(1, $weight),
                    'answerType' => $answerType,
                ];
            }
        }
        return $engine;
    }

    /**
     * Nombre: hasQuestions
     * Descripción: Indica si hay preguntas cargadas en el motor.
     * @return bool true si existen preguntas.
     * Método de uso: if(!$engine->hasQuestions()){ ... }
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function hasQuestions(): bool
    {
        return !empty($this->questionBank);
    }

    /**
     * Nombre: getQuestionBank
     * Descripción: Devuelve el banco de preguntas materializado.
     * @return array<int,array{id:string,normative:string,block:string,text:string,weight:int,answerType:string}>
     * Método de uso: $bank = $engine->getQuestionBank();
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function getQuestionBank(): array
    {
        return $this->questionBank;
    }

    /**
     * Nombre: selectQuestions
     * Descripción: Filtra por normativas y devuelve hasta $limit preguntas, ponderando por peso.
     *              Estrategia: primero filtra por normativa, luego ordena por peso DESC y mantiene orden estable.
     * @param array<int,string> $normatives Lista de normativas seleccionadas.
     * @param int $limit Número máximo de preguntas.
     * @return array<int,array<string,mixed>> Preguntas seleccionadas.
     * Método de uso: $qs = $engine->selectQuestions(['GDPR'], 25);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function selectQuestions(array $normatives, int $limit): array
    {
        $limit = max(1, $limit);
        $set = array_filter(
            $this->questionBank,
            static fn(array $q): bool => in_array($q['normative'], $normatives, true)
        );

        usort(
            $set,
            static function (array $a, array $b): int {
                // Peso DESC; si empatan, mantener estabilidad por id
                $dw = ($b['weight'] <=> $a['weight']);
                return $dw !== 0 ? $dw : strcmp((string)$a['id'], (string)$b['id']);
            }
        );

        return array_slice(array_values($set), 0, $limit);
    }

    /**
     * Nombre: scoreAnswers
     * Descripción: Calcula el porcentaje de cumplimiento por normativa y por bloque.
     * @param array<string,mixed> $answers Mapa idPregunta => valor (0/1 o 0..5).
     * @return array{normatives: array<string,float>, blocks: array<string,array<string,float>>}
     * Método de uso: $scores = $engine->scoreAnswers($_POST['answers'] ?? []);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function scoreAnswers(array $answers): array
    {
        $normativeTotals = []; // [norm => ['achieved'=>float, 'max'=>float]]
        $blockTotals     = []; // [norm => [block => ['achieved'=>float,'max'=>float]]]

        foreach ($this->questionBank as $q) {
            $id = $q['id'];
            if (!array_key_exists($id, $answers)) {
                continue;
            }
            $norm = $q['normative'];
            $blk  = $q['block'];
            $w    = (float)$q['weight'];
            $at   = $q['answerType'];

            $scoreUnit = $this->normalizeAnswerScore($at, $answers[$id]) * $w;

            // Normativa
            if (!isset($normativeTotals[$norm])) {
                $normativeTotals[$norm] = ['achieved' => 0.0, 'max' => 0.0];
            }
            $normativeTotals[$norm]['achieved'] += $scoreUnit;
            $normativeTotals[$norm]['max']      += $w;

            // Bloque
            if (!isset($blockTotals[$norm][$blk])) {
                $blockTotals[$norm][$blk] = ['achieved' => 0.0, 'max' => 0.0];
            }
            $blockTotals[$norm][$blk]['achieved'] += $scoreUnit;
            $blockTotals[$norm][$blk]['max']      += $w;
        }

        $normativePct = [];
        foreach ($normativeTotals as $norm => $pair) {
            $normativePct[$norm] = $pair['max'] > 0 ? round(($pair['achieved'] / $pair['max']) * 100, 2) : 0.0;
        }

        $blockPct = [];
        foreach ($blockTotals as $norm => $blocks) {
            foreach ($blocks as $blk => $pair) {
                $blockPct[$norm][$blk] = $pair['max'] > 0 ? round(($pair['achieved'] / $pair['max']) * 100, 2) : 0.0;
            }
        }

        return ['normatives' => $normativePct, 'blocks' => $blockPct];
    }

    /**
     * Nombre: buildTodo
     * Descripción: Genera lista de acciones priorizadas basadas en brechas (preguntas con puntuación baja).
     * @param array<string,mixed> $answers
     * @return array<int,array{normative:string,block:string,priority:int,question:string,action:string}>
     * Método de uso: $todo = $engine->buildTodo($_POST['answers'] ?? []);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function buildTodo(array $answers): array
    {
        $items = [];

        foreach ($this->questionBank as $q) {
            $id = $q['id'];
            if (!array_key_exists($id, $answers)) {
                continue;
            }

            $answerType = $q['answerType'] ?? 'yes_no';
            $normScore = $this->normalizeAnswerScore($answerType, $answers[$id]); // 0..1
            $gap = 1.0 - $normScore;
            if ($gap <= 0.01) { // sin brecha
                continue;
            }

            $weight = (int)$q['weight'];
            $priority = (int)max(1, min(5, round($gap * $weight))); // 1..5 aprox según brecha y peso
            $action = $this->suggestAction($q);

            $items[] = [
                'normative' => $q['normative'],
                'block'     => $q['block'],
                'priority'  => $priority,
                'question'  => $q['text'],
                'action'    => $action,
            ];
        }

        // Orden: mayor prioridad primero, luego por normativa y bloque
        usort(
            $items,
            static fn($a, $b) =>
                ($b['priority'] <=> $a['priority'])
                ?: strcmp($a['normative'], $b['normative'])
                ?: strcmp($a['block'], $b['block'])
        );

        return $items;
    }

    /**
     * Nombre: buildAiPrompt
     * Descripción: Construye un prompt textual para el proveedor de IA (sin PII).
     * @param array{companyUuid:string,companyType:string,companySize:int,selectedNormatives:array<int,string>} $meta
     * @param array{normatives: array<string,float>, blocks: array<string,array<string,float>>} $scores
     * @param array<string,mixed> $answers
     * @return string Prompt preparado para IA.
     * Método de uso: $prompt = $engine->buildAiPrompt($meta,$scores,$answers);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function buildAiPrompt(array $meta, array $scores, array $answers): string
    {
        $uuid  = $meta['companyUuid'];
        $type  = $meta['companyType'];
        $size  = (int)$meta['companySize'];
        $norms = $meta['selectedNormatives'];

        $lines = [];
        $lines[] = "Contexto de análisis (empresa anonimizada):";
        $lines[] = "- Alias: {$uuid}";
        $lines[] = "- Tipo de empresa: {$type}";
        $lines[] = "- Tamaño (empleados): {$size}";
        $lines[] = "- Normativas seleccionadas: " . implode(', ', $norms);
        $lines[] = "";
        $lines[] = "Métricas de cumplimiento calculadas (0–100%):";

        foreach ($scores['normatives'] as $norm => $pct) {
            $lines[] = "* {$norm}: {$pct}%";
            if (!empty($scores['blocks'][$norm])) {
                foreach ($scores['blocks'][$norm] as $blk => $bpct) {
                    $lines[] = "  - {$blk}: {$bpct}%";
                }
            }
        }

        $lines[] = "";
        $lines[] = "Cuestionario (id => respuesta normalizada):";
        foreach ($this->questionBank as $q) {
            $id = $q['id'];
            if (!array_key_exists($id, $answers)) {
                continue;
            }
            $norm = $this->normalizeAnswerScore($q['answerType'] ?? 'yes_no', $answers[$id]);
            $lines[] = "- {$id}: " . number_format($norm, 2, '.', '');
        }

        $lines[] = "";
        $lines[] = "Instrucciones:";
        $lines[] = "Redacta un análisis técnico y comprensible, sin enumeraciones numéricas ni listas. Resume los riesgos clave por normativa, las causas probables y las áreas de mejora. Concluye con recomendaciones prácticas priorizadas en texto corrido.";

        return implode("\n", $lines);
    }

    /**
     * Nombre: normalizeAnswerScore
     * Descripción: Normaliza una respuesta a rango [0..1].
     * @param string $answerType 'yes_no' | 'scale_0_5'
     * @param mixed $answer Valor recibido desde el formulario.
     * @return float Valor normalizado.
     */
    private function normalizeAnswerScore(string $answerType, mixed $answer): float
    {
        if ($answerType === 'scale_0_5') {
            $v = is_numeric($answer) ? (float)$answer : 0.0;
            return max(0.0, min(1.0, $v / 5.0));
        }
        // yes_no
        $v = is_numeric($answer) ? (int)$answer : (filter_var($answer, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        return $v === 1 ? 1.0 : 0.0;
    }

    /**
     * Nombre: suggestAction
     * Descripción: Sugiere una acción de alto nivel para cubrir la brecha detectada.
     * @param array<string,mixed> $q Pregunta del banco.
     * @return string Acción recomendada.
     */
    private function suggestAction(array $q): string
    {
        $norm = (string)($q['normative'] ?? '');
        $blk  = (string)($q['block'] ?? '');
        $txt  = (string)($q['text'] ?? '');

        $map = [
            'GDPR' => 'Revisar base jurídica, políticas, derechos y evidencias; fortalecer seguridad y trazabilidad. ',
            'NIS2' => 'Mejorar gestión de riesgos, controles técnicos/operativos y respuesta a incidentes. ',
            'DORA' => 'Alinear gobernanza TIC, resiliencia operativa y terceros críticos con requisitos DORA. ',
            'ENS'  => 'Consolidar marco SGSI ENS, aplicar medidas por nivel y reforzar continuidad. ',
        ];
        $prefix = $map[$norm] ?? 'Implementar controles y evidencias proporcionales al riesgo. ';

        return $prefix . 'Punto afectado: ' . $blk . ' — ' . $txt . '. Definir responsables, evidencias y métricas; plan 30/60/90 días.';
    }
}
