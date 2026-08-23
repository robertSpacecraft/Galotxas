# Testing — Galotxas

## Propósito

Este documento describe la estrategia de pruebas del proyecto Galotxas.

Su objetivo es garantizar que la evolución del sistema no comprometa el comportamiento funcional, la seguridad ni el contrato de la API.

---

# 1. Principios

- Las pruebas forman parte del desarrollo.
- Todo cambio relevante debe valorar la incorporación de tests.
- Las pruebas deben ser repetibles y aisladas.
- Nunca deben ejecutarse pruebas destructivas sobre la base de desarrollo.

---

# 2. Entornos

## Desarrollo

Utiliza la base MariaDB principal del proyecto.

No debe emplearse para ejecutar `RefreshDatabase` ni `migrate:fresh`.

## Pruebas

Las pruebas de integración utilizan una instancia MariaDB aislada mediante Docker.

Esta base es temporal y puede destruirse sin afectar al entorno de desarrollo.

---

# 3. Tecnologías

Backend:
- PHPUnit
- Laravel Testing
- RefreshDatabase cuando proceda
- Docker

Frontend:
- Vitest
- React Testing Library
- `@testing-library/jest-dom`
- `@testing-library/user-event`
- jsdom
- Playwright con Chromium para el smoke E2E

---

# 4. Tipos de pruebas

## Unitarias

Verifican lógica aislada.

Ejemplos:
- algoritmos;
- utilidades;
- Services con dependencias simuladas.

## Integración

Comprueban la interacción entre:
- rutas;
- middleware;
- controllers;
- Services;
- modelos;
- base de datos.

## Funcionales

Validan flujos completos desde el punto de vista del usuario.

Incluyen tanto pruebas automáticas como verificaciones manuales.

## Fuente y páginas legales

`VERSIONED-LEGAL-PAGES-1` cubre el pipeline independiente de `legal/`, su
artefacto y el consumidor React. Las pruebas rechazan documentos fuera de la
allowlist, metadatos incompletos, slug duplicado, estado no válido, cuerpo o H1
ausentes, marcadores internos, datos con forma telefónica y salida no
sincronizada. También comprueban la exclusión de README, borradores y
Knowledge.

Vitest/RTL cubre repositorio fail-closed, tres rutas exactas, lazy loading,
metadatos, versión, fecha, navegación, footer, enlaces seguros, tablas y 404.
Playwright recorre los tres textos, Contacto desactivado, ausencia de banner y
recursos remotos y viewport de 320 px. `npm run build` ejecuta
`legal:check` antes de Vite y la validación construye en un directorio temporal
para no escribir `frontend/dist`.

Validación de 7D.2C1, 2026-08-06: 418 tests Vitest en 63 archivos, ESLint sin
salida, proyección legal de tres documentos sincronizada, build temporal de
214 módulos sin warnings y 47/47 escenarios Playwright. Knowledge conserva 40
documentos y cuatro colecciones, sus dos hashes no cambian y `EST-REF-001`
mantiene su hash histórico.

---

# 5. Prioridades

Alta prioridad:
- autenticación;
- autorización;
- usuarios activos;
- inscripción;
- área privada “Mi Panel”;
- resultados;
- rankings;
- Resources;
- contrato API.

Prioridad media:
- panel administrativo;
- filtros;
- búsquedas.

Prioridad baja:
- vistas simples sin lógica.

---

# 6. Flujo recomendado

1. Implementar.
2. Ejecutar tests afectados.
3. Ejecutar la suite completa cuando el cambio sea relevante.
4. Revisar `git diff --check`.
5. Actualizar documentación si cambia el comportamiento.

---

# 7. Comandos habituales

Suite backend desde la raíz:

`backend/scripts/run-tests.sh`

Suite backend dirigida:

`backend/scripts/run-tests.sh --filter=School`

Comprobación de sintaxis:

`php -l`

Comprobación de espacios y conflictos:

`git diff --check`

Auditoría frontend completa y de producción:

`cd frontend && npm audit`

`cd frontend && npm audit --omit=dev`

Validación y auditoría backend dentro del contenedor oficial:

`docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app composer validate --strict`

`docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app composer audit`

`docker compose --project-name galotxas -f backend/docker/docker-compose.yml exec app composer audit --no-dev`

Suite frontend no interactiva:

`cd frontend && npm run test:run`

Modo watch frontend:

`cd frontend && npm run test:watch`

Smoke E2E completo:

`cd frontend && npm run e2e`

Descarga inicial del runtime oficial de Playwright con Chromium:

`cd frontend && npm run e2e:install`

Chromium se ejecuta dentro de la imagen oficial de Playwright fijada a la misma versión que `@playwright/test`. Así el host WSL no necesita una instalación global del navegador ni librerías Linux adicionales.

La configuración de los scripts fija `/tmp` para Vitest porque WSL puede heredar `TMP` y `TEMP` de Windows; así los workers no dependen de directorios temporales externos al entorno Linux.

Los tests unitarios y de componentes frontend se ejecutan en jsdom y verifican funciones, renderizado e interacción aislada. No arrancan un navegador real ni recorren conjuntamente frontend, API y MariaDB. Esos recorridos los cubre la suite separada E2E-1.

## Entorno Playwright E2E

`npm run e2e` orquesta un entorno desechable e independiente:

- usa el proyecto Compose `galotxas-e2e` y el archivo `backend/docker/docker-compose.e2e.yml`;
- publica el backend en `127.0.0.1:8081` y levanta Vite dentro del runner en el puerto `5174` por defecto;
- crea exclusivamente la base MariaDB `galotxas_e2e` sobre `tmpfs`;
- ejecuta migraciones y el seeder explícito `E2ESmokeSeeder`;
- lanza una suite serial Chromium desde el runner oficial de Playwright contra frontend, API y MariaDB reales;
- desmonta contenedores, red y volúmenes al terminar, también cuando falla la suite.

Antes de levantar y antes de desmontar, el runner valida con `docker compose config` el nombre exacto del proyecto y archivo, `APP_ENV=e2e`, la base E2E, el almacenamiento `tmpfs` y la ausencia de referencias o nombres de desarrollo. La limpieza sólo se ejecuta a través de `safe-compose-down.sh`, que vuelve a comprobar esas condiciones y exige proyecto y archivo explícitos. Los tests backend aplican la misma defensa mediante el proyecto separado `galotxas-test` y `docker-compose.test.yml`.

Los puertos pueden cambiarse con `E2E_BACKEND_PORT` y `E2E_FRONTEND_PORT`. Playwright también admite `E2E_BASE_URL`, `E2E_BACKEND_URL` y `E2E_SKIP_WEBSERVER` para ejecuciones controladas contra un stack E2E ya levantado. Ninguna de estas variables debe apuntar a la base o al backend habitual de desarrollo.

El seeder aborta salvo que coincidan simultáneamente `APP_ENV=e2e` y `DB_DATABASE=galotxas_e2e`. No está registrado en `DatabaseSeeder`. Sus credenciales, válidas solo mientras vive el stack temporal, son:

- `admin.e2e@example.test`;
- `player1.e2e@example.test`;
- `player2.e2e@example.test`;
- contraseña común: `E2E-password-123!`.

La suite es serial de forma deliberada: representa un único smoke narrativo sobre dos partidos sembrados, primero valida un resultado coincidente, después provoca una discrepancia y finalmente la resuelve desde el panel Blade. El estado inicial siempre procede de una base temporal nueva y se destruye al finalizar; nunca depende de IDs manuales ni de datos de desarrollo.

E2E-1 cubre navegación pública y móvil, CTA principal, calendario público con jornadas y partidos, CMS, login real, bloques y acciones de Mi Panel, confirmación coincidente, discrepancia no editable, acceso real del administrador al panel Blade, revisión y resolución del conflicto, resultado oficial actualizado y ranking histórico. Dobles queda fuera del smoke porque dispone de cobertura Feature específica.

## DEPS-1 — Auditoría y actualización controlada

La auditoría inicial de npm detectó 11 vulnerabilidades: 6 altas, 4 moderadas y 1 baja. Cinco pertenecían al árbol de producción —4 altas y 1 moderada— y afectaban a Axios, React Router y dependencias transitivas. Composer detectó 20 advisories en 11 paquetes: 17 correspondían al árbol de producción y los 3 restantes a `symfony/yaml` en desarrollo. Los paquetes afectados eran Laravel Framework, Guzzle, PSR-7, CommonMark y componentes HTTP, routing, mail, MIME, IDN y YAML de Symfony.

Las actualizaciones directas compatibles fueron:

- Axios `1.13.6` → `1.18.1`;
- React Router DOM `7.13.1` → `7.18.1`;
- Vite `8.0.1` → `8.1.4`;
- Laravel Framework `12.54.1` → `12.63.0`.

Los gestores actualizaron también las dependencias transitivas vulnerables dentro de sus rangos compatibles: Babel, `brace-expansion`, `follow-redirects`, `form-data`, `js-yaml`, Picomatch, PostCSS, Guzzle, PSR-7, CommonMark, componentes Symfony 7.4 y polyfills. No se incorporaron nuevas dependencias funcionales ni se cambiaron versiones principales de la plataforma.

El resultado final es 0 vulnerabilidades tanto en `npm audit` como en `npm audit --omit=dev`, y 0 advisories en `composer audit`. La regresión posterior comprende ESLint, 35 tests Vitest, build Vite, 6 smokes Playwright y 167 tests Laravel con 1.082 aserciones sobre MariaDB aislada.

## Instantánea de validación tras QA-FIX-1

Las cifras siguientes son una instantánea verificada el 13 de julio de 2026, no un número fijo que deban conservar futuras ampliaciones de la suite:

- backend: 167 tests y 1.082 aserciones sobre MariaDB aislada;
- frontend: 9 archivos y 45 tests Vitest;
- E2E: 9 escenarios Playwright Chromium en `frontend/e2e/mvp-smoke.spec.js`;
- calidad frontend: ESLint y build Vite correctos;
- auditorías: 0 vulnerabilidades npm completas y de producción, y 0 advisories Composer.

QA-FIX-1 añadió 10 regresiones Vitest/RTL: cinco para carga, error, vacío, colección real y fallbacks del calendario; cuatro para semántica, apertura/cierre y sesión del navbar; y una para el enlace interactivo del Hero. El smoke existente creció con tres escenarios para CTA, calendario real y navegación móvil a 390 × 844. La revalidación dirigida y la regresión completa repitieron frontend, E2E, backend y auditorías antes de habilitar el paso a MVP-RC-1.

## Instantánea de validación tras RC-HARDEN-1

La instantánea verificada el 14 de julio de 2026 amplía la anterior sin modificar los contratos de API ni las reglas deportivas:

- backend: 168 tests y 1.088 aserciones sobre MariaDB aislada;
- frontend: 18 archivos y 65 tests Vitest;
- E2E: los mismos 9 escenarios Playwright Chromium, ampliados con comprobaciones de formularios accesibles, ruta real de torneos y navegación Blade móvil;
- calidad frontend: ESLint y build Vite correctos;
- artefactos E2E: una ejecución correcta elimina `test-results`, `playwright-report` y `blob-report`; una ejecución fallida o interrumpida los conserva para diagnóstico.

RC-HARDEN-1 añade regresiones para el formateo seguro de fechas ausentes o inválidas, asociaciones de etiquetas y control de teclado en registro y recuperación, jerarquía de encabezados, invalidación de sesión sin ruido duplicado, ruta única de torneos y control móvil accesible del panel. También impide volver a versionar metadatos `Zone.Identifier` sin eliminar el SVG real.

---

# 8. Pruebas manuales

Antes de cerrar funcionalidades importantes se recomienda ejecutar flujos completos de usuario y administración para validar la experiencia de uso.

Las pruebas manuales complementan, pero no sustituyen, las pruebas automáticas.

## Validación manual de auth/token frontend

Para cambios en la autenticación de React deben validarse al menos estos flujos:

- login correcto y almacenamiento de `token` y `user`;
- acceso a `/player` con sesión válida;
- logout correcto, incluyendo llamada a backend cuando existe token;
- acceso a `/player` tras logout, que debe redirigir a login;
- comportamiento con token ausente o inválido, limpiando `token` y `user`;
- consola del navegador sin errores relevantes.

Estas comprobaciones deben acompañarse de `npm run lint`, `npm run build` y `git diff --check` cuando el cambio afecte al frontend.

## Validación de la URL API del frontend

Cuando cambie la configuración del cliente HTTP deben comprobarse al menos estos escenarios:

- desarrollo sin `VITE_API_BASE_URL`: usa `http://localhost:8080/api/v1`;
- build de producción sin variable: usa `/api/v1` y no incorpora localhost como base efectiva;
- build con `VITE_API_BASE_URL` explícita: incorpora el valor configurado tras eliminar espacios exteriores;
- los interceptores de autenticación y la instancia Axios única se mantienen activos;
- `npm run lint`, `npm run build` y `git diff --check` finalizan correctamente.

## Validación manual de CMS público React

Para cambios en el renderizado público de páginas CMS deben validarse al menos estos flujos:

- carga correcta de `/contenidos` con el índice de páginas publicadas;
- carga correcta de `/contenidos/{slug}` para una página publicada;
- navegación desde el navbar hacia `/contenidos/prensa-media`, `/contenidos/nosotros`, `/contenidos/federaciones`, `/contenidos/academy` y `/contenidos`;
- exclusión en el índice de páginas borrador y páginas con `published_at` futuro;
- renderizado de bloques `heading`, `text`, `list`, `button` y `document_link` cuando existan;
- estado de carga;
- estado vacío del índice cuando no existan páginas publicadas;
- estado de página inexistente o no publicada;
- consola del navegador sin errores relevantes.

Estas comprobaciones deben acompañarse de `npm run lint`, `npm run build` y `git diff --check`.

## Validación manual de resultados de partidos React

Para cambios en `/matches/{id}` y el workflow de resultados deben validarse al menos estos flujos:

- carga del detalle público de partido sin sesión;
- acceso autenticado al workflow del participante;
- envío de resultado con mensaje de éxito o error backend visible;
- confirmación de resultado rival cuando exista `opposite_report`;
- visualización clara de partido validado;
- visualización clara de partido `under_review`;
- navegación desde Mi Panel o calendario hacia `/matches/{id}`;
- consola del navegador sin errores relevantes.

Estas comprobaciones deben acompañarse de `npm run lint`, `npm run build` y `git diff --check`.

---

# 9. Cobertura Feature actual

El backend dispone de cobertura automatizada Feature para componentes críticos del dominio:

## Panel Administrativo
- acceso seguro y protección de sesiones activas;
- contadores de solicitudes pendientes y aprobadas sin categoría en el dashboard;
- enlace desde el dashboard a la sección específica de solicitudes e inscripciones;
- ausencia de formularios operativos de aprobación o asignación en el dashboard;
- acceso administrativo y protección frente a usuarios no administradores en la nueva sección;
- visualización de solicitudes pendientes y aprobadas sin categoría en sus bloques correspondientes;
- exclusión de solicitudes rechazadas y aprobadas ya asignadas;
- aprobación y rechazo desde la sección reutilizando las rutas existentes;
- formulario de asignación rápida con las categorías del campeonato correspondiente;
- preselección de la categoría sugerida cuando pertenece al campeonato;
- asignación desde la sección reutilizando `CategoryRegistrationController@store`;
- desaparición de la solicitud del bloque pendiente tras crear `CategoryRegistration`;
- rechazo de asignaciones a categorías de otro campeonato;
- ausencia del formulario cuando el campeonato no tiene categorías disponibles;

## Competición
- regresión de finales de copa: semifinales validadas con GameMatch.status casteado a enum generan final y tercer puesto;
- protección frente a generación de finales con semifinales no validadas;
- regeneración de finales de copa sin duplicados;
- cobertura de generación normal de semifinales de copa.

### CUP-FLOW-1 — Flujo completo de Copa

La caracterización previa a cualquier corrección demostró que los tres caminos
de resultados de Copa ya compartían persistencia con Liga:

- validación directa desde el formulario de Categoría;
- reportes coincidentes entre participantes;
- reportes discrepantes y resolución administrativa.

La única incoherencia reproducida era el descarte silencioso de tanteos enviados
con `status=scheduled`; la regresión exige ahora error de validación para Copa y
Liga. La cobertura del bloque incluye además:

- ausencia o número distinto de dos semifinales, estados no validados y empate;
- fases `cup` y stages `semifinal`, `final` y `third_place`;
- top 4 y cruces 1.º-4.º / 2.º-3.º;
- emparejamientos de ganadores y perdedores, fechas inicialmente ausentes,
  programación manual posterior e idempotencia;
- API ordenada, sin N+1 mediante relaciones precargadas, tanteos oficiales,
  `winner_entry` allowlisted y ausencia de trazabilidad privada;
- contrato frontend cerrado ante stages desconocidos, estados pendientes,
  cuadro semántico, ganador oficial, accesibilidad y 320 px;
- E2E aislado Liga → semifinal coincidente → semifinal conflictiva → resolución
  Blade → Final/3.º-4.º → campeón, con cleanup.

La regresión de rankings conserva por separado categoría limitada a Liga y
agregados de campeonato, temporada e histórico con todas las rondas validadas,
incluida Copa.

Validación local final de CUP-FLOW-1, 2026-08-22:

- backend dirigido de workflow, generación, API y rankings: 26 tests y 211
  aserciones;
- backend completo sobre MariaDB aislada: 554 tests y 4.259 aserciones;
- frontend dirigido de contrato, cuadro y composición: 18 tests en 3 archivos;
- frontend completo: 644 tests en 91 archivos;
- E2E dirigido de Copa, smoke MVP y patrocinadores: 21 escenarios Chromium;
- E2E completo: 68 escenarios Chromium en 3,1 minutos;
- Composer estricto, Pint dirigido, `php -l`, ESLint, Legal, Knowledge, SEO,
  build Vite e imagen Docker de producción: correctos.

El primer E2E completo detectó que el fixture de Copa reutilizaba dos jugadores
del smoke narrativo y alteraba su contador de acciones pendientes. Las cuatro
identidades quedaron aisladas en el seeder E2E; la repetición dirigida y la
suite completa posterior finalizaron sin fallos. Los recursos temporales se
eliminaron al terminar.

### CUP-PUBLIC-VIEW-1 — Vista dedicada y alcance de rankings

El refinamiento posterior separa la presentación sin cambiar el endpoint de
schedule ni las reglas deportivas:

- navegación de categoría en orden Resumen, Clasificación, Calendario y
  resultados, Copa, con un único `aria-current="page"`;
- helper, ruta diferida y montaje de `/categories/{id}/cup`;
- loading, error/retry, vacío, semifinales parciales o completas,
  Final/3.º-4.º y campeón oficial en `CupPage`;
- selección exacta por `type`, `phase` y `stage`, sin inferencias por nombre u
  orden para datos legados ambiguos;
- regresión de Schedule limitada a rondas `type=league`, conservando contexto,
  `MatchCard` y estados remotos;
- categoría y Mi Panel congelados sobre Liga; campeonato, temporada e
  histórico incluyen Copa validada con los puntos y multiplicador comunes,
  sin bonus por fase o título;
- E2E Liga → Copa por teclado, navegación activa, cuadro completo, enlace al
  detalle de partido y reflow a 320 px con cleanup.

Validación local final de CUP-PUBLIC-VIEW-1, 2026-08-23:

- ranking backend dirigido: 15 tests y 178 aserciones;
- backend completo sobre MariaDB aislada: 557 tests y 4.314 aserciones;
- frontend dirigido de ruta, navegación, CupPage, Schedule, cuadro y SEO: 128
  tests en 9 archivos;
- frontend completo: 659 tests en 93 archivos;
- E2E dirigido de Copa: 1 escenario Chromium;
- E2E completo: 68 escenarios Chromium en 3,1 minutos;
- Composer estricto, Pint dirigido, `php -l`, ESLint, Legal, Knowledge, SEO,
  build Vite y `git diff --check`: correctos.

La aceptación manual en staging permanece pendiente.

## Rankings deterministas

RANK-1 incorpora cobertura Feature para:

- cálculo básico de puntos, estadísticas y posiciones;
- enfrentamiento directo cuando exactamente dos entradas empatan a puntos;
- empate triple con ciclo A vence a B, B vence a C y C vence a A;
- omisión del enfrentamiento directo por parejas en grupos de tres o más;
- igualdad total resuelta de forma reproducible mediante `entry_id`;
- participantes aprobados sin partidos y estadísticas a cero;
- equipos de dobles identificados y ordenados correctamente;
- `win_rate` histórico en escala `0–100`, incluido el caso 1/2 = `50`;
- coherencia entre categoría, campeonato y temporada;
- conservación del contrato privado de Mi Panel.

RANK-POINTS-FIX-1 amplía esa cobertura con:

- resolvedor unitario único para los casos individuales `10-7`, `10-8`, `10-9`, los equivalentes de dobles `12-7`, `12-8`, `12-11` y todos sus resultados simétricos como visitante;
- invariancia de tres puntos base totales y rechazo explícito de `8-8`;
- aplicación exacta de `3-0` y `2-1` en categoría, campeonato, temporada, histórico y Mi Panel;
- cálculo de `raw_points`, `weighted_points`, puntos ponderados por partido y posiciones sin alterar multiplicadores ni desempates;
- reparto de dobles `12-8` por roles: ganador `0,50/1,50`, perdedor `0,25/0,75`, con multiplicador de nivel aplicado después;
- conservación deliberada de categoría limitada a liga frente a agregados que incluyen todas las rondas validadas;
- resolución administrativa de un `10-8` con efecto `2-1` en la clasificación;
- cambio de orden comprobado antes de generar semifinales y siembra posterior `1.º-4.º` y `2.º-3.º`;
- ausencia de una segunda fórmula productiva y eliminación de `StandingsCalculatorService`, que no tenía consumidores.

La validación final de RANK-POINTS-FIX-1 completa 437 tests backend y 3.485 aserciones sobre MariaDB aislado. El E2E oficial completa 63 escenarios Chromium en 2,7 minutos, incluido el resultado validado y su reflejo posterior en rankings. Pint, `php -l` y `git diff --check` no encuentran incidencias.

`formatPercentage` dispone de cobertura unitaria para números, strings numéricos, límites `0–100` y valores ausentes, infinitos o fuera de rango.

## Workflow seguro de resultados
- mantenimiento del contrato público anónimo sin trazabilidad interna;
- respuesta limitada y sin datos privados para usuarios autenticados sin perfil de jugador;
- respuesta limitada y sin reportes para jugadores ajenos al partido;
- contrato específico de participante con reportes mínimos sin emails ni identificadores internos;
- envío de resultado permitido únicamente a participantes;
- confirmación de resultado permitida únicamente al participante rival;
- respuestas seguras de `submit-result` y `confirm-result`;
- comportamiento de dobles cuando la pareja ya ha enviado el reporte del lado.

## MATCH-2 — Flujo integral de resultados

La cobertura Feature del workflow incluye:

- primer envío desde cualquiera de los lados y transición `scheduled` → `submitted`;
- validación de tipos, valores negativos, empates, objetivo de 10 en individuales y 12 en dobles, y límite del comentario;
- rechazo de usuarios sin jugador, jugadores ajenos, mismo lado, compañero que ya reportó y reenvío del propio jugador;
- inmutabilidad del primer reporte y ausencia de sobrescritura;
- confirmación coincidente con validación de ambos reportes, tanteo oficial, ganador y responsables;
- segundo reporte discrepante con ambos reportes `conflict`, partido `under_review` y ausencia de tanteo oficial;
- bloqueo de nuevos reportes y confirmaciones en estados cerrados;
- resolución administrativa del conflicto, conservación íntegra de la trazabilidad y efecto posterior en rankings;
- protección de la resolución frente a usuarios no administradores;
- flujo de dobles con cualquiera de los miembros, representación única del lado y ganador de equipo;
- reversión transaccional del segundo reporte y de todos los cambios de estado si falla la resolución del resultado;
- regresión de los Resources seguros para anónimos, usuarios ajenos y participantes.

Las pruebas de integración se ejecutan exclusivamente sobre la instancia MariaDB aislada del proyecto `galotxas-test`.

## ADMIN-CONFLICT-1 — Resolución Blade de conflictos

`AdminMatchConflictResolutionTest` cubre además:

- listado vacío y listado limitado a partidos `under_review`;
- contexto competitivo, participantes, fecha, pista y ambos reportes en listado y detalle;
- ausencia de correos electrónicos en las vistas;
- autorización de administrador activo y rechazo de usuarios normales o administradores inactivos;
- resolución válida de individuales y dobles reutilizando las reglas deportivas del backend;
- conservación exacta de tanteos, comentarios, autores y estado `conflict` de los reportes;
- registro de `validated_by`, cálculo de ganador y efecto posterior en rankings;
- rechazo sin cambios de negativos, empates, objetivos inválidos, estados no revisables y segundos intentos;
- rollback completo ante un fallo de persistencia provocado después de actualizar el partido dentro de la transacción.

`AdminDashboardTest` verifica el contador y el acceso a la sección desde el dashboard. El smoke Playwright valida el recorrido real desde la discrepancia comunicada por los jugadores hasta su resolución administrativa y la publicación del resultado oficial.

## Área Privada "Mi Panel"
- datos del usuario autenticado;
- consulta, creación y actualización del perfil de jugador;
- solicitudes de inscripción propias;
- partidos propios;
- calendario;
- rankings;
- comportamiento de usuarios sin perfil de jugador;
- rechazo de acceso no autenticado.

## PANEL-1 — Acciones pendientes de partidos

La cobertura Feature verifica:

- autenticación obligatoria y colección vacía segura para usuarios sin perfil;
- colección vacía para jugadores sin partidos pendientes;
- `submit_result` para partidos `scheduled` sin reportes;
- `confirm_result` únicamente para el lado rival de un reporte existente;
- ausencia de segunda acción para el jugador o compañero cuyo lado ya reportó;
- exclusión de partidos `validated`, `cancelled` y `postponed`;
- inclusión de `under_review` exclusivamente como aviso informativo;
- representación compartida del lado en dobles, sin duplicados y con confirmación solo para la pareja rival;
- aislamiento frente a terceros ajenos al partido;
- ausencia de emails, reportes, comentarios, responsables e identificadores de trazabilidad en el contrato.

En frontend, `PendingMatchActions` dispone de pruebas de loading, error, empty y content, incluidos los tres tipos de acción y sus enlaces al workflow.

## FE-TEST-1 — Base de pruebas frontend

FE-TEST-1 incorpora seis suites colocadas junto al código cubierto:

- `formatPercentage`: contrato de formato y rechazo de valores inválidos;
- `PendingMatchActions`: estados remotos, contador, participantes, fecha, enlaces y aviso no editable `under_review`;
- `MatchWorkflow`: carga, error, permisos, estados cerrados, envío numérico, validación local, confirmación y comentario opcional;
- `CmsPage`: carga, error, 404, contenido válido y omisión de bloques desconocidos;
- `Schedule`: carga, error, vacío, colección de jornadas, partidos, enlaces y fallbacks;
- `Navbar`: enlaces públicos, estado accesible del menú, cierre y variantes de sesión;
- `Hero`: destino e interacción del CTA de torneos;
- `authSession`: lectura y limpieza de almacenamiento y evento de sesión invalidada;
- `resolveApiBaseUrl`: URL configurada y fallbacks de desarrollo y producción.

RC-HARDEN-1 amplía esta base con pruebas de `formatDate`, detalle de torneo sin fechas válidas, formularios de registro y recuperación, encabezados únicos en autenticación y partido, coordinación de `AuthContext` ante respuestas 401/500 y resolución de la ruta pública `/torneos`. El test Feature del dashboard verifica además el contrato accesible del control móvil de Blade.

La utilidad `renderWithProviders` ofrece únicamente `MemoryRouter`, rutas de prueba y `AuthContext` opcional. Los tests priorizan roles y nombres accesibles; las comprobaciones dirigidas de Navbar verifican además la clase visual activa y el estado abierto, sin snapshots masivos ni peticiones reales.

Limitaciones deliberadas:

- no existe cobertura global ni se fija un porcentaje objetivo;
- no se prueban estilos pixel a pixel;
- no se inicia Laravel ni MariaDB desde Vitest;
- Vitest no sustituye los tests Feature ni el smoke E2E;
- el navegador real se cubre en una suite Playwright separada.

## E2E-1 — Smoke del MVP

La suite ampliada a 15 escenarios cubre:

- navegación pública progresiva en escritorio, landing dinámica de Competición e índice/detalle CMS;
- CTA del Hero hacia el listado real de torneos;
- recorrido Inicio → Competición → Campeonato → Categoría → Clasificación → Calendario → Partido, con dos jornadas y retorno contextual;
- menú público móvil, cierre al navegar y con Escape, foco recuperado y enlaces cerrados no visibles;
- estado activo de Competición en `/competicion`, `/torneos` y `/rankings`;
- sistema común de `/competicion` con jerarquía pública real de temporada y campeonato, headings `h1`–`h4`, enlace al detalle, título y descripción por ruta, tarjetas legibles, foco visible, Tab y Enter;
- matriz responsive a 320, 375, 768, 1024, 1280 y 1440 px sobre Navbar y toda la rama deportiva, más foco y zoom, sin overflow documental ni solapamientos;
- fallback 404 React y recuperación hacia Inicio;
- login real y acciones pendientes de Mi Panel;
- envío y confirmación coincidente de un resultado;
- discrepancia visible y bloqueada para los jugadores;
- resolución del conflicto desde el panel Blade;
- resultado oficial y ranking histórico sin porcentaje incorrecto ni `NaN`.

Las comprobaciones incorporadas por RC-HARDEN-1 reutilizan esta suite y verifican también la expansión del perfil de jugador, las etiquetas del formulario de recuperación, el listado real de `/torneos` y el estado accesible del control móvil del panel. El script de ejecución conserva los artefactos de Playwright cuando el resultado no es correcto y los limpia tras un cierre satisfactorio.

Limitaciones deliberadas del smoke:

- sólo Chromium; la matriz de tamaños no sustituye pruebas multibrowser ni una revisión visual completa;
- ejecución serial sobre un único relato y datos sembrados;
- no cubre dobles, porque ese flujo permanece en tests Feature;
- no valida apariencia pixel a pixel, accesibilidad completa ni todos los formularios administrativos;
- no sustituye la QA funcional, visual y responsive de QA-MVP-1.

## PUBLIC-NAVIGATION-1 — Navegación pública funcional

La cobertura de Fase 3B valida:

- una configuración editorial única con exactamente Inicio y Competición, sin destinos futuros, deportivos secundarios ni CMS en el primer nivel;
- Navbar anónimo y autenticado con logo, cuenta nombrada y separada, saludo, Mi Panel y logout conservado;
- matcher exacto de Inicio y de toda la rama de Competición, una sola selección, clase visual y `aria-current="page"` o `location` según corresponda;
- apertura y cierre móvil, `aria-expanded`, `aria-controls`, selección, cambio de ruta, Escape y retorno de foco;
- landing `/competicion` con un `h1` y enlaces accesibles a `/torneos` y `/rankings`, sin datos simulados ni placeholders;
- wildcard 404 sin redirect, enlaces de recuperación y prioridad correcta de rutas dinámicas válidas;
- un único landmark `<main>` en Home y el índice CMS;
- regresión representativa de auth, `/player`, Torneos, Rankings, Nosotros y CMS;
- E2E real de navegación desktop/móvil, cuenta, estado activo, 404 y matriz responsive, además de los workflows previos.

Instantánea verificada de PUBLIC-NAVIGATION-1, 2026-07-19:

- frontend: 23 archivos y 105 tests Vitest;
- calidad: ESLint y build Vite correctos;
- E2E: 13 escenarios Playwright Chromium sobre el stack MariaDB temporal;
- responsive: 320, 375, 768, 1024, 1280 y 1440 px sin overflow horizontal ni solapamiento de los grupos del Navbar;
- artefactos: el cierre correcto desmonta el stack y elimina los informes Playwright.

Vitest comprueba estructura e interacción, pero no puede acreditar por sí solo que `display: none` retire enlaces del foco. El E2E móvil verifica que el menú cerrado no expone esos enlaces como visibles o alcanzables y que Escape devuelve el foco al botón.

## PUBLIC-LANDING-SYSTEM-1 — Sistema común de landings públicas

La cobertura de Fase 3C valida:

- contenedor `<article>` sin `<main>`, headings implícitos ni Layout paralelo;
- cabecera con un único `h1`, introducción asociada y acciones opcionales;
- secciones con `h2`, ID explícito estable, `aria-labelledby`, introducción opcional y contenido hijo;
- acciones, rejilla y tarjetas basadas en `Link`, sin controles anidados, con nombre accesible, Tab y activación mediante Enter;
- metadatos sin dependencias: actualización y restauración de `document.title`, una única meta description y `noindex` local reversible para 404;
- `/competicion` sobre componentes compartidos, sin llamadas API ni datos deportivos simulados, con los destinos y copy funcional de 3B preservados;
- 404 con identidad propia, acciones de recuperación y metadatos específicos, sin redirección;
- ausencia de rutas placeholder para Aprende a jugar, Escuela y Club y regresión de Navbar, router, rutas deportivas, cuenta y CMS;
- Playwright a 320, 375, 768, 1024, 1280 y 1440 px sin overflow, con tarjetas legibles y foco verificable por teclado.

En 3C no se creó un componente común para `loading`, error, vacío o reintento. Los consumidores tenían contratos heterogéneos y no existía una adopción segura en dos ubicaciones sin modificar comportamiento. Fase 4A conecta la landing con datos reales mediante estados específicos de Competición y mantiene aplazada la abstracción global.

Instantánea verificada de PUBLIC-LANDING-SYSTEM-1, 2026-07-19:

- frontend: 25 archivos y 118 tests Vitest;
- calidad: ESLint y build Vite correctos;
- E2E: 14 escenarios Playwright Chromium sobre el stack MariaDB temporal;
- responsive: landing y Navbar validados a 320, 375, 768, 1024, 1280 y 1440 px sin overflow horizontal;
- teclado: los destinos de Competición reciben foco visible mediante Tab y navegan con Enter;
- artefactos: el cierre correcto desmonta el stack y elimina los informes Playwright.

## COMPETITION-LANDING-DATA-1 — Landing dinámica de Competición

La cobertura de Fase 4A valida:

- servicio: una única llamada a `GET /seasons`, extracción del envelope `{ message, data }`, propagación del error y ausencia de peticiones alternativas;
- hook: loading inicial, éxito, vacío, error seguro, reintento real, ausencia de duplicados al rerenderizar y descarte de respuestas posteriores al desmontaje;
- composición: un solo `h1`, sección dinámica mediante `PublicLanding`, temporadas con `h3`, campeonatos con `h4`, IDs derivados de identificadores reales y destinos generales conservados;
- contrato deportivo: etiquetas comprensibles para estados y modalidad, fechas sólo cuando existen, recuento de categorías cuando está disponible y enlace `/torneos/{id}`;
- privacidad de presentación: ausencia de `is_public`, slugs, timestamps y otros campos técnicos; React no aplica filtrado editorial;
- estados específicos: loading anunciado, error anunciado con retry, vacío global y temporada visible sin campeonatos;
- nullables: fechas nulas, descripción nula, recuento de categorías ausente y colección de campeonatos nula no rompen el renderizado;
- regresión: `PublicLanding`, Navbar, router, 404, Torneos, Rankings, CMS, cuenta y Mi Panel conservan sus contratos;
- E2E real: temporada y campeonato de `E2ESmokeSeeder`, estado y fechas, enlace de detalle, ausencia de `is_public`, destinos secundarios, teclado y matriz responsive 320–1440 px.

Instantánea verificada de COMPETITION-LANDING-DATA-1, 2026-07-19:

- frontend: 28 archivos y 134 tests Vitest;
- calidad: ESLint y build Vite correctos;
- E2E: 14 escenarios Playwright Chromium sobre el stack MariaDB temporal;
- responsive: temporada, campeonato y destinos generales legibles a 320, 375, 768, 1024, 1280 y 1440 px sin overflow horizontal;
- teclado: el enlace contextual de campeonato recibe foco visible mediante Tab y navega al detalle con Enter;
- artefactos: el cierre correcto desmonta el stack, la red y los volúmenes temporales y elimina los informes Playwright.

## COMPETITION-RANKING-NAVIGATION-1 — Ranking y rutas contextuales

La cobertura de Fase 4B valida:

- servicio: una única llamada a `GET /rankings/all-time`, extracción del envelope `{ message, data }`, propagación del error y ausencia de fallbacks;
- hook: loading inicial, contenido, vacío, error seguro, retry específico, ausencia de duplicados al rerenderizar, descarte tras desmontaje y protección frente a una respuesta anterior que resuelva después del retry;
- preview: orden exacto de la respuesta, máximo de cinco filas, posición nula sin numeración inventada, puntos y categorías opcionales y ausencia de `player_id` en la interfaz;
- independencia: fallo del resumen de temporadas con ranking utilizable y fallo del ranking con temporadas utilizables; cada retry conserva una única petición al recurso correspondiente;
- navegación: enlace permanente a `/rankings`, detalle de campeonato y categoría con destinos reales de detalle, standings y schedule, y helpers defensivos ante identificadores ausentes;
- regresión: la tabla histórica completa presenta más de cinco filas y conserva su servicio; `App.jsx`, rutas, Navbar, Home, metadatos y contratos API permanecen intactos;
- E2E real: vacío histórico inicial del seeder sin resultados validados, preview real tras los workflows de validación, navegación al ranking completo y recorrido contextual de categoría hasta standings y schedule;
- responsive y accesibilidad: lista semántica, regiones y navegación etiquetadas, targets de al menos 44 px, foco visible y ausencia de overflow en la matriz 320–1440 px.

Instantánea verificada de COMPETITION-RANKING-NAVIGATION-1, 2026-07-19:

- frontend: 33 archivos y 151 tests Vitest;
- calidad: ESLint y build Vite correctos;
- E2E: 14 escenarios Playwright Chromium sobre el stack MariaDB temporal;
- backend: no modificado y sin suite backend necesaria para este bloque frontend;
- artefactos: el cierre correcto desmonta el stack, la red y los volúmenes temporales y elimina los informes Playwright.

## COMPETITION-UX-CLOSURE-1 — Cierre de la experiencia pública de Competición

La cobertura de Fase 4C valida:

- composición: acceso principal a Torneos antes de la jerarquía, ranking histórico como único enlace de la landing a `/rankings` y ausencia de acciones duplicadas en las tarjetas de campeonato;
- estados remotos: loading anunciado, error diferenciado del vacío y reintento acotado en Torneos, campeonato, categoría, clasificación, calendario y rankings; el fallo del ranking no oculta el campeonato y el fallo del contexto no borra una colección disponible;
- jerarquía: Temporada → Campeonato → Categoría visible, retornos deterministas y navegación común de resumen, clasificación y calendario con `aria-current`;
- dominio: enums traducidos con fallback neutral, fechas parciales sin separadores vacíos, valores ausentes seguros y posiciones de standings y rankings tomadas del backend sin renumeración React;
- estructura: el detalle de categoría ya no solicita ni duplica standings o schedule; las tablas conservan semántica y scroll horizontal acotado; el partido vuelve al calendario real;
- regresión: rutas, Navbar, Home, 404, CMS, cuenta, inscripciones, workflow de resultados y contratos API permanecen funcionales;
- E2E real: Inicio → Competición → Campeonato → Categoría → Clasificación → Calendario → Partido, ranking completo, vacío filtrado, foco visible, zoom y matriz 320–1440 px en toda la rama deportiva.

Instantánea verificada de COMPETITION-UX-CLOSURE-1, 2026-07-19:

- frontend: 36 archivos de test y 166 tests Vitest;
- calidad: ESLint y build Vite correctos;
- E2E: 15 escenarios Playwright Chromium sobre MariaDB temporal, todos correctos;
- backend: inspeccionado como fuente de contrato, no modificado y sin suite backend necesaria para este bloque frontend;
- artefactos: el cierre correcto desmontó contenedores y red temporal; `dist` e informes generados se eliminan antes de la entrega.

## KNOWLEDGE-COMPILER-1 — Contrato y compilador build-time

La cobertura de Fase 5A valida:

- descubrimiento de las cuatro colecciones aprobadas, exclusión explícita de AGENTS, README y metodología, orden previo al procesamiento e ignorado de archivos no Markdown;
- front matter escalar válido, campos obligatorios, delimitadores, duplicados, valores complejos, SemVer, fechas ISO reales, estados y slugs;
- ID único global, slug único por namespace, ruta lógica y orden únicos;
- rechazo de scripts, iframes, eventos HTML, esquemas peligrosos, imports/exports MDX, JSX, expresiones ejecutables, imágenes y traversal, sin confundir llaves descriptivas con código;
- enlaces relativos, anchors e IDs válidos, y errores explícitos para destinos rotos;
- igualdad byte a byte con distinto orden de creación, sin timestamp ni rutas absolutas;
- corpus real con 40 documentos, cuatro colecciones, IDs esperados y ausencia de los documentos excluidos;
- sincronía exacta entre el corpus y `frontend/src/generated/knowledge/knowledge.json`;
- creación del directorio de salida, escritura completa y conservación de una salida anterior cuando la validación falla.

Los 32 casos del compilador usan únicamente directorios temporales bajo el entorno del runner, no modifican el corpus real y limpian sus fixtures. `knowledge:check` y `knowledge:build` no requieren backend, MariaDB o red. La regresión completa de frontend confirma que el artefacto no se importa todavía en páginas y que el build Vite conserva su comportamiento.

Instantánea verificada de KNOWLEDGE-COMPILER-1, 2026-07-20:

- corpus: 40 documentos compilables y cuatro exclusiones explícitas;
- compilador: 32 tests Vitest dirigidos;
- frontend: 37 archivos de test y 198 tests Vitest;
- calidad: `knowledge:check`, `knowledge:build`, ESLint y build Vite correctos;
- determinismo: dos compilaciones consecutivas producen el mismo SHA-256;
- backend y E2E: no ejecutados porque no cambian aplicación pública, API o base de datos.

## KNOWLEDGE-PUBLICATION-READINESS-1 — Preparación editorial del corpus

La cobertura de Fase 5A.1 amplía el compilador para validar:

- un único H1 por documento, situado como primer heading y coincidente con `titulo`;
- secciones y subsecciones H2/H3 coherentes, rechazo de niveles fuera de H1–H6 y de saltos como H2 → H4;
- referencias `Vigente → Vigente` válidas y rechazo diagnosticado de `Vigente → Borrador` o destinos inexistentes;
- referencias desde un borrador hacia borradores o vigentes permitidas mientras el grafo canónico sea válido;
- corpus real con 40 documentos `Vigente`, cero borradores, los ocho Reglamentos aprobados, un H1 exacto por documento y la tabla de REG-006 preservada;
- validación en memoria sin escritura, regeneración segura, sincronía byte a byte y determinismo independiente del orden del filesystem.

Los diagnósticos de headings y referencias incluyen `sourcePath`, ID de origen y el heading o destino responsable. La normalización modifica sólo estado, fecha de revisión y marcadores de headings: no altera texto, títulos, IDs, slugs, versiones ni referencias.

Instantánea verificada de KNOWLEDGE-PUBLICATION-READINESS-1, 2026-07-20:

- corpus: 40 documentos vigentes, cuatro colecciones y cero referencias a contenido no publicable;
- compilador: 44 tests Vitest dirigidos;
- frontend: 37 archivos de test y 210 tests Vitest;
- calidad: `knowledge:check`, `knowledge:build`, ESLint y build Vite correctos;
- determinismo: dos compilaciones consecutivas producen el mismo SHA-256;
- backend y E2E: no ejecutados porque no cambian React público, API, base de datos ni navegación.

## KNOWLEDGE-PUBLIC-CONSUMER-1 — Proyección y consumo público seguro

La cobertura de Fase 5B valida:

- separación entre el artefacto canónico completo y `public-knowledge.json`, inclusión exclusiva de `Vigente`, omisión de colecciones vacías y fallo cuando no queda contenido público;
- ausencia total de ID, slug, título, cuerpo, ruta, estado o referencias de un borrador mediante fixtures temporales que no modifican el corpus real;
- bloqueo de referencias públicas hacia borradores o destinos inexistentes y resolución de referencias explícitas a rutas públicas, sin convertir menciones de ID no explícitas;
- parser build-time limitado para H2–H6, párrafos, negrita, énfasis, listas, tabla, separadores, UTF-8 y anchors deterministas, con exclusión del H1 canónico;
- rechazo de HTML, imágenes, blockquotes, código, listas anidadas, tablas inconsistentes, inline incompleto, URLs peligrosas y nesting ambiguo;
- determinismo de ambos JSON, sincronía byte a byte, escritura coordinada y rollback de las dos salidas si falla la segunda promoción;
- repositorio frontend de esquema v1, orden, grupos, resolución por ID/slug y ausencia de campos editoriales;
- renderer semántico sin HTML inyectado, rutas de landing, Manual y documentos, metadatos, referencias, tabla y 404 sin filtración;
- Navbar con Inicio, Competición y Aprende a jugar, `aria-current` en toda la rama, menú móvil, cuenta separada y regresión de rutas existentes;
- recorrido Playwright, teclado, foco, un H1, tabla localmente desplazable, zoom al 200 %, matriz responsive 320–1440 px y 404 para slug o grupo inválido.

Instantánea verificada de KNOWLEDGE-PUBLIC-CONSUMER-1, 2026-07-20:

- corpus público: 40 documentos, cuatro colecciones, 117 enlaces inline resueltos y una tabla en REG-006;
- compilador: 61 tests dirigidos entre contrato canónico y proyección pública;
- frontend: 42 archivos de test y 261 tests Vitest;
- E2E: 16 escenarios Playwright sobre stack temporal aislado, incluido el recorrido de Aprende a jugar;
- calidad: `knowledge:check`, doble `knowledge:build`, ESLint y build Vite correctos;
- artefactos: 199.120 bytes canónicos y 622.547 bytes públicos; ambos hashes permanecen estables entre regeneraciones consecutivas;
- bundle: 697,04 kB JavaScript (154,83 kB gzip) en la compilación de producción;
- backend: suite no ejecutada porque Fase 5B no modifica backend, API, base de datos o seeders.

## KNOWLEDGE-EXPERIENCE-CLOSURE-1 — Cierre de Aprende a jugar y el Manual

La cobertura de Fase 5C valida:

- repositorio: orden de colecciones y documentos, posición, anterior/siguiente, límites primero/medio/último, colección de un documento, ausencia de wrap o cruces y copias de arrays que no permiten mutar el estado interno;
- tabla de contenidos: exclusión de H1, inclusión de H2–H6 en orden, IDs compilados y colisiones preservados, caracteres valencianos, nombre accesible y criterio explícito de mostrar también un único heading y omitir cero headings;
- composición: contexto local Aprende → Manual → colección, un H1, metadata, contenido seguro, tabla REG-006 y navegación documental sin retornos duplicados;
- fragmentos: navegación SPA con foco solicitado desde el índice, carga directa y recarga sin cambiar metadatos ni volver a parsear contenido;
- lazy loading: sólo las tres páginas de Aprende se importan mediante `React.lazy`, el fallback `Suspense` usa `role=status` sin añadir `<main>`, H1 o 404 y ninguna ruta ajena importa el repositorio o el artefacto;
- regresión: Navbar conserva tres entradas y su estado activo, 404 conserva URL y `noindex`, rutas deportivas, cuenta, CMS, renderer, referencias, listas y tabla mantienen sus contratos;
- Playwright: estado diferido observable sin sleep, recorrido landing → Manual → documento, índice y deep link, recarga directa, vecinos primero/medio/último, referencias, tabla, responsive 320–1440 px, zoom 200 %, teclado y 404.

Instantánea verificada de KNOWLEDGE-EXPERIENCE-CLOSURE-1, 2026-07-21:

- frontend: 43 archivos y 271 tests Vitest;
- E2E: 16 escenarios Playwright Chromium sobre el stack temporal aislado;
- build anterior: un único JS inicial de 697.044 bytes y 153.194 bytes gzip medidos localmente, con aviso Vite por superar 500 kB;
- build 5C: JS inicial de 412.506 bytes y 121.161 bytes gzip; chunk compartido de Knowledge de 282.957 bytes y 32.083 bytes gzip, más entradas diferidas de 885, 1.308 y 5.358 bytes;
- privacidad del bundle: la frase de control «La cancha de Galotxas constituye el espacio donde se desarrolla el juego.» desaparece del JS referenciado por `dist/index.html` y aparece exclusivamente en el chunk diferido de Knowledge;
- calidad: `knowledge:check`, doble `knowledge:build`, suite Vitest, ESLint, build Vite y E2E correctos; el build final no emite el aviso de chunk superior a 500 kB;
- backend: no modificado y sin suite backend necesaria para este bloque frontend.

## Flujo de Inscripción y Administración (Fase 3 Core)
- prevención de inscripciones si el campeonato está cerrado;
- prevención de inscripciones duplicadas;
- creación exitosa de inscripciones en estado pendiente;
- rechazo de usuarios sin perfil de jugador al intentar inscribirse;
- acciones administrativas (aprobar/rechazar/devolver a pendiente);
- asignación de categoría exclusiva para jugadores con solicitudes aprobadas;
- prevención de asignaciones duplicadas a la misma categoría;
- protección de accesos administrativos frente a usuarios normales.

## CMS público
- listado público de páginas publicadas en `GET /api/v1/cms/pages`;
- publicación inmediata de páginas `published` con `published_at = null`;
- publicación de páginas con fecha pasada o igual al momento actual;
- exclusión de borradores y páginas futuras del listado público;
- aplicación del mismo criterio de fecha y estado al listado y al detalle por `slug`;
- ausencia de bloques y campos internos en el listado público;
- orden estable del listado por `published_at` descendente e `id` descendente;
- lectura pública de una página publicada por `slug`;
- respuesta `404` para páginas inexistentes;
- respuesta `404` para páginas en borrador;
- respuesta `404` para páginas publicadas con fecha futura;
- serialización de bloques ordenados;
- ocultación de campos internos del CMS en el Resource público.

## CMS administrativo
- acceso del administrador al listado de páginas CMS;
- creación de página CMS siempre como borrador y orientación del flujo desde el formulario;
- rechazo de peticiones manipuladas que intenten crear directamente una página publicada;
- edición de página CMS desde panel admin;
- rechazo de publicación de una página sin bloques y publicación válida cuando existe contenido;
- conservación de `published_at = null` como publicación inmediata;
- presentación diferenciada de Borrador, Programada y Publicada;
- explicación de `config('app.timezone')` en el formulario de edición;
- validación de unicidad de `slug`;
- conservación del propio `slug` durante edición;
- protección de acceso frente a usuarios no administradores.
- visualización de bloques CMS de una página;
- creación, edición y eliminación de bloques CMS con feedback visible o flash comprobado;
- eliminación normal del último bloque en borradores y de un bloque publicado cuando permanece otro;
- rechazo y conservación del último bloque de una página `published`;
- ordenación de bloques por `sort_order`;
- validación de tipo de bloque;
- validación de datos mínimos según tipo de bloque;
- protección frente a edición de bloques desde una página ajena;
- comprobación de que los bloques creados desde admin salen por el endpoint público.

## CMS-EDITORIAL-1 — Endurecimiento editorial

La Fase 2A añade regresiones dirigidas para:

- el flujo crear borrador → añadir bloques → publicar;
- la defensa backend frente a altas publicadas manipuladas;
- la invariancia que impide publicar páginas vacías;
- publicación inmediata con fecha nula y programación con fecha futura;
- estado derivado correcto en el índice y detalle Blade;
- zona horaria configurada comunicada en el formulario;
- protección del último bloque de páginas `published`, incluida la conservación de sus datos;
- feedback de creación, actualización, borrado y rechazo de bloques;
- pertenencia del bloque a la página también durante el borrado;
- igualdad de criterio entre listado y detalle públicos para borrador, fecha nula, pasada y futura;
- orden de bloques de una página pública válida;
- continuidad de las pruebas de sesión para administradores activos e inactivos.

Las fechas sensibles se fijan con Carbon y se restablecen en cada prueba. Toda la cobertura Feature usa factories y el MariaDB aislado de `galotxas-test`; no depende de datos locales.

## SEASON-ADMIN-1 — Integridad administrativa de temporadas

`AdminSeasonTest` cubre el contrato Blade de Temporadas:

- acceso al formulario para administradores activos y rechazo de usuarios normales o administradores inactivos;
- creación y persistencia explícita de nombre, estado, fecha de inicio y fecha de fin;
- conservación de la nulabilidad real de ambas fechas tanto en el alta como al limpiarlas durante la edición;
- obligatoriedad del nombre, pertenencia del estado a `SeasonStatus` y validación individual de las fechas;
- rechazo de una fecha de fin anterior al inicio sin persistir datos inválidos;
- selección correcta del enum casteado, incluida la regresión que impide mostrar `planned` para una temporada `active`;
- prioridad de `old()` sobre los valores persistidos después de un error de validación;
- actualización completa e inmutabilidad de los datos previos ante una actualización inválida;
- regresión de listado y borrado, y continuidad del envelope y los campos de `SeasonResource` en el endpoint público existente.

La suite dirigida se ejecuta junto con `AdminActiveSessionTest` sobre el MariaDB aislado de `galotxas-test`. Este bloque no modifica rutas, controladores o Resources de la API ni añade cobertura frontend o E2E.

## CHAMPIONSHIP-ADMIN-1 — Integridad administrativa de campeonatos

`AdminChampionshipTest` cubre el contrato Blade de Campeonatos:

- acceso de administradores activos y rechazo de usuarios normales, administradores inactivos y sesiones anónimas;
- opciones reales y recuperación de valores en alta y edición para temporada, tipo, estado del campeonato y estado de inscripciones;
- creación y actualización explícitas de todos los campos no multimedia, incluido el cambio a una temporada existente;
- nulabilidad y limpieza de descripción, fechas del campeonato y fechas de inscripción;
- obligatoriedad, longitudes, existencia de temporada, enums, formato de fechas y cronología independiente de cada intervalo;
- prioridad de `old()` sobre los valores persistidos tras un error de validación;
- inmutabilidad ante actualizaciones inválidas y regresión específica contra pérdidas silenciosas de datos;
- ausencia de entrada `image_path`, rechazo de datos manipulados en el alta y conservación del valor persistido en la edición;
- continuidad de listado, detalle, categorías, solicitudes de inscripción, relaciones y borrado existente;
- invariancia de campos, envelope y visibilidad por estado de los endpoints públicos actuales.

La suite dirigida se ejecuta sobre el MariaDB aislado de `galotxas-test` junto con `AdminActiveSessionTest`, las pruebas administrativas de solicitudes y categorías y `ChampionshipRegistrationTest`. No se añade cobertura frontend o E2E porque este bloque no modifica React ni la API pública.

## CATEGORY-ADMIN-1 — Integridad administrativa de categorías

`AdminCategoryTest` cubre el contrato Blade de Categorías:

- acceso de administradores activos y rechazo de usuarios normales, administradores inactivos y sesiones anónimas;
- creación anidada bajo un campeonato existente y rechazo por route model binding de campeonatos inexistentes;
- ausencia de selector o payload administrable para mover una categoría a otro campeonato;
- creación y actualización explícitas de nombre, descripción, nivel, género y estado;
- nulabilidad y limpieza de descripción y nivel;
- obligatoriedad y límites del nombre, límite de descripción, rango de nivel y pertenencia de género y estado a sus valores reales;
- recuperación correcta de valores y prioridad de `old()` tras un error de validación;
- inmutabilidad ante actualizaciones inválidas y regresión específica contra pérdidas silenciosas de descripción y estado;
- ausencia de entrada `image_path`, rechazo de datos manipulados en el alta y conservación del valor persistido en la edición;
- conservación de campeonato, inscripciones, participantes competitivos y rondas durante la actualización;
- continuidad de listado, detalle y borrado existentes;
- invariancia del contrato y la visibilidad pública actual de categorías, incluida la ausencia de descripción e imagen en `CategoryPublicResource`;
- continuidad de standings, schedule, partidos, inscripciones, generación de liga y copa y rankings relacionados.

La suite dirigida se ejecuta sobre el MariaDB aislado de `galotxas-test` junto con las pruebas de sesión administrativa, inscripciones, rankings, calendario, copa y contrato público de partidos. No se añade cobertura frontend o E2E porque este bloque no modifica React ni la API pública.

## COMPETITION-VISIBILITY-FOUNDATION-1 — Base administrativa de visibilidad

La Fase 2B.4A incorpora cobertura Feature para:

- columnas booleanas, default privado y casts de `is_public` en `Season`, `Championship` y `Category`;
- factories privadas por defecto y estados expresivos `publiclyVisible()` y `privatelyVisible()`;
- checkbox accesible, valor oculto, recuperación del valor persistido y prioridad de `old()` en los tres formularios;
- creación y actualización públicas o privadas de temporadas sin alterar su estado operativo;
- creación y actualización de campeonatos públicos únicamente bajo temporadas públicas;
- creación y actualización de categorías públicas únicamente bajo campeonatos y temporadas públicos;
- aceptación normal de registros privados bajo cualquier combinación de padres;
- rechazo de booleanos manipulados y mensajes comprensibles para las violaciones jerárquicas;
- ocultación de temporadas o campeonatos sin cascada sobre los flags de sus descendientes;
- conservación de todos los campos completados en 2B.1, 2B.2 y 2B.3, incluidas relaciones e imágenes no administrables;
- continuidad de permisos para administrador activo, inactivo, usuario normal y anónimo;
- regresión pública temporal: las consultas aún entregan registros privados, los Resources no incluyen `is_public` y permanecen intactos rutas, campos y envelopes.
- aislamiento histórico del CRUD API administrativo durante 2B.4A: sus respuestas no serializaban `is_public` y sus escrituras masivas no podían modificarlo antes de 2B.5.

La migración se valida mediante `migrate:fresh` sobre el MariaDB aislado. Su backfill se revisa explícitamente: primero crea las columnas con default `false`, después marca como públicos todos los registros preexistentes y conserva `false` como default para altas futuras. No se ejecuta contra desarrollo.

## COMPETITION-VISIBILITY-PUBLIC-1 — Aplicación pública de visibilidad

La Fase 2B.4B incorpora cobertura Feature para:

- scopes locales y métodos de instancia coincidentes para temporada, campeonato, categoría y partido, sin global scopes ni dependencia de estados operativos;
- filtrado de listados, detalles y relaciones anidadas, preservando campos, envelopes y ausencia de `is_public` en Resources;
- respuesta `404` en accesos directos a campeonatos, categorías, standings, schedules, rankings y partidos de ramas privadas;
- exclusión de resultados privados en rankings públicos de campeonato, temporada e histórico, conservando los cálculos internos completos;
- inicio de inscripciones únicamente en campeonatos públicos y conservación de solicitudes propias existentes;
- continuidad de partidos, calendario y rankings privados relacionados en Mi Panel, así como workflows de participantes;
- acceso administrativo a entidades privadas y aislamiento respecto a los scopes públicos;
- ocultación y restauración de padres sin modificar flags descendientes;
- factories privadas por defecto y seeders de desarrollo y E2E con jerarquías públicas explícitas.

La regresión incluye tests administrativos y públicos dirigidos, suite backend completa en MariaDB, tests unitarios de React, lint, build y batería E2E completa. Las pruebas frontend verifican el mismo contrato: el filtrado es responsabilidad del backend y no se reproduce en React.

## COMPETITION-ADMIN-API-1 — Endurecimiento de la API administrativa

La Fase 2B.5 incorpora cobertura Feature para los 15 endpoints CRUD de temporadas, campeonatos y categorías:

- rechazo de anónimos, usuarios normales y administradores inactivos, y acceso completo del administrador activo;
- Form Requests compartidos con Blade cuando las reglas coinciden y Request API específico para el padre requerido al crear una categoría por la ruta plana;
- whitelists y persistencia explícitas, sin `$request->all()`, con enums, booleanos, fechas nullable y cronología validados;
- altas y actualizaciones completas, limpieza de campos nullable y ausencia de mutación tras una petición inválida;
- gestión de `is_public` con jerarquía idéntica a Blade, sin scopes públicos ni cascada sobre flags descendientes;
- conservación de `image_path`, derivación de slugs e inmunidad frente a `id`, timestamps, campos desconocidos y relaciones manipuladas;
- asociación de categoría en creación e imposibilidad de trasladarla mediante el payload de actualización;
- contratos exactos de `AdminSeasonResource`, `AdminChampionshipResource` y `AdminCategoryResource`, envelopes y códigos HTTP;
- consulta administrativa de entidades privadas y regresión de filtros, `404`, campos y envelopes de la API pública.

Las rutas CRUD son planas, por lo que no existen desajustes de route model binding anidado que probar. La regresión se ejecuta sobre MariaDB aislado e incluye además los tests Blade de las fases 2B.1–2B.4, sesiones administrativas activas y contratos públicos relacionados.

## CMS público React
- consumo del endpoint `GET /api/v1/cms/pages` desde el cliente API existente;
- ruta pública `/contenidos`;
- consumo del endpoint `GET /api/v1/cms/pages/{slug}` desde el cliente API existente;
- ruta pública `/contenidos/:slug`;
- enlaces institucionales del navbar apuntando a rutas CMS;
- índice público con estados de carga, error, vacío y contenido;
- renderizado controlado de bloques sin HTML libre;
- estados de carga y no encontrado.

## CMS seeders
- creación no destructiva de páginas institucionales base;
- slugs institucionales `prensa-media`, `nosotros`, `federaciones`, `academy`, `documentos` y `federarse`;
- creación de bloques mínimos `heading` y `text` para páginas nuevas;
- preservación de páginas CMS existentes con el mismo slug.

## Pistas administrativas y seeder

VENUE-1 incorpora cobertura Feature para:

- listado, creación y edición de pistas por un administrador;
- denegación de acceso a usuarios no administradores;
- obligatoriedad y unicidad del nombre, conservación del nombre propio en edición y longitudes máximas;
- eliminación de una pista sin relaciones;
- bloqueo de eliminación de una pista utilizada por un partido;
- creación de `Pista 1` a `Pista 5` mediante `DefaultVenueSeeder`;
- idempotencia del seeder y preservación de datos existentes.

El modelo no dispone de `active`, por lo que no existe un scope de activas que probar en este bloque. Las pruebas se ejecutan sobre el MariaDB aislado de Docker, igual que el resto de pruebas Feature.

## Generación reproducible de liga

SCHEDULE-1 incorpora cobertura Feature para:

- fallo sin pistas, mensaje administrativo accionable y ausencia de jornadas o partidos parciales;
- pistas con IDs no consecutivos y nombres personalizados;
- selección de todas las pistas existentes en orden determinista por ID;
- reutilización segura de una única pista solo en fechas u horas distintas;
- fallo atómico cuando una jornada supera los siete huecos por pista;
- mantenimiento del round-robin y los descansos en individuales impares;
- mantenimiento del round-robin de dobles, incluido el nivel 1;
- protección frente a regeneración duplicada;
- regresión de copa y finales mediante la suite competitiva existente.

La cobertura de SCHEDULE-1 valida colisiones dentro de cada categoría generada. La coordinación de horarios entre categorías diferentes queda fuera de este bloque.


# 10. Pruebas de gobernanza y publicación

## Cobertura actual

La base CMS dispone de pruebas Feature para administración de páginas y bloques, autorización administrativa, unicidad de `slug`, creación obligatoria en borrador, contenido mínimo publicable, publicación inmediata, programación, protección del último bloque, feedback, exclusión de borradores y fechas futuras, acceso público por `slug` y Resources. React dispone de pruebas para los estados y el renderizado controlado de páginas CMS, y el smoke E2E recorre el índice y el detalle bajo `/contenidos`.

Esta cobertura confirma el módulo básico actual. No demuestra todavía que las futuras áreas de Club, Escuela, noticias, archivos o formularios estén implementadas ni que sus requisitos específicos estén resueltos.

## Requisitos para futuras secciones administrables

Cada ampliación debe seleccionar pruebas proporcionales a su riesgo e incluir, cuando corresponda:

- autorización de lectura y escritura administrativa;
- validación mediante Form Requests;
- unicidad y estabilidad de slugs;
- transiciones de borrador, publicación, despublicación y programación;
- exclusión de borradores y publicaciones futuras en listados públicos;
- respuesta segura ante acceso directo a contenido inexistente o no publicable;
- contrato de Resources y ausencia de campos administrativos;
- renderizado frontend en estados `loading`, `error`, `empty` y `content`;
- tipos de bloque o contenido desconocidos sin ejecución de HTML arbitrario;
- integración de rutas y servicios;
- E2E del recorrido administrativo y público cuando sea crítico;
- accesibilidad, navegación por teclado y responsive.

El backend debe probar el filtro de publicación. Una prueba que solo comprueba que React oculta un borrador no satisface la seguridad editorial.

## Validación de `knowledge/`

KNOWLEDGE-COMPILER-1 cubre en 5A estructura, campos obligatorios, IDs, slugs, namespaces, rutas lógicas, referencias, seguridad y generación determinista. Los consumidores React del Manual deberán probar datos generados válidos, ausentes e inválidos en 5B; esa cobertura no se atribuye al compilador ni se considera publicada en 5A.

## SCHOOL-CORE-ADMIN-1 — Núcleo operativo de Escuela

Fase 6B.1 incorpora 28 tests Feature dirigidos sobre MariaDB para:

- las cuatro migraciones, defaults seguros, casts, relaciones, factories y estados expresivos de `SchoolProgram`, `SchoolLevel`, `SchoolLocation` y `SchoolSchedule`;
- un único programa público garantizado por servicio y restricción de MariaDB, con error administrativo comprensible;
- `SchoolLevel` como clasificación formativa propia, sin slug y sin reutilizar `Category`;
- `SchoolLocation` separada de `Venue`, con localidad obligatoria y notas exclusivamente administrativas;
- día semanal ISO 1–7, hora inicial anterior a final, ubicación y nivel activos al activar, duplicado exacto rechazado y solapamientos parciales permitidos;
- visibilidad efectiva de programa, nivel y horario, incluida la ubicación activa, sin cascadas de flags al ocultar un padre;
- borrado restrictivo y feedback para programa, nivel y ubicación en uso;
- persistencia explícita, filtros, recuperación de valores, estados vacíos y navegación de las cuatro áreas Blade;
- permisos de administrador activo y rechazo de administrador inactivo, usuario normal y anónimo;
- ausencia de rutas públicas web o API de Escuela.

La ejecución dirigida `--filter=School` finaliza con 28 tests y 182 aserciones. La suite backend completa finaliza con 283 tests y 2158 aserciones. Estos tests no atribuyen cobertura a inscripciones, centros, actividades, API o React.

## SCHOOL-ENROLLMENT-ADMIN-1 — Inscripciones de Escuela

Fase 6B.2 incorpora 38 tests Feature dirigidos y 283 aserciones sobre MariaDB para:

- tabla, defaults, enum casteado, fechas inmutables, relaciones, estados de factory, scopes y orden estable;
- claves foráneas restrictivas, `user_id` con `nullOnDelete` y restricción compuesta programa–nivel;
- edad en `requested_at`, cumpleaños exacto de 18 años, día anterior, fecha futura, consulta histórica y nacimiento en año bisiesto;
- menores con representante obligatorio, adultos con representante normalizado a `null`, teléfono y correo siempre obligatorios;
- solicitud anónima y vinculación opcional desde Sanctum, sin aceptar cuenta, estado, fechas o notas desde el payload;
- programa público abierto resuelto en backend y respuesta `409` idéntica para ausencia, privacidad o cierre;
- nivel público opcional y rechazo de nivel privado, inactivo, inexistente o de otro programa;
- creación siempre pendiente, aprobación con nivel, rechazo, baja histórica, reasignación e imposibilidad de repetir o revertir transiciones;
- `POST /api/v1/school/enrollments` con `201` genérico sin identificador ni datos personales, ausencia de GET y payload cerrado;
- limitador `school-enrollments` de cinco intentos por minuto por IP y hash del correo, sin afectar rutas públicas ajenas;
- listado, filtros, contadores, alta manual, detalle, edición limitada, acciones por estado, estados vacíos, `old()` y ausencia de `destroy`;
- autorización para administrador activo y rechazo de administrador inactivo, usuario normal y anónimo;
- ausencia de `Player`, seeders, colegios, actividades, API de lectura, Resources y frontend.

La regresión dirigida del núcleo escolar, sesión administrativa y limitadores añade 38 tests y 233 aserciones. La suite backend completa finaliza con 321 tests y 2441 aserciones. `migrate:fresh` y el rollback de la migración más reciente se verifican en el entorno Docker aislado de test; no se utiliza SQLite ni la base de desarrollo.

## SCHOOL-EDUCATIONAL-ACTIVITIES-1 — Centros y actividades

Fase 6B.3 incorpora 27 tests Feature dirigidos y 213 aserciones sobre MariaDB para:

- las dos migraciones, defaults, casts, relaciones, claves foráneas restrictivas y factories de `EducationalCenter` y `EducationalActivity`;
- centros homónimos, orden estable por localidad/nombre/ID, contacto nullable, notas privadas y activación;
- actividades de nombre libre, fecha obligatoria, horas ambas presentes o ausentes y hora inicial anterior a la final;
- `expected_students` nullable al planificar, positivo cuando se informa y obligatorio al completar;
- creación siempre `planned` aunque el payload intente asignar estado;
- transiciones exclusivas `planned → completed` y `planned → cancelled`, sin repetición, reversión o reactivación;
- conservación de centro y ubicación históricos al desactivarlos, con rechazo de asociaciones nuevas inactivas;
- borrado permitido sólo para actividades planificadas y protección de centros y ubicaciones con histórico;
- listados, filtros, detalle, formularios, `old()`, acciones contextuales, estados vacíos y navegación Blade;
- autorización para administrador activo y rechazo de administrador inactivo, usuario normal y anónimo;
- ausencia de alumnos nominales, campos extra persistidos, rutas web públicas, API pública y API administrativa.

La regresión dirigida del conjunto escolar finaliza con 93 tests y 678 aserciones. La suite backend completa finaliza con 348 tests y 2654 aserciones. `migrate:fresh`, rollback de las dos migraciones de 6B.3 y reaplicación se verifican en el entorno Docker aislado de test; no se utiliza SQLite ni la base de desarrollo.

## SCHOOL-PUBLIC-READ-API-1 — Lectura pública de Escuela

Fase 6B.4 incorpora 8 tests Feature y 52 aserciones dirigidas sobre MariaDB para:

- `GET /api/v1/school` anónimo, de sólo lectura y con envelope exacto;
- respuesta `200` con `data: null` idéntica ante ausencia o privacidad del programa;
- contrato mínimo, completo y parcial con contacto nullable, ubicación habitual activa y apertura efectiva;
- niveles activos y públicos en orden `sort_order`, ID, incluidos niveles sin horarios;
- horarios efectivos en orden día ISO, hora inicial, `sort_order`, ID y horas `HH:MM`;
- exclusión de niveles privados o inactivos, horarios inactivos y ubicaciones inactivas;
- allowlists recursivas sin flags, órdenes, claves foráneas, notas, timestamps, inscripciones, usuarios, centros o actividades;
- ausencia de mutación y número de consultas constante y no superior a cinco al crecer niveles, horarios y ubicaciones.

La regresión conjunta del nuevo GET y el POST existente completa 19 tests y 134 aserciones. `--filter=School` completa 80 tests y 557 aserciones. La regresión explícita de Escuela, centros, actividades, inscripciones, permisos y rate limiting completa 111 tests y 783 aserciones; la suite backend completa, 356 tests y 2708 aserciones. El limitador `school-enrollments`, payload, `201`, `409` y privacidad del POST permanecen intactos. No se atribuye cobertura a React, `/escuela`, Navbar o E2E.

## SCHOOL-PUBLIC-EXPERIENCE-1 — Experiencia pública de Escuela

Fase 6C completa la cobertura React de:

- normalización y servicios `GET /school` y `POST /school/enrollments`;
- hook de lectura con loading, contenido, `data: null`, error, retry y descarte tras desmontaje;
- cálculo local de minoría, cumpleaños 18, fecha futura, fecha inválida y 29 de febrero sin desplazamiento UTC;
- labels ISO de días y rangos de edad sin reordenar datos;
- landing, datos completos y parciales, niveles sin horarios, contacto/ubicación opcionales, cierre y metadatos;
- formulario de menor y adulto, representante condicional, nivel opcional numérico, validación local, foco, `aria-describedby`, envío único, `201`, `409`, `422`, `429` y error general;
- ruta diferida, fallback, Navbar en cuarta posición, Home sin “Academy”, descendientes no registrados y 404.

La suite frontend finaliza con 51 archivos y 312 tests. ESLint y build completan sin errores. El build genera School como chunk diferido de 15,37 kB (4,84 kB gzip); el inicial queda en 413,09 kB (122,54 kB gzip), Knowledge permanece separado y Vite no emite aviso de tamaño.

Playwright completa 21 escenarios en Chromium sobre el stack desechable: los 16 previos y cinco de Escuela. El recorrido escolar usa backend y MariaDB reales para lectura y solicitudes de menor y adulto; también cubre carga diferida, Navbar/Home, tamaños 320–1440 px, zoom 200 %, validación/foco, cierre concurrente `409`, `data: null`, lectura cerrada, error recuperable y descendiente 404. `E2ESmokeSeeder` sólo puede ejecutarse con `APP_ENV=e2e` y `galotxas_e2e`; contenedores, red, volumen temporal e informes se eliminan al finalizar correctamente.

La regresión backend no cambia: `--filter=School` completa 80 tests y 557 aserciones, y la suite completa 356 tests y 2708 aserciones sobre MariaDB. La cobertura de inscripciones deportivas no se usa como sustituto de `SchoolEnrollment`.

`SCHOOL-CONTRACT-AUDIT-1` queda cerrado. La compatibilidad y eventual migración de `academy` continúan como deuda editorial independiente.

## DOCKER-ENVIRONMENT-ISOLATION-1 — Separación y revalidación de entornos

La validación inicial de 6C terminó con un incidente operativo: un `docker compose down --volumes --remove-orphans` sin proyecto explícito se ejecutó sobre el antiguo archivo que reunía desarrollo y tests. Compose resolvió el proyecto `docker` desde el directorio del archivo, incluyó los servicios fijos de desarrollo y eliminó `docker_galotxas_db_data`. `APP_ENV` no intervino en la propiedad de esos recursos.

6C.1 separa:

- desarrollo en `galotxas` y `docker-compose.yml`;
- PHPUnit en `galotxas-test` y `docker-compose.test.yml`;
- Playwright en `galotxas-e2e` y `docker-compose.e2e.yml`.

Tests backend y E2E usan redes propias, base propia y `tmpfs`, sin volumen Docker. Ninguna configuración conserva `container_name`. `compose-isolation-guard.sh` valida la configuración resuelta y los recursos etiquetados; `safe-compose-down.sh` es la única ruta automática de limpieza; los runners pasan siempre proyecto y archivo explícitos.

La regresión automática de guardas completa 11 comprobaciones:

- acepta las configuraciones resueltas de backend test y E2E;
- rechaza el proyecto `galotxas` durante E2E;
- rechaza referencias al volumen de desarrollo;
- rechaza base o `APP_ENV` incorrectos;
- rechaza nombres fijos locales;
- rechaza archivo Compose inesperado;
- rechaza limpiar sin proyecto explícito;
- verifica que los runners no llaman a `down` directamente.

La prueba de no destrucción creó sólo una red centinela temporal, sin volumen ni base. Durante E2E coexistió con `galotxas-e2e`; después del cleanup seguía presente y se retiró de forma explícita. El stack E2E mostró cuatro contenedores prefijados, una red propia, cero volúmenes y `/var/lib/mysql` en `tmpfs`. Después de backend y E2E no quedó ningún contenedor, red o volumen de `galotxas-test` o `galotxas-e2e`.

Instantánea verificada de DOCKER-ENVIRONMENT-ISOLATION-1, 2026-07-30:

- guardas: 11 comprobaciones correctas y rechazo adicional de los runners reales al forzar el proyecto `galotxas`;
- backend dirigido: 80 tests School y 557 aserciones;
- backend completo: 356 tests y 2.708 aserciones;
- rutas: GET `/api/v1/school` y POST `/api/v1/school/enrollments` presentes;
- frontend: 51 archivos y 312 tests;
- calidad: `knowledge:check`, ESLint, build, `php -l` y Pint correctos;
- E2E: 21 escenarios Chromium correctos;
- build: chunk School de 15,37 kB, inicial de 413,09 kB y Knowledge separado, sin advertencia de tamaño;
- Knowledge: hashes canónico `66dfba8b620539b148539a8181e7f196b1abcc1a77e86efc737714983035d182` y público `4e5f28fd21d29291cddba2fede70ef7e057e45dbc5bb7583399186133911517e`;
- residuos: ningún recurso Docker de test ni informe Playwright;
- desarrollo: no se levantó, migró, sembró, restauró ni reconstruyó.

El análisis completo del incidente, la matriz de propiedad y los comandos seguros están en `13-docker-environment-isolation.md`.

---

## MVP-PARITY-AUDIT-1 — Auditoría documental de paridad

Fase 7A inspecciona estáticamente modelos, rutas, administración Blade, API,
router React, servicios, hooks y pruebas. `php artisan route:list
--except-vendor --json` confirma 185 rutas de aplicación, 58 bajo `/api/v1`;
la clasificación funcional separa lectura pública, autenticación, `/me`,
workflows de participante y API administrativa.

El bloque registra matrices de backend/frontend, rutas, CMS y autogestión en
`14-mvp-parity-audit.md`. También identifica los gates de pruebas aún ausentes:
Club/Contacto/footer, navegación agrupada, inscripción deportiva E2E, correo de
reset y despliegue real. No atribuye cobertura a esas funciones.

No se ejecutaron suites, migraciones, seeders ni Docker. La última instantánea
validada sigue siendo DOCKER-ENVIRONMENT-ISOLATION-1: 356 tests backend y 2.708
aserciones, 51 archivos y 312 tests frontend, y 21 escenarios Chromium. Fase 7A
exige únicamente inspección de sólo lectura y `git diff --check`.

---

## MVP-EDITORIAL-NAVIGATION-CONTRACT-1 — Contrato documental del MVP

Fase 7B reaudita de forma estática router, navegación, Home, Nosotros, CMS,
Escuela y Resources públicos deportivos. Registra en
`15-mvp-editorial-and-navigation-contract.md`:

- una única topología final con Inicio, Competición, Aprende, Club y Cuenta;
- interacción desktop/móvil, foco, Escape, estado activo y atributos ARIA;
- rutas canónicas, legado, aliases temporales y redirects futuros separados;
- fuentes CMS, Knowledge, Laravel y React;
- plantillas de Quiénes somos, Contacto, Federarse, Documentos, Escuela y Home;
- matriz legal y alternativas de identidad pública sin seleccionar política;
- gates humanos y criterios de prueba para 7C–7G.

Este marcador acredita coherencia documental, no comportamiento implementado.
No se atribuyen tests a submenús, rutas Club, footer, páginas legales, aliases,
redirects, cambios de identidad, datos School o despliegue todavía ausentes.

La validación de 7B se limita a revisión estática, búsquedas de coherencia,
`git diff --check`, `git diff --stat`, `git diff --name-only` y estado Git. No
ejecuta Docker, suites, migraciones, seeders, frontend, build o E2E.

---

## CLUB-VERTICAL-READINESS-AUDIT-1 — Preparación documental de Club

La subfase 7C.0 inspecciona estáticamente Knowledge, CMS, API, React,
`/nosotros`, rutas, cobertura y recursos y contrasta cada dato editorial
aportado. `16-club-vertical-readiness-audit.md` registra capacidades, límites,
grafías, duplicidades, imágenes, readiness, preguntas y gates sin implementar
la vertical.

Este marcador no acredita rutas Club, contenido CMS, aliases, paridad ni
publicación. La estrategia recomendada divide el trabajo futuro entre cierre y
carga editorial privada (7C.1) e implementación/validación pública (7C.2).

La validación de 7C.0 se limita a inspección estática, búsquedas con `rg`,
`git diff --check`, `git diff --stat`, `git diff --name-only` y estado Git. No
se ejecutan Docker, migraciones, seeders, suites, frontend, build ni E2E; las
últimas instantáneas funcionales documentadas permanecen sin cambios.

---

## CLUB-CONTACT-TECHNICAL-FOUNDATION-1 — Base técnica de Club y contacto

7C.1 añade cobertura Feature sobre MariaDB para migración, enum, casts,
factory, scopes, validación y límites; consentimiento, honeypot, HMAC y
ausencia de IP en claro; flags, 201/422/429/503, persistencia y notificación
posterior no bloqueante; configuración pública allowlisted; y administración,
filtros, transiciones y permisos. `AdminCmsBlockTest` cubre `mailto:` válido
sólo en enlaces y el rechazo de variantes malformadas o peligrosas.

Vitest cubre el servicio aislado de contacto para configuración, envío,
errores 422/429/503, red y respuestas inesperadas. El renderer mantiene la
allowlist separada de enlaces y media, y el router conserva `/club` y sus
cuatro rutas futuras en el fallback 404.

La validación de cierre exige runner backend aislado completo, suite frontend,
lint, build a un directorio temporal, Pint, `knowledge:check`, hashes
canónicos y `git diff --check`. No se ejecuta E2E porque no se crea una ruta o
interfaz pública.

## CLUB-PUBLIC-FACADES-1 — Fachadas públicas de Club

7C.2 añade Vitest para el mapa ruta/slug cerrado, carga diferida, estados CMS,
metadatos, respuestas inválidas y vacías, 404 y reintento. Contacto cubre
configuración independiente false/error/true y el formulario cubre validación,
consentimiento, honeypot, 201, 422, 429, 503, red, respuesta inesperada, foco,
doble envío y ausencia de storage.

Playwright utiliza sólo páginas CMS ficticias creadas por `E2ESmokeSeeder` bajo
su guard `e2e`: recorre las cuatro fachadas y rutas legadas, simula página
ausente y config false, valida el formulario real habilitado en E2E con 201 y
422, teclado, matriz 320/768/1280, descendientes 404 y Navbar sin Club. La
regresión backend mantiene publicación CMS y privacidad/configuración de
Contacto. El build se dirige a un directorio temporal y debe conservar Club,
School y Knowledge en chunks diferidos sin warnings.

## PUBLIC-NAVIGATION-HOME-FOOTER-1 — Navegación y layout global

7D.1 amplía Vitest/RTL sobre la configuración exacta del árbol, tipos,
visibilidad, audiencia, padres sin ruta, prefijos y `aria-current` exacto. El
Navbar cubre disclosure por click/Enter/Space, exclusión mutua, Escape y retorno
de foco, click exterior, cierre al navegar, dos niveles de menú móvil, Cuenta
anónima/autenticada y ramas activas. Home cubre copy, CTAs, cuatro bloques sin
destinos vacíos y metadatos; Footer cubre montaje global, rutas Club, redes
seguras, año calculado y ausencia deliberada de legal, Prensa y Federaciones.

Playwright añade recorridos desktop a Manual y Quiénes somos, un único grupo
abierto, Escape/foco, navegación móvil a Escuela con cierre total, Cuenta en
ambos estados, CTAs, footer, redes y 320 px sin overflow. Las suites existentes
continúan verificando las cargas diferidas de Club, School y Knowledge con
fixtures del stack temporal. La validación usa `frontend/scripts/run-e2e.sh` y
no toca la base de desarrollo.

El cierre exige suite frontend completa, lint, build a `outDir` temporal sin
warnings, `knowledge:check`, hashes canónicos, E2E completo y
`git diff --check`. Backend y PHP no se ejecutan porque 7D.1 no los modifica.

Validación de 7D.1, 2026-08-04: 371 tests Vitest en 57 archivos, lint,
`knowledge:check` con 40 documentos y cuatro colecciones, build temporal sin
warnings y 37/37 escenarios Chromium correctos. Los hashes de ambos artefactos
Knowledge coinciden con `HEAD`; el runner E2E elimina sus recursos aislados.

## LEGAL-PRIVACY-READINESS-1 — Auditoría documental de 7D.2A

7D.2A no modifica contratos runtime. Añade únicamente la exclusión exacta de la
referencia histórica al compilador y amplía su test de descubrimiento. Su
validación dirigida comprueba:

- `knowledge:check` y estabilidad de los hashes canónico/público;
- exclusión de `EST-REF-001` de ambos artefactos;
- ausencia de imports runtime desde `docs/legal-drafts/`;
- ausencia de rutas legales y enlaces nuevos;
- `CONTACT_FORM_ENABLED=false` por defecto;
- búsqueda controlada de denominaciones conflictivas y del teléfono privado
  sin reproducir su valor;
- `git diff --check` y ausencia de cambios en `frontend/dist`.

El cierre posterior de 7D.2B añade cobertura de identidad pública,
almacenamiento de sesión y recursos externos. Las rutas legales se implementan
después en 7D.2C1; primera capa, activación y mecanismos verificables quedan
para 7D.2C2.

Validación de 7D.2A: 45/45 tests de `compiler.test.js`, ESLint dirigido sin
salida y `knowledge:check` correcto con 40 documentos, cuatro colecciones y
cinco exclusiones explícitas.

## PRIVACY-HARDENING-PUBLIC-IDENTITY-1 — Privacidad técnica e identidad pública

7D.2B añade Feature tests sobre MariaDB para la proyección adulta con alias o
inicial, normalización de espacios, nombres y apellidos compuestos, menores,
fecha ausente y datos incompletos fail-closed; allowlists de clasificación,
calendario, partido y rankings; equipos; prevención de lazy loading; y
conservación de `/me` y Blade. También verifica que bienvenida y layout admin
no cargan fuentes, scripts, hojas o iframes remotos conocidos. La ejecución
dirigida completa 29 tests y 360 aserciones; la suite backend completa, 386
tests y 2.966 aserciones.

Vitest cubre el helper que sólo acepta `public_display_name`, consumidores de
Competición, login, recarga mediante `/me`, `401`/`419`, dato legado, logout y
ausencia de perfil persistido. El ajuste final distingue además el `403`
ordinario del `403` de usuario inactivo, conserva Cuenta, comprueba el Bearer
en la petición posterior y evita registrar payloads privados. Sus pruebas
focalizadas completan 26 casos; la suite completa finaliza con 389 tests en 59
archivos y ESLint sin errores.

Playwright completa 41 escenarios sobre el stack aislado. Además de la
regresión anterior, comprueba clasificación, partido y equipo con identidad
minimizada y respuestas sin campos privados; login real, recarga, ausencia de
`localStorage.user`, logout, `401`, `403` ordinario con Cuenta y Bearer
conservados; Club y Contacto oculto; 404 legal; y ausencia de peticiones a
Google Fonts, Bunny Fonts o jsDelivr. El runner elimina red, contenedores e
informes al terminar.

El build temporal transforma 207 módulos sin warnings, mantiene Club en 11,39
kB, School en 15,33 kB y Knowledge en 282,13 kB como chunks separados; el
inicial queda en torno a 420,3 kB. No modifica `frontend/dist`. Pint, `php -l`,
`knowledge:check`, hashes y `git diff --check` forman parte del cierre.

---

## VERIFIABLE-MINOR-PUBLIC-IDENTITY-1 — Autorización verificable de menores

7D.2C2A añade cobertura Feature sobre MariaDB para constraints, máquina de
estados, unicidad efectiva, hash y uso único del token, expiración, reenvío y
rate limits. Verifica por separado confirmación o rechazo del representante,
revisión administrativa, conformidad de 14–17 años, vínculo inequívoco con
`Player`, fallo de correo no bloqueante, historial sanitizado y revocación
inmediata. La proyección pública se comprueba en partidos, calendarios,
clasificaciones y rankings con `alias`, `name_initial`, ausencia de alias,
datos insuficientes y transición a mayoría de edad, sin ampliar las allowlists.

Vitest cubre la separación entre privacidad, inscripción e identidad, opción
anónima inicial, modos, versión del aviso, validación accesible, confirmación y
rechazo, token inválido, error remoto, foco, doble envío y ausencia de
persistencia local. El pipeline legal prueba por separado las tres páginas
públicas y el único aviso de formulario permitido, con descubrimiento cerrado,
validación de metadatos y artefactos deterministas.

Playwright usa únicamente datos ficticios de `E2ESmokeSeeder` bajo su guard
`e2e`: cubre inscripción independiente, fallo controlado de correo,
confirmación de representante, revisión de menor de 14, conformidad de 14–17,
alias, inicial, denegación, caducidad, uso único y revocación. También comprueba
privacidad recursiva de la API, Contacto oculto con configuración desactivada,
Legal y Manual, `noindex`, 320 px y ausencia de recursos remotos. La fixture de
identidad usa participantes deportivos propios para no alterar `Mi Panel` ni
los rankings del smoke histórico.

Validación final de 7D.2C2A, 2026-08-06:

- backend focalizado: 39 tests y 376 aserciones;
- backend completo: 401 tests y 3.126 aserciones;
- frontend focalizado: 60 tests en tres archivos;
- frontend completo: 429 tests en 64 archivos;
- E2E completo: 52 escenarios Chromium;
- calidad: ESLint, Pint y `php -l` correctos;
- legal: tres páginas, un aviso, 49.772 bytes públicos y 8.482 bytes de avisos;
- Knowledge: 40 documentos, cuatro colecciones y hashes sin cambios
  (`66dfba8b620539b148539a8181e7f196b1abcc1a77e86efc737714983035d182`
  canónico y
  `4e5f28fd21d29291cddba2fede70ef7e057e45dbc5bb7583399186133911517e`
  público);
- estatutos históricos: hash sin cambios
  `17314902d717fa94a3b4016dcd5d6bed7ee6a94a233a314b58fb7ce83d56eadc`;
- build temporal: 221 módulos, chunk de confirmación de 4,78 kB, School de
  25,39 kB, Knowledge de 282,13 kB e inicial de 413,63 kB, sin warnings;
- residuos: el `outDir` temporal y la pila Docker aislada se eliminan; no se
  modifica `frontend/dist` ni se ejecuta contra la base de desarrollo.

La retención de tres años para evidencia y la purga técnica a treinta días se
documentan y no se automatizan en este bloque. Los jobs productivos permanecen
como gate posterior.

## CONTACT-OPERATION-PRIVACY-LAYER-1 — Operación de Contacto

7D.2C2B añade Feature tests sobre MariaDB para migración incremental, casts,
legado sin versión inventada, aviso compilado, configuración fail-closed,
consentimiento exacto, HMAC, honeypot, rate limit y respuestas 201/422/429/503.
La notificación cubre persistencia previa, estados, From controlado, Reply-To,
CRLF, fallo sanitizado, reintento manual, actor e intentos limitados.

La administración cubre permisos, filtros, lectura, cierre con 12 meses,
historial, hold y liberación, confirmación de anonimización y ausencia de
edición/reapertura. Los comandos se prueban en modo `--dry-run`, ejecución e
idempotencia, excluyendo solicitudes abiertas y holds, eliminando PII y HMAC a
30 días sin afectar otras verticales.

Vitest prueba los dos avisos allowlisted, determinismo, config exacta, primera
capa, versión, enlace de Privacidad, casilla no premarcada, 201/422/429/503/red,
foco, doble envío, valores corregibles, CMS independiente y ausencia de
storage. Playwright usa sólo el stack E2E aislado con mailer y direcciones
ficticias y conserva las regresiones de Legal, Manual, 320 px y recursos
remotos.

Validación final de 7D.2C2B, 2026-08-07:

- backend focalizado: 34 tests y 212 aserciones;
- backend completo: 415 tests y 3.256 aserciones;
- frontend focalizado: 46 tests en cuatro archivos;
- frontend completo: 434 tests en 64 archivos;
- E2E completo: 53 escenarios Chromium, incluido el recorrido Contacto →
  persistencia → Blade → cierre;
- legal: tres páginas, dos avisos, 49.772 bytes públicos y 13.209 bytes de
  avisos;
- Knowledge: 40 documentos y cuatro colecciones, con hashes canónico y público
  sin cambios;
- estatutos históricos: hash sin cambios;
- build temporal: 221 módulos, sin warnings y sin escribir `frontend/dist`;
- calidad: ESLint, Pint, `php -l`, `legal:check`, `knowledge:check` y
  `git diff --check` correctos.

---

## PUBLIC-SEO-ACCESSIBILITY-1 — SEO e interacción transversal

Fase 7D.3 añade tests unitarios para configuración fail-closed, normalización
de URL, seis clases de ruta, aliases y targets, metadata, limpieza de tags,
canonical, robots, Open Graph, JSON-LD, sitemap y representación exacta de
Knowledge, Legal y Club. `npm run seo:check` repite las invariantes críticas
sin navegador ni red y forma parte del build.

Playwright levanta dos servidores Vite dentro del runner aislado: uno usa el
origen reservado `https://example.test` con indexación activa y otro conserva
el modo noindex. La cobertura recorre rutas canónicas, aliases, CMS genérico,
deporte dinámico, auth, token y 404; verifica un main/H1/canonical, privacidad
de metadata, robots/sitemap, skip link, foco SPA, announcer, disclosures,
teclado, formularios, tablas, recursos remotos, viewports 320–1600 y reflow al
200 %. `/glosario` se prueba como 404 porque no existe en el router real.

No se incorpora axe ni se modifica el lock: la auditoría automática integral,
contraste perceptual y matriz multibrowser siguen como mejora y gate humano.
El backend no cambia en este bloque; la regresión E2E sobre MariaDB cubre su
integración pública sin justificar otra suite Laravel completa.

Validación final de 7D.3, 2026-08-09:

- focalizados SEO/accesibilidad: 94 tests en 6 archivos;
- frontend completo: 481 tests en 68 archivos;
- ESLint, `seo:check`, `legal:check` y `knowledge:check`: correctos;
- builds temporales fail-closed e indexable: 228 módulos, sin warnings;
- E2E completo: 61 tests en 7 archivos, incluidos 8 nuevos de 7D.3, ejecutados
  sobre el stack Docker/MariaDB aislado en 2,5 minutos;
- cleanup: contenedores y red E2E eliminados por el runner.

Con este gate, 7D.3 y 7D quedan cerradas. No se ejecutó una suite backend
separada porque no cambian PHP, API o contrato backend.

---

## SCHOOL-OPERATIONAL-READINESS-1 — Preparación operativa de Escuela

7E añade cobertura MariaDB para apertura `open|closed|unavailable`, flag
desactivada por defecto, configuración completa, bloqueo administrativo,
contenido administrable y ausencia de contacto privado en el agregado. El POST
cubre aviso exacto, teléfono, payload cerrado, honeypot, rate limit, menor,
adulto, nivel, cierre concurrente y persistencia previa a cualquier flujo
opcional de identidad.

La administración prueba actores y fechas de corrección, aprobación, rechazo y
baja; seis meses para pendientes/rechazadas, dos años tras baja; holds,
`--dry-run`, anonimización e idempotencia. Centros y actividades mantienen una
relación centro–múltiples actividades sin identidades individuales. La lectura
pública conserva allowlists, orden y número constante de consultas.

Vitest/RTL cubre estados, contenido, aviso, menor/adulto, validación, doble
envío, foco, ausencia de storage y contratos HTTP. Playwright añade dos
escenarios de Escuela y recorre cierre, apertura E2E, privacidad, identidad
opcional, trazabilidad, colegios, 320 px, zoom/reflow, teclado, Legal,
Knowledge, SEO y ausencia de recursos remotos.

Validación final de 7E, 2026-08-09:

- backend focalizado: 78 tests y 549 aserciones;
- backend completo mediante runner oficial: 421 tests y 3.309 aserciones;
- frontend focalizado: 24 tests en tres archivos;
- frontend completo: 484 tests en 68 archivos;
- E2E de Escuela: 7 escenarios; E2E completo: 63 escenarios Chromium en 2,6
  minutos sobre el stack aislado;
- lint, Pint, `php -l`, `legal:check`, `knowledge:check`, `seo:check` y
  `git diff --check`: correctos;
- Legal: tres páginas, tres avisos, 49.772 bytes públicos y 19.140 bytes de
  avisos;
- Knowledge: 40 documentos y cuatro colecciones; hashes canónico
  `66dfba8b620539b148539a8181e7f196b1abcc1a77e86efc737714983035d182`
  y público
  `4e5f28fd21d29291cddba2fede70ef7e057e45dbc5bb7583399186133911517e`;
- estatutos: hash
  `17314902d717fa94a3b4016dcd5d6bed7ee6a94a233a314b58fb7ce83d56eadc`;
- build temporal: 228 módulos, School 21,87 kB (6,63 kB gzip), chunk inicial
  380,78 kB (110,77 kB gzip), sin escribir `frontend/dist`;
- cleanup: directorio de build, informes E2E, contenedores y red eliminados.

La revisión humana no se afirma realizada. Los datos y horarios reales,
activación, correo, scheduler, backups, restore, staging y rollback permanecen
en 7F; Fase 7 y el MVP siguen abiertos.

---

## PRODUCTION-READINESS-1 — Preparación productiva sin despliegue

7F.1 añade pruebas MariaDB del health público mínimo, CORS exacto para el
frontend Bearer, preflight backend válido e inválido, configuración de correo
condicionada y bootstrap administrativo seguro, robusto e idempotente. El
contrato de plataforma comprueba Railway, ausencia de migraciones durante el
arranque, cachés, endurecimiento PHP y buffers FastCGI compatibles con los
redirects de validación Blade.

Vitest valida URLs HTTPS, dominio/API canónicos, separación de staging,
indexación inicial cerrada, rechazo de localhost/placeholders y la estructura
Vercel —build, `dist`, fallback SPA, redirect `www`, headers y ausencia de
HSTS prematuro—. E2E revalida el stack con su origen CORS explícito y conserva
los 63 recorridos funcionales.

Validación final de 7F.1, 2026-08-10:

- backend focalizado: 10 tests y 70 aserciones;
- backend completo mediante runner oficial: 431 tests y 3.379 aserciones;
- frontend focalizado de despliegue: 9 tests en dos archivos;
- frontend completo: 493 tests en 70 archivos;
- E2E completo: 63 escenarios Chromium en 2,6 minutos sobre el stack aislado;
- lint, Pint, `php -l`, cachés de configuración/rutas/vistas, `legal:check`,
  `knowledge:check`, `seo:check` y preflights frontend: correctos;
- builds normal y production-like noindex: 228 módulos, sólo `robots.txt`
  bloqueante y sin escribir `frontend/dist`;
- imagen Railway: build correcto, puerto dinámico, caches de arranque y `/up`
  `200` mínimo sin DB ni `X-Powered-By`;
- Knowledge: 40 documentos y cuatro colecciones; hashes canónico
  `66dfba8b620539b148539a8181e7f196b1abcc1a77e86efc737714983035d182`
  y público
  `4e5f28fd21d29291cddba2fede70ef7e057e45dbc5bb7583399186133911517e`;
- estatutos: hash
  `17314902d717fa94a3b4016dcd5d6bed7ee6a94a233a314b58fb7ce83d56eadc`;
- cleanup: builds, informes E2E, contenedores, redes e imagen de verificación
  eliminados.

La primera ejecución E2E detectó el origen temporal ausente de la nueva
allowlist CORS; tras declararlo, los logs de una segunda ejecución aislaron un
`upstream sent too big header` al devolver validación Blade con sesión-cookie.
El buffer FastCGI se fijó en 16 KiB y la ejecución completa posterior pasó
63/63. No se desplegó ni se conectó ningún servicio. 7F, 7G, Fase 7 y el MVP
siguen abiertos.

## MEDIA-PERSISTENT-CORE-LOCAL-1 — Núcleo multimedia local

7F.2B.1 cubre el núcleo sin consumidores mediante imágenes pequeñas generadas
durante los tests, sin incorporar binarios al repositorio. La normalización
prueba JPEG, PNG y WebP; rechaza texto renombrado, contenido que afirma ser
JPEG pero no decodifica, SVG, GIF animado y AVIF; aplica límites de bytes,
dimensiones y píxeles, conserva ratio, evita upscale, corrige orientación EXIF,
elimina metadata y produce una salida redecodificable cuya extensión procede
del encoder.

Los tests de filesystem usan `Storage::fake('media_local')` o adapters
controlados. Cubren escritura privada, existencia, metadata, borrado, URL
temporal simulada sin presentarla como prueba S3, keys UUID con prefijos
cerrados, rechazo de path traversal y propagación saneada de fallos. El probe
acredita éxito y cleanup local, error genérico sin secrets y separación de la
capacidad de URL temporal.

Validación de 7F.2B.1, 2026-08-15:

- suite dirigida: 22 tests y 89 aserciones;
- backend completo mediante el runner MariaDB aislado: 459 tests y 3.574
  aserciones;
- `composer validate`, Pint, `php -l` y `git diff --check`: correctos;
- imagen de test y Dockerfile Railway construidos con GD y EXIF; `gd_info()`
  acredita JPEG, PNG y WebP en ambos;
- límites comprobados dentro de los contenedores: `upload_max_filesize=10M`,
  `post_max_size=12M` y `memory_limit=256M`;
- ningún bucket, endpoint, feature upload, frontend o base de desarrollo
  intervino en las pruebas.

Esta evidencia valida únicamente `media_local` y dobles de Flysystem. No
acredita `media_s3`, persistencia Railway, credenciales, presigned GET ni el
gate remoto: 7F.2B continúa abierta.

## SPONSORS-INSTITUTIONAL-PUBLIC-1 — Colaboradores administrables

7F.2C prueba casts, factory, estados y límites exactos de la ventana efectiva,
orden estable, permisos Blade, create/update/replace/destroy, compensación ante
fallo de MariaDB, cleanup fallido, imágenes inválidas, dimensiones, URL HTTPS y
fechas. La API verifica allowlist, vacío, exclusión temporal, 404 fail-closed,
503 saneado, `X-Accel-Redirect` local y redirect S3 simulado.

Vitest cubre contrato cerrado, colección inválida, estados remotos sin ruido,
varios logos, orden recibido, dimensiones, alt, enlace `sponsored`, ausencia
total de franja y exclusión en cuenta/token/404. Playwright genera PNG pequeños
en memoria y recorre dos altas, orden, desactivación, reactivación, reemplazo,
atributos del enlace, 320 px, borrado y 404 del objeto eliminado. El volumen
multimedia E2E es efímero, compartido sólo entre PHP-FPM y Nginx y se elimina
con `safe-compose-down`; no interviene staging.

Validación dirigida inicial de 7F.2C, 2026-08-16:

- backend MariaDB dirigido: 23 tests y 163 aserciones;
- backend completo: 476 tests y 3.700 aserciones;
- frontend: 65 tests en cuatro archivos;
- frontend completo: 529 tests en 76 archivos;
- E2E Chromium: un recorrido integral verde;
- E2E completo: 64 escenarios Chromium en 2,7 minutos;
- lint y Pint: correctos.

Esta evidencia no sustituye la creación, reemplazo, redeploy y cleanup reales
con `media-staging`. 7F.2C permanece abierta hasta esa aceptación manual.

Seguimiento manual real de 7F.2C en staging, 2026-08-16:

- validados migración, alta administrativa, objeto real, render frontend,
  múltiples colaboradores, orden, reemplazo con cleanup y
  desactivación/reactivación;
- no ejecutados todavía: ventanas temporales, persistencia tras redeploy,
  borrado con cleanup y revisión móvil/accesible.

El bloque continúa abierto; la evidencia parcial no se presenta como
aceptación completa.

## PRIVATE-USER-PROFILE-PHOTO-1 — Foto privada de Usuario

7F.2D cubre la eliminación de la exposición latente en login, `/me`, perfil
propio y Resources deportivos; `profile_photo_path`, `avatars/` y las keys no
aparecen en respuestas. Las pruebas API verifican usuario anónimo/inactivo,
cuenta sin `Player`, upload, sustitución, borrado idempotente, `/me`, lectura
local y streaming S3 simulado, keys legacy, objeto ausente, fallos saneados,
validación y rate limit sin penalizar el GET.

Las pruebas de lifecycle fuerzan fallo de DB, cleanup nuevo/anterior, warning
post-commit, referencias obsoletas replace/replace y replace/delete, borrado
administrativo de `User` y conservación al borrar sólo `Player`, sin `sleep`.
La normalización JPEG/PNG/WebP, ratio, orientación, metadata y límites se
reutiliza de `MEDIA-PERSISTENT-CORE-LOCAL-1`.

Vitest cubre contrato cerrado, FormData sin boundary manual, blob autenticado,
fallback/iniciales, preview, upload/replace/delete, revocación de object URLs,
errores 422/429/503, imagen rota, actualización de contexto y ausencia de
persistencia del blob. Playwright recorre login, fallback, upload, reemplazo,
borrado, 320 px, consola y ausencia de keys en `/me` y APIs públicas sobre el
storage E2E efímero.

Validación local de 7F.2D, 2026-08-16:

- regresión previa de exposición: 24 tests y 263 aserciones;
- lifecycle/API dirigido: 18 tests y 151 aserciones;
- backend completo MariaDB: 496 tests y 3.885 aserciones;
- frontend dirigido inicial: 32 tests en cinco archivos;
- frontend completo: 550 tests en 79 archivos;
- E2E dirigido: un escenario Chromium verde;
- E2E completo: 65 escenarios Chromium en 2,7 minutos.

El hotfix de serving privado posterior añade cobertura de `readStream`, cierre
del recurso, `200` binario S3 sin `Location` ni llamada a `temporaryUrl`, fallos
de metadata/lectura saneados y regresiones que mantienen los redirects S3 de
Sponsor público y de su preview Blade. Resultados locales del hotfix:

- backend dirigido: 69 tests y 630 aserciones;
- backend completo sobre MariaDB aislada: 501 tests y 3.942 aserciones;
- frontend dirigido: 20 tests en tres archivos;
- frontend completo: 550 tests en 79 archivos;
- E2E dirigido: un escenario Chromium verde;
- E2E completo: 65 escenarios Chromium en 2,9 minutos;
- Composer estricto, Pint dirigido, `php -l`, ESLint, build Vite e imagen
  Docker de producción: correctos.

La ejecución local no acredita referencias legacy reales, serving S3 tras el
nuevo despliegue, persistencia tras redeploy ni cleanup remoto. El CORS exacto
GET/HEAD del bucket ya fue comprobado, pero la lectura del avatar deja de
depender de él porque el navegador recibe el binario desde la API. 7F.2D
continúa abierta hasta repetir sus gates en staging.

## NEWS-EDITORIAL-PUBLIC-1 — Fase 7F.2E

La cobertura backend comprueba modelo, factory, estados derivados, scope
efectivo, orden, reserva del slug tras soft delete, administración y permisos,
validación de texto/imagen/derechos, publicación inmediata y programada,
inmutabilidad histórica, locks, compensación y cleanup. La API cubre paginación
de 12, páginas fuera de rango, allowlists, 404 indistinguible, serving local/S3
y regresiones de Sponsor, avatar y CMS.

Vitest cubre contrato estricto, URLs seguras, Axios/AbortSignal, protección
frente a respuestas obsoletas, append/dedupe, estados y retry, noticia
destacada cronológica, detalle escapado, fallbacks, Navbar, metadata article,
canonical, Open Graph, JSON-LD, cleanup y noindex. Playwright crea contenido
sólo en el entorno efímero: borrador invisible, dos publicaciones ordenadas,
programación, detalle, reemplazo/cleanup, eliminación/404, 320 px, teclado,
privacidad y SEO.

Validación local final de 7F.2E, 2026-08-21:

- backend dirigido de dominio/administración/lifecycle: 30 tests y 106
  aserciones;
- backend dirigido API/media/regresiones: 34 tests y 318 aserciones;
- backend completo sobre MariaDB aislada: 526 tests y 4.103 aserciones;
- frontend dirigido de Noticias/navegación/SEO: 147 tests en 14 archivos;
- frontend completo: 601 tests en 86 archivos;
- E2E dirigido de Noticias: un escenario Chromium verde;
- E2E completo: 66 escenarios Chromium en 2,8 minutos;
- Composer estricto, Pint dirigido, 30 comprobaciones `php -l`, ESLint,
  Legal, Knowledge, SEO, build Vite e imagen Docker de producción: correctos.

La ejecución local no acreditaba migración, object storage S3, persistencia tras
redeploy, cleanup remoto, metadata sobre dominio real ni aprobación editorial
de imágenes. Posteriormente, la migración y el flujo técnico/funcional principal
se aceptaron manualmente en staging; los gates secundarios diferidos conservan
su seguimiento propio.

## CMS-NAVIGATION-CONTROLLED-SLOT-1 — Fase 7F.2F

La cobertura backend valida relación, casts, enum, defaults fail-closed,
factory, orden estable, constraint página/slot y cascade; administración de
administrador activo, inactivo, usuario y anónimo; altas activas/inactivas,
edición, borrado sin borrar página, selector, payloads no escalares, etiquetas,
orden, slot, páginas reservadas y duplicados. La API comprueba publicación
efectiva, borrador/futuro/inactivo, republicación, defensa reservada, etiqueta
inválida, URL derivada, allowlist, vacío, orden y dos consultas constantes con
eager loading.

Vitest cubre contrato cerrado y omisión fail-closed, URLs internas, slugs
reservados, duplicados, servicio Axios con AbortSignal, hook sin retries,
composición inmutable structural-first y Navbar desktop/móvil, activo y
`aria-current`. El E2E desechable crea una página y bloque desde Blade,
publica, asigna y activa, recorre la URL, alterna placement y estado editorial,
rechaza un reserved manipulado, prueba 320 px/Escape/foco, elimina el placement
y devuelve la página temporal a borrador. No usa seeder editorial permanente.

Validación local final de 7F.2F, 2026-08-22:

- backend dirigido de modelo, administración, API y regresiones CMS/Club/News:
  45 tests y 245 aserciones;
- backend completo sobre MariaDB aislada: 545 tests y 4.188 aserciones;
- frontend dirigido de contrato, servicio, hook, composición y Navbar: 83
  tests;
- frontend completo: 632 tests en 89 archivos;
- E2E dirigido CMS Navigation: un escenario Chromium verde;
- regresión E2E legal ajustada al nuevo endpoint propio: 6 escenarios verdes;
- E2E completo: 67 escenarios Chromium en 2,9 minutos;
- Composer estricto, Pint sobre 21 PHP modificados, 21 comprobaciones `php -l`,
  ESLint, Legal, Knowledge, SEO, build Vite e imagen Docker de producción:
  correctos.

Estado: 7F.2F implementada y validada localmente; pendiente de aceptación en
staging.

# 11. Evolución

La cobertura de pruebas debe crecer junto con el proyecto.

Las nuevas funcionalidades relevantes deberían incorporar pruebas desde su primera implementación.

La evolución prevista incluye extender la cobertura funcional del módulo CMS conforme crezca, validando:
- gestión administrativa de contenidos;
- validaciones de seguridad de archivos adjuntos;
- prevención de spam en formularios públicos.

También quedan como evolución posterior una métrica porcentual de cobertura frontend y una matriz E2E con navegadores adicionales. Ninguna de las dos bloquea el candidato MVP actual.

---

## Mantenimiento

Cuando cambie la estrategia de pruebas, el entorno Docker o el proceso de validación, este documento deberá actualizarse.
