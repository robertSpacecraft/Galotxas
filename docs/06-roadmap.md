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
28. **Fase 7B — Decisiones y preparación editorial:** navegación final, grupos Aprende/Club, cuenta separada, rutas institucionales canónicas, compatibilidad, fuentes CMS, footer, plantillas, matriz legal, identidad pública pendiente y gates 7C–7G cerrados documentalmente sin implementar código ni contenido.
29. **Fase 7C.0 — Auditoría de preparación de Club:** Knowledge, CMS, API, React, `/nosotros`, rutas, recursos y datos aportados contrastados; readiness, preguntas y gates documentados sin implementar 7C ni cargar contenido.
30. **Fase 7C.1 — Preparación técnica de Club y contacto:** assets y `dist` auditados; capacidad CMS y carga manual documentadas; dominio, API, antispam, administración y notificación opcional de contacto implementados con flags desactivados; servicio React aislado validado, sin contenido CMS ni rutas Club.
31. **Fase 7C.2 — Fachadas públicas de Club:** carga editorial local realizada manualmente; cuatro rutas canónicas diferidas sobre slugs CMS cerrados; estados completos, metadatos y formulario condicionado; legado, Navbar y datos locales conservados; cobertura frontend/backend/E2E y documentación cerradas.
32. **Fase 7D.1 — Navegación, Home y footer estructural:** configuración única con Inicio/Competición, disclosures Aprende/Club y Cuenta separada; interacción desktop/móvil accesible; Home con destinos reales; footer global con identidad, rutas Club y redes confirmadas; legal y Contacto productivo aplazados.
33. **Fase 7D.2A — Consolidación legal, privacidad y cookies:** identidad jurídica/pública y datos institucionales confirmados correctamente separados de acreditaciones registrales y validaciones pendientes; estatutos históricos, matriz institucional, tratamientos, identidad deportiva, menores, imágenes, almacenamientos, cookies y terceros auditados; cinco borradores internos creados sin rutas, enlaces legales, CMS ni activación de Contacto.
34. **Fase 7D.2B — Endurecimiento técnico de privacidad:** identidad deportiva anónima proyectada mediante allowlists y criterio fail-closed; perfil retirado de `localStorage` y restaurado con `/me`; recursos automáticos de Google Fonts, Bunny Fonts y jsDelivr eliminados; contratos privados, CMS, imágenes, legal y Contacto conservados.
35. **Fase 7D.2C1 — Fuente y páginas legales versionadas:** fuente canónica `legal/`, metadatos y compilador fail-closed independientes de Knowledge, proyección build-time, tres rutas lazy y enlaces de footer; política de menores y conservación publicada, con Contacto, correo, consentimientos e imágenes todavía desactivados.
36. **Fase 7D.2C2A — Identidad pública verificable de menores:** aviso de formulario versionado, estados y modos explícitos, tokens hash de un solo uso, confirmación de representante, conformidad 14–17, revisión y revocación Blade, integración opcional con Escuela y proyección fail-closed en toda Competición; flags y correo productivo desactivados.
37. **Fase 7D.2C2B — Primera capa y operación de Contacto:** aviso versionado, consentimiento trazable, config fail-closed, persistencia como recepción, correo auxiliar configurable, reintento limitado, administración Blade, cierre, retención, holds, anonimización y purga de HMAC; producción y proveedor siguen desactivados.
38. **Fase 7D.3 — SEO, accesibilidad e indexación pública:** inventario y clasificación de rutas, canonical y aliases centralizados, indexación fail-closed, metadata/OG/JSON-LD prudentes, robots y sitemap deterministas, foco y anuncio SPA, reflow y 61 escenarios E2E validados; sin dominio, activación o despliegue.
39. **Fase 7E — Preparación operativa de Escuela:** apertura fail-closed centralizada, contenido de programa administrable, contacto operativo privado, aviso versionado, trazabilidad, retención, holds y anonimización; 421 tests backend, 484 frontend y 63 E2E validados con producción cerrada y sin inventar datos reales.
40. **Fase 7F.1 — Production readiness, entornos y runbooks:** dominio y API canónicos centralizados, staging/producción separados, preflights fail-closed, liveness mínima, bootstrap administrativo seguro, CORS/headers/proxy, artefactos Vercel/Railway y operación de DB, CMS, correo, DNS, backup, restore y rollback documentados; 431 tests backend, 493 frontend y 63 E2E validados sin desplegar ni activar servicios.
41. **Fase 7F.2A — Rankings y navegación de Competición en `develop`:** `/rankings` consolidado en Histórico, Temporada, Campeonato y Categoría sobre contratos API existentes; selectores jerárquicos seguros; Competición convertida en disclosure accesible con Vista general, Campeonatos y Rankings; 508 tests frontend y 63 E2E validados sin modificar backend, desplegar ni obtener aceptación humana de staging.
42. **Fase 7F.2B — Infraestructura multimedia persistente:** auditoría, ADR-043 aprobado, núcleo local implementado y validado, bucket staging creado, conectado y validado con persistencia tras redeploy; 7F.2B cerrada.
43. **Fase 7F.2C — Patrocinadores/colaboradores administrables:** modelo Sponsor, CRUD Blade, lifecycle privado, API efectiva, serving estable y franja React pre-footer implementados; staging validó el flujo principal y posteriormente todos los gates secundarios diferidos (temporalidad, redeploy, borrado y revisión móvil/accesible). 7F.2C completamente aceptada y cerrada en staging.
44. **Fase 7F.2D — Foto de perfil privada de Usuario:** referencia User.profile_photo_path endurecida, API propia, serving privado backend (200 OK) resolviendo CORS de S3 y UI accesible en Mi Panel implementados; staging validó el flujo principal tras hotfix, difiriendo cleanup manual, redeploy y accesibilidad a regresión global.
45. **Fase 7F.2E — Noticias:** `NewsArticle` dedicado, administración Blade, publicación efectiva, lifecycle de portada, API paginada, listado/detalle React, Navbar, SEO article y E2E implementados; migración aplicada manualmente y flujo aceptado en staging. 7F.2E cerrada.
46. **Fase 7F.2F — Navegación CMS administrable:** placements dedicados con slot Club único, administración Blade, publicación efectiva desde `CmsPage`, API cerrada y composición React structural-first/fail-soft implementados; migración y flujo completo aceptados manualmente en staging. 7F.2F cerrada.
47. **Cierre del gap preproducción de Copa en `develop`:** fases y stages explícitos, resultados comunes a Liga/Copa caracterizados, validación administrativa coherente, generación segura de Final/3.º-4.º, contrato público con ganador oficial, cuadro React y E2E completo implementados y validados localmente; aceptación en staging pendiente.
48. **Refinamiento público de Copa y rankings en `develop`:** vista dedicada `/categories/{id}/cup`, navegación contextual de cuatro destinos y Schedule reservado a Liga; categoría y Mi Panel excluyen Copa, mientras campeonato, temporada e histórico incluyen sus partidos validados sin bonus. Validación local completa superada; aceptación en staging pendiente.

La Fase 2B queda completa con los subbloques 2B.1–2B.5. Las fases 3A–3C, 4A–4C, 5A–5C y 6A–6C.1 completan respectivamente las fases 3, 4, 5 y 6. Fases 7A, 7B, 7E y 7F.1, los bloques 7C.0–7C.2 y 7D.1–7D.3 están completados; 7C y 7D quedan cerradas.

**Fase 7F (Staging)** ha sido validada mediante *smoke test global y aceptación humana*: Vercel/Railway/MariaDB separados para staging, configuración de DNS/TLS, CMS verificado, API, Legal, Contacto (formulario/persistencia) y Escuela (inscripciones, permisos) validados. El envío **SMTP real desde Railway queda BLOQUEADO por el plan Railway Hobby** (requiere Pro o proveedor HTTPS alternativo, pospuesto); por tanto, la notificación real de Contacto, el flujo completo de identidad de menores (token/confirmación) y la recuperación de contraseña por correo quedan pendientes. La verificación de backups/restore/rollback en staging queda **aplazada / no ejecutada por decisión operativa** (el backup nativo de volúmenes también está bloqueado por el plan Railway Hobby; no se ensaya por ahora la alternativa manual ni el rollback de aplicación). Se ha registrado una posible mejora de UX para `/aprende-a-jugar` (no bloqueante).

7F (Producción), Fase 7 y el MVP siguen abiertos. No se ha realizado publicación productiva ni migración editorial definitiva.
Se incorpora la **Fase 7F.2** de refinamiento preproducción, re-evaluando capacidades como noticias, foto de usuario, multimedia persistente y rankings.

Antes de iniciar 7F.2A se ha cerrado su prerrequisito de dominio: el reparto base de rankings es `3-0` cuando quien pierde suma menos de 8 juegos y `2-1` cuando suma 8 o más, siempre con tres puntos totales. Los cuatro cálculos backend y Mi Panel consumen una única regla, con regresión de dobles y generación de copa. Esta corrección no inicia ni completa 7F.2A y no vuelve a sembrar copas ya generadas.

7F.2A está cerrada en staging (aceptación superada el 2026-08-15). 7F.2B está cerrada en staging tras superar las pruebas de persistencia. 7F.2C ha superado todos sus gates secundarios diferidos y está completamente aceptada en staging. 7F.2D tiene su flujo principal aceptado en staging, con gates secundarios diferidos. 7F.2E y 7F.2F han sido cerradas y aceptadas manualmente en staging. El cierre técnico del gap de Copa está implementado y validado localmente, pendiente de smoke y aceptación humana en staging. La corrección de reparto de puntos que actuaba como prerrequisito se verificó manualmente en staging el 2026-08-15, sin que esa evidencia se considere aceptación de 7F.2A.

## Fase 7 abierta — bloques parcialmente completados / bloqueados

1. **Fase 7F (Pendientes de Staging y Producción):** configuración de SMTP real (bloqueado en Hobby), identidad pública completa de menores y reset de contraseña (dependen de SMTP), y ejecución de backup/restore/rollback en staging (aplazado).
2. **Fase 7F.2 — Refinamiento preproducción:** 7F.2A, 7F.2B y 7F.2C están cerradas en staging; el flujo principal de 7F.2D está aceptado allí con cierres secundarios diferidos; 7F.2E y 7F.2F están cerradas y aceptadas manualmente en staging. El cierre del gap de Copa está completo sólo en local y debe superar staging.

## Fase 7 abierta — bloques pendientes

1. **Fase 7F.2 — Refinamiento preproducción y ampliación controlada del alcance:** 7F.2C, 7F.2E y 7F.2F cerradas en staging; flujo principal de 7F.2D aceptado en staging; Copa implementado y validado localmente pero pendiente de staging. El antiguo gap funcional de Copa deja de estar pendiente en `develop`, sin que ello constituya aceptación del baseline productivo.
2. **Fase 7F (Producción):** tras completar validaciones y smoke de staging, desplegar producción inicialmente noindex y con Contacto, Escuela, identidad de menores y scheduler cerrados, activándolos de uno en uno sólo tras sus gates.
3. **Fase 7G — Validación y cierre del MVP:** ejecutar regresión, recorridos críticos, QA responsive/multibrowser priorizada, smoke y aceptación humana antes de tag/release.

Las autorizaciones de imágenes para web, redes sociales y archivo histórico
permanecen como un frente independiente posterior, todavía sin numeración
aprobada. No forman parte de 7D.2C2B ni reutilizan la autorización de identidad
deportiva.

Las fases 4, 5, 6 y los bloques 7B, 7C.0–7C.2, 7D.1–7D.3, 7E y 7F.1 están completados.
`/competicion` ofrece el recorrido deportivo; `/aprende-a-jugar` y el Manual
presentan los 40 documentos desde Knowledge; `/escuela` consume la configuración
pública y admite solicitudes anónimas cuando el backend las abre. El cierre de
Escuela queda revalidado sobre proyectos Docker aislados. `Club` funciona como
disclosure, no como landing `/club`; sus cuatro rutas hijas presentan la página
CMS correspondiente cuando está publicada. Aprende agrupa Aprende a jugar,
Manual y Escuela sin fusionar sus fuentes. Home y el footer global ofrecen
destinos estructurales reales. 7D.2A aporta la base interna, 7D.2B resuelve el
endurecimiento técnico y 7D.2C1 publica tres textos controlados desde `legal/`
con rutas y footer. 7D.2C2A añade un aviso de formulario y autorización
verificable de identidad de menores. 7D.2C2B completa después la capacidad
técnica de Contacto, pero mantiene `CONTACT_FORM_ENABLED=false` y
`CONTACT_NOTIFICATION_ENABLED=false` como defaults: no configura proveedor,
credenciales, entrega o activación productiva. 7E añade la misma separación
para Escuela: la capacidad técnica queda preparada y validada, pero
`SCHOOL_ENROLLMENT_ENABLED=false` continúa como default hasta cargar y aprobar
configuración real durante la ejecución manual de 7F. 7F.1 aporta los
preflights y runbooks, pero no conecta proveedores ni activa ninguna flag.
Los borradores permanecen como historia interna no vigente.

La carga editorial local se realizó manualmente y 7C.2 incorpora la interfaz
React condicionada, pero el formulario productivo continúa desactivado hasta
cerrar privacidad, destinatario, correo y operación. Cada entorno productivo
requiere carga y publicación CMS manual; paridad de `/nosotros`, derechos de
imagen y aceptación humana siguen pendientes. Los aliases institucionales ya
tienen canonical y no compiten en el sitemap; el CMS genérico y las superficies
deportivas volátiles permanecen `noindex`. Redirects permanentes, retirada del
legado, metadata por respuesta para bots sin JavaScript, limpieza de código
huérfano y migración de `academy`, Prensa y Federaciones permanecen en bloques
posteriores. El dominio y las URLs canónicas están decididos y centralizados,
el entorno de staging ha sido desplegado y validado, salvo el bloque de backup/restore/rollback (aplazado) y el envío SMTP real (bloqueado por Railway Hobby, afectando a Contacto, menores y contraseñas); el backup nativo de volúmenes también está bloqueado por el plan Railway Hobby, pero
el despliegue, indexación productiva y activación de funciones operativas reales
siguen sin activar hasta finalizar 7F y 7G.

Este programa no altera por sí solo el proceso operativo de revisión y publicación del candidato descrito más abajo. Antes de iniciar un bloque funcional debe reconciliarse su calendario con el candidato y con cualquier corrección P0/P1.

---

# Estado del MVP completo

El núcleo técnico del candidato anterior está implementado y QA-MVP-1,
QA-FIX-1, RC-HARDEN-1 y MVP-RC-1 conservan su valor histórico. Sin embargo, la
auditoría 7A amplía el criterio desde “candidato técnico” a “aplicación pública
y funcionalmente completa”: el MVP completo **todavía no está completado**.

Permanecen P0 la activación productiva de Contacto y correo saliente, los gates
operativos de la autorización de menores, las imágenes, configuración y
privacidad productiva de Escuela,
despliegue Railway/Vercel/MariaDB y validación de recorridos críticos. La
definición observable y priorización se encuentran en
`14-mvp-parity-audit.md`; el contrato, las plantillas y gates de implementación
están en `15-mvp-editorial-and-navigation-contract.md`.

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
- flujo completo de Copa con stages explícitos, resultados compartidos,
  cuadro público y campeón derivado del ganador oficial de la Final
  (CUP-FLOW-1, pendiente de aceptación en staging);
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
- Vitest, React Testing Library y 601 tests en 86 archivos, incluidos Noticias, pipeline y páginas legales, SEO/canonical, identidad pública, sesión mínima, Escuela, formularios, navegación agrupada, Home/footer, Knowledge, los cuatro ámbitos de Rankings, Competición, cuenta, foco, landmarks, 404, preflight y contrato Vercel;
- smoke Playwright completo: 66 escenarios Chromium con stack temporal aislado, incluido el lifecycle editorial/multimedia de Noticias y las regresiones SEO, Escuela, Club, cuenta, Sponsor y avatar;
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
- identidad deportiva pública minimizada y fail-closed, perfil autenticado sólo en memoria con bootstrap `/me`, y recursos tipográficos/administrativos locales sin peticiones automáticas a los proveedores retirados (PRIVACY-HARDENING-PUBLIC-IDENTITY-1 / Fase 7D.2B).
- preparación reproducible de Vercel/Railway, liveness mínima, preflights frontend/backend, CORS exacto, headers, proxy, admin bootstrap y runbooks forward-only de operación sin despliegue ni activación externa (PRODUCTION-READINESS-1 / Fase 7F.1).
- centro público de Rankings con cuatro ámbitos y navegación Competición agrupada, jerárquica, accesible y responsive sobre los endpoints existentes, validado sólo en `develop` (COMPETITION-RANKINGS-HUB-1 / Fase 7F.2A).
- inventario, instalación limpia, regresión, auditoría, notas de versión y runbook del candidato preparados sin publicar ni etiquetar (MVP-RC-1).

---

# Siguiente paso del candidato y de Fase 7

MVP-RC-1 queda como instantánea histórica preparada y no publicada. El orden de
publicación anterior queda condicionado por los P0 descubiertos en 7A. No se
debe crear el tag o la release hasta completar Fase 7G.

1. revisión humana y eventual merge documental de 7A;
2. cierre de decisiones y contenido en 7B;
3. ejecutar manualmente 7F conforme al runbook; 7D, 7E y la preparación técnica 7F.1 ya están cerradas;
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
- formularios públicos institucionales o de federación con privacidad y antispam;
- SEO y ordenación editorial avanzados del CMS;
- métricas y filtros administrativos avanzados;
- aplicación móvil y API administrativa consolidada;
- **Patrocinios Contextuales**: asociación de patrocinadores con campeonatos o pistas temporales, manteniendo la identidad física inmutable;
- **Perfil Público Deportivo**: ficha pública opcional (requiere autorización explícita e independiente para foto, alias y palmarés, fail-closed por defecto).
- **Borrado administrativo de páginas CMS**: borrado o retirada con política de integridad, referencias, bloques, navegación, SEO, URLs publicadas, posibles aliases y seguridad (trazabilidad, soft delete vs delete real).

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
