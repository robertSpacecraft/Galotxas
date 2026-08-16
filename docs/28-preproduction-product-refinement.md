# Refinamiento preproducción de producto, navegación y multimedia

## 1. Propósito
Este documento oficializa una intervención documental para registrar decisiones de producto surgidas tras la aceptación inicial del entorno de staging y las pruebas con usuarios reales (testers). Su objetivo es estructurar la promoción de capacidades previamente consideradas Post-MVP (P1/P2) hacia una fase de preproducción controlada, sin reescribir la auditoría histórica ni fingir que siempre formaron parte del alcance original de la Fase 7.

## 2. Origen del refinamiento
Tras validar exhaustivamente el baseline en staging, han surgido necesidades de refinamiento antes de abrir el producto al público en producción. En lugar de retrasarlas hasta después del MVP o implementarlas sin documentar, se agrupan en una fase de ampliación estructurada.

## 3. Baseline actual
- Staging base ha sido desplegado y aceptado mediante smoke test y revisión humana.
- La Fase 7F original (Staging) ha cumplido sus validaciones, salvo restricciones de proveedor (SMTP Hobby y backups nativos bloqueados).
- Producción no está desplegada y el MVP sigue abierto.

## 4. No reescritura histórica
Las decisiones de las fases 7A, 7B y 7D relativas a Competición, Prensa y Multimedia (que postergaban estos aspectos) se mantienen como registro válido de su momento. Este documento no invalida esa historia; actúa como addendum vinculante de promoción de alcance. El baseline de staging aceptado sigue siendo válido y requerirá una **nueva validación** tras incorporar estos bloques.

## 5. Alcance promovido (Fase 7F.2)
Las siguientes capacidades pasan a formar parte de los requisitos preproducción:

### 7F.2A — Rankings y navegación de Competición (Cerrada en staging)
- **Estado**: Implementada, promovida y aceptada manualmente en staging el 2026-08-15 sin incidencias funcionales.
- Prerrequisito de dominio ya cerrado antes de iniciar el bloque: reparto base único `3-0` si quien pierde suma menos de 8 juegos y `2-1` si suma 8 o más, siempre con tres puntos totales. Los rankings históricos se recalculan dinámicamente desde partidos validados.
- Implementado en `develop` el 2026-08-15: `/rankings` es el centro público con vistas de Histórico (por defecto), Temporada, Campeonato y Categoría sobre los contratos públicos existentes, sin cálculos deportivos en React.
- Implementado en `develop` el disclosure `Competición` con Vista general, Campeonatos y Rankings, compartido por desktop y móvil y documentado en ADR-042.
- Los selectores siguen la jerarquía Temporada → Campeonato → Categoría, invalidan hijos y respuestas obsoletas al cambiar un padre y distinguen carga, error recuperable, vacío y contenido.
- La regresión en desarrollo comprende 508 tests frontend y 63 E2E sobre el stack aislado. La promoción, el smoke y la aceptación humana del nuevo baseline de staging siguen pendientes.
- La corrección del prerrequisito no inicia 7F.2A. Los cruces de copa ya generados no se vuelven a sembrar automáticamente y deberán revisarse de forma operativa si se desea regenerarlos.

### 7F.2B — Infraestructura multimedia persistente (Núcleo local implementado; bloque abierto)
- **Estado**: Auditoría y ADR-043 cerrados; 7F.2B.1 implementa el núcleo local y sus tests. Bucket, configuración Railway, probe remoto y gate de staging NO realizados.
- Backend incorpora discos privados `media_local` y `media_s3`, perfiles centralizados, normalización JPEG/PNG/WebP con GD/EXIF, keys UUID, servicio común y probe con cleanup.
- `FILESYSTEM_DISK` continúa en `local`; `media_s3` sólo define el contrato `MEDIA_*` y no contiene secrets, bucket o URL hardcodeados.
- Banners, Avatar, Noticias y CMS no consumen todavía la infraestructura. El cierre de 7F.2B exige object storage S3-compatible separado del filesystem efímero y evidencia real en staging.
- Sigue siendo requisito previo ineludible para banderas visuales y noticias.

### 7F.2C — Banners administrables
- CRUD en Blade para gestión promocional interna sin React.
- Sin inclusión de scripts, trackers o dependencias publicitarias de terceros (seguridad fail-closed).

### 7F.2D — Foto de perfil de Usuario
- Reutilización segura de `User.profile_photo_path` sin duplicar estado en `Player`.
- Capacidad de subida, sustitución, fallback y visualización en Mi Panel.
- **Protección de menores**: la foto existirá en la cuenta, pero su proyección en perfiles públicos deportivos estará condicionada por políticas específicas de consentimiento (independientes de la actual `public_competition_identity`).

### 7F.2E — Noticias
- Creación de entidad/arquitectura editorial (ya sea `NewsArticle` o especialización CMS) para gestionar listado cronológico, extractos y detalle.
- Separación estricta frente al dominio previo de `prensa-media`.

### 7F.2F — Navegación CMS administrable
- Capacidad limitada y validada para que Blade asigne páginas del CMS a slots controlados de navegación.
- Protección estricta de las rutas de producto y la estructura del enrutador React.

## 6. Dependencias y decisiones
- **Decisiones cerradas**: Sustitución parcial de la navegación de Competición (ADR-042); obligatoriedad de almacenamiento de objetos (sin sistema de archivos efímero) para persistencia. Utilización de `User.profile_photo_path` para fotos de perfil.
- **Prerrequisito cerrado de Rankings**: una única regla backend distribuye tres puntos base por partido (`3-0` o `2-1`) antes de contribuciones de dobles y multiplicadores de nivel; no existe persistencia ni backfill de puntos.
- **Verificación operativa del prerrequisito**: el reparto corregido se comprobó manualmente en staging el 2026-08-15. Esta evidencia corresponde al prerrequisito de dominio y no acredita despliegue, smoke ni aceptación de 7F.2A.
- **Decisiones abiertas**: Proveedor de CDN o modelo S3 concreto; modelo exacto de datos para noticias (`NewsArticle` vs `CmsPage`); configuración exacta de los slots del menú.

## 7. Privacidad y Seguridad
La filosofía fail-closed se mantiene:
- La autorización para perfiles públicos de menores NO incluye por defecto autorización de difusión de fotografía. Requiere un gate de consentimiento explícito e independiente.
- Las imágenes subidas por usuarios no controlarán sus rutas locales directamente (prevención de path traversal).
- No se incorporan rastreadores publicitarios mediante los banners.

## 8. Ciclo de pruebas y compatibilidad
El ciclo de desarrollo deberá seguir la pauta:
`desarrollo → tests dirigidos → regresión completa → staging → smoke → beta/pruebas manuales → aceptación del nuevo baseline → producción`.

## 9. Relación con 7F Producción y 7G
El despliegue en Producción (7F) queda **suspendido** hasta la compleción y validación estricta de toda la Fase 7F.2 en Staging. (Nota: 7F.2A ya se cerró en staging; faltan 7F.2B–7F.2F). El cierre del MVP (7G) ocurrirá posteriormente. La deuda técnica de post-MVP descrita en el roadmap (p. ej. aplicación móvil, pasarela de pago, migración auth) sigue fuera del alcance.

## 10. Checklist observable
- [x] 7F.2A implementado y validado automáticamente en `develop`.
- [x] Auditoría 7F.2B completada y ADR-043 formalizado.
- [x] 7F.2B.1 núcleo local, runtime, normalización, storage y probe implementados y validados.
- [x] 7F.2B infraestructura multimedia S3 implementada, validada (persistencia tras redeploy) y gate superado.
- [ ] 7F.2C banners funcionales.
- [ ] 7F.2D avatar de usuario gestionable.
- [ ] 7F.2E noticias navegables.
- [ ] 7F.2F enlaces de menú CMS asignables.
- [ ] Promoción a Staging y nueva aceptación humana (beta) superadas. (7F.2A verificada el 2026-08-15)
