# API Outline — Galotxas

Este documento describe la API mínima inicial del proyecto Galotxas.

Su objetivo es definir los recursos principales, los endpoints esperados y el alcance funcional antes de implementar el backend, para garantizar un contrato estable entre capas.

La API debe ser REST, versionada y consistente.

---

## 1. Convenciones generales

### Prefijo base

`/api/v1`

### Formato general de respuesta
```json
{
  "success": true,
  "data": {},
  "message": null,
  "errors": null,
  "meta": {}
}
```

### Convenciones de naming

- rutas en plural
- versionadas
- consistentes
- respuestas JSON en `camelCase`

### Estados esperados en recursos relevantes

#### `matches.status`

- `scheduled`
- `submitted`
- `validated`
- `postponed`
- `cancelled`

---

## 2. Endpoints públicos

Los endpoints públicos deben permitir consultar la información visible del sistema sin autenticación.

### Seasons

#### `GET /api/v1/seasons`

Devuelve la lista de temporadas disponibles.

#### `GET /api/v1/seasons/{id}`

Devuelve el detalle de una temporada concreta.

---

### Championships

#### `GET /api/v1/championships`

Devuelve la lista de campeonatos.

Debe permitir filtros futuros, por ejemplo:

- temporada
- tipo (`singles`, `doubles`)
- estado

#### `GET /api/v1/championships/{id}`

Devuelve el detalle de un campeonato concreto.

---

### Categories

#### `GET /api/v1/categories`

Devuelve la lista de categorías.

Debe poder soportar filtros por:

- temporada
- campeonato
- tipo de categoría
- nivel

#### `GET /api/v1/categories/{id}`

Devuelve el detalle de una categoría concreta.

#### `GET /api/v1/categories/{id}/standings`

Devuelve la clasificación de la categoría.

Debe calcularse a partir de los resultados validados.

#### `GET /api/v1/categories/{id}/schedule`

Devuelve el calendario completo de la categoría.

#### `GET /api/v1/categories/{id}/results`

Devuelve los resultados de la categoría.

#### `GET /api/v1/categories/{id}/participants`

Devuelve los participantes inscritos en la categoría.

---

### Matches

#### `GET /api/v1/matches/{id}`

Devuelve el detalle de una partida concreta.

Debe incluir, al menos:

- categoría
- participantes
- fecha
- instalación
- estado
- resultado, si existe

---

### Rankings

#### `GET /api/v1/rankings/general`

Devuelve la clasificación general histórica.

La fórmula podrá evolucionar, pero este endpoint debe existir como recurso diferenciado.

---

### Public content

#### `GET /api/v1/rules`

Devuelve información pública sobre las reglas del deporte.

#### `GET /api/v1/content/pages/{slug}`

Devuelve páginas públicas informativas si se implementa sistema de contenido.

Ejemplos:

- reglamento
- historia
- contacto

---

## 3. Endpoints autenticados

Estos endpoints requieren usuario autenticado.

### Auth

#### `POST /api/v1/auth/login`

Autentica a un usuario.

#### `POST /api/v1/auth/logout`

Cierra la sesión o invalida el token actual.

#### `GET /api/v1/me`

Devuelve los datos del usuario autenticado.

Debe incluir, cuando aplique:

- rol
- jugador asociado
- permisos básicos

---

### Participación del usuario

#### `GET /api/v1/me/matches`

Devuelve las partidas relacionadas con el usuario autenticado.

Útil para mostrar:

- próximos partidos
- partidos pendientes de resultado
- histórico personal

#### `POST /api/v1/matches/{id}/submit-result`

Permite enviar el resultado de una partida.

Debe validar:

- que el usuario tenga permiso
- que el resultado sea coherente con el tipo de campeonato
- que no exista un estado incompatible

Campos esperados, de forma orientativa:

```json
{
  "homeScore": 10,
  "awayScore": 8
}
```

---

## 4. Endpoints de administración

Estos endpoints requieren rol de administración.

### Seasons

#### `POST /api/v1/admin/seasons`

Crea una temporada.

#### `PATCH /api/v1/admin/seasons/{id}`

Actualiza una temporada.

---

### Championships

#### `POST /api/v1/admin/championships`

Crea un campeonato.

#### `PATCH /api/v1/admin/championships/{id}`

Actualiza un campeonato.

---

### Categories

#### `POST /api/v1/admin/categories`

Crea una categoría.

#### `PATCH /api/v1/admin/categories/{id}`

Actualiza una categoría.

#### `POST /api/v1/admin/categories/{id}/entries`

Añade participantes a una categoría.

---

### Matches and scheduling

#### `POST /api/v1/admin/categories/{id}/generate-league`

Genera el calendario de liga para la categoría.

#### `POST /api/v1/admin/categories/{id}/generate-cup`

Genera la fase de copa a partir de la clasificación de liga.

#### `PATCH /api/v1/admin/matches/{id}`

Actualiza manualmente una partida.

#### `PATCH /api/v1/admin/matches/{id}/validate-result`

Valida el resultado enviado por un usuario.

#### `PATCH /api/v1/admin/matches/{id}/cancel`

Cancela una partida.

#### `PATCH /api/v1/admin/matches/{id}/postpone`

Aplaza una partida.

---

### Users

#### `GET /api/v1/admin/users`

Lista usuarios del sistema.

#### `PATCH /api/v1/admin/users/{id}`

Actualiza rol, estado o vínculo con jugador.

---

## 5. Recursos principales esperados

La API gira en torno a estos recursos:

- seasons
- championships
- categories
- category entries
- matches
- standings
- rankings
- users
- content pages

---

## 6. Reglas importantes del contrato API

- El frontend no debe calcular clasificaciones.
- Las clasificaciones se sirven ya calculadas desde backend.
- Los resultados solo cuentan si están en estado válido para ello.
- La clasificación histórica general no debe reutilizar sin más la lógica de standings de categoría.
- Los nombres de campos deben mantenerse estables salvo versión nueva de la API.

---

## 7. Alcance inicial recomendado

Para la primera versión funcional del backend, debe priorizarse este conjunto mínimo:

### Públicos

- `GET /api/v1/seasons`
- `GET /api/v1/categories/{id}`
- `GET /api/v1/categories/{id}/standings`
- `GET /api/v1/categories/{id}/schedule`
- `GET /api/v1/matches/{id}`

### Autenticados

- `POST /api/v1/auth/login`
- `GET /api/v1/me`
- `POST /api/v1/matches/{id}/submit-result`

### Administración

- `POST /api/v1/admin/seasons`
- `POST /api/v1/admin/championships`
- `POST /api/v1/admin/categories`
- `POST /api/v1/admin/categories/{id}/entries`
- `PATCH /api/v1/admin/matches/{id}/validate-result`