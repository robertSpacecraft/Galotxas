# Arquitectura — Galotxas

## Propósito

Este documento describe la arquitectura técnica del proyecto Galotxas y la relación entre sus distintas capas.

Complementa a `01-domain.md`, que describe el funcionamiento del negocio. Aquí se explica cómo dicho dominio se implementa técnicamente.

---

# 1. Visión general

Galotxas es un monorepo compuesto por dos aplicaciones que comparten un mismo dominio y una única base de datos.

Componentes principales:

- Frontend React.
- Backend Laravel.
- MariaDB.
- Docker como entorno de desarrollo y pruebas.

El backend constituye la fuente de verdad del dominio ejecutable y de la publicación de contenido administrable. `knowledge/` es la fuente editorial del conocimiento canónico y estable.

---

# 2. Arquitectura actual

Actualmente el proyecto se encuentra en una fase de consolidación.

Conviven componentes ya adaptados al patrón arquitectónico objetivo con otros que todavía mantienen una implementación heredada.

Esto es una decisión consciente para priorizar la estabilidad del proyecto y evitar reescrituras innecesarias.

Ejemplos:

- coexistencia de endpoints ya serializados mediante Resources con otros pendientes de normalización;
- controladores con distintos niveles de extracción hacia Services;
- panel administrativo Blade y frontend React funcionando de forma paralela.

La arquitectura documentada debe reflejar este estado real.

---

# 3. Arquitectura objetivo

La evolución prevista del proyecto persigue:

- separación clara entre Controllers, Services y Resources;
- utilización sistemática de Resources en la API;
- reducción progresiva de lógica en controladores;
- contrato API homogéneo;
- reutilización de Services;
- mantenimiento de compatibilidad con los consumidores existentes.

La arquitectura objetivo constituye una guía para el desarrollo futuro y no implica que todas las áreas del proyecto hayan alcanzado todavía ese nivel de homogeneidad.

---

# 4. Capas de la aplicación

## Frontend React

Responsabilidades:

- interfaz pública;
- zona privada del participante;
- navegación;
- experiencia de usuario;
- consumo de la API.

No implementa reglas deportivas.

## Backend Laravel

Responsabilidades:

- dominio;
- autenticación;
- autorización;
- API REST;
- panel administrativo Blade;
- persistencia;
- generación de competiciones;
- rankings;
- validación de resultados.

Toda decisión deportiva pertenece al backend.

## Base de datos

MariaDB es el único motor soportado por el proyecto.

No existe compatibilidad con SQLite.

Las pruebas utilizan una instancia MariaDB completamente aislada.

## Aislamiento de entornos Docker

Desarrollo, integración backend y E2E son proyectos Compose distintos y explícitos:

- `galotxas` usa `docker-compose.yml`, su red propia y el único volumen persistente de desarrollo;
- `galotxas-test` usa `docker-compose.test.yml`, una red propia y MariaDB sobre `tmpfs`;
- `galotxas-e2e` usa `docker-compose.e2e.yml`, una red propia y MariaDB sobre `tmpfs`.

Los archivos de test no declaran `container_name` ni referencian la red o el volumen de desarrollo. Los runners pasan siempre `--project-name` y el archivo absoluto esperado. Antes de cualquier cleanup, una guarda inspecciona `docker compose config`, el entorno, la base y los recursos etiquetados; sólo un helper validado puede ejecutar `down --volumes --remove-orphans`. El contrato completo está en `13-docker-environment-isolation.md`.

## Arquitectura híbrida de contenidos

La arquitectura pública aprobada conecta tres canales diferentes:

1. **Dominio funcional:** `Laravel → API → React` para competición, inscripciones, calendarios, resultados y rankings.
2. **Contenido administrable:** `Panel Blade → base de datos → API pública → React` para contenido institucional, noticias, actividades, convocatorias y demás información editable sin despliegue.
3. **Conocimiento canónico:** `knowledge/ → compilador validado → datos generados → React` para Manual, Reglamento, Conceptos y contenido pedagógico estable.

Los tres canales disponen ya de una base comprobable. En el tercero, Fase 5A implementa el contrato editorial, el validador, el compilador determinista y un artefacto JSON canónico versionado. Fase 5A.1 aprueba editorialmente el Reglamento inicial, normaliza los 40 documentos a un único H1 y deja todo el corpus en estado `Vigente`. Fase 5B añade una proyección pública independiente, un repositorio frontend, un renderer seguro y las rutas iniciales de Aprende a jugar y el Manual. Fase 5C completa esa experiencia con contexto local, índices derivados de headings compilados, navegación canónica dentro de cada colección, fragmentos estables y carga diferida de toda la rama. La primera versión no utiliza MDX, HTML ejecutable, base de datos, API Laravel ni CRUD Blade.

Una misma pieza no debe mantenerse de forma editable en más de un canal. Los criterios de elección y la matriz de fuentes se definen en `10-content-governance.md`.

### Contrato híbrido de Escuela de Galotxas

Fases 6A y 6A.1 definen Escuela como una vertical híbrida independiente de Aprende a jugar, Club y Competición. 6B.1 implementa su núcleo operativo, 6B.2 añade el flujo privado de inscripciones y 6B.3 incorpora centros y actividades exclusivamente administrativos:

- `knowledge/` podrá aportar metodología, iniciación y recursos pedagógicos estables únicamente cuando exista una colección real y aprobada; mientras tanto, Escuela enlazará al Manual existente;
- Laravel/MariaDB es la fuente del programa permanente, niveles, horarios, ubicaciones, inscripciones, centros, actividades y datos personales;
- Blade administra programa, niveles, ubicaciones, horarios, inscripciones, centros y actividades;
- `GET /api/v1/school` entrega sólo configuración, niveles, horarios, ubicaciones, apertura y contacto efectivos;
- `POST /api/v1/school/enrollments` recibe solicitudes anónimas o vinculadas opcionalmente a la sesión, siempre pendientes y sujetas a revisión;
- React compone `/escuela` y su formulario desde la API, pero no almacena contenido editorial ni decide visibilidad o mayoría de edad;
- el CMS genérico podrá conservar piezas no estructuradas, pero nunca alumnos, centros, actividades, horarios o solicitudes.

El contrato completo queda formado por `SchoolProgram`, `SchoolLevel`, `SchoolSchedule`, `SchoolLocation`, `SchoolEnrollment`, `EducationalCenter` y `EducationalActivity`. Todos disponen de persistencia y dominio. `SchoolEnrollment` añade su servicio transaccional, controlador público de escritura y administración Blade. `EducationalActivityService` es el único punto para crear actividades planificadas, aplicar las transiciones definitivas, validar alumnado al completar y borrar únicamente registros planificados; sus controladores Blade no aceptan `status` desde el formulario general.

`SchoolLocation` es propia del dominio escolar y se comparte entre horarios y actividades. El `Venue` existente no se reutiliza: el generador de liga trata todos los registros de `venues` como pistas competitivas y sus relaciones y restricciones de borrado están acopladas a partidos y reprogramaciones.

La persistencia mantiene defaults privados e inactivos y claves foráneas restrictivas. Un `SchoolProgramService` coordina la escritura del programa dentro de una transacción y bloquea el programa público existente. MariaDB completa la garantía concurrente mediante `public_slot`, una columna generada que vale `1` sólo para el programa público y `NULL` para los privados, con índice único. No se despublica silenciosamente otro registro.

Los scopes `effectivelyPublic()` de programa, nivel y horario centralizan la futura consulta pública. El horario efectivo exige programa público, nivel activo y público, horario activo y ubicación activa; no se propagan flags al ocultar padres. `SchoolDayOfWeek` aporta el valor ISO int-backed y las etiquetas administrativas.

La Escuela admite menores y adultos. El representante se exige sólo al menor calculado desde nacimiento y fecha de solicitud; teléfono y correo siempre son obligatorios. No se gestionan plazas, pagos, asistentes nominales de centros o cuentas obligatorias.

`SchoolEnrollmentService` resuelve dentro de transacciones el único programa público abierto, valida la coherencia programa–nivel y aplica las únicas transiciones admitidas. MariaDB refuerza la coherencia del nivel con una clave foránea compuesta; los programas y niveles no se borran si tienen inscripciones, mientras la cuenta nullable usa `nullOnDelete`. La edad se calcula respecto de `requested_at` mediante fechas inmutables.

La escritura pública se limita a cinco intentos por minuto por combinación de IP y hash SHA-256 del correo normalizado. La respuesta `201` sólo contiene confirmación genérica; programa no disponible responde `409` sin distinguir ausencia, privacidad o cierre. No existen lectura, consulta individual, Resources públicos ni API administrativa de inscripciones.

Centros y actividades constituyen un subdominio operativo separado de las inscripciones. Sus dos tablas usan claves foráneas restrictivas, sus registros no se publican, Blade es su único consumidor y no existe API pública o administrativa. Los centros nacen inactivos; las actividades nacen `planned` y sólo pasan a `completed` o `cancelled`. Desactivar un centro o una ubicación preserva las relaciones históricas y sólo bloquea asociaciones nuevas.

`SchoolPublicOverviewService` centraliza la consulta pública de sólo lectura: resuelve el programa público, restringe y ordena niveles y horarios, limita las columnas cargadas y obtiene todas las relaciones mediante eager loading. `SchoolController` sólo coordina esa consulta y el envelope. `PublicSchoolResource`, `PublicSchoolLevelResource`, `PublicSchoolScheduleResource` y `PublicSchoolLocationResource` aplican allowlists por contexto y no consultan la base de datos.

La ausencia de programa público devuelve `200` con `data: null`; la respuesta no diferencia ausencia, privacidad o configuración incompleta. Con programa público, el contrato admite contacto nullable, ubicación habitual activa opcional, niveles sin horarios y datos parciales. Inscripciones, usuarios, centros, actividades, notas, flags y timestamps permanecen fuera de la lectura.

Fase 6C completa el consumidor React en `frontend/src/features/school/`: servicio Axios y normalización ligera, hook remoto local, helpers puros, landing diferida y formulario. El cliente conserva el orden recibido, no importa el corpus Knowledge, no persiste datos personales y trata `data: null` como ausencia válida. Fase 6C.1 revalida ese cierre tras separar los tres entornos Docker; no cambia el dominio ni los contratos School. El contrato completo está en `12-school-of-galotxas.md`.

## Canalización build-time de Knowledge

`frontend/scripts/knowledge/` descubre únicamente las cuatro colecciones aprobadas, parsea el subconjunto escalar del front matter, valida UTF-8/LF, metadatos, IDs, slugs, rutas lógicas, headings, referencias y contenido no ejecutable, y serializa dos artefactos con `schemaVersion: 1`: `frontend/src/generated/knowledge/knowledge.json`, que conserva el corpus editorial completo, y `frontend/src/generated/knowledge/public-knowledge.json`, que sólo contiene documentos `Vigente`. Cada documento exige exactamente un H1 inicial coincidente con `titulo`, niveles H1–H6 y una jerarquía sin saltos; las secciones y subsecciones actuales usan H2 y H3.

El grafo editorial también forma parte del contrato canónico: un documento `Vigente` sólo puede referenciar otro documento `Vigente`. Los borradores futuros podrán relacionarse con borradores o vigentes dentro del artefacto canónico, pero se excluyen por completo de la proyección pública. Si una referencia pública explícita no resuelve a otro documento público y a una ruta permitida, la generación falla antes de escribir. El corpus actual proyecta 40 documentos y cuatro colecciones.

El mismo corpus genera los mismos bytes en ambos artefactos: colecciones, documentos, headings y referencias tienen orden explícito; los JSON no incorporan tiempo de ejecución, rutas absolutas, usuario o metadatos Git. La escritura coordinada prepara las dos salidas temporales, conserva copias de las versiones anteriores y restaura ambas si falla cualquiera de las promociones.

Los dos artefactos se versionan porque no existe todavía configuración CI o de despliegue que garantice acceso desde una raíz `frontend/` a la carpeta hermana `knowledge/`. Por esa misma razón, `npm run dev` y `npm run build` no ejecutan implícitamente el compilador. `npm run knowledge:check` valida en memoria ambas salidas y su determinismo sin escribir; `npm run knowledge:build` regenera el par y la suite comprueba su sincronía byte a byte.

La proyección transforma durante build el subconjunto real de Markdown en nodos serializables de heading, párrafo, listas, tabla, separador, texto, negrita, énfasis y referencia. El H1 se excluye de los bloques; React lo toma de `title`. Cualquier HTML, imagen, blockquote, bloque de código, lista anidada, tabla inconsistente o inline ambiguo no soportado bloquea la proyección. `frontend/src/features/knowledge/` importa exclusivamente el artefacto público, valida su versión y resuelve colecciones, documentos y rutas. `KnowledgeRenderer` convierte sólo esos nodos a HTML semántico y enlaces React, sin `dangerouslySetInnerHTML`, parsing Markdown o peticiones HTTP.

La canalización no crea una API, CMS o segunda fuente editorial. Las rutas `/aprende-a-jugar`, `/aprende-a-jugar/manual`, `/aprende-a-jugar/manual/reglamento/:slug` y `/aprende-a-jugar/manual/conceptos/:group/:slug` consumen la proyección local. `App.jsx` registra esas mismas rutas mediante `React.lazy` y `Suspense`: el repositorio, el renderer y `public-knowledge.json` quedan fuera del chunk inicial y sólo se descargan al entrar en Aprende. El fallback anunciado no añade otro `<main>` o `h1`, y los errores de carga no se convierten en la 404 documental. El contrato completo, las colecciones, nodos y exclusiones se mantienen en `11-knowledge-pipeline.md`.

## Base de visibilidad de la competición

La competición funcional separa dos dimensiones persistidas:

- **estado operativo**, expresado por los estados propios de temporada, campeonato o categoría;
- **visibilidad declarada**, expresada por el booleano `is_public` y gestionada desde Blade.

`is_public` no se deriva de estados, fechas, inscripciones, calendarios o resultados. Los modelos `Season`, `Championship` y `Category` lo castean a booleano y los nuevos registros son privados por defecto. La migración de incorporación marca como públicos los registros preexistentes para preservar su accesibilidad anterior.

La administración valida la jerarquía Temporada → Campeonato → Categoría al activar la visibilidad. Desactivar un padre no propaga escrituras a sus hijos: la visibilidad declarada de cada descendiente se conserva. La visibilidad efectiva es la conjunción de los flags de la rama completa.

`Season`, `Championship`, `Category` y `GameMatch` ofrecen scopes locales `effectivelyPublic()` y métodos de instancia basados en la misma consulta. No existen global scopes: cada controlador público opta expresamente por el filtro, mientras administración, generación, servicios internos y endpoints personales mantienen acceso a las entidades relacionadas con el usuario.

Los listados filtran primero la entidad raíz y restringen el eager loading de campeonatos y categorías, por lo que un Resource no puede serializar descendientes privados ni provocar lazy loading sin filtrar. Los detalles públicos verifican la misma regla y responden `404` cuando la rama es privada. Los rankings públicos activan explícitamente el filtro de partidos, sin cambiar el conjunto utilizado por los mismos Services en ámbitos internos.

`is_public` no forma parte de ningún contrato público. Los modelos ocultan el flag de su serialización Eloquent y no lo admiten mediante asignación masiva. Tanto Blade como la API administrativa lo asignan de forma explícita después de validar la misma jerarquía. No se añade un índice simple sobre el booleano, de baja cardinalidad: cualquier optimización de las consultas jerárquicas queda condicionada a medición real.

## API administrativa de competición

Los CRUD API de temporadas, campeonatos y categorías se mantienen separados de las consultas públicas aunque compartan modelos y reglas de integridad. Sus rutas planas bajo `/api/v1/admin` requieren Sanctum, usuario activo y rol administrador; no aplican `effectivelyPublic()`, por lo que permiten gestionar registros privados.

Las escrituras reutilizan los Form Requests de Blade cuando el contrato coincide. La creación plana de categorías amplía esas reglas mediante un Request API específico que exige un `championship_id` existente; la actualización no admite ese campo y conserva la relación. Los métodos de escritura de estos tres CRUD trabajan exclusivamente con `validated()`, construyen los atributos permitidos de forma explícita, derivan los slugs existentes del nombre y asignan `is_public` fuera de la asignación masiva. `image_path`, identificadores, timestamps y relaciones deportivas quedan fuera de la whitelist.

`AdminSeasonResource`, `AdminChampionshipResource` y `AdminCategoryResource` delimitan las respuestas de este contexto y exponen `is_public` junto con los datos administrativos necesarios. Los Resources públicos permanecen independientes, no incluyen ese flag y reciben exclusivamente consultas ya filtradas. Esta separación evita que la capacidad administrativa de consultar entidades privadas debilite la visibilidad efectiva pública.

## Arquitectura CMS pública

La primera base backend del CMS público sigue el mismo patrón general del proyecto:

- **Backend Laravel**: modelos `CmsPage` y `CmsBlock`, migraciones MariaDB y enums de estado/tipo.
- **Panel Blade**: gestión administrativa básica de páginas CMS, estado de publicación, metadatos SEO y bloques estructurados.
- **API pública**: endpoints de solo lectura para listar páginas publicadas y entregar una página publicada por `slug`.
- **Resources públicos**: `PublicCmsPageSummaryResource`, `PublicCmsPageResource` y `PublicCmsBlockResource` controlan el contrato serializado.
- **React**: consumo de la API pública desde `/contenidos` y `/contenidos/:slug`
  y desde cuatro fachadas canónicas Club, con un único renderer de bloques
  estructurados y sin HTML libre.
- **Navegación pública**: 7C.2 registra las fachadas Club, pero el disclosure
  del Navbar permanece pendiente de 7D; las URLs `/contenidos/{slug}` continúan
  como acceso legado.

La subida de documentos o imágenes, las noticias como entidad propia y los formularios públicos quedan fuera de esta base inicial.

7C.1 amplía de forma acotada los enlaces del CMS: `button` y
`document_link` aceptan una dirección `mailto:` válida, mientras imágenes y
galerías conservan exclusivamente rutas internas y `http(s)`. Backend y
renderer aplican la misma separación y continúan rechazando esquemas
peligrosos.

`/contenidos` representa la estructura pública actual del CMS, pero se considera legada respecto a la arquitectura de información aprobada. Su implementación, API y contenido permanecen sin cambios hasta que se complete el inventario y la migración por áreas.

## Arquitectura técnica de contacto institucional

La infraestructura de contacto es una vertical funcional distinta del CMS:

- `ContactRequest` y MariaDB conservan el mensaje mínimo, consentimiento y HMAC
  de IP;
- un Form Request allowlisted normaliza y valida el payload anónimo;
- middleware de feature flag, honeypot y rate limiter HMAC protegen el POST;
- `ContactRequestService` persiste antes de delegar una notificación opcional;
- Resources públicos cierran la configuración a `enabled` y el acuse a
  `received`;
- Blade usa el middleware administrativo existente y permite listado, detalle
  y transiciones conservadoras;
- el servicio React alimenta el panel funcional de `/club/contacto`, que sólo
  monta campos cuando la configuración pública devuelve `enabled: true` y no
  bloquea el contenido CMS si falla.

`CONTACT_FORM_ENABLED=false` y `CONTACT_NOTIFICATION_ENABLED=false` son los
defaults. La activación productiva depende de privacidad y operación. El
destinatario nunca forma parte de respuestas públicas. `frontend/dist`
continúa como artefacto Vite ignorado y no participa como fuente de este flujo.

## Gestión de pistas y generación de calendarios

La configuración de pistas se mantiene en el backend mediante el modelo `Venue` y un CRUD web protegido por el middleware administrativo. Los Form Requests validan los tres campos persistidos actualmente (`name`, `location` y `description`). No existe todavía un campo `active` en el esquema.

`Venue` expone relaciones explícitas con `GameMatch` y `MatchRescheduleRequest`. El panel solo permite borrar una pista cuando ninguna de esas relaciones existe, aunque la clave foránea histórica de `game_matches` admita `nullOnDelete`; esta defensa de aplicación evita perder la pista asignada a un partido.

`DefaultVenueSeeder` es un seeder de ejecución explícita, no registrado en `DatabaseSeeder`. Crea por nombre un conjunto mínimo estable y usa `firstOrCreate`, por lo que no necesita ni fuerza IDs concretos y no modifica pistas preexistentes.

`GenerateLeagueScheduleService` obtiene una sola vez todas las pistas mediante una consulta ordenada por `id`. La selección no depende de IDs consecutivos, nombres, modalidad, nivel de categoría ni de `DefaultVenueSeeder`. El orden estable se reutiliza al construir los huecos de cada jornada, por lo que una misma base de datos produce el mismo reparto.

Cada pista aporta los siete huecos temporales heredados por jornada. Dentro de la categoría procesada, el servicio nunca genera dos partidos con la misma combinación de pista y fecha/hora; si los cruces exceden la capacidad disponible, lanza un error dentro de la transacción. Todas las rondas y partidos de la liga se crean en una única transacción, de modo que una insuficiencia detectada tras crear una ronda o cualquier otro fallo de persistencia revierte la operación completa.

Si no existe ninguna pista, el servicio falla antes de abrir la transacción y antes de crear datos. No se ha añadido un scope `active()` porque el modelo no soporta ese estado.

La ocupación se calcula únicamente para la categoría que se está generando. Evitar solapamientos con calendarios ya generados de otras categorías exigiría coordinación de disponibilidad compartida y bloqueo concurrente; esa capacidad queda fuera de SCHEDULE-1.

---

# 5. Interfaces del sistema

Actualmente existen dos interfaces oficiales.

## Panel administrativo

Tecnología:

- Laravel Blade
- Bootstrap

Gestiona el dominio mediante controladores web.

## Frontend React

Consume exclusivamente la API REST.

No accede directamente a la base de datos.

---

# 6. Flujo de comunicación

Panel Blade

Administrador
→ Blade
→ Controllers Web
→ Services
→ Eloquent
→ MariaDB

Frontend

Usuario
→ React
→ API REST
→ Resources
→ Services
→ Eloquent
→ MariaDB

---

# 7. Organización del backend

La arquitectura backend se basa en responsabilidades separadas.

- Controllers coordinan peticiones.
- Form Requests validan entradas.
- Services implementan lógica de negocio.
- Resources serializan la API.
- Middleware controla la autorización y otras responsabilidades transversales.
- Models representan entidades persistentes.

Actualmente la autorización del proyecto se basa principalmente en Middleware y comprobaciones explícitas.

Las Policies forman parte de la arquitectura objetivo y podrán incorporarse progresivamente cuando aporten una mejora clara respecto a la implementación existente.

En el área privada, `BuildMyRankingsService` coordina el caso de uso de rankings del participante. Localiza sus categorías, reutiliza `BuildCategoryRankingService` para el cálculo deportivo y entrega una estructura explícita al Resource, evitando que el controlador gestione consultas, búsqueda de posición o modelos Eloquent.

Esta extracción no sustituye ni modifica el servicio de ranking de categoría.

`BuildCategoryRankingService` construye primero las estadísticas globales y agrupa las filas por puntos. El enfrentamiento directo se resuelve fuera del comparador general y únicamente para grupos de dos entradas; así un ciclo entre tres o más participantes no introduce un comparador no transitivo. Los grupos múltiples continúan por diferencia, juegos a favor, nombre e identificador estable.

Los rankings de campeonato, temporada e histórico mantienen sus criterios agregados existentes y utilizan `player_id` como último desempate técnico cuando también coincide el nombre. `BuildAllTimeRankingService` calcula `win_rate` en escala porcentual `0–100`; React solo formatea ese valor y no vuelve a calcularlo.

## Coordinación del workflow de resultados

`MatchController` coordina los endpoints del participante y delega la entrada en Form Requests. `MatchResultReportService` concentra autorización funcional, bloqueo del partido, persistencia de reportes y transiciones; `MatchResultService` valida las reglas deportivas del tanteo y determina el ganador.

El envío se ejecuta dentro de una transacción MariaDB y bloquea la fila del partido con `lockForUpdate`. La restricción única por partido y lado, junto con la comprobación de dominio, impide que un mismo jugador o su compañero sobrescriban el reporte existente. Crear el segundo reporte, comparar ambos y validar el partido o marcar el conflicto constituye una única operación atómica.

Una coincidencia valida ambos reportes y publica el resultado oficial. Una discrepancia conserva ambos como `conflict`, limpia cualquier tanteo oficial y mueve el partido a `under_review`.

La interfaz Blade de conflictos entra por `Admin\MatchConflictController`: lista y carga exclusivamente partidos `under_review`, mientras `ResolveMatchConflictRequest` autoriza al administrador activo y valida la forma básica del tanteo. Tanto este controlador como el endpoint administrativo existente delegan la resolución en `MatchResultService`. El servicio abre una transacción, bloquea la fila del partido, vuelve a comprobar el estado y las reglas deportivas, fija tanteo, ganador y `validated_by`, y finalmente cambia el estado a `validated`. Los `MatchResultReport` se consultan para la revisión, pero nunca se reescriben durante la resolución.

Los Resources específicos por contexto siguen delimitando la salida: los participantes reciben el contrato privado mínimo y los usuarios ajenos conservan únicamente el detalle público.

---

# 8. Organización del frontend

La arquitectura del frontend evoluciona hacia una organización por funcionalidades.

Los elementos principales son:

- páginas;
- componentes;
- hooks;
- contextos;
- servicios API;
- CSS Modules.

Cada capa debe tener una responsabilidad clara.

Mi Panel consulta `GET /api/v1/me/matches/pending-actions` al montar el Dashboard del jugador. El backend determina el tipo de intervención y lo serializa mediante `PendingMatchActionResource`; React se limita a representar la etiqueta, los estados remotos y el enlace a `/matches/{id}`. Al abandonar el detalle y volver a Mi Panel, el Dashboard se monta de nuevo y vuelve a solicitar el resumen, sin polling ni estado deportivo duplicado en el cliente.

## Configuración del cliente API

El frontend centraliza todas las peticiones HTTP en la instancia Axios de `frontend/src/api/client.js`.

La URL base se resuelve por entorno:

- `VITE_API_BASE_URL`, cuando se configura, tiene prioridad y se normaliza eliminando espacios exteriores;
- durante desarrollo, si no existe variable, se utiliza `http://localhost:8080/api/v1`;
- en builds de producción sin variable se utiliza `/api/v1`, asumiendo un proxy inverso bajo el mismo dominio.

Los servicios funcionales no deben duplicar esta resolución ni definir URLs base propias. Los despliegues con frontend y API en dominios distintos deben configurar `VITE_API_BASE_URL` durante el build.

## Rutas React implementadas

`frontend/src/App.jsx` registra actualmente:

- `/`: inicio público;
- `/competicion`: landing dinámica de temporadas y campeonatos públicos, preview del ranking histórico global y acceso a los destinos deportivos, sobre el sistema común de landings públicas;
- `/aprende-a-jugar` y sus rutas de Manual: conocimiento público compilado y carga diferida;
- `/escuela`: landing diferida del programa público, niveles, horarios, ubicaciones, contacto e inscripción;
- `/club/quienes-somos`, `/club/contacto`, `/club/federarse` y
  `/club/documentos`: fachadas diferidas sobre un mapeo cerrado de slugs CMS;
- `/nosotros`: página estática heredada;
- `/torneos` y `/torneos/:championshipId`: listado y detalle de campeonatos;
- `/categories/:categoryId`, `/categories/:categoryId/standings` y `/categories/:categoryId/schedule`: detalle, clasificación y calendario de categoría;
- `/matches/:matchId`: detalle público o workflow de resultado según la sesión;
- `/rankings`: rankings públicos;
- `/contenidos` y `/contenidos/:slug`: índice y páginas CMS;
- `/login`, `/register`, `/forgot-password` y `/reset-password`: autenticación;
- `/player`: Mi Panel protegido por sesión React.
- `*`: fallback React accesible para cualquier URL no reconocida.

No existe un panel administrativo React. Tampoco existen todavía rutas React para reprogramación ni edición completa del perfil.

El calendario y la clasificación independientes de categoría obtienen su contexto mediante `GET /categories/{id}` y, en paralelo, consumen sus colecciones dedicadas. Las llamadas pasan por `championshipsService`: React no reconstruye contenedores inexistentes, no calcula posiciones ni reglas deportivas y usa `position` y los valores entregados por Laravel. Un fallo del contexto conserva la colección disponible con fallbacks explícitos; un fallo de la colección produce un estado de error recuperable sin ocultar la navegación contextual.

`frontend/src/navigation/competitionRoutes.js` centraliza las raíces estáticas y la construcción defensiva de las URLs deportivas reutilizadas. No registra rutas ni aliases: refleja exclusivamente Competición, Torneos, Rankings y los detalles existentes de campeonato, categoría, standings, schedule y partido ya declarados en `App.jsx`. `CategoryNavigation` comparte las tres vistas de categoría y marca la actual con `aria-current="page"`; los retornos apuntan a un destino determinista de la jerarquía y no dependen del historial del navegador.

`frontend/src/navigation/publicNavigation.js` es la fuente única del menú editorial. Tras 6C contiene, en orden, Inicio, Competición, Aprende a jugar y Escuela de Galotxas; Club continúa ausente. Torneos y Rankings siguen disponibles como destinos secundarios y las rutas CMS e institucionales conservan acceso directo sin ocupar el primer nivel. Los matchers compartidos activan cada rama; la ruta exacta utiliza `aria-current="page"` y las ubicaciones secundarias `aria-current="location"`.

En móvil y tablet se reutiliza ese mismo array mediante estado React y un botón con `aria-expanded` y `aria-controls`; el menú se cierra al seleccionar una ruta, al cambiar la ubicación, mediante el propio botón o con Escape, que devuelve el foco al control. Los enlaces cerrados quedan fuera de la navegación por teclado mediante el estado visual responsive. La cuenta es un grupo accesible hermano: el visitante recibe Iniciar sesión y el usuario autenticado conserva saludo, Mi Panel y Salir.

El router no define nesting, loaders ni acciones, pero sí una ruta wildcard final que muestra una experiencia 404 con enlaces de recuperación y sin redirección automática. El servidor SPA puede seguir entregando inicialmente `index.html` con HTTP 200; coordinar una respuesta HTTP 404 real pertenece al despliegue posterior. Home y el índice CMS ya no crean un segundo `<main>` dentro del landmark global. El footer continúa montándose sólo en Home y no contiene navegación; no forma parte del sistema común de landings. La rama no consumida de `ProtectedRoute` hacia `/dashboard` se mantiene documentada como deuda, sin crear esa ruta.

## Sistema común de landings públicas

`frontend/src/components/PublicLanding/` contiene la base visual y semántica incorporada en Fase 3C. Es una capa de presentación independiente de Laravel, CMS, `knowledge/`, slugs y servicios concretos: recibe títulos, introducciones, acciones, destinos y contenido mediante props o `children`.

- `PublicLanding` aporta un contenedor `<article>` responsive, sin crear un Layout paralelo ni un segundo `<main>`.
- `LandingHeader` produce el único `h1`, asocia su introducción y admite acciones opcionales controladas.
- `LandingSection` exige un identificador explícito y estable, usa `<section>` y enlaza su `h2` mediante `aria-labelledby`.
- `LandingActions`, `LandingLinkGrid` y `LandingLinkCard` generan navegación React Router real, targets de al menos 44 px, foco visible y una única interacción por tarjeta.
- `PageMetadata` actualiza título y descripción por ruta sin dependencias, reutiliza una meta description existente y restaura el estado anterior al desmontarse. Competición y sus vistas deportivas aportan metadatos básicos con sus datos disponibles; la 404 añade `noindex` de forma local y reversible. No se implementan canonical, Open Graph ni robots globales.

El módulo CSS común usa grids fluidos, corte a una columna cuando no hay espacio y texto no truncado, sin alturas rígidas ni estilos globales nuevos. La matriz Playwright valida 320, 375, 768, 1024, 1280 y 1440 px, además del acceso por Tab y activación con Enter.

La adopción comenzó en `/competicion` y se extendió a Aprende a jugar y `/escuela`. Escuela reutiliza cabecera, secciones, acciones y metadatos, pero mantiene sus estados y componentes operativos dentro de la feature. Home no se ha refactorizado como landing común; en 6C sólo sustituye su referencia pública “Academy” por una tarjeta-enlace funcional a `/escuela`.

`championshipsService.getSeasons` mantiene la comunicación HTTP y extrae `data` del envelope; `useCompetitionOverview` coordina carga, éxito, error, vacío, reintento y descarte de respuestas posteriores al desmontaje; los componentes específicos `CompetitionOverview`, `CompetitionSeason` y `CompetitionChampionshipCard` se limitan a presentar el contrato deportivo, y `CompetitionPage` compone esos estados dentro de `PublicLanding`. No se consulta `/championships` ni detalles para construir el resumen, porque `/seasons` ya entrega los campeonatos públicos y su recuento de categorías en una sola respuesta.

`championshipsService.getAllTimeRanking` sigue siendo el único consumidor HTTP del ranking histórico tanto para `/rankings` como para la landing. `useAllTimeRanking` aporta a 4B un ciclo remoto propio con reintento y descarte de respuestas obsoletas, mientras `CompetitionRankingPreview` limita visualmente la colección con `slice(0, 5)`. No ordena, puntúa, posiciona ni completa filas: muestra el nombre público, `position` sólo cuando existe, `weighted_points` sólo cuando es numérico y `categories_played_list` sólo cuando contiene contexto real; `player_id` se usa únicamente como clave técnica. `/rankings` conserva su tabla completa y su paginación visual previa.

Las cargas de temporadas y ranking son independientes: el error, retry, loading o vacío de una no bloquea ni borra la otra. Fase 4C aplica el mismo principio por recurso en Torneos, campeonato, clasificación, calendario y Rankings, sin crear un estado remoto global para contratos distintos. React conserva el orden de la API, no interpreta ni vuelve a filtrar `is_public` y no deriva posiciones, oficialidad o reglas; Laravel continúa siendo la fuente de verdad. Los datos nulos se omiten cuando no aportan información, los desconocidos usan fallbacks neutrales y cada bloque conserva un reintento acotado cuando es recuperable.

El cierre 4C ordena `/competicion` como propósito, acceso principal a Torneos, temporadas y campeonatos, y ranking histórico. Elimina el acceso duplicado a Rankings porque el propio bloque histórico mantiene su enlace completo. `/torneos` distingue error de vacío y cada tarjeta ofrece una sola acción al detalle. El campeonato mantiene su información si falla el ranking; el detalle de categoría queda como resumen de la entidad y deja standings y schedule en sus URLs compartibles. Partido regresa al calendario real de su categoría. No se añaden endpoints, rutas, filtros, cálculos, resultados destacados ni bloques nuevos en la landing.

## Arquitectura pública objetivo

Fase 7B sustituye la topología plana inicial por cuatro controles editoriales:

- **Inicio** (`/`) es un enlace a la landing híbrida.
- **Competición** (`/competicion`) es un enlace al dominio público deportivo y
  agrupa semánticamente Torneos, Rankings, Calendarios, Clasificaciones y
  Resultados sin mover sus rutas.
- **Aprende** es un disclosure sin ruta propia. Agrupa Aprende a jugar
  (`/aprende-a-jugar`), Manual y reglas (`/aprende-a-jugar/manual`) y Escuela
  de Galotxas (`/escuela`).
- **Club** es un disclosure sin landing propia. Agrupa
  `/club/quienes-somos`, `/club/contacto`, `/club/federarse` y
  `/club/documentos`, implementadas como fachadas CMS diferidas en 7C.2.

La zona de autenticación conservará identidad, acceso, Mi Panel y cierre de sesión como bloque separado del menú editorial. Las rutas actuales de Torneos, Rankings y detalles deportivos permanecen como destinos funcionales secundarios; no se trasladarán bajo `/competicion` sin una necesidad demostrable. `/contenidos` y `/contenidos/:slug` permanecen como compatibilidad técnica durante una migración incremental, pero no formarán parte del primer nivel final.

La agrupación es sólo arquitectura de información. Escuela conserva su dominio
Laravel, ruta y contrato; no se fusiona con Knowledge. Club utiliza CMS como
fuente única, mientras React aporta fachadas de ruta y presentación. El padre
Club no enlaza `/club`, porque no existe una función independiente que
justifique otra landing.

En el estado actual están registradas `/`, `/competicion`,
`/aprende-a-jugar`, `/escuela` y las cuatro fachadas Club, mientras el Navbar
continúa mostrando cuatro enlaces planos. Competición presenta datos públicos
reales; Aprende a jugar deriva sus 40 documentos y cuatro colecciones; Escuela
presenta el agregado `GET /api/v1/school`; y Club presenta sólo las páginas que
Laravel considera publicadas. Los disclosures siguen pendientes de 7D.
El detalle operativo se mantiene en `09-public-navigation.md` y el cierre
editorial de 7B en `15-mvp-editorial-and-navigation-contract.md`.

La secuencia aprobada separa responsabilidades: 3B–3C establecieron navegación y landings; 4A–4C completaron Competición; 5A–5C consolidaron Knowledge y Aprende; 6A–6B.4 establecieron el dominio escolar, 6C publica su consumo y 6C.1 revalida el cierre de la Fase 6 con aislamiento Docker efectivo. Consolidación institucional, migraciones, aliases, redirects, canonical, indexación de `/contenidos` y SEO completo quedan en bloques independientes posteriores.

---

# 9. Contrato API

La API constituye el contrato entre backend y consumidores.

Los consumidores nunca deben depender de modelos Eloquent.

Las respuestas relevantes deben serializarse mediante Resources.

La normalización completa del contrato se documenta en `03-api-contract.md`.

---

# 10. Seguridad

La arquitectura incorpora actualmente:

- Sanctum;
- Middleware;
- comprobación de usuarios activos;
- rate limiting;
- recuperación segura de contraseña;
- separación entre recursos públicos y privados.

La incorporación de Policies forma parte de la arquitectura objetivo, pero actualmente la autorización se implementa mediante Middleware y comprobaciones explícitas.

---

# 11. Testing

Se distinguen claramente:

- base de desarrollo;
- base de pruebas.

Las pruebas nunca deben ejecutarse sobre la base de desarrollo.

La ejecución oficial utiliza Docker y una instancia MariaDB temporal.

El frontend utiliza Vitest integrado en `vite.config.js`, React Testing Library y `@testing-library/user-event`. Los tests de componentes se ejecutan en `jsdom`, cargan los matchers de `jest-dom` desde un setup central y limpian el árbol React después de cada caso.

Las pruebas se mantienen junto al código cubierto. `renderWithProviders` aporta `MemoryRouter`, rutas parametrizadas y un `AuthContext` controlado cuando resulta necesario; los hooks y servicios remotos se simulan de forma localizada en cada suite. Esta capa valida utilidades, contratos de presentación e interacciones React sin iniciar Laravel ni realizar llamadas HTTP reales.

Vitest/RTL no sustituye las pruebas Feature de Laravel ni constituye E2E.

El smoke E2E implementado utiliza Playwright con Chromium y el proyecto Compose explícito `galotxas-e2e`. Levanta Laravel, Nginx y una MariaDB `galotxas_e2e` sobre `tmpfs`, ejecuta `E2ESmokeSeeder`, inicia Vite desde el runner oficial y recorre frontend React y panel Blade con servicios reales. La suite es serial. El script valida la configuración resuelta antes de levantar y antes de limpiar, y elimina únicamente contenedores y red etiquetados para ese proyecto.

El E2E cubre el recorrido crítico del MVP; no constituye una matriz multibrowser ni sustituye QA visual/manual.

---

# Auditoría de paridad y arquitectura pendiente del MVP

Fase 7A compara las capacidades reales de Laravel, Blade, API y React en
`14-mvp-parity-audit.md`. La arquitectura funcional ya implementada cubre
Competición, Knowledge, Escuela, autenticación y la administración interna,
pero todavía no acredita una experiencia institucional canónica ni un
despliegue productivo completo.

Fase 7B aprueba documentalmente agrupar los destinos formativos bajo
**Aprende** sin fusionar sus fuentes: Aprende a jugar y Manual continúan en la
proyección build-time de `knowledge/`, mientras Escuela conserva su dominio
Laravel y su ruta independiente. También aprueba **Club** como disclosure de
Quiénes somos, Contacto, Federarse y Documentos, con CMS como fuente editorial
única y sin landing `/club`.

ADR-033 registra navegación, rutas, CMS, footer y compatibilidad, sustituyendo
parcialmente la topología plana de ADR-028. No cierra la política de identidad
pública ni aporta contenido, legal, datos School o implementación. Esos gates,
las plantillas y el plan 7C–7G se definen en
`15-mvp-editorial-and-navigation-contract.md`. La Fase 7 permanece abierta; el
anterior candidato técnico no equivale a despliegue ni a MVP público completo.

7C.1 implementa la preparación privada de Club: audita assets y CMS, añade el
backend de contacto desactivado y su cliente React aislado. ADR-034 sustituye el
punto de ADR-033 que descartaba el formulario. 7C.2 incorpora las cuatro rutas
exactas y diferidas, valida el slug CMS esperado, reutiliza el renderer y monta
el formulario sólo con configuración pública positiva, sin convertir código en
fuente editorial. Privacidad, activación productiva, publicación por entorno,
Navbar, aliases y redirects siguen pendientes.

---

# 12. Gestión de dependencias

Las dependencias se auditan distinguiendo el árbol de producción del tooling de desarrollo. `npm audit --omit=dev` delimita la exposición del bundle frontend, mientras que la auditoría completa cubre también Vite, ESLint, Vitest y Playwright. Composer se ejecuta siempre dentro del contenedor oficial y su resultado se clasifica igualmente según el paquete pertenezca a `require` o `require-dev`.

Las correcciones se realizan mediante actualizaciones dirigidas de los paquetes afectados y sus dependencias compatibles. No se regeneran locks manualmente, no se aplican actualizaciones globales ni se fuerzan saltos de versión principal. Cada cambio debe conservar Node 22, React 19, Vite 8, PHP 8.2, Laravel 12 y la infraestructura Docker/MariaDB, salvo que un bloque futuro apruebe expresamente una migración principal.

Después de modificar un lock son obligatorias una nueva auditoría, la validación del árbol instalado y la regresión completa de la capa afectada. Cuando cambian dependencias de producción de ambas aplicaciones, se ejecutan además la suite Laravel sobre MariaDB aislada y el smoke Playwright del sistema completo.

---

# 13. Principios arquitectónicos

- Backend como fuente de verdad.
- React sin lógica deportiva.
- Un contexto funcional ⇒ un Resource.
- Blade y React son interfaces oficiales.
- Services para la lógica de dominio.
- MariaDB como único motor soportado.
- Evolución incremental sin reescrituras masivas.

---

# Relación con el resto de la documentación

- `00-glossary.md`
- `01-domain.md`
- `03-api-contract.md`
- `04-admin-panel.md`
- `08-resources.md`
- `09-public-navigation.md`
- `10-content-governance.md`
- `14-mvp-parity-audit.md`
- `15-mvp-editorial-and-navigation-contract.md`
- `17-club-technical-preparation-and-contact.md`

## Mantenimiento

Cuando cambie la arquitectura del proyecto o se adopte un nuevo patrón estructural, este documento deberá actualizarse.
