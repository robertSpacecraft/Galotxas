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
| P1.D.2-A — inspector/clasificador de reconciliación read-only | completado hasta producción |
| P1.D.2-B1 — esquema y representación durable read-only | implementado localmente; pendiente de aceptación humana y promoción |

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

## Cierre de P1.D.2-A

El commit `b4f68144258900764ccf5790f3a09e4aa1e04030` está desplegado y
aceptado en staging y producción. El comando read-only independiente
`media:responsive-backfill-reconcile` existe en ambos entornos. La aceptación
fue estrictamente no mutante: en cada entorno el journal permaneció en
runs/items/objects `0/0/0` antes y después, la barrera global se observó
`clear`, el resumen acotado terminó con exit 0 y declaró cero mutaciones de
journal y cero escrituras/borrados de storage. `--execute` no existe y fue
rechazado con exit 2; `--limit` con selector de item u objeto también fue
rechazado con exit 2, conforme al contrato.

D2-A sólo añade lecturas acotadas del journal, observación de claves exactas y
clasificaciones tipadas de objetos, items y runs. No incorpora estados durables
de reconciliación ni APIs de mutación, no usa listing heurístico, no muta
journal, checkpoint, dominio o storage, no adquiere el lock de APPLY y no exige
ni activa mantenimiento.

`absent_now` es exclusivamente una observación puntual. En particular, ante
`intent` o `unknown` no acredita ausencia permanente, no fabrica un recibo y no
despeja la barrera de recuperación. Tampoco la igualdad actual de contenido
acredita ownership. Un conjunto funcional exacto describe únicamente que el
plan completo y sus bytes actuales coinciden; conserva por separado la
atribución histórica, incluso `rejected`, `failed` o `planned`. En la inspección
de un run, `--limit` acota el trabajo sobre items y un detalle truncado se marca
explícitamente sin convertirlo en inconsistencia. Las contradicciones entre run
e item se propagan fail-closed a las clasificaciones de item, objeto y resumen
global. La clasificación de conjunto funcional exacto es independiente de la
atribución histórica y sigue siendo sólo un candidato read-only pendiente de
revalidación de dominio en D2-C. D2-A no implementa cleanup automático local ni
cleanup S3.

P1.D.1C-B3 permanece aceptado hasta producción. El despliegue de D2-A no
autoriza ningún APPLY real: durante su aceptación no hubo APPLY operacional,
reconciliación mutante, publicación de storage ni creación de evidencia
artificial. P1.D.2 y P1.D permanecen abiertos. El diseño D2-B posterior definió
el modelo mínimo de resolución durable; el estado local de su primer bloque se
detalla a continuación. Toda reconciliación mutante sigue siendo trabajo futuro,
posterior a la implementación y aceptación de sus contratos de seguridad.

## Estado local de P1.D.2-B1

D2-B1 está implementado únicamente en el árbol local y queda pendiente de
revisión humana, commit y promoción. Añade un esquema híbrido: eventos de
reconciliación conceptualmente append-only y proyecciones nullable en runs,
items y objetos. `NULL` significa sin resolución D2 aceptada, incluida toda fila
pre-D2. Los hechos originales de APPLY y los checkpoints no se reescriben.

La inspección read-only muestra las proyecciones como una dimensión separada.
También incorpora `functionalStorageSetExact`: acredita sólo que el plan
completo y los bytes actuales forman el conjunto funcional exacto. Es
independiente de la atribución histórica y puede coexistir con cleanup
`pending`, `failed` o `unknown`, pero es falso ante cleanup `deleted`, lectura
no exacta, plan incompleto/duplicado, identidad distinta o evidencia
inconsistente. No acredita ownership ni constituye una resolución durable.

La barrera de recuperación conserva deliberadamente en B1 sus cuatro
predicados históricos sin override por proyecciones. No existe todavía ningún
repositorio/API de mutación de reconciliación, cierre tardío de run, cleanup ni
ausencia confirmada. El rollback físico de la migración B1 sólo se permite
cuando no hay eventos ni proyecciones. D2-B2 será el siguiente bloque sólo tras
la aceptación de B1; P1.D.2 y P1.D permanecen abiertos.

## Documentos de referencia

- [31-responsive-backfill-foundation.md](31-responsive-backfill-foundation.md) — fundación de lectura, inspección y preflight (P1.D.1A).
- [32-responsive-backfill-safety.md](32-responsive-backfill-safety.md) — journal, identidad, lock, mantenimiento y escritura exclusiva (P1.D.1B).
- [33-responsive-backfill-runner.md](33-responsive-backfill-runner.md) — dry-run y wiring CLI APPLY aceptados hasta producción.
- [34-responsive-backfill-apply-design.md](34-responsive-backfill-apply-design.md) — diseño aprobado de APPLY; B1/B2/B3-A/B3-B y B3 aceptados hasta producción.
- [35-responsive-backfill-reconciliation.md](35-responsive-backfill-reconciliation.md) — D2-A read-only aceptado hasta producción y D2-B1 local pendiente: esquema híbrido y representación durable read-only.

## Invariante de traspaso

Sólo se arranca otro agente de implementación desde un checkpoint limpio y commiteado.

No se traspasa un bloque a medio implementar sin checkpoint seguro explícito y decisión del usuario. Las reglas completas están en `/AGENTS.md`.
