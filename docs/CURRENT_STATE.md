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
| P1.D.2-B1 — esquema y representación durable read-only | completado hasta producción |
| P1.D.2-B2 — APIs internas item-atomic de resolución | completado hasta producción |

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
el modelo mínimo de resolución durable; el cierre de su primer bloque se detalla
a continuación. Toda reconciliación mutante sigue siendo trabajo futuro.

## Cierre de P1.D.2-B1

El commit `ad6334b60415ceab3b1658cc700e842e52183e9f` está desplegado y
aceptado en staging y producción. Añade el esquema híbrido de eventos de
reconciliación conceptualmente append-only y proyecciones nullable en runs,
items y objetos. `NULL` significa sin resolución D2 aceptada, incluida toda fila
pre-D2. Los hechos originales de APPLY y los checkpoints no se reescriben.

El despliegue de código en Railway no ejecutó la migración Laravel. En cada
entorno se instaló exclusivamente la migración B1 con `php artisan migrate`
usando el path exacto
`database/migrations/2026_09_12_000000_add_media_backfill_reconciliation_representation.php`
y `--force`; no se usó migrate general, rollback ni fresh. Es una nota
operativa de despliegue, no un cambio de arquitectura de la aplicación.

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
cuando no hay eventos ni proyecciones.

En staging y producción, runs/items/objects/events y los cinco conteos de
proyecciones no nulas fueron cero antes y después. `--execute` continuó
rechazado con exit 2; la inspección read-only terminó con exit 0 y observó
`recoveryBarrier()=clear`, sin mutaciones de journal ni escrituras/borrados de
storage. Las APIs item-atomic de resolución forward/no-effect corresponden a
D2-B2 y se describen a continuación. Barrier V2 y el cierre tardío de run siguen
en D2-B3; la reconciliación destructiva/cleanup permanece para trabajo
posterior. P1.D.2 y P1.D continúan abiertos.

## Cierre de P1.D.2-B2

El commit `64af3f2358afdaad08ac34bfe8d758121d54711d` está desplegado y
aceptado en staging y producción. Añade el repositorio interno de mutación
`backend/app/Services/Media/Backfill/Reconciliation/ReconciliationJournal.php`
con exactamente cuatro operaciones: `beginRunReconciliation()`,
`recordBlockedAttempt()`, `recordForwardItemResolution()` y
`recordNoEffectItemResolution()`. `ApplyJournal` no gana métodos de mutación de
reconciliación y no existen setters genéricos. No requirió ninguna migración
propia: el esquema instalado en D2-B1 ya cubría eventos y proyecciones.

Los eventos son append-only y se direccionan por `event_id` y `attempt_id` del
llamador: replay idéntico es no-op, cualquier divergencia o intento de
sustituir una proyección terminal falla cerrado, y un estado durable parcial se
rechaza en lugar de repararse. La aceptación forward es item-atomic sobre una
sola transacción con orden de bloqueo run → item → objetos ascendentes y valida
una evidencia tipada v1 con hashing canónico, sin ningún I/O de storage en el
repositorio. El cierre no-effect sólo se apoya en la historia inmutable del
journal y proyecta únicamente el item; no existe setter público por objeto.

Dos ayudantes read-only, `ReconciliationEventValidator` y
`CandidateManifestReader`, comparten una sola regla de validación semántica
entre la inspección D2-A y el repositorio de mutación. Toda resolución exige un
`attempt_started` semánticamente válido; un puntero durable que no resuelve a
un evento válido del tipo, parent y procedencia esperados se informa como
inválido e inconsistente en la inspección read-only, nunca como un simple
fingerprint. La igualdad entre la proyección del item y la de cada objeto se
comprueba sobre los identificadores durables de evento, nunca sobre el
fingerprint truncado, que sigue siendo sólo metadato de presentación. La
procedencia de un evento está anclada al hash de identidad de storage durable
del run: un inicio y una resolución corrompidos de forma coherente entre sí,
pero discordantes con su run, fallan cerrado. Los metadatos de candidato deben
corresponder a la clasificación de preflight antes de cerrar un item por
no-effect, y cualquier `reconciliation_event_id` no nulo en un run deja fuera
de alcance las cuatro operaciones B2, porque el cierre de run pertenece a
D2-B3.

Los hechos de APPLY y los checkpoints permanecen inmutables y
`ApplyJournal::recoveryBarrier()` conserva exactamente sus cuatro predicados
históricos: ninguna proyección B2 despeja todavía un bloqueo. No hay llamador
operacional: ningún comando, endpoint, job o provider alcanza estas APIs. No
hay cleanup, borrado, ausencia confirmada ni cierre de run.

En staging y producción, antes del despliegue, runs/items/objects/events y los
cinco conteos de proyecciones no nulas eran cero; `--execute` siguió rechazado
con exit 2; la inspección read-only terminó con exit 0, observó
`recoveryBarrier()=clear`, mostró cero runs activos, items sin terminar y
objetos no resueltos, y declaró cero mutaciones de journal y cero
escrituras/borrados de storage. Después del despliegue, los nueve conteos eran
idénticos a los de antes en ambos entornos. La aceptación fue deliberadamente
no mutante: no se invocó ninguna operación de `ReconciliationJournal`, porque
D2-B2 carece de llamador operacional.

Validación local: focales 280 tests / 3.216 aserciones y suite backend completa
1.549 tests / 15.550 aserciones, ambas con exit 0; `php -l`, Pint sobre los
archivos afectados y `git diff --check` en PASS. La auditoría humana exacta del
diff dio PASS.

D2-B3 es el siguiente bloque: cierre tardío de run, Barrier V2 y guards de
APPLY frente a proyecciones reconciliadas. Hereda sin cambios las fronteras de
seguridad de B2: `recoveryBarrier()` sigue sin alterarse hasta que B3 la
extienda explícitamente, las proyecciones B2 no clasifican como resolución
hasta entonces, y D2-C (observación/revalidación de storage para construir la
evidencia forward) sigue siendo trabajo futuro no iniciado. P1.D.2 y P1.D
permanecen abiertos.

## Documentos de referencia

- [31-responsive-backfill-foundation.md](31-responsive-backfill-foundation.md) — fundación de lectura, inspección y preflight (P1.D.1A).
- [32-responsive-backfill-safety.md](32-responsive-backfill-safety.md) — journal, identidad, lock, mantenimiento y escritura exclusiva (P1.D.1B).
- [33-responsive-backfill-runner.md](33-responsive-backfill-runner.md) — dry-run y wiring CLI APPLY aceptados hasta producción.
- [34-responsive-backfill-apply-design.md](34-responsive-backfill-apply-design.md) — diseño aprobado de APPLY; B1/B2/B3-A/B3-B y B3 aceptados hasta producción.
- [35-responsive-backfill-reconciliation.md](35-responsive-backfill-reconciliation.md) — D2-A, D2-B1 y D2-B2 aceptados hasta producción; inspección read-only, esquema híbrido, repositorio de mutación item-atomic y límites de la reconciliación futura.

## Invariante de traspaso

Sólo se arranca otro agente de implementación desde un checkpoint limpio y commiteado.

No se traspasa un bloque a medio implementar sin checkpoint seguro explícito y decisión del usuario. Las reglas completas están en `/AGENTS.md`.
