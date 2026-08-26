# Preparación productiva, entornos y runbook de despliegue

## Estado y alcance

`PRODUCTION-READINESS-1` preparó el repositorio para staging y producción sin
crear proyectos. Tras la ejecución manual parcial de 7F, el entorno de **staging** está
desplegado y validado (proyectos en Vercel/Railway, base de datos MariaDB, DNS
personalizado, dominios canónicos de staging). 
El despliegue a **producción** permanece pendiente.
Fase 7, 7F de producción, 7G y el MVP permanecen abiertos. La preparación 7G.0
reconcilia el gate final en `29-mvp-final-acceptance-and-production-gate.md`;
no acredita ninguna acción productiva.

La primera publicación está deliberadamente cerrada:

- `VITE_PUBLIC_INDEXING_ENABLED=false`;
- `CONTACT_FORM_ENABLED=false`;
- `CONTACT_NOTIFICATION_ENABLED=false`;
- `SCHOOL_ENROLLMENT_ENABLED=false`;
- `PUBLIC_IDENTITY_AUTHORIZATION_ENABLED=false`;
- `PUBLIC_IDENTITY_NOTIFICATION_ENABLED=false`;
- `DEPLOYMENT_SCHEDULER_ENABLED=false`.

Este documento es el contrato operativo; no acredita que se hayan ejecutado
sus pasos manuales.

## Identidad y destinos aprobados

- denominación jurídica literal: `Club Galotxes de Monover`;
- denominación pública: `Club Galotxes Monòver`;
- CIF: `G03912193`;
- domicilio social: `C/ Pierrot, 1, 1.º, 03640 Monóvar, Alicante`;
- instalaciones: `Centro Polideportivo de Monóvar, Av. Novelda, s/n, 03640
  Monòver, Alicante`;
- web canónica prevista: `https://galotxesmonover.es`;
- API prevista: `https://api.galotxesmonover.es`;
- `https://www.galotxesmonover.es` redirige permanentemente al apex conservando
  la ruta;
- dominio y buzones: DonDominio.

El teléfono institucional es privado. No se incorpora a código, datos de
prueba, variables, documentación, metadata ni logs.

## Arquitectura aprobada

Producción separa tres servicios persistentes o desplegables:

```text
galotxesmonover.es                  api.galotxesmonover.es
Vercel / React-Vite       ───────>  Railway / Nginx-PHP-FPM-Laravel
                                               │
                                               └── MariaDB Railway persistente
```

Staging reproduce el límite entre capas con un proyecto Vercel preview o
staging, un servicio Railway backend y otra MariaDB. No reutiliza recursos de
producción. El frontend no aloja Laravel y Railway no construye React.

ADR-041 fija la separación física, las migraciones manuales forward-only y la
ausencia de migraciones en cada arranque.

## Matriz de entornos

| Entorno | Finalidad | Persistencia | Datos y correo | Indexación |
|---|---|---|---|---|
| `local` | Desarrollo manual | MariaDB local `galotxas` y volumen propio | Sólo datos de desarrollo; mailer `log` | Cerrada |
| `testing` | PHPUnit | MariaDB `galotxas_testing` en `tmpfs` | Factories; mailer `array` | No aplica |
| `e2e` | Relato Playwright | MariaDB `galotxas_e2e` en `tmpfs` | `E2ESmokeSeeder` y direcciones `.test` | Dos builds locales controlados |
| `staging` | Ensayo de release | MariaDB staging persistente y separada | Sin datos reales; mailer no real | `noindex, nofollow` |
| `production` | Servicio público | MariaDB productiva persistente | Datos reales revisados; correo HTTPS sólo tras gate | Cerrada en el primer deploy |

Prohibiciones comunes:

- no ejecutar E2E ni sus seeders en staging o producción;
- no copiar producción a staging sin un proceso posterior, explícito y
  anonimizado;
- no experimentar en producción;
- no compartir DB, secrets, dominios de backend ni buckets entre staging y
  producción;
- no ejecutar `migrate:fresh`, `db:wipe` o `migrate:reset` fuera de los
  entornos desechables autorizados.

## Inventario de preparación

| Área | Situación actual tras ejecución parcial 7F | Gate manual pendiente |
|---|---|---|
| URL frontend/API | Dominios de staging activos y validados con TLS | Asignar dominios de producción y comprobar TLS |
| Vercel | Proyecto `galotxas-staging` vinculado y desplegado | PENDIENTE ESPERADO / NO CREADO |
| Railway | Servicios backend y MariaDB activos para staging | `backend-production` (0 deploys) y `mariadb-production` (11.4, SUCCESS) pre-provisionados |
| MariaDB | DB de staging operativa, migraciones completadas | Crear DB producción, activar política en capas y acreditar restore aislado |
| CORS | Origen exacto, sin patrones, wildcard o cookies CORS | Cargar el origen real de cada entorno |
| Auth | Sanctum Bearer existente; contratos 401/403/419 intactos | Smoke HTTPS de registro/login/logout/reset |
| Sesión Blade | Cookie Secure/HttpOnly/SameSite y driver DB en ejemplos | Validar admin detrás del proxy |
| Proxy/HTTPS | `TRUSTED_PROXIES` y cabeceras Traefik | Verificar cadena Railway y URL generada |
| Salud | `/up` mínimo, independiente de DB; readiness CLI | Monitor externo y ejecución preflight |
| Admin | `admin:create` interactivo e idempotente | Crear la primera cuenta por consola |
| CMS | Páginas staging recreadas manualmente y aprobadas | Recrear y aprobar en producción |
| Legal/Knowledge | Hashes validados y activos en staging | Validar hashes en producción |
| SEO | Staging validado como no indexable (`robots.txt`) | Activar indexación en producción posterior |
| Contacto/Escuela/menores | Capacidades validadas con fail-closed y flag global; persistencia staging probada | Correo real, flujo menores completo en staging |
| Queue/scheduler | Cola síncrona, ningún worker/cron productivo | Diseñar y ensayar purgas antes de activar |
| Logs/storage | `stderr`; `media_s3` y bucket privado de staging validados; Sponsor es el primer consumidor en `develop` | Crear bucket privado productivo aislado y aceptar 7F.2C en staging antes de promoción |
| Backups/restore/rollback | Restore aislado ensayado en 7G.1D (PASS, RTO 5m27s) | Validar media y rollback rehearsal antes del Go productivo |

## Variables y secretos

Los contratos copiables están en:

- `backend/.env.production.example` y `backend/.env.staging.example`;
- `frontend/.env.production.example` y `frontend/.env.staging.example`.

Son plantillas sin secrets. Las variables de plataforma se configuran en sus
paneles, nunca en Git. `APP_KEY`, credenciales de MariaDB y `MAIL_PASSWORD` son
secretos. La clave se genera manualmente una sola vez con `php artisan
key:generate --show`, se guarda directamente en Railway y no se regenera en un
entorno con datos cifrados.

Producción exige como mínimo:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.galotxesmonover.es
FRONTEND_URL=https://galotxesmonover.es
CORS_ALLOWED_ORIGINS=https://galotxesmonover.es
TRUSTED_PROXIES=*
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
```

`TRUSTED_PROXIES=*` sólo es válido cuando el servicio permanece detrás del
proxy gestionado de Railway; el smoke debe acreditar `X-Forwarded-Proto` y
generación HTTPS. No se acepta `APP_DEBUG=true` en staging.

## Frontend y Vercel

El proyecto Vercel se crea con Root Directory `frontend`. El contrato tracked
usa `npm ci`, `npm run deploy:build` y `dist`. `deploy:build` ejecuta primero:

```bash
npm run deploy:check
```

El preflight valida `DEPLOYMENT_TARGET`, etapa, API, site URL, HTTPS, ausencia
de localhost/placeholders, `/api/v1`, separación de staging y política de
indexación; después ejecuta `seo:check`. `vercel.json` conserva deep links de
la SPA y la redirección 308 de `www` al apex. No contiene backend ni secrets.

Staging debe configurar:

```text
DEPLOYMENT_TARGET=staging
DEPLOYMENT_RELEASE_STAGE=initial
VITE_API_BASE_URL=https://<backend-staging>/api/v1
VITE_PUBLIC_SITE_URL=https://<frontend-staging>
VITE_PUBLIC_INDEXING_ENABLED=false
```

Producción empieza con:

```text
DEPLOYMENT_TARGET=production
DEPLOYMENT_RELEASE_STAGE=initial
VITE_API_BASE_URL=https://api.galotxesmonover.es/api/v1
VITE_PUBLIC_SITE_URL=https://galotxesmonover.es
VITE_PUBLIC_INDEXING_ENABLED=false
```

Sólo después de aceptación humana se cambia la etapa a `live` y, en un cambio
separado, la indexación a `true`. El primer despliegue debe responder con
robots cerrado y sin sitemap. Tras abrir, deben comprobarse robots, sitemap,
canonical y metadata sobre el dominio real.

## Backend y Railway

El servicio Railway usa Root Directory `backend`, config as code
`/backend/railway.json` e imagen `/backend/Dockerfile`. Es un único contenedor
con Nginx y PHP-FPM; escucha el `PORT` inyectado, oculta la versión PHP y sirve
Laravel. El arranque sólo genera `config:cache`, `route:cache` y `view:cache`.
No ejecuta migraciones, seeders, workers ni scheduler.

Nginx reserva 16 KiB para la cabecera FastCGI: cubre redirects Blade con
validación y `old input` sin ampliar el tamaño admitido de los cuerpos. La
misma cifra se prueba en el stack local/E2E para evitar discrepancias.

La MariaDB es otro servicio Railway con almacenamiento persistente. Laravel
sólo recibe variables. `DB_CONNECTION=mariadb` es obligatorio; charset y
collation continúan en `utf8mb4`/`utf8mb4_unicode_ci`. Staging usa otra
instancia, no otro schema del servicio productivo.

El filesystem general de la aplicación es efímero. Sólo aloja cachés, sesiones
si se configuraran como archivo local por error y logs transitorios. Producción
usa sesión y caché en MariaDB y logs en `stderr`; los uploads funcionales sólo
pueden usar `MEDIA_DISK=media_s3`, nunca ese filesystem efímero. Los assets
públicos versionados en Git forman parte de la imagen.

Fase 7F.2B añade la infraestructura multimedia persistente S3-compatible:

- `FILESYSTEM_DISK=local` permanece inalterado;
- `MEDIA_DISK=media_local` permite desarrollo privado en
  `storage/app/media`, sin `storage:link` ni URL temporal local;
- `media_s3` acepta únicamente variables `MEDIA_*`, usa visibilidad privada y
  propaga fallos;
- `php artisan media:probe` comprueba escritura, tamaño, existencia y cleanup;
  `--temporary-url` añade la capacidad de URL firmada cuando el adapter la
  ofrece.

El bucket privado de staging, sus secrets Railway, `MEDIA_DISK=media_s3`, probe
con URL temporal y persistencia tras redeploy ya fueron validados para cerrar
7F.2B. Producción sigue sin configurar y debe disponer de bucket/namespace y
credenciales propios. 7F.2C es el primer consumidor: los logos de Sponsor se
sirven por ruta Laravel estable y redirect prefirmado corto. El modo local usa
`MEDIA_LOCAL_ROOT` opcional, ficheros privados `0640`, directorios `0750` y una
ubicación Nginx `internal`; el proceso Nginx debe pertenecer al grupo lector.
No usar `storage:link` ni persistir URLs firmadas.

7F.2D reutiliza la misma infraestructura para la foto privada de `User`. El
backend sólo acepta keys `avatars/<uuid>.(jpg|png|webp)` y entrega la ruta
propia autenticada. En `media_s3`, Laravel lee el objeto privado mediante
stream y devuelve `200 image/*` sin `Location` ni URL prefirmada; en local, el
salto Nginx `internal` conserva el valor
`Access-Control-Allow-Origin` que Laravel ya resolvió contra su allowlist; no
refleja un origen no autorizado.

Antes de promover 7F.2D se debe auditar, sin modificar ni sembrar datos, si
existen referencias anteriores:

```sql
SELECT id, profile_photo_path
FROM users
WHERE profile_photo_path IS NOT NULL;
```

Un valor fuera del prefijo/formato aprobado se trata como ausencia: no se
sirve ni se intenta borrar automáticamente.

No se introduce Redis ni worker: los flujos actuales no despachan jobs y las
notificaciones controladas soportan ejecución síncrona. Un worker futuro debe
tener supervisión, reintentos, failed jobs y runbook antes de cambiar
`QUEUE_CONNECTION`.

## CORS, autenticación, sesiones y cabeceras

React conserva el token Sanctum Bearer y lo envía mediante `Authorization`.
No se migra la autenticación a cookies ni se habilita `statefulApi`; el panel
Blade sí usa sesión. Por ello:

- CORS afecta sólo `api/*`;
- se permite un origen exacto por entorno;
- `supports_credentials=false` y no hay wildcard;
- `Authorization`, `Accept` y `Content-Type` están allowlisted;
- el redirect de `www` evita necesitarlo como origen API; sólo se añadirá si
  una prueba real demuestra una ventana técnica previa al redirect;
- los contratos existentes `401`, `403` y `419` no cambian;
- Blade requiere cookie `Secure`, `HttpOnly`, `SameSite=lax` y sesión DB.

El bucket privado de `media-staging` mantiene CORS de lectura limitado a
`GET`/`HEAD` y al origen exacto `https://staging.galotxesmonover.es`;
producción usará exclusivamente `https://galotxesmonover.es`. La lectura del
avatar ya no depende de ese CORS porque el navegador recibe el binario desde la
API. No añadir `Origin: null`, no usar `*`, no habilitar `PUT` directo y no
configurar credenciales desde el repositorio. Los recursos públicos que usan
redirect prefirmado, como Sponsor, conservan su estrategia actual.

Backend y frontend emiten una base prudente de cabeceras:
`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin` y una
`Permissions-Policy` que desactiva cámara, geolocalización y micrófono. No se
añade CSP sin inventariar todos los recursos. HSTS se aplaza hasta verificar
HTTPS real; no se solicita preload.

## Salud, readiness y cachés

`GET /up` devuelve sólo `200`, texto `OK`, `no-store` y cabeceras prudentes. No
abre sesión ni consulta DB y no expone Laravel, motor, entorno, paths o stack.
Railway lo usa exclusivamente como liveness de despliegue; su healthcheck no
sustituye monitorización continua.

La readiness detallada es de consola:

```bash
php artisan deploy:check
```

Es read-only, no imprime valores sensibles y devuelve estado distinto de cero
si fallan entorno/debug/clave, HTTPS, MariaDB o conexión, migraciones, CORS,
sesión, proxy, caché, cola, logs, filesystem, flags o scheduler. En la primera
publicación exige todos los flujos sensibles apagados. Para una activación
posterior controlada:

```bash
php artisan deploy:check --allow-live-features
```

Ese modo comprueba dependencias de Contacto, Escuela, identidad y SMTP cuando
corresponde; no activa nada. La indexación pertenece al build frontend y se
valida con `npm run deploy:check`.

Compatibilidad de release:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Se ejecutan al iniciar la imagen. Para diagnóstico manual se puede limpiar y
reconstruir la caché de aplicación, pero no se debe ocultar una variable
incorrecta reiniciando sin corregirla.

## Base de datos, migraciones y primer administrador

La estrategia es incremental y forward-only. El despliegue no migra por sí
solo. *(Nota operativa: Sponsors y News confirmaron en staging que un deploy
exitoso no ejecuta migraciones, y éstas deben verificarse/aplicarse
explícitamente con los comandos inferiores).* En una DB vacía o una release con cambios:

1. ejecutar los dos preflight con flags cerradas; antes de la primera
   migración el backend puede bloquear exclusivamente por repositorio o
   migraciones pendientes, y cualquier otro bloqueo detiene la operación;
2. completar el gate predeploy de snapshot nativo, dump lógico y media cuando
   ya exista información;
3. activar maintenance sólo si la migración o compatibilidad lo exige;
4. ejecutar `php artisan migrate:status`;
5. revisar la lista exacta;
6. ejecutar una vez `php artisan migrate --force` desde la release nueva;
7. repetir `migrate:status` y `deploy:check`;
8. comprobar `/up`, API y smoke;
9. retirar maintenance si se activó.

No se usa `migrate:fresh`, `db:wipe`, `migrate:reset`, rollback automático ni
`E2ESmokeSeeder`. `DatabaseSeeder` contiene credenciales/datos de demo y queda
prohibido. Ante un fallo se prefiere corregir hacia delante; restaurar una DB
requiere decisión humana y el runbook de restore.

El administrador inicial se crea en la consola privada del backend:

```bash
php artisan admin:create --email=<correo-verificado> --name=<nombre>
```

La contraseña se solicita dos veces sin eco, exige al menos 12 caracteres con
mayúsculas, minúsculas, números y símbolos, no se registra y queda hasheada. El
comando es idempotente para un admin activo y nunca eleva automáticamente una
cuenta normal o inactiva. No existen password ni admin por defecto.

## CMS de producción

CMS vive en MariaDB. Un redeploy de aplicación no borra páginas. No se crea
`cms:export/import` ni un seeder productivo. Después del bootstrap:

1. entrar en Blade por `/admin`;
2. recrear manualmente `nosotros` como borrador;
3. recrear `contacto` como borrador;
4. recrear `federarse` como borrador;
5. recrear `documentos` como borrador;
6. revisar bloques, links y datos contra la fuente aprobada;
7. comparar `/contenidos/*` y las fachadas `/club/*` en staging;
8. publicar una página cada vez tras aprobación;
9. repetir la carga en producción, sin copiar la DB de staging.

La política en capas de MariaDB protege esta persistencia editorial; el bucket
multimedia se recupera por separado. Ningún deploy debe ejecutar un seeder
general.

## Correo y migración del canal anterior

### Auditoría 7G.1A del reset y del runtime

El flujo actual de recuperación está completo a nivel de aplicación, pero no
dispone de un transporte operativo en Railway Hobby:

1. `POST /api/v1/auth/forgot-password` usa `ForgotPasswordRequest`, normaliza
   el correo y valida `required|email`;
2. `AuthController::forgotPassword()` llama al broker `users` mediante
   `Password::sendResetLink()` y devuelve siempre el mismo `200` para una
   cuenta existente o inexistente;
3. `User` no sobrescribe la notificación: usa
   `Illuminate\Auth\Notifications\ResetPassword`, canal `mail`, sin
   `ShouldQueue` y, por tanto, envío síncrono;
4. `AppServiceProvider` construye
   `FRONTEND_URL/reset-password?token=...&email=...`;
5. el broker guarda en `password_reset_tokens` un hash del token, sustituye el
   anterior para el mismo correo, expira a los 60 minutos y limita la emisión
   de otro token durante 60 segundos;
6. las dos rutas de password comparten además `throttle:auth.password`, cinco
   peticiones por minuto para la clave correo normalizado más IP;
7. `POST /api/v1/auth/reset-password` valida token, correo, contraseña mínima
   de ocho caracteres y confirmación; el broker comprueba usuario/token,
   persiste la contraseña mediante el cast `hashed`, rota `remember_token`,
   elimina el token y emite `PasswordReset`;
8. el reset no revoca los personal access tokens Sanctum ya emitidos.

La cobertura existente comprueba respuesta genérica y ausencia de notificación
para un correo desconocido, generación de la notificación estándar, reset
válido, login posterior, token inválido, confirmación y los dos límites HTTP.
No comprueba todavía la URL exacta del mensaje, token expirado, rechazo de
reutilización, contenido/idioma del correo, fallo del transporte ni que el
preflight exija correo aunque las flags opcionales estén cerradas. Tampoco hay
un test React dedicado a `/reset-password`; el smoke E2E sólo visita
`/forgot-password`.

El controlador ignora deliberadamente los estados `INVALID_USER` y
`RESET_THROTTLED`, preservando la respuesta genérica. Una excepción del
transporte, en cambio, se propaga hoy sólo para una cuenta existente y podría
producir un `500` diferenciable; además, el token ya creado queda sujeto al
throttle del broker. La implementación debe fijar y probar una política de
fallo no enumerable, observable mediante logs saneados y sin token, API key o
correo completo.

El mailer `log` tampoco es un baseline seguro para una ruta de reset accesible:
`LogTransport` serializa el mensaje completo a nivel `debug`, incluido el
correo y la URL con token. Staging deberá usar `array` mientras no esté abierta
la ventana Resend y deberá comprobarse que no conserva trazas anteriores con
tokens. Esta auditoría no limpia logs ni modifica el entorno desplegado.

### Transports realmente disponibles antes de 7G.1A

`config/mail.php` enumera más drivers de los que las dependencias instaladas
permiten usar. El inventario de `composer.lock`, clases cargables y la imagen
Alpine de producción da este resultado:

| Transport | Estado real del repositorio |
|---|---|
| `smtp` | Disponible mediante `symfony/mailer`, pero Railway Free/Trial/Hobby bloquea SMTP saliente. |
| `log` y `array` | Disponibles y no entregan correo real. `log` vuelca mensaje y token de reset, por lo que no es seguro para staging; PHPUnit usa `array`, que no escribe el mensaje al log. |
| `sendmail` | La clase Symfony y la entrada de configuración existen, pero la imagen no instala `/usr/sbin/sendmail`; no es operativo. |
| `ses` | El SDK AWS está instalado transitivamente por Flysystem S3 y hay configuración base, pero no existe contrato de credenciales/identidad SES ni evidencia operativa. No se debe convertir una dependencia transitiva en decisión de infraestructura implícita. |
| `resend` | Existen las entradas en `mail.php` y `services.php`; falta `resend/resend-php`, por lo que todavía no es cargable. |
| `postmark` | Existen las entradas de configuración; faltan `symfony/postmark-mailer` y `symfony/http-client`. |
| `mailgun` | Faltan entrada propia en `mailers`, configuración de servicio y `symfony/mailgun-mailer`/`symfony/http-client`. |
| SendGrid | No existe transport, configuración ni SDK. Requeriría `sendgrid/sendgrid` y adaptación propia al canal Mail de Laravel. |
| `failover` / `roundrobin` | La composición está presente, pero no crea capacidad nueva: `failover` termina en `log` si SMTP falla y `roundrobin` referencia Postmark no instalado. Ninguna acredita entrega. |

### Decisión 7G.1A: Resend por API HTTPS

Railway exige servicios transaccionales por API HTTPS en Free, Trial y Hobby y
[señala Resend como opción recomendada](https://docs.railway.com/networking/outbound-networking#email-delivery).
Laravel 12 ofrece un
[transport oficial de Resend](https://laravel.com/docs/12.x/mail#resend-driver)
con una única dependencia, `resend/resend-php`. Frente a las alternativas:

| Opción | Integración y prueba | Coste orientativo auditado | Decisión |
|---|---|---|---|
| **Resend** | Transport oficial Laravel; el repo ya tiene mailer y `RESEND_API_KEY`. Permite key sólo de envío restringida al dominio, SPF/DKIM y destinatarios de prueba. No tiene sandbox de producción: `resend.dev` sólo entrega al propietario hasta verificar dominio. | Free: 3.000 mensajes/mes y 100/día; Pro desde 20 USD/mes. | **Seleccionada** por cambio mínimo, seguridad de credencial, ausencia de aprobación productiva y rollback simple al mailer anterior. |
| **Postmark** | Transport oficial Laravel con dos paquetes; sandbox black-hole, token de test y streams transaccionales. | Free de desarrollo: 100 mensajes/mes sin caducidad; Basic desde 15 USD/mes. | **Alternativa** si la prueba de entrega/operación de Resend no supera el gate. |
| Mailgun | Transport oficial Laravel con dos paquetes y nueva configuración; sandbox limitado a destinatarios autorizados y test mode. | Free: 100/día; Basic desde 15 USD/mes. | Válida, pero añade más configuración que Resend y no mejora el ajuste actual. |
| SendGrid | API HTTPS y SDK PHP oficial, pero Laravel 12 no aporta un transport SendGrid nativo. | Trial: 100/día durante 60 días; Essentials desde 19,95 USD/mes. | Descartada para este P0 por adaptación propia, mayor lock-in y coste de mantenimiento. |

Fuentes de precio y pruebas consultadas el 23-08-2026:
[Resend](https://resend.com/docs/knowledge-base/what-is-resend-pricing),
[Postmark](https://postmarkapp.com/pricing),
[Mailgun](https://www.mailgun.com/pricing/) y
[SendGrid](https://www.twilio.com/en-us/products/email-api/pricing). El precio
no sustituye la prueba de alta, dominio, entrega y condiciones aplicables a la
cuenta real.

La selección no es irreversible: queda condicionada a la prueba de staging.
No se ha creado cuenta, instalado paquete, cambiado DNS, cargado secret ni
enviado correo en 7G.1A. Si Resend no supera el gate, Postmark puede sustituirlo
sin cambiar el broker, los endpoints, la notificación o el frontend.

### Contrato propuesto para la implementación

Variables no secretas comunes:

```text
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=notificaciones@galotxesmonover.es
MAIL_FROM_NAME="Club Galotxes Monòver"
FRONTEND_URL=<origen HTTPS exacto del frontend del entorno>
```

Secret por entorno, almacenado sólo en el panel de Railway:

```text
RESEND_API_KEY=<key distinta por entorno, sending-only y restringida al dominio>
```

`MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME` y `MAIL_PASSWORD` no
forman parte del contrato Resend. Staging usará `MAIL_MAILER=array` fuera de la
ventana controlada; durante el gate usará `resend`, una key propia, un
remitente aprobado del dominio verificado y sólo buzones controlados. Producción
usará otra key y el remitente institucional. Las DNS exactas serán únicamente
las generadas para el dominio real, con revisión para preservar MX y acreditar
SPF, DKIM y DMARC; no se inventan registros en el repositorio.

Contacto continúa con `CONTACT_FORM_ENABLED=false` y
`CONTACT_NOTIFICATION_ENABLED=false`. Cuando se autorice, su override
`CONTACT_NOTIFICATION_MAILER` deberá ser `resend` o quedar vacío para heredar
el default. Escuela no tiene correo propio de confirmación: sólo el flujo
opcional de identidad de menores usa el mailer por defecto, y sus dos flags
siguen cerradas.

### Implementación local 7G.1B y gates restantes

7G.1B completa localmente los seis primeros pasos: fija
`resend/resend-php` 1.10.0, configura los ejemplos seguros, sustituye el
contrato SMTP del preflight, mantiene el `200` no enumerable ante fallo,
invalida el token no entregado, registra sólo un `failure_code` saneado y
cubre URL, tokens, expiración, reutilización, límites, transport y preflight.
El transport oficial se carga sin envío y la suite completa pasa sobre
MariaDB aislada. Staging usa `MAIL_MAILER=array` fuera de la ventana Resend y
producción no acepta `array` mientras el reset forme parte del MVP.

Quedan dos gates manuales:

1. en staging, solicitar un reset para una cuenta controlada, acreditar API
   HTTPS, aceptación y entrega, From, enlace al frontend de staging, reset,
   login nuevo, invalidez del token usado y logs saneados; probar también el
   fallo/revocación de key sin enumerar cuentas;
2. en producción, ejecutar un único smoke no destructivo con cuenta controlada,
   rotación aprobada y revisión de proveedor/logs.

No se ha creado cuenta, cargado secret, cambiado DNS, verificado dominio,
modificado Railway ni enviado correo real. Hasta completar el primer gate, la
integración está validada sólo localmente y el P0 sigue abierto.

### Contrato SMTP anterior, ahora bloqueado

El contrato de 7F.1 usaba Laravel Mail estándar con SMTP DonDominio en puerto
587 y STARTTLS. Se conserva aquí como referencia de rollback para un entorno
que permita SMTP, pero **no es ejecutable en Railway Hobby** ni es el contrato
seleccionado para cerrar el P0:

```text
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.dondominio.com
MAIL_PORT=587
MAIL_USERNAME=notificaciones@galotxesmonover.es
MAIL_PASSWORD=<secret de Railway>
MAIL_FROM_ADDRESS=notificaciones@galotxesmonover.es
MAIL_FROM_NAME="Club Galotxes Monòver"
```

Buzones y aliases previstos:

- `info@galotxesmonover.es` y `notificaciones@galotxesmonover.es` son buzones;
- `privacidad@galotxesmonover.es` y `escuela@galotxesmonover.es` redirigen a
  `info@galotxesmonover.es`;
- Contacto notifica a `info@`, usa `notificaciones@` como From y el email
  validado del solicitante sólo como Reply-To;
- mensajes a usuarios deben usar el canal institucional apropiado como
  Reply-To, nunca un dato del payload sin validar.

El correo anterior aparece en cuatro clases:

| Clase | Archivos | Tratamiento |
|---|---|---|
| Legal vigente | `legal/aviso-legal.md`, `legal/privacidad.md` y tres avisos en `legal/notices/` | No cambiar hasta confirmar buzones/aliases; después, nueva versión legal explícita |
| Proyecciones generadas | JSON Legal de frontend/backend | No editar; regenerar sólo desde Legal versionado |
| Runtime derivado | `seoMetadata.js` y plantilla de confirmación de identidad | Cambiar coordinadamente con la versión legal, no antes |
| Tests, borradores y auditoría histórica | `SeoProvider.test.jsx`, `docs/legal-drafts/`, docs 16 y 20 | Ajustar test al contrato futuro; conservar historia cuando corresponda |

Por tanto, 7F.1 no sustituye `clubgalotxesmonover@hotmail.com`. Primero se
crean buzones/aliases, se prueban recepción, envío, Reply-To, SPF/DKIM/DMARC y
rebotes; después revisión humana aprueba migrar general a `info@`, derechos a
`privacidad@` y Escuela a `escuela@`, con bump de versión y recompilación
determinista de Legal.

## Contacto, Escuela e identidad de menores

En el primer despliegue los tres frentes están apagados. Tras implementar y
probar el correo HTTPS seleccionado y cargar datos reales se activan de uno en
uno:

1. Contacto: aviso vigente, destinatario, persistencia, antispam, From,
   Reply-To, envío y reintento en staging; activar primero formulario y decidir
   separadamente la notificación;
2. Escuela: crear programa, presentación, proceso, ubicación activa, contacto
   privado válido, nivel público, horario y ubicación; validar `unavailable →
   closed → open` antes de activar la flag;
3. identidad de menores: probar token, expiración, confirmación, revisión,
   vinculación, conformidad 14–17, revocación y notificación; activar
   autorización y después notificación.

Ningún flag frontend decide estas reglas. `deploy:check
--allow-live-features` debe pasar antes y después de cada cambio.

## Scheduler y comandos de purga

7F.1 no registra cron. Estos comandos existen y permanecen manuales:

```bash
php artisan contact:purge-abuse-hashes --dry-run
php artisan contact:purge-expired --dry-run
php artisan school:purge-expired --dry-run
```

Frecuencia candidata una vez activados los flujos: revisión diaria, porque
los comandos son idempotentes y se basan en fechas. Antes de programar:

1. confirmar flags y políticas vigentes;
2. tomar/revisar el backup;
3. ejecutar `--dry-run` y registrar sólo conteos;
4. revisar holds y vencimientos desde Blade;
5. ejecutar manualmente sin `--dry-run`;
6. repetir dry-run y validar conteos/errores;
7. ensayar en staging;
8. sólo entonces crear un proceso Railway separado y activar
   `DEPLOYMENT_SCHEDULER_ENABLED` en otro bloque.

No se registran PII, tokens, `Authorization`, credenciales SMTP ni payloads de
formularios. Railway recibe logs de aplicación por `stderr`; Vercel sólo debe
registrar la entrega estática. Los fallos de mail conservan códigos
sanitizados, no destinatarios ni cuerpos completos.

## Runbook DNS

No se modifica DNS durante 7F.1. Cuando existan los proyectos:

1. anotar TTL y exportar/capturar la zona actual;
2. copiar exactamente el target que Vercel muestre para el apex;
3. conectar `www` a Vercel y comprobar el redirect permanente al apex;
4. copiar exactamente el target que Railway muestre para `api`;
5. conservar todos los MX de DonDominio al cambiar registros web;
6. configurar SPF según el valor exacto de DonDominio, sin duplicar políticas;
7. habilitar DKIM si DonDominio proporciona selector y valor;
8. publicar DMARC inicialmente en modo de observación según revisión humana;
9. bajar TTL antes de la ventana sólo si se ha planificado y restaurarlo
   después;
10. comprobar resolución, HTTPS, MX, recepción y envío antes de activar flags.

No se inventan IP, CNAME, MX, selector DKIM ni política SPF: se copian del
panel vigente de cada proveedor.

## Capacidades vigentes de Railway para recuperación

Consulta realizada el **2026-08-24** sobre documentación oficial:

- [Backups de volúmenes](https://docs.railway.com/volumes/backups);
- [límites y caveats de volúmenes](https://docs.railway.com/volumes/reference);
- [precios](https://docs.railway.com/pricing);
- [Image Auto Updates](https://docs.railway.com/deployments/image-auto-updates);
- [Storage Buckets](https://docs.railway.com/storage-buckets).

**CORRECCIÓN 2026-08-24:** La verificación operativa demuestra que Railway restringe los backups nativos y PITR al plan Pro (`maxBackupsCount = 0`). La documentación pública era ambigua. Esta restricción cierra la posibilidad de utilizar backups nativos o snapshots en el plan Hobby. El MVP dependerá íntegramente de la recuperación mediante dump lógico portable.

Contrato de Railway verificado para este workspace Hobby:

1. La interfaz indica: "Backups and point-in-time recovery (PITR) are only available for customers on the Pro plan."
2. El límite efectivo del workspace es `maxBackupsCount = 0`.
3. Por tanto, no se pueden crear backups nativos (ni manuales, ni programados) para volúmenes en el plan Hobby.
4. Cualquier mención a `snapshot` o restore nativo dentro del entorno Hobby queda descartada para este proyecto.

## Arquitectura recuperable de Galotxas

Un snapshot del volumen MariaDB no es un backup completo del sistema:

| Capa | Incluye | No incluye / observaciones |
|---|---|---|
| MariaDB | Usuarios, players y tokens; Temporadas, Campeonatos, Categorías, equipos, inscripciones, partidos, resultados y rankings derivados; CMS, bloques y navegación; metadatos de Noticias y Sponsors; Escuela, contacto y autorizaciones; sesiones, password resets, caché y tablas de jobs. | Sólo conserva las object keys de media, no los binarios. Restaurar también puede reintroducir sesiones, tokens o caché antiguos y exige una decisión explícita de invalidación/limpieza antes del cutover. |
| Bucket privado `media_s3` | Avatares, logos de Sponsors, portadas de Noticias y futuros objetos multimedia. | Es independiente del volumen MariaDB. Railway Buckets no ofrece actualmente snapshots/backups, object versioning, object lock ni lifecycle; la ventana de dos días para recuperar un bucket eliminado no recupera un objeto borrado individualmente. |
| Git y configuración de plataforma | Git conserva código, migraciones, `knowledge/`, `legal/`, docs y ejemplos de configuración sin secrets. | Variables reales, secrets, DNS y ajustes de Vercel/Railway requieren inventario operativo separado y no deben copiarse a Git ni al dump. |

La imagen productiva del backend no instala `mariadb-dump`. El dump lógico no
puede darse por disponible dentro del contenedor Laravel. La automatización
versionada usa una imagen efímera separada en `backend/backup/`, con cliente
MariaDB 11.4, restic y rclone, y mantiene las credenciales fuera de argumentos
y logs. El job no forma parte del runtime web ni restaura automáticamente.

### Job externo de backup preparado en 7G.3

La prueba manual de producción acreditó un snapshot restic cifrado con dump y
media, restauración aislada, SHA-256 idéntico para el dump, importación en una
MariaDB 11.4 efímera y `restic check` sin errores. Sobre esa evidencia se
versiona un job no interactivo, todavía **no desplegado ni programado**, con el
siguiente contrato:

- ejecución por defecto: `/usr/local/bin/galotxas-backup backup`;
- comprobación sin snapshot nuevo: `/usr/local/bin/galotxas-backup check`;
- configuración rclone, cliente MariaDB y password-file restic temporales con
  permisos `0600` y limpieza mediante `trap` en éxito, error o señal;
- dump con transacción única, rutinas, triggers, eventos y binarios seguros;
- copia completa del bucket privado, admitiendo inventario vacío;
- manifiesto técnico sin credenciales con fecha UTC, tamaños, recuento de
  media y SHA-256 del dump;
- snapshot y retención con agrupación estable `host,tags`, además de filtros
  por ese host/tag, para que los paths temporales aleatorios formen un único
  conjunto automatizado de 14 diarios, 8 semanales y 12 mensuales;
- `forget --prune` únicamente después de crear el snapshot y fallo observable
  si la retención no concluye;
- el snapshot manual `7G.3-pre-migration` conserva host/tag distintos y queda
  fuera de la selección y retención del job automatizado;
- ninguna operación de restore contra producción.

Los nombres y defaults no sensibles están inventariados en
`backend/backup/.env.example`. Credenciales DB de backup, S3, OAuth de Google y
password restic se cargarán exclusivamente como secrets/references del servicio
efímero. Desplegar el servicio, vincular referencias, elegir schedule y ejecutar
el primer backup automatizado siguen siendo pasos manuales posteriores; este
cambio no cierra 7G.3.

Existe además un gate previo de OAuth: la aplicación propia `Galotxas Backup`
está configurada como External + Testing. Google documenta que, al solicitar
scopes distintos de identidad básica como Drive, los refresh tokens emitidos en
Testing caducan a los siete días. El token actual no es una credencial
productiva definitiva. Antes de desplegar o programar el job, el operador debe,
si Google lo permite para este uso, pasar la aplicación a `In production`,
revocar la concesión de Testing, autorizarla de nuevo y guardar como secret el
nuevo token. La referencia canónica es la sección
[Refresh token expiration de Google OAuth 2.0](https://developers.google.com/identity/protocols/oauth2#expiration).

## Estrategia de backup en capas

La combinación mínima propuesta para el MVP es:

### Capa 1 — (Descartada) snapshot nativo del volumen MariaDB

- (No aplicable) Railway Hobby restringe esta capacidad al plan Pro.
- La exigencia de "snapshot nativo predeploy" queda eliminada.

- activar schedules Daily, Weekly y Monthly con la retención propia de Railway;
- crear un backup manual inmediatamente antes de cualquier migración con
  esquema o intervención destructiva, comprobando antes el límite del 50 %;
- registrar entorno, volumen, timestamp, estado completo y tamaño, sin datos o
  credenciales;
- conservar el snapshot como recuperación rápida dentro del mismo project y
  environment, no como copia portable ni como único backup.

### Capa 2 — dump lógico MariaDB portable

- generar diariamente un dump consistente con cliente 11.4 verificado,
  transacción única para tablas transaccionales, triggers, rutinas, eventos y
  binarios seguros;
- comprobar exit code y salida no vacía, comprimir, validar el archivo
  comprimido y generar SHA-256;
- cifrar antes de abandonar Railway y copiar a almacenamiento restringido
  fuera del servicio y del proyecto productivo;
- conservar, como propuesta inicial, 30 copias diarias y tres cierres
  mensuales; eliminar una copia sólo después de acreditar otra reciente;
- alertar por ausencia, fallo, checksum, crecimiento anómalo o falta de espacio.

### Capa 3 — copia independiente de media

- inventariar diariamente las keys referenciadas por MariaDB y los objetos del
  bucket, sin publicar nombres, URLs firmadas ni PII;
- copiar los objetos a un destino independiente con versionado o namespaces
  fechados y retención mínima de 30 días; Railway Buckets no puede aportar por
  sí mismo ese versionado o lifecycle;
- conservar tamaño y SHA-256 cuando la herramienta pueda calcularlo; no tratar
  el ETag de un multipart como checksum criptográfico;
- ensayar la recuperación de al menos un objeto y reconciliar key, bytes,
  tipo, privacidad y ruta Laravel. Si el bucket productivo estuviera vacío,
  registrar el inventario cero y completar el ensayo antes del primer upload
  real.

El gate por defecto exige las tres capas cuando existan objetos. Aceptar
temporalmente el riesgo de media sin copia requiere un `GO` humano explícito,
fechado y limitado; no queda implícito por la durabilidad del bucket.

## RPO, RTO y propiedad propuestos

Valores a aprobar, no SLA de proveedor:

- RPO: **24 horas** para MariaDB y media; backup manual adicional inmediatamente
  antes de una migración con esquema;
- RTO: **4 horas** para recuperar el núcleo en modo controlado/read-only y
  **8 horas** para reabrir todos los flujos de escritura, medidos desde la
  declaración del incidente dentro de una ventana con responsable disponible;
- retención: schedules nativos 6 días/1 mes/3 meses, dump lógico 30 diarios y
  3 mensuales, copia de media mínima de 30 días;
- propiedad: una persona responsable de operación (mantenedor único sin suplente, política de congelación de cambios ante ausencia) revisa el
  último backup, reciben fallos, autorizan restore y registran cada ensayo.

El RPO/RTO sólo pasan de objetivo a evidencia después del drill. El drill 7G.1D arrojó un RTO de 5 min 27 s (cumpliendo el objetivo de 4 h para núcleo read-only). El RTO para reapertura de escrituras sigue sin medirse. Si el tiempo
observado supera el objetivo, se ajusta el procedimiento o se registra un
`NO-GO`; no se corrige la cifra retrospectivamente para declarar éxito.

## Gate predeploy con cambios de esquema

Antes de cualquier `php artisan migrate --force` productivo:

1. fijar hash candidato, entorno, operador, ventana y rollback compatible;
2. ejecutar preflight y `php artisan migrate:status`, guardando sólo salida
   saneada y la lista exacta de migraciones pendientes;
3. comprobar estado, uso y capacidad del volumen (snapshot nativo no aplicable en Hobby);
   manual y esperar a que figure completo;
4. generar el dump lógico con cliente compatible, comprimirlo, validar que es
   legible y no vacío, calcular SHA-256 y cifrarlo;
5. copiar el artefacto fuera del servicio/proyecto y volver a verificar el
   checksum sobre la copia almacenada;
6. registrar timestamp UTC, hash candidato, migraciones, tamaño, checksum,
   retención, ubicación lógica y mantenedor, nunca secrets o contenido;
7. confirmar el último inventario/copia de media cuando la release pueda
   modificar referencias u objetos;
8. obtener aprobación humana. Sólo entonces ejecutar una vez
   `php artisan migrate --force`.

Una DB completamente vacía puede registrar “sin datos que preservar”, pero no
permite saltarse el restore drill previo al Go ni la activación de la política
para los siguientes cambios.

## Restore drill obligatorio en staging

Se comparan dos ensayos, siempre sin E2E seeders, `migrate:fresh`, `db:wipe` o
`migrate:reset`:

| Opción | Procedimiento seguro | Verificación y vuelta atrás |
|---|---|---|
| Restore nativo Railway | (No aplicable en Hobby) | (No aplicable en Hobby) |
| Restore lógico aislado | Tomar un dump de staging, copiarlo con checksum a una MariaDB temporal separada y sin tráfico, importar con cliente compatible y nunca cambiar `DB_*` del servicio activo. | Verificar checksum, migraciones, constraints, conteos agregados por dominio, object keys sin descargar media, `deploy:check` y smoke de lectura controlado. Registrar duración y destruir sólo el destino temporal y sus credenciales. |

El **restore lógico aislado es el ensayo obligatorio antes del Go**: acredita
el contenido real del artefacto portable y evita que la restricción nativa de
mismo environment obligue a intervenir la DB activa. El ensayo nativo sobre
servicio desechable se recomienda en la misma ventana para acreditar el flujo
staged, el redeploy y la recuperación del volumen anterior, pero no sustituye
al lógico.

Un backup no queda acreditado hasta que el drill lógico termine con checksum,
conteos, migraciones, seguridad de sesiones/tokens, duración y responsable. No
se promueve el destino temporal. Un restore productivo posterior exige además
preservar el estado actual, bloquear escrituras cuando corresponda y decisión
humana explícita.

## Runbook de rollback y maintenance

Rollback de aplicación, restore de datos y corrección de esquema son acciones
distintas:

| Superficie | Trigger y acción | Evidencia | Riesgo, responsable y límite objetivo |
|---|---|---|---|
| Frontend Vercel | Defecto crítico sólo cliente: promover el deployment inmutable anterior y repetir smoke. | Hash/deployment anterior, hora, rutas y resultado del smoke. | Incompatibilidad con API o caché; operación con revisión frontend, 30 min. |
| Backend Railway | Error crítico de API/admin: redeploy anterior sólo si sigue siendo compatible con el esquema; si no, forward fix. | Hash/deployment, `migrate:status`, logs saneados y smoke. | Código anterior incompatible; operación + responsable técnico, 60 min. |
| DB sin migración | Corrupción o borrado: congelar escrituras, preservar el estado actual, restaurar primero en destino aislado y decidir cutover. | Snapshot/dump/checksum, conteos, RPO perdido y RTO observado. | Pérdida de cambios posteriores y sesiones/tokens revividos; decisión conjunta, objetivo 4 h. |
| DB tras migración forward-only | Priorizar release compatible o migración correctiva. Sólo ante daño irreversible coordinar artefacto anterior con restore del dump lógico predeploy. Nunca ejecutar `migrate:rollback` por reflejo. | Matriz código/esquema, dump lógico predeploy, migraciones y aprobación. | Pérdida de escrituras posteriores; técnico + operación + producto, decisión durante la ventana y recuperación objetivo 4–8 h. |
| Media | Objeto ausente/corrupto: restaurar sólo la versión afectada desde la copia independiente y reconciliarla con su key DB. | Inventario, SHA-256 si existe, tipo, privacidad y serving Laravel. | Desalineación DB/objeto o exposición; operación + dueño editorial/privacidad, objetivo 4 h por objeto. |

`php artisan down` se usa sólo cuando una migración incompatible o una
intervención exige bloquear escrituras. `/up` continúa disponible para la
plataforma. Antes se valida acceso de consola; después se ejecutan preflight y
smoke, y sólo entonces `php artisan up`.

El parte registra release, migraciones, timestamp, trigger, decisor, acción,
duración, pérdida asumida, resultado y próximos pasos, sin secrets, dumps,
object keys ni datos personales.

## Observabilidad mínima

Sin añadir SaaS ni analytics, la operación revisa:

- disponibilidad continua de web/API desde una comprobación externa futura;
- `/up` durante el despliegue;
- errores y latencia en Railway/Vercel sin payloads;
- espacio, conexiones y crecimiento de MariaDB;
- estado y antigüedad del último backup;
- resultado del último restore test;
- estados de notificación `failed` y reintentos desde Blade;
- capacidad de disco efímero y cachés;
- expiración de dominio, TLS, SPF, DKIM y DMARC.

La ausencia de un monitor externo persistente es un gate manual de 7F, no una
capacidad que 7F.1 finja resolver.

## Aceptación de staging (Ejecución real de 7F)

Los siguientes pasos ya se han validado en staging:
1. recursos físicamente separados sin datos reales (Vercel/Railway) creados;
2. secrets cargados por panel y confirmado `APP_DEBUG=false`;
3. preflight frontend y backend superados;
4. MariaDB vacía migrada exitosamente;
5. admin de prueba seguro creado;
6. CMS recreado manualmente en borrador, publicado y comprobado (`nosotros`, `contacto`, `federarse`, `documentos`);
7. Legal, Knowledge y hashes validados con scripts en staging;
8. API, CORS exacto, proxy, HTTPS, auth y administración validados sobre dominio real;
9. robots `noindex, nofollow` y ausencia de sitemap confirmados en staging;
10. Contacto y Escuela probados en ventana controlada con `log`, validando persistencia y fail-closed; el SMTP real desde Railway está BLOQUEADO por el plan Hobby, por lo que la notificación de Contacto, el reset de contraseña y el ciclo de identidad pública de menores siguen pendientes.

Pasos **Pendientes / Aplazados** en staging:
11. **Ensayar el rollback coordinado.** La fase 7G.1D ejecutó y validó con éxito el restore lógico aislado de staging con RTO de 5m27s, cerrando el P0 de recuperación de base de datos para este entorno.
    programado de volumen no está documentado como exclusivo de Pro; la
    estrategia está reconciliada, pero ningún backup o restore se ejecutó en
    este bloque.
12. **Ejecutar smoke completo global y aceptación humana para cerrar la fase 7F staging (COMPLETADO).** *(Nota: Se ha registrado una observación de UX no bloqueante en `/aprende-a-jugar` para 7G o posterior).*

La aceptación humana de staging queda completada. Staging ha quedado devuelto a un estado seguro (`CONTACT_FORM_ENABLED=false`, `SCHOOL_ENROLLMENT_ENABLED=false`, etc.). Nunca se enviaron correos a usuarios reales.

## Primer despliegue y aceptación de producción

Orden manual:

1. crear Vercel (pendiente), Railway backend (creado, 0 deploys) y MariaDB (creada 11.4) sin conectar todavía el dominio;
   *Nota 7G.3: `APP_KEY` de producción ha sido corregida operativamente a una clave Laravel válida (formato base64, adecuada para AES-256) sin deployment. Estado: RESUELTA a nivel de configuración/formato; pendiente de validación runtime mediante preflight tras el primer deployment. `APP_FAKER_LOCALE` ausente (no bloqueante). `SESSION_DOMAIN` ausente (fallback host-only). `CONTACT_NOTIFICATION_MAILER` ausente (fallback global). Variables `MEDIA_*` configuradas en Railway pero P1 de documentar en `.env.production.example`.*
2. cargar variables/secrets con todos los interruptores cerrados;
3. ejecutar preflight y construir artefactos; aceptar antes de migrar sólo el
   bloqueo backend esperado por esquema pendiente;
4. acreditar previamente el restore drill de staging y, si ya existe
   información productiva, completar el gate predeploy con dump lógico consistente,
   compresión, checksum y cifrado;
5. migrar MariaDB manualmente;
6. crear administrador;
7. validar `/up`, API y admin en URLs de proveedor;
8. configurar DNS sin alterar MX y esperar TLS válido;
9. desplegar frontend noindex y validar canonical/metadata/robots;
10. recrear CMS en borrador y publicar tras revisión;
11. activar schedules nativos, verificar la copia lógica/media y registrar su
    responsable;
12. ejecutar smoke no destructivo;
13. obtener aceptación humana;
14. activar indexación en una release separada;
15. activar Contacto, Escuela, identidad/notificaciones y scheduler, uno por
    uno y sólo después de sus gates.

No se considera 7F cerrada hasta completar y evidenciar esas acciones.

## Gate de staging de Noticias 7F.2E

7F.2E ha superado su aceptación funcional manual en staging. Su checklist se
ejecutó con la salvedad de recuperación que se explicita en el punto 1:

1. preflight completado; el backup previo permaneció aplazado bajo la premisa
   entonces vigente y no se acepta retrospectivamente como evidencia de
   recovery;
2. revisar `php artisan migrate:status` antes de cambiar el esquema;
3. ejecutar explícitamente `php artisan migrate --force`;
4. repetir `migrate:status` y confirmar la nueva migración;
5. ejecutar `php artisan deploy:check`;
6. crear una noticia borrador desde Blade;
7. confirmar que borrador, detalle e imagen no son públicos;
8. subir una portada de prueba sin personas a `media-staging`;
9. publicar dos noticias efectivas;
10. comprobar destacada, cards y orden cronológico en `/noticias`;
11. comprobar detalle, fecha, cuerpo, alt y crédito;
12. verificar el redirect y render de portada pública S3;
13. confirmar que JSON/API/HTML no exponen `image_key` ni procedencia/derechos;
14. programar una noticia futura y confirmar que permanece oculta;
15. reemplazar una portada y acreditar cleanup del objeto anterior;
16. eliminar una noticia y acreditar 404 y cleanup;
17. hacer redeploy y confirmar persistencia de filas y objetos vigentes;
18. revisar índice y detalle a 320 px sin overflow;
19. recorrer Navbar, listado y detalle sólo con teclado;
20. verificar canonical, Open Graph article, JSON-LD y limpieza en 404;
21. revisar logs saneados sin key, URL firmada, procedencia o PII;
22. comprobar sin regresión `/contenidos/prensa-media`, CMS, Sponsor y avatar;
23. obtener aprobación editorial y de derechos de las imágenes de prueba.

Un deploy marcado como `SUCCESS` no acredita que la migración se haya
ejecutado (como evidenció el error 500 inicial por la ausencia de la tabla `news_articles`, resuelto al aplicar `migrate --force`). Este gate y la aceptación humana de 7F.2E se consideran completados.
La salvedad del backup no reabre la feature, pero permanece dentro del P0
transversal de recuperación que debe ensayarse antes del Go.

## Gate de staging de Navegación CMS 7F.2F

Este checklist queda documentado para la futura promoción. Se ha ejecutado
con éxito en staging sobre datos temporales y sin seeders editoriales:

1. ejecutar `php artisan migrate:status`;
2. ejecutar `php artisan migrate --force`;
3. repetir `php artisan migrate:status` y confirmar la nueva migración;
4. crear una `CmsPage` temporal;
5. añadir contenido válido y publicarla;
6. crear un placement del slot Club;
7. activarlo;
8. verificar el item en API y Navbar;
9. comprobar el orden con dos items CMS temporales si procede;
10. navegar y verificar padre Club activo y `aria-current="page"` exacto;
11. pasar la página a borrador y comprobar que desaparece;
12. republicarla y comprobar que reaparece;
13. desactivar el placement y comprobar que desaparece;
14. reactivarlo y comprobar que reaparece;
15. eliminar el placement y comprobar que desaparece sin borrar la página;
16. verificar que una asignación de slug reservado es rechazada;
17. revisar el flujo a 320 px;
18. revisar teclado, Escape y retorno de foco;
19. comprobar intactos los cuatro hijos estructurales de Club;
20. comprobar intactos Noticias, Competición y Aprende;
21. comprobar degradación structural-first con API vacía/error cuando pueda
    hacerse sin alterar infraestructura;
22. hacer redeploy y confirmar persistencia de página y placement vigentes;
23. revisar logs saneados sin payloads, sesiones o datos editoriales privados;
24. eliminar placements y devolver/eliminar páginas temporales según las
    capacidades seguras disponibles.

`deploy SUCCESS != migrations applied`: 7F.2F ha acreditado explícitamente los
pasos de migración y el recorrido funcional en staging. Este gate y la
aceptación humana de 7F.2F se consideran completados.

## Smoke no destructivo post-deploy

- `/`, `/competicion`, Aprende, Manual y un documento Knowledge;
- `/escuela` visible pero inscripción cerrada;
- cuatro fachadas Club y Contacto cerrado;
- tres páginas Legal;
- `/up` mínimo y una lectura API pública;
- registro/login/logout/reset, Mi Panel y contratos 401/403/419;
- login Blade y navegación administrativa sin escrituras innecesarias;
- robots, ausencia/presencia esperada de sitemap, canonical y metadata;
- apex, redirect `www`, API y HTTPS sin mixed content;
- ninguna carga automática de recursos remotos retirada;
- Contacto, Escuela, identidad de menores y scheduler cerrados;
- logs sin PII/secrets y sin errores repetidos;
- cuando 7F.2B configure staging: `media:probe --temporary-url` correcto y sin
  objeto residual;
- para aceptar 7F.2C: crear dos Sponsors de prueba, comprobar orden, HTTPS y
  rejilla pre-footer; reemplazar logo y verificar cleanup; probar fechas,
  desactivación y 320 px; redeploy y confirmar persistencia; borrar ambos y
  verificar objetos ausentes; (Aceptación superada y bloque cerrado).
- para aceptar 7F.2D: ejecutar primero la consulta legacy; iniciar sesión con
  una cuenta sin depender de `Player`; subir una imagen real y comprobar
  objeto privado, `/me` sin key y foto visible mediante `200` desde la API, sin
  `302` ni `Location`; sustituir y verificar cleanup;
  borrar y verificar cleanup/fallback; repetir tras redeploy, a 320 px y por
  teclado; comprobar que ninguna API o vista pública publica la foto y que los
  logs no contienen key, token o PII;
- para aceptar 7F.2E: ejecutar íntegramente el gate anterior, incluida la
  migración explícita, publicación/orden, serving S3, programación,
  replace/delete con cleanup, redeploy, privacidad, SEO, 320 px, teclado y
  aprobación editorial;
- para aceptar 7F.2F: ejecutar íntegramente su checklist de 24 pasos, incluida
  migración explícita, publicación efectiva, orden structural-first,
  active/draft/republish, reserved, fail-soft, redeploy, 320 px, teclado y
  cleanup temporal;
- estado del último backup.

Las escrituras de aceptación se realizan primero en staging. Producción no usa
E2E, seeders ni cuentas con password por defecto.

## Riesgos y gates abiertos

- no existen todavía proyectos, DNS, TLS o recursos externos de producción
  configurados; los equivalentes de staging sí existen y fueron validados;
- SMTP saliente está bloqueado por el plan Railway Hobby; Resend HTTPS está
  integrado y se ha validado operativamente con éxito extremo a extremo en staging
  (dominio, SPF, DKIM, DMARC p=none, entrega de reset). Para producción faltan
  sus propios dominios, aliases, logs y keys independientes;
- el correo anterior sigue siendo el canal Legal vigente;
- CMS y datos reales de Escuela no están cargados en producción;
- rollback rehearsal y monitor continuo no están
  acreditados;
- HSTS/CSP, otros uploads de features, worker y scheduler continúan aplazados;
  7F.2D está aceptada completamente en staging y cerrada; la configuración multimedia productiva sigue
  abierta;
- la SPA mantiene metadata client-side y la respuesta HTTP de rutas React no
  constituye SSR;
- el token Bearer continúa en `localStorage`, según la decisión vigente;
- Copa debe aceptarse antes de reconciliar el candidato en 7G.1; la regresión
  global final 7F.2 constituye 7G.2 y debe cerrarse antes de preparar
  producción;
- 7G debe validar aceptación final antes de tag/release, conforme al gate
  ordenado de `29-mvp-final-acceptance-and-production-gate.md`.

## Criterio de cierre de 7F.1

7F.1 sólo puede marcarse técnicamente completada cuando tests backend/frontend,
lint, builds normal y production-like noindex, SEO, Legal, Knowledge, hashes,
estatutos, E2E, imagen Railway, cachés, Pint, `php -l` y `git diff --check`
pasen sin modificar `frontend/dist`, `knowledge/`, `legal/`, datos locales o
servicios externos. Ese cierre no equivale a un despliegue y mantiene 7F,
Fase 7 y MVP abiertos.
