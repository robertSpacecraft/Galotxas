# Operación y primera capa de privacidad de Contacto

## 1. Propósito y estado

Este documento registra `CONTACT-OPERATION-PRIVACY-LAYER-1`, correspondiente a
la Fase 7D.2C2B. El bloque completa la capacidad técnica del formulario de
Contacto, pero no lo activa en producción ni selecciona un proveedor de correo.

La recepción queda acreditada cuando Laravel persiste la solicitud en MariaDB.
El correo es una notificación auxiliar: su desactivación o fallo no revierte el
registro ni cambia el acuse público `201`.

## 2. Alcance y separación de fuentes

La página `/club/contacto` mantiene dos fuentes independientes:

- el CMS Blade aporta todo el contenido institucional;
- `ContactRequest` gestiona el formulario, consentimiento, operación y
  retención;
- `legal/notices/contact-form.md` aporta la primera capa versionada.

React no duplica contenido editorial. El aviso no pertenece al CMS ni a
`knowledge/`, no crea una cuarta página legal y se proyecta de forma
determinista a frontend y backend.

## 3. Aviso y primera capa

El aviso canónico es `NOTICE-CONTACT-FORM`, versión `1.0.0`, alcance
`contact_request` y URL ampliada `/legal/privacidad`. Informa de:

- responsable y finalidad;
- campos necesarios y carácter opcional del envío;
- consentimiento como base coherente con la Política de privacidad;
- acceso por personal autorizado y categorías técnicas sin inventar proveedor;
- conservación durante 12 meses desde el cierre;
- derechos y correo público;
- ausencia de decisiones automatizadas.

El compilador mantiene exactamente tres páginas legales y dos avisos
allowlisted. Rechaza ficheros desconocidos, borradores, metadatos inválidos,
teléfonos, artefactos desactualizados y cualquier contaminación de Knowledge.

La Política de privacidad permanece en versión `1.1.0`: ya recogía base,
campos, plazo, suspensión por reclamación, canal de derechos y proveedor de
correo pendiente. Esta implementación no exige modificar silenciosamente ese
texto vigente.

## 4. Consentimiento

La casilla no está premarcada y exige una acción afirmativa. El payload incluye
`privacy_notice_id` y `privacy_notice_version`; Laravel valida ambos contra la
proyección vigente y registra `consent_at`. No se almacena una copia del texto.

Una versión obsoleta devuelve `422`. La Política de privacidad se abre en otra
pestaña para conservar los campos introducidos. El consentimiento de Contacto
no se mezcla con identidad de menores, imágenes o comunicaciones comerciales.

## 5. Configuración fail-closed

`GET /api/v1/contact/config` devuelve sólo `enabled: false` si falla cualquiera
de estos requisitos:

1. `CONTACT_FORM_ENABLED=true`;
2. aviso vigente y compilado;
3. URL interna exacta de Privacidad;
4. destinatario sintácticamente válido y sin CR/LF;
5. columnas de persistencia operativa disponibles.

Cuando todo es válido añade únicamente ID y versión del aviso y la URL de
Privacidad. Nunca expone destinatario, remitente, mailer, estados, códigos de
fallo o flags internos. La notificación puede permanecer desactivada porque el
canal principal es la persistencia local.

Los defaults continúan seguros:

```dotenv
CONTACT_FORM_ENABLED=false
CONTACT_NOTIFICATION_ENABLED=false
CONTACT_NOTIFICATION_TO=
CONTACT_NOTIFICATION_FROM=
CONTACT_NOTIFICATION_REPLY_TO_MODE=requester
CONTACT_NOTIFICATION_MAILER=
CONTACT_RETENTION_MONTHS=12
CONTACT_ABUSE_HASH_RETENTION_DAYS=30
```

## 6. Modelo y legado

La migración incremental conserva los datos existentes y añade:

- referencia y versión del aviso;
- cierre y vencimiento de retención;
- estado, intentos y fechas de notificación;
- código de fallo sanitizado;
- vencimiento del HMAC de IP;
- hold, motivo mínimo, actores y fechas;
- fecha de anonimización;
- historial mínimo de eventos.

`consent_at` es el nombre histórico equivalente a `consented_at`. Los registros
anteriores conservan ese instante si existía, pero `privacy_notice_id` y
`privacy_notice_version` quedan nulos: Blade los identifica como legado y no
inventa un consentimiento versionado retroactivo. Para registros cerrados
existentes, la migración usa `updated_at` como mejor aproximación disponible a
`closed_at` y calcula desde ahí 12 meses. El HMAC existente recibe un vencimiento
de 30 días desde su creación.

## 7. Estados de notificación

| Estado | Significado |
|---|---|
| `not_requested` | Estado inicial técnico o legado sin intento acreditado |
| `pending` | Intento registrado antes de entregar al mailer |
| `sent` | El mailer aceptó el envío |
| `failed` | El intento falló y se guardó un código sanitizado |
| `disabled` | La notificación no estaba preparada o habilitada |

Cada intento incrementa un contador antes del envío. El máximo administrativo
es tres. El historial registra actor, fecha, resultado, número de intento y, si
procede, código técnico; nunca copia nombre, correo, mensaje, cabeceras, payload
de mail ni stack trace.

## 8. Destinatario, remitente y Reply-To

El destinatario vive sólo en entorno. El valor operativo previsto no está
hardcodeado ni se expone al navegador. El `From` también procede de una
configuración controlada y debe ser válido; nunca se usa el correo aportado por
la persona como remitente.

Con `CONTACT_NOTIFICATION_REPLY_TO_MODE=requester`, el correo RFC ya validado
se usa como `Reply-To`; `none` lo omite. Destinatario y remitente rechazan CR/LF,
el asunto es constante, Blade escapa el cuerpo y no existen adjuntos. El mailer
puede seleccionarse por configuración sin acoplar el dominio a un proveedor.

## 9. Persistencia, fallo y reintento

Laravel guarda solicitud, consentimiento, HMAC e historial antes de intentar
notificar. El `201` confirma sólo recepción y no promete correo. Ante fallo:

- la solicitud permanece disponible en Blade;
- el estado pasa a `failed`;
- el log contiene sólo ID interno y código sanitizado;
- el administrador puede reintentar después de corregir configuración;
- no se exponen detalles técnicos en API.

ADR-038 fija esta semántica. No hay reintentos automáticos ni infinitos.

## 10. Administración Blade

Sólo un administrador activo puede:

- listar y filtrar por estado de solicitud y notificación;
- consultar detalle, aviso, consentimiento, cierre y retención;
- marcar como leída y cerrar;
- reintentar una notificación fallida o desactivada;
- colocar o liberar un hold;
- anonimizar manualmente cuando el plazo haya vencido;
- consultar el historial mínimo.

No se puede editar el mensaje, cambiar el consentimiento o la versión,
responder desde la aplicación, reabrir ni borrar sin confirmación. El listado y
detalle presentan de forma explícita los registros anonimizados y legados.

## 11. Cierre, conservación y anonimización

Cerrar fija `closed_at` y `retention_until` a 12 meses, sin reabrir. Vencido el
plazo y sin hold, la anonimización elimina nombre, correo, asunto, mensaje,
HMAC y código de fallo. Conserva sólo estado, fechas, referencia del aviso y
evidencia mínima necesaria. La acción es idempotente.

Un hold sólo puede colocarse sobre una solicitud cerrada no anonimizada. Guarda
motivo mínimo, actor y fecha; liberarlo registra actor y fecha sin alterar el
vencimiento original. Mientras esté activo impide la anonimización manual y por
comando.

## 12. Comandos operativos

Los comandos no se programan en esta fase:

```bash
php artisan contact:purge-expired --dry-run
php artisan contact:purge-expired
php artisan contact:purge-abuse-hashes --dry-run
php artisan contact:purge-abuse-hashes
```

La salida contiene sólo recuentos. `contact:purge-expired` ignora abiertas,
holds y registros ya anonimizados. `contact:purge-abuse-hashes` elimina sólo el
HMAC vencido de Contacto y también cubre legados según su fecha de creación.
Ambos son idempotentes y no afectan CMS, Escuela o Competición.

## 13. Hash, rate limit y honeypot

Se conserva el HMAC con la clave de aplicación; no se almacena IP completa y no
se reutiliza para analítica. Los nuevos hashes vencen a 30 días. El rate limit
continúa en cinco intentos por diez minutos con respuesta `429` accesible. El
honeypot sigue devolviendo el mismo `201` sin persistir ni notificar.

## 14. Frontend y accesibilidad

`/club/contacto` renderiza primero el CMS. Un error de config mantiene ese
contenido y ofrece reintento. Sólo un contrato exacto y coincidente con el
aviso compilado muestra la primera capa y el formulario.

La UI mantiene labels, errores asociados, foco al primer error, `aria-live`,
checkbox descrito por el aviso, teclado, foco visible, prevención de doble
envío y conservación de valores corregibles. Tras `201` limpia los campos y
muestra un acuse neutro. No usa URL, localStorage, sessionStorage, telemetría o
logs para los datos. La composición se verifica desde 320 px y a zoom 200 %.

## 15. Testing y E2E

Feature tests sobre MariaDB cubren migración, legado, aviso, config fail-closed,
consentimiento, HMAC, honeypot, rate limit, allowlists, correo fake, From,
Reply-To, CRLF, fallos no bloqueantes, reintento, límites, permisos, cierre,
retención, hold, comandos, anonimización, idempotencia y logs sanitizados.

Vitest cubre contrato de config, proyección legal, versión, enlace, casilla,
validación, errores remotos, doble envío, foco, conservación y ausencia de
storage. El stack E2E aislado habilita Contacto sólo con destinatario y
remitente ficticios y mailer `array`; no contacta un proveedor real ni modifica
datos de desarrollo.

## 16. Activación, operación y rollback

Antes de activar en producción se debe completar en 7F:

1. proveedor contratado y revisado;
2. remitente verificado y credenciales secretas sólo en entorno;
3. destinatario y responsable de atención confirmados;
4. HTTPS, dominio y CORS efectivos;
5. SPF, DKIM y DMARC cuando correspondan;
6. prueba de entrega, Reply-To, rebote y fallo;
7. logs ordinarios con máximo de 30 días y seguridad con 90 días;
8. scheduler de purgas, monitorización y procedimiento de holds;
9. backups y restauración con rotación de 30 días y reaplicación de borrados;
10. staging, aceptación humana y procedimiento de rollback.

Rollback funcional: fijar `CONTACT_FORM_ENABLED=false` y recargar configuración.
El CMS y las solicitudes ya persistidas permanecen operables. Desactivar
`CONTACT_NOTIFICATION_ENABLED` detiene nuevos correos sin impedir la recepción.

## 17. Riesgos y deuda

- Contacto productivo sigue desactivado.
- No existe proveedor, remitente verificado, credencial, scheduler o monitor de
  rebotes configurado.
- Los plazos de logs y backups dependen de la infraestructura de 7F.
- Restaurar un backup puede reintroducir datos ya anonimizados; el proceso de
  purga debe repetirse.
- CMS export/import, imágenes, aliases, redirects, canonical y SEO completo son
  frentes independientes.

7D.2C2B y 7D.2 quedan cerradas técnicamente. 7D.3, 7D, Fase 7 y el MVP continúan
abiertos.
