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

# ADR-017 — Pistas base explícitas y borrado administrativo conservador

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El generador de liga heredado espera determinadas pistas, pero una instalación limpia no las crea de forma reproducible.
- `venues` solo contiene `name`, `location` y `description`; no existe un estado activo.
- Los partidos usan una clave foránea `nullOnDelete`, mientras que las solicitudes de reprogramación restringen el borrado de la pista solicitada.

Decisión:
- Gestionar pistas desde un CRUD Blade exclusivo para administradores usando únicamente los campos existentes.
- Crear `DefaultVenueSeeder` como seeder explícito, no incluido en `DatabaseSeeder`, con los nombres estables `Pista 1` a `Pista 5` y sin asumir IDs.
- Usar creación idempotente por nombre y no modificar registros que ya existan.
- Bloquear desde el panel el borrado de cualquier pista asociada a partidos o solicitudes de reprogramación, aunque una de las claves foráneas permita dejar el partido sin pista.
- No añadir `active` ni modificar todavía la selección principal de `GenerateLeagueScheduleService`.

Consecuencias:
- Una instalación puede preparar de forma controlada las pistas mínimas y gestionarlas posteriormente desde Blade.
- Repetir el seeder conserva la configuración administrativa existente.
- El calendario y la trazabilidad de reprogramaciones quedan protegidos frente a borrados administrativos accidentales.
- `SCHEDULE-1` sigue siendo necesario para eliminar los IDs mágicos y definir la consulta de pistas aptas para generación automática.

---

# ADR-018 — Todas las pistas configuradas participan en la generación de liga

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- La selección heredada reservaba nombres o IDs concretos para dobles de nivel 1 y usaba los IDs 2–5 para el resto.
- El modelo `Venue` no contiene modalidad, nivel, elegibilidad ni estado activo que permita expresar esa diferenciación como configuración de dominio.
- El calendario ya distribuye cruces en siete horarios distintos por pista y jornada.

Decisión:
- Consultar una sola vez todas las pistas existentes y ordenarlas por `id` para obtener un reparto estable.
- No depender de nombres, IDs concretos, secuencias consecutivas ni de `DefaultVenueSeeder`.
- Mantener los horarios y el orden de cruces existentes: por cada hora se recorren las pistas en el orden consultado.
- Permitir reutilizar una pista en horas diferentes, pero nunca duplicar pista y fecha/hora dentro de una liga generada.
- Fallar antes de crear datos si no existen pistas.
- Si una jornada supera los siete huecos por pista, lanzar un error dentro de la transacción para revertir rondas y partidos parciales.

Consecuencias:
- Una instalación con cualquier conjunto de pistas puede generar ligas sin preparar IDs o nombres especiales.
- Singles y dobles aplican la misma disponibilidad porque el esquema no expresa restricciones distintas.
- Una única pista admite hasta siete cruces por jornada; capacidades superiores requieren más pistas.
- La ausencia de colisiones queda garantizada dentro de la categoría generada; la coordinación entre categorías conserva la semántica heredada y queda como evolución futura.
- La disponibilidad avanzada, reservas, restricciones por modalidad y calendarios por pista permanecen fuera de alcance.

---

# ADR-019 — Desempates transitivos y deterministas en rankings

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El ranking de categoría aplicaba enfrentamiento directo dentro de un comparador por parejas aunque el empate a puntos incluyera tres o más entradas.
- Un ciclo A vence a B, B vence a C y C vence a A produce relaciones no transitivas y puede hacer depender el resultado del algoritmo de ordenación.
- Los servicios agregados utilizaban el nombre como último criterio, pero dos jugadores pueden compartirlo.

Decisión:
- Agrupar primero el ranking de categoría por puntos.
- Aplicar enfrentamiento directo solo cuando el grupo empatado contiene exactamente dos entradas.
- Para grupos de tres o más, omitir el directo y ordenar por diferencia de juegos, juegos a favor, nombre y `entry_id`.
- Mantener el nombre como criterio heredado y usar el identificador únicamente cuando persiste la igualdad total.
- Añadir `player_id` como último criterio técnico en campeonato, temporada e histórico.
- Considerar `win_rate` un porcentaje en escala `0–100` en todo el contrato histórico.

Consecuencias:
- El comparador general vuelve a ser transitivo para empates múltiples.
- Repetir un cálculo con los mismos datos produce el mismo orden, incluso con nombres duplicados.
- El identificador garantiza estabilidad técnica; no representa mérito ni ventaja deportiva.
- No se introducen nuevos criterios como sets, average, fair play o miniligas entre empatados.

---

# ADR-020 — Reporte único e inmutable por lado y resolución atómica

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El workflow permitía localizar un reporte por partido y lado con `updateOrCreate`, de modo que el mismo jugador podía reenviarlo y sustituir su tanteo o comentario.
- En dobles, dos jugadores distintos representan al mismo lado y deben compartir una única declaración.
- La creación del segundo reporte, la comparación y el cambio del partido forman una sola decisión de dominio.

Decisión:
- Permitir un único reporte inmutable por partido y lado, respaldado por la restricción única de base de datos y por una comprobación explícita con mensaje de dominio.
- Considerar que el reporte de cualquier miembro de una pareja representa al lado completo.
- Bloquear la fila del partido y ejecutar dentro de una misma transacción la creación del reporte, la comparación y las transiciones de reportes y partido.
- Ante coincidencia, validar ambos reportes y publicar el resultado oficial; ante discrepancia, marcar ambos como `conflict`, dejar vacíos los campos oficiales y pasar a `under_review`.
- Conservar los reportes originales en conflicto cuando un administrador establece el resultado oficial.

Consecuencias:
- Un participante no puede corregir silenciosamente ni sobrescribir una declaración ya enviada; cualquier rectificación requiere intervención administrativa trazable.
- Los compañeros de dobles no pueden producir versiones rivales desde el mismo lado.
- Un fallo durante la comparación o resolución automática no deja un segundo reporte ni estados parciales persistidos.
- Los Resources seguros existentes no cambian: esta decisión afecta al comportamiento de dominio, no amplía el contrato de datos.

---

# ADR-021 — Contrato específico para acciones pendientes e inclusión informativa de revisiones

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El endpoint de acciones pendientes agrupaba partidos en varias colecciones solapadas y reutilizaba `MatchResource`, que contiene identificadores, responsables y trazabilidad innecesarios para Mi Panel.
- Un partido con reporte rival aparecía simultáneamente como pendiente de reporte y de confirmación.
- Los partidos `under_review` requieren visibilidad para el jugador, pero el dominio no permite nuevos reportes mientras se resuelve el conflicto.

Decisión:
- Devolver una colección plana con una única entrada por partido y los tipos `submit_result`, `confirm_result` o `under_review`.
- Crear `PendingMatchActionResource` como contrato del contexto y delegar la representación segura del partido en `ParticipantMatchResource`.
- Considerar `under_review` un aviso informativo enlazado al detalle, nunca una autorización para editar o reportar.
- Devolver una colección vacía a usuarios autenticados sin perfil de jugador.
- Mantener la representación por lado en dobles: ambos integrantes comparten la acción, pero cada consulta contiene una sola entrada por partido.

Consecuencias:
- React no deduce reglas deportivas ni combina colecciones potencialmente contradictorias.
- El Dashboard puede mostrar un contador directo a partir de la longitud de la colección.
- No se exponen reportes, comentarios, emails, usuarios ni trazabilidad administrativa.
- Cambiar o añadir tipos de acción exige revisar el contrato, los tests y su representación en Mi Panel.

---

# ADR-022 — Vitest y React Testing Library para la base de pruebas frontend

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El frontend React no disponía de ejecución automatizada y solo se validaba mediante ESLint, build y comprobaciones manuales.
- Los flujos críticos necesitan proteger funciones puras, estados remotos e interacciones sin introducir todavía una infraestructura E2E completa.
- El proyecto utiliza React 19, Vite 8 y Node 22.

Decisión:
- Adoptar Vitest integrado en la configuración Vite como runner frontend.
- Usar React Testing Library, `jest-dom` y `user-event` para probar comportamiento accesible desde la perspectiva del usuario.
- Ejecutar componentes en jsdom con setup y limpieza centralizados.
- Mantener las suites junto al código cubierto y reutilizar una utilidad mínima con `MemoryRouter` y `AuthContext` opcional.
- Simular hooks y servicios de forma localizada, sin llamadas reales al backend, cobertura porcentual obligatoria ni snapshots masivos.
- Mantener E2E-1 como bloque independiente para navegador y sistema completos.

Consecuencias:
- `npm run test:run` se convierte en validación obligatoria de cambios frontend junto con lint y build.
- Los contratos críticos pueden evolucionar con regresiones rápidas y deterministas.
- jsdom no valida integración real con Laravel, comportamiento específico de navegador ni apariencia visual.
- Añadir cobertura debe responder a riesgo funcional, no a un porcentaje artificial.

---

# ADR-023 — Actualizaciones de dependencias dirigidas y auditables

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El MVP depende de ecosistemas npm y Composer con árboles de producción y desarrollo diferentes.
- Una actualización global puede introducir cambios funcionales o saltos principales no relacionados con el advisory que se intenta resolver.
- Los locks son la fuente reproducible de las versiones instaladas y deben mantenerse mediante sus gestores oficiales.

Decisión:
- Auditar por separado el árbol completo y el árbol de producción siempre que el gestor lo permita.
- Priorizar vulnerabilidades de producción y aplicar actualizaciones nominales del paquete afectado dentro de las versiones principales aprobadas.
- Permitir que npm o Composer actualicen las dependencias transitivas compatibles requeridas por ese paquete, sin editar manualmente los locks.
- No usar correcciones forzadas, actualizaciones globales ni saltos principales sin un bloque de migración específico.
- Ejecutar después la reauditoría y la regresión completa correspondiente, incluido E2E cuando cambien dependencias de runtime frontend o backend.

Consecuencias:
- Cada cambio de dependencias conserva un alcance identificable y una comparación antes/después.
- Las vulnerabilidades que requieran una migración principal permanecen documentadas en lugar de ocultarse mediante una actualización indiscriminada.
- `package-lock.json` y `composer.lock` solo cambian a través de npm y Composer.

---

## Mantenimiento

Cuando una decisión arquitectónica relevante cambie, deberá registrarse una nueva entrada en este documento en lugar de modificar silenciosamente una anterior.
