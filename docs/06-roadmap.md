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
- CMS-1: base backend pública de páginas CMS y bloques controlados.
- CMS-2: gestión admin básica de páginas CMS.
- CMS-3: gestión admin básica de bloques CMS.
- CMS-4: renderizado público de páginas CMS en React.

### Frontend

- React básico.
- Panel privado.

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
4. **Prensa y media / Noticias**:
   - CRUD admin;
   - contenido mediante bloques;
   - imagen principal;
   - estado borrador/publicado;
   - API pública;
   - listado y detalle en React.
5. **Documentos públicos**:
   - CRUD admin;
   - subida segura de documentos;
   - categorías;
   - visibilidad;
   - consulta/descarga desde React.
6. **Federación / Federarse**:
   - página informativa editable;
   - explicación del papel del club en federaciones y seguros;
   - enlaces oficiales;
   - posible formulario de interés.
7. **Academy / Escuela**:
   - página promocional editable;
   - información de escuela;
   - aprendizaje/normas básicas;
   - galería o bloques visuales;
   - formulario de inscripción/interés.
8. **Sistema de bloques de contenido**:
   - se elige enfoque de bloques controlados, no HTML libre tipo editor Word;
   - [x] bloques iniciales: encabezado, texto, lista, imagen, galería simple, enlace/botón, documento relacionado;
   - [x] React renderiza los bloques con componentes controlados.
9. **Formularios públicos de interés**:
   - federarse;
   - academy;
   - rate limit;
   - antispam/honeypot o captcha futuro;
   - estados internos en admin.
10. **Seguridad CMS**:
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
