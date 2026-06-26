# Resources — Galotxas

## Propósito

Este documento define la estrategia de serialización utilizada por la API de Galotxas.

Los Resources constituyen la capa de traducción entre el dominio interno del backend y el contrato público de la API.

No describen cómo se almacenan los datos, sino cómo deben exponerse a cada consumidor.

---

# 1. Principios

- Los Resources forman parte del contrato público de la API.
- Un cambio en un Resource puede romper consumidores.
- El dominio y la API pueden evolucionar de forma parcialmente independiente.
- Los nuevos endpoints no deben devolver modelos Eloquent directamente.

---

# 2. Estado actual

El proyecto se encuentra en una migración progresiva hacia una serialización homogénea mediante Resources.

Actualmente conviven:

- endpoints completamente serializados mediante Resources;
- endpoints heredados pendientes de adaptación;
- distintas estrategias de serialización según la antigüedad del módulo.

Esta situación es conocida y aceptada.

La migración debe realizarse de forma incremental, aprovechando la evolución natural de cada módulo.

No se pretende una reescritura masiva únicamente para homogeneizar la serialización.

---

## Resources del usuario autenticado

`MeResource` compone la respuesta de `GET /api/v1/me` sin exponer modelos Eloquent directamente.

Delega los datos de cuenta en `UserResource` y el perfil deportivo opcional en `PlayerProfileResource`.

`UserResource` expone únicamente `id`, `name`, `lastname`, `email`, `role`, `active` y `has_player`. No serializa credenciales, tokens, verificación de email, timestamps ni otros campos internos.

---

## Resource de calendario privado

`CalendarDayResource` serializa cada día devuelto por `GET /api/v1/me/calendar`.

Mantiene la agrupación mediante `date` y delega la serialización de cada partido en `MatchResource`, evitando devolver modelos Eloquent directamente.

---

## Resource de rankings privados

`MyRankingResource` serializa cada fila devuelta por `GET /api/v1/me/rankings`.

Recibe una estructura explícita preparada por `BuildMyRankingsService` y expone únicamente campeonato, categoría, tipo y nombre de inscripción, posición y estadísticas deportivas. No expone el modelo `CategoryEntry` utilizado internamente para localizar al jugador.

## Resources públicos CMS

`PublicCmsPageSummaryResource` serializa cada elemento de `GET /api/v1/cms/pages`.

Expone únicamente:

- `slug`;
- `title`;
- `seo_description`;
- `published_at`;
- `url`.

No expone bloques, id interno, estado, timestamps ni datos administrativos. El frontend React lo consume desde `/contenidos` para construir el índice público.

`PublicCmsPageResource` serializa la respuesta de `GET /api/v1/cms/pages/{slug}`.

Expone únicamente:

- `slug`;
- `title`;
- `seo_title`;
- `seo_description`;
- `published_at`;
- `blocks`.

No expone el id interno de la página, estado, timestamps ni datos administrativos.

`PublicCmsBlockResource` serializa cada bloque público de una página CMS.

Expone únicamente:

- `type`;
- `order`;
- `data`.

No expone id interno, `cms_page_id`, `sort_order` ni timestamps. El campo `data` contiene JSON estructurado y controlado por el tipo de bloque.

Estructura pública actual de `data`:

- `heading`: `text` y `level`;
- `text`: `text`;
- `list`: `items`;
- `image`: `url` y `alt`;
- `gallery`: `urls`;
- `button`: `label` y `url`;
- `document_link`: `label` y `url`.

El frontend React consume el resumen desde `/contenidos` y la página completa desde `/contenidos/:slug`. Los bloques se renderizan mediante componentes controlados por `type`, sin interpretar HTML libre.

Slugs institucionales recomendados para el CMS MVP:

- `prensa-media`;
- `nosotros`;
- `federaciones`;
- `academy`;
- `documentos`;
- `federarse`.

El seeder `InstitutionalCmsPageSeeder` puede crear estas páginas y sus bloques mínimos en entornos de desarrollo sin sobrescribir páginas existentes.

---

# 3. Responsabilidades

Un Resource debe:

- seleccionar los datos que se exponen;
- ocultar información interna;
- transformar relaciones cuando sea necesario;
- mantener un formato estable;
- representar únicamente el contexto para el que fue diseñado.

No debe:

- ejecutar reglas de negocio;
- consultar la base de datos;
- decidir permisos;
- calcular rankings;
- implementar lógica deportiva.

---

# 4. Resources por contexto

La estrategia oficial del proyecto es:

**Un contexto funcional ⇒ un Resource.**

No reutilizar un Resource únicamente porque comparte parte de los datos.

Ejemplo implementado actualmente:

- PublicMatchResource

Ejemplos de Resources que podrán existir conforme evolucione el proyecto:

- ParticipantMatchResource
- AdminMatchResource

Cada uno representa un contrato distinto.

---

# 5. Contextos habituales

## Público

Información visible sin autenticación.

Debe excluir:

- emails;
- comentarios internos;
- usuarios responsables;
- trazabilidad administrativa.

## Participante autenticado

Información necesaria para la experiencia del jugador autenticado.

## Administrador

Información completa necesaria para gestionar la competición.

---

# 6. Relaciones

Las relaciones deben exponerse únicamente cuando aporten valor al consumidor.

Evitar:

- árboles excesivamente profundos;
- relaciones completas innecesarias;
- dependencias circulares.

Seleccionar únicamente los campos relevantes.

---

# 7. Enums

Los enums deben serializarse de forma explícita y estable.

No depender del comportamiento automático de Eloquent cuando ello pueda afectar al contrato.

---

# 8. Seguridad

Los Resources constituyen una capa adicional de protección.

Aunque una ruta esté protegida por middleware, el Resource solo debe exponer la información necesaria para ese contexto.

---

# 9. Relación con la API

El contrato API depende directamente de los Resources.

Antes de modificar un Resource debe comprobarse:

- compatibilidad con React;
- impacto sobre otros consumidores;
- tests existentes;
- documentación.

---

# 10. Buenas prácticas

Preferir:

- Resources pequeños;
- responsabilidad única;
- nombres descriptivos;
- serialización explícita;
- estabilidad del contrato.

Evitar:

- condicionales según contexto;
- reutilización excesiva;
- exposición accidental de datos internos;
- devolver modelos Eloquent completos.

---

# 11. Evolución prevista

El objetivo arquitectónico es que todos los endpoints relevantes utilicen Resources específicos y coherentes con su contexto funcional.

La adopción se realizará de forma progresiva conforme evolucionen los distintos módulos del proyecto.

Con la evolución del sistema de gestión de contenidos (CMS), deberá mantenerse de forma estricta la separación de Resources: los Resources administrativos podrán incluir metadatos, estado de borrador o trazabilidad, mientras que los Resources públicos seguirán entregando únicamente información visible para usuarios anónimos.

---

## Mantenimiento

Cuando cambie la estrategia de serialización deberá actualizarse este documento y, cuando corresponda, `03-api-contract.md`.
