# Domain Model — Galotxas

Este documento describe el modelo conceptual del dominio del sistema de gestión de competiciones de Galotxas.

Su objetivo es definir las entidades principales del sistema y las relaciones entre ellas antes de diseñar el modelo de base de datos definitivo.

---

## 1. Estructura general de competiciones

Las competiciones siguen una jerarquía clara:

Season
└── Championship
    └── Category
        └── CategoryEntry
            └── Player / Team

### Temporada
Representa un año o periodo competitivo.  
*Ejemplo: Temporada 2026.* Cada temporada contiene varios campeonatos.

### Campeonato
Cada temporada contiene dos campeonatos principales que definen el tipo de participación:
- **Mà a mà** (individual)
- **Dobles** (parejas)

### Categoría
Cada campeonato se divide en varias categorías que funcionan como competiciones independientes.  
*Ejemplo:* 1ª, 2ª, 3ª, 4ª, 5ª, 6ª, femenina o mixta.

---

## 2. Participantes

Dependiendo del campeonato, los participantes pueden ser:

### Individual
El participante es directamente un **jugador** (`Player`).

### Dobles
El participante es un **equipo** (`Team`) formado por dos jugadores.

---

## 3. Inscripción en categoría

Los participantes se inscriben en categorías concretas mediante una entidad de relación.

Category
└── CategoryEntry
    └── Player / Team

Esto permite controlar los participantes por categoría, registrar históricos y soportar distintos formatos.

---

## 4. Calendario de competición

Cada categoría genera su propio calendario.

### Jornadas (Rounds)
Agrupan los partidos dentro de una fase (ej. Round 1, Round 2). Las jornadas pueden pertenecer a diferentes fases.

### Fases
- **Liga:** Formato round-robin (todos contra todos) a ida única o ida y vuelta.
- **Copa:** Los 4 primeros clasificados de la liga disputan eliminatorias (Semifinal, Final y 3º/4º puesto).

---

## 5. Partidas (Matches)

Una partida representa un enfrentamiento entre dos participantes.

Match
├── home participant
├── away participant
├── scheduled date
└── venue

---

## 6. Resultados

Cada partida registra su resultado dentro de la propia entidad `Match`.

El flujo es el siguiente:

1. El ganador introduce el resultado de la partida.
2. El sistema lo registra con estado `submitted`.
3. El administrador lo valida.
4. El resultado pasa a estado definitivo (`validated`).

---

## 7. Clasificaciones

### Clasificación de categoría
Calculada dinámicamente según:
- Victoria: **3 puntos**
- Derrota: **0 puntos**
- Derrota con más de 8 juegos: **1 punto**

### Ranking histórico
Ranking global inspirado en el sistema **ATP**. Se calcula sobre todas las temporadas y categorías basándose en el rendimiento acumulado de los jugadores.

---

## 8. Instalaciones (Venues)

Las partidas se disputan en sedes concretas.
**Venue** ├── **name** ├── **location** └── **description**

---

## 9. Relaciones principales

Resumen jerárquico del sistema:

**Season** └── **Championship** └── **Category** └── **CategoryEntry** └── **Player / Team**

Category
└── Round
    └── Match

---

## 10. Principios del modelo de dominio

* El dominio deportivo es la fuente de verdad.
* Las reglas del juego se implementan exclusivamente en el **backend**.
* La clasificación se deriva siempre de los resultados validados.
* El sistema es multitemporada y multicategoría por diseño.
* Soporte nativo para diferentes formatos de competición (Liga y Copa).