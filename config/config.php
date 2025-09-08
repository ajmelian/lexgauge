<?php
declare(strict_types=1);

/**
 * Nombre: config.php
 * Descripción de la funcionalidad:
 *   Define la configuración estática de la aplicación para ejecución local, incluyendo:
 *   - Metadatos de la app y ubicación del banco de preguntas JSON.
 *   - Parámetros de seguridad y validaciones permitidas en formularios.
 *   - Proveedores IA en modo BYOK (endpoints y modelos por defecto).
 *
 * Parámetros de entrada: N/A
 * Salida:
 *   - Constante CONFIG (array asociativo con la configuración)
 *   - Función auxiliar config(string $key, mixed $default = null): mixed (opcional)
 *
 * Método de uso:
 *   - require_once desde bootstrap.php
 *   - Acceso directo: CONFIG['app']['title']
 *   - Acceso con helper: config('app.title', 'Título por defecto')
 *
 * Fecha de desarrollo: 2025-09-08
 * Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */

/**
 * @var array{
 *   app: array{
 *     title: string,
 *     questionsFile: string,
 *     questionCount: int
 *   },
 *   security: array{
 *     csrfKey: string,
 *     allowedNormatives: array<int,string>,
 *     allowedCompanyTypes: array<int,string>,
 *     maxCompanyNameLen: int
 *   },
 *   ai: array{
 *     enabled: bool,
 *     providers: array{
 *       chatgpt: array{
 *         label: string,
 *         endpoint: string,
 *         defaultModel: string
 *       },
 *       claude: array{
 *         label: string,
 *         endpoint: string,
 *         defaultModel: string,
 *         anthropicVersion: string
 *       }
 *     }
 *   }
 * }
 * CONFIG
 */
const CONFIG = [
    'app' => [
        'title'         => 'Assessment Rápido de Cumplimiento (GDPR/NIS2/DORA/ENS) - Local',
        'questionsFile' => DATA_PATH . '/questions.json', // Ruta al banco de preguntas
        'questionCount' => 25,                            // Nº de preguntas a presentar en el test
    ],
    'security' => [
        'csrfKey'            => '_csrf_token',
        'allowedNormatives'  => ['GDPR', 'NIS2', 'DORA', 'ENS'],
        'allowedCompanyTypes'=> ['SA', 'SL', 'Cooperativa', 'Autónomo', 'Fundación', 'Asociación', 'Otra'],
        'maxCompanyNameLen'  => 100,
    ],
    'ai' => [
        'enabled'   => true,
        'providers' => [
            'chatgpt' => [
                'label'        => 'ChatGPT (OpenAI)',
                'endpoint'     => 'https://api.openai.com/v1/chat/completions',
                'defaultModel' => 'gpt-4o-mini',
            ],
            'claude' => [
                'label'             => 'Claude (Anthropic)',
                'endpoint'          => 'https://api.anthropic.com/v1/messages',
                'defaultModel'      => 'claude-3-5-sonnet-20240620',
                'anthropicVersion'  => '2023-06-01',
            ],
        ],
    ],
];

/**
 * Nombre: config
 * Descripción: Helper opcional para obtener valores de CONFIG mediante clave en notación de puntos.
 * @param string $key     Clave en formato "seccion.subclave.otranivel" (p.ej., "ai.providers.chatgpt.endpoint").
 * @param mixed  $default Valor por defecto si la clave no existe.
 * @return mixed          Valor encontrado o $default.
 * Método de uso: $endpoint = config('ai.providers.chatgpt.endpoint');
 * Fecha de desarrollo: 2025-09-08 | Autor: Aythami Melián Perdomo
 * Fecha de actualización: 2025-09-08 | Autor: Aythami Melián Perdomo
 */
function config(string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key);
    $value = CONFIG;
    foreach ($segments as $seg) {
        if (!is_array($value) || !array_key_exists($seg, $value)) {
            return $default;
        }
        /** @var mixed $value */
        $value = $value[$seg];
    }
    return $value;
}
