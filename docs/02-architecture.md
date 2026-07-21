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

## Arquitectura híbrida de contenidos

La arquitectura pública aprobada conecta tres canales diferentes:

1. **Dominio funcional:** `Laravel → API → React` para competición, inscripciones, calendarios, resultados y rankings.
2. **Contenido administrable:** `Panel Blade → base de datos → API pública → React` para contenido institucional, noticias, actividades, convocatorias y demás información editable sin despliegue.
3. **Conocimiento canónico:** `knowledge/ → compilador validado → datos generados → React` para Manual, Reglamento, Conceptos y contenido pedagógico estable.

Los tres canales disponen ya de una base comprobable. En el tercero, Fase 5A implementa el contrato editorial, el validador, el compilador determinista y un artefacto JSON canónico versionado. Fase 5A.1 aprueba editorialmente el Reglamento inicial, normaliza los 40 documentos a un único H1 y deja todo el corpus en estado `Vigente`. Fase 5B añade una proyección pública independiente, un repositorio frontend, un renderer seguro y las rutas iniciales de Aprende a jugar y el Manual. Fase 5C completa esa experiencia con contexto local, índices derivados de headings compilados, navegación canónica dentro de cada colección, fragmentos estables y carga diferida de toda la rama. La primera versión no utiliza MDX, HTML ejecutable, base de datos, API Laravel ni CRUD Blade.

Una misma pieza no debe mantenerse de forma editable en más de un canal. Los criterios de elección y la matriz de fuentes se definen en `10-content-governance.md`.

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
- **React**: consumo de la API pública desde `/contenidos` y `/contenidos/:slug`, con renderizado de bloques estructurados, sin HTML libre.
- **Navegación pública**: los enlaces institucionales del navbar apuntan a slugs CMS bajo `/contenidos/{slug}`.

La subida de documentos o imágenes, las noticias como entidad propia y los formularios públicos quedan fuera de esta base inicial.

`/contenidos` representa la estructura pública actual del CMS, pero se considera legada respecto a la arquitectura de información aprobada. Su implementación, API y contenido permanecen sin cambios hasta que se complete el inventario y la migración por áreas.

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

`frontend/src/navigation/publicNavigation.js` es la fuente única del menú editorial. En 3B contiene exclusivamente Inicio y Competición; Torneos y Rankings siguen disponibles como destinos secundarios y las rutas CMS e institucionales conservan acceso directo sin ocupar el primer nivel. El matcher compartido activa Inicio sólo en `/` y Competición en su landing, campeonatos, categorías, standings, schedule, partidos y rankings. La ruta exacta utiliza `aria-current="page"` y las ubicaciones secundarias `aria-current="location"`.

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

La adopción inicial se limita a `/competicion`. Desde Fase 4A, la página conserva el propósito, la estructura común y los enlaces a `/torneos` y `/rankings`, y añade la jerarquía real de temporadas y campeonatos obtenida mediante una única petición pública a `GET /api/v1/seasons`. Fase 4B añade una carga independiente de `GET /api/v1/rankings/all-time` y presenta como máximo las primeras cinco filas del orden canónico recibido. La 404 conserva su presentación propia y sólo reutiliza acciones y metadatos. Home no se ha refactorizado.

`championshipsService.getSeasons` mantiene la comunicación HTTP y extrae `data` del envelope; `useCompetitionOverview` coordina carga, éxito, error, vacío, reintento y descarte de respuestas posteriores al desmontaje; los componentes específicos `CompetitionOverview`, `CompetitionSeason` y `CompetitionChampionshipCard` se limitan a presentar el contrato deportivo, y `CompetitionPage` compone esos estados dentro de `PublicLanding`. No se consulta `/championships` ni detalles para construir el resumen, porque `/seasons` ya entrega los campeonatos públicos y su recuento de categorías en una sola respuesta.

`championshipsService.getAllTimeRanking` sigue siendo el único consumidor HTTP del ranking histórico tanto para `/rankings` como para la landing. `useAllTimeRanking` aporta a 4B un ciclo remoto propio con reintento y descarte de respuestas obsoletas, mientras `CompetitionRankingPreview` limita visualmente la colección con `slice(0, 5)`. No ordena, puntúa, posiciona ni completa filas: muestra el nombre público, `position` sólo cuando existe, `weighted_points` sólo cuando es numérico y `categories_played_list` sólo cuando contiene contexto real; `player_id` se usa únicamente como clave técnica. `/rankings` conserva su tabla completa y su paginación visual previa.

Las cargas de temporadas y ranking son independientes: el error, retry, loading o vacío de una no bloquea ni borra la otra. Fase 4C aplica el mismo principio por recurso en Torneos, campeonato, clasificación, calendario y Rankings, sin crear un estado remoto global para contratos distintos. React conserva el orden de la API, no interpreta ni vuelve a filtrar `is_public` y no deriva posiciones, oficialidad o reglas; Laravel continúa siendo la fuente de verdad. Los datos nulos se omiten cuando no aportan información, los desconocidos usan fallbacks neutrales y cada bloque conserva un reintento acotado cuando es recuperable.

El cierre 4C ordena `/competicion` como propósito, acceso principal a Torneos, temporadas y campeonatos, y ranking histórico. Elimina el acceso duplicado a Rankings porque el propio bloque histórico mantiene su enlace completo. `/torneos` distingue error de vacío y cada tarjeta ofrece una sola acción al detalle. El campeonato mantiene su información si falla el ranking; el detalle de categoría queda como resumen de la entidad y deja standings y schedule en sus URLs compartibles. Partido regresa al calendario real de su categoría. No se añaden endpoints, rutas, filtros, cálculos, resultados destacados ni bloques nuevos en la landing.

## Arquitectura pública objetivo

El contrato de primer nivel fija estas cinco rutas canónicas:

- **Inicio** (`/`) será una landing híbrida y conserva su función actual.
- **Competición** (`/competicion`) agrupará Torneos, Rankings, Calendarios, Clasificaciones y Resultados sobre el dominio Laravel.
- **Aprende a jugar** (`/aprende-a-jugar`) será la entrada al contenido divulgativo, Manual, Reglamento, Conceptos e Historia cuando exista.
- **Escuela de Galotxas** (`/escuela`) combinará conocimiento pedagógico estable con actividad operativa administrable; no será una subsección del Manual.
- **Club** (`/club`) agrupará contenido institucional administrable.

La zona de autenticación conservará identidad, acceso, Mi Panel y cierre de sesión como bloque separado del menú editorial. Las rutas actuales de Torneos, Rankings y detalles deportivos permanecen como destinos funcionales secundarios; no se trasladarán bajo `/competicion` sin una necesidad demostrable. `/contenidos` y `/contenidos/:slug` permanecen como compatibilidad técnica durante una migración incremental, pero no formarán parte del primer nivel final.

En el estado actual están registradas `/`, `/competicion` y `/aprende-a-jugar`. Competición aplica la estructura común de Fase 3C, presenta datos deportivos públicos de `GET /api/v1/seasons` y `GET /api/v1/rankings/all-time` sin entidades simuladas y mantiene los destinos funcionales `/torneos` y `/rankings`. Aprende a jugar resume de forma derivada sus 40 documentos y cuatro colecciones; el Manual añade accesos por colección, y cada detalle dispone de contexto local, tabla de contenidos H2–H6, fragmentos compartibles y anterior/siguiente sin cruzar colecciones. Escuela y Club conservan dependencias editoriales explícitas, no aparecen como enlaces deshabilitados y no tienen rutas placeholder. El contrato detallado, los mínimos de contenido, la compatibilidad y los gates se definen en `09-public-navigation.md`.

La secuencia aprobada separa responsabilidades: 3B implementó la navegación progresiva, el fallback 404 React y la landing mínima de `/competicion`; 3C aportó su estructura visual y técnica reutilizable, headings y metadatos básicos; 4A–4C completaron el recorrido deportivo; 5A/5A.1 consolidaron el corpus, 5B publicó su primer consumo seguro y 5C cerró la experiencia, navegación documental y carga diferida. La Fase 5 queda completa. Consolidación institucional, migraciones, aliases, redirects, canonical, indexación de `/contenidos` y SEO completo quedan en bloques independientes posteriores.

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

El smoke E2E implementado utiliza Playwright con Chromium y un stack Compose independiente. Levanta Laravel, Nginx y una MariaDB `galotxas_e2e` temporal, ejecuta `E2ESmokeSeeder`, inicia Vite desde el runner oficial y recorre frontend React y panel Blade con servicios reales. La suite es serial y el script elimina contenedores, red y volúmenes al finalizar.

El E2E cubre el recorrido crítico del MVP; no constituye una matriz multibrowser ni sustituye QA visual/manual.

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

## Mantenimiento

Cuando cambie la arquitectura del proyecto o se adopte un nuevo patrón estructural, este documento deberá actualizarse.
