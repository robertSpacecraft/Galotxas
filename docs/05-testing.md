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

Suite backend:

`docker compose -f backend/docker/docker-compose.yml --profile test run --rm test`

Comprobación de sintaxis:

`php -l`

Comprobación de espacios y conflictos:

`git diff --check`

Auditoría frontend completa y de producción:

`cd frontend && npm audit`

`cd frontend && npm audit --omit=dev`

Validación y auditoría backend dentro del contenedor oficial:

`docker compose -f backend/docker/docker-compose.yml exec app composer validate --strict`

`docker compose -f backend/docker/docker-compose.yml exec app composer audit`

`docker compose -f backend/docker/docker-compose.yml exec app composer audit --no-dev`

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

Las pruebas de integración se ejecutan exclusivamente sobre la instancia MariaDB aislada del perfil `test` de Docker.

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

La utilidad `renderWithProviders` ofrece únicamente `MemoryRouter`, rutas de prueba y `AuthContext` opcional. Los tests no dependen de nombres de clases CSS, no usan snapshots masivos y no hacen peticiones reales.

Limitaciones deliberadas:

- no existe cobertura global ni se fija un porcentaje objetivo;
- no se prueban estilos pixel a pixel;
- no se inicia Laravel ni MariaDB desde Vitest;
- Vitest no sustituye los tests Feature ni el smoke E2E;
- el navegador real se cubre en una suite Playwright separada.

## E2E-1 — Smoke del MVP

La suite de nueve escenarios cubre:

- navegación pública e índice/detalle CMS;
- CTA del Hero hacia el listado real de torneos;
- calendario de categoría con dos jornadas, partidos y navegación al detalle;
- menú público a 390 × 844, cierre al navegar a Rankings y Contenidos, y ausencia de overflow horizontal;
- login real y acciones pendientes de Mi Panel;
- envío y confirmación coincidente de un resultado;
- discrepancia visible y bloqueada para los jugadores;
- resolución del conflicto desde el panel Blade;
- resultado oficial y ranking histórico sin porcentaje incorrecto ni `NaN`.

Las comprobaciones incorporadas por RC-HARDEN-1 reutilizan esta suite y verifican también la expansión del perfil de jugador, las etiquetas del formulario de recuperación, el listado real de `/torneos` y el estado accesible del control móvil del panel. El script de ejecución conserva los artefactos de Playwright cuando el resultado no es correcto y los limpia tras un cierre satisfactorio.

Limitaciones deliberadas del smoke:

- solo Chromium, con viewport de escritorio y un escenario móvil dirigido;
- ejecución serial sobre un único relato y datos sembrados;
- no cubre dobles, porque ese flujo permanece en tests Feature;
- no valida apariencia pixel a pixel, accesibilidad completa ni todos los formularios administrativos;
- no sustituye la QA funcional, visual y responsive de QA-MVP-1.

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
- exclusión de borradores y páginas futuras del listado público;
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
- creación de página CMS desde panel admin;
- edición de página CMS desde panel admin;
- validación de unicidad de `slug`;
- conservación del propio `slug` durante edición;
- protección de acceso frente a usuarios no administradores.
- visualización de bloques CMS de una página;
- creación, edición y eliminación de bloques CMS;
- ordenación de bloques por `sort_order`;
- validación de tipo de bloque;
- validación de datos mínimos según tipo de bloque;
- protección frente a edición de bloques desde una página ajena;
- comprobación de que los bloques creados desde admin salen por el endpoint público.

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


# 10. Evolución

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
