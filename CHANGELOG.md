# Changelog

Este archivo registra los cambios relevantes de Galotxas. La estructura sigue de forma simplificada [Keep a Changelog](https://keepachangelog.com/) y las versiones propuestas siguen SemVer.

## Unreleased

### Added

- **OPERACIÓN Y CONTROL SOURCE BACKUP 7G.3:** Se valida la operatividad productiva del automatismo de backup y se cierra el P0 de control de source/autodeploy mediante una política deliberadamente manual para la primera publicación. Se configuró el usuario dedicado `galotxas_backup` en MariaDB con mínimos privilegios. El P0 de autodeploy se resuelve desactivando el autodeploy de GitHub en `backend-production` (conectado a `main`) y manteniendo `backup-production` source-less (sin integración a GitHub), desplegado exitosamente vía `railway up` manual desde un árbol limpio de `develop`. Esta política exige acción humana explícita para cada despliegue. En la infraestructura de backup, se validó el servicio con secretos, retención, cron UTC y alertas. Se ejecutó exitosamente el modo `check` y el primer backup productivo supervisado, creando el snapshot `b7225bfc` sobre un esquema pre-migraciones, cuya integridad fue ratificada (SHA256 PASS) en restauración. El gate 7G.3 permanece NO-GO por P0 operativos restantes (ownership, rollback rehearsal, evidencia de correo Resend y hash final).
- **OAUTH Y BACKUP 7G.3 (DRIVE.FILE):** Se reconcilia el subbloque OAuth/Google Drive para backups automáticos (`fix(ops): limitar backups a Google Drive drive.file`). La App `Galotxas Backup` se pasa a 'En producción' (tipo External). Se utiliza exclusivamente el scope restringido `drive.file`. El repositorio remoto canónico cambia a `galotxes-backup-drivefile/production`, ya que el scope limitado impide leer el repositorio antiguo (`galotxes-backup/production`), el cual queda preservado intacto con su snapshot `7G.3-pre-migration`. El script `backend/backup/backup.sh` impone `scope = drive.file` sin fallback.
- **AUTOMATIZACIÓN DE BACKUP 7G.3 PREPARADA:** Se añade imagen efímera independiente con cliente MariaDB 11.4, rclone y restic; limpieza garantizada, modo `check` y retención agrupada.
- **AUDITORÍA PREPARATORIA 7G.3:** Se superó el drill manual de recuperación de producción. Se constató la infraestructura backend en Railway y se corrigió operativamente la `APP_KEY`. El proyecto Vercel productivo `galotxas` está inerte. El gate 7G.3 permanece NO-GO pendiente de: confirmación externa Resend, ownership, ensayo de rollback y validación verde del hash final.
- **COPA STAGING PASS:** Aceptación humana completa y exitosa del flujo y visualización de Copa en el entorno de staging. Se documentan dos mejoras UX de Copa como P1 post-MVP (no bloqueantes). Queda pendiente la regresión global final 7G.2.

- **REGRESIÓN GLOBAL 7G.2 (PASS / CERRADO):** Se ha completado la regresión global sobre el candidato `e2b299cfd7e6d9fa1d59d15d09d177605bcb35ba` en Vercel Staging y Railway Staging. Los hallazgos previos (falso positivo `P0-STAGING-NEWS-DETAIL`, vulnerabilidades npm, contrato `/sitemap.xml` SPA fallback) están cerrados. La suite completa de backend, frontend y recorridos E2E fue superada exitosamente con 0 bloqueos P0/P1 y cobertura SEO, accessibility y legal validada. La evidencia de Railway que devolvía `Unauthorized` también ha sido cerrada tras verificación externa read-only de despliegue exitoso (commit `dfa5f34`). El gate 7G.2 se considera PASS formalmente. El siguiente paso técnico es 7G.3. La Fase 7G.4 NO está iniciada y Producción continúa sin desplegar.

- **RESTORE DRILL 7G.1D:** PASS técnico obtenido en el restore lógico aislado de la MariaDB de staging. Se valida el uso de un dump lógico (con checksum SHA-256) restaurado sobre una base de datos efímera y comprobado estructuralmente, obteniendo un RTO de pared de 5 minutos 27 segundos. Se cierra el P0 de recuperación MariaDB para el entorno de staging sin modificar infraestructura ni usar snapshots nativos. Producción y media se mantienen como gates productivos pendientes.

- **CORRECCIÓN FORWARD-ONLY 7G.1C:** Se verifica operativamente en el workspace que Railway Hobby restringe los backups nativos y PITR al plan Pro (`maxBackupsCount = 0`). La documentación se corrige para desestimar cualquier expectativa de "snapshot nativo" predeploy. La estrategia vigente se apoya en dumps lógicos portables con compresión, cifrado, checksum, copia externa y prueba de restore aislado; Media (Buckets) se gestiona de manera completamente independiente con su propio inventario y copia externa. Esta corrección no muta entornos ni dependencias.

- Fase 7G.1C reconcilia el P0 de backup/restore con la documentación oficial
  vigente de Railway: snapshots manuales y programados de volumen disponibles
  sin restricción Hobby publicada, backup pre-Image Auto Update específico de
  Pro, restore staged en el mismo project/environment y límites/coste actuales.
- Se documenta la arquitectura recuperable MariaDB/bucket/Git, una estrategia
  de snapshot nativo + dump lógico cifrado fuera del proyecto + copia
  independiente de media, el gate predeploy, RPO/RTO propuestos y rollback por
  superficie. No se creó backup ni se ejecutó restore; el P0 permanece abierto
  hasta el drill lógico aislado y el rollback rehearsal.

- Fase 7G.1B integra el transport oficial de Resend para Laravel 12, prepara
  producción con `resend` y staging seguro con `array`, endurece el preflight
  y hace no enumerable el fallo de entrega con invalidación del token y log
  saneado. La validación extrema a extremo en staging se ha cerrado operativamente
  con éxito tras comprobar entrega, flujo de reset y limpieza en log.
  El baseline seguro staging quedó restaurado y el P0 de correo queda pendiente
  de llave y smoke test productivo.

- Fase 7G.1A audita extremo a extremo el reset de contraseña y el runtime de
  correo, confirma el bloqueo SMTP de Railway Hobby y selecciona Resend por
  API HTTPS, con Postmark como alternativa. Se documentan contrato de
  variables, cambios y gates pendientes sin integrar proveedor, instalar
  dependencias, cargar secrets, tocar entornos ni cerrar el P0.

- Se prepara documentalmente Fase 7G mediante
  `MVP-FINAL-GATE-READINESS-1`: baseline reconciliado, vigencia de evidencias,
  matriz staging/producción, flags, restricciones de proveedor, checklist de
  regresión 7F.2, Go/No-Go y gates 7G.1–7G.7. No se acepta Copa, no se ejecuta
  la regresión global ni se inicia producción, migraciones, flags, tag o
  release; 7G permanece abierta.

- Fase 7F.2F incorpora `CmsNavigationItem` con slot DB/PHP único `club`,
  constraint página/slot, activación privada por defecto, relación cascade y
  administración Blade separada; las cuatro fachadas estructurales Club son
  slugs reservados y no existe URL manual ni editor libre de menú.
- Se publica `GET /api/v1/cms-navigation` con allowlist cerrada, URL derivada y
  filtro de placement activo más `CmsPage` efectivamente publicada. React
  valida fail-closed y añade los hijos al final de Club sin mutar la navegación
  protegida; Home, footer, Cuenta y demás ramas permanecen intactos.
- La cobertura 7F.2F incluye constraints, permisos, payloads manipulados,
  publicación y N+1; contrato/servicio/hook/composición/Navbar; y E2E temporal
  Blade→API→React con 320 px, teclado, ciclo editorial y cleanup. ADR-045 y el
  checklist documentan la decisión. Migración aplicada y flujo completo aceptado
  manualmente en staging, cerrando 7F.2F.
- Se documenta como mejora futura (P1) el borrado/retirada administrativa de
  páginas CMS con política de integridad, referencias, bloques, navegación y
  seguridad (soft delete vs hard delete). No invalida 7F.2F porque el placement
  puede eliminarse y la página retirarse.

- Se cierra localmente el gap preproducción de Copa: la caracterización confirma
  persistencia correcta por admin, reportes coincidentes y conflictos; la única
  incoherencia reproducida, tanteos con estado incompatible descartados en
  silencio, pasa a rechazarse mediante validación común a Liga y Copa.
- Semifinal, Final y tercer puesto usan `phase=cup` y stages explícitos; la
  generación valida exactamente dos semifinales oficiales sin empate, conserva
  ganadores/perdedores, nace sin programación y sigue siendo idempotente.
- El schedule público existente añade fase, stage y `winner_entry` allowlisted,
  orden estable y tanteos sólo oficiales. React incorpora la ruta diferida
  `/categories/{id}/cup`, navegación contextual de cuatro vistas y un cuadro
  accesible y responsive con pendientes y campeón derivado exclusivamente del
  ganador backend. `Calendario y resultados` queda reservado a Liga.
- El frontend selecciona Copa únicamente mediante `type=cup`, `phase=cup` y un
  stage admitido, sin inferir datos legados por nombre u orden. La clasificación
  de categoría y Mi Panel excluyen Copa; campeonato, temporada e histórico
  incluyen partidos de Copa validados con el reparto común y sin bonus.
- La implementación de Copa queda validada localmente y pendiente de aceptación
  en staging; la regresión refinada completa 557 tests backend con 4.314
  aserciones, 659 frontend y 68 E2E, sin anunciar todavía su cierre productivo.

- Fase 7F.2E implementa Noticias como dominio editorial dedicado: modelo y
  migración `news_articles`, administración Blade, borrador/programación,
  publicación efectiva, slug histórico, soft delete y ADR-044, sin reutilizar
  CMS ni reinterpretar `/contenidos/prensa-media`.
- Las portadas reutilizan el núcleo multimedia privado con perfil
  `news_cover`, confirmación administrativa de derechos, lifecycle con
  compensación/cleanup y serving estable: local vía Nginx `internal`, S3 vía
  redirect público temporal; keys, procedencia y derechos no salen por API.
- Se publican `GET /api/v1/news`, detalle e imagen por slug, con paginación de
  12 y Resources cerrados; React añade `/noticias`, `/noticias/:slug` y el
  enlace estructural Noticias en Navbar, sin feed Home, footer ni adelantar
  7F.2F.
- El índice entra en el sitemap estático y el detalle aplica canonical, Open
  Graph article y JSON-LD tras validar la respuesta. Los slugs runtime quedan
  fuera del sitemap MVP como deuda P1, así como metadata client-side y SSR
  dinámico. La fase pasa 526 tests backend, 601 frontend y 66 E2E.
- Se promueve 7F.2E a staging; un 500 inicial evidenció la tabla `news_articles`
  ausente al no ejecutarse migraciones automáticamente en el deploy.
- Se aplica explícitamente `migrate --force` con éxito y se acepta manualmente
  en staging todo el flujo técnico/funcional de Noticias, cerrando 7F.2E.
  Este cierre dio paso a 7F.2F (Navegación CMS administrable).
- Se acepta en staging el flujo funcional de Sponsor (Fase 7F.2C); migración, alta administrativa, almacenamiento real, render público y desactivar/reactivar confirmados. Se validaron manualmente los gates secundarios diferidos (redeploy, borrado físico de imagen, programación temporal excluyente y accesibilidad responsive 320 px). 7F.2C queda completamente aceptada y cerrada.
- 7F.2D aceptada completamente en staging y cerrada.
- Se documentan como mejoras futuras (post-MVP) los patrocinios contextuales (campeonatos y pistas) y el perfil público deportivo opcional de jugador. Ninguna altera 7F.2E (Noticias) ni 7F.2F (Menú CMS).

- Fase 7F.2D implementa en `develop` la foto privada de `User` sin migración:
  lifecycle transaccional, cleanup post-commit, keys `avatars/` estrictas,
  API `/me` autenticada, serving local/S3 privado y rate limit de mutaciones.
- Mi Panel permite previsualizar, subir, sustituir y eliminar la foto mediante
  descarga Bearer como blob, object URLs revocables y fallback de iniciales.
  La foto no se publica ni amplía el consentimiento de identidad deportiva;
  7F.2D permanece abierta hasta su aceptación manual en staging.
- Nginx conserva el origen CORS exacto ya autorizado por Laravel durante la
  entrega `X-Accel-Redirect`, sin abrir el recurso privado a otros orígenes.

- Fase 7F.2C implementa en `develop` patrocinadores/colaboradores administrables:
  `Sponsor`, CRUD Blade, ventanas efectivas, lifecycle privado, API allowlisted,
  serving estable local/S3 y rejilla React inmediatamente antes del footer.
  No crea plataforma publicitaria, tracking, cookies, CTA, placement o carousel;
  el bloque permanece abierto hasta la aceptación manual de staging.
- La regresión de Colaboradores cubre dominio, permisos, validación, fallos y
  cleanup multimedia, contrato/serving público, estados React y un E2E real de
  dos logos, orden, reemplazo, 320 px y borrado sobre volumen efímero aislado.
- Se cierra la Fase 7F.2B tras validar la infraestructura multimedia persistente S3-compatible en staging, verificando el probe, limpieza y persistencia real tras redeploy; la producción multimedia sigue sin configurarse y el siguiente bloque oficial es 7F.2C Banners.
- Fase 7F.2B.1 incorpora el núcleo multimedia local sin consumidores: discos
  privados `media_local`/`media_s3`, configuración `MEDIA_DISK`, adaptador
  Flysystem S3, normalización JPEG/PNG/WebP con GD/EXIF, keys UUID, servicio de
  storage y probe con cleanup.
- Docker de test y Railway quedan preparados con JPEG/PNG/WebP y límites
  Nginx/PHP de 12M/10M/12M. La cobertura añade 22 tests dirigidos y la suite
  backend pasa 459 pruebas; no existe bucket, secret, endpoint o integración
  de feature; en ese subbloque 7F.2B continuaba abierta.
- Fase 7F.1 prepara, sin desplegar, los contratos separados de staging y
  producción para Vercel, Railway y MariaDB: dominio/API canónicos, plantillas
  de entorno sin secretos, CORS exacto, proxy, headers prudentes, liveness
  mínima, preflights frontend/backend y bootstrap administrativo seguro.
- Se incorporan artefactos reproducibles de Vercel y Railway sin migraciones
  en startup, más el runbook de DB forward-only, CMS manual, DonDominio sin
  password, DNS/MX, backup/restore, rollback, mantenimiento, observabilidad,
  smoke y activación gradual. ADR-041 fija la separación física de entornos.
- La validación de 7F.1 pasa 431 tests backend, 493 frontend y 63 E2E, además de
  lint, Pint, sintaxis, caches, builds temporales, SEO, Legal, Knowledge y
  hashes. 7F, 7G, Fase 7 y MVP siguen abiertos; no hay deploy ni activación.
- Fase 7E incorpora apertura fail-closed de Escuela centralizada en Laravel con
  estados `open`, `closed` y `unavailable`, flag productiva desactivada por
  defecto y gate de configuración completa. Presentación y proceso pasan a
  `SchoolProgram`; el agregado público deja de exponer teléfono y correo.
- `NOTICE-SCHOOL-ENROLLMENT` 1.0.0 versiona la primera capa independiente de
  identidad pública. Las inscripciones añaden trazabilidad administrativa,
  vencimiento, holds, anonimización y purga manual idempotente conforme a los
  plazos publicados, sin scheduler ni correo productivo.
- La operación se valida con 421 tests backend, 484 frontend y 63 E2E, además
  de lint, Pint, sintaxis, Legal, Knowledge, SEO, hashes y build temporal. 7E
  queda cerrada técnicamente sin datos inventados ni activación; 7F, 7G, Fase
  7 y MVP continúan abiertos.
- Fase 7D.3 incorpora inventario y clasificación de rutas, metadata y canonical
  centralizados, aliases institucionales `noindex`, Open Graph prudente,
  JSON-LD confirmado de Home y `seo:check` sin dependencias nuevas.
- Vite genera `robots.txt` y un sitemap determinista de 52 URLs sólo bajo una
  URL HTTPS y activación explícita; el default bloquea rastreo y omite sitemap.
  Foco y announcer SPA, reduced motion y 61 escenarios responsive/E2E completan
  el cierre técnico de 7D. Fase 7, MVP, dominio y despliegue siguen abiertos.
- Fase 7D.2C2B incorpora `NOTICE-CONTACT-FORM` 1.0.0, primera capa visible,
  consentimiento no premarcado y trazable, configuración fail-closed y
  proyecciones legales deterministas sin crear una cuarta página pública.
- `ContactRequest` añade estados de notificación, historial mínimo, reintento
  manual limitado, From/Reply-To controlados, cierre, retención de 12 meses,
  holds, anonimización y purga idempotente del HMAC a 30 días. ADR-038 fija que
  la persistencia acredita recepción y el correo es auxiliar.
- Blade incorpora filtros y operación completa de las solicitudes; React
  conserva siempre el CMS y sólo muestra aviso/formulario si API y artefacto
  coinciden. Los defaults productivos siguen desactivados, sin proveedor,
  secretos, despliegue, scheduler o backups configurados. 7D.2 queda cerrada;
  el cierre posterior de 7D se registra en la entrada de 7D.3.
- Fase 7D.2C2A incorpora autorización versionada, verificable y revocable de
  identidad pública de menores para `public_competition_identity`, con estados
  explícitos, modos `alias|name_initial|anonymous`, tokens hash de un uso,
  confirmación de representante, conformidad 14–17, revisión e historial Blade.
- Escuela integra la decisión opcional sin condicionar la inscripción; la
  vinculación con `Player` usa nacimiento sólo como compatibilidad, exige
  selección y confirmación administrativa explícitas, conserva correcciones en
  el historial y bloquea cambios de sujeto tras confirmar evidencia; toda la
  superficie pública de Competición conserva `Participante` salvo autorización
  efectiva. El correo y los flags productivos continúan desactivados.
- React retira el token del fragmento durante su captura y antes de cualquier
  petición remota, sin almacenamiento, logs, repetición tras recarga ni
  reexposición al volver atrás. 7D.2C2B mantiene su alcance aprobado de Contacto
  y correo operativo; las imágenes quedan en un frente posterior independiente.
- `legal/notices/public-identity-minors.md` y sus proyecciones de formulario
  amplían el pipeline sin crear una cuarta página legal; ADR-037 y el documento
  23 registran dominio, privacidad, retención, E2E y gates restantes.
- Fase 7D.2C1 crea `legal/` como fuente pública versionada, un compilador
  build-time fail-closed independiente de Knowledge, la proyección legal
  determinista, tres rutas React diferidas y los enlaces del footer. Publica
  versión, fecha, política de menores y conservación sin activar Contacto,
  correo, consentimientos verificables, imágenes o despliegue.
- ADR-036 fija que los textos legales no pertenecen al CMS ni a JSX; las
  pruebas de pipeline, frontend y E2E cubren allowlist, metadatos, 404,
  accesibilidad, responsive y ausencia de recursos automáticos de terceros.
- Fase 7D.2B incorpora una proyección pública de identidad deportiva
  centralizada y fail-closed, Resources deportivos con allowlists, consumo
  React de `public_display_name`, bootstrap autenticado mediante `/me` sin
  persistir el perfil, y recursos locales para frontend y Blade sin Google
  Fonts, Bunny Fonts o jsDelivr. Contacto, páginas legales, CMS, imágenes y
  Knowledge permanecen sin publicar o modificar.
- La invalidación React distingue credencial o sesión inválida (`401`, `419` y
  el `403` explícito de usuario inactivo) de un `403` de autorización ordinario,
  que conserva Cuenta y el Bearer para peticiones posteriores.

- Fase 7D.2A documenta la matriz institucional, estatutos históricos,
  tratamientos de datos, identidad deportiva, menores, imágenes, cookies,
  almacenamientos y terceros, y añade cinco borradores internos expresamente
  no publicables sin crear rutas o enlaces legales; el compilador excluye
  `EST-REF-001` de forma exacta y mantiene inalterados ambos artefactos. El
  ajuste de cierre conserva el renombrado histórico, clasifica CIF, domicilio,
  constitución, presidencia y Junta según sus fuentes confirmadas, y mantiene
  separadas las acreditaciones registrales y validaciones pendientes.

- Se completa Fase 7D.1 con configuración única de navegación, disclosures accesibles Aprende/Club, Cuenta separada, Home con recorridos reales y footer global con rutas Club, identidad y redes confirmadas, conservando lazy loading y el legado.
- Se completa 7C.2 y la Fase 7C con cuatro fachadas Club diferidas sobre slugs CMS cerrados, estados remotos completos, metadatos, formulario de Contacto condicionado, compatibilidad legada y cobertura frontend/backend/E2E sin cambiar Navbar ni contenido editorial.
- Se amplía el escenario E2E protegido con páginas CMS institucionales ficticias y activación de Contacto exclusiva del entorno temporal, sin modificar `DatabaseSeeder` ni datos de desarrollo.
- Se completa técnicamente 7C.1 con auditoría de assets y `dist`, guía de carga CMS manual y `ContactRequest`: persistencia local, API pública protegida, configuración allowlisted, honeypot, rate limit HMAC, administración Blade, notificación opcional y servicio React aislado, todo desactivado y sin rutas Club o contenido editorial.
- Se completa documentalmente 7C.0 con `CLUB-VERTICAL-READINESS-AUDIT-1`: inspección de Knowledge, CMS, API, React, `/nosotros`, rutas y recursos; contraste de la información aportada, inventario de imágenes, readiness, preguntas y gates, sin implementar 7C ni cargar contenido.
- Se completa documentalmente Fase 7B con `MVP-EDITORIAL-NAVIGATION-CONTRACT-1`: navegación final, rutas institucionales canónicas, plantillas editoriales, matriz legal, inventario de identidad pública, checklist School, gates humanos y plan refinado 7C–7G, sin implementar código, contenido ni datos.
- Se completa MVP-PARITY-AUDIT-1 de Fase 7A con inventarios de backend, Blade, API, React, CMS y autogestión, definición observable del MVP, priorización P0–P2, recomendación de navegación y plan 7B–7G, sin implementar funciones ni modificar datos.
- Se incorpora la base administrativa `is_public` para temporadas, campeonatos y categorías, con nuevos registros privados, backfill compatible y validación jerárquica sin cascadas; la API pública todavía no filtra por este campo.
- Se documenta el contrato de navegación pública de Fase 3A: cinco áreas canónicas, fuentes de verdad, rutas secundarias, compatibilidad heredada y gates de implementación, sin cambios visibles en Navbar ni nuevas landings.
- Se incorpora la navegación pública progresiva de Fase 3B con configuración única para Inicio y Competición, cuenta separada y landing mínima `/competicion` enlazada a Torneos y Rankings.
- Se añade una experiencia 404 de React Router con enlaces de recuperación, sin redirects ni cambios de hosting.
- Se incorpora el sistema común de landings públicas de Fase 3C con contenedor, cabecera, acciones, secciones, rejilla y tarjetas-enlace desacoplados de las fuentes de contenido.
- Se añaden metadatos básicos reversibles por ruta para Competición y 404, semántica y teclado cubiertos y una matriz responsive de 320 a 1440 px, cerrando técnicamente la Fase 3 sin publicar nuevas áreas.
- Se completa la auditoría y el contrato documental de Fase 6A para Escuela de Galotxas: arquitectura híbrida, fuentes de verdad, MVP informativo-operativo, dominio provisional, privacidad, migración futura de `academy` y planes pendientes de 6B/6C, sin modelos, API, ruta, Navbar o formulario.
- Se cierra documentalmente en Fase 6A.1 el contrato funcional de la Escuela permanente: niveles, horarios semanales, solicitud pública sin cuenta obligatoria, ciclo pendiente/activa/rechazada/baja, centros, actividades y plan 6B.1–6B.4, sin implementar modelos, API, administración o frontend.
- Se incorpora en Fase 6B.1 el núcleo operativo administrable de Escuela con programa, niveles, ubicaciones y horarios, defaults seguros, visibilidad efectiva, integridad relacional, permisos y cobertura MariaDB, sin API ni experiencia React públicas.
- Se incorpora en Fase 6B.2 `SchoolEnrollment` con solicitudes anónimas o vinculadas opcionalmente a la sesión, validación de menores y adultos, ciclo pendiente/activa/rechazada/baja, nivel controlado, administración Blade, rate limiting y respuesta pública sin identificador ni datos personales, sin lectura pública, centros, actividades o React.
- Se incorpora en Fase 6B.3 la gestión administrativa de centros educativos y actividades con nombre libre, estados planificada/completada/cancelada, fechas, horas emparejadas, alumnado previsto, ubicación escolar opcional, transiciones y borrados conservadores, sin API pública, frontend ni asistentes nominales.
- Se incorpora en Fase 6B.4 `GET /api/v1/school` con programa público, apertura efectiva, contacto nullable, ubicación habitual activa, niveles y horarios efectivos ordenados, Resources cerrados y privacidad verificada, sin publicar `/escuela`, centros, actividades o inscripciones.
- Se completa Fase 6C y la Fase 6 con `/escuela` diferida, consumo del agregado público, niveles, horarios, ubicaciones, contacto, apertura y formulario anónimo para menores y adultos; el Navbar y Home enlazan la sección sin migrar ni redirigir el CMS `academy`.
- Se incorpora DOCKER-ENVIRONMENT-ISOLATION-1 con proyectos y archivos Compose independientes para desarrollo, backend test y E2E, runners seguros, configuración resuelta validada y cleanup limitado por proyecto.
- Se incorpora la landing dinámica de Competición de Fase 4A con temporadas y campeonatos públicos obtenidos en una única carga, estados loading/error/retry/vacío y enlaces contextuales al detalle.
- Se integra en `/competicion` el preview histórico de Fase 4B mediante una carga independiente, limitado visualmente a cinco filas en el orden del backend y enlazado a la experiencia completa `/rankings`.
- Se completa la Fase 4 con el recorrido público de Competición desde la landing hasta campeonato, categoría, clasificación, calendario y partido, con retornos deterministas, metadatos básicos y navegación contextual accesible.
- Se formaliza en Fase 5A el contrato de seis metadatos para 40 documentos compilables de Reglamento y Conceptos, con namespaces, orden y exclusiones explícitas.
- Se incorpora un validador y compilador build-time sin dependencias para `knowledge/`, con referencias y contenido ejecutable controlados, salida JSON determinista de esquema v1 y escritura segura.
- Se añade KNOWLEDGE-COMPILER-1 con fixtures temporales, validación del corpus real, sincronía byte a byte del artefacto y regresión del build frontend, sin publicar rutas de Aprende a jugar o Manual.
- Se registra la aprobación editorial inicial de `REG-001`–`REG-008` y se prepara para Fase 5B un corpus canónico de 40 documentos `Vigente`, sin crear proyección pública, renderer ni rutas React.
- Se añade `public-knowledge.json` como proyección versionada exclusiva de documentos `Vigente`, sin Markdown, estado ni datos editoriales de borradores, con escritura coordinada junto al artefacto canónico.
- Se incorpora un parser build-time limitado y un renderer React semántico para headings, párrafos, énfasis, listas, tabla, separadores y referencias internas, sin HTML inyectado.
- Se publican la landing `/aprende-a-jugar`, el Manual, los documentos de Reglamento y los tres grupos de Conceptos, con repositorio frontend, metadatos, retorno y 404 segura.
- El Navbar incorpora Aprende a jugar tras Competición y mantiene activa toda la rama formativa en desktop y móvil, con cuenta separada.
- Se completa Fase 5C con resumen derivado de Aprende, accesos a colecciones, contexto documental local, tabla de contenidos y navegación anterior/siguiente sin cruzar colecciones.
- Los headings compilados disponen de deep links estables que funcionan tanto en navegación SPA como tras recarga directa.

### Changed

- Composer incorpora `resend/resend-php` 1.10.0 y actualiza Guzzle y CommonMark
  a sus parches compatibles tras detectar advisories nuevos; la auditoría del
  lock vuelve a quedar sin vulnerabilidades conocidas.

- `aria-current="page"` queda reservado a coincidencias exactas; los descendientes mantienen el estado visual de su rama. Legal y activación productiva de Contacto permanecen pendientes de 7D.2, sin enlaces vacíos ni nuevas rutas.
- La carga editorial local de Club se mantiene manual y fuera del repositorio; cada entorno debe revisar y publicar sus páginas, mientras `/nosotros` y `/contenidos/:slug` se conservan hasta acreditar paridad y 7D.2 permanece pendiente.
- ADR-034 sustituye únicamente la decisión inicial de Contacto sin formulario: el CMS conserva el contenido institucional, mientras el formulario usa dominio funcional separado, persiste antes de notificar y permanece bloqueado por privacidad y configuración productiva.
- Los bloques CMS de enlace admiten `mailto:` con dirección válida; media continúa limitada a rutas internas y `http(s)`, y los protocolos peligrosos siguen rechazándose.
- ADR-033 sustituye documentalmente la topología pública plana por Inicio y Competición como enlaces, Aprende y Club como disclosures y Cuenta separada; fija cuatro rutas Club y un footer contractual; su decisión inicial de Contacto sin formulario queda sustituida por ADR-034.
- La API administrativa de temporadas, campeonatos y categorías utiliza Form Requests, persistencia explícita y Resources dedicados, con contratos y permisos de administrador activo verificados.
- Se elimina la asignación no validada de esos CRUD; `is_public` respeta la jerarquía de Blade y los campos protegidos, incluidas imágenes y relaciones, no pueden manipularse mediante payload.
- La API pública de competición excluye las ramas privadas en listados, detalles, relaciones, partidos, rankings, standings, schedules e inicio de inscripciones, manteniendo los contratos serializados.
- La visibilidad efectiva se aplica con scopes locales sin limitar la administración, los servicios internos ni los datos relacionados de Mi Panel.
- El CMS crea las páginas como borrador y exige contenido validado antes de publicarlas.
- `published_at = null` representa publicación inmediata; las fechas futuras se presentan como Programada según la zona horaria configurada por Laravel.
- El panel distingue Borrador, Programada y Publicada y muestra el feedback de las operaciones de bloques.
- El Navbar comparte estructura entre desktop y móvil, representa el área activa en toda la rama deportiva, devuelve el foco al cerrar con Escape y evita la cabecera intermedia en dos filas.
- Torneos, Rankings y las rutas deportivas, CMS e institucionales existentes se conservan, aunque dejan de ocupar el primer nivel público.
- En Fase 3C, la landing mínima `/competicion` reutiliza la estructura común y mantiene su copy y destinos reales sin API ni datos simulados; la 404 conserva identidad propia y reutiliza sólo acciones y metadatos.
- `/competicion` presenta desde 4A la jerarquía pública real de temporadas y campeonatos, conserva Torneos y Rankings en todos los estados y añade semántica, teclado y responsive 320–1440 px sin volver a filtrar la visibilidad decidida por backend.
- Los detalles de campeonato y categoría exponen accesos claros a detalle, clasificación y calendario mediante las rutas deportivas existentes y generadores de URL compartidos, sin rutas nuevas ni cambios de API.
- Torneos, detalles deportivos y Rankings distinguen loading, error recuperable, vacío y contenido; el fallo de un ranking o del contexto no oculta datos independientes ya disponibles.
- El detalle de categoría queda como resumen de la entidad y delega clasificación y calendario en sus vistas dedicadas, eliminando su doble representación.
- Se normalizan los 40 documentos canónicos a un H1 inicial único y una jerarquía H2/H3 coherente, preservando íntegramente texto, títulos, IDs, slugs, versiones y referencias.
- El compilador de Knowledge valida la jerarquía de headings y rechaza referencias desde contenido `Vigente` hacia destinos no vigentes o inexistentes, con diagnóstico contextual de origen y destino.
- `knowledge:check` valida en memoria y de forma determinista los dos artefactos; `knowledge:build` los promueve como pareja y restaura ambos si falla una escritura.
- React importa únicamente la proyección pública mediante una capa de repositorio; el artefacto canónico permanece fuera del bundle.
- Las rutas de Aprende se cargan mediante `React.lazy` y `Suspense`; el corpus público sale del chunk inicial sin alterar rutas, metadatos, Navbar o la 404.
- El repositorio Knowledge devuelve copias de sus colecciones y documentos y resuelve posición y vecinos según el orden canónico de cada colección.

### Fixed

- Hotfix en Fase 7F.2D: el serving privado S3 del avatar de usuario se transmite a través del backend (200 OK) en lugar de usar un redirect cruzado a la URL prefirmada, resolviendo el bloqueo CORS cross-origin del navegador en Mi Panel. Sponsor conserva su redirect S3 directo.
- Se aplicó y validó regla CORS explícita (GET/HEAD) en el bucket S3 media-staging.

- La foto privada en `media_s3` deja de redirigir el XHR Bearer al bucket:
  Laravel transmite el binario con `200`, cabeceras privadas y sin `Location`
  ni URL prefirmada. `media_local` conserva `X-Accel-Redirect` y los logos S3
  de Sponsor mantienen su redirect temporal público. No se relaja CORS ni se
  acepta `Origin: null`; 7F.2D continúa abierta hasta repetir staging.
- Se impide eliminar el último bloque de una página `published` sin despublicarla primero.
- Se amplía la cobertura Feature del flujo editorial, el criterio público compartido y las sesiones administrativas activas.
- El CRUD Blade de Temporadas valida y persiste nombre, estado y fechas nullable, respeta la cronología y selecciona correctamente el enum casteado al editar.
- El CRUD Blade de Campeonatos valida y persiste explícitamente todos los campos no multimedia, recupera correctamente valores y errores, y conserva `image_path` durante la edición.
- El CRUD Blade de Categorías valida y persiste sus campos no multimedia, respeta la relación con Campeonato y los valores nullable, y conserva `image_path` durante la edición.
- Home y el índice CMS evitan landmarks `<main>` duplicados dentro del layout global.
- Las vistas públicas usan etiquetas deportivas coherentes, fechas parciales sin separadores vacíos y posiciones suministradas por backend; las tablas quedan contenidas y navegables en la matriz responsive 320–1440 px.
- Las tarjetas de torneo eliminan el doble CTA al mismo detalle y los partidos regresan al calendario real de su categoría.
- Se remedia la colisión Compose que durante la validación de 6C eliminó el volumen local de desarrollo: se retiran servicios compartidos y nombres fijos, se añaden guardas negativas y una prueba centinela de no destrucción, y se revalida Escuela completa sin reconstruir la base perdida.

El primer candidato MVP continúa pendiente de revisión humana, commit de preparación, etiquetado y publicación.

## 0.1.0-rc.1 — pendiente de publicación

### Added

- **AUDITORÍA PREPARATORIA 7G.3:** Se superó el drill manual de recuperación de producción (dump lógico, snapshot restic cifrado en Google Drive, copia de media y restauración aislada correcta). Se constató la infraestructura backend en Railway (`mariadb-production` activa, `backend-production` pre-provisionada sin despliegues). Se corrigió operativamente la `APP_KEY` a una clave Laravel válida (formato base64, adecuada para AES-256) sin efectuar deployment. El proyecto Vercel productivo `galotxas` está creado (Node.js 22.x, root `frontend`, Vite, `npm ci`, `npm run deploy:build`, `dist`, cinco variables Production-only) pero permanece inerte sin Git, sin dominios y con 0 deployments hasta 7G.5. El gate 7G.3 permanece NO-GO pendiente de pasar la app `Galotxas Backup` a 'In production' (si Google lo permite), revocar token Testing, reautorizar, obtener nuevo token operativo y guardarlo como secret; usuario DB dedicado de backup, secret references, despliegue del job, primer check y backup supervisado, horario/alertas, correo y restantes P0 operativos de 7G.3.
- **COPA STAGING PASS:** Aceptación humana completa y exitosa del flujo y visualización de Copa en el entorno de staging. Se documentan dos mejoras UX de Copa como P1 post-MVP (no bloqueantes). Queda pendiente la regresión global final 7G.2.

- **REGRESIÓN GLOBAL 7G.2 (PASS / CERRADO):** Se ha completado la regresión global sobre el candidato `e2b299cfd7e6d9fa1d59d15d09d177605bcb35ba` en Vercel Staging y Railway Staging. Los hallazgos previos (falso positivo `P0-STAGING-NEWS-DETAIL`, vulnerabilidades npm, contrato `/sitemap.xml` SPA fallback) están cerrados. La suite completa de backend, frontend y recorridos E2E fue superada exitosamente con 0 bloqueos P0/P1 y cobertura SEO, accessibility y legal validada. La evidencia de Railway que devolvía `Unauthorized` también ha sido cerrada tras verificación externa read-only de despliegue exitoso (commit `dfa5f34`). El gate 7G.2 se considera PASS formalmente. El siguiente paso técnico es 7G.3. La Fase 7G.4 NO está iniciada y Producción continúa sin desplegar.

- **RESTORE DRILL 7G.1D:** PASS técnico obtenido en el restore lógico aislado de la MariaDB de staging. Se valida el uso de un dump lógico (con checksum SHA-256) restaurado sobre una base de datos efímera y comprobado estructuralmente, obteniendo un RTO de pared de 5 minutos 27 segundos. Se cierra el P0 de recuperación MariaDB para el entorno de staging sin modificar infraestructura ni usar snapshots nativos. Producción y media se mantienen como gates productivos pendientes.

- **CORRECCIÓN FORWARD-ONLY 7G.1C:** Se verifica operativamente en el workspace que Railway Hobby restringe los backups nativos y PITR al plan Pro (`maxBackupsCount = 0`). La documentación se corrige para desestimar cualquier expectativa de "snapshot nativo" predeploy. La estrategia vigente se apoya en dumps lógicos portables con compresión, cifrado, checksum, copia externa y prueba de restore aislado; Media (Buckets) se gestiona de manera completamente independiente con su propio inventario y copia externa. Esta corrección no muta entornos ni dependencias.

- Autenticación de usuarios, recuperación de contraseña y control de cuentas activas.
- Perfil deportivo, temporadas, campeonatos, categorías, solicitudes de inscripción, asignaciones, participantes y equipos.
- Gestión administrativa Blade de usuarios, jugadores, competiciones, pistas, CMS y conflictos de resultados.
- Generación de liga, copa, final y tercer puesto sobre pistas configuradas.
- Consulta pública de campeonatos, categorías, calendarios, partidos, rankings y contenidos CMS.
- Mi Panel con perfil, inscripciones, partidos, calendario, rankings y acciones pendientes.
- Workflow de resultados con reporte inmutable por lado, confirmación, discrepancia y resolución administrativa.
- Rankings de categoría, campeonato, temporada e histórico con desempates deterministas.
- Tests Laravel sobre MariaDB aislada, Vitest/RTL y smoke Playwright con stack E2E desechable.

### Changed

- MariaDB queda como único motor de base de datos soportado.
- El frontend resuelve la API mediante `VITE_API_BASE_URL`, con fallback local en desarrollo y `/api/v1` en producción.
- Los contratos públicos y de participante utilizan Resources específicos en los contextos críticos del MVP.
- Las dependencias vulnerables se actualizaron dentro de las versiones principales aprobadas y quedaron fijadas en sus lockfiles.
- La documentación técnica y la base `knowledge/` se separaron como fuentes de arquitectura y dominio deportivo.

### Fixed

- Calendario público alineado con el contrato real de jornadas y partidos.
- Navegación pública responsive, semántica de formularios y control móvil del panel administrativo.
- Fechas ausentes de campeonatos sin valores de 1970.
- Invalidación de sesión sin logs de aplicación duplicados para respuestas
  esperadas `401`/`419` y el `403` explícito de usuario inactivo.
- Ruta pública `/torneos` sin placeholder duplicado.
- Limpieza de artefactos Playwright tras ejecuciones satisfactorias y exclusión de `Zone.Identifier`.

### Security

- Tokens Sanctum Bearer y revocación del token actual durante logout.
- Middleware de usuarios y administradores activos.
- Rate limiting de autenticación y escritura de resultados.
- Recuperación de contraseña sin enumeración de emails.
- Resources públicos y de participante sin trazabilidad administrativa ni datos privados innecesarios.
- Auditorías del candidato con 0 vulnerabilidades npm y 0 advisories Composer.

### Known limitations

- El token Bearer permanece en `localStorage`; un `403` ordinario conserva la
  sesión y el `403` de usuario inactivo limpia el token revocado en servidor.
- La reprogramación no tiene interfaz React ni limiter específico.
- La edición del perfil React, los contratos API heredados y varios componentes amplios siguen pendientes de evolución.
- Solo Chromium forma parte del smoke E2E; correo real, TLS, proxy, backups y monitorización no están validados.
- El despliegue productivo no forma parte de este candidato.
- Las limitaciones completas y su clasificación se mantienen en `docs/09-release-candidate.md`.
