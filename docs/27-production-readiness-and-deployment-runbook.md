# Preparación productiva, entornos y runbook de despliegue

## Estado y alcance

`PRODUCTION-READINESS-1` preparó el repositorio para staging y producción sin
crear proyectos. Tras la ejecución manual parcial de 7F, el entorno de **staging** está
desplegado y validado (proyectos en Vercel/Railway, base de datos MariaDB, DNS
personalizado, dominios canónicos de staging). 
El despliegue a **producción** permanece pendiente.
Fase 7, 7F (completa), 7G y el MVP permanecen abiertos.

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
| `production` | Servicio público | MariaDB productiva persistente | Datos reales revisados; SMTP sólo tras gate | Cerrada en el primer deploy |

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
| Vercel | Proyecto `galotxas-staging` vinculado y desplegado | Crear proyecto producción |
| Railway | Servicios backend y MariaDB activos para staging | Crear servicios de producción |
| MariaDB | DB de staging operativa, migraciones completadas | Crear DB producción, **backups y restore test (aplazados)** |
| CORS | Origen exacto, sin patrones, wildcard o cookies CORS | Cargar el origen real de cada entorno |
| Auth | Sanctum Bearer existente; contratos 401/403/419 intactos | Smoke HTTPS de registro/login/logout/reset |
| Sesión Blade | Cookie Secure/HttpOnly/SameSite y driver DB en ejemplos | Validar admin detrás del proxy |
| Proxy/HTTPS | `TRUSTED_PROXIES` y cabeceras Traefik | Verificar cadena Railway y URL generada |
| Salud | `/up` mínimo, independiente de DB; readiness CLI | Monitor externo y ejecución preflight |
| Admin | `admin:create` interactivo e idempotente | Crear la primera cuenta por consola |
| CMS | Páginas staging recreadas manualmente y aprobadas | Recrear y aprobar en producción |
| Legal/Knowledge | Hashes validados y activos en staging | Validar hashes en producción |
| SEO | Staging validado como no indexable (`robots.txt`) | Activar indexación en producción posterior |
| Contacto/Escuela/menores | Capacidades validadas con fail-closed y flag global; persistencia staging probada | SMTP real, flujo menores completo en staging |
| Queue/scheduler | Cola síncrona, ningún worker/cron productivo | Diseñar y ensayar purgas antes de activar |
| Logs/storage | `stderr`; núcleo `media_local` validado, sin consumidores y sin bucket remoto | Crear bucket privado aislado, configurar `media_s3` y superar probe/gate de staging antes de uploads |
| Backups/restore/rollback | **Aplazado por decisión operativa** (backup nativo bloqueado por plan) | Contratar Pro para backup nativo, o ensayar backup manual en staging |

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

El filesystem de la aplicación es efímero. Sólo aloja cachés, sesiones si se
configuraran como archivo local por error y logs transitorios. Producción usa
sesión y caché en MariaDB, logs en `stderr` y todavía no admite uploads de
features. Los assets e imágenes públicas versionadas en Git forman parte de la
imagen.

Fase 7F.2B añade la infraestructura multimedia persistente S3-compatible:

- `FILESYSTEM_DISK=local` permanece inalterado;
- `MEDIA_DISK=media_local` permite desarrollo privado en
  `storage/app/media`, sin `storage:link` ni URL temporal local;
- `media_s3` acepta únicamente variables `MEDIA_*`, usa visibilidad privada y
  propaga fallos;
- `php artisan media:probe` comprueba escritura, tamaño, existencia y cleanup;
  `--temporary-url` añade la capacidad de URL firmada cuando el adapter la
  ofrece.

No se han añadido variables `MEDIA_*` a los contratos remotos porque todavía
no existe bucket. En el siguiente subbloque de 7F.2B deberán crearse recursos
físicamente separados, cargar secrets sólo en Railway, seleccionar
`MEDIA_DISK=media_s3` y ejecutar el probe con URL temporal en staging. El
resultado no podrá promoverse a producción ni habilitar Banners, Avatar,
Noticias o CMS hasta superar ese gate.

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
solo. En una DB vacía o una release con cambios:

1. ejecutar los dos preflight con flags cerradas; antes de la primera
   migración el backend puede bloquear exclusivamente por repositorio o
   migraciones pendientes, y cualquier otro bloqueo detiene la operación;
2. tomar backup cuando ya exista información;
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

El backup diario protege esta persistencia editorial. Ningún deploy debe
ejecutar un seeder general.

## Correo y migración del canal anterior

El contrato futuro usa Laravel Mail estándar. Laravel 12 configura el SMTP de
puerto 587 con `MAIL_SCHEME=smtp`; Symfony Mailer negocia STARTTLS cuando el
servidor lo ofrece. La verificación manual debe acreditar TLS antes de activar:

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

En el primer despliegue los tres frentes están apagados. Tras configurar SMTP
y datos reales se activan de uno en uno:

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

## Runbook de backup

Política mínima: dump lógico diario, unos 30 días de retención y una copia
separada de la DB productiva. La copia no puede vivir únicamente en el mismo
servicio o volumen. Si sale del proveedor se cifra antes de transferirla.

Para cada backup:

1. identificar entorno, release y hora UTC sin incluir PII en el nombre;
2. ejecutar `mariadb-dump` con transacción consistente, triggers y rutinas,
   obteniendo credenciales desde un mecanismo protegido, no la línea de
   comandos ni Git;
3. comprobar salida no vacía y exit code;
4. generar SHA-256;
5. cifrar cuando la copia abandone Railway;
6. mover a almacenamiento separado de acceso restringido;
7. registrar fecha, tamaño, checksum y resultado, no contenido;
8. aplicar retención aproximada de 30 días sólo tras confirmar que existe una
   copia reciente válida;
9. alertar por ausencia, fallo, crecimiento anómalo o falta de espacio.

Un backup no está acreditado hasta superar un restore test. RPO candidato:
hasta 24 horas, coherente con la frecuencia diaria pero todavía no garantizado.
El RTO se mide en el ensayo y requiere aprobación; no se promete un valor sin
evidencia.

## Runbook de restore

Nunca se restaura primero encima de producción:

1. activar maintenance o bloquear escrituras si el incidente lo exige;
2. preservar un backup de la DB actual, incluso dañada;
3. verificar checksum, cifrado, fecha y cadena de custodia;
4. crear una MariaDB temporal aislada;
5. restaurar allí el dump con cliente compatible;
6. ejecutar `migrate:status` y `deploy:check` contra la DB temporal;
7. comparar migraciones y conteos agregados sin volcar PII en logs;
8. ejecutar smoke de lectura y flujos controlados;
9. documentar duración, incidencias y resultado;
10. decidir humanamente si se promueve, se corrige hacia delante o se aborta;
11. sólo con aprobación, ventana y rollback preservar/cambiar el destino
    productivo;
12. retirar de forma segura la DB temporal y su acceso al finalizar.

Staging debe completar al menos un restore test antes de la apertura definitiva.

## Runbook de rollback y maintenance

Frontend: promover o restaurar el deployment Vercel anterior y repetir el
smoke. Backend: usar el deployment Railway anterior sólo si su código es
compatible con el esquema ya migrado. La DB no recibe `migrate:rollback`
automático; se prefiere una migración correctiva. Restore es el último recurso
y sigue el runbook anterior.

`php artisan down` se usa sólo cuando una migración incompatible o una
intervención exige bloquear escrituras. `/up` continúa disponible para la
plataforma. Antes se valida acceso de consola; después se ejecutan migración,
preflight y smoke, y sólo entonces `php artisan up`. Un fallo de código sin
cambio de esquema se resuelve normalmente promoviendo la release anterior.

El parte de rollback registra release, migraciones aplicadas, hora, motivo,
decisor, resultado y próximos pasos, sin copiar secrets o datos personales.

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
11. **Ejecutar backup, restore a DB temporal y rollback (El backup nativo está bloqueado por requerir cuenta Railway Pro. La alternativa de backup manual y el rollback de aplicación se posponen por decisión operativa).**
12. **Ejecutar smoke completo global y aceptación humana para cerrar la fase 7F staging (COMPLETADO).** *(Nota: Se ha registrado una observación de UX no bloqueante en `/aprende-a-jugar` para 7G o posterior).*

La aceptación humana de staging queda completada. Staging ha quedado devuelto a un estado seguro (`CONTACT_FORM_ENABLED=false`, `SCHOOL_ENROLLMENT_ENABLED=false`, etc.). Nunca se enviaron correos a usuarios reales.

## Primer despliegue y aceptación de producción

Orden manual:

1. crear Vercel, Railway backend y MariaDB sin conectar todavía el dominio;
2. cargar variables/secrets con todos los interruptores cerrados;
3. ejecutar preflight y construir artefactos; aceptar antes de migrar sólo el
   bloqueo backend esperado por esquema pendiente;
4. migrar MariaDB manualmente;
5. crear administrador;
6. validar `/up`, API y admin en URLs de proveedor;
7. configurar DNS sin alterar MX y esperar TLS válido;
8. desplegar frontend noindex y validar canonical/metadata/robots;
9. recrear CMS en borrador y publicar tras revisión;
10. comprobar backup y completar restore test;
11. ejecutar smoke no destructivo;
12. obtener aceptación humana;
13. activar indexación en una release separada;
14. activar Contacto, Escuela, identidad/notificaciones y scheduler, uno por
    uno y sólo después de sus gates.

No se considera 7F cerrada hasta completar y evidenciar esas acciones.

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
- estado del último backup.

Las escrituras de aceptación se realizan primero en staging. Producción no usa
E2E, seeders ni cuentas con password por defecto.

## Riesgos y gates abiertos

- no existen todavía proyectos, DNS, TLS o recursos externos configurados;
- SMTP saliente está bloqueado por el plan Railway Hobby; entrega, rebotes, SPF, DKIM, DMARC y aliases no están probados;
- el correo anterior sigue siendo el canal Legal vigente;
- CMS y datos reales de Escuela no están cargados en producción;
- backup, restore, RTO, rollback rehearsal y monitor continuo no están
  acreditados;
- HSTS/CSP, integración de uploads en features, gate S3, worker y scheduler
  continúan aplazados;
- la SPA mantiene metadata client-side y la respuesta HTTP de rutas React no
  constituye SSR;
- el token Bearer continúa en `localStorage`, según la decisión vigente;
- 7G debe validar aceptación final antes de tag/release.

## Criterio de cierre de 7F.1

7F.1 sólo puede marcarse técnicamente completada cuando tests backend/frontend,
lint, builds normal y production-like noindex, SEO, Legal, Knowledge, hashes,
estatutos, E2E, imagen Railway, cachés, Pint, `php -l` y `git diff --check`
pasen sin modificar `frontend/dist`, `knowledge/`, `legal/`, datos locales o
servicios externos. Ese cierre no equivale a un despliegue y mantiene 7F,
Fase 7 y MVP abiertos.
