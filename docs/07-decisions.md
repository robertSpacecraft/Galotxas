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
- ParticipantMatchResultReportResource
- PendingMatchActionResource

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

Estado: Sustituida por ADR-026

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

Esta decisión se conserva como trazabilidad histórica. ADR-026 mantiene la publicación inmediata, pero formaliza `null` como valor significativo y deja de sustituirlo por `now()`.

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

Nota de trazabilidad: ADR-018 completa la evolución que ADR-017 dejó expresamente para SCHEDULE-1. ADR-017 se conserva porque explica la decisión anterior de no mezclar el CRUD de pistas con el generador.

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

Nota de trazabilidad: E2E-1 se implementó posteriormente mediante la decisión registrada en ADR-024. ADR-022 continúa aceptada porque Vitest/RTL y Playwright cubren capas distintas.

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

# ADR-024 — Playwright en un stack E2E aislado y desechable

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- Vitest y los tests Feature validan sus capas de forma aislada, pero no recorren conjuntamente React, API, MariaDB y panel Blade en un navegador real.
- El smoke no puede utilizar la base de desarrollo ni depender de IDs o datos manuales.
- La versión de Chromium debe ser compatible con `@playwright/test` sin imponer una instalación global en WSL.

Decisión:
- Ejecutar Playwright con Chromium dentro de la imagen oficial fijada a la misma versión que `@playwright/test`.
- Levantar mediante `backend/docker/docker-compose.e2e.yml` un proyecto Compose separado con Laravel/Nginx, MariaDB `galotxas_e2e` sobre `tmpfs` y runner Playwright.
- Proteger `E2ESmokeSeeder` para que solo se ejecute con `APP_ENV=e2e` y `DB_DATABASE=galotxas_e2e`.
- Mantener una suite serial que narra el flujo crítico desde contenido público y Mi Panel hasta conflicto, resolución Blade y ranking.
- Desmontar contenedores, red y volúmenes al finalizar, también cuando la suite falla.

Consecuencias:
- `npm run e2e` prueba seis recorridos con frontend, backend y base reales sin tocar desarrollo.
- La ejecución es reproducible y no necesita navegador ni librerías Playwright instaladas globalmente en el host.
- Chromium es el único navegador cubierto en el MVP; una matriz adicional queda como evolución posterior.
- El smoke no sustituye tests Feature, Vitest ni QA visual/manual.

---

# ADR-025 — Gobernanza híbrida de contenido y arquitectura pública

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El proyecto combina dominio competitivo, un CMS básico de páginas y bloques, páginas React estáticas y conocimiento estable en `knowledge/`.
- La navegación pública actual expone Torneos, Rankings, páginas institucionales y `/contenidos` sin una autoridad editorial única para todas las áreas.
- El Manual necesita contenido canónico versionado; las noticias, actividades e información institucional temporal deben poder actualizarse sin Git ni despliegue.
- La Escuela de Galotxas combina pedagogía estable con actividad operativa y no encaja por completo en una sola fuente.

Decisión:
- Adoptar tres canales: dominio Laravel mediante API, contenido administrable mediante CMS Laravel/Blade y conocimiento canónico mediante `knowledge/` y un futuro compilador build-time.
- Mantener el Manual estático desde `knowledge/` en su primera versión, sin base de datos, API Laravel, CRUD Blade, MDX o HTML ejecutable.
- Usar el CMS Laravel para el contenido que requiera edición administrativa, borradores, programación, archivos o actualización frecuente.
- Tratar la Escuela de Galotxas como sección pública híbrida e independiente del Manual: conocimiento pedagógico estable y actividad operativa administrable.
- Establecer React como capa de experiencia y presentación, nunca como fuente editorial.
- Mantener Blade como interfaz administrativa oficial; no crear un panel editorial React paralelo.
- Considerar `/contenidos` y sus páginas una estructura legada pendiente de inventario y migración, sin eliminarla ni cambiarla en la Fase 0.
- Organizar la arquitectura pública objetivo en Inicio, Competición, Aprende a jugar, Escuela de Galotxas y Club, con la zona autenticada separada.

Alternativas descartadas:
- Almacenar todo el contenido en el CMS: perdería la autoridad versionada y revisable del reglamento y los conceptos.
- Mantener todo el contenido en Markdown: impediría la edición operativa por administradores y la publicación temporal sin despliegue.
- Escribir contenido institucional o pedagógico directamente en JSX: convertiría React en fuente editorial y crearía duplicados difíciles de gobernar.
- Servir el Manual mediante una API Laravel o MDX en v1: añadiría persistencia o ejecución innecesarias antes de disponer de un contrato editorial validado.
- Situar la Escuela bajo `/manual/academy`: confundiría una sección educativa y operativa propia con el Manual, además de conservar una denominación pública legada.

Consecuencias:
- Cada nueva sección debe definir previamente fuente de verdad, responsables, publicación, URLs, multimedia, permisos, tests y documentación.
- Una misma pieza no puede mantenerse como copia editable en `knowledge/`, base de datos, React o seeders.
- El compilador, el contrato editorial, las nuevas rutas y las ampliaciones CMS requieren bloques posteriores; esta decisión no los implementa.
- El backend debe excluir contenido no publicable antes de responder y los Resources deben delimitar el contrato público.
- La migración de `/contenidos`, la duplicidad de Nosotros, el slug legado `academy`, el almacenamiento persistente y la protección de menores requieren auditoría y trabajo posterior.

---

# ADR-026 — Invariantes editoriales y estado derivado del CMS

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- El CMS admitía páginas nuevas publicadas antes de poder añadir bloques y permitía eliminar el último bloque de una página `published`.
- El filtro público ya interpretaba `published_at = null` como visible, mientras el controlador administrativo reemplazaba ese valor por `now()`.
- Una fecha futura se ocultaba correctamente en la API, pero Blade la presentaba como «Publicada».
- El formulario `datetime-local` no comunicaba la zona horaria utilizada.

Decisión:
- Crear siempre las páginas como `draft`; una petición manipulada que solicite `published` durante el alta falla de forma explícita.
- Permitir borradores vacíos, pero exigir al menos un bloque validado para pasar una página a `published`.
- Definir `status = published` y `published_at = null` como publicación inmediata, conservando el valor nulo.
- Derivar «Programada» cuando el estado persistido es `published` y la fecha es futura; no añadir un valor `scheduled` al enum ni al esquema.
- Considerar publicada una página `published` con fecha nula, pasada o igual al momento actual.
- Impedir eliminar el último bloque de una página con estado `published` hasta que un administrador la pase expresamente a borrador.
- Interpretar y mostrar el campo de fecha con la zona `config('app.timezone')`.

Alternativas descartadas:
- Mantener el autocompletado con `now()`: ocultaría la semántica explícita de publicación inmediata y divergiría del filtro público.
- Persistir un tercer estado `scheduled`: duplicaría información ya derivable de estado y fecha y exigiría migración sin aportar una transición distinta.
- Cambiar automáticamente la página a borrador al borrar su último bloque: introduciría una decisión editorial implícita e inesperada.
- Permitir páginas publicadas vacías y ocultarlas solo en React: rompería la invariancia del backend y convertiría al cliente en barrera editorial.

Consecuencias:
- El flujo administrativo es crear borrador, añadir bloques y publicar.
- Listado y detalle Blade distinguen Borrador, Programada y Publicada mediante un estado de presentación no persistido.
- Listado y detalle API conservan URLs, envelope y Resources y comparten la misma regla temporal.
- Las páginas ya publicadas conservan al menos un bloque a través de los flujos administrativos.
- Roles editoriales, trazabilidad, preview, revisiones, redirects, uploads y entidades específicas permanecen fuera de esta decisión.

---

# ADR-027 — Visibilidad explícita y jerárquica de la competición

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- Temporadas, campeonatos y categorías disponen de estados operativos, pero esos estados describen el ciclo deportivo y no una intención de publicación.
- Los endpoints públicos actuales aceptan campeonatos `pending` o `cancelled` y categorías `pending`; derivar su publicación del estado cambiaría comportamientos válidos y mezclaría responsabilidades.
- Fechas, apertura de inscripciones, calendarios y resultados tampoco expresan de forma estable si la administración desea mostrar una entidad.
- La incorporación del criterio no debe ocultar de forma repentina los registros existentes ni aplicar parcialmente filtros a unos endpoints y no a otros.

Decisión:
- Añadir el booleano no nullable `is_public` a temporadas, campeonatos y categorías.
- Mantener estado operativo y visibilidad pública como dimensiones independientes.
- Crear los registros futuros con `is_public = false` por defecto.
- Marcar con `is_public = true` los registros existentes durante la migración para preservar su accesibilidad anterior.
- Exigir una temporada pública para marcar público un campeonato, y campeonato y temporada públicos para marcar pública una categoría.
- Permitir ocultar una temporada o campeonato aunque existan descendientes con su flag activo.
- No propagar automáticamente el cambio del padre: cada descendiente conserva su visibilidad declarada para poder restaurar la rama.
- Gestionar y validar los flags desde el panel Blade mediante Form Requests y persistencia explícita.
- Excluir `is_public` de la asignación masiva y de la serialización Eloquent heredada para impedir que el CRUD API administrativo lo incorpore accidentalmente antes de 2B.5.
- Aplazar a 2B.4B la visibilidad efectiva y la aplicación conjunta de la jerarquía en todos los endpoints públicos.
- Mantener durante 2B.4A los controladores, Resources, rutas, envelopes y campos públicos anteriores; `is_public` no se serializa.
- Al implementar 2B.4B, mantener intactos rutas, envelopes y campos públicos; `is_public` continúa sin serializarse.
- Aplicar la visibilidad efectiva mediante scopes locales explícitos, sin global scopes, y reutilizar exactamente esas consultas en los métodos de instancia.
- Filtrar entidad raíz y relaciones anidadas antes de serializar; responder `404` en accesos públicos directos a una rama privada.
- Excluir de rankings y otros agregados públicos los partidos de ramas privadas mediante una opción explícita en los Services compartidos, manteniendo su comportamiento interno sin filtro.
- Mantener sin filtro público la administración, generación, datos personales y workflows de participantes.
- Sembrar explícitamente como pública sólo la jerarquía destinada a desarrollo público y E2E; conservar privadas las factories por defecto.
- En 2B.5, permitir que la API administrativa gestione `is_public` mediante los mismos Form Requests y reglas jerárquicas que Blade, con persistencia explícita y sin scopes públicos.
- Serializar esos CRUD mediante Resources administrativos dedicados; mantener `is_public` fuera de todos los Resources públicos.

Alternativas descartadas:
- Filtrar sólo por estado operativo: impediría combinaciones válidas como `pending + público`, `active + privado` o `cancelled + público`.
- Reutilizar fechas o ventanas de inscripción: expresan planificación deportiva, no intención de visibilidad.
- Mantener visibilidad implícita según calendario, resultados o relaciones: sería difícil de administrar, probar y explicar.
- Compartir un enum editorial con el CMS: confundiría entidades funcionales con páginas y bloques sujetos a borrador, programación y publicación editorial.
- Ocultar automáticamente todos los hijos al ocultar un padre: perdería la configuración declarada y obligaría a reconstruirla al restaurar la rama.

Consecuencias:
- El administrador puede configurar explícitamente la visibilidad sin alterar estados deportivos.
- Una rama puede conservar hijos declarados públicos mientras un padre la mantiene efectivamente oculta.
- Durante 2B.4A, persistencia, formularios y validación jerárquica quedaron preparados antes de modificar el contrato de lectura, y un registro privado siguió accesible temporalmente por la API pública.
- Los listados y relaciones públicas excluyen ramas privadas, y el conocimiento de un identificador no permite consultar directamente la entidad.
- Los agregados públicos no revelan indirectamente resultados de una rama privada.
- Ocultar un padre retira efectivamente la rama; restaurarlo recupera sólo los descendientes cuyo flag se conservó activo.
- No se añade un índice simple sobre `is_public`, de baja cardinalidad; la optimización queda supeditada a medir las consultas jerárquicas reales.
- Desde 2B.5, los CRUD API administrativos pueden consultar y modificar `is_public` con la misma jerarquía que Blade, sin alterar la visibilidad pública efectiva ni los contratos visitantes.

---

# ADR-028 — Cinco áreas canónicas y compatibilidad de la navegación pública

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- Antes de 3B, Navbar exponía ocho enlaces públicos planos: Inicio, Torneos, Rankings, cuatro destinos CMS concretos y el índice técnico Contenidos.
- En ese momento, el router conservaba rutas funcionales de competición, una página React estática de Nosotros, rutas CMS bajo `/contenidos` y una zona de cuenta, pero no disponía de landings para organizar estos destinos.
- Nosotros está duplicado entre React y CMS; `academy` existe como slug legado, pero no acredita la futura arquitectura híbrida de Escuela.
- `knowledge/` contiene Reglamento y Conceptos, pero todavía no tiene contrato normalizado, compilador, artefactos React ni colecciones de Historia o Escuela.
- Retirar o redirigir rutas antes de disponer de contenido equivalente pondría en riesgo consumidores internos, marcadores, SEO y workflows funcionales.

Decisión:
- Fijar exactamente cinco áreas públicas de primer nivel: Inicio (`/`), Competición (`/competicion`), Aprende a jugar (`/aprende-a-jugar`), Escuela de Galotxas (`/escuela`) y Club (`/club`).
- Mantener identidad, acceso, registro, Mi Panel y cierre de sesión en una zona de cuenta separada del menú editorial.
- Conservar `/torneos`, `/rankings`, detalles de campeonato y categoría, standings, schedule y partidos como rutas funcionales secundarias de Competición, sin exigir que cambien de namespace.
- Mantener temporalmente `/contenidos` y `/contenidos/:slug` como infraestructura pública heredada del CMS, pero retirarlas del primer nivel final.
- Mantener `/nosotros` y `/contenidos/nosotros` hasta que el CMS tenga paridad canónica, se migren enlaces y se apruebe la compatibilidad.
- No equiparar ni redirigir automáticamente `academy` a Escuela de Galotxas o Aprende a jugar.
- Asignar fuentes diferenciadas: dominio Laravel para Competición; artefactos compilados desde `knowledge/` para Aprende a jugar; `knowledge/` futuro más CMS/backend para Escuela; CMS para Club; composición híbrida para Inicio.
- No registrar ni enlazar una landing sin propósito, fuente, contenido inicial mínimo, destinos, SEO, responsive y pruebas definidos.
- Aplazar aliases y redirects hasta que exista equivalencia. Los cambios con valor SEO deberán coordinar React Router con respuestas de servidor/CDN y canonical.
- Utilizar `09-public-navigation.md` como contrato operativo de rutas, clasificación, compatibilidad y gates 3B/3C.
- Aplicar el contrato progresivamente: en 3B el menú muestra sólo Inicio y Competición; las áreas pendientes no aparecen deshabilitadas, y Torneos y Rankings pasan a destinos secundarios de la landing mínima `/competicion`.
- Conservar todas las URLs deportivas e institucionales heredadas y no introducir redirects en 3B.
- Incorporar en 3C un sistema común de presentación para landings, compuesto por contenedor, cabecera, acciones, secciones, navegación secundaria y tarjetas-enlace, sin crear un Layout ni un `<main>` alternativos.
- Mantener esos componentes desacoplados de API Laravel, CMS y `knowledge/`: reciben contenido y destinos mediante props o `children` y no almacenan contenido editorial.
- Adoptar inicialmente la estructura común sólo en `/competicion`; la 404 reutiliza únicamente acciones y metadatos porque no es una landing editorial.
- Gestionar título y meta description mediante un componente mínimo y reversible por ruta; aplicar `noindex` local a 404 sin introducir canonical, Open Graph o robots globales.
- No crear en 3C rutas, enlaces o placeholders para Aprende a jugar, Escuela o Club, ni avanzar el contenido dinámico de Competición previsto para Fase 4.
- Aplazar el componente común de estados remotos hasta disponer de dos adopciones con semántica compatible; los patrones actuales de Torneos, Rankings, CMS y Mi Panel no justifican una abstracción segura dentro de 3C.
- Adoptar en 4A `GET /api/v1/seasons` como fuente primaria única de `/competicion`, porque ya entrega temporadas efectivamente públicas, campeonatos asociados y recuentos de categorías suficientes para el resumen; no duplicar la carga con `/championships` ni endpoints de detalle.
- Mantener Laravel como fuente y filtro de visibilidad: React preserva el orden recibido, no consulta ni vuelve a filtrar `is_public` y no infiere estados deportivos a partir de fechas.
- Separar en 4A la comunicación existente de `championshipsService`, el estado en `useCompetitionOverview`, la presentación en componentes específicos y la composición en `CompetitionPage`; loading, error, retry y vacío permanecen locales hasta acreditar una segunda adopción común.

Alternativas descartadas:
- Conservar los ocho enlaces planos: mezcla áreas, funciones deportivas, páginas institucionales y una ruta técnica sin jerarquía estable.
- Usar Torneos, Rankings y Competición simultáneamente como áreas de primer nivel: fragmentaría un mismo dominio funcional.
- Convertir `academy` en Escuela sólo por su nombre: atribuiría a una página genérica un alcance híbrido, operativo y de privacidad que no existe.
- Hardcodear contenido institucional o formativo en React para completar las landings: crearía fuentes editoriales duplicadas.
- Crear páginas “próximamente”: produciría rutas sin valor, metadatos débiles y falsos criterios de completitud.
- Mover todas las rutas deportivas bajo `/competicion`: rompería URLs funcionales sin aportar una necesidad demostrada.
- Aplicar redirects permanentes desde el primer cambio de Navbar: impediría una migración observable y reversible antes de validar paridad.
- Integrar Mi Panel como sexta área pública: mezclaría navegación editorial con permisos y estado de sesión.

Consecuencias:
- La auditoría 3A no cambió elementos visibles; 3B incorpora `/competicion` y una navegación progresiva compartida por desktop y móvil.
- Inicio y la landing dinámica de Competición están implementadas; Aprende a jugar, Escuela y Club conservan gates editoriales explícitos y no aparecen como enlaces deshabilitados.
- La retirada de un enlace del Navbar, la conservación de una URL y un redirect son decisiones independientes.
- Las rutas legadas pueden coexistir durante la migración sin convertirse en fuente canónica futura.
- Desktop y móvil comparten configuración, nombres, orden y estado activo; la cuenta es un grupo separado.
- Torneos y Rankings siguen operativos como navegación secundaria; las rutas heredadas no se eliminan ni redirigen.
- React dispone de fallback 404, aunque el estado HTTP real continúa dependiendo del hosting.
- Fase 3C aporta la estructura visual y técnica común, headings, enlaces y metadatos básicos, validada inicialmente en `/competicion` sin desarrollar su contenido en profundidad.
- Fase 4A utiliza esa base para presentar temporadas y campeonatos públicos con enlaces por ID, estados remotos y datos nullable seguros; los componentes comunes continúan sin conocer el contrato deportivo.
- La base común puede presentar datos del dominio, artefactos de `knowledge/` o contenido CMS sin conocer ni sustituir esas fuentes de verdad.
- La 404 deja de heredar el título de la ruta anterior y restaura su `noindex` al navegar; la cobertura de metadatos del resto de rutas continúa incompleta.
- Aprende a jugar, Escuela y Club siguen sin rutas ni placeholders; al cierre de 4A, los bloques 4B y 4C estaban pendientes. ADR-029 registra la implementación posterior de 4B y su seguimiento documenta el cierre de 4C.
- Consolidación institucional, migraciones, aliases, redirects, canonical, indexación de `/contenidos` y SEO completo quedan para bloques posteriores.

---

# ADR-029 — Preview histórico independiente y rutas deportivas contextuales

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- Fase 4A ya cargaba temporadas y campeonatos públicos en `/competicion`, mientras `/rankings` consumía el ranking histórico completo mediante `GET /api/v1/rankings/all-time`.
- El backend entrega el ranking en orden canónico, con participantes oficiales primero, provisionales después y `position = null` cuando todavía no existe puesto oficial.
- El detalle de campeonato enlazaba al detalle de categoría, pero no hacía visibles sus rutas existentes de clasificación y calendario; esas URLs se construían además en varios consumidores.
- La carga de temporadas y la de ranking tienen disponibilidad y fallos independientes.

Decisión:
- Reutilizar `championshipsService.getAllTimeRanking` y el endpoint existente; no añadir ni modificar API, Resources, seeders o reglas de visibilidad.
- Mantener dos recursos remotos independientes en `/competicion`: `useCompetitionOverview` para temporadas y `useAllTimeRanking` para el ranking. Cada uno conserva loading, error, retry, vacío, protección frente a respuestas obsoletas y contenido propios.
- Presentar exclusivamente las primeras cinco filas recibidas mediante un corte visual, sin ordenar, recalcular posiciones, ponderar puntos ni interpretar las reglas deportivas en React.
- Mostrar sólo nombre público, posición cuando existe, señal comprensible para la fila sin posición oficial, puntos ponderados cuando el contrato entrega un número y la lista de categorías cuando aporta contexto real. No presentar `player_id` ni otros campos técnicos.
- Mantener `Ver ranking completo` disponible en todos los estados y conservar `/rankings` como experiencia completa, sin aplicarle el límite del preview.
- Centralizar únicamente los generadores de las rutas deportivas existentes y usarlos para ofrecer desde campeonato y categoría accesos explícitos al detalle, standings y schedule.
- Mantener el preview separado de calendarios, partidos recientes, resultados y clasificaciones, que continúan fuera del alcance de 4B.

Alternativas descartadas:
- Ordenar o volver a numerar las filas en React: duplicaría reglas cuya fuente de verdad es Laravel y alteraría la distinción oficial/provisional.
- Acoplar temporadas y ranking en un único `Promise.all`: un fallo parcial ocultaría contenido válido y mezclaría reintentos independientes.
- Reutilizar directamente la tabla completa de `/rankings` dentro de la landing: introduciría densidad, paginación visual y responsabilidades impropias de un preview.
- Crear un endpoint agregado específico para la landing: ampliaría innecesariamente el contrato API cuando los dos endpoints públicos existentes ya cubren los datos.
- Incluir calendarios, standings o resultados en `/competicion`: adelantaría Fase 4C.
- Duplicar strings de rutas en cada tarjeta: mantendría divergencias evitables sin aportar flexibilidad.

Consecuencias:
- `/competicion` sigue siendo útil si sólo una de las dos cargas remotas responde correctamente y ofrece reintento específico para la que falla.
- El máximo de cinco es una decisión de presentación local, no un cambio de contrato ni de ranking; `/rankings` continúa mostrando su colección completa.
- En el estado inicial del seeder E2E no existen partidos validados y el preview presenta su vacío real. Tras validar resultados, refleja las filas reales del backend sin fixtures frontend.
- Las rutas de campeonato y categoría se conservan exactamente; no hay aliases, redirects ni rutas nuevas.
- Fase 4B queda completada y deja el cierre compositivo y de recorrido para Fase 4C.

Seguimiento de Fase 4C, 2026-07-19:
- Se mantiene la decisión de no reordenar ni recalcular datos deportivos: standings, rankings de campeonato, temporada e histórico muestran la `position` entregada por backend o un fallback neutral.
- Las raíces y generadores de detalle de la rama deportiva se concentran en el mismo contrato de rutas; los retornos son deterministas y no usan historial implícito.
- El detalle de categoría se limita a su entidad y contexto, mientras clasificación y calendario permanecen en sus URLs dedicadas mediante una navegación común con `aria-current`.
- La landing prioriza un único acceso a Torneos y conserva el enlace a Rankings dentro de su bloque histórico, sin duplicar acciones ni incorporar tablas, partidos o resultados.
- Fase 4C y la Fase 4 global quedan completadas sin cambiar backend, API, Resources, rutas, seeders, Home, Navbar o `knowledge/`.

---

# ADR-030 — Contrato y compilación build-time del conocimiento canónico

Estado: Aceptada

Fecha aproximada: 2026-07

Contexto:
- `knowledge/` ya contenía Reglamento y Conceptos reales, pero trece documentos compilables carecían de slug y no existían contrato ejecutable, validación global o artefacto consumible.
- React no puede convertirse en fuente editorial ni leer Markdown suelto desde el navegador.
- El Manual v1 no necesita Laravel, base de datos, API, CMS, MDX o HTML ejecutable.
- El repositorio no dispone de CI o configuración de despliegue que garantice que un build con raíz `frontend/` pueda acceder a la carpeta hermana `knowledge/`.

Decisión:
- Mantener `knowledge/` como única fuente canónica y editarla mediante Git y revisión humana.
- Compilar en Node antes del futuro renderizado, sin dependencias nuevas y con un parser limitado a los seis valores escalares reales del front matter.
- Exigir `id`, `slug`, `titulo`, `version`, `estado` y `ultima_revision`; derivar colección de la ruta y orden del sufijo numérico del ID.
- Compilar sólo `REG-001`–`REG-008`, `conceptos/elementos`, `conceptos/personas` y `conceptos/juego`; excluir instrucciones, README y `REG-000`, que declara no formar parte del reglamento.
- Validar ID único global, slug único por namespace, ruta lógica única, SemVer, fecha ISO real, estados `Borrador`/`Vigente`, headings, referencias y seguridad.
- Exigir exactamente un H1 como primer heading, coincidente con `titulo`, y una jerarquía H1–H6 sin saltos arbitrarios.
- Impedir que un documento `Vigente` referencie un documento que no esté también `Vigente`; permitir que un borrador futuro relacione borradores o vigentes mientras los destinos existan.
- Registrar la aprobación editorial humana de REG-001–REG-008 como Reglamento inicial `Vigente`, sin reformular contenido, reglas, terminología o referencias y conservando sus versiones actuales.
- Tratar cualquier modificación editorial futura como una revisión consciente: deberá revisar la versión y actualizar `ultima_revision` cuando corresponda.
- Rechazar HTML, JSX/MDX, scripts, iframes, eventos, código ejecutable, URLs peligrosas, imágenes y rutas que salgan de `knowledge/`.
- Generar JSON con `schemaVersion: 1`, orden explícito y sin timestamp, rutas absolutas, datos del sistema o HTML precompilado.
- Versionar `frontend/src/generated/knowledge/knowledge.json` y comprobar en tests su igualdad byte a byte con el corpus.
- No acoplar todavía `dev` o `build` a la generación. Mantener `knowledge:check` y `knowledge:build` explícitos hasta disponer de un contrato de CI/despliegue fiable.
- No importar el artefacto canónico en páginas, crear una proyección pública ni registrar rutas durante 5A o su normalización 5A.1.
- En 5B, generar `public-knowledge.json` como proyección separada que incluye exclusivamente documentos `Vigente`, omite colecciones vacías y no conserva estado, `sourcePath`, Markdown, rutas lógicas editoriales ni información de borradores.
- Transformar el Markdown público durante build a una estructura cerrada de bloques e inline nodes; rechazar cualquier sintaxis no soportada en lugar de interpretarla en el navegador.
- Resolver sólo referencias explícitas hacia IDs públicos y sus rutas React; bloquear la generación si el destino no existe o no es publicable.
- Versionar los dos artefactos y promoverlos de forma coordinada con temporales, copias anteriores y rollback para no dejar una pareja desincronizada.
- Permitir que React importe únicamente `public-knowledge.json` mediante un repositorio de esquema v1 y renderice los nodos con HTML semántico, `Link` y sin `dangerouslySetInnerHTML`.
- Registrar `/aprende-a-jugar`, `/aprende-a-jugar/manual`, los documentos de Reglamento y los tres grupos de Conceptos; utilizar la 404 existente para cualquier grupo, slug o forma no válida.

Alternativas descartadas:
- Hardcodear el contenido en JSX: duplicaría la fuente editorial y exigiría despliegues para cada corrección.
- Cargar Markdown directamente en el navegador: expondría archivos sin contrato y trasladaría parsing y seguridad al cliente.
- Importar Reglamento y Conceptos a Laravel o MariaDB: añadiría persistencia y sincronización sin necesidad funcional para el Manual v1.
- Duplicar el contenido en el CMS: crearía dos autoridades editables para las mismas reglas.
- Usar MDX: permitiría componentes y expresiones ejecutables dentro de la fuente canónica.
- Compilar HTML sin una política de sanitización y renderer aprobados: ampliaría la superficie de ejecución antes de construir la experiencia pública.
- Importar `knowledge.json` desde React y filtrar allí: introduciría borradores y metadatos editoriales en el bundle antes de aplicar la política de publicación.
- Usar un parser Markdown en navegador: duplicaría el contrato build-time y ampliaría la superficie de sintaxis y ejecución del cliente.
- Degradar silenciosamente nodos no soportados: podría publicar contenido incompleto o con semántica distinta de la fuente revisada.
- Ignorar el artefacto y regenerarlo automáticamente en cada build: el contexto de despliegue actual no garantiza acceso a `knowledge/` desde la raíz frontend.

Consecuencias:
- Un cambio estructural inválido falla con un diagnóstico localizado antes de producir una salida nueva.
- Fuente y artefacto versionado deben actualizarse en el mismo cambio; el JSON generado nunca se edita a mano.
- Dos compilaciones del mismo corpus producen los mismos bytes.
- Las rutas lógicas del artefacto canónico no son URLs públicas. La proyección asigna rutas públicas mediante helpers cerrados para Reglamento y Conceptos.
- El corpus alimenta en 5B una proyección de 40 documentos `Vigente`; un borrador futuro permanecerá en el JSON canónico y quedará completamente fuera del público y del bundle.
- Una normalización técnica no autoriza cambios de texto editorial: estado, fecha y marcadores estructurales se revisan separadamente del contenido semántico.
- Fase 5B queda implementada con una experiencia inicial funcional de Aprende a jugar y Manual.
- Cuando CI y despliegue estén definidos podrá revisarse la política de versionado y generación automática sin cambiar la autoridad editorial.

Seguimiento de Fase 5C, 2026-07-21:

- El repositorio conserva como unidad de navegación cada colección canónica y resuelve posición, anterior y siguiente sin wrap ni cruces entre Reglamento y los tres grupos de Conceptos.
- La tabla de contenidos consume exclusivamente `headings` H2–H6 ya compilados, conserva sus IDs y no analiza bloques o Markdown en el navegador.
- Los fragmentos forman parte de las URLs documentales existentes; el montaje diferido desplaza al destino tras navegación SPA o carga directa y sólo solicita foco programático cuando el usuario activa el índice.
- La navegación de contexto es local a Aprende a jugar y no establece un sistema global de breadcrumbs.
- Las tres páginas de Aprende se cargan mediante `React.lazy` y `Suspense`; repositorio, renderer y `public-knowledge.json` quedan fuera del chunk inicial sin cambiar rutas, metadatos, Navbar o 404.
- El esquema v1, el corpus, los artefactos, el backend, la API y el CMS permanecen inalterados. Fase 5C y la Fase 5 quedan completadas.

---

## Mantenimiento

Cuando una decisión arquitectónica relevante cambie, deberá registrarse una nueva entrada en este documento en lugar de modificar silenciosamente una anterior.
