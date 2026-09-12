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
| P1.D.1C-B3-B — wiring CLI APPLY | completado hasta producción |
| P1.D.1C-B3 — coordinador y wiring CLI APPLY | completado hasta producción |
| P1.D.2-A — inspector/clasificador de reconciliación read-only | implementado localmente; pendiente de revisión humana/promoción |

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

## Cierre de P1.D.1C-B3

El commit `a39a0b15f42eb74817855675ec4479992ce92317` está desplegado y
aceptado en staging y producción. `--apply` existe en ambos entornos y
`--resume` no existe. B3-A sigue siendo la única fuente de orquestación de
negocio y seguridad; el CLI B3-B permanece fino.

La aceptación fue exclusivamente no destructiva. En staging y producción,
Laravel no estaba en mantenimiento: help mostró `--apply` y no `--resume`, la
invocación prohibida con `--resume` devolvió exit 2 y la invocación con forma
válida `--apply --domain=news --limit=1` mostró la advertencia sobre writers
externos y se detuvo en `maintenance_required`, exit 3. El journal permaneció
en runs/items/objects `0/0/0` antes y después; no se creó ningún run ni se
publicó media. Esto no acredita que workers estuvieran detenidos.

El contrato permanece en un dominio explícito, `--limit` obligatorio entre 1 y
1000, `--after-id` exclusivo opcional, sin `--resume` y con exits
`0/2/3/4/5/6/7`.

P1.D.1C-B3 está completado hasta producción. No se ha autorizado ni ejecutado
ningún APPLY operacional bajo mantenimiento ni ningún backfill/publicación de
media. P1.D.1C-B, P1.D, D2 y D3 continúan abiertos.

## Estado local de P1.D.2-A

D2-A implementa localmente el inspector/clasificador read-only y el comando
separado `media:responsive-backfill-reconcile`. Está pendiente de revisión
humana y promoción; no está aceptado en staging ni producción. Sólo añade
lecturas acotadas del journal, observación de claves exactas y clasificaciones
tipadas de objetos, items y runs. No muta journal, checkpoint, dominio o
storage, no adquiere el lock de APPLY y no exige ni activa mantenimiento.

`absent_now` es exclusivamente una observación puntual. En particular, ante
`intent` o `unknown` no acredita ausencia permanente, no fabrica un recibo y no
despeja la barrera de recuperación. Tampoco la igualdad actual de contenido
acredita ownership. Un conjunto funcional exacto describe únicamente que el
plan completo y sus bytes actuales coinciden; conserva por separado la
atribución histórica, incluso `rejected`, `failed` o `planned`. En la inspección
de un run, `--limit` acota el trabajo sobre items y un detalle truncado se marca
explícitamente sin convertirlo en inconsistencia. D2-A no implementa cleanup
automático local ni cleanup S3.

P1.D.1C-B3 permanece aceptado hasta producción, pero sigue sin autorizarse ni
haberse ejecutado ningún APPLY real. El siguiente bloque, sólo después de la
aceptación humana de D2-A, es D2-B: estados/API de resolución durable. Hasta
entonces toda evidencia bloqueante permanece intacta.

## Documentos de referencia

- [31-responsive-backfill-foundation.md](31-responsive-backfill-foundation.md) — fundación de lectura, inspección y preflight (P1.D.1A).
- [32-responsive-backfill-safety.md](32-responsive-backfill-safety.md) — journal, identidad, lock, mantenimiento y escritura exclusiva (P1.D.1B).
- [33-responsive-backfill-runner.md](33-responsive-backfill-runner.md) — dry-run y wiring CLI APPLY aceptados hasta producción.
- [34-responsive-backfill-apply-design.md](34-responsive-backfill-apply-design.md) — diseño aprobado de APPLY; B1/B2/B3-A/B3-B y B3 aceptados hasta producción.
- [35-responsive-backfill-reconciliation.md](35-responsive-backfill-reconciliation.md) — D2-A read-only local: lectores acotados, observación exacta, clasificaciones y CLI.

## Invariante de traspaso

Sólo se arranca otro agente de implementación desde un checkpoint limpio y commiteado.

No se traspasa un bloque a medio implementar sin checkpoint seguro explícito y decisión del usuario. Las reglas completas están en `/AGENTS.md`.
