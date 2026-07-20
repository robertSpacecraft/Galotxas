# Changelog

Este archivo registra los cambios relevantes de Galotxas. La estructura sigue de forma simplificada [Keep a Changelog](https://keepachangelog.com/) y las versiones propuestas siguen SemVer.

## Unreleased

### Added

- Se incorpora la base administrativa `is_public` para temporadas, campeonatos y categorías, con nuevos registros privados, backfill compatible y validación jerárquica sin cascadas; la API pública todavía no filtra por este campo.
- Se documenta el contrato de navegación pública de Fase 3A: cinco áreas canónicas, fuentes de verdad, rutas secundarias, compatibilidad heredada y gates de implementación, sin cambios visibles en Navbar ni nuevas landings.
- Se incorpora la navegación pública progresiva de Fase 3B con configuración única para Inicio y Competición, cuenta separada y landing mínima `/competicion` enlazada a Torneos y Rankings.
- Se añade una experiencia 404 de React Router con enlaces de recuperación, sin redirects ni cambios de hosting.
- Se incorpora el sistema común de landings públicas de Fase 3C con contenedor, cabecera, acciones, secciones, rejilla y tarjetas-enlace desacoplados de las fuentes de contenido.
- Se añaden metadatos básicos reversibles por ruta para Competición y 404, semántica y teclado cubiertos y una matriz responsive de 320 a 1440 px, cerrando técnicamente la Fase 3 sin publicar nuevas áreas.
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

### Changed

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

### Fixed

- Se impide eliminar el último bloque de una página `published` sin despublicarla primero.
- Se amplía la cobertura Feature del flujo editorial, el criterio público compartido y las sesiones administrativas activas.
- El CRUD Blade de Temporadas valida y persiste nombre, estado y fechas nullable, respeta la cronología y selecciona correctamente el enum casteado al editar.
- El CRUD Blade de Campeonatos valida y persiste explícitamente todos los campos no multimedia, recupera correctamente valores y errores, y conserva `image_path` durante la edición.
- El CRUD Blade de Categorías valida y persiste sus campos no multimedia, respeta la relación con Campeonato y los valores nullable, y conserva `image_path` durante la edición.
- Home y el índice CMS evitan landmarks `<main>` duplicados dentro del layout global.
- Las vistas públicas usan etiquetas deportivas coherentes, fechas parciales sin separadores vacíos y posiciones suministradas por backend; las tablas quedan contenidas y navegables en la matriz responsive 320–1440 px.
- Las tarjetas de torneo eliminan el doble CTA al mismo detalle y los partidos regresan al calendario real de su categoría.

El primer candidato MVP continúa pendiente de revisión humana, commit de preparación, etiquetado y publicación.

## 0.1.0-rc.1 — pendiente de publicación

### Added

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
- Invalidación de sesión sin logs de aplicación duplicados para respuestas esperadas 401/403.
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

- El token Bearer permanece en `localStorage` y cualquier 403 autenticado conserva el cierre global vigente.
- La reprogramación no tiene interfaz React ni limiter específico.
- La edición del perfil React, los contratos API heredados y varios componentes amplios siguen pendientes de evolución.
- Solo Chromium forma parte del smoke E2E; correo real, TLS, proxy, backups y monitorización no están validados.
- El despliegue productivo no forma parte de este candidato.
- Las limitaciones completas y su clasificación se mantienen en `docs/09-release-candidate.md`.
