# Roadmap — Galotxas

## Propósito

Este documento representa el estado y el orden oficial de evolución del proyecto. Es dinámico: no sustituye la documentación funcional ni conserva el detalle de cada implementación ya cerrada.

---

# Programa de arquitectura pública y contenidos

La arquitectura híbrida y la nueva organización pública se desarrollarán mediante bloques separados. Una decisión documental no pasa a “Completado” hasta que su implementación y validación existan.

## Fase 0 — Gobernanza documental completada

Este bloque formalizó fuentes de verdad, responsabilidades editoriales, arquitectura pública objetivo, reglas para agentes y criterios de seguridad, multimedia y testing. No creó rutas, componentes, endpoints, modelos, migraciones, pantallas Blade, compiladores ni nuevas colecciones en `knowledge/`.

## Bloques completados del programa

1. **Fase 1 — Auditoría del CMS, panel Blade, rutas y API:** capacidades, permisos, contenido, duplicados, consumidores y cobertura reales inventariados.
2. **Fase 2A — Endurecimiento editorial del CMS:** creación obligatoria en borrador, contenido mínimo publicable, publicación inmediata o programada, estado derivado, protección del último bloque, feedback y cobertura dirigidos.
3. **Fase 2B.1 — Integridad administrativa de Temporadas:** formularios Blade, enum casteado, fechas nullable, validación cronológica, persistencia explícita, permisos y regresión pública verificados.
4. **Fase 2B.2 — Integridad administrativa de Campeonatos:** contrato Blade completo para campos no multimedia, validación, persistencia explícita, conservación de `image_path`, permisos y regresión pública verificados.
5. **Fase 2B.3 — Integridad administrativa de Categorías:** contrato Blade completo para campos no multimedia, relación inmutable con campeonato, valores nullable y controlados, conservación de `image_path` y regresiones deportivas y públicas verificados.
6. **Fase 2B.4A — Visibilidad explícita de la competición:** `is_public` administrable en temporadas, campeonatos y categorías, backfill compatible, jerarquía sin cascada y contrato público temporalmente inalterado.
7. **Fase 2B.4B — Aplicación pública de visibilidad:** scopes locales y filtro jerárquico en listados, detalles, relaciones, partidos, rankings, standings, schedules e inicio de inscripciones, preservando administración y Mi Panel.
8. **Fase 2B.5 — Endurecimiento de la API administrativa:** CRUD de temporadas, campeonatos y categorías protegido por administrador activo, Form Requests, persistencia explícita, Resources propios, `is_public` jerárquico y campos no administrables aislados.

La Fase 2B queda completa con los subbloques 2B.1–2B.5. Este cierre no inicia la Fase 3 ni modifica la navegación pública.

## Siguientes bloques aprobados

1. **Reestructuración de navegación:** implantar Inicio, Competición, Aprende a jugar, Escuela de Galotxas y Club, conservando una migración compatible.
2. **Landing Competición:** agrupar Torneos, Rankings, Calendarios, Clasificaciones, Resultados y accesos de jugadores sobre contratos verificados.
3. **Landing Aprende a jugar:** crear la entrada divulgativa diferenciada del Manual.
4. **Contrato editorial de `knowledge/`:** normalizar metadatos, IDs, slugs, relaciones y validaciones de las colecciones aprobadas.
5. **Compilador build-time:** validar `knowledge/` y generar artefactos seguros y deterministas para React, sin MDX ni HTML ejecutable.
6. **Manual MVP:** construir la experiencia pública desde los artefactos generados, sin base de datos, API Laravel o CRUD Blade.
7. **Escuela de Galotxas:** combinar contenido pedagógico estable con actividad operativa administrable y protección específica de menores.
8. **Club y migración de Contenidos:** asignar una fuente canónica a cada página institucional y retirar gradualmente la arquitectura legada.
9. **QA, accesibilidad y despliegue:** validar contratos, recorridos, responsive, teclado, multimedia, persistencia y operación.

Estos bloques permanecen pendientes. El primero corresponde a la evolución posterior a la Fase 2B; las rutas conceptuales y demás capacidades descritas no están implementadas por aparecer en el roadmap.

Este programa no altera por sí solo el proceso operativo de revisión y publicación del candidato descrito más abajo. Antes de iniciar un bloque funcional debe reconciliarse su calendario con el candidato y con cualquier corrección P0/P1.

---

# Estado del MVP

El núcleo funcional previsto para el MVP está implementado. QA-MVP-1, QA-FIX-1 y RC-HARDEN-1 están completados. MVP-RC-1 ha preparado y validado la documentación del primer candidato como `v0.1.0-rc.1`, pero todavía no se ha creado el commit definitivo, el tag ni la GitHub Release.

La clasificación actual es **RC preparado con limitaciones**: no se han detectado P0/P1 y la regresión está verde, pero el candidato requiere revisión humana y no equivale a un despliegue de producción. Las limitaciones aceptadas y los requisitos pendientes se detallan en `09-release-candidate.md`.

La ausencia de una interfaz React de reprogramación no bloquea este cierre. El backend conserva ese workflow y su interfaz queda expresamente pospuesta.

---

# Completado

## Base de plataforma y seguridad

- monorepo React + Laravel + panel Blade;
- MariaDB como único motor soportado;
- Docker para desarrollo, integración y E2E;
- registro, login, logout y recuperación/restablecimiento de contraseña;
- tokens Bearer Sanctum para React;
- usuarios activos en API y panel administrativo;
- rate limiting de autenticación y resultados;
- creación de perfil deportivo y edición parcial por API;
- administración de usuarios y jugadores.

## Competición y administración

- temporadas, campeonatos y categorías;
- integridad del CRUD Blade de temporadas para nombre, estado y fechas nullable, con validación cronológica y selección correcta del enum (Fase 2B.1);
- integridad del CRUD Blade de campeonatos para todos los campos no multimedia, con validación de enums e intervalos, persistencia explícita y conservación de `image_path` (Fase 2B.2);
- integridad del CRUD Blade de categorías para nombre, descripción, nivel nullable, género y estado, con relación inmutable al campeonato y conservación de `image_path` (Fase 2B.3);
- base administrativa de `is_public` para temporadas, campeonatos y categorías, con jerarquía explícita, nuevos registros privados y backfill de registros existentes (Fase 2B.4A);
- aplicación jerárquica de la visibilidad efectiva en toda la superficie pública de competición, con scopes locales, `404` seguro, agregados filtrados y preservación de Mi Panel y administración (Fase 2B.4B);
- API administrativa de temporadas, campeonatos y categorías endurecida con Form Requests, Resources administrativos, persistencia explícita, permisos de administrador activo y gestión jerárquica de `is_public` (Fase 2B.5);
- solicitudes de inscripción, aprobación/rechazo, pago manual y asignación administrativa;
- equipos y participantes competitivos de individuales y dobles;
- generación de liga, copa, final y tercer puesto;
- gestión Blade de pistas y seeder explícito no destructivo (VENUE-1);
- generación reproducible con pistas configuradas, capacidad controlada y rollback atómico (SCHEDULE-1);
- rankings de categoría, campeonato, temporada e histórico;
- desempates deterministas y porcentaje histórico en escala `0–100` (RANK-1);
- edición administrativa de partidos dentro de cada categoría;
- workflow backend de reprogramación entre participantes.

## Resultados y Mi Panel

- Mi Panel con perfil, inscripciones, partidos, calendario y rankings;
- detalle React unificado de partido y resultados (MATCH-1);
- contrato público/participante aislado mediante Resources mínimos (SEC-MATCH-1);
- reporte único e inmutable por lado, confirmación, conflicto y transacciones (MATCH-2);
- acciones pendientes `submit_result`, `confirm_result` y aviso `under_review` (PANEL-1);
- listado, detalle y resolución Blade de conflictos con trazabilidad (ADMIN-CONFLICT-1).

## CMS público

- páginas y bloques estructurados sin HTML libre (CMS-1);
- gestión Blade de páginas (CMS-2);
- gestión Blade de bloques (CMS-3);
- detalle público React (CMS-4);
- índice de contenidos publicados (CMS-5);
- navegación institucional y seeder no destructivo (CMS-6).
- flujo editorial endurecido con creación en borrador, contenido mínimo, programación derivada, protección del último bloque y feedback administrativo (CMS-EDITORIAL-1 / Fase 2A).

## Frontend, despliegue y calidad

- URL API por `VITE_API_BASE_URL`, fallback local de desarrollo y `/api/v1` en producción (DEPLOY-1);
- Vitest, React Testing Library y 65 tests críticos en 18 archivos, incluidos fechas incompletas, formularios accesibles, sesión inválida, calendario, navbar responsive y CTA (FE-TEST-1, QA-FIX-1 y RC-HARDEN-1);
- smoke Playwright de 9 escenarios con Chromium y stack temporal aislado, incluidos CTA, calendario, formularios, ruta real de torneos y navegación móvil pública y administrativa (E2E-1, QA-FIX-1 y RC-HARDEN-1);
- auditoría y actualización compatible de npm/Composer sin vulnerabilidades conocidas pendientes en la instantánea de cierre (DEPS-1);
- documentación técnica 00–08 reconciliada con el código (DOC-1);
- corrección de los bloqueantes QA del calendario público y de la navegación responsive, con revalidación dirigida en 1440 × 900, 1280 × 720 y 390 × 844 (QA-FIX-1).
- endurecimiento menor previo al candidato con 168 tests Laravel, 1.088 aserciones y validaciones frontend/E2E ampliadas (RC-HARDEN-1).
- inventario, instalación limpia, regresión, auditoría, notas de versión y runbook del candidato preparados sin publicar ni etiquetar (MVP-RC-1).

---

# Siguiente paso del candidato

MVP-RC-1 está preparado, pero no publicado. El orden siguiente es:

1. revisión humana del alcance, `CHANGELOG.md`, limitaciones y runbook;
2. commit único del bloque en `develop` y push;
3. fast-forward de `main` y push;
4. creación del tag anotado `v0.1.0-rc.1` desde el commit aprobado;
5. publicación de GitHub Release como prerelease;
6. validación del tag en un segundo entorno y apertura del periodo de prueba definido por el usuario.

No debe intercalarse una fase funcional nueva antes de etiquetar salvo que aparezca un defecto P0/P1 o un impedimento de reproducibilidad.

---

# Post-MVP funcional

Estas capacidades son válidas, pero no bloquean el candidato actual:

- interfaz React para solicitar y confirmar reprogramaciones;
- edición completa del perfil desde React;
- pagos online;
- notificaciones;
- sugerencia o asignación automática de categoría;
- noticias como entidad editorial propia;
- subida segura y gestión de documentos e imágenes;
- formularios públicos de federación o Escuela de Galotxas con antispam;
- SEO y ordenación editorial avanzados del CMS;
- métricas y filtros administrativos avanzados;
- aplicación móvil y API administrativa consolidada.

---

# Deuda técnica conocida

## API y seguridad

- estudiar la migración de Bearer en `localStorage` a cookies `HttpOnly`/`SameSite` con CSRF;
- normalizar envelopes, errores, paginación y serialización heredada;
- resolver mediante una decisión versionada el `slug` nulo que `SeasonResource` conserva aunque `Season` no disponga de ese atributo;
- documentar el contrato mediante OpenAPI;
- separar o reducir los usos amplios de `MatchResource` en “mis partidos”, calendario, reprogramación y administración;
- endurecer reprogramaciones: Form Request dedicado, rate limiting y política explícita de rectificación;
- definir una rectificación administrativa trazable de reportes de resultado del participante;
- persistir un motivo administrativo de resolución de conflicto si el producto lo requiere.

## Competición y datos

- coordinar disponibilidad de pistas entre categorías distintas;
- proteger generaciones concurrentes con una estrategia de bloqueo;
- trasladar la unicidad del nombre de pista, hoy validada en formularios, a una restricción de base de datos;
- modelar actividad/elegibilidad de pistas y restricciones por modalidad o nivel cuando exista ese requisito.

## Mantenibilidad

- dividir `frontend/src/pages/Dashboard.jsx` por responsabilidades;
- reducir responsabilidades del `Api\V1\MatchController`;
- limpiar rutas/componentes heredados y duplicados sin alterar el contrato;
- retirar adaptadores de compatibilidad cuando sus consumidores hayan migrado;
- mantener auditorías periódicas de npm y Composer.

## Calidad

- decidir si aporta valor una métrica porcentual de cobertura frontend;
- ampliar E2E a navegadores adicionales cuando el riesgo de compatibilidad lo justifique;
- extender el smoke más allá del relato crítico sin convertirlo en sustituto de Feature tests.

---

# Fuera del alcance de este cierre

DOC-1, QA-MVP-1 y MVP-RC-1 no autorizan por sí solos:

- nuevas reglas deportivas;
- cambios globales del contrato API;
- migraciones de autenticación;
- nuevas entidades CMS avanzadas;
- refactors estructurales amplios;
- publicación automática del producto.

---

# Criterio de mantenimiento

Una capacidad solo pasa a “Completado” cuando existen implementación, validación razonable y documentación coherente. Las cifras de tests se mantienen como instantáneas fechadas en `05-testing.md`, no como objetivos inmutables del roadmap.
