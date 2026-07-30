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
9. **Fase 3A — Contrato de navegación y rutas públicas:** router, enlaces, CMS, API, `knowledge/`, compatibilidad, accesibilidad, responsive y SEO auditados; cinco rutas canónicas y sus gates documentados sin cambiar React.
10. **Fase 3B — Navegación pública funcional:** configuración editorial única con Inicio y Competición, cuenta separada, estado activo de toda la rama deportiva, menú accesible y responsive, landing mínima `/competicion`, fallback 404 React y rutas existentes preservadas.
11. **Fase 3C — Sistema común de landings públicas:** contenedor, cabecera, acciones, secciones, destinos y metadatos básicos reutilizables, aplicados a `/competicion` y de forma acotada a 404 con semántica, teclado y responsive validados.
12. **Fase 4A — Landing dinámica de Competición:** una única carga de temporadas con sus campeonatos públicos desde la API, jerarquía accesible, enlaces de detalle, estados loading/error/retry/vacío y matriz responsive validados sin modificar backend.
13. **Fase 4B — Rankings y navegación contextual en Competición:** preview independiente de las primeras cinco filas del ranking histórico en orden backend, enlace al ranking completo y accesos de categoría a detalle, clasificación y calendario mediante las rutas existentes.
14. **Fase 4C — Cierre de la experiencia pública de Competición:** composición final, navegación contextual determinista, estados remotos recuperables, jerarquía y etiquetas coherentes, tablas accesibles y recorrido completo hasta partido y rankings sin ampliar backend.
15. **Fase 5A — Contrato y compilador de Knowledge:** auditoría de las colecciones reales, contrato de seis metadatos, validación de IDs, slugs, referencias y seguridad, compilador determinista y artefacto JSON versionado sin rutas públicas.
16. **Fase 5A.1 — Preparación del corpus publicable:** aprobación editorial de REG-001–REG-008, 40 documentos `Vigente`, un único H1 y jerarquía coherente por documento, y referencias vigentes restringidas a destinos vigentes.
17. **Fase 5B — Consumo público seguro de Knowledge:** proyección pública sin borradores ni Markdown, parser build-time limitado, renderer semántico, repositorio frontend, landing `/aprende-a-jugar`, Manual, documentos, referencias, Navbar y 404 validados.
18. **Fase 5C — Cierre de Aprende a jugar y el Manual:** contexto local, índice por headings compilados, navegación anterior/siguiente dentro de colección, deep links estables y carga diferida de toda la rama con regresión completa.
19. **Fase 6A — Auditoría y contrato de Escuela de Galotxas:** backend, frontend, CMS, `academy`, inscripciones deportivas y `knowledge/` auditados; fuentes, MVP, dominio provisional, privacidad y planes de implementación documentados sin publicar la sección.
20. **Fase 6A.1 — Cierre funcional de Escuela de Galotxas:** Escuela permanente, niveles, horarios semanales, ubicación escolar, inscripción pública sin cuenta obligatoria, ciclo de estados, centros y actividades definidos como contrato documental sin implementar código.
21. **Fase 6B.1 — Núcleo operativo de Escuela:** `SchoolProgram`, `SchoolLevel`, `SchoolLocation` y `SchoolSchedule`, integridad relacional, visibilidad efectiva, administración Blade, permisos y tests sobre MariaDB, sin API o frontend públicos.
22. **Fase 6B.2 — Inscripciones de Escuela:** `SchoolEnrollment`, enum y ciclo de estados, solicitud pública anónima con cuenta opcional, menores y adultos, administración Blade, privacidad, rate limiting y cobertura MariaDB, sin lectura pública o frontend.
23. **Fase 6B.3 — Centros y actividades educativas:** `EducationalCenter`, `EducationalActivity`, enum y transiciones definitivas, ubicación opcional, administración Blade, borrado conservador, permisos y cobertura MariaDB, sin API pública o frontend.
24. **Fase 6B.4 — Lectura pública de Escuela:** `GET /api/v1/school`, consulta centralizada, Resources cerrados, visibilidad efectiva, orden estable, privacidad y cobertura MariaDB, sin frontend público.
25. **Fase 6C — Experiencia pública de Escuela:** `/escuela` diferida, agregado público, niveles, horarios, ubicaciones, contacto, apertura, inscripción de menores y adultos, Navbar/Home, accesibilidad, responsive y E2E.
26. **Fase 6C.1 — Remediación del aislamiento Docker:** proyectos, archivos, redes y bases separados para desarrollo, backend test y E2E; guardas de cleanup, prueba de no destrucción y revalidación completa de 6C sin reconstruir la base local.
27. **Fase 7A — Auditoría de paridad y plan del MVP completo:** backend, Blade, API, React, CMS, autogestión, contenido y despliegue inventariados; definición observable, prioridades, decisiones humanas y plan 7B–7G documentados sin implementar funciones.

La Fase 2B queda completa con los subbloques 2B.1–2B.5. Las fases 3A–3C, 4A–4C, 5A–5C y 6A–6C.1 completan respectivamente las fases 3, 4, 5 y 6. Fase 7A completa sólo la auditoría: Fase 7 sigue abierta. Redirects y migraciones editoriales no se han iniciado.

## Fase 7 abierta — bloques propuestos y no iniciados

1. **Fase 7B — Decisiones y preparación editorial:** aprobar navegación, URLs, compatibilidad, responsables, contenido institucional, legal, privacidad, imágenes, identidad pública y datos operativos.
2. **Fase 7C — Vertical institucional Club:** implementar Quiénes somos, Contacto, Federarse y Documentos desde CMS, conservando legados hasta acreditar paridad.
3. **Fase 7D — Navegación, Home, footer y legal:** aplicar la arquitectura aprobada, cuenta separada, menús accesibles, portada veraz y footer global.
4. **Fase 7E — Preparación operativa de Escuela:** cargar y validar configuración real, privacidad y procedimiento de solicitudes antes de abrir inscripciones.
5. **Fase 7F — Preparación de despliegue:** cerrar Railway/Vercel, MariaDB, variables, CORS, correo, sesiones, logs, backups, migraciones, salud y rollback.
6. **Fase 7G — Validación y cierre del MVP:** ejecutar regresión, recorridos críticos, QA, smoke y aceptación humana antes de tag/release.

Las fases 4, 5 y 6 están completadas. `/competicion` ofrece el recorrido deportivo; `/aprende-a-jugar` y el Manual presentan los 40 documentos desde Knowledge; `/escuela` consume la configuración pública y admite solicitudes anónimas cuando el backend las abre. El cierre de Escuela queda revalidado sobre proyectos Docker aislados y guardados; `/club` continúa sin implementar y no aparece como placeholder.

Después de 3C permanecen en bloques posteriores la consolidación institucional, la migración de Nosotros, aliases, redirects, canonical, indexación de `/contenidos`, SEO completo, sitemap y robots, limpieza de código huérfano y migración de `academy` y `documentos`. No forman parte de 3C ni del cierre de la Fase 4.

Este programa no altera por sí solo el proceso operativo de revisión y publicación del candidato descrito más abajo. Antes de iniciar un bloque funcional debe reconciliarse su calendario con el candidato y con cualquier corrección P0/P1.

---

# Estado del MVP completo

El núcleo técnico del candidato anterior está implementado y QA-MVP-1,
QA-FIX-1, RC-HARDEN-1 y MVP-RC-1 conservan su valor histórico. Sin embargo, la
auditoría 7A amplía el criterio desde “candidato técnico” a “aplicación pública
y funcionalmente completa”: el MVP completo **todavía no está completado**.

Permanecen P0 de contenido institucional, Contacto, Home/footer/legal,
configuración y privacidad de Escuela, despliegue Railway/Vercel/MariaDB y
validación de recorridos críticos. La definición observable, priorización y
criterios de cierre se encuentran en `14-mvp-parity-audit.md`.

La ausencia de edición avanzada de perfil, resumen directo de equipo e interfaz
React de reprogramación continúa como P1 y no bloquea por sí misma el MVP.

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
- Vitest, React Testing Library y 312 tests en 51 archivos, incluida Escuela pública, formularios, errores HTTP, edad, navegación, Knowledge, Competición, cuenta, foco, landmarks, 404 y regresiones previas (SCHOOL-PUBLIC-EXPERIENCE-1 y los bloques anteriores);
- smoke Playwright de 21 escenarios con Chromium y stack temporal aislado, incluidos los recorridos públicos completos de Competición, Aprende a jugar y Escuela, solicitudes de menor y adulto, referencias, tabla, 404, foco, zoom y matriz responsive 320–1440 px, además de los workflows anteriores;
- auditoría y actualización compatible de npm/Composer sin vulnerabilidades conocidas pendientes en la instantánea de cierre (DEPS-1);
- documentación técnica 00–08 reconciliada con el código (DOC-1);
- corrección de los bloqueantes QA del calendario público y de la navegación responsive, con revalidación dirigida en 1440 × 900, 1280 × 720 y 390 × 844 (QA-FIX-1).
- endurecimiento menor previo al candidato con 168 tests Laravel, 1.088 aserciones y validaciones frontend/E2E ampliadas (RC-HARDEN-1).
- navegación pública progresiva con configuración única, cuenta separada, landing mínima de Competición, fallback 404 y rutas heredadas conservadas (PUBLIC-NAVIGATION-1 / Fase 3B).
- sistema común de landings desacoplado de fuentes de contenido, con semántica, metadatos básicos, enlaces accesibles y responsive aplicado a Competición sin abrir nuevas rutas (PUBLIC-LANDING-SYSTEM-1 / Fase 3C).
- landing dinámica de Competición basada en una única respuesta pública de temporadas y campeonatos, con estados locales, detalle contextual, accesibilidad y responsive sin duplicar el filtrado backend (COMPETITION-LANDING-DATA-1 / Fase 4A).
- preview independiente del ranking histórico, límite visual de cinco sin reordenar, enlace a la vista completa y navegación contextual de categorías sobre las URLs existentes (COMPETITION-RANKING-NAVIGATION-1 / Fase 4B).
- cierre compositivo y funcional de la rama pública de Competición con navegación determinista, estados recuperables, jerarquía y tablas accesibles sin ampliar el dominio (COMPETITION-UX-CLOSURE-1 / Fase 4C).
- contrato y compilador determinista de `knowledge/`, con 40 documentos en cuatro colecciones, artefacto de esquema v1 y 32 tests dirigidos sin publicar rutas (KNOWLEDGE-COMPILER-1 / Fase 5A).
- corpus canónico preparado para 5B con los ocho Reglamentos aprobados, 40 documentos vigentes, headings normalizados y grafo publicable, validado mediante 44 tests dirigidos sin crear proyección pública, páginas o rutas (KNOWLEDGE-PUBLICATION-READINESS-1 / Fase 5A.1).
- proyección pública versionada con 40 documentos vigentes, parser seguro, repositorio y renderer frontend, landing de Aprende, Manual, referencias internas, Navbar y 404 validados sin backend o API (KNOWLEDGE-PUBLIC-CONSUMER-1 / Fase 5B).
- cierre de Aprende a jugar con resumen derivado, accesos de colección, contexto local, índice documental, anterior/siguiente sin cruces, fragmentos estables y Knowledge fuera del chunk inicial (KNOWLEDGE-EXPERIENCE-CLOSURE-1 / Fase 5C).
- inventario, instalación limpia, regresión, auditoría, notas de versión y runbook del candidato preparados sin publicar ni etiquetar (MVP-RC-1).

---

# Siguiente paso del candidato y de Fase 7

MVP-RC-1 queda como instantánea histórica preparada y no publicada. El orden de
publicación anterior queda condicionado por los P0 descubiertos en 7A. No se
debe crear el tag o la release hasta completar Fase 7G.

1. revisión humana y eventual merge documental de 7A;
2. cierre de decisiones y contenido en 7B;
3. implementación incremental 7C–7F, cada bloque validado y revisado;
4. cierre de aceptación en 7G;
5. sólo entonces, preparación del nuevo candidato, tag y publicación.

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
- formularios públicos institucionales o de federación con privacidad y antispam;
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
