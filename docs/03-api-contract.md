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

## Obtener página publicada por slug

`GET /api/v1/cms/pages/{slug}`

Reglas:

- es público y no requiere autenticación;
- devuelve únicamente páginas con estado `published`;
- si `published_at` tiene una fecha futura, la página todavía no se considera visible;
- las páginas inexistentes o en borrador devuelven `404`;
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

- `heading`;
- `text`;
- `list`;
- `image`;
- `gallery`;
- `button`;
- `document_link`.

El campo `data` es JSON estructurado y su forma depende del tipo de bloque. Esta base no incorpora todavía endpoints de noticias, formularios públicos, administración CMS ni subida de documentos o imágenes.

---

# 11. Relación con otros documentos

- `00-glossary.md`
- `01-domain.md`
- `02-architecture.md`
- `08-resources.md`

---

## Mantenimiento

Siempre que se modifique el contrato público de un endpoint deberá revisarse este documento y, cuando proceda, la documentación específica correspondiente.
