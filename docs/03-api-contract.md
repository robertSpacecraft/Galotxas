# Contrato API — Galotxas

## Propósito

Este documento describe el contrato actual de la API REST de Galotxas y las decisiones que afectan a su evolución.

No pretende sustituir una especificación OpenAPI. Su objetivo es documentar los principios del contrato utilizados por el proyecto y el estado real de la API.

---

# 1. Principios

- La API es el contrato entre backend y consumidores.
- El backend controla el dominio.
- Los consumidores no deben depender de modelos Eloquent.
- Los cambios incompatibles deben realizarse de forma planificada.
- La estabilidad del contrato tiene prioridad sobre cambios cosméticos.

---

# 2. Consumidores actuales

Actualmente la API es utilizada por:

- Frontend React.
- Herramientas de pruebas.
- Clientes HTTP de desarrollo.

En el futuro podrán añadirse aplicaciones móviles u otras integraciones.

---

# 3. Versionado

La API se expone actualmente bajo:

`/api/v1`

Los cambios incompatibles deberán introducirse mediante una nueva versión cuando resulte necesario.

---

# 4. Estado actual del contrato

Actualmente la API refleja una fase de consolidación.

El proyecto mantiene compatibilidad con los consumidores existentes mientras evoluciona progresivamente hacia un contrato más homogéneo.

Situación actual:

- conviven endpoints completamente serializados mediante Resources con otros heredados;
- existen respuestas que todavía no siguen un formato completamente uniforme;
- el proyecto utiliza actualmente nombres de campos en `snake_case`;
- no existe todavía un envelope completamente homogéneo para todas las respuestas;
- la prioridad es mantener la compatibilidad con el frontend antes que realizar cambios puramente estéticos.

La normalización completa del contrato constituye una fase específica del roadmap.

---

## Inventario de rutas verificado

El inventario siguiente corresponde a `backend/routes/api.php` y a la salida de `php artisan route:list` revisada en DOC-1. Todas las rutas se sirven bajo `/api/v1`.

### Rutas públicas y autenticación

| Método | Ruta | Salida principal |
|---|---|---|
| `POST` | `/auth/register` | payload controlado con token Bearer, usuario y perfil `null` |
| `POST` | `/auth/login` | payload controlado con token Bearer, usuario y `PlayerProfileResource` opcional |
| `POST` | `/auth/forgot-password` | mensaje genérico sin enumerar emails |
| `POST` | `/auth/reset-password` | mensaje controlado o error `422` |
| `GET` | `/seasons` | colección `SeasonResource` |
| `GET` | `/championships` | colección `ChampionshipPublicResource` |
| `GET` | `/championships/{championship}` | `ChampionshipPublicResource` |
| `GET` | `/championships/{championship}/ranking` | colección `ChampionshipRankingResource` |
| `GET` | `/categories/{category}` | `CategoryPublicResource` |
| `GET` | `/categories/{category}/standings` | colección `CategoryRankingResource` |
| `GET` | `/categories/{category}/schedule` | colección `CategoryScheduleRoundResource` |
| `GET` | `/matches/{gameMatch}` | `PublicMatchResource` |
| `GET` | `/cms/pages` | colección `PublicCmsPageSummaryResource` |
| `GET` | `/cms/pages/{slug}` | `PublicCmsPageResource` o `404` |
| `GET` | `/seasons/{season}/ranking` | colección `ChampionshipRankingResource` |
| `GET` | `/rankings/all-time` | colección `AllTimeRankingResource` |

Los cuatro endpoints de autenticación públicos están limitados a cinco intentos por minuto según email/IP o IP, conforme al limiter concreto. Las rutas públicas de lectura no usan ese limiter sensible.

Las lecturas deportivas públicas aplican visibilidad efectiva antes de filtros y serialización:

- `/seasons` exige `season.is_public` y filtra campeonatos y categorías anidados por toda su jerarquía;
- los listados, detalle y ranking de campeonato exigen campeonato y temporada públicos;
- el ranking de temporada exige temporada pública y omite partidos de campeonatos o categorías privados;
- detalle, standings y schedule de categoría exigen categoría, campeonato y temporada públicos;
- el detalle de partido exige una categoría y toda su rama pública;
- el ranking histórico sólo agrega partidos de ramas efectivamente públicas.

Los listados conservan orden, filtros, campos y envelopes. Un acceso directo que no cumpla la jerarquía responde `404`; `is_public` no se serializa en ningún Resource público. El estado operativo no participa en esta decisión, por lo que los estados públicos admitidos anteriormente continúan admitidos.

### Rutas autenticadas

`POST /auth/logout` exige `auth:sanctum`, pero deliberadamente queda fuera de `EnsureUserIsActive` para que un usuario desactivado pueda revocar su token actual.

Las rutas restantes de esta tabla exigen conjuntamente token Sanctum y usuario activo:

| Método | Ruta | Salida principal |
|---|---|---|
| `GET` | `/me` | `MeResource` |
| `GET` | `/me/player-profile` | `PlayerProfileResource` |
| `POST` | `/me/player-profile` | `PlayerProfileResource` |
| `PATCH` | `/me/player-profile` | `PlayerProfileResource`; solo apodo, mano dominante y notas |
| `GET` | `/me/championship-registrations` | colección `ChampionshipRegistrationRequestResource` |
| `GET` | `/me/matches` | colección del `MatchResource` privado heredado |
| `GET` | `/me/matches/pending-actions` | colección `PendingMatchActionResource` |
| `GET` | `/me/calendar` | colección `CalendarDayResource`, con partidos en `MatchResource` |
| `GET` | `/me/rankings` | colección `MyRankingResource` |
| `GET` | `/matches/{gameMatch}/workflow` | `PublicMatchResource` o Resources mínimos de participante según contexto |
| `POST` | `/matches/{gameMatch}/submit-result` | `ParticipantMatchResource` y `ParticipantMatchResultReportResource` |
| `POST` | `/matches/{gameMatch}/confirm-result` | `ParticipantMatchResource` y `ParticipantMatchResultReportResource` |
| `GET` | `/matches/{gameMatch}/reschedule-workflow` | `MatchResource` y `MatchRescheduleRequestResource` |
| `POST` | `/matches/{gameMatch}/request-reschedule` | `MatchRescheduleRequestResource` |
| `POST` | `/matches/{gameMatch}/confirm-reschedule` | `MatchResource` y `MatchRescheduleRequestResource` |
| `GET` | `/championships/{championship}/registration` | payload controlado y `ChampionshipRegistrationRequestResource` opcional |
| `POST` | `/championships/{championship}/register` | `ChampionshipRegistrationRequestResource` |

Los dos endpoints de escritura de resultados comparten un límite de diez intentos por minuto por usuario/IP. Las escrituras de reprogramación no tienen todavía un limiter específico.

Los endpoints de registro en campeonato forman parte del inicio de una operación desde la experiencia pública aunque exijan autenticación: sólo admiten campeonatos efectivamente públicos y responden `404` para una rama privada. La categoría sugerida también debe pertenecer al campeonato y ser efectivamente pública. En cambio, `/me/championship-registrations` conserva las solicitudes propias ya existentes aunque después se oculte la competición.

Las rutas `/me/*` y los workflows en los que el usuario participa son contextos personales: no aplican el scope público a sus partidos, calendario, rankings o solicitudes. El fallback de lectura de `/matches/{gameMatch}/workflow` para un usuario no participante sí exige que el partido sea público. Las rutas administrativas mantienen acceso a entidades privadas y no incorporan `is_public` al CRUD heredado.

### API administrativa

Todas las rutas `/api/v1/admin/*` exigen Sanctum, usuario activo y `IsAdmin`.

| Métodos | Ruta | Estado del contrato |
|---|---|---|
| `GET`, `POST`, `PUT/PATCH`, `DELETE` | `/admin/seasons` y `/admin/seasons/{season}` | controladores heredados; modelos Eloquent directos y `204` al borrar |
| `GET`, `POST`, `PUT/PATCH`, `DELETE` | `/admin/championships` y `/admin/championships/{championship}` | controladores heredados; modelos Eloquent directos y `204` al borrar |
| `GET`, `POST`, `PUT/PATCH`, `DELETE` | `/admin/categories` y `/admin/categories/{category}` | controladores heredados; modelos Eloquent directos y `204` al borrar |
| `POST` | `/admin/categories/{category}/entries` | `CategoryEntry` directo heredado |
| `GET` | `/admin/matches/under-review` | colección `MatchResource` |
| `GET` | `/admin/matches/{gameMatch}/conflict` | `MatchResource` y colección `MatchResultReportResource` |
| `POST` | `/admin/matches/{gameMatch}/resolve-conflict` | `MatchResource` y colección `MatchResultReportResource` |
| `POST` | `/admin/matches/{gameMatch}/validate-result` | `MatchResource` |
| `GET` | `/admin/championships/{championship}/registration-requests` | colección `ChampionshipRegistrationRequestResource` |
| `PATCH` | `/admin/championships/{championship}/registration-requests/{registrationRequest}/status` | `ChampionshipRegistrationRequestResource` |

Los CRUD administrativos sin Resource y sus validaciones masivas con `$request->all()` son estado heredado real, no el patrón recomendado para endpoints nuevos. Su normalización requiere un bloque específico y no forma parte de DOC-1.

### Separación respecto al panel Blade

Las rutas `/admin/*` definidas en `routes/web.php` son páginas y formularios web con sesión, CSRF, middleware `auth` e `IsAdmin`. No pertenecen a la API REST, no usan el prefijo `/api/v1` y se documentan en `04-admin-panel.md`.

---

# 5. Resources

Siempre que un endpoint exponga información estructurada deberá utilizar Resources cuando resulte razonable.

Los Resources forman parte del contrato público.

Un mismo modelo puede disponer de distintos Resources según el contexto:

- público;
- participante autenticado;
- administrador.

---

# 6. Seguridad

La API distingue claramente entre:

- endpoints públicos;
- endpoints autenticados;
- endpoints administrativos.

Los datos sensibles nunca deben exponerse mediante endpoints públicos.

## Autenticación desde React

El frontend React consume la API autenticada mediante tokens Bearer emitidos por Laravel Sanctum en los endpoints de autenticación.

Estrategia actual:

- el token se conserva en `localStorage` bajo la clave `token`;
- los datos mínimos del usuario autenticado se conservan en `localStorage` bajo la clave `user`;
- el cliente Axios obtiene su URL base de `VITE_API_BASE_URL`; sin variable usa el backend local durante desarrollo y `/api/v1` en producción;
- el cliente Axios añade `Authorization: Bearer <token>` cuando existe token local;
- `GET /api/v1/me` se utiliza para refrescar los datos de la cuenta y su perfil de jugador;
- `POST /api/v1/auth/logout` revoca el token actual en backend;
- ante respuestas `401` o `403` en peticiones autenticadas, React elimina `token` y `user` para evitar sesiones locales desincronizadas.

La estrategia forma parte del estado MVP actual. Una futura migración a cookies `HttpOnly`/`SameSite` con protección CSRF requerirá un bloque específico porque modifica el modelo de consumo del frontend y no debe mezclarse con cambios funcionales menores.

---

# 7. Formato de las respuestas

Actualmente la mayoría de los endpoints propios del proyecto utilizan un formato similar al siguiente:

```json
{
    "message": null,
    "data": { ... }
}
```

No obstante, todavía existen respuestas que utilizan directamente el formato estándar de Laravel, especialmente en:

- errores de validación;
- autenticación;
- rate limiting;
- determinadas excepciones del framework.

Esta heterogeneidad es conocida y forma parte del estado actual del proyecto.

La normalización completa del contrato API constituye una fase específica del roadmap.

## Usuario autenticado

`GET /api/v1/me` mantiene el envelope habitual y devuelve la cuenta autenticada junto con su perfil de jugador, si existe:

```json
{
    "message": null,
    "data": {
        "user": {
            "id": 1,
            "name": "Nom",
            "lastname": "Cognoms",
            "email": "jugador@example.com",
            "role": "user",
            "active": true,
            "has_player": true
        },
        "player": {}
    }
}
```

`user` se serializa mediante `UserResource` y solo expone los campos explícitos del contrato. No incluye credenciales, token de sesión, estado de verificación de email, timestamps ni otros atributos internos del modelo.

La respuesta completa se compone mediante `MeResource`, que delega el perfil deportivo en `PlayerProfileResource`. Cuando el usuario no tiene perfil de jugador, `has_player` es `false` y `player` es `null`.

Los nombres de campos y la estructura consumida por React se mantienen sin cambios.

---

## Calendario privado

`GET /api/v1/me/calendar` mantiene el envelope habitual y agrupa los partidos por día:

```json
{
    "message": null,
    "data": [
        {
            "date": "2026-07-15",
            "matches": []
        }
    ]
}
```

Cada elemento de `matches` se serializa mediante `MatchResource`.

El endpoint no devuelve modelos Eloquent directamente ni expone relaciones completas de forma implícita.

Los nombres de los campos se mantienen en `snake_case`.

---

## Rankings privados del participante

`GET /api/v1/me/rankings` mantiene el envelope habitual y devuelve una fila por cada categoría en la que se localiza al jugador autenticado:

```json
{
    "message": null,
    "data": [
        {
            "championship": { "id": 1, "name": "Mà a mà" },
            "category": { "id": 4, "name": "Primera" },
            "entry_type": "player",
            "entry_name": "Jugador",
            "position": 1,
            "played": 3,
            "wins": 2,
            "losses": 1,
            "points": 6,
            "games_for": 28,
            "games_against": 19,
            "games_diff": 9
        }
    ]
}
```

La respuesta se serializa mediante `MyRankingResource`. El cálculo y la localización de la fila del jugador se realizan en `BuildMyRankingsService`, que reutiliza el ranking de categoría existente.

El endpoint no devuelve `CategoryEntry` ni otros modelos Eloquent. Los nombres de los campos y el comportamiento para usuarios sin perfil de jugador (`data: []`) se mantienen sin cambios.

---

## Ranking histórico

`GET /api/v1/rankings/all-time` devuelve el ranking histórico serializado mediante `AllTimeRankingResource`.

El campo `win_rate` utiliza escala `0–100`, no una fracción `0–1`:

```json
{
    "played": 2,
    "wins": 1,
    "win_rate": 50.0
}
```

Los consumidores deben añadir únicamente el formato visual del porcentaje. No deben multiplicar el valor por `100`. El Resource redondea `win_rate` a dos decimales y mantiene valores numéricos válidos.

---

## Resultados de partidos para participantes

### Acciones pendientes de Mi Panel

`GET /api/v1/me/matches/pending-actions` requiere Sanctum y usuario activo. Devuelve una colección plana con una entrada por partido relevante para el jugador autenticado:

```json
{
    "message": null,
    "data": [
        {
            "type": "confirm_result",
            "match": {
                "id": 42,
                "scheduled_date": "2026-07-15T18:30:00.000000Z",
                "status": "submitted",
                "home_score": null,
                "away_score": null,
                "home_entry": {},
                "away_entry": {},
                "winner_entry": null,
                "venue": {},
                "round": {}
            }
        }
    ]
}
```

Tipos estables:

- `submit_result`: el lado del jugador todavía no ha reportado y no existe reporte rival;
- `confirm_result`: existe un reporte del lado rival y el lado del jugador todavía puede actuar;
- `under_review`: aviso informativo de discrepancia; no habilita escritura.

Los partidos `validated`, `cancelled` y `postponed` no aparecen. Un lado que ya ha reportado tampoco recibe otra acción. En dobles, los dos integrantes representan al mismo lado y obtienen la misma única acción por partido, nunca una acción duplicada por integrante.

El partido se serializa en este contexto mediante `ParticipantMatchResource`, envuelto por `PendingMatchActionResource`. No se incluyen reportes, comentarios, emails, usuarios, responsables, claves de trazabilidad ni timestamps internos. Un usuario autenticado sin perfil de jugador o un jugador sin acciones recibe `data: []`; un jugador ajeno nunca recibe el partido.

React construye el enlace `/matches/{id}` a partir del identificador y resuelve allí cualquier envío, confirmación o discrepancia.

---

El detalle público `GET /api/v1/matches/{gameMatch}` utiliza `PublicMatchResource`. Es accesible sin autenticación y oculta tanteos, ganador y trazabilidad interna mientras el partido no está validado.

La gestión privada del resultado se realiza con endpoints autenticados bajo Sanctum y usuario activo:

- `GET /api/v1/matches/{gameMatch}/workflow`;
- `POST /api/v1/matches/{gameMatch}/submit-result`;
- `POST /api/v1/matches/{gameMatch}/confirm-result`.

`POST /submit-result` acepta:

```json
{
    "home_score": 10,
    "away_score": 7,
    "comment": "Partido finalizado"
}
```

`home_score` y `away_score` son enteros no negativos obligatorios. `comment` es opcional, puede ser `null` y admite hasta 2.000 caracteres. El backend rechaza empates y marcadores que no alcancen exactamente el objetivo de la modalidad: 10 en individuales y 12 en dobles.

`POST /confirm-result` no permite cambiar el tanteo rival y acepta únicamente el comentario opcional con el mismo límite:

```json
{
    "comment": "Confirmado"
}
```

`GET /workflow` mantiene respuesta `200` para usuarios autenticados con sesión válida, pero adapta el contrato al contexto:

- si el usuario no tiene perfil de jugador o no participa en el partido, `match` se serializa mediante `PublicMatchResource` y el bloque `workflow` devuelve `participates: false`, `can_report: false` y todos los reportes a `null`;
- si el jugador participa, `match` se serializa mediante `ParticipantMatchResource` y los reportes visibles se serializan mediante `ParticipantMatchResultReportResource`;
- las respuestas de `submit-result` y `confirm-result` utilizan los mismos Resources seguros del participante.

`ParticipantMatchResource` expone únicamente el partido, participantes visibles, fecha, estado, pista y jerarquía competitiva básica que necesita React. No incluye reportes, emails, responsables internos ni timestamps de trazabilidad. Los tanteos y ganador oficiales solo se incluyen cuando el partido está validado.

`ParticipantMatchResultReportResource` expone solo lado, tanteos, estado y comentario. No incluye `user_id`, `player_id`, email ni objetos de usuario.

El bloque `workflow` conserva:

- `participates`;
- `user_side`;
- `can_report`;
- `blocked_reason`;
- `my_report`;
- `same_side_report_by_teammate`;
- `opposite_report`;
- `match_status`.

React consume este contrato desde la pantalla `/matches/{id}`. El frontend solo representa estados y acciones disponibles; la validación deportiva del tanteo, la confirmación automática y la detección de conflicto pertenecen al backend.

La respuesta limitada para un usuario autenticado ajeno permite mantener el detalle público y mostrar el mensaje de que solo los participantes pueden gestionar el resultado, sin convertir una consulta pública válida en un cierre de sesión frontend.

Cuando el primer participante envía un resultado, se crea un reporte `submitted`, el partido pasa a `submitted` y todavía no expone tanteo ni ganador oficiales. Cada lado solo puede crear un reporte y no puede sobrescribirlo; en dobles, el reporte de un miembro bloquea también a su compañero.

El lado rival puede completar el flujo de dos formas:

- `confirm-result` copia el tanteo del reporte contrario y, al coincidir, ambos reportes pasan a `validated`; el partido queda `validated` con tanteo y ganador oficiales;
- `submit-result` permite declarar su propio tanteo; si difiere, ambos reportes pasan a `conflict`, el partido queda `under_review` y sus campos oficiales permanecen vacíos hasta la resolución administrativa.

Solo puede actuar un participante del lado correspondiente. Un usuario sin perfil o ajeno no obtiene datos privados y no puede reportar; el mismo lado no puede confirmar su propio reporte. Los estados `validated`, `cancelled`, `postponed` y `under_review` no admiten nuevos envíos ni confirmaciones.

Los errores de validación y de regla de dominio se devuelven con estado `422` y un mensaje apto para mostrar al usuario; los fallos de autenticación o autorización conservan los estados HTTP establecidos por el middleware. La creación del reporte y las transiciones asociadas son atómicas.

### Reprogramación de partidos

El backend expone tres endpoints autenticados independientes:

- `GET /api/v1/matches/{gameMatch}/reschedule-workflow`;
- `POST /api/v1/matches/{gameMatch}/request-reschedule`;
- `POST /api/v1/matches/{gameMatch}/confirm-reschedule`.

La solicitud acepta `scheduled_date`, `scheduled_time`, `venue_id` y un comentario opcional de hasta 2.000 caracteres. Solo puede actuar un participante; en dobles, cualquiera de sus miembros representa al lado. El rival confirma la propuesta existente, tras lo cual se actualizan fecha y pista dentro de la misma transacción.

El backend rechaza partidos cerrados y colisiones exactas de pista/fecha/hora dentro del mismo campeonato. El contrato actual usa el `MatchResource` amplio y `MatchRescheduleRequestResource`, que incluye trazabilidad autorizada para este flujo privado. El mismo lado puede actualizar su propuesta antes de que exista una propuesta rival y estos endpoints todavía no tienen rate limiting específico.

La UI React de reprogramación no está implementada y queda fuera del cierre bloqueante del MVP.

---

# 8. Compatibilidad

Antes de modificar un endpoint debe comprobarse:

- si React lo consume;
- si existe un Resource asociado;
- si existen tests;
- si el cambio rompe el contrato actual.

---

# 9. Evolución prevista

Se consideran mejoras futuras:

- normalización completa del envelope;
- documentación OpenAPI;
- paginación homogénea;
- normalización de errores;
- metadatos comunes;
- revisión completa de serialización.

Estas mejoras deberán abordarse de forma coordinada y no mezclarse con pequeños desarrollos funcionales.

---

# 10. CMS público

La API pública incorpora lectura de páginas CMS publicadas mediante una estructura JSON de bloques.

## Listar páginas publicadas

`GET /api/v1/cms/pages`

Reglas:

- es público y no requiere autenticación;
- devuelve únicamente páginas con estado `published`;
- `published_at = null` significa publicación inmediata y la página es visible;
- una fecha pasada o igual al momento actual es visible;
- una fecha futura significa publicación programada y la página no es visible hasta alcanzarla;
- las páginas `draft` nunca son visibles;
- no devuelve bloques;
- no expone identificadores internos, estado, timestamps ni campos administrativos;
- el orden es `published_at` descendente y, en caso de empate, `id` descendente.

Respuesta:

```json
{
    "message": null,
    "data": [
        {
            "slug": "federarse",
            "title": "Federarse",
            "seo_description": "Información pública para federarse.",
            "published_at": "2026-06-24T10:00:00.000000Z",
            "url": "/contenidos/federarse"
        }
    ]
}
```

## Obtener página publicada por slug

`GET /api/v1/cms/pages/{slug}`

Reglas:

- es público y no requiere autenticación;
- devuelve únicamente páginas con estado `published`;
- `published_at = null` publica la página inmediatamente;
- si `published_at` tiene una fecha futura, la página todavía no se considera visible;
- las páginas inexistentes, en borrador o programadas para el futuro devuelven `404`;
- los bloques se devuelven ordenados por `order`;
- no expone identificadores internos, estado, timestamps ni claves foráneas del CMS.

Respuesta:

```json
{
    "message": null,
    "data": {
        "slug": "federarse",
        "title": "Federarse",
        "seo_title": "Federarse en Galotxas",
        "seo_description": "Información pública para federarse.",
        "published_at": "2026-06-24T10:00:00.000000Z",
        "blocks": [
            {
                "type": "heading",
                "order": 10,
                "data": {
                    "text": "Federarse"
                }
            }
        ]
    }
}
```

Tipos iniciales de bloque:

- `heading`: `{ "text": "...", "level": 2 }`;
- `text`: `{ "text": "..." }`;
- `list`: `{ "items": ["..."] }`;
- `image`: `{ "url": "...", "alt": "..." }`;
- `gallery`: `{ "urls": ["..."] }`;
- `button`: `{ "label": "...", "url": "..." }`;
- `document_link`: `{ "label": "...", "url": "..." }`.

El campo `data` es JSON estructurado y su forma depende del tipo de bloque. Esta base no incorpora todavía endpoints de noticias, formularios públicos ni subida de documentos o imágenes.

El listado y el detalle reutilizan el mismo criterio backend de publicación: estado `published` y fecha nula o no futura. La presentación administrativa «Programada» es derivada; no se expone un estado adicional ni cambia el envelope público.

## Consumo desde React

El frontend público consume este endpoint mediante la ruta:

`/contenidos/{slug}`

React renderiza los bloques con componentes controlados por `type` y no interpreta HTML libre. La ruta se mantiene bajo el prefijo `/contenidos` para evitar conflictos con rutas públicas ya existentes.

---

# 11. Relación con otros documentos

- `00-glossary.md`
- `01-domain.md`
- `02-architecture.md`
- `08-resources.md`

---

## Mantenimiento

Siempre que se modifique el contrato público de un endpoint deberá revisarse este documento y, cuando proceda, la documentación específica correspondiente.
