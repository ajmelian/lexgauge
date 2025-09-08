# LexGauge: Assessment Rápido de Cumplimiento (GDPR · NIS2 · DORA · ENS)

Aplicación PHP 8.4 **sin BBDD** que permite ejecutar un **test de 25 preguntas** para evaluar el cumplimiento de una empresa frente a **GDPR, NIS2, DORA y ENS**. La herramienta genera un **informe en 5 bloques** y, de forma opcional (BYOK), solicita a un proveedor de IA (OpenAI o Anthropic) un **análisis técnico sin enumeraciones** y un **TO‑DO priorizado** por riesgo.

> **Privacidad por diseño**: se ejecuta en local, no guarda datos en base de datos, y nunca envía a la IA el NIF/NIE/CIF ni el nombre real de la empresa. Se usa un **UUID** como alias anonimizado.



## ✨ Características

- ✅ 100% local, **sin almacenamiento** persistente (solo sesión en memoria del proceso PHP).
- ✅ **BYOK** (Bring Your Own Key): el token de IA es del usuario y no se almacena.
- ✅ **CSP estricta**, validaciones en frontend y backend, y protección **CSRF**.
- ✅ **Informe en 5 bloques**: datos, gráficos de cumplimiento, análisis técnico, TO‑DO, y trazabilidad Q&A.
- ✅ **Motor de preguntas** en JSON (sin SGBD), con pesos y tipos de respuesta (`yes_no`/`scale_0_5`).
- ✅ Código en **POO**, camelCase, **PHPDoc**, Clean Code y Desarrollo Seguro.



## 📦 Requisitos

- **PHP 8.4** con extensiones estándar (curl, json).
- Un entorno local (CLI o servidor local tipo XAMPP/MAMP/Docker).
- (Opcional) Token de IA para OpenAI o Anthropic.



## 🗂️ Estructura del proyecto

```
.
├── config/
│   └── config.php
├── data/
│   └── questions.json            # (lo pones tú)
├── public/
│   ├── index.php                 # Formulario inicial
│   ├── consent.php               # Aviso + consentimiento
│   ├── questionnaire.php         # Cuestionario (25 preguntas)
│   ├── report.php                # Informe (o report_refactored.php si usas el refactor)
│   └── assets/
│       ├── bootstrap/
│       │   └── bootstrap.min.css # lo descargas tú
│       ├── css/app.css
│       └── js/app.js
├── src/
│   ├── Ai/
│   │   ├── AiClientInterface.php
│   │   ├── ChatGptClient.php
│   │   └── ClaudeClient.php
│   ├── Domain/
│   │   ├── ComplianceEngine.php
│   │   └── PiiScrubber.php
│   └── Support/
│       ├── Csrf.php
│       ├── Http.php
│       └── Validator.php
├── bootstrap.php
└── README.md
```


## 🚀 Instalación y ejecución

1. **Clona** o desempaqueta el proyecto en tu máquina.
2. Descarga **Bootstrap 5** y coloca `bootstrap.min.css` en `public/assets/bootstrap/`.
3. Crea el fichero de preguntas `data/questions.json` (ver formato más abajo).
4. **Levanta** el servidor embebido de PHP:
   ```bash
   php -S 127.0.0.1:8080 -t public
   ```
5. Abre `http://127.0.0.1:8080` en tu navegador.



## ⚙️ Configuración

Archivo: `config/config.php`

```php
'app' => [
  'title'        => 'Assessment Rápido de Cumplimiento (GDPR/NIS2/DORA/ENS) - Local',
  'questionsFile'=> DATA_PATH . '/questions.json',
  'questionCount'=> 25
],
'security' => [
  'csrfKey' => '_csrf_token',
  'allowedNormatives' => ['GDPR','NIS2','DORA','ENS'],
  'allowedCompanyTypes'=> ['SA','SL','Cooperativa','Autónomo','Fundación','Asociación','Otra'],
  'maxCompanyNameLen'  => 100
],
'ai' => [
  'enabled' => true,
  'providers' => [
    'chatgpt' => ['endpoint' => 'https://api.openai.com/v1/chat/completions','defaultModel'=>'gpt-4o-mini'],
    'claude'  => ['endpoint' => 'https://api.anthropic.com/v1/messages','defaultModel'=>'claude-3-5-sonnet-20240620','anthropicVersion'=>'2023-06-01']
  ]
]
```

> Puedes cambiar la ruta del JSON (`questionsFile`) o el número de preguntas (`questionCount`).



## 🧩 Formato del fichero `data/questions.json`

El motor lee un JSON **estricto** (sin comentarios). Claves requeridas por pregunta:

- `id` (string, único), por ejemplo: `"gdpr-gob-001"`
- `normative` (string) — uno de: `"GDPR" | "NIS2" | "DORA" | "ENS"`
- `block` (string) — el grupo/bloque donde se agrupa la pregunta
- `text` (string) — enunciado mostrado al usuario
- `weight` (int ≥1) — peso relativo en el cálculo
- `answerType` (`"yes_no"` | `"scale_0_5"`)

> La app **selecciona hasta `questionCount`** preguntas repartidas entre los bloques de las normativas elegidas.

**Ejemplo mínimo (recortado):**
```json
{
  "version": "1.0",
  "questions": [
    {
      "id": "gdpr-gob-001",
      "normative": "GDPR",
      "block": "Gobernanza y Responsabilidad",
      "text": "¿Existe un Registro de Actividades de Tratamiento actualizado?",
      "weight": 4,
      "answerType": "yes_no"
    },
    {
      "id": "gdpr-sec-002",
      "normative": "GDPR",
      "block": "Seguridad y Brechas",
      "text": "Valora el uso de cifrado en tránsito y reposo.",
      "weight": 4,
      "answerType": "scale_0_5"
    }
  ]
}
```

> Si el JSON es inválido o está vacío, verás el mensaje **“No se encontraron preguntas…”** y podrás continuar sin preguntas (porcentajes a 0).



## 🧭 Flujo de uso

1. **Formulario inicial** (`index.php`)  
   - NIF/NIE/CIF y Nombre (solo para mostrar en el informe; **no se envían a la IA**).  
   - Tipo de empresa y número de empleados.  
   - Selección de normativas (al menos una).  
   - IA opcional (BYOK): proveedor + token.

2. **Consentimiento** (`consent.php`)  
   - Vista previa de los **datos que se enviarían a la IA** (si activaste IA):  
     `UUID`, tipo de empresa, tamaño, normativas, **respuestas**.  
   - Obligatorio **marcar el checkbox** para continuar.

3. **Cuestionario** (`questionnaire.php`)  
   - Muestra hasta `questionCount` preguntas, distribuidas por bloques.  
   - Tipos de respuesta: **Sí/No** o **Escala 0–5**.

4. **Informe** (`report.php`)  
   - **Bloque 1**: Datos de la empresa (incluye alias UUID).  
   - **Bloque 2**: Porcentaje por normativa y por bloque (barras).  
   - **Bloque 3**: Análisis técnico de IA (si activado).  
   - **Bloque 4**: **TO‑DO** priorizado (si hay brechas).  
   - **Bloque 5**: Trazabilidad de preguntas y respuestas.



## 🤖 IA (BYOK) y protección de datos

- El **token** es propiedad del usuario (BYOK) y **no se persiste**.
- Se aplica un **PiiScrubber** que bloquea el envío a la IA si detecta PII (email, teléfono, NIF/NIE/CIF, IPv4, IBAN con prefijos reales).  
  - Se ha **reducido** la probabilidad de falsos positivos (p. ej., el UUID no debe activar el patrón de IBAN).
- **Nunca** se envía NIF/NIE/CIF ni el nombre real; se usa `companyUuid` como alias.
- **CSP** estricta y cabeceras de seguridad activadas en `bootstrap.php`.
- Si prefieres **desactivar temporalmente** el scrubber para pruebas, puedes anular su chequeo en `public/report.php` (solo para test).



## 🔐 Seguridad y privacidad

- **CSRF**: token de sesión en todos los formularios.
- **Validaciones**: patrón en frontend y **regex en backend** (NIF/NIE/CIF, nombre, tamaño, etc.).
- **Sesión segura**: `httponly`, `samesite=Lax`, `use_strict_mode`.
- **CSP**: `default-src 'self'` y bloqueo de orígenes no confiables.
- **Sin BBDD**: no hay persistencia de datos; solo variables de sesión.
- **Ejecución local**: ideal para valoraciones internas sin salida de dato sensible.



## 🛠️ Personalización

- **Añadir normativas**: agrega preguntas con un nuevo valor en `normative` y referencia ese nombre en `allowedNormatives` (config).  
- **Cambiar scoring**: modifica `weight` por pregunta.  
- **UI**: estilos en `public/assets/css/app.css`.  
- **Proveedor IA**: amplia `config['ai']['providers']` y añade un cliente en `src/Ai/`.



## 🧪 Buenas prácticas del proyecto

- **PHPDoc** exhaustivo en clases y métodos (Nombre, Descripción, I/O, Uso, Fechas y Autor: _Aythami Melián Perdomo_).
- **camelCase** para funciones, métodos y variables.
- **POO, Clean Code y Desarrollo Seguro**: tipado estricto, control de errores, utilidades de infraestructura (`Support`).
- **Reciclaje de variables** y **tipado** para escalabilidad/mantenibilidad.


---

## 🧯 Solución de problemas (FAQ)

**“No se encontraron preguntas…”**  
- El JSON no existe o es inválido/está vacío. Revísalo con un validador. Debe estar en `data/questions.json`.

**“Se detectaron posibles datos personales…” pero no envío PII**  
- Puede ser un **falso positivo**. La versión actual del `PiiScrubber` ya restringe IBAN a prefijos reales y elimina heurísticos agresivos.  
- Si persiste, muestra la **causa** activando el detalle en `report.php` (patrones detectados).

**Errores HTTP con OpenAI/Anthropic**  
- Comprueba el **token**, el **modelo** y la **conectividad**. Asegura que tu red permite salida hacia `api.openai.com` o `api.anthropic.com`.

**500 / Pantalla en blanco**  
- Activa el log de errores de PHP.
- Valida que `config/config.php` tiene la ruta correcta a `questionsFile` y que PHP tiene permisos de lectura a `data/`.

**Bootstrap no carga**  
- Debes poner **tu** `bootstrap.min.css` en `public/assets/bootstrap/`.



## 🧱 Despliegue (opcional)

### Nginx + PHP‑FPM (ejemplo mínimo)
```
server {
  listen 80;
  server_name ejemplo.local;
  root /ruta/al/proyecto/public;

  add_header Content-Security-Policy "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'self';" always;

  location / {
    index index.php;
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
  }
}
```

### Docker (simple, opcional)
```dockerfile
FROM php:8.4-cli
WORKDIR /app
COPY . /app
EXPOSE 8080
CMD ["php","-S","0.0.0.0:8080","-t","public"]
```
> Copia tu `bootstrap.min.css` en la imagen o móntalo como volumen.




## 📄 Licencia

MIT (sugerida). Ajusta según tus necesidades de distribución interna o cliente.




## 👤 Autor

**Aythami Melián Perdomo**  
Arquitectura y desarrollo PHP 8.4 · Laravel/Symfony/CodeIgniter · Seguridad & Cumplimiento

