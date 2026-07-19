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

Los dos primeros canales disponen actualmente de infraestructura en distintas áreas del proyecto. El tercero es arquitectura objetivo: el compilador, el contrato editorial normalizado y los artefactos generados todavía no están implementados. La primera versión del Manual no utilizará MDX, HTML ejecutable, base de datos, API Laravel ni CRUD Blade.

Una misma pieza no debe mantenerse de forma editable en más de un canal. Los criterios de elección y la matriz de fuentes se definen en `10-content-governance.md`.

## Base de visibilidad de la competición

La competición funcional separa dos dimensiones persistidas:

- **estado operativo**, expresado por los estados propios de temporada, campeonato o categoría;
- **visibilidad declarada**, expresada por el booleano `is_public` y gestionada desde Blade.

`is_public` no se deriva de estados, fechas, inscripciones, calendarios o resultados. Los modelos `Season`, `Championship` y `Category` lo castean a booleano y los nuevos registros son privados por defecto. La migración de incorporación marca como públicos los registros preexistentes para preservar su accesibilidad anterior.

La administración valida la jerarquía Temporada → Campeonato → Categoría al activar la visibilidad. Desactivar un padre no propaga escrituras a sus hijos: la visibilidad declarada de cada descendiente se conserva. La visibilidad efectiva será la conjunción de los flags de la rama cuando 2B.4B la aplique en las consultas públicas.

Durante 2B.4A los controladores, rutas y Resources públicos permanecen intactos, `is_public` no forma parte del contrato serializado y las consultas aún no filtran registros privados. Los modelos ocultan además el flag de su serialización Eloquent y no lo admiten mediante asignación masiva, de modo que el CRUD API administrativo heredado no lo lee ni lo modifica accidentalmente; Blade lo asigna de forma explícita. Esta separación deliberada permite validar primero persistencia y administración antes de cambiar el comportamiento de lectura.

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
- `/nosotros`: página estática heredada;
- `/torneos` y `/torneos/:championshipId`: listado y detalle de campeonatos;
- `/categories/:categoryId`, `/categories/:categoryId/standings` y `/categories/:categoryId/schedule`: detalle, clasificación y calendario de categoría;
- `/matches/:matchId`: detalle público o workflow de resultado según la sesión;
- `/rankings`: rankings públicos;
- `/contenidos` y `/contenidos/:slug`: índice y páginas CMS;
- `/login`, `/register`, `/forgot-password` y `/reset-password`: autenticación;
- `/player`: Mi Panel protegido por sesión React.

No existe un panel administrativo React. Tampoco existen todavía rutas React para reprogramación ni edición completa del perfil.

El calendario independiente de categoría obtiene su contexto mediante `GET /categories/{id}` y, en paralelo, consume `GET /categories/{id}/schedule` como la colección de jornadas definida por el contrato. Ambas llamadas pasan por `championshipsService`: React no reconstruye un objeto contenedor inexistente ni calcula reglas deportivas. Un fallo del contexto conserva las jornadas disponibles con fallbacks explícitos; un fallo de la colección produce un estado de error controlado.

La navegación pública conserva todos sus enlaces en escritorio. En móvil y tablet, el mismo árbol de enlaces se expone mediante estado React y un botón con `aria-expanded` y `aria-controls`; el menú se cierra al seleccionar una ruta, al cambiar la ubicación, mediante el propio botón o con Escape. El acceso anónimo al área de jugadores y el acceso autenticado a Mi Panel permanecen independientes del estado del menú.

## Arquitectura pública objetivo

La navegación pública futura se organizará conceptualmente en Inicio, Competición, Aprende a jugar, Escuela de Galotxas y Club. La zona autenticada conservará identidad, Mi Panel y cierre de sesión como bloque separado.

- **Inicio** será una landing híbrida.
- **Competición** agrupará Torneos, Rankings, Calendarios, Clasificaciones y Resultados sobre el dominio Laravel.
- **Aprende a jugar** será la entrada al contenido divulgativo, Manual, Reglamento y Conceptos.
- **Escuela de Galotxas** combinará conocimiento pedagógico estable con actividad operativa administrable; no será una subsección del Manual.
- **Club** agrupará contenido institucional administrable.

Las rutas conceptuales `/aprende`, `/manual` y sus subrutas, y `/escuela` son futuras. No están registradas actualmente y esta decisión no autoriza asumir endpoints, componentes o datos que todavía no existan. Las rutas actuales de Torneos, Rankings y Contenidos podrán mantenerse durante una migración incremental.

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

## Mantenimiento

Cuando cambie la arquitectura del proyecto o se adopte un nuevo patrón estructural, este documento deberá actualizarse.
