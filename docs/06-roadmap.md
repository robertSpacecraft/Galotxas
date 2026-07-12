# Roadmap — Galotxas

## Propósito

Este documento representa el plan oficial de evolución del proyecto.

A diferencia del resto de documentos de `/docs`, este archivo es dinámico y deberá actualizarse conforme avance el desarrollo.

No describe el funcionamiento del sistema; únicamente indica el estado del proyecto, la deuda técnica conocida y el orden recomendado de implementación.

---

# Estado actual

## Completado

### Documentación

- Estructura documental reorganizada.
- AGENTS global y específicos.
- Guías de estilo para backend y frontend.
- Glosario.
- Dominio.
- Arquitectura.
- Contrato API.
- Panel administrativo.
- Estrategia de testing.
- ADR.
- Estrategia de Resources.

### Backend

- Autenticación.
- Recuperación segura de contraseña.
- Logout API.
- Usuarios activos en API.
- Usuarios activos en panel Blade.
- Rate limiting.
- Flujo completo de inscripción (solicitudes, aprobación, asignación).
- Gestión administrativa de solicitudes (`/admin/registration-requests`).
- Gestión de temporadas, campeonatos y categorías.
- Equipos de dobles.
- Generación de competición, partidos y resultados.
- Rankings y API privada ("Mi Panel").
- Separación de Resources públicos.
- VENUE-1: CRUD administrativo de pistas, borrado seguro y seeder explícito no destructivo.
- SCHEDULE-1: generación de ligas con pistas dinámicas, deterministas y sin IDs mágicos.
- CMS-1: base backend pública de páginas CMS y bloques controlados.
- CMS-2: gestión admin básica de páginas CMS.
- CMS-3: gestión admin básica de bloques CMS.
- CMS-4: renderizado público de páginas CMS en React.
- CMS-5: índice público de páginas CMS publicadas.
- CMS-6: navegación pública institucional hacia páginas CMS.

### Frontend

- React básico.
- Panel privado.
- Detalle de partido React unificado para consulta pública y workflow autenticado de resultados.

### Infraestructura

- Docker.
- MariaDB como único motor soportado.
- Entorno de pruebas aislado.

---

# Fase 0 — Validación funcional

Objetivo:

Verificar manualmente todos los flujos principales desde la perspectiva del usuario y del administrador.

Estado:

Parcialmente completada.

Pendientes:

- continuar validando UX conforme evolucionen nuevas funcionalidades;
- revisar consistencia del panel administrativo.

---

# Fase 1 — Consolidación técnica

Objetivo:

Eliminar deuda técnica inmediata sin modificar el comportamiento funcional.

Pendientes prioritarios:

- homogeneidad de respuestas API;
- normalización progresiva mediante Resources;
- revisión de controladores con lógica excesiva;
- limpieza de rutas duplicadas;
- revisión de seguridad restante.

---

# Fase 2 — Panel del participante

Objetivo:

Completar la experiencia privada del usuario.

Incluye:

- Mi Panel;
- calendario;
- partidos;
- rankings;
- estado de inscripciones;
- validación UX.

---

# Fase 3 — Panel administrativo

Objetivo:

Mejorar productividad del administrador.

Estado:
En progreso.

Líneas previstas:

- [x] Tareas pendientes en el dashboard (Bloque 1).
- [x] Acciones rápidas (Bloque 2).
- [x] Vista de huérfanos sin categoría (Bloque 3).
- [x] Asignación rápida de categoría en la sección específica de solicitudes (Bloque 4).
- [ ] Métricas y filtros avanzados (Bloque 5).

---

# Fase 4 — Contrato API

Objetivo:

Congelar el contrato público.

Incluye:

- normalización del envelope;
- revisión de nombres;
- serialización;
- paginación;
- errores homogéneos;
- documentación OpenAPI futura.

---

# Fase 5 — Cobertura de pruebas

Objetivo:

Endurecer la cobertura antes de considerar el proyecto funcionalmente estable.

Incluye:

- auditoría de cobertura;
- Services;
- Resources;
- Middleware;
- Policies;
- flujos completos;
- frontend cuando la interfaz se estabilice;
- regresiones.

---

# Fase 6 — Funcionalidades futuras (Competición)

No prioritarias actualmente:

- pagos online;
- pasarela de pago;
- sugerencia automática de categoría;
- asignación automática;
- notificaciones;
- mejoras avanzadas de rankings;
- aplicación móvil;
- API administrativa completa.

---

# Mini-fase de cierre competitivo

Objetivo:

Antes de implementar CMS/contenidos públicos, realizar una revisión técnica orientada al cierre competitivo de la primera fase.

Pendientes prioritarios:

1. **Cerrar Mi Panel React** (completado):
   - [x] adaptar calendario al contrato agrupado por días;
   - [x] adaptar rankings privados a los objetos championship/category;
   - [x] corregir estados de inscripción pending/approved/rejected;
   - [x] corregir getRankings() para consumir el payload funcional;
   - [x] reducir la duplicidad entre clientes Axios dejando `api.js` como alias compatible de `client.js`.
2. **Finales de copa con status enum** (corregido):
   - `GameMatch.status` está casteado a enum;
   - GenerateCupService::generateFinals() compara contra GameMatchStatus::VALIDATED y queda cubierto por test de regresión.
3. **Revisar/documentar estrategia de autenticación/token** (completado en C3):
   - [x] mantener bearer token en `localStorage` como estrategia MVP actual;
   - [x] logout con revocación backend y limpieza local garantizada;
   - [x] limpieza de `token` y `user` ante `401`/`403`;
   - [x] documentar como deuda futura una posible migración a cookie HttpOnly/SameSite/CSRF en bloque específico.
4. **MATCH-1 — Unificar flujo React de resultados** (completado):
   - [x] conectar `/matches/{id}` con el workflow backend de resultados;
   - [x] consolidar envío, confirmación, conflicto y estados cerrados en una única experiencia visible;
   - [x] mantener reprogramación fuera del alcance de este bloque.
5. **SEC-MATCH-1 — Aislar el workflow privado de resultados** (completado):
   - [x] mantener el detalle público mediante `PublicMatchResource` para usuarios sin perfil o ajenos al partido;
   - [x] crear contratos mínimos `ParticipantMatchResource` y `ParticipantMatchResultReportResource`;
   - [x] eliminar emails, usuarios completos, reportes agregados y trazabilidad interna del workflow del participante;
   - [x] sanear también las respuestas de envío y confirmación;
   - [x] cubrir seguridad, autorización funcional y dobles con tests Feature.
6. **DEPLOY-1 — Configurar la URL API del frontend por entorno** (completado):
   - [x] usar `VITE_API_BASE_URL` desde el cliente Axios central;
   - [x] eliminar espacios exteriores del valor configurado;
   - [x] mantener localhost únicamente como fallback de desarrollo;
   - [x] usar `/api/v1` como fallback de producción para despliegues bajo el mismo dominio;
   - [x] validar builds con y sin variable explícita.
7. **VENUE-1 — Gestión básica y configuración reproducible de pistas** (completado):
   - [x] CRUD Blade protegido para listar, crear, editar y eliminar pistas;
   - [x] validación de nombre único y longitudes sobre los campos reales;
   - [x] bloqueo de borrado cuando existen partidos o solicitudes de reprogramación;
   - [x] `DefaultVenueSeeder` explícito, idempotente y basado en nombres estables;
   - [x] tests Feature de permisos, validación, CRUD, borrado y seeder;
   - [x] SCHEDULE-1: sustituir la selección heredada de IDs por todas las pistas existentes en orden estable.
8. **SCHEDULE-1 — Generación reproducible sin IDs mágicos** (completado):
   - [x] eliminar filtros por IDs y nombres concretos de pistas;
   - [x] consultar una sola vez todas las pistas en orden por ID;
   - [x] fallar con mensaje accionable cuando no existen pistas;
   - [x] conservar siete huecos por pista y jornada sin colisiones;
   - [x] revertir atómicamente la generación si la capacidad resulta insuficiente;
   - [x] cubrir IDs arbitrarios, nombres personalizados, una y varias pistas, individuales, dobles y regeneración.

---

# Fase CMS — Contenidos públicos

Objetivo:

Dotar al sistema de una parte pública administrable, independiente del sistema competitivo.

Estado:

En progreso.

Esta fase incluirá:

0. **Base backend pública de contenidos controlados (CMS-1)**:
   - [x] modelo de página pública con `slug`, título, estado y SEO mínimo;
   - [x] modelo de bloques controlados con orden y datos JSON;
   - [x] endpoint público `GET /api/v1/cms/pages/{slug}`;
   - [x] Resources públicos para página y bloques;
   - [x] tests de publicación, borrador, fecha futura, inexistente, orden y ocultación de campos internos.
1. **Gestión admin básica de páginas CMS (CMS-2)**:
   - [x] listado de páginas CMS en Blade;
   - [x] creación y edición de páginas CMS;
   - [x] gestión de estado `draft`/`published` y `published_at`;
   - [x] campos SEO mínimos;
   - [x] enlace desde navegación admin;
   - [x] tests de listado, creación, edición, slug único y acceso no admin.
2. **Gestión admin básica de bloques CMS (CMS-3)**:
   - [x] listado de bloques dentro del detalle de página CMS;
   - [x] creación, edición y eliminación de bloques;
   - [x] validación mínima de `data` por tipo de bloque;
   - [x] orden manual mediante `sort_order`;
   - [x] protección frente a bloques de otra página;
   - [x] tests de CRUD, orden, validación, autorización y salida pública.
3. **Renderizado público de páginas CMS en React (CMS-4)**:
   - [x] servicio frontend para `GET /api/v1/cms/pages/{slug}`;
   - [x] ruta pública `/contenidos/:slug`;
   - [x] página React con estados de carga, error y no encontrado;
   - [x] renderizadores controlados para bloques iniciales;
   - [x] renderizado sin HTML libre ni `dangerouslySetInnerHTML`.
4. **Índice público de páginas CMS (CMS-5)**:
   - [x] endpoint público `GET /api/v1/cms/pages`;
   - [x] Resource resumen sin bloques ni campos internos;
   - [x] orden estable por `published_at` descendente e `id` descendente;
   - [x] ruta React `/contenidos`;
   - [x] página índice con estados de carga, error, vacío y contenido;
   - [x] enlace público conservador desde la navegación principal.
5. **Navegación pública y páginas institucionales CMS (CMS-6)**:
   - [x] enlaces informativos del navbar hacia `/contenidos/{slug}`;
   - [x] slugs institucionales fijados para el MVP;
   - [x] seeder explícito y no destructivo para páginas institucionales base;
   - [x] tests del seeder institucional.
6. **Prensa y media / Noticias**:
   - CRUD admin;
   - contenido mediante bloques;
   - imagen principal;
   - estado borrador/publicado;
   - API pública;
   - listado y detalle en React.
7. **Documentos públicos**:
   - CRUD admin;
   - subida segura de documentos;
   - categorías;
   - visibilidad;
   - consulta/descarga desde React.
8. **Federación / Federarse**:
   - página informativa editable;
   - explicación del papel del club en federaciones y seguros;
   - enlaces oficiales;
   - posible formulario de interés.
9. **Academy / Escuela**:
   - página promocional editable;
   - información de escuela;
   - aprendizaje/normas básicas;
   - galería o bloques visuales;
   - formulario de inscripción/interés.
10. **Sistema de bloques de contenido**:
   - se elige enfoque de bloques controlados, no HTML libre tipo editor Word;
   - [x] bloques iniciales: encabezado, texto, lista, imagen, galería simple, enlace/botón, documento relacionado;
   - [x] React renderiza los bloques con componentes controlados.
11. **Formularios públicos de interés**:
   - federarse;
   - academy;
   - rate limit;
   - antispam/honeypot o captcha futuro;
   - estados internos en admin.
12. **Seguridad CMS**:
   - validación MIME;
   - límites de tamaño;
   - almacenamiento seguro;
   - sanitización o evitar HTML libre;
   - separación borrador/publicado;
   - permisos admin/editor si procede.

---

# Observaciones abiertas

Durante la documentación se han identificado varias posibles evoluciones:

- revisar periódicamente que la terminología del dominio permanezca alineada con las entidades reales (`User`, `Player`, `CategoryRegistration`, `CategoryEntry` y `Team`) conforme evolucione el proyecto.
- coordinar la ocupación de pistas entre calendarios de categorías distintas y proteger generaciones concurrentes;
- documentar la API mediante casos de uso completos;
- ampliar el catálogo de ADR;
- ampliar la documentación del contrato API con ejemplos reales;
- mantener una estrategia estricta de Resources por contexto.

Estas observaciones no constituyen tareas obligatorias, pero deberán revisarse cuando resulte oportuno.

---

# Criterios para cerrar una fase

Antes de considerar cerrada una fase deberían cumplirse:

- funcionalidad implementada;
- pruebas razonables;
- validación manual;
- documentación actualizada;
- commit independiente;
- merge realizado cuando corresponda.

---

## Mantenimiento

Este documento deberá revisarse al finalizar cada bloque importante de desarrollo para reflejar el estado real del proyecto.
