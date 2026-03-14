# Galotxas — Contexto técnico y funcional del proyecto

Este documento define el contexto funcional, el dominio, la arquitectura y los principios de desarrollo del proyecto **Galotxas**.

Su objetivo es servir como guía estable para agentes y desarrolladores, de modo que las decisiones técnicas y de implementación mantengan coherencia con la realidad del deporte y con la arquitectura del sistema.

Este documento debe considerarse referencia principal antes de generar código, modelos, endpoints o interfaces.

---

## 0. Descripción general del deporte Galotxas

Las **Galotxas** son una modalidad local de **pilota valenciana** practicada principalmente en **Monóvar** y, en menor medida, en localidades cercanas como **La Romana**.

Fuera de Monóvar esta modalidad también se conoce como **galotxetes**, pero dentro del proyecto se utilizará de forma unificada el término **Galotxas** como denominación oficial del deporte, del sistema y de la terminología de dominio.

### Modalidades de juego

Existen dos formatos principales:

* **Mà a mà (1 vs 1)**
* **Dobles (2 vs 2)**

Cada modalidad tiene campeonatos propios.

### Instalación de juego

Las partidas se disputan en una cancha cerrada con paredes laterales y paredes de fondo.

Características relevantes:

* La cancha se divide con una **red destensada**.
* La red cuelga desde los extremos y su parte central queda cerca o tocando el suelo.
* Si la pelota toca la red se considera **fallo**, aunque la atraviese.

A diferencia de deportes como tenis o pádel:

* La pelota **puede tocar directamente las paredes** sin necesidad de bote previo.
* Solo se permite **un bote en el suelo**.
* En el saque sí existe bote previo obligatorio.

En las paredes de fondo existe una cuña inclinada llamada **aire**:

* Si la pelota bota en esa cuña se considera **como si no hubiera botado**.

### Sistema de tanteo

Cada punto se contabiliza siguiendo un sistema equivalente al del tenis:

* 15
* 30
* val
* joc

Cada **joc** equivale a un juego ganado dentro de la partida.

### Finalización de la partida

No puede haber empate. La partida finaliza cuando:

* En **individual**, un jugador alcanza **10 juegos**.
* En **dobles**, un equipo alcanza **12 juegos**.

---

## 1. Sistema de competiciones

Cada **temporada** contiene dos campeonatos principales:

* **Mà a mà** (1 vs 1)
* **Dobles** (2 vs 2)

Cada campeonato se organiza en **categorías independientes**.

Ejemplo de categorías: 1ª, 2ª, 3ª, 4ª, 5ª, 6ª, femenina, mixta. Cada categoría funciona como una competición autónoma con participantes, calendario, clasificación y fase final propia.

### Fases de competición

Cada categoría se compone de dos fases:

1.  **Liga:** Todos los participantes se enfrentan entre sí (ida única o ida y vuelta, configurable).
2.  **Copa:** Los **4 primeros clasificados de la liga** disputan la fase final:
    * Semifinal 1: 1º vs 4º
    * Semifinal 2: 2º vs 3º
    * Final
    * Partido por 3º y 4º puesto

---

## 2. Sistema de puntuación

La clasificación de liga se calcula a partir de los resultados de las partidas:

* **Victoria:** 3 puntos.
* **Derrota:** 0 puntos.
* **Derrota con más de 8 juegos anotados:** 1 punto.

Estas reglas aplican tanto a individuales como a dobles.

---

## 3. Clasificaciones del sistema

El sistema debe manejar dos tipos de clasificación diferentes:

### 3.1 Clasificación de categoría
Es la clasificación correspondiente a una categoría concreta dentro de un campeonato y una temporada. Se calcula dinámicamente a partir de puntos, victorias, derrotas, juegos a favor/contra y diferencia de juegos. No se almacena como tabla persistente inicialmente.

### 3.2 Clasificación general histórica
Es un ranking global de jugadores (tipo **ATP**) basado en el rendimiento acumulado en todas las categorías y temporadas. La fórmula podrá evolucionar y debe ser independiente de la lógica de la clasificación de categoría.

---

## 4. Objetivo del proyecto

Desarrollar una plataforma web para la gestión y visualización de competiciones de Galotxas que permita:

* Gestión de temporadas, campeonatos, categorías y participantes.
* Programación de partidos y registro de resultados.
* Visualización pública de calendarios, clasificaciones e histórico.
* Arquitectura basada en API para futuras integraciones (móvil, estadística).

---

## 5. Arquitectura del sistema

El sistema sigue un modelo desacoplado:
**Frontend (React) → API REST → Backend (Laravel) → Base de datos (MariaDB)**

* Backend independiente del frontend.
* API como contrato estable.
* Lógica de negocio exclusivamente en backend (no genera HTML).

---

## 6. Organización del repositorio (Monorepo)

* `/backend`: Aplicación Laravel (dominio, API, cálculos).
* `/frontend`: Aplicación React (interfaz, consumo de API).
* `/backend/docker`: Infraestructura (PHP-FPM, Nginx, MariaDB, Node).
* `/docs`: Documentación.
* `AGENTS.md` / `README.md`.

---

## 7. Tecnologías

* **Backend:** Laravel, PHP, MariaDB, Docker, Laravel Sanctum.
* **Frontend:** React, Vite.

---

## 8. Usuarios del sistema

* **Administrador:** Control total sobre la estructura, participantes y validación de resultados.
* **Participante autenticado:** Consulta de calendario/clasificación e introducción de resultados (normalmente el ganador).
* **Usuario público:** Consulta de datos sin necesidad de login.

---

## 9. Dominio del sistema (Entidades)

`users`, `players`, `seasons`, `championships`, `categories`, `teams`, `team_members`, `category_entries`, `rounds`, `matches`, `venues`.

---

## 10. Modelo conceptual de datos

### users
`id`, `name`, `email`, `password`, `role`, `player_id`, `timestamps`.

### players
`id`, `name`, `slug`, `active`, `timestamps`.

### seasons
`id`, `name`, `start_date`, `end_date`, `status`, `timestamps`.

### championships
`id`, `season_id`, `name`, `type` (singles|doubles), `status`, `timestamps`.

### categories
`id`, `championship_id`, `name`, `slug`, `level` (1-6|null), `category_type` (open|female|mixed), `status`, `timestamps`.

### teams / team_members
`teams` gestiona los grupos y `team_members` vincula `player_id` con `team_id` para un campeonato concreto.

### category_entries
Participantes inscritos. `entry_type` (player|team) determina si se usa `player_id` o `team_id`.

### rounds / matches
`rounds` define la fase (league|cup). `matches` registra el encuentro, puntuaciones, estado (scheduled, submitted, validated, etc.) y trazabilidad de quién introdujo y validó el resultado.

---

## 11. Gestión de resultados

1.  El ganador introduce el resultado.
2.  Backend valida permisos y coherencia.
3.  El sistema registra el autor y la fecha.
4.  El administrador valida el resultado para que sea definitivo.

---

## 12. API (`/api/v1`)

* **Públicos:** `/seasons`, `/categories/{id}/standings`, `/rankings/general`.
* **Autenticados:** `/auth/login`, `/me`, `/matches/{id}/submit-result`.
* **Admin:** `/admin/seasons`, `/admin/matches/{id}/validate-result`.

---

## 13. Autenticación

Uso de **Laravel Sanctum** para soportar tanto entorno web como futuras apps móviles.

---

## 14. Convenciones de código

* **DB:** `snake_case`.
* **JSON API:** `camelCase`.
* **React:** Variables `camelCase`, Componentes `PascalCase`.
* **PHP:** Clases `PascalCase`.
* **Rutas:** Plurales y versionadas.

---

## 15. Respuesta estándar de API

```json
{
  "success": true,
  "data": {},
  "message": null,
  "errors": null,
  "meta": {}
}
```

## 16. Principios de implementación

Los agentes deben respetar los siguientes pilares durante el desarrollo:

* **El backend contiene la lógica del deporte:** Cualquier regla sobre puntuación, validación de sets o finalización de partidas debe residir en el servidor.
* **El frontend no calcula clasificaciones:** La interfaz se limita a pintar los datos procesados que recibe de la API.
* **La API es el contrato entre capas:** Cualquier cambio en la estructura de datos debe reflejarse en el contrato de la API para mantener la integridad del sistema.
* **Evitar lógica duplicada:** Reutilizar servicios y modelos para asegurar que el dominio deportivo sea consistente en todo el sistema.
* **Mantener trazabilidad:** Es obligatorio registrar quién, cuándo y cómo se introducen y validan los resultados.

---

## 17. Fases de desarrollo

El proyecto se ejecutará siguiendo este orden de prioridades:

### Fase 1 — Núcleo
* Sistema de autenticación y roles básicos.
* Estructura base de competición (Temporadas, Campeonatos, Categorías).
* Gestión de participantes y programación de partidos.
* Flujo de introducción de resultados por parte de los jugadores.
* Cálculo dinámico de la clasificación de categoría.

### Fase 2 — Panel de Administración
* Interfaz de gestión completa para administradores.
* Herramientas de validación y corrección de resultados.
* Gestión de usuarios y permisos.
* Algoritmo para la generación automática de calendarios (round-robin).

### Fase 3 — Ranking histórico
* Implementación de la clasificación general global (ranking tipo ATP).
* Módulo de estadísticas avanzadas.
* Perfiles públicos detallados para cada jugador.

### Fase 4 — Expansión
* Desarrollo de aplicación móvil nativa/híbrida.
* Sistema de notificaciones (push/email) para próximos partidos.
* Integraciones con plataformas externas o herramientas de análisis.

---

Antes de escribir código, resume brevemente cómo vas a estructurar la solución, qué archivos vas a crear y qué decisiones de diseño estás asumiendo.

No implementes toda la aplicación de una sola vez.

Trabaja por fases pequeñas, con cambios coherentes y verificables.  
Antes de crear nuevas partes relevantes del sistema, confirma primero la estructura propuesta.

Prioriza siempre:
1. modelo de dominio
2. backend y contrato API
3. frontend consumidor de la API
4. mejoras y automatizaciones