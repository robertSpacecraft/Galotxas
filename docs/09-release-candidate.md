# Candidato MVP — Galotxas 0.1.0 RC1

## Estado

**Clasificación: RC PREPARADO CON LIMITACIONES.**

La versión propuesta es `v0.1.0-rc.1`. El sistema supera la instalación limpia y la regresión funcional, y no se han detectado P0 ni P1. No está etiquetado, publicado ni desplegado.

Este documento distingue un candidato MVP funcional de un despliegue de producción. TLS, proxy inverso, copias de seguridad, monitorización, correo real y operación productiva continúan pendientes.

## Identidad del candidato

- Commit base validado: `6d8bdffa873102175dca09ff1b26400ec86a26e1` (`6d8bdff`).
- Commit definitivo del candidato: pendiente; será el commit que incorpore esta documentación sobre el commit base.
- Ramas al iniciar MVP-RC-1: `develop`, `main`, `origin/develop` y `origin/main` alineadas en `6d8bdff`.
- Tags existentes al iniciar el bloque: ninguno.
- Fecha de validación técnica: 16 de julio de 2026.
- Fecha de publicación: pendiente.

Antes de crear el tag debe anotarse aquí o en las notas de publicación el hash definitivo de `main` y comprobarse que contiene exclusivamente el commit aprobado del RC.

## Propuesta de versión

El repositorio no dispone de tags ni de una política previa de releases. `frontend/package.json` conserva la versión privada genérica `0.0.0` y Composer no declara una versión del producto.

Se recomienda SemVer y el tag `v0.1.0-rc.1` porque:

- representa una versión previa a `1.0.0`;
- identifica explícitamente un release candidate;
- no declara estabilidad pública definitiva;
- permite promover posteriormente el MVP estable como `v0.1.0`, sin saltar automáticamente a `v1.0.0`;
- no exige modificar versiones internas mientras Git sea la fuente de versionado del producto.

## Inventario verificado

### Plataforma

| Componente | Versión o estado |
|---|---|
| Docker Engine | cliente y servidor `29.6.1` |
| Docker Compose | `v5.3.0` |
| PHP | `8.2.30` en el contenedor activo; `8.2.32` en una reconstrucción limpia |
| Laravel | `12.63.0` |
| Composer | `2.9.5` en el contenedor activo |
| MariaDB | `11.4.10` en el contenedor activo; imagen declarada `mariadb:11.4` |
| Nginx | imagen `nginx:1.27-alpine`; reconstrucción validada con `1.27.5` |
| Node.js | `22.23.0` |
| npm | `10.9.8` |

Las imágenes `php:8.2-fpm-alpine`, `composer:2`, `nginx:1.27-alpine` y `mariadb:11.4` fijan familias de versión, no un digest inmutable. Por ello una reconstrucción conserva compatibilidad de plataforma, pero puede recibir revisiones de parche distintas.

### Backend

- 56 rutas propias bajo `/api/v1`.
- 77 rutas web propias, principalmente el panel `/admin`.
- 27 migraciones MariaDB.
- Seeders explícitos:
  - `DefaultVenueSeeder`, idempotente y no destructivo;
  - `InstitutionalCmsPageSeeder`, idempotente y no destructivo;
  - `E2ESmokeSeeder`, protegido por `APP_ENV=e2e` y base `galotxas_e2e`.
- `DatabaseSeeder` contiene datos de demostración y una cuenta administrativa predecible; no debe ejecutarse en producción.
- Servicios Docker habituales: `app`, `web` y `db`.
- Perfil Docker `test`: añade `test` y `test-db` con MariaDB temporal.
- Stack E2E: `app`, `web`, `db` y `runner`; el runner pertenece al perfil `runner`.

### Frontend

| Paquete | Versión instalada |
|---|---|
| React / React DOM | `19.2.4` |
| React Router DOM | `7.18.1` |
| Axios | `1.18.1` |
| Vite | `8.1.4` |
| Vitest | `4.1.10` |
| Playwright | `1.61.1` |

Scripts npm: `dev`, `build`, `lint`, `preview`, `test`, `test:run`, `test:watch`, `e2e` y `e2e:install`.

Build verificado:

- 152 módulos transformados;
- `dist` total: aproximadamente 1,70 MB;
- JavaScript principal: 390,43 kB, 117,54 kB gzip;
- CSS principal: 52,05 kB, 10,37 kB gzip.

## Alcance incluido

- Registro, login, logout y recuperación/restablecimiento de contraseña.
- Usuarios y administradores activos.
- Creación de perfil de jugador y edición parcial por API.
- Temporadas, campeonatos y categorías.
- Solicitudes de inscripción, revisión, pago manual y asignación administrativa.
- Participantes competitivos y equipos de individuales y dobles.
- Gestión de pistas y seeders explícitos.
- Generación de liga, copa, final y tercer puesto.
- Consulta pública de campeonatos, categorías, calendarios y partidos.
- Envío y confirmación de resultados.
- Detección de discrepancias y resolución administrativa con trazabilidad.
- Rankings de categoría, campeonato, temporada e histórico.
- Mi Panel con perfil, inscripciones, partidos, calendario y rankings.
- Acciones pendientes `submit_result`, `confirm_result` y aviso `under_review`.
- CMS público de bloques estructurados e índice de contenidos.
- Administración Blade de páginas y bloques CMS.
- Configuración del cliente API mediante `VITE_API_BASE_URL`.
- Tests backend, frontend y E2E aislados.
- Auditoría de dependencias npm y Composer.

## Funcionalidades excluidas

- Interfaz React de reprogramación.
- Edición completa del perfil existente desde React.
- CMS avanzado, noticias, subida de archivos y formularios públicos.
- OpenAPI y normalización global del contrato API.
- Cookies `HttpOnly`/`SameSite` y nueva estrategia CSRF para React.
- Notificaciones y entrega real de correo.
- Soporte multideporte.
- Matriz E2E con navegadores adicionales.
- Despliegue real de producción.

## Limitaciones y deudas aceptadas

### Deuda de seguridad futura

- El token Bearer se almacena en `localStorage`.
- Cualquier 403 autenticado provoca el cierre global vigente.
- Los endpoints de reprogramación no tienen limiter específico.
- `DatabaseSeeder` crea datos de demostración con una credencial predecible y debe quedar fuera de producción; conviene protegerlo por entorno antes del MVP estable.
- La configuración CORS efectiva permite cualquier origen para API con `supports_credentials=false`; un despliegue entre dominios debe restringir orígenes explícitamente.

### Deuda técnica

- `MatchResource` sigue siendo amplio en “mis partidos”, calendario, reprogramación y administración.
- Los contratos API y envelopes heredados no son homogéneos.
- La coordinación de pistas se garantiza dentro de la categoría generada, no entre categorías.
- No existe bloqueo global de generaciones concurrentes.
- `Dashboard.jsx` y `Api\V1\MatchController` mantienen responsabilidades amplias.
- Las imágenes Docker no están fijadas por digest, por lo que las revisiones de parche pueden variar entre reconstrucciones.
- Node 22 está requerido por el proyecto, pero todavía no existe `.nvmrc`, `engines` ni otro archivo de pinning dentro del repositorio.

### Mejora funcional

- La reprogramación está disponible solo en backend.
- La edición de perfil desde React es limitada.
- Un participante no puede rectificar un reporte ya enviado.
- La resolución administrativa no persiste un motivo específico.

### Limitación de pruebas y operación

- Chromium es el único navegador E2E.
- La entrega real de emails no está validada; desarrollo usa el mailer `log`.
- TLS, proxy inverso, fallback SPA productivo y CORS de un dominio real no están validados.
- No existe todavía una validación en un segundo entorno ni con datos funcionales no E2E.

### Limitación documental

- REG-007 mantiene pendiente decidir si delantero y trasero pueden intercambiar permanentemente sus funciones. La contradicción o ambigüedad debe resolverse antes de promover el MVP estable.
- `knowledge/conceptos` contiene rutas paralelas `Personas/` y `personas/`, con fichas duplicadas o incompletas; no afecta al runtime Linux, pero dificulta la portabilidad a sistemas de archivos no sensibles a mayúsculas.
- `docs/02-architecture.md` conserva una mención histórica a un placeholder duplicado de `/torneos` que ya fue eliminado del código.

Ninguna de estas limitaciones se clasifica como P0 o P1 para etiquetar el RC funcional. Sí requieren aceptación explícita y varias bloquean un despliegue productivo o la promoción a estable.

## Instalación reproducible validada

### Backend local con Docker

Desde la raíz:

```bash
cp backend/.env.example backend/.env
docker compose -f backend/docker/docker-compose.yml run --rm --no-deps --user "$(id -u):$(id -g)" app composer install --no-interaction --prefer-dist
docker compose -f backend/docker/docker-compose.yml up -d --build
docker compose -f backend/docker/docker-compose.yml exec app php artisan key:generate --force
docker compose -f backend/docker/docker-compose.yml exec app php artisan migrate --force
```

`key:generate --force` solo corresponde a una instalación nueva. No debe sustituirse la clave de un entorno existente.

Opcionalmente, para datos base no destructivos de desarrollo:

```bash
docker compose -f backend/docker/docker-compose.yml exec app php artisan db:seed --class=DefaultVenueSeeder
docker compose -f backend/docker/docker-compose.yml exec app php artisan db:seed --class=InstitutionalCmsPageSeeder
```

No ejecutar `DatabaseSeeder` en producción. `storage` y `bootstrap/cache` deben ser escribibles por el usuario del proceso PHP. `storage:link` solo será necesario cuando se utilice el disco público; el MVP actual no sube archivos.

### Frontend

```bash
cd frontend
cp .env.example .env
npm ci
npm run dev
```

El servidor temporal validado respondió HTTP 200. Para un build productivo bajo el mismo dominio:

```bash
VITE_API_BASE_URL=/api/v1 npm run build
```

Si frontend y backend usan dominios distintos, la variable debe contener la URL HTTPS completa y el backend debe restringir CORS al origen real.

### Evidencia de instalación limpia

- `npm ci`: 269 paquetes instalados en una copia temporal desde `package-lock.json`.
- `composer install`: 112 paquetes instalados en una copia temporal desde `composer.lock`.
- Las 27 migraciones se ejecutaron sobre MariaDB efímera.
- Los tres seeders explícitos se ejecutaron correctamente.
- Nginx/Laravel respondió HTTP 200.
- Las copias temporales y sus recursos Docker se retiraron al finalizar.

## Comandos de validación

```bash
docker compose -f backend/docker/docker-compose.yml --profile test run --rm test
docker compose -f backend/docker/docker-compose.yml exec app composer validate --strict
docker compose -f backend/docker/docker-compose.yml exec app composer audit

cd frontend
npm ls --depth=0
npm run lint
npm run test:run
npm run build
npm run e2e
npm audit
npm audit --omit=dev
```

## Resultado de validación

| Comprobación | Resultado |
|---|---|
| Laravel | 168 tests, 1.088 aserciones, 5,95 s; 0 fallos, skips o warnings relevantes |
| Vitest/RTL | 18 archivos, 65 tests, 3,53 s; 0 fallos |
| ESLint | correcto |
| Vite | 152 módulos, 1,07 s; build correcto |
| Playwright | 9 escenarios, 21,3 s; 0 fallos, skips o reintentos |
| npm audit | 0 vulnerabilidades |
| npm audit --omit=dev | 0 vulnerabilidades |
| Composer validate | `composer.json` válido en modo estricto |
| Composer audit | 0 advisories |

El smoke no registró fallos de consola ni de red que invaliden los recorridos. La notificación de una nueva versión principal de npm es informativa y no autoriza una actualización en este bloque.

## Cachés Laravel

En el entorno temporal se ejecutaron correctamente:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
```

No quedaron cachés persistentes en el repositorio.

## Revisión de secretos y artefactos

- No hay `.env` reales, claves privadas, tokens, PEM, dumps, `node_modules`, `vendor`, `dist` ni reportes Playwright rastreados.
- Los únicos `.env*` rastreados son `backend/.env.example` y `frontend/.env.example`.
- Las credenciales de E2E pertenecen al stack efímero protegido.
- Las credenciales MariaDB del Compose habitual son locales y no son aptas para producción.
- `DatabaseSeeder` contiene una cuenta de demostración predecible; no es un secreto real, pero no debe ejecutarse fuera de desarrollo.
- Existe un log Laravel local ignorado; no forma parte del candidato Git y debe gestionarse mediante rotación o eliminación operativa antes de empaquetar un entorno.

## Preparación de producción

### Disponible

- Variables Laravel para entorno, clave, URL, MariaDB, logs, cache, cola, sesión y mail.
- `APP_ENV=production`, `APP_DEBUG=false` y `APP_KEY` son compatibles con la configuración actual y quedan exigidos por este runbook.
- Build Vite con `VITE_API_BASE_URL` o fallback relativo `/api/v1`.
- Cachés de configuración, rutas y vistas validadas.
- Nginx/PHP-FPM y migraciones MariaDB reproducibles.
- No hay tareas programadas de aplicación ni jobs encolados detectados en el MVP actual.

### Pendiente antes de producción

- Configurar HTTPS y un proxy inverso real.
- Servir `frontend/dist` con fallback de rutas SPA a `index.html` y enrutar `/api/v1` y `/admin` hacia Laravel.
- Sustituir todas las credenciales locales por secretos administrados fuera de Git.
- Configurar `APP_URL`, `FRONTEND_URL`, cookies seguras y dominio de sesión.
- Restringir CORS si existe separación de dominios.
- Definir almacenamiento persistente, propiedad y permisos de `storage` y `bootstrap/cache`.
- Configurar y probar SMTP u otro proveedor de correo.
- Implantar backups de MariaDB y probar una restauración.
- Configurar observabilidad, alertas, rotación de logs y health checks externos.
- Definir un proceso de despliegue y rollback; añadir worker o scheduler únicamente si futuras funciones los necesitan.

## Checklist de publicación

- [ ] Revisión humana de `CHANGELOG.md`, este documento y las limitaciones aceptadas.
- [ ] Confirmar que no hay P0/P1 abiertos.
- [ ] Crear el commit único de MVP-RC-1 en `develop`.
- [ ] Repetir `git status`, `git diff --check` y la regresión que corresponda al commit final.
- [ ] Push de `develop`.
- [ ] Fast-forward de `main` desde `develop` y push de `main`.
- [ ] Registrar el hash definitivo de `main`.
- [ ] Crear el tag anotado `v0.1.0-rc.1` desde ese hash.
- [ ] Push del tag.
- [ ] Crear una GitHub Release marcada como prerelease.
- [ ] Ejecutar una validación del tag en un segundo entorno.
- [ ] Abrir el periodo de prueba definido por el usuario.

## Comandos Git propuestos — no ejecutados

Los comandos siguientes son una propuesta sujeta a revisión. Deben ejecutarse únicamente cuando el árbol contenga exactamente los archivos aprobados:

```bash
git add README.md CHANGELOG.md docs/README.md docs/06-roadmap.md docs/09-release-candidate.md
git commit -m "docs: prepara el candidato MVP 0.1.0 RC1"
git push origin develop

git checkout main
git pull --ff-only origin main
git merge --ff-only develop
git push origin main

git tag -a v0.1.0-rc.1 -m "Galotxas MVP 0.1.0 RC1"
git push origin v0.1.0-rc.1
```

Antes del tag: `git status --short` debe estar vacío, `main` debe apuntar al commit aprobado y la regresión final debe permanecer verde.

## Borrador de GitHub Release

**Título:** `Galotxas MVP 0.1.0 RC1`

**Tipo:** prerelease.

```markdown
Primer candidato del MVP funcional de Galotxas.

Incluye autenticación, perfiles de jugador, gestión de competiciones, inscripciones, pistas, calendarios, resultados con confirmación y conflictos, rankings, Mi Panel, CMS público y administración Blade.

Validación del candidato:
- 168 tests Laravel / 1.088 aserciones sobre MariaDB aislada;
- 65 tests Vitest/RTL;
- 9 escenarios Playwright Chromium;
- ESLint y build Vite correctos;
- 0 vulnerabilidades npm y 0 advisories Composer.

Este RC no es un despliegue de producción. TLS, proxy inverso, backups, monitorización y correo real siguen pendientes. Chromium es el único navegador E2E y se mantienen las limitaciones de autenticación, reprogramación y contratos heredados documentadas en docs/09-release-candidate.md.

Para probarlo, seguir la instalación del README y ejecutar los comandos de validación del documento del RC. Las incidencias deben incluir versión/tag, entorno, pasos reproducibles, resultado esperado/obtenido, logs sin secretos y severidad propuesta.
```

## Checklist de rollback

### Antes de desplegar

- Detener la publicación si el tag no apunta al hash aprobado o aparece un P0/P1.
- No reutilizar el mismo tag para otro commit.
- Si una prerelease publicada debe retirarse, marcarla como retirada y eliminar el tag solo tras aprobación explícita del responsable.

### Si el RC se prueba en un entorno desplegado

- Conservar el commit o tag anterior conocido como bueno.
- Hacer backup de MariaDB antes de cualquier cambio.
- Redeplegar el artefacto anterior y ejecutar `php artisan optimize:clear`.
- Restaurar base de datos únicamente si hubo cambios de datos incompatibles; este RC no añade migraciones respecto al commit base.
- Verificar `/up`, login, consulta pública y acceso administrativo después del rollback.
- Documentar causa, alcance y evidencia de la retirada.

## Criterios de promoción a `v0.1.0`

- Periodo de prueba con fecha de inicio, fecha de fin y responsable definidos por el usuario, completado sin P0 ni P1.
- Regresión completa verde sobre el commit/tag que se pretenda promover.
- Instalación y validación correctas en un segundo entorno.
- Prueba funcional con datos controlados que no procedan de `E2ESmokeSeeder`.
- Revisión funcional y aceptación por el usuario o responsables del producto.
- Revisión del reglamento y resolución explícita de REG-007.
- Reconciliación de las rutas duplicadas por mayúsculas/minúsculas dentro de `knowledge/conceptos`.
- Eliminación o protección por entorno de la cuenta predecible de `DatabaseSeeder`.
- Decisión documentada sobre despliegue y dominio objetivo.
- HTTPS, proxy, secretos, correo, backups y monitorización preparados si la promoción implica producción.
- Copia de seguridad verificada antes del primer despliegue productivo.
- Ausencia de vulnerabilidades conocidas que superen el umbral aceptado por el responsable.

Cumplir estos criterios permite considerar `v0.1.0`; no implica ni recomienda automáticamente `v1.0.0`.
