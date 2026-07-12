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

Estado: Aceptada

Se decide que la gestión de contenidos públicos se realizará mediante un sistema de bloques controlados (encabezado, texto, lista, imagen, documento) en lugar de permitir la inserción de HTML libre tipo WYSIWYG.

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

# ADR-010 — Autocompletar `published_at` al publicar páginas CMS

Estado: Aceptada

Fecha aproximada: 2026-06

Contexto:
- El panel admin permite cambiar una página CMS entre `draft` y `published`.
- El endpoint público solo considera visible una página publicada cuando `published_at` está vacío o no está en el futuro.
- En edición manual puede ocurrir que un administrador seleccione `published` sin introducir fecha.

Decisión:
- Cuando una página CMS se guarda con estado `published` y `published_at` vacío, el backend completa `published_at` con `now()`.
- Si el administrador proporciona una fecha, se respeta.
- Si el estado es `draft`, no se fuerza fecha de publicación.

Consecuencias:
- Publicar desde el panel tiene efecto inmediato aunque el campo de fecha quede vacío.
- Sigue siendo posible programar publicación introduciendo una fecha futura.
- La regla queda centralizada en el controlador admin de páginas CMS y cubierta por tests de creación.

---

# ADR-011 — Estructura MVP de `data` para bloques CMS

Estado: Aceptada

Fecha aproximada: 2026-06

Contexto:
- Los bloques CMS se almacenan como JSON para mantener flexibilidad sin HTML libre.
- El panel admin necesita validar una estructura mínima antes de persistir cada bloque.
- Todavía no existe subida real de imágenes o documentos.

Decisión:
- Mantener una estructura `data` explícita por tipo:
  - `heading`: `text` y `level`;
  - `text`: `text`;
  - `list`: `items`;
  - `image`: `url` y `alt`;
  - `gallery`: `urls`;
  - `button`: `label` y `url`;
  - `document_link`: `label` y `url`.
- Aceptar URLs `http(s)` y rutas internas que comiencen por `/`, excluyendo valores protocol-relative que empiezan por `//`.
- Convertir listas y galerías desde texto multilínea del panel admin a arrays.

Consecuencias:
- El endpoint público mantiene un contrato estable y sin HTML libre.
- React podrá renderizar por tipo de bloque sin interpretar contenido arbitrario.
- La futura subida de archivos deberá sustituir o complementar las URLs manuales sin romper el contrato público.

---

# ADR-012 — Ruta pública React para páginas CMS

Estado: Aceptada

Fecha aproximada: 2026-06

Contexto:
- Las páginas CMS públicas se identifican por `slug`.
- El frontend ya dispone de rutas públicas específicas para home, torneos, rankings, jugador y otras secciones.
- Una ruta raíz dinámica como `/:slug` podría colisionar con rutas presentes o futuras.

Decisión:
- Renderizar páginas CMS públicas bajo `/contenidos/:slug`.
- No introducir todavía un catch-all raíz.
- Consumir `GET /api/v1/cms/pages/{slug}` desde el servicio frontend CMS.

Consecuencias:
- Se evita romper rutas públicas existentes.
- Las páginas CMS quedan disponibles de forma explícita y reversible.
- Una futura fase podrá estudiar rutas limpias si se define una estrategia global de routing público.

---

# ADR-013 — Orden del índice público CMS

Estado: Aceptada

Fecha aproximada: 2026-06

Contexto:
- El índice público de páginas CMS debe ser descubrible y estable sin implementar todavía orden manual, categorías ni paginación.
- Las páginas ya tienen `published_at` como fecha funcional de publicación.

Decisión:
- Ordenar `GET /api/v1/cms/pages` por `published_at` descendente.
- Usar `id` descendente como desempate estable.
- No añadir campos nuevos de orden manual en CMS-5.

Consecuencias:
- Las páginas más recientes aparecen primero.
- El contrato queda documentado sin ampliar la base de datos.
- Una futura fase podrá introducir orden manual si se define una necesidad editorial clara.

---

# ADR-014 — Slugs institucionales CMS y seeder explícito

Estado: Aceptada

Fecha aproximada: 2026-06

Contexto:
- El CMS MVP debe servir páginas informativas enlazadas desde la navegación pública.
- El navbar no debe depender de rutas informativas estáticas o no implementadas.
- En desarrollo conviene disponer de páginas base sin sobrescribir contenido creado desde el panel.

Decisión:
- Fijar los slugs institucionales MVP: `prensa-media`, `nosotros`, `federaciones`, `academy`, `documentos` y `federarse`.
- Enlazar desde React a `/contenidos/{slug}` para las entradas institucionales principales.
- Crear `InstitutionalCmsPageSeeder` como seeder explícito, no llamado automáticamente desde `DatabaseSeeder`.
- El seeder solo crea páginas y bloques mínimos cuando el slug no existe.

Consecuencias:
- La navegación pública queda alineada con el CMS.
- Los entornos de desarrollo pueden poblar contenido institucional mínimo con un comando controlado.
- El contenido existente no se sobrescribe si un administrador ya ha creado una página con el mismo slug.

---

# ADR-015 — Contrato seguro por contexto para el workflow de resultados

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El detalle público de partido ya utilizaba `PublicMatchResource` y ocultaba tanteos no validados y trazabilidad.
- El endpoint autenticado de workflow reutilizaba `MatchResource`, que podía incluir reportes, comentarios, responsables y emails aunque el usuario no participara.
- React ya distingue visualmente entre participante y usuario ajeno mediante `workflow.participates`.
- El cliente frontend limpia la sesión ante respuestas `403`, por lo que devolver `403` a un usuario autenticado que solo consulta un detalle público produciría una consecuencia lateral no deseada.

Decisión:
- Mantener respuesta `200` limitada para usuarios autenticados sin perfil de jugador o no participantes.
- Serializar su partido mediante `PublicMatchResource` y devolver todos los reportes del workflow a `null`.
- Crear `ParticipantMatchResource` para el partido del participante y `ParticipantMatchResultReportResource` para cada reporte que necesita React.
- Aplicar los Resources de participante también a las respuestas de `submit-result` y `confirm-result`.
- Mantener `MatchResultReportResource` en el contexto administrativo, donde la trazabilidad sí está autorizada.

Consecuencias:
- Un usuario ajeno puede seguir viendo el detalle público sin recibir datos privados ni perder su sesión.
- Los participantes conservan envío, confirmación, discrepancia y estados del workflow con el contrato mínimo necesario.
- Los emails, objetos de usuario, IDs internos de reporte y timestamps no forman parte del contrato del participante.
- La API aplica explícitamente el principio un contexto funcional ⇒ un Resource.

---

# ADR-016 — Resolución de la URL API del frontend por entorno

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- `frontend/.env.example` ya declaraba `VITE_API_BASE_URL`, pero el cliente Axios ignoraba la variable y fijaba `http://localhost:8080/api/v1`.
- Un build de producción con esa URL intentaría acceder al localhost del visitante.
- El proyecto mantiene una única instancia Axios compartida por los servicios frontend.

Decisión:
- Dar prioridad a `VITE_API_BASE_URL` y eliminar espacios exteriores antes de utilizarla.
- Usar `http://localhost:8080/api/v1` únicamente como fallback del servidor de desarrollo.
- Usar `/api/v1` como fallback de producción para permitir despliegue bajo el mismo dominio mediante proxy inverso.
- Mantener toda la resolución en `frontend/src/api/client.js`, sin duplicarla en servicios.

Consecuencias:
- Los builds de producción sin configuración explícita dejan de apuntar al localhost del visitante.
- Los despliegues con API en otro dominio deben proporcionar `VITE_API_BASE_URL` durante el build.
- El desarrollo local sigue funcionando sin crear un `.env` real.
- Los interceptores Bearer y de limpieza de sesión permanecen independientes de la URL configurada.

---

## Mantenimiento

Cuando una decisión arquitectónica relevante cambie, deberá registrarse una nueva entrada en este documento en lugar de modificar silenciosamente una anterior.
