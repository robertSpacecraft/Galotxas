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

El backend constituye la fuente de verdad del sistema.

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

## Arquitectura CMS pública

La primera base backend del CMS público sigue el mismo patrón general del proyecto:

- **Backend Laravel**: modelos `CmsPage` y `CmsBlock`, migraciones MariaDB y enums de estado/tipo.
- **Panel Blade**: gestión administrativa básica de páginas CMS, estado de publicación, metadatos SEO y bloques estructurados.
- **API pública**: endpoints de solo lectura para listar páginas publicadas y entregar una página publicada por `slug`.
- **Resources públicos**: `PublicCmsPageSummaryResource`, `PublicCmsPageResource` y `PublicCmsBlockResource` controlan el contrato serializado.
- **React**: consumo de la API pública desde `/contenidos` y `/contenidos/:slug`, con renderizado de bloques estructurados, sin HTML libre.
- **Navegación pública**: los enlaces institucionales del navbar apuntan a slugs CMS bajo `/contenidos/{slug}`.

La subida de documentos o imágenes y los formularios públicos quedan fuera de esta base inicial y se abordarán en bloques posteriores.

## Gestión de pistas y generación de calendarios

La configuración de pistas se mantiene en el backend mediante el modelo `Venue` y un CRUD web protegido por el middleware administrativo. Los Form Requests validan los tres campos persistidos actualmente (`name`, `location` y `description`). No existe todavía un campo `active` en el esquema.

`Venue` expone relaciones explícitas con `GameMatch` y `MatchRescheduleRequest`. El panel solo permite borrar una pista cuando ninguna de esas relaciones existe, aunque la clave foránea histórica de `game_matches` admita `nullOnDelete`; esta defensa de aplicación evita perder la pista asignada a un partido.

`DefaultVenueSeeder` es un seeder de ejecución explícita, no registrado en `DatabaseSeeder`. Crea por nombre un conjunto mínimo estable y usa `firstOrCreate`, por lo que no necesita ni fuerza IDs concretos y no modifica pistas preexistentes.

`GenerateLeagueScheduleService` obtiene una sola vez todas las pistas mediante una consulta ordenada por `id`. La selección no depende de IDs consecutivos, nombres, modalidad, nivel de categoría ni de `DefaultVenueSeeder`. El orden estable se reutiliza al construir los huecos de cada jornada, por lo que una misma base de datos produce el mismo reparto.

Cada pista aporta los siete huecos temporales heredados por jornada. Dentro de la categoría procesada, el servicio nunca genera dos partidos con la misma combinación de pista y fecha/hora; si los cruces exceden la capacidad disponible, lanza un error dentro de la transacción. Todas las rondas y partidos de la liga se crean en una única transacción, de modo que una insuficiencia detectada tras crear una ronda o cualquier otro fallo de persistencia revierte la operación completa.

Si no existe ninguna pista, el servicio falla antes de abrir la transacción y antes de crear datos. No se ha añadido un scope `active()` porque el modelo no soporta ese estado.

La ocupación se calcula únicamente para la categoría que se está generando. Evitar solapamientos con calendarios ya generados de otras categorías exigiría coordinación de disponibilidad compartida y bloqueo concurrente; esa capacidad no se incorpora en SCHEDULE-1.

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

Una coincidencia valida ambos reportes y publica el resultado oficial. Una discrepancia conserva ambos como `conflict`, limpia cualquier tanteo oficial y mueve el partido a `under_review`. La resolución administrativa fija el resultado oficial sin reescribir los reportes originales, que permanecen como trazabilidad.

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

## Configuración del cliente API

El frontend centraliza todas las peticiones HTTP en la instancia Axios de `frontend/src/api/client.js`.

La URL base se resuelve por entorno:

- `VITE_API_BASE_URL`, cuando se configura, tiene prioridad y se normaliza eliminando espacios exteriores;
- durante desarrollo, si no existe variable, se utiliza `http://localhost:8080/api/v1`;
- en builds de producción sin variable se utiliza `/api/v1`, asumiendo un proxy inverso bajo el mismo dominio.

Los servicios funcionales no deben duplicar esta resolución ni definir URLs base propias. Los despliegues con frontend y API en dominios distintos deben configurar `VITE_API_BASE_URL` durante el build.

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

---

# 12. Principios arquitectónicos

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
