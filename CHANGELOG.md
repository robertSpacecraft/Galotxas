# Changelog

Este archivo registra los cambios relevantes de Galotxas. La estructura sigue de forma simplificada [Keep a Changelog](https://keepachangelog.com/) y las versiones propuestas siguen SemVer.

## Unreleased

### Changed

- El CMS crea las páginas como borrador y exige contenido validado antes de publicarlas.
- `published_at = null` representa publicación inmediata; las fechas futuras se presentan como Programada según la zona horaria configurada por Laravel.
- El panel distingue Borrador, Programada y Publicada y muestra el feedback de las operaciones de bloques.

### Fixed

- Se impide eliminar el último bloque de una página `published` sin despublicarla primero.
- Se amplía la cobertura Feature del flujo editorial, el criterio público compartido y las sesiones administrativas activas.
- El CRUD Blade de Temporadas valida y persiste nombre, estado y fechas nullable, respeta la cronología y selecciona correctamente el enum casteado al editar.
- El CRUD Blade de Campeonatos valida y persiste explícitamente todos los campos no multimedia, recupera correctamente valores y errores, y conserva `image_path` durante la edición.
- El CRUD Blade de Categorías valida y persiste sus campos no multimedia, respeta la relación con Campeonato y los valores nullable, y conserva `image_path` durante la edición.

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
