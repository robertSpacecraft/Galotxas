# Galotxas — Reglas globales para agentes

## Propósito

Este archivo define las reglas globales del proyecto Galotxas.

Su objetivo es proporcionar un contexto estable para cualquier agente o desarrollador. Debe contener únicamente principios duraderos, nunca el estado puntual del desarrollo ni el roadmap.

Las reglas específicas de cada capa se encuentran en:

- `/backend/AGENTS.md`
- `/frontend/AGENTS.md`

La documentación funcional y técnica se encuentra en `/docs`.

---

## Arquitectura general

El proyecto es un monorepo compuesto por:

- Frontend React (interfaz pública y zona privada del jugador autenticado).
- Backend Laravel (API REST y panel administrativo Blade).
- MariaDB como único motor de base de datos soportado.
- Docker como entorno de desarrollo y pruebas.

Existen dos interfaces independientes:

1. React → API REST → Laravel → MariaDB
2. Blade Admin → Laravel Web → MariaDB

Ambas comparten el mismo dominio de negocio.

---

## Principios del proyecto

- El backend es la fuente de verdad del dominio.
- El frontend nunca calcula reglas deportivas ni rankings.
- Toda regla deportiva debe implementarse exclusivamente en backend.
- La API constituye el contrato entre frontend y backend.
- La lógica de negocio debe residir en Services cuando sea reutilizable.
- Los cambios deben ser pequeños, coherentes y fácilmente verificables.

---

## Base de datos

- MariaDB es el único motor soportado.
- SQLite no forma parte del proyecto.
- Las pruebas de integración se ejecutan sobre una instancia MariaDB aislada.

---

## Convenciones generales

- Evitar duplicar lógica.
- Priorizar reutilización frente a copiar código.
- Mantener separación entre dominio, API, administración y presentación.
- Los cambios estructurales deben ir acompañados de pruebas y actualización documental.

---

## Precedencia documental

Las instrucciones se aplican de forma jerárquica.

Orden de aplicación:

1. `AGENTS.md` situado en la raíz del proyecto.
2. `AGENTS.md` del directorio donde se encuentra el archivo que se va a modificar.
3. Documentación técnica aplicable (`/docs`, `BACKEND_STYLE.md`, `FRONTEND_STYLE.md`, etc.).

En caso de conflicto, prevalece siempre la instrucción más específica.

Por tanto:

- El `AGENTS.md` de la raíz define las reglas generales del proyecto.
- Los `AGENTS.md` de `backend` y `frontend` complementan esas reglas dentro de su ámbito.
- Las guías de estilo concretan convenciones de implementación, pero no sustituyen las reglas definidas por los `AGENTS.md`.

---

## Flujo de trabajo

- Trabajar por bloques pequeños.
- Validar antes de continuar.
- Mantener commits con un único objetivo.
- Actualizar la documentación cuando cambie el comportamiento del sistema.

No introducir cambios amplios en varias capas simultáneamente sin una justificación clara.
