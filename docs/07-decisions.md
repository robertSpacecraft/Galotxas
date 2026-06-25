# Decisiones arquitectónicas — Galotxas

## Propósito

Este documento registra las decisiones arquitectónicas relevantes (ADR simplificados) adoptadas durante el desarrollo del proyecto.

Su objetivo es conservar el contexto que motivó cada decisión para evitar reabrir debates ya resueltos.

No debe utilizarse para registrar tareas pendientes ni roadmap.

---

# Formato

Cada decisión debe incluir:

- Identificador.
- Estado (Aceptada, Sustituida o Rechazada).
- Fecha aproximada.
- Contexto.
- Decisión.
- Consecuencias.

---

# ADR-001 — Backend como fuente de verdad

Estado: Aceptada

Se decide que toda la lógica deportiva reside exclusivamente en el backend.

Consecuencias:
- React no calcula rankings.
- React no decide elegibilidad.
- Blade tampoco implementa reglas deportivas.

---

# ADR-002 — MariaDB como único motor soportado

Estado: Aceptada

El proyecto adopta MariaDB como único motor de base de datos.

Consecuencias:
- Eliminación de SQLite.
- Entorno de pruebas aislado con MariaDB.
- Migraciones orientadas a MariaDB.

---

# ADR-003 — API y Blade conviven

Estado: Aceptada

El backend ofrece simultáneamente:

- API REST.
- Panel administrativo Blade.

Ambas interfaces forman parte de la arquitectura oficial.

---

# ADR-004 — Resources por contexto

Estado: Aceptada

Cuando distintos consumidores requieren distinta información, se crean Resources independientes.

Ejemplos:

- PublicMatchResource
- ParticipantMatchResource
- AdminMatchResource

Se evita un único Resource con múltiples condicionales.

---

# ADR-005 — Desarrollo por bloques pequeños

Estado: Aceptada

Las implementaciones se realizan mediante bloques funcionales pequeños.

Cada bloque debe intentar incluir:

- pruebas;
- validación;
- documentación;
- commit independiente.

---

# ADR-006 — Documentación como fuente de contexto

Estado: Aceptada

La documentación forma parte del proyecto.

Las decisiones relevantes deben reflejarse en `/docs` y los principios estables en los distintos `AGENTS.md`.

---

# ADR-007 — Enfoque CMS basado en bloques estructurados

Estado: Aceptada (Futura implementación)

Se decide que la futura gestión de contenidos públicos (Noticias, Páginas) se realizará mediante un sistema de bloques controlados (encabezado, texto, lista, imagen, documento) en lugar de permitir la inserción de HTML libre tipo WYSIWYG.

Contexto:
- El uso de HTML libre incrementa el riesgo de inyecciones XSS si la sanitización no es estricta.
- Un diseño consistente es más difícil de mantener si el administrador puede alterar los estilos incrustados.

Consecuencias:
- Mayor seguridad al evitar sanitización compleja de HTML en la base de datos y en React.
- React renderizará componentes nativos para cada tipo de bloque, asegurando que el diseño visual del frontend permanezca consistente.
- El panel de administración Blade requerirá una interfaz estructurada para agregar y ordenar estos bloques en lugar de un único editor de texto enriquecido.

---

# ADR-008 — Estrategia auth/token del frontend MVP

Estado: Aceptada

Fecha aproximada: 2026-06

Contexto:
- El backend Laravel Sanctum ya emite tokens Bearer para la API.
- El frontend React consume endpoints privados de Mi Panel y mantiene rutas protegidas.
- Existían dos clientes Axios (`api.js` y `client.js`) con gestión parcial y divergente de errores de autenticación.

Decisión:
- Mantener en el MVP la autenticación Bearer con `token` y `user` en `localStorage`.
- Consolidar la instancia real de Axios en `client.js` y dejar `api.js` como alias compatible para evitar un refactor amplio.
- Limpiar siempre `token` y `user` ante `401`/`403` para impedir estado React/localStorage desincronizado.
- Mantener la migración a cookies `HttpOnly`/`SameSite`/CSRF como decisión futura, no incluida en este bloque.

Consecuencias:
- Los imports heredados de `api.js` y los nuevos de `client.js` comparten interceptores y comportamiento.
- `/player` depende de token y usuario local, y una sesión inválida termina limpiándose en el siguiente fallo autenticado.
- La estrategia actual sigue siendo adecuada para el MVP, pero no cierra la discusión de endurecimiento posterior con cookies seguras.

---

# ADR-009 — Datos de bloques CMS en JSON MariaDB

Estado: Aceptada

Fecha aproximada: 2026-06

Contexto:
- El CMS público necesita una base flexible para bloques controlados sin introducir HTML libre.
- Los tipos iniciales de bloque comparten una estructura común (`type`, orden y datos), pero cada tipo necesita atributos propios.
- MariaDB es el único motor soportado por el proyecto.

Decisión:
- Persistir los bloques CMS en `cms_blocks` con una columna `data` de tipo JSON gestionada por Laravel.
- Mantener el tipo de bloque en un enum controlado (`heading`, `text`, `list`, `image`, `gallery`, `button`, `document_link`).
- Serializar `data` mediante `PublicCmsBlockResource`, sin exponer campos internos como ids, claves foráneas o timestamps.

Consecuencias:
- La API pública entrega datos estructurados y evita almacenar HTML libre.
- React podrá renderizar componentes controlados por tipo de bloque.
- La validación fina de la forma de `data` deberá incorporarse cuando exista administración CMS o endpoints de escritura.

---

## Mantenimiento

Cuando una decisión arquitectónica relevante cambie, deberá registrarse una nueva entrada en este documento en lugar de modificar silenciosamente una anterior.
