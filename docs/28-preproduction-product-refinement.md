# Refinamiento preproducción de producto, navegación y multimedia

## 1. Propósito
Este documento oficializa una intervención documental para registrar decisiones de producto surgidas tras la aceptación inicial del entorno de staging y las pruebas con usuarios reales (testers). Su objetivo es estructurar la promoción de capacidades previamente consideradas Post-MVP (P1/P2) hacia una fase de preproducción controlada, sin reescribir la auditoría histórica ni fingir que siempre formaron parte del alcance original de la Fase 7.

## 2. Origen del refinamiento
Tras validar exhaustivamente el baseline en staging, han surgido necesidades de refinamiento antes de abrir el producto al público en producción. En lugar de retrasarlas hasta después del MVP o implementarlas sin documentar, se agrupan en una fase de ampliación estructurada.

## 3. Baseline actual
- Staging base ha sido desplegado y aceptado mediante smoke test y revisión humana.
- La Fase 7F original (Staging) ha cumplido sus validaciones, salvo restricciones de proveedor (SMTP Hobby y backups nativos bloqueados).
- Producción no está desplegada y el MVP sigue abierto.

Seguimiento 7G.1C (2026-08-24): la segunda restricción conserva valor como
fotografía de la premisa operativa de entonces, pero ya no es el contrato
vigente. La verificación operativa demuestra que Railway restringe backups nativos y PITR al plan Pro. El P0 de MariaDB en staging se cerró con el PASS operativo del restore aislado 7G.1D (RTO: 5m27s), pero producción y media siguen pendientes.
aislado, el rollback rehearsal y la copia independiente de media definida en
el runbook 27.

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
- La regresión en desarrollo comprende 508 tests frontend y 63 E2E sobre el stack aislado; la promoción, el smoke y la aceptación humana de 7F.2A se completaron el 2026-08-15.
- La corrección del prerrequisito no inicia 7F.2A. Los cruces de copa ya generados no se vuelven a sembrar automáticamente y deberán revisarse de forma operativa si se desea regenerarlos.

### 7F.2B — Infraestructura multimedia persistente (Cerrada en staging)
- **Estado**: Auditoría y ADR-043 cerrados; núcleo local, bucket, configuración Railway, probe remoto y persistencia tras redeploy validados en staging.
- Backend incorpora discos privados `media_local` y `media_s3`, perfiles centralizados, normalización JPEG/PNG/WebP con GD/EXIF, keys UUID, servicio común y probe con cleanup.
- `FILESYSTEM_DISK` continúa en `local`; `media_s3` sólo define el contrato `MEDIA_*` y no contiene secrets, bucket o URL hardcodeados.
- 7F.2C fue el primer consumidor, 7F.2D reutiliza el núcleo para el avatar
  privado y 7F.2E lo aplica a portadas públicas de Noticias; CMS aún no lo
  consume.
- Sigue siendo requisito previo ineludible para futuras imágenes del CMS.

### 7F.2C — Patrocinadores/colaboradores administrables
- El nombre histórico “Banners administrables” se especializa sin reescribir la historia: no existe entidad Banner, campaña, placement o plataforma publicitaria.
- Sponsor gestiona en Blade nombre, logo, web HTTPS opcional, orden, activación y ventana temporal; todos los efectivos aparecen simultáneamente en una rejilla discreta antes del footer.
- API y Resources son cerrados; el object key permanece privado y el serving usa la infraestructura de ADR-043 con autorización previa.
- React renderiza `null` en vacío/error/contrato inválido y omite cuenta, token y 404. Los enlaces usan `rel="sponsored noopener noreferrer"`.
- Implementación y regresión automática completadas en develop.
- **Estado**: implementada, promovida a staging y completamente aceptada de manera manual. 7F.2C cerrada.
- **Validado manualmente en staging**: el flujo base superó la migración y prueba inicial. Todos los gates secundarios pospuestos han sido validados exitosamente:
  - **Temporalidad**: `starts_at` (inclusivo) y `ends_at` (exclusivo) controlan efectivamente la aparición sin re-guardado.
  - **Persistencia**: redeploy no rompe el serving ni los registros; el logo se sirve del bucket correctamente.
  - **Borrado**: limpieza física confirmada tras la eliminación administrativa.
  - **Responsive**: franja adaptada y legible a 320 px sin scroll horizontal ni traslapes.
  - **Accesibilidad**: orden de tabulación lógico, foco visible y comportamiento `Enter` adecuado sin foco muerto.

### 7F.2D — Foto de perfil privada de Usuario (Aceptada completamente en staging / cerrada)
- **Modelo/Privacidad**: Reutiliza User.profile_photo_path sin exponer key/URL en APIs públicas. Aplica a menores y adultos; no amplía public_competition_identity. Fallback visual por iniciales.
- **API Privada**: POST/DELETE/GET autenticados sin ID en URL. Lifecycle seguro (store -> commit -> cleanup antiguo) con compensación de fallos. Normalización JPEG/PNG/WebP, máx 3 MB, resize/re-encode y sin EXIF/GPS.
- **Frontend (Mi Panel)**: UI de subida, sustitución, borrado y preview. Descarga autenticada como blob con URLs revocables, sin persistir en localStorage. Aviso explícito de privacidad.
- **Incidente Staging y Hotfix CORS**:
  - Se detectó un bloqueo CORS cross-origin en navegador (frontend -> API -> 302 -> presigned URL S3).
  - Se configuró la regla CORS GET/HEAD en el bucket media-staging.
  - **Hotfix**: El serving privado S3 ahora streamea la imagen a través de Laravel (200 OK) evitando el redirect, mientras Sponsor conserva su redirect 302 a presigned URL.
  - Validación local automática del hotfix: 501 tests backend, 550 tests frontend, 65 tests E2E y análisis estático superados.
- **Validado manualmente en staging**: UI visible en Mi Panel, upload real hacia media-staging, hotfix desplegado resolviendo CORS, renderizado correcto de la foto, replace y delete básicos operativos.
- **Gates secundarios cerrados en staging**: cleanup post-replace/delete,
  persistencia post-redeploy, revisión móvil/accesible y revisión de logs
  completados. El bloque está aceptado y no requiere repetir escrituras en la
  regresión global salvo que un cambio o una incidencia invalide esa evidencia.

### 7F.2E — Noticias
- **Estado**: implementada, promovida y aceptada manualmente en staging. 7F.2E cerrada.
- `NewsArticle` dedicado, separado de `CmsPage`/`CmsBlock` y de
  `/contenidos/prensa-media`, conforme a ADR-044.
- Administración Blade con borrador, programación, publicación efectiva,
  slug protegido, soft delete y gate de imagen/alt/procedencia/derechos.
- Portadas sobre el núcleo 7F.2B con normalización `news_cover`, lifecycle
  transaccional/compensatorio y serving estable local o S3 público temporal.
- API pública paginada de 12, detalle por slug y Resources cerrados; React
  aporta `/noticias`, detalle lazy, `Cargar más`, estados remotos, responsive y
  navegación top-level estructural.
- SEO client-side con canonical, OG article y JSON-LD en detalles válidos;
  `/noticias` entra en sitemap.
- Regresión local final: 526 tests backend, 601 frontend y 66 E2E, además de
  análisis estático, build Vite e imagen Docker de producción.
- **Incidente de migración**: Tras el deploy exitoso, los endpoints devolvieron error 500 por tabla `news_articles` ausente. Se verificó que el deploy no aplica migraciones automáticamente. Se resolvió con `migrate --force` y validación posterior.
- **Validado manualmente en staging**: migración explícita, administración, alta/edición, publicación efectiva, imágenes funcionales tras migración y navegación pública.
- **Regla editorial (no técnica)**: Las imágenes reales publicadas deben tener procedencia/derechos/autorizaciones verificables; fotos con personas/menores requieren procedimiento. El checkbox administrativo es sólo registro de verificación.
- **Deuda P1 (sin reabrir 7F.2E)**: Sitemap dinámico runtime de slugs, metadata client-side, sin SSR/prerender dinámico.

### 7F.2F — Navegación CMS administrable
- **Estado**: implementada, promovida y aceptada manualmente en staging. 7F.2F cerrada.
- `CmsNavigationItem` usa un único slot DB/PHP `club`, nace inactivo, relaciona
  una página una sola vez y deriva siempre `/contenidos/{slug}`.
- Blade administra página, etiqueta, orden y activación sin URL manual. Las
  páginas estructurales `nosotros`, `contacto`, `federarse` y `documentos` no
  son asignables y continúan primero en Club.
- La API publica únicamente placements activos con página efectivamente
  publicada y una allowlist de slot, etiqueta, URL y orden. Draft, fecha
  futura, reservado o etiqueta inválida desaparecen.
- React valida fail-closed, hace una petición por montaje y compone sin mutar
  el árbol. Error, vacío o payload inválido conserva navegación estructural.
- Home, footer, Cuenta, Legal, Noticias, Competición y Aprende quedan fuera;
  `/contenidos/:slug` sigue `noindex` y el sitemap no cambia.
- ADR-045 registra la decisión.
- **Validado manualmente en staging**: el flujo funcional completo ha sido probado con éxito (crear página CMS temporal, publicarla, acceder a ruta, crear placement inactivo, activarlo, verificar aparición y navegación en Club, retiro y eliminación del placement con desaparición exitosa en frontend). Comportamiento fail-closed y navegación estructural preservados.
- **Hallazgo no bloqueante / Deuda P1**: El CMS no dispone actualmente de borrado administrativo de páginas `CmsPage`. No invalida la fase porque el placement de navegación sí puede eliminarse y la página retirarse. La futura solución auditará páginas reservadas, bloques, navegación, SEO, URLs, aliases e integridad referencial, sin inventar soft delete en este momento.

### 7F.2 (GAP) — Flujo de Copa y Resultados
- **Estado**: corregido y validado localmente tras 7F.2F; pendiente de smoke y aceptación humana en staging. Continúa bloqueando Producción hasta superar ese gate.
- **Caracterización**: el supuesto defecto general de persistencia no se reprodujo. La escritura directa Blade, los reportes coincidentes y la resolución de conflictos ya persistían y reaparecían correctamente en admin. Sí se reprodujo el descarte silencioso de tanteos combinados con `scheduled`, corregido mediante validación común a Liga y Copa.
- **Backend**: semifinal, Final y tercer puesto registran `phase=cup` y stages explícitos; Final/3.º-4.º exigen exactamente dos semifinales validadas, con tanteos completos y sin empate, conservan los emparejamientos correctos y nacen sin programación manual inventada.
- **API**: el schedule existente publica fases, stages y `winner_entry` oficial allowlisted, con orden estable, tanteos sólo validados, precarga de relaciones y ausencia de trazabilidad privada. No se crea endpoint nuevo.
- **Frontend**: `/categories/{id}/schedule` queda reservado a Liga y la nueva ruta diferida `/categories/{id}/cup` presenta semifinales, estados pendientes, Final, tercer puesto y campeón desde `winner_entry`. La navegación local ofrece Resumen, Clasificación, Calendario y resultados y Copa; un stage desconocido o una Copa legada sin `phase/stage` se omite de forma cerrada. No se calcula el ganador en React.

### Mejoras UX de Copa (P1 No Bloqueantes)
- **P1-UX-COPA-1 (Resumen final de categoría)**: Mostrar podio de Liga y campeón/subcampeón de Copa en la vista de Resumen cuando una categoría haya concluido. Pendiente de definir semántica y acción administrativa para "Finalizar campeonato".
- **P1-UX-COPA-2 (Presentación del campeón)**: En `/categories/{id}/cup`, mejorar la jerarquía visual del bloque del campeón para que no parezca un botón interactivo y adquiera semántica de resultado final, sin romper accesibilidad.

- **Regresión**: pruebas backend, contrato/frontend y un E2E aislado cubren Liga completa, ambos workflows de semifinal, resolución Blade, generación, programación, resultados, navegación por teclado, enlace al partido, campeón y 320 px con cleanup. El gate local refinado completa 557 tests backend y 4.314 aserciones, 659 tests frontend y 68 escenarios Chromium.
- La inclusión de partidos de Copa en rankings agregados de campeonato, temporada e histórico es el contrato aprobado; el ranking de categoría y Mi Panel permanecen limitados a Liga. No existe bonus por semifinal, Final, tercer puesto o campeón.

## 6. Dependencias y decisiones
- **Decisiones cerradas**: Sustitución parcial de la navegación de Competición (ADR-042); obligatoriedad de almacenamiento de objetos (sin sistema de archivos efímero) para persistencia; utilización de `User.profile_photo_path` para fotos de perfil; `NewsArticle` dedicado y separado del CMS/prensa-media (ADR-044).
- **Prerrequisito cerrado de Rankings**: una única regla backend distribuye tres puntos base por partido (`3-0` o `2-1`) antes de contribuciones de dobles y multiplicadores de nivel; no existe persistencia ni backfill de puntos.
- **Verificación operativa del prerrequisito**: el reparto corregido se comprobó manualmente en staging el 2026-08-15. Esta evidencia corresponde al prerrequisito de dominio y no acredita despliegue, smoke ni aceptación de 7F.2A.
- **Decisiones abiertas**: Proveedor de CDN; cualquier slot futuro distinto de
  Club; sitemap dinámico runtime de noticias; cualquier consentimiento
  fotográfico general o publicación futura de avatares sigue fuera de
  7F.2D/7F.2E.

## 7. Privacidad y Seguridad
La filosofía fail-closed se mantiene:
- La autorización para perfiles públicos de menores NO incluye por defecto autorización de difusión de fotografía. Requiere un gate de consentimiento explícito e independiente.
- Las imágenes subidas por usuarios no controlarán sus rutas locales directamente (prevención de path traversal).
- No se incorporan rastreadores publicitarios mediante los colaboradores.

## 8. Ciclo de pruebas y compatibilidad
El ciclo de desarrollo deberá seguir la pauta:
`desarrollo → tests dirigidos → regresión completa → staging → smoke → beta/pruebas manuales → aceptación del nuevo baseline → producción`.

## 9. Relación con 7F Producción y 7G
El despliegue en Producción (7F) queda **suspendido** hasta la compleción y validación estricta de toda la Fase 7F.2 en Staging. 7F.2A, 7F.2B y 7F.2C ya se cerraron allí; 7F.2D está aceptada completamente en staging y cerrada; 7F.2E y 7F.2F están cerradas y aceptadas en staging. El cierre del gap de Copa ha superado la aceptación humana completa en staging. El cierre del MVP (7G) ocurrirá posteriormente. La deuda técnica de post-MVP descrita en el roadmap (p. ej. aplicación móvil, pasarela de pago, migración auth) sigue fuera del alcance.

## 10. Checklist observable
- [x] 7F.2A implementado y validado automáticamente en `develop`.
- [x] Auditoría 7F.2B completada y ADR-043 formalizado.
- [x] 7F.2B.1 núcleo local, runtime, normalización, storage y probe implementados y validados.
- [x] 7F.2B infraestructura multimedia S3 implementada, validada (persistencia tras redeploy) y gate superado.
- [x] 7F.2C patrocinadores funcionales y regresión automática en `develop`.
- [x] 7F.2C completar programación, redeploy, borrado y revisión móvil/accesible en staging.
- [x] 7F.2D foto privada de usuario gestionable y regresión local en `develop`.
- [x] 7F.2D flujo principal aceptado manualmente en staging tras hotfix.
- [x] 7F.2D completar gates secundarios diferidos en staging.
- [x] 7F.2E noticias navegables y regresión local completa.
- [x] 7F.2E migración, storage real y aceptación humana en staging.
- [x] 7F.2F enlaces de menú CMS implementados y validados localmente.
- [x] 7F.2F migración, smoke, redeploy y aceptación humana en staging.
- [x] Gap de Copa implementado y validado localmente de extremo a extremo.
- [x] Copa separada en vista pública propia y contrato de rankings reforzado localmente.
- [x] Flujo de Copa aceptado manualmente en staging.
- [ ] Regresión global final de 7F.2 y aceptación humana del baseline integrado
      superadas después de aceptar Copa.

La preparación y el orden del gate posterior se documentan en
`29-mvp-final-acceptance-and-production-gate.md`. 7G permanece sin cerrar y no
se ha iniciado su gate irreversible.


## 11. Mejoras futuras (Post-MVP)

Las siguientes propuestas han sido concebidas durante la Fase 7F.2 pero **NO** forman parte del alcance actual (no alteran 7F.2E ni 7F.2F) y se difieren a una evolución posterior del producto:

### 11.1 Patrocinios Contextuales (Evolución de Sponsor)
- **Campeonatos Patrocinados**: Asociación (N:M) de un campeonato con uno o varios patrocinadores, mostrando su identidad en detalles y clasificaciones sin duplicar datos en el modelo de campeonato.
- **Pistas Patrocinadas**: Asociación comercial temporal de una pista (ej. "Pista OpenAI"), preservando la identidad estructural y técnica original para no romper referencias históricas ni estadísticas.
- **Diseño**: Podría requerir relaciones N:M o un modelo Sponsorship con metadatos contractuales (vigencia, prioridad). Se mantendrá la filosofía sin trackers ni scripts de terceros.

### 11.2 Perfil Público Deportivo de Jugador
- **Ficha Pública**: Espacio opcional para mostrar nombre, apodo, palmarés, categorías y estadísticas.
- **Privacidad y Menores**: Requerirá consentimientos independientes, trato diferenciado para menores (fail-closed) y un sistema de alias.
- **Fotografía**: La foto de perfil privada (7F.2D) **NO** se reutilizará automáticamente; publicarla exigirá una autorización explícita separada.

### 11.3 Borrado administrativo de páginas CMS
- **Política de integridad**: Capacidad de borrado/retirada administrativa de `CmsPage` evaluando referencias, bloques asociados, `CmsNavigationItem`, URLs publicadas, posibles aliases/fachadas, SEO y seguridad.
- **Trazabilidad**: Definir estrategia definitiva (soft delete vs delete real).
- **Alcance actual**: No implementar ahora. No inventar soft delete sin política general. Las páginas reservadas deberán estar auditadas y protegidas en la solución futura.
