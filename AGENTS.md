# Galotxas — Reglas globales para agentes

## Propósito

Este archivo define las reglas globales del proyecto Galotxas.

Su objetivo es proporcionar un contexto estable para cualquier agente o desarrollador. Debe contener únicamente principios duraderos, nunca el estado puntual del desarrollo ni el roadmap.

Las reglas específicas de cada capa se encuentran en:

- `/backend/AGENTS.md`
- `/frontend/AGENTS.md`
- `/knowledge/AGENTS.md`

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

## Gobernanza de contenido

- Antes de crear o ampliar una sección pública, definir su finalidad, audiencia, fuente de verdad, responsables de edición, persistencia, administración, API, publicación, permisos, multimedia, URLs y pruebas.
- Distinguir el dominio funcional, el contenido administrable y el conocimiento canónico. La fuente concreta de cada área se documenta en `/docs/10-content-governance.md`.
- No mantener una misma fuente editorial editable a la vez en `knowledge/`, base de datos, componentes React, seeders u otras copias manuales.
- React no es una fuente editorial para contenido que deba modificar un administrador.
- Todo cambio debe revisar el impacto coordinado entre `backend/`, `frontend/` y `knowledge/`, aunque finalmente solo afecte a una de estas zonas.
- Una sección administrable se trata como un bloque vertical: dominio o CMS, administración Blade, autorización, persistencia, API, frontend, pruebas y documentación según corresponda.
- La documentación afectada se actualiza dentro del mismo bloque que cambia el comportamiento.

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
- El `AGENTS.md` de `knowledge` complementa estas reglas para el conocimiento canónico y editorial.
- Las guías de estilo concretan convenciones de implementación, pero no sustituyen las reglas definidas por los `AGENTS.md`.

Antes de modificar un archivo se deben consultar todos los `AGENTS.md` aplicables a su ubicación y las referencias técnicas que estos indiquen.

---

## Flujo de trabajo

- Trabajar por bloques pequeños.
- Validar antes de continuar.
- Mantener commits con un único objetivo.
- Actualizar la documentación cuando cambie el comportamiento del sistema.
- Trabajar desde WSL Ubuntu con Bash y herramientas Linux para modificar el repositorio.
- Mantener los archivos de texto en UTF-8, con finales de línea LF y sin espacios finales.

No introducir cambios amplios en varias capas simultáneamente sin una justificación clara.

---

## Operativa multiagente

Codex, Claude Code y Antigravity son agentes de implementación equivalentes para este repositorio.

La elección entre ellos depende de disponibilidad y capacidad. Ningún diseño, decisión ni continuidad del proyecto puede depender de la memoria privada o del historial de sesión de un agente concreto.

Reglas de bloque y traspaso:

- Un bloque de implementación debe completarse normalmente con el mismo agente que lo inició.
- No se traspasa un bloque a medio implementar por el mero hecho de que ese agente agote cuota o contexto. El traspaso exige un checkpoint seguro explícito y una decisión del usuario.
- Antes de que otro agente asuma un bloque nuevo, el repositorio debe encontrarse en un checkpoint de traspaso commiteado y revisable, con el árbol de trabajo completamente limpio: sin cambios preparados, sin cambios sin preparar y sin archivos sin seguimiento. La invariante alcanza a todo el repositorio, incluida documentación y configuración.
- Git permanece bajo control del usuario. Los agentes no ejecutan `git add`, `git commit`, `git push`, `git merge`, `git reset`, `git restore`, `git stash` ni despliegues salvo instrucción explícita.

---

## Contexto canónico del proyecto

El contexto canónico es el propio repositorio:

- la jerarquía de archivos `AGENTS.md`;
- `/docs`;
- `/knowledge` cuando la materia sea conocimiento canónico;
- el código fuente y sus pruebas.

Las memorias locales de un agente, los Knowledge Items, el historial de conversación, los planes generados y los almacenes de reglas propios de una herramienta no son canónicos y no pueden invocarse como fuente.

Por tanto:

- Toda decisión duradera, restricción, riesgo no resuelto o estado de implementación que necesite el siguiente agente debe escribirse en el repositorio antes del traspaso.
- Al iniciar un bloque nuevo, o al asumir el relevo de otro agente, leer primero `docs/CURRENT_STATE.md` y después los documentos técnicos que este referencie.
- `docs/CURRENT_STATE.md` se mantiene breve y orientado a punteros. No duplica documentos técnicos extensos ni sustituye a `/docs`.
