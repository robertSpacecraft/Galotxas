# Estado actual para traspaso entre agentes

## Propósito

Este archivo describe únicamente el estado de traspaso vigente. No es arquitectura, no es roadmap y no sustituye a la documentación técnica.

Es el punto de entrada de cualquier agente que inicie un bloque nuevo o tome el relevo de otro.

Es deliberadamente mutable: se actualiza al cerrar cada bloque.

## Fuente de verdad

Si este archivo entra en conflicto con el código o con un documento técnico específico, prevalecen esas fuentes más concretas y actuales, y este archivo debe corregirse.

No debe contener secretos, credenciales, identificadores de infraestructura, rutas personales, detalles de cuota o contexto de agentes, ni relatos históricos extensos. El historial pertenece a `06-roadmap.md`, `07-decisions.md` y `CHANGELOG.md`.

## Línea de trabajo actual

P1 — optimización responsive de media.

Sublínea activa: P1.D — backfill de masters legacy.

| Bloque | Estado |
| --- | --- |
| P1.D.1A — fundación de lectura | completado |
| P1.D.1B — primitivas de seguridad | completado |
| P1.D.1C-A — dry-run read-only | completado hasta producción |
| P1.D.1C-B1 — rango/checkpoint/barrera internos | completado hasta producción |
| P1.D.1C-B2 — publicador interno item-atomic | completado hasta producción |
| P1.D.1C-B3-A — coordinador interno de invocación/rango APPLY | completado hasta producción |
| P1.D.1C-B3-B — wiring CLI APPLY | implementado localmente; pendiente de revisión humana y promoción |

P1.D.1C-A está completado hasta producción. Su smoke de producción terminó correctamente con exit 0, cero bloqueos y cero escrituras de storage.

Los smokes read-only de B1 en staging y producción validaron el JSON tipado de
`ApplyRunSelection` y devolvieron `recoveryBarrier()=clear` en el momento de cada
comprobación, ambos con exit 0.

Los smokes no destructivos de B2 en staging y producción confirmaron la
resolución por DI de `ApplyItemPublisher`, la firma de tres parámetros de
`publish()`, `recoveryBarrier()=clear` en el momento de cada comprobación y la
ausencia de `--apply`; ambos finalizaron con exit 0.

Los smokes no destructivos de B3-A en staging y producción confirmaron para el
commit aceptado la resolución por DI de `ResponsiveBackfillApply`, la firma de
un parámetro de `run()`, `recoveryBarrier()=clear` en el momento de cada
comprobación y la ausencia de `--apply`; ambos finalizaron con exit 0. Ese valor
`clear` es evidencia puntual, no una propiedad permanente. No se autorizó ni
ejecutó ningún APPLY operacional ni se publicó media durante estos smokes.

## Bloque local pendiente de revisión

P1.D.1C-B3-B incorpora en este branch/worktree local el wiring CLI `--apply`,
su validación, presentación segura y mapeo de resultados. No debe considerarse
presente en staging ni producción hasta su revisión humana y promoción.

La invocación APPLY local exige un dominio explícito, `--limit` obligatorio
entre 1 y 1000 y `--after-id` exclusivo opcional; no existe `--resume`. Es no
interactiva, advierte que el operador debe detener workers y escritores externos
y usa exits `0/2/3/4/5/6/7`. B3-A sigue siendo la única fuente de orquestación
de negocio y seguridad; el CLI permanece fino.

No se ha autorizado ni ejecutado ningún APPLY operacional. P1.D.1C-B3,
P1.D.1C-B, P1.D, D2 y D3 continúan abiertos.

## Documentos de referencia

- [31-responsive-backfill-foundation.md](31-responsive-backfill-foundation.md) — fundación de lectura, inspección y preflight (P1.D.1A).
- [32-responsive-backfill-safety.md](32-responsive-backfill-safety.md) — journal, identidad, lock, mantenimiento y escritura exclusiva (P1.D.1B).
- [33-responsive-backfill-runner.md](33-responsive-backfill-runner.md) — dry-run aceptado y wiring CLI APPLY implementado localmente.
- [34-responsive-backfill-apply-design.md](34-responsive-backfill-apply-design.md) — diseño aprobado de APPLY, B1/B2/B3-A aceptados hasta producción y B3-B local pendiente de revisión/promoción.

## Invariante de traspaso

Sólo se arranca otro agente de implementación desde un checkpoint limpio y commiteado.

No se traspasa un bloque a medio implementar sin checkpoint seguro explícito y decisión del usuario. Las reglas completas están en `/AGENTS.md`.
