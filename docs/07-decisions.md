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
- AdminMatchResource

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

## Mantenimiento

Cuando una decisión arquitectónica relevante cambie, deberá registrarse una nueva entrada en este documento en lugar de modificar silenciosamente una anterior.