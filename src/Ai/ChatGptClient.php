<?php
declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/**
 * Nombre: ChatGptClient
 * Descripción de la funcionalidad:
 *   Cliente BYOK para el endpoint de Chat Completions de OpenAI. Envía un prompt
 *   ya anonimizado y devuelve el texto de análisis técnico para el informe.
 *   Incluye endurecimiento de cURL (timeouts, verificación TLS) y manejo de errores.
 *
 * Parámetros de entrada (método analyze):
 *   - string $token   Token de API propiedad del usuario (no se almacena).
 *   - string $prompt  Prompt ya anonimizado (sin NIF/NIE/CIF ni nombre real).
 *   - array  $options Opciones del proveedor, p. ej.:
 *       ['model' => 'gpt-4o-mini', 'temperature' => 0.2, 'maxTokens' => 800, 'timeoutSec' => 20]
 *
 * Salida:
 *   - string Texto de análisis en lenguaje natural, sin enumeraciones numéricas.
 *
 * Método de uso:
 *   - (new ChatGptClient('https://api.openai.com/v1/chat/completions'))
 *         ->analyze($token, $prompt, ['model' => 'gpt-4o-mini']);
 *
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
final class ChatGptClient implements AiClientInterface
{
    /**
     * @var string Endpoint HTTP absoluto para el recurso /v1/chat/completions.
     */
    private string $endpoint;

    /**
     * Nombre: __construct
     * Descripción: Inicializa el cliente con el endpoint del proveedor.
     * @param string $endpoint URL absoluta del endpoint de Chat Completions.
     * Método de uso: $c = new ChatGptClient('https://api.openai.com/v1/chat/completions');
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function __construct(string $endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Nombre: analyze
     * Descripción: Ejecuta la llamada al proveedor de IA y devuelve el texto de análisis.
     *
     * @param string $token Token de API del usuario (no persistido).
     * @param string $prompt Prompt ya anonimizado.
     * @param array<string,mixed> $options
     *        Claves soportadas:
     *        - model       (string) por defecto 'gpt-4o-mini'
     *        - temperature (float) [0.0, 1.0] por defecto 0.2
     *        - maxTokens   (int)   número máximo de tokens de salida
     *        - timeoutSec  (int)   segundos de timeout (por defecto 20)
     *        - headers     (array) cabeceras HTTP adicionales
     * @return string Texto de respuesta listo para el bloque “Análisis técnico”.
     * @throws RuntimeException Si hay problemas de red, HTTP no exitoso o JSON inválido.
     * Método de uso: $text = $client->analyze($token, $prompt, ['model'=>'gpt-4o-mini']);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function analyze(string $token, string $prompt, array $options = []): string
    {
        $model       = (string)($options['model'] ?? 'gpt-4o-mini');
        $temperature = $this->sanitizeTemperature($options['temperature'] ?? 0.2);
        $maxTokens   = isset($options['maxTokens']) ? max(1, (int)$options['maxTokens']) : null;
        $timeoutSec  = isset($options['timeoutSec']) ? max(1, (int)$options['timeoutSec']) : 20;

        $payload = $this->buildPayload($model, $prompt, $temperature, $maxTokens);

        $headers = array_merge(
            [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
                'User-Agent: Compliance-Tool/1.0 (+local)'
            ],
            $this->normalizeHeaders($options['headers'] ?? [])
        );

        [$httpCode, $body] = $this->execRequest($this->endpoint, $payload, $headers, $timeoutSec);

        if ($httpCode < 200 || $httpCode >= 300) {
            $detail = $this->extractProviderError($body);
            throw new RuntimeException('OpenAI devolvió HTTP ' . $httpCode . ($detail !== '' ? ' — ' . $detail : ''));
        }

        $text = $this->extractContent($body);
        return $text;
    }

    /**
     * Nombre: buildPayload
     * Descripción: Construye el JSON para Chat Completions.
     * @param string $model
     * @param string $prompt
     * @param float $temperature
     * @param int|null $maxTokens
     * @return string JSON serializado.
     */
    private function buildPayload(string $model, string $prompt, float $temperature, ?int $maxTokens): string
    {
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'Eres un analista de cumplimiento. Redacta sin enumeraciones numéricas ni listas, en tono claro y profesional.'
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt
                ],
            ],
            'temperature' => $temperature,
        ];

        if ($maxTokens !== null) {
            $data['max_tokens'] = $maxTokens;
        }

        try {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo serializar el payload JSON.');
        }
    }

    /**
     * Nombre: execRequest
     * Descripción: Ejecuta la petición HTTP POST con cURL y devuelve [httpCode, body].
     * @param string $url
     * @param string $payload
     * @param array<int,string> $headers
     * @param int $timeoutSec
     * @return array{0:int,1:string}
     */
    private function execRequest(string $url, string $payload, array $headers, int $timeoutSec): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => min($timeoutSec, 10),
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Error de conexión con OpenAI: ' . ($err !== '' ? $err : 'desconocido'));
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$httpCode, (string)$responseBody];
    }

    /**
     * Nombre: extractContent
     * Descripción: Extrae el texto de la primera choice del cuerpo JSON.
     * @param string $body Respuesta JSON del proveedor.
     * @return string Contenido textual.
     */
    private function extractContent(string $body): string
    {
        try {
            /** @var array<string,mixed> $data */
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException('Respuesta JSON inválida del proveedor.');
        }

        // Ruta típica: choices[0].message.content
        $choices = $data['choices'] ?? null;
        if (!is_array($choices) || empty($choices) || !isset($choices[0]['message']['content'])) {
            // Intentar mensaje de error estándar
            $detail = $this->extractProviderError($body);
            if ($detail !== '') {
                throw new RuntimeException('Respuesta del proveedor sin contenido utilizable — ' . $detail);
            }
            throw new RuntimeException('Respuesta del proveedor sin contenido utilizable.');
        }

        $content = $choices[0]['message']['content'];
        if (!is_string($content)) {
            throw new RuntimeException('El contenido devuelto no es texto.');
        }

        return trim($content);
    }

    /**
     * Nombre: extractProviderError
     * Descripción: Intenta sacar mensaje de error de un cuerpo JSON de OpenAI.
     * @param string $body
     * @return string Mensaje de error o cadena vacía si no aplica.
     */
    private function extractProviderError(string $body): string
    {
        try {
            /** @var array<string,mixed> $data */
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '';
        }

        if (isset($data['error']['message']) && is_string($data['error']['message'])) {
            return $data['error']['message'];
        }
        return '';
    }

    /**
     * Nombre: sanitizeTemperature
     * Descripción: Normaliza y acota la temperatura a [0.0, 1.0].
     * @param mixed $t
     * @return float
     */
    private function sanitizeTemperature(mixed $t): float
    {
        $val = is_numeric($t) ? (float)$t : 0.2;
        if ($val < 0.0) {
            $val = 0.0;
        } elseif ($val > 1.0) {
            $val = 1.0;
        }
        return $val;
    }

    /**
     * Nombre: normalizeHeaders
     * Descripción: Filtra y normaliza cabeceras adicionales del usuario.
     * @param mixed $headers
     * @return array<int,string>
     */
    private function normalizeHeaders(mixed $headers): array
    {
        if (!is_array($headers)) {
            return [];
        }
        $out = [];
        foreach ($headers as $h) {
            if (is_string($h) && strpos($h, ':') !== false) {
                $out[] = $h;
            }
        }
        return $out;
    }
}
