# Roadmap — Galotxas

## Propósito

Este documento representa el estado y el orden oficial de evolución del proyecto. Es dinámico: no sustituye la documentación funcional ni conserva el detalle de cada implementación ya cerrada.

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
- formularios públicos de federación/academy con antispam;
- SEO y ordenación editorial avanzados del CMS;
- métricas y filtros administrativos avanzados;
- aplicación móvil y API administrativa consolidada.

---

# Deuda técnica conocida

## API y seguridad

- estudiar la migración de Bearer en `localStorage` a cookies `HttpOnly`/`SameSite` con CSRF;
- normalizar envelopes, errores, paginación y serialización heredada;
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
