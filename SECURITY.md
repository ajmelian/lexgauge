# SECURITY.md

## 1) Divulgación responsable (CVD)

Si detectas una vulnerabilidad, **no abras un issue público**. Por favor, envía un informe privado a **[ajmelper@gmail.com](mailto:ajmelper@gmail.com)** con:

* Descripción clara del problema y su posible impacto.
* Pasos para reproducirlo y PoC (capturas o script) **sin credenciales reales**.
* Alcance afectado (URL/módulo/fichero, versión o hash de commit).
* Entorno (SO, servidor web, versión de PHP) y configuración relevante.
* Cualquier log **anonimizado** que ayude al diagnóstico.

**SLA objetivo de tratamiento** (orientativo, no contractual):

| Severidad (CVSS aprox.) | Acuse de recibo      | Inicio de análisis | Mitigación/Parche |
| ----------------------- | -------------------- | ------------------ | ----------------- |
| Crítica (≥9.0)          | ≤ 3 días laborables  | ≤ 7 días           | 7–30 días         |
| Alta (7.0–8.9)          | ≤ 3 días laborables  | ≤ 10 días          | 30–60 días        |
| Media (4.0–6.9)         | ≤ 5 días laborables  | ≤ 15 días          | ≤ 90 días         |
| Baja (<4.0)             | ≤ 10 días laborables | Según prioridad    | Próxima versión   |

> **Nota**: No existe programa de recompensas; agradecemos divulgaciones responsables.
> **Llave PGP/GnuPG**: Actualmente no dispongo de clave PGP/GnuPG.

### Alcance

* **En alcance**: código de este repositorio, configuración de despliegue, flujos de datos entre navegador y aplicación, y entre la aplicación y los proveedores de IA (BYOK).
* **Fuera de alcance**: ingeniería social, ataques físicos, disponibilidad del proveedor de IA, fallos exclusivos de navegadores obsoletos, denegación de servicio a nivel de red, hallazgos sin PoC explotable.


## 2) Versiones admitidas

Se da soporte activo a la rama principal (`main`) y a las últimas versiones etiquetadas. Incluye siempre el **hash de commit** en tus informes.


## 3) Modelo de amenazas (resumen)

* **Activos**: respuestas del cuestionario, metadatos de empresa (UUID anonimizado, tipo, tamaño), selección de normativas, **token BYOK** del proveedor de IA, configuración de seguridad.
* **Actores**: usuario legítimo, atacante web (XSS/CSRF), atacante de red (MITM), atacante con acceso al host.
* **Superficies**: formularios públicos (`index.php`, `consent.php`, `questionnaire.php`), generación de informe (`report.php`), llamadas salientes a IA (OpenAI/Anthropic).
* **Suposiciones**: ejecución **en local** o en entorno controlado, **sin SGBD** ni almacenamiento persistente de cuestionarios; uso de **HTTPS**; política de **egress** restringida.


## 4) Controles de seguridad implementados

* **CSRF** en todos los formularios (token de sesión).
* **Validación y saneo**: patrones en frontend y validaciones en backend (NIF/NIE/CIF, nombre de empresa, tipo, tamaño, normativas, token IA). Salidas HTML con `htmlspecialchars`.
* **Cabeceras**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` y **CSP** restrictiva (`default-src 'self'`, JS/CSS locales).
* **Sesiones**: `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, `Secure` si HTTPS; cookies endurecidas.
* **No persistencia**: sin base de datos; sólo se lee `data/questions.json`. No se guardan cuestionarios.
* **PII-Scrubber**: detector prudente de PII antes de enviar prompts a la IA (email, teléfono, NIF/NIE/CIF, IBAN real, IPv4…).
* **BYOK**: el token de IA es del usuario; se usa **sólo en memoria** y no se almacena ni registra.
* **cURL seguro**: verificación de certificado/host activada y timeouts configurados.


## 5) Flujo de datos hacia proveedores de IA (BYOK)

Cuando el usuario activa IA:

* **Se envía**: `companyUuid` (alias anonimizado), `companyType`, `companySize`, `selectedNormatives`, métricas de cumplimiento y respuestas **normalizadas** (sí/no/escala).
* **No se envía**: NIF/NIE/CIF, nombre real de la empresa u otros datos personales directos.
* **Token**: el **token BYOK** se usa únicamente para la llamada al endpoint del proveedor seleccionado y no se persiste.


## 6) Despliegue seguro (checklist)

* [ ] Servir bajo **HTTPS**; `session.cookie_secure=1`.
* [ ] `APP_ENV=prod` en producción (`display_errors=0`, `log_errors=1`).
* [ ] Publicar sólo el directorio `public/` como raíz del servidor web.
* [ ] Mantener la **CSP** por defecto; si se añaden recursos externos, considerar `nonce`/`sha256` y evitar `unsafe-inline` en JS.
* [ ] Restringir **egress** únicamente a endpoints de IA configurados (OpenAI/Anthropic).
* [ ] Permisos mínimos: el proceso PHP sin escritura en `src/` ni `config/`; `data/questions.json` sólo lectura.
* [ ] Actualizar CA bundle del sistema y PHP/cURL regularmente.
* [ ] Revisar logs para no registrar tokens/PII; evitar volcado de cuerpos sensibles.


## 7) Gestión de secretos

* Los tokens BYOK **no se almacenan**; nunca enviarlos por query string.
* Evitar que proxies o access logs capturen tokens.
* Rotar el token ante sospecha de exposición; revocarlo en el panel del proveedor.


## 8) Respuesta ante incidentes (PII o credenciales)

1. **Revocar** inmediatamente el token en el proveedor afectado.
2. **Invalidar sesión** y limpiar cachés temporales del servidor.
3. Recopilar evidencias (logs anonimizados, timestamps, IPs).
4. Informar a **[ajmelper@gmail.com](mailto:ajmelper@gmail.com)** con el detalle técnico.
5. Evaluar impacto y, si aplica, seguir obligaciones regulatorias (p. ej., AEPD / NIS2).


## 9) Pruebas y hardening recomendados

* Análisis con **OWASP ZAP Baseline** (en entorno de pruebas).
* Revisión de cabeceras con herramientas tipo *securityheaders* (self-hosted/CI).
* Análisis estático (PHPStan/Psalm) y linters.
* Tests manuales de CSRF/XSS y verificación del **PII-Scrubber**.


## 10) Política de cambios de seguridad

Los cambios relevantes de seguridad se documentarán en el **CHANGELOG** y se reflejarán aquí cuando afecten a controles, dependencias o flujos de datos.


## Contacto

Email de seguridad: ajmelper@gmail.com
**Email de seguridad**: **[ajmelper@gmail.com](mailto:ajmelper@gmail.com)**
**Llave PGP/GnuPG**: Actualmente no dispongo de clave PGP/GnuPG.
