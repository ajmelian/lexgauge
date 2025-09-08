<?php
declare(strict_types=1);

namespace App\Ai;

/**
 * Nombre: AiClientInterface
 * Descripción de la funcionalidad:
 *   Contrato para clientes de IA (BYOK) utilizados por la aplicación para generar el
 *   análisis técnico del informe de cumplimiento. Implementaciones típicas: ChatGptClient,
 *   ClaudeClient. Las implementaciones deben encargarse de la autenticación con el
 *   proveedor, construcción de payload y manejo de errores de red.
 *
 * Parámetros de entrada (a través de métodos):
 *   - string $token  : Token de API propiedad del usuario (no se almacena).
 *   - string $prompt : Prompt ya anonimizado (sin PII/NIF/NIE/CIF ni nombre real).
 *   - array  $options: Opciones específicas del proveedor, p.ej. ['model' => 'gpt-4o-mini', 'temperature' => 0.2].
 *
 * Salida:
 *   - string Respuesta textual del modelo de IA, lista para mostrar en el bloque de “Análisis técnico”.
 *
 * Método de uso:
 *   - $client = new ChatGptClient('https://api.openai.com/v1/chat/completions');
 *   - $text   = $client->analyze($token, $prompt, ['model' => 'gpt-4o-mini']);
 *
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
interface AiClientInterface
{
    /**
     * Nombre: analyze
     * Descripción: Ejecuta una llamada al proveedor de IA con BYOK para obtener un análisis textual.
     *
     * @param string $token Token de API del usuario (no persistido por la aplicación).
     * @param string $prompt Prompt ya anonimizado y preparado por el motor (sin PII).
     * @param array<string,mixed> $options
     *        Opciones del proveedor. Claves sugeridas (no obligatorias):
     *        - model        (string)  Modelo a utilizar (p. ej. 'gpt-4o-mini', 'claude-3-5-sonnet').
     *        - temperature  (float)   Creatividad del modelo (0–1).
     *        - maxTokens    (int)     Límite de tokens de salida.
     *        - timeoutSec   (int)     Timeout duro de la petición HTTP.
     *        - headers      (array)   Cabeceras HTTP adicionales si aplica.
     *
     * @return string Texto de análisis listo para incrustar en el informe (sin numeraciones).
     *
     * Método de uso: $text = $client->analyze($token, $prompt, ['model'=>'gpt-4o-mini']);
     * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
     * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
     */
    public function analyze(string $token, string $prompt, array $options = []): string;
}
