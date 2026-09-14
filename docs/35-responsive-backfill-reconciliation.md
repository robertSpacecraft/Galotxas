# Reconciliación de backfill responsive — P1.D.2

> **D2-A COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-B1 COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-B2 COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-B3 COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-C1 COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-C2 COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-C3 COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **P1.D.2 CERRADO HASTA PRODUCCIÓN / D3 ACEPTADO EN STAGING / P1.D COMPLETADO.**
> P1.D.1C-B3 permanece aceptado hasta producción. D3 ejecutó exactamente un
> APPLY real acotado en staging; no se ejecutó APPLY ni reconciliación mutante
> en producción.

## Aceptación de D2-A hasta producción

El commit `b4f68144258900764ccf5790f3a09e4aa1e04030` está desplegado y
aceptado en staging y producción. El CLI read-only existe en ambos entornos con
los selectores `--run`, `--item`, `--object`, `--after-id` y `--limit`.
`--execute` no está soportado y fue rechazado con exit 2; también se rechazó con
exit 2 el uso inválido de `--limit` con un selector de item. El mismo contrato
invalida `--limit` con un selector de objeto.

La aceptación fue estrictamente no mutante. En ambos entornos el journal tenía
runs/items/objects `0/0/0` antes y después. La inspección global acotada observó
la barrera de recuperación en `clear`, no encontró runs activos, items sin
terminar ni objetos no resueltos, declaró cero mutaciones de journal y cero
escrituras/borrados de storage, y terminó con exit 0. No se ejecutó APPLY
operacional o bajo mantenimiento, reconciliación mutante ni publicación de
storage, y no se creó evidencia artificial para la aceptación.

## Aceptación de D2-B1 hasta producción

El commit `ad6334b60415ceab3b1658cc700e842e52183e9f` está desplegado y
aceptado en staging y producción. El despliegue de código en Railway no ejecutó
la migración Laravel; en cada entorno se aplicó exclusivamente:

```text
php artisan migrate \
  --path=database/migrations/2026_09_12_000000_add_media_backfill_reconciliation_representation.php \
  --force
```

No se usó migrate general, rollback ni fresh. Esta es una nota operativa sobre
la instalación del esquema, no una modificación de la arquitectura de la
aplicación.

Después de instalarla, ambos entornos tenían la tabla de eventos y las cinco
proyecciones B1. Antes y después de la aceptación, los conteos de
runs/items/objects/events eran `0/0/0/0` y cada conteo de proyección no nula era
cero. `--execute` siguió sin soporte y fue rechazado con exit 2. La inspección
read-only terminó con exit 0, observó `recoveryBarrier()=clear`, mostró cero runs
activos, items sin terminar y objetos no resueltos, y declaró cero mutaciones de
journal y cero escrituras/borrados de storage. La aceptación posterior a la
instalación del esquema fue, por tanto, estrictamente no mutante.

## Aceptación de D2-B2 hasta producción

El commit `64af3f2358afdaad08ac34bfe8d758121d54711d` está desplegado y
aceptado en staging y producción. Añade el repositorio interno de mutación
`ReconciliationJournal` sobre el esquema B1 ya instalado; no requiere ni
ejecuta ninguna migración propia.

En cada entorno, antes del despliegue, runs/items/objects/events y los cinco
conteos de proyecciones no nulas (puntero de run, resultado de item, puntero
de item, resolución de objeto, puntero de objeto) eran cero. `--execute`
continuó rechazado con exit 2. La inspección read-only terminó con exit 0,
observó `recoveryBarrier()=clear`, mostró cero runs activos, items sin
terminar y objetos no resueltos, y declaró cero mutaciones de journal y cero
escrituras/borrados de storage. Después del despliegue, los nueve conteos
anteriores eran idénticos a los de antes en ambos entornos.

La aceptación fue deliberadamente no mutante: no se invocó ninguna de las
cuatro operaciones de `ReconciliationJournal` en staging ni en producción,
porque D2-B2 no tiene llamador operacional y el smoke se limitó al mismo
comando read-only ya aceptado en D2-A/D2-B1. Esto acredita despliegue y
disponibilidad de las clases, no ejecución de reconciliación real.

## Alcance de D2-A

D2-A aporta exclusivamente inspección read-only de la evidencia ya persistida
por APPLY. No resuelve estados, no continúa runs, no avanza checkpoints y no
publica ni elimina objetos. El flujo APPLY existente queda sin cambios.

La implementación se separa en:

- lectores acotados y deterministas del journal sobre la conexión de escritura;
- observación puntual mediante `fileExists`, `size` y `readStream` de cada clave
  exacta presente en el plan journalizado;
- clasificación tipada e inmutable en niveles objeto, item y run;
- comando independiente `media:responsive-backfill-reconcile`.

No se enumeran buckets o directorios y no se infiere ownership por forma de
path, namespace ni igualdad de contenido. No se muestra la clave: el CLI emite
únicamente un fingerprint SHA-256 truncado. ETag y version ID sólo se exponen
como indicadores `yes/no`, nunca como valores.

## Lecturas de journal

`ApplyJournal` añade solamente APIs read-only:

- `itemsForRun(runId, afterId, limit)`;
- `objectsForItem(itemId, afterId, limit)`;
- `allUnfinishedItems(afterId, limit)`;
- `unresolvedObjectsForRun(runId, afterId, limit)`;
- `runEvidenceCounts(runId)`.

Las páginas ordenan por ID ascendente, aceptan como máximo 1000 filas y usan
`useWritePdo()` para conservar la semántica read-after-write. El agregado de run
cuenta items totales/no terminados y objetos con escrituras ambiguas o cleanup
pendiente. No existe una API nueva de mutación ni una migración D2-A.

## Observación y atribución

La observación actual de un objeto tiene exactamente cuatro resultados:

| Observación | Evidencia |
| --- | --- |
| `absent_now` | La clave exacta no existe en el backend observado ahora. |
| `expected_content_present` | Tamaño, SHA-256, tipo y estructura/descriptor esperados coinciden. |
| `different_content_present` | La clave existe, pero alguna comparación verificable difiere. |
| `unreadable` | No fue posible obtener evidencia fiable; nunca se interpreta como ausencia. |

`absent_now` es observacional y no resolutivo. Esto es especialmente importante
para intentos S3 antiguos sin identidad inmutable: una ausencia actual no prueba
que el efecto nunca existió. De igual modo, contenido exacto actual no prueba
que lo crease este intento.

La atribución conserva exclusivamente hechos durables:
`not_dispatched`, `created_receipt`, `rejected_collision`,
`failed_without_write`, `attempt_ambiguous` e `inconsistent_journal`.
Un recibo terminal `failed` sigue separado de una colisión `rejected`.

La clasificación combinada distingue intent/unknown ausente, exacto, diferente
o ilegible; created exacto, desaparecido, diferente o ilegible; colisión y fallo
sin escritura; y cleanup pending/failed/unknown presente, ausente ahora o
ilegible. Los nombres con `absent_now` no autorizan transición alguna y siguen
bloqueando cuando el estado durable es `intent`, `unknown` o cleanup no resuelto.

## Clasificación de item y run

Un item sólo se presenta como
`storage_set_exact_domain_revalidation_pending_d2_c` cuando el manifest
candidato es válido, existe una fila exacta para el manifest y para cada
variante, todos los objetos coinciden en bytes/metadata, no hay cleanup pendiente
y no existe evidencia interna contradictoria de parent o plan. La atribución
histórica no restringe esta clasificación funcional: `not_dispatched`,
`created_receipt`, `rejected_collision`, `failed_without_write` y
`attempt_ambiguous` se conservan sin alteración y ninguna implica ownership por
la igualdad actual. Este resultado es sólo un candidato read-only: el histórico,
incluido `publication_unknown`, permanece sin cambios. C1 aporta la revalidación
exacta de referencia, owner live y master, pero D2-A no la invoca y la resolución
operacional permanece pendiente del coordinador C2.

Los demás resultados de item son `no_publication_observed_now`,
`cleanup_attention_candidate`, `ambiguous_blocked` e
`internally_inconsistent`. Un manifest exacto con una variante ausente o
diferente nunca es candidato forward.

Los runs informan selección/checkpoint sin modificarlos, conteos exactos de
evidencia durable y flags combinables: `clean_terminal`,
`active_no_other_blocker`, `has_unfinished_items`, `has_ambiguous_writes`,
`has_cleanup_attention`, `storage_identity_mismatch` e `inconsistent`. El estado
global de `recoveryBarrier()` se etiqueta como observación puntual y D2-A no lo
altera. Cada item debe pertenecer al dominio seleccionado por su run y satisfacer
`entity_id > after_id` y `entity_id <= upper_bound`; además, el total de items no
puede superar el `limit` durable del run. Una relación imposible se clasifica
como inconsistente. Esta validación de parent se aplica uniformemente a las
inspecciones de item, objeto y resumen global: si falla, la clasificación
propaga `inconsistent` de forma fail-closed aunque una observación puntual del
objeto pueda seguir siendo legible.

El límite de detalle de run se aplica antes de inspeccionar children y observar
storage. Si quedan items fuera de la página, el flag `details_truncated` y el
campo `itemsTruncated` lo declaran explícitamente. Esta truncación intencional no
es inconsistencia ni causa exit 7, pero impide afirmar `clean_terminal` o
`active_no_other_blocker` basándose en una vista parcial. Los conteos durables
agregados siguen cubriendo el run completo.

## Contrato CLI

```text
php artisan media:responsive-backfill-reconcile
php artisan media:responsive-backfill-reconcile --run=<uuid> [--limit=1..1000]
php artisan media:responsive-backfill-reconcile --item=<id>
php artisan media:responsive-backfill-reconcile --object=<id>
```

Sin selector, `--limit` acota cada sección de runs activos, items no terminados
y objetos no resueltos. Con `--run`, acota el número de items que realmente se
leen e inspeccionan, no sólo su presentación. `--limit` es inválido con
`--item` o `--object`. `--after-id` sólo pagina la sección de objetos no
resueltos del resumen. Se admite exactamente uno de `--run`, `--item` u
`--object`; el UUID debe estar en forma canónica minúscula y los enteros son
base 10 dentro de rango.

Los exits de D2-A son:

| Exit | Significado |
| --- | --- |
| `0` | Inspección completada; puede seguir mostrando evidencia durable no resuelta. |
| `2` | Selector u opciones inválidos. |
| `7` | Evidencia inconsistente/ilegible o identidad de storage no concordante impide una clasificación fiable. |

No existen `--execute`, `--cleanup`, `--finalize`, `--resume` ni `--apply`. El
comando no requiere mantenimiento ni lock porque carece de capacidad de
mutación.

## Efecto cero y límites

Las pruebas comparan antes/después todas las filas del journal, incluidos
timestamps y checkpoint, el estado de la barrera y hashes de todos los objetos
locales, incluido un master no relacionado. También limitan la captura SQL a
las tablas del journal y comprueban que la inspección sólo emite SELECT. Los
mocks de observación sólo admiten operaciones sobre una clave exacta y rechazan
listing, escritura y borrado.

D2-A no ofrece cleanup automático local ni cleanup S3; no borra manifests,
variantes, masters preservados u objetos ajenos. No incorporó estados durables
de reconciliación y no puede despejar ningún predicado de la barrera. La
igualdad de contenido es evidencia funcional actual, no prueba suficiente para
un borrado futuro.

## D2-B1: esquema durable sin API de mutación

D2-B1 añade una única migración MariaDB aditiva posterior al journal APPLY. El
modelo es híbrido:

- `media_backfill_reconciliation_events` conserva eventos conceptualmente
  append-only con UUID de evento e intento, parent run/item, tipo cerrado en
  PHP pero `VARCHAR` en MariaDB, versión y hash de evidencia, hash de identidad
  de storage, modo de backend, revisión de código nullable, JSON de evidencia y
  `created_at`; no tiene `updated_at`;
- `media_backfill_runs.reconciliation_event_id` es un puntero nullable;
- `media_backfill_items.reconciliation_result` admite en B1
  `forward_accepted` o `closed_no_effect` y su puntero de evento nullable;
- `media_backfill_objects.reconciliation_resolution` admite en B1 únicamente
  `forward_retained` y su puntero de evento nullable.

Todas las proyecciones tienen default `NULL`. `NULL` significa que no existe
una resolución D2 aceptada, también para filas pre-D2. Un valor desconocido o
un estado/puntero incoherente se representa como inválido/no resuelto y falla
cerrado; nunca se interpreta como resolución. Los FKs usan RESTRICT en DELETE y
UPDATE. El ciclo entre parents y eventos es deliberado y seguro para el futuro:
el evento referenciará una fila existente y, dentro de una transacción
soportada, la proyección nullable podrá apuntar después al evento.

La migración sólo puede revertirse físicamente mientras el esquema siga vacío:
`down()` comprueba, antes de cualquier DDL, que no haya eventos ni ninguna
proyección no nula. Si existe procedencia durable, aborta el rollback. D2-B1 no
ofrece código de aplicación para insertar eventos o actualizar proyecciones;
las escrituras directas existen sólo en tests MariaDB aislados para verificar
constraints, parsing y el guard de rollback.

Los hechos originales `run.state`, `item.phase`, `item.apply_result`,
`object.write_state`, `object.create_state`, `object.cleanup_state`, recibos y
checkpoints continúan siendo historia APPLY autoritativa e inmutable para D2.
El cierre tardío `active → interrupted` con evento explícito queda reservado a
D2-B3. Los checkpoints quedan permanentemente fuera de las mutaciones D2.

## Dimensión funcional exacta de D2-B1

El report de item expone adicionalmente `functionalStorageSetExact`. Es una
observación read-only, no una proyección ni una resolución, y no acredita
ownership. Sólo es true si el manifest candidato es válido e internamente
consistente, parent run/item y selección son válidos, la identidad de storage
coincide, existe exactamente una fila de manifest y una por cada variante sin
faltantes, duplicados ni extras, todas las relaciones key/tamaño/MIME/descriptor
son válidas y todas las claves exactas contienen los bytes esperados.

La atribución histórica (`not_dispatched`, `attempt_ambiguous`, recibo created,
rejected o failed) no restringe esta dimensión. Tampoco exige
`cleanup_state=not_required`: `pending`, `failed` y `unknown` pueden coexistir
con `functionalStorageSetExact=true` si el conjunto deseado está completo y
exacto ahora. `deleted`, ausencia, contenido diferente, lectura imposible,
identidad distinta o cualquier inconsistencia hacen que sea false. La
clasificación D2-A conserva su precedencia histórica: la presencia de cleanup
attention sigue produciendo `cleanup_attention_candidate`; sin esa atención,
el caso exacto puede seguir siendo
`storage_set_exact_domain_revalidation_pending_d2_c`.

El CLI muestra los estados históricos, la observación/clasificación actual y
la proyección durable por separado. Para el puntero sólo muestra ausencia,
invalidez o un fingerprint SHA-256 truncado; no vuelca UUIDs de evento,
`evidence_json`, claves, ETags, version IDs ni configuración de storage.

## Barrera sin cambios en D2-B1

`recoveryBarrier()` conserva exactamente sus predicados aceptados: run activo,
item no finished, escritura intent/unknown o cleanup pending/failed/unknown.
Ninguna proyección B1 los sustituye ni los debilita, incluso si se inyecta
manualmente un valor no nulo en tests. La consulta sigue sin I/O de storage.
Barrier V2 queda reservado a D2-B3, después de disponer de APIs soportadas y
proyecciones alcanzables por código.

## Validación aceptada de D2-B1

La validación focal MariaDB cubre journal/rango, esquema/rollback,
clasificadores, representación durable e inspector: 120 tests y 1.156
aserciones, exit 0. La suite backend completa pasa una vez con 1.460 tests y
14.188 aserciones, exit 0. También pasan la sintaxis PHP de todos los archivos
PHP afectados, Pint limitado a esos archivos y `git diff --check`.

## D2-B2: APIs internas item-atomic de resolución

> **COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> El commit `64af3f2358afdaad08ac34bfe8d758121d54711d` está desplegado en
> staging y producción. Sigue sin tener llamador operacional y no se ha
> ejecutado ninguna reconciliación real en ningún entorno.

D2-B2 añade el repositorio interno de mutación
`Backfill/Reconciliation/ReconciliationJournal` sobre el esquema aceptado en B1.
No amplía `ApplyJournal` ni introduce setters genéricos. Expone exactamente
cuatro operaciones públicas:

| Operación | Efecto durable |
| --- | --- |
| `beginRunReconciliation()` | inserta `attempt_started` para un run exacto; ninguna proyección |
| `recordBlockedAttempt()` | inserta `attempt_blocked` con una razón cerrada; ninguna proyección |
| `recordForwardItemResolution()` | inserta `item_forward_accepted` y proyecta item y todos sus objetos |
| `recordNoEffectItemResolution()` | inserta `item_no_effect_closed` y proyecta únicamente el item |

No existen `closeReconciledRun()`, `recordCleanupResolution()`, resolución
pública por objeto, ausencia confirmada, primitiva de borrado ni camino CLI.

### Contrato operativo del llamador

Todas las operaciones exigen un `ReconciliationContext` con el `attempt_id`
canónico, el handle del lock exclusivo de APPLY, la identidad de storage, el
modo de backend y una revisión de código opcional de 40 hex. El repositorio
**no adquiere ni libera el lock**: el futuro coordinador es dueño de su ciclo de
vida y el repositorio sólo verifica propiedad.

Antes de cualquier escritura se comprueban conexión MariaDB, ausencia de
transacción ambiente del llamador, coincidencia de `StorageIdentity::current()`
con el contexto, con el handle del lock y con el `storage_identity_hash` durable
del run, coincidencia del modo de backend, mantenimiento según la semántica
vigente del guard y propiedad del lock. La propiedad del lock y el mantenimiento
se vuelven a comprobar inmediatamente antes del commit.

### Validación semántica compartida

`ReconciliationEventValidator` y `CandidateManifestReader` son dos ayudantes
read-only sin I/O de storage ni mutación, usados tanto por la inspección D2-A
como por el repositorio de mutación, de modo que lectura y escritura aplican una
sola regla.

El validador de eventos comprueba, para cualquier fila: identificadores
canónicos de evento, intento y run; tipo conocido; `evidence_version`
soportada; `storage_identity_hash` y `evidence_sha256` de 64 hex;
`backend_mode` conocido; `code_revision` nula o de 40 hex; `evidence_json`
acotado cuyo SHA-256 coincide exactamente con `evidence_sha256`; y un sobre v1
canónico cuyo conjunto y orden de claves, `kind`, `observed_at`, hash de
identidad y modo de backend concuerdan con las columnas escalares. El cuerpo
específico de cada tipo también se valida, incluido el ámbito de item: un
`attempt_started` o un cierre de run no puede tener `item_id`, y una resolución
de item lo exige. La columna `evidence_json` es `json` en MariaDB, de modo que
el motor ya rechaza payloads sintácticamente inválidos.

Para un puntero durable, el validador exige además evento existente, tipo
compatible con la proyección, parentesco exacto de run e item y procedencia: el
intento del evento debe tener exactamente un `attempt_started` válido del mismo
run, sin abarcar dos runs, y con identidad de storage, modo de backend y
revisión de código concordantes. Un UUID sintácticamente válido ha dejado de ser
suficiente.

Esa procedencia está anclada al hash de identidad de storage **durable del
run**: quien valida un puntero pasa explícitamente
`media_backfill_runs.storage_identity_hash` y tanto el evento como su
`attempt_started` deben coincidir con él. Un par corrupto de forma coherente,
que concuerda consigo mismo pero no con su run, falla cerrado; una identidad
durable malformada también. Esto es independiente de la dimensión ya existente
de deriva respecto a la identidad actual del entorno, que se sigue informando
por separado. La revisión de código del evento no tiene que coincidir con la del
run APPLY original, porque la reconciliación puede ejecutarse con código
posterior; sólo debe ser sintácticamente válida y concordante dentro del intento.
No se inventa una columna de modo de backend en el run: para el backend se
conserva la concordancia entre inicio y evento.

`CandidateManifestReader` conserva sin cambios la regla de coherencia de
candidato ya aceptada en D2-A y la comparte con el repositorio.

### Provenance obligatoria del intento

`recordBlockedAttempt()`, `recordForwardItemResolution()` y
`recordNoEffectItemResolution()` sólo continúan un intento cuyo
`attempt_started` es semánticamente válido. Un evento de inicio corrupto,
duplicado, retipado, con `item_id`, con sobre o hash de evidencia inválidos, con
identidad, backend o revisión discordantes, o perteneciente a otro run, no
autoriza ninguna escritura durable. La ausencia total de inicio es
`illegal_resolution`; un inicio presente pero inválido es
`inconsistent_journal`.

### Eventos append-only e idempotencia

La tabla de eventos sigue siendo append-only en aplicación: no hay método de
actualización ni de borrado. Cada evento se direcciona por `event_id` y
`attempt_id` canónicos suministrados por el llamador. Un `attempt_id` no puede
abarcar dos runs y sólo admite un `attempt_started`; blocked, forward y
no-effect exigen que ese `attempt_started` ya exista.

- `event_id` ausente: se inserta sólo tras cumplirse todas las precondiciones.
- `event_id` existente con todos los campos durables y la evidencia idénticos,
  y proyecciones ya iguales al resultado esperado: replay idempotente, sin
  escritura.
- Cualquier divergencia de campo o evidencia: `replay_conflict`.
- Otro evento sobre una proyección ya terminal: `replay_conflict`.
- Evento sin proyecciones, proyección sin evento, punteros cruzados, tipo de
  evento incompatible o conjunto parcial: `inconsistent_journal`. El estado
  parcial artificial se rechaza, nunca se repara en silencio.

La evidencia se canonicaliza en PHP con orden de claves fijo antes de
codificarse; `evidence_sha256` cubre exactamente los bytes almacenados en
`evidence_json`. No se depende del orden de claves que devuelva MariaDB. El
payload se limita a 16.384 bytes y `evidence_version` es 1.

### Evidencia v1 de aceptación forward

`ForwardItemEvidence` es el snapshot tipado que C1 ya puede construir tras
observar y revalidar; C2 será su consumidor operacional. El repositorio **no
hace I/O de storage**: valida la evidencia contra los hechos inmutables del
journal.

La evidencia atestigua momento de observación, hash de identidad de storage,
modo de backend, hash de la identidad de referencia del dominio, hash de la
master key, SHA-256 actual de la master, SHA-256 del manifest candidato,
revalidación de owner vivo único con dominio y entidad, validación estructural
del manifest candidato, comprobación de clave exacta de que no existe un target
canónico inesperado y una entrada por cada objeto planificado, en orden
ascendente de ID, con SHA-256 y tamaño observados, MIME observado y las
atestaciones de descriptor y estructura.

No se duplican en la evidencia claves de objeto, SHA/tamaño/MIME esperados ni el
manifest completo: ya son hechos durables. El repositorio comprueba que el
conjunto de IDs de evidencia coincide exactamente con las filas del item, sin
faltantes, extras ni duplicados; que cada SHA y tamaño observados igualan los
esperados; que todas las atestaciones son verdaderas; y que el plan journalizado
es internamente consistente. Un booleano de conjunto exacto suministrado por el
llamador no se acepta como prueba.

### Transición forward item-atomic

Dentro de una única transacción corta se bloquea run, después item y después
todos los objetos en orden ascendente de ID; se revalidan las precondiciones; se
inserta el evento; se proyectan **todos** los objetos a `forward_retained` y el
item a `forward_accepted` con ese mismo evento; se recomprueba la propiedad del
lock y se hace commit. Un fallo en cualquier punto revierte evento y
proyecciones a la vez.

La aceptación forward no exige una atribución histórica concreta: `planned`,
`intent`, `unknown`, recibo `created`, colisión `rejected` y fallo conocido
`failed` pueden coexistir con ella, igual que cleanup `pending`, `failed` o
`unknown`. `cleanup_state=deleted` la hace ilegal. También se exige el plan
completo: manifest más cada variante, sin filas faltantes, duplicadas o ajenas.

### Coherencia de candidato y preflight

Antes de cualquier resolución, el item debe presentar exactamente la forma que
produce un snapshot válido para su clasificación. Un item
`legacy_backfillable` exige candidato completo y coherente: hash de master,
hash del JSON candidato, `source_sha256` y un manifest que parsea y concuerda
con dominio, perfil, política y contrato de claves. Una clasificación no
candidata no puede llevar JSON, hash de candidato ni `source_sha256`, sólo
admite fase `inspected` o `finished` y no puede tener filas de objeto, porque
planificar un objeto exige un candidato coherente. `writing` sólo es alcanzable
con al menos un objeto despachado. La correspondencia entre `master_key` y
`manifest_key` se comprueba siempre.

Esto cierra el hueco de un item `legacy_backfillable` manipulado al que se le
retira el candidato y se le vacían los objetos: sin candidato coherente no hay
cierre no-effect. Un item candidato legítimo que quedó en `inspected` sin
objetos planificados sí puede cerrarse: cero objetos es válido, metadatos
inmutables corruptos no.

### Cierre no-effect

`recordNoEffectItemResolution()` sólo cierra el bloqueo de fase del item cuando
la historia durable prueba por sí misma que no pudo existir efecto de storage:
item no `finished`, `apply_result` nulo y, para cada fila de objeto, atribución
`not_dispatched`, `rejected_collision` o `failed_without_write` con cleanup
`not_required`, sin recibo de creación, ETag, version ID ni confirmación de
escritura. Un item sin objetos planificados también cualifica. Cualquier
`intent`, `unknown`, `created` o cleanup no `not_required` lo rechaza. Sólo se
escribe la proyección del item; los objetos permanecen en `NULL`.

### Puntero de reconciliación del run

El cierre tardío de run pertenece a D2-B3, de modo que B2 adopta la variante
más estricta compatible con estos documentos: cualquier
`runs.reconciliation_event_id` no nulo deja fuera de alcance las cuatro
operaciones, incluso si nombra un evento real de ese mismo run. B2 no crea ni
modifica proyecciones de run. La inspección read-only, en cambio, sí clasifica
el puntero: sólo es válido si nombra un `run_closed_after_reconciliation`
estructuralmente válido, sin `item_id` y con procedencia concordante; un puntero
a un `attempt_started` se informa como inválido e inconsistente.

### Enlaces inválidos en la inspección read-only

La representación read-only ya no muestra un puntero sin comprobarlo. Un enlace
inexistente, de otro tipo, de otro run, de otro item, con hash o sobre de
evidencia corruptos, o cuya procedencia de intento falta o es inválida, se
clasifica como inválido e inconsistente, propaga `internally_inconsistent`,
anula `functionalStorageSetExact` y produce exit 7. También se validan las
reglas cruzadas: una aceptación forward exige que item y **todos** sus objetos
nombren el mismo evento válido, un cierre no-effect no admite ninguna proyección
de objeto y ningún objeto puede resolverse sin resolución de su item. Esa
igualdad se comprueba sobre los identificadores durables de las filas del
journal. El fingerprint SHA-256 truncado que muestran los reports y el CLI es
exclusivamente metadato de presentación y nunca acredita identidad durable. El CLI
sigue siendo read-only, no adquiere lock ni exige mantenimiento, y
`recoveryBarrier()` no cambia.

### Inmutabilidad y límites

Las columnas de APPLY no se tocan: estados de escritura, creación y cleanup,
recibos, fase, resultado, `finished_at`, resumen, error y heartbeat del run y
checkpoints permanecen byte a byte idénticos, incluido `updated_at`. Las
pruebas comparan todas las columnas históricas antes y después de cada
resolución aceptada.

`ApplyJournal::recoveryBarrier()` permanece exactamente igual: run activo, item
no `finished`, escritura `intent`/`unknown` o cleanup `pending`/`failed`/
`unknown`. Una proyección B2 válida no despeja ningún predicado; Barrier V2, el
cierre tardío `active → interrupted` y los guards de APPLY frente a proyecciones
reconciliadas siguen reservados a D2-B3.

Los errores usan la enumeración cerrada `ReconciliationError`
(`invalid_input`, `maintenance_required`, `lock_lost`, `identity_mismatch`,
`unsupported_storage`, `ambient_transaction`, `journal_unavailable`,
`inconsistent_journal`, `evidence_mismatch`, `replay_conflict`,
`illegal_resolution`) y no encadenan excepciones previas ni exponen claves,
ETags, version IDs, evidencia cruda ni configuración de storage.

D2-B2 no tiene llamador operacional: ningún comando, controlador, ruta, job ni
provider lo referencia, y las pruebas lo verifican recorriendo esas rutas del
código. Tampoco observa, escribe o borra storage, no hace cleanup, no acredita
ausencia confirmada y no cierra runs. `ObjectEvidenceClassifier` expone ahora
`attributionFor()` para reutilizar en B2 la validación de estados durables ya
aceptada en D2-A, sin cambiar su clasificación externa.

### Validación local de D2-B2

Focales de journal, rango, esquema, clasificadores, representación, inspector y
mutación: 280 tests y 3.216 aserciones, exit 0. Suite backend completa: 1.549
tests y 15.550 aserciones, exit 0. `php -l` sobre todos los PHP afectados, Pint
limitado a esos archivos y `git diff --check`: PASS.

## D2-B3: cierre tardío, Barrier V2 y guards de APPLY

> **COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> El commit `255688b914a6659136c73bb74a0eb6601cf941fe` está desplegado y
> aceptado en staging y producción.

`ReconciliationStateValidator` centraliza una única lectura semántica DB-only
de los hechos APPLY, las proyecciones y sus eventos. La usan Barrier V2, las
precondiciones de cierre y la validación read-only del puntero de run. Valida
selección, bounds y checkpoint; parentage y rango de todos los descendientes;
coherencia de fase/resultado/candidato y estados write/create/receipt/cleanup;
pares completos de proyección; tipo, parentage, intento, identidad, backend,
revisión, hash y cuerpo exacto del evento. No usa fingerprints para igualdad y
no observa ni muta storage.

### Overrides exactos de Barrier V2

Los cuatro predicados históricos siguen siendo la base:

| Predicado histórico | Único override B3 |
| --- | --- |
| run `active` | ninguno; sólo deja de existir tras el cierre atómico del run |
| item no `finished` | `forward_accepted` o `closed_no_effect` válido del item exacto |
| write `intent`/`unknown` | `forward_retained` válido bajo el mismo evento `forward_accepted` exacto del item |
| cleanup `pending`/`failed`/`unknown` | ninguno |

`attempt_started`, `attempt_blocked` y un `forward_retained` aislado no despejan
nada. `closed_no_effect` no despeja objetos; forward no despeja cleanup. Los
`NULL` pre-D2 despejan cero predicados bloqueantes. Un valor desconocido, par
parcial, evento ausente o de tipo/padre/run incorrecto, procedencia de intento
inválida, identidad/backend/revisión discordante, IDs cruzados, conjunto
forward parcial o evidencia stale/corrupta bloquea. Un `NULL` ordinario sobre
historia que ya no satisface ningún predicado bloqueante sigue siendo normal.

### Cierre tardío del run

`ReconciliationJournal` añade exactamente una quinta operación pública:

```text
closeRunAfterReconciliation(runId, eventId, observedAt, context)
```

Sin payload ni contadores del llamador, bloquea el run, todos sus items por ID
ascendente y después todos sus objetos por ID ascendente. Exige MariaDB, cero
transacción ambiente, mantenimiento, lock propio, identidad actual/contexto/
lock/run concordante, un único `attempt_started` válido, modo APPLY, estado
`active`, `finished_at` y puntero de run nulos, selección y descendientes
coherentes, todo blocker de item/write exactamente resuelto y cero cleanup
blockers. Otros runs no intervienen en el cierre de este run.

El evento v1 `run_closed_after_reconciliation` tiene, en orden canónico:
`v`, `kind`, `observed_at`, `storage_identity_hash`, `backend_mode`,
`from_state`, `to_state`, `items_total`, `objects_total`,
`item_blockers_resolved`, `write_blockers_resolved`,
`cleanup_blockers_remaining` y `resolution_snapshot_sha256`. Los estados son
siempre `active → interrupted`, los conteos se derivan de las filas bloqueadas,
cleanup es cero y el digest es SHA-256 sobre un preimage JSON versionado y de
orden fijo que cubre las identidades/selección/checkpoint usadas, todos los
hechos descendientes relevantes, punteros exactos y eventos/procedencia
referenciados. El mismo helper construye el digest para escritura, replay y
validación.

Evento, `run.finished_at` y `run.updated_at` comparten exactamente el timestamp
canónico observado. En una transacción se inserta el evento append-only y sólo
se cambian `state`, `finished_at`, `reconciliation_event_id` y `updated_at` del
run exacto. Nunca se produce `completed` o `failed`; resumen, error, inicio,
heartbeat, checkpoints y todos los hechos APPLY de items/objetos permanecen
intactos. Inmediatamente antes del commit se recomprueban mantenimiento y lock.

Replay con el mismo evento, intento, evidencia, contexto y proyección terminal
es no-op y devuelve `replayed=true`. Evidencia/contexto divergente o puntero
competidor es `replay_conflict`; evento/proyección parcial es
`inconsistent_journal`; un terminal sin la proyección exacta es
`illegal_resolution`. Nunca se reparan estados parciales. El puntero del run
sólo puede nombrar este tipo de evento.

### Congelación de APPLY y límites

El helper bloqueado común de `ApplyJournal` rechaza el run exacto con
`SafetyError::ReconciliationRequired` si existe cualquier evento o cualquier
proyección de run/item/objeto. `ApplyItemPublisher` ejecuta además un guard
temprano antes de revalidar dominio o storage. El outcome APPLY se mapea de
forma explícita a `reconciliation_required`; la conducta no reconciliada
permanece igual y un nuevo APPLY independiente puede comenzar tras una historia
terminal reconciliada si Barrier V2 está clear.

B3 reutiliza sin cambios el esquema B1 y no añade migración. No implementa
cleanup, ausencia confirmada, setter genérico de objeto, observación o mutación
de storage, coordinador D2-C, comando, endpoint, job, provider, ruta ni frontend.
No se ha ejecutado ninguna reconciliación real.

### Validación local de D2-B3

El foco B3 (`BackfillReconciliationJournal`, `BackfillApplyJournal`,
`BackfillApplyItemPublisher`, `ResponsiveBackfillApply` y
`BackfillReconciliationInspector`) pasa con 278 tests y 3.529 aserciones. La
suite backend completa pasa con 1.594 tests y 15.925 aserciones. Ambas se
ejecutaron una vez con exit 0 mediante el runner Docker/MariaDB aislado; no se
usó SQLite. `php -l` sobre todos los PHP afectados, Pint limitado a esos
archivos, `git diff --check` y la auditoría humana del diff: PASS.

### Aceptación hasta producción de D2-B3

El commit exacto `255688b914a6659136c73bb74a0eb6601cf941fe`
(`feat(media): añadir Barrier V2 y cierre reconciliado`) se desplegó con estado
`SUCCESS` en Railway staging mediante
`85fb1c6c-d301-4974-b8b2-10a269aa199d` y en Railway producción mediante
`680ef57a-336f-457c-9b52-e7a704ad7206`. Ambos entornos usaron el driver
MariaDB y no requirieron migración para B3.

En staging y producción, los nueve conteos de runs/items/objects/eventos y
proyecciones no nulas fueron cero antes y después. Barrier V2 devolvió `clear`
con `BARRIER_EXIT=0` y la API pública de `ReconciliationJournal` presentó
exactamente, con `API_EXIT=0`:

- `beginRunReconciliation`;
- `closeRunAfterReconciliation`;
- `recordBlockedAttempt`;
- `recordForwardItemResolution`;
- `recordNoEffectItemResolution`.

En ambos entornos, `--execute` continuó sin soporte y fue rechazado con exit 2;
la reconciliación read-only terminó con exit 0; las mutaciones de journal y las
escrituras/borrados de storage fueron cero; y `recovery barrier modified` fue
`no`. No se autorizó ni ejecutó ninguna reconciliación mutante real.

## D2-C1: base de evidencia operacional exacta

> **COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> El commit `a73475e4289fcfb235cd3a15be323fe54744f825` está desplegado y
> aceptado en staging y producción. C1 es read-only y no hace alcanzables las
> mutaciones B2/B3.

D2-C se divide en tres bloques independientes: C1 aporta evidencia operacional
exacta; C2 compone el coordinador interno para un único run; C3 quedó reservado
para cablear un CLI mutante explícito. C1 no contiene coordinador, comando,
endpoint, job, provider, ruta ni llamador de `ReconciliationJournal`. Su API
pública sigue teniendo exactamente cinco operaciones:

- `beginRunReconciliation`;
- `closeRunAfterReconciliation`;
- `recordBlockedAttempt`;
- `recordForwardItemResolution`;
- `recordNoEffectItemResolution`.

### Gate de lectura y observación exacta

`StorageObservationCapability` compara la `StorageIdentity`, la topología
configurada y la instancia runtime real. Para local exige el adaptador Flysystem
local soportado y el mapping canónico de path; para S3 exige
`Illuminate\Filesystem\AwsS3V3Adapter`,
`League\Flysystem\AwsS3V3\AwsS3V3Adapter` y capacidad instalada de HEAD/GET.
Sólo acredita `fileExists`, tamaño y stream de lectura de clave exacta: no hace
probes de escritura, borrado, copia o listing, no expone configuración sensible
y falla cerrado ante identidad, configuración, adaptador o capacidad no
concordante.

`ExactObjectObserver` conserva las cuatro clasificaciones D2-A y añade el valor
tipado `ExactObjectObservation`: `absent_now`, `expected_content_present`,
`different_content_present` y `unreadable`. Sólo el caso exacto transporta el
SHA-256 y tamaño calculados desde los bytes acotados realmente leídos, el MIME
derivado de bytes/estructura y las atestaciones de descriptor y estructura. Los
demás estados no fabrican hechos observados. Los bytes, excepciones de storage,
credenciales y secretos no cruzan la frontera. ETag y VersionId no se usan como
prueba de contenido u ownership.

### Revalidación actual y análisis DB-only

`ManagedMediaCurrentStateValidator` reutiliza el registro canónico de
referencias para exigir entidad y referencia actuales exactas, identidad de
referencia canónica, un único owner vivo del dominio y entidad seleccionados,
master key sin deriva y compatibilidad de metadatos. La master se lee de forma
acotada y debe concordar en SHA-256, MIME, estructura y descriptor con la
historia y el candidato. Este camino no escribe dominio ni storage.

`ReconciliationStateValidator::analyzeItem()` reutiliza la misma semántica
interna DB-only de Barrier V2 y distingue historia ordinaria no bloqueante,
candidato no-effect, candidato forward, resolución forward/no-effect exacta y
estado inconsistente. Punteros parciales, cruzados, extranjeros o malformados
fallan cerrado. La atención de cleanup permanece separada y sigue bloqueando;
el análisis no modifica la barrera ni expone mutaciones.

### Construcción read-only de evidencia forward

`ForwardItemEvidenceBuilder` sólo devuelve el `ForwardItemEvidence` existente
de B2 tras validar parentage y selección, candidato y plan exactos, estado
actual de dominio/owner/master, cada objeto esperado y la ausencia puntual de
cualquier target canónico V1 fuera del candidato. Esa comprobación recorre un
universo finito de keys generadas por la aplicación y nunca usa listing. El
builder revalida los hechos durables y la capacidad actual después de observar,
pero no inserta eventos, cambia proyecciones ni cierra runs.

La fuente canónica del SHA esperado de cada variante es
`media_backfill_objects.expected_sha256`, calculado desde los bytes preparados
antes del dispatch histórico. `ManifestImage` no contiene ese SHA y no se
fabrica uno desde geometría, ETag o bytes actuales. El objeto manifest
planificado debe concordar además con el `candidate_manifest_sha256` durable.
La evidencia por objeto conserva los hechos realmente observados y se ordena
por ID durable ascendente. Cleanup `deleted` invalida forward; cleanup
`pending`, `failed` o `unknown` puede coexistir con evidencia forward, pero
permanece visible y bloqueante y C1 no lo despeja.

### Frontera no-effect y seguridad operacional pendiente

La elegibilidad no-effect de C1 se deriva sólo de hechos APPLY inmutables en DB
y ejecuta cero observaciones de storage. No consulta deriva actual de entidad,
owner o master y nunca convierte ausencia actual en prueba de no escritura.
Todo `intent`, `unknown`, `created`, recibo, confirmación de escritura o actividad
de cleanup la invalida. Los checkpoints y todos los hechos APPLY permanecen
inmutables; no existen cleanup, ausencia confirmada ni inferencia de ownership
por igualdad actual de bytes.

La base de observación S3 de C1 incluye un gate runtime validado para capacidad
de lectura exacta sobre el adaptador real; esto no afirma que staging o
producción hayan leído un objeto concreto ni autoriza una reconciliación forward
mutante en S3. C1 no resuelve el TOCTOU entre storage y MariaDB ni congela
writers externos. C2, descrito a continuación, aplica mantenimiento, advisory
lock, identidad y revalidación inmediata, pero rechaza forward porque el modelo
actual no puede demostrar el writer freeze global exigido.

### Validación y aceptación de D2-C1

La auditoría humana dio PASS sobre los 13 PHP exactos del bloque. La validación
local pasó con 328 tests / 3.645 aserciones focales y 1.614 tests / 16.224
aserciones en la suite backend completa, ambas con exit 0. `php -l` sobre los 13
PHP, Pint limitado a los afectados y `git diff --check` pasaron. No hubo
migración ni cambio de configuración y `ApplyItemPublisher` permaneció intacto.

Staging y producción aceptaron el mismo commit sobre MariaDB, modo `s3` y disco
`media_s3`. En ambos se comprobaron los adaptadores runtime Laravel/Flysystem y
el retorno correcto de `StorageObservationCapability::currentDisk()` con exit 0,
además de la resolución de los servicios C1, Barrier V2 puntualmente `clear`, la
API de cinco métodos y el comando read-only con exit 0. Los nueve conteos de
journal/proyecciones fueron cero antes y después, por lo que no se ejercitó GET
ni lectura exacta de ningún objeto en esos entornos. Hubo cero mutaciones de
journal, cero escrituras/borrados de storage y la barrera no cambió. No se
ejecutó reconciliación mutante real.

## D2-C2: coordinador interno exact-one-run

> **COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> El commit `36274f27029178af82ec6f5c686b8a9c2ce2a621`
> (`feat(media): añadir coordinador de reconciliación`) está desplegado y
> aceptado en staging y producción. Su parent es
> `881c84453d3f15481a01e0eabf200f4762054f16`.

C2 añade exclusivamente el límite interno:

```text
ReconciliationCoordinator::run(
    ReconciliationInvocation $invocation
): ReconciliationReport
```

Cada invocation recibe exactamente un UUID canónico de run. No admite selector
de item u objeto, mutation limit ni paginación que pueda cerrar un recorrido
parcial. Carga el run mediante write PDO y recorre el conjunto durable completo
por ID de item ascendente, con un máximo fail-closed de 1000 items. No mantiene
una transacción MariaDB global alrededor del recorrido o del I/O de storage.

Antes de progresar exige mantenimiento, identidad actual válida, modo/backend y
capability gate exactos, adquiere el mismo advisory lock de APPLY y demuestra
su ownership inmediatamente y en cada frontera de mutación. La identidad
actual, la durable del run y la del lock deben concordar. Revalida selección,
rango, checkpoints, parentage, hechos APPLY, eventos y proyecciones mediante los
validadores compartidos. Una pérdida de lock o una liberación incierta tras
progreso no puede devolver éxito limpio.

Si necesita trabajar, crea un nuevo `attempt_started` mediante
`beginRunReconciliation()`. Un retry obtiene otro `attempt_id` y otro
`event_id`, pero los descendientes ya resueltos sólo se omiten cuando la
validación semántica compartida acredita su proyección y evento exactos. El
primer blocker detiene el recorrido y puede añadir como máximo un
`attempt_blocked` saneado en esa invocation; el progreso item-atomic anterior
permanece durable. Historia, selección, parentage o proyecciones malformadas
fallan cerrado.

### No-effect DB-only

C2 hace operativa dentro del coordinador interno únicamente la rama no-effect.
Su elegibilidad deriva de forma exclusiva de los hechos APPLY inmutables en DB:
`not_dispatched`, `rejected_collision` o `failed_without_write` según la forma
exacta aceptada por el validador. No observa objetos ni usa el estado actual de
entidad, referencia, owner o master como prueba; la deriva actual de esos hechos
es deliberadamente irrelevante. La identidad y topología runtime globales sí
deben seguir concordando.

Antes de `recordNoEffectItemResolution()` se repiten mantenimiento, lock,
identidad, backend y capability gate, se recarga el run/item desde write PDO y
se confirma otra vez la elegibilidad DB-only. Un snapshot elegible sin objetos
planificados sigue soportado. Cualquier intent, unknown, created, recibo,
confirmación de escritura o actividad de cleanup lo invalida. No se modifican
los hechos APPLY ni checkpoints y no se introduce observación de storage.

### Forward rechazado fail-closed

La auditoría de writers C2 concluyó que el runtime actual no puede demostrar
una congelación global. Los writers normales del ciclo de vida pueden escribir
media administrada; mantenimiento bloquea tráfico HTTP normal nuevo, pero no
acredita la detención de peticiones en curso, invocaciones directas, workers,
servicios o clientes externos/S3. El advisory lock sólo excluye a componentes
cooperantes y no todos los writers del lifecycle participan en él.

`ManagedMediaWriterFreezeGuard` es deliberadamente no bypassable, no acepta un
booleano permisivo del caller y rechaza **local y S3** con
`storage_observation_untrusted`. El rechazo sucede antes de observar objetos y
antes de `recordForwardItemResolution()`. Por tanto, forward está implementado
estructuralmente pero no disponible operacionalmente. C1 sólo acreditó el
adaptador, la topología y la capacidad de lectura exacta S3; ni C1 ni C2
autorizan forward S3.

Tras una futura prueba real de writer freeze, la rama ya estructura dos pasadas
independientes de `ForwardItemEvidenceBuilder`: análisis DB, revalidación actual
de dominio/referencia/owner/master, plan exacto, observación exacta del conjunto
y ausencia puntual del universo canónico V1 no candidato; después repite gates,
recarga, análisis, revalidación y observación, y sólo la evidencia final podría
llegar al journal. Con el guard actual esta secuencia no es alcanzable.

### No-op, cleanup y cierre

Un cierre B3 válido existente devuelve `AlreadyClosed` como no-op real y no
añade evento. Un run terminal `completed`, `failed` o `interrupted`, sin puntero
de cierre B3 pero con evaluación compartida completa ya resuelta, devuelve
`NoReconciliationRequired`: no crea attempt, evento ni proyección y declara
`durableProgressOccurred=false`. En ambos casos se revalidan los gates antes de
retornar y se informa la Barrier V2 global; otro run puede mantenerla bloqueada
sin invalidar el resultado del run seleccionado.

Un run activo ya resuelto no entra en ese no-op: inicia un attempt y sólo puede
cerrar mediante la operación B3 `active → interrupted`. Un run terminal failed
o interrupted con blockers pendientes puede resolver items no-effect elegibles,
pero nunca recibe un primer cierre B3. El cierre automático sólo se intenta tras
recorrido completo sin blocker, revalidación DB compartida integral, cero
cleanup blockers y todas las precondiciones B3.

C2 no hace cleanup. Cleanup `deleted` invalida forward y cleanup `pending`,
`failed` o `unknown` permanece bloqueante y evita el cierre. No hay escritura,
borrado, copia, movimiento, rename o listing de storage; ausencia confirmada;
inferencia de ownership desde bytes actuales; ni avance de checkpoint.
`absent_now` continúa siendo una observación puntual.

Toda mutación usa exclusivamente `ReconciliationJournal`, cuya API pública
permanece exactamente en cinco operaciones:

- `beginRunReconciliation`;
- `closeRunAfterReconciliation`;
- `recordBlockedAttempt`;
- `recordForwardItemResolution`;
- `recordNoEffectItemResolution`.

C2 no añade migración ni configuración y no modifica directamente eventos o
proyecciones. Tampoco incorpora command Artisan mutante, endpoint, job, route,
scheduler ni provider wiring. El CLI mutante explícito quedó reservado a C3:

```text
php artisan media:responsive-backfill-reconcile-run --run=<uuid> --execute
```

En el cierre de C2 ese comando todavía no existía. El CLI D2-A read-only
permanecía intacto y rechazaba `--execute` con exit 2.

### Validación local de D2-C2

La suite focal del coordinador pasó con 33 tests / 660 aserciones; el foco
combinado C1/B2/B3/APPLY pasó con 314 tests / 4.126 aserciones; y la suite
backend oficial completa pasó con 1.647 tests / 16.884 aserciones sobre MariaDB
aislada. `php -l` sobre los PHP nuevos/modificados, Pint limitado a los
afectados, `git diff --check` y la auditoría humana de implementación: PASS.

### Aceptación de staging y producción de D2-C2

| Entorno | Proyecto | Environment | Servicio backend | Deployment | Estado |
| --- | --- | --- | --- | --- | --- |
| staging | `8cef1db0-14bc-4d81-a1b9-d16f55e63728` | `60e4c050-1404-44c3-a7ea-c1c5b0cf1eee` | `4739ea72-bbbe-4b95-8f2d-ebf157aa77d1` | `e9af8ed3-d29c-47ef-9b26-f1f65b574874` | `SUCCESS` |
| producción | `87540113-7f61-4081-9b8f-5172e7d43e7a` | `5cdac336-8763-4e92-ba72-9d7f24e4e4aa` | `9c6bfc33-8ee1-41f5-a2dc-11215ddf5bdd` | `5868cc88-daf3-4c6f-9373-3861ff7b346c` | `SUCCESS` |

Ambos entornos ejecutaron el mismo smoke no destructivo sobre el SHA exacto
`36274f27029178af82ec6f5c686b8a9c2ce2a621`. Antes y después, los nueve
conteos —runs, items, objetos, eventos y las cinco proyecciones de
reconciliación no nulas— fueron cero. Se confirmó MariaDB, disco `media_s3`,
backend `s3`, `StorageObservationCapability::currentDisk()` en PASS, el adapter
Laravel `Illuminate\Filesystem\AwsS3V3Adapter` y el adapter Flysystem
`League\Flysystem\AwsS3V3\AwsS3V3Adapter`.

También se resolvieron `ReconciliationCoordinator`,
`ManagedMediaWriterFreezeGuard`, `ReconciliationStateValidator` y
`ForwardItemEvidenceBuilder`; el gate directo de writer freeze devolvió
`storage_observation_untrusted`; la API pública de `ReconciliationJournal`
conservó cinco métodos; y hubo cero coincidencias de CLI mutante C2/C3. El
comando read-only con `--execute` devolvió exit 2, la inspección read-only
terminó con exit 0 y Barrier V2 se observó puntualmente `clear`.

La precisión de esta aceptación es deliberada: el smoke compartido no activó
mantenimiento ni invocó `ReconciliationCoordinator::run()`. No creó attempt,
run, item, objeto o evento; no realizó una reconciliación DB-only no-effect ni
forward; no leyó un objeto S3 concreto para C2; y no escribió o borró storage.
Con cero filas no había nada legítimo que reconciliar. Es una aceptación de
despliegue, runtime y rechazo fail-closed, no una autorización de forward.

## D2-C3: CLI mutante explícito y mapeo de exits

> **COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> El commit `68794a1e8428f0c60952638620fe9adc6e1e502c`
> (`feat(media): añadir CLI mutante de reconciliación`) está desplegado y
> aceptado en staging y producción. Su parent es
> `7748a10057114707a6f277d03a9bc70dcbd076df`.

C3 añade el primer y único llamador operacional de
`ReconciliationCoordinator`:

```text
php artisan media:responsive-backfill-reconcile-run \
  --run=<canonical-lowercase-uuid> \
  --execute
```

`--run` y `--execute` son obligatorios. La superficie específica no contiene
`--item`, `--object`, `--after-id`, `--limit`, `--force`, `--resume`,
`--cleanup`, `--finalize`, `--apply`, `--dry-run` ni `--yes`. La ausencia de
una opción obligatoria, un UUID no canónico o una opción no soportada devuelve
exit 2 sin invocar el coordinador. Si falta `--execute`, el comando no muta ni
redirige automáticamente: señala la inspección disponible mediante
`media:responsive-backfill-reconcile --run=<uuid>`.

Con entrada válida muestra primero `Modo: RECONCILIATION MUTATING / EXACT RUN`,
construye exactamente una `ReconciliationInvocation`, llama exactamente una
vez a `ReconciliationCoordinator::run()` y presenta sólo hechos tipados y
acotados de `ReconciliationReport`. No replica política de reconciliación, no
consulta ni muta directamente las tablas, no llama a `ReconciliationJournal`,
no observa ni muta storage, no adquiere un segundo lock y no activa ni desactiva
el mantenimiento de Laravel. Todos los gates continúan perteneciendo a C2. El
comando D2-A `media:responsive-backfill-reconcile` permanece read-only, conserva
su superficie y sigue rechazando `--execute` con exit 2.

### Contrato de salida C3

El proceso deriva el exit exclusivamente del outcome y los hechos tipados del
report, nunca de mensajes humanos:

| Exit | Hecho tipado |
| --- | --- |
| `0` | `Completed`, `AlreadyClosed` o `NoReconciliationRequired`. Una Barrier V2 global `blocked` causada por otro run se muestra, pero no invalida el éxito del run seleccionado. |
| `2` | Uso CLI inválido: falta `--run` o `--execute`, UUID no canónico u opción no soportada. El coordinador no se invoca. |
| `3` | `MaintenanceRequired`. |
| `4` | `LockBusy`. |
| `5` | `LockAcquireFailed`. |
| `6` | `SafetyFailure` con `attemptId == null` y `durableProgressOccurred != true`: no se ha establecido intento o progreso durable. |
| `7` | `Blocked`, o `SafetyFailure` con `attemptId != null` o `durableProgressOccurred == true`; una incertidumbre post-attempt representada por esos hechos tipados también conserva la precedencia de 7. |

El resumen muestra run, outcome, no-op, attempt, conteos recorridos y resueltos,
primer blocker, cierre de run, estado final, Barrier V2 global, progreso durable
y exit. No expone keys, evidencia JSON, respuestas de storage, ETag, VersionId,
credenciales, mensajes crudos de excepción ni stack traces.

### No-effect accesible; forward continúa fail-closed

C3 hace operacionalmente invocable la rama no-effect DB-only ya aceptada en C2
sin cambiarla. La elegibilidad depende sólo de historia APPLY inmutable, hace
cero observaciones de objetos y no usa la deriva actual de entidad, referencia,
owner o master como prueba. Mantenimiento, lock, identidad, capability gate y
las demás revalidaciones C2 siguen siendo obligatorios. Los tests de integración
locales ejercitaron esta ruta con MariaDB aislada, mantuvieron intactos los
hechos APPLY y checkpoints y observaron cero I/O de objetos.

C3 no habilita forward. `ManagedMediaWriterFreezeGuard` sigue siendo no
bypassable y rechaza local y S3 con `storage_observation_untrusted`. El camino
actual crea la procedencia durable normal `attempt_started` antes de encontrar
el blocker y conserva `attempt_blocked`; por eso el CLI devuelve exit 7. No se
observa ningún objeto exacto, no se registra `ItemForwardAccepted` y no se
escribe, borra, copia, mueve o enumera storage.

### Validación local de D2-C3

La suite focal C3 pasó con 48 tests / 516 aserciones. El foco combinado
C3/C2/C1/B3/APPLY/read-only/locks pasó con 454 tests / 5.495 aserciones y la
suite backend oficial completa con 1.695 tests / 17.199 aserciones, siempre
sobre MariaDB aislada y con exit 0. `php -l` sobre los PHP afectados, Pint
limitado a esos archivos, `git diff --check` y la auditoría humana de
implementación: PASS. C3 no añadió migración.

### Aceptación de staging y producción de D2-C3

| Entorno | Proyecto | Environment | Servicio backend | Deployment | Estado |
| --- | --- | --- | --- | --- | --- |
| staging | `8cef1db0-14bc-4d81-a1b9-d16f55e63728` | `60e4c050-1404-44c3-a7ea-c1c5b0cf1eee` | `4739ea72-bbbe-4b95-8f2d-ebf157aa77d1` | `37a5cfa8-3d98-4555-9850-2bef6cb5c832` | `SUCCESS` |
| producción | `87540113-7f61-4081-9b8f-5172e7d43e7a` | `5cdac336-8763-4e92-ba72-9d7f24e4e4aa` | `9c6bfc33-8ee1-41f5-a2dc-11215ddf5bdd` | `5570e051-d155-4379-a855-bc6f6cadb676` | `SUCCESS` |

Ambos entornos ejecutaron el mismo smoke no destructivo sobre el SHA exacto
`68794a1e8428f0c60952638620fe9adc6e1e502c`. Los nueve conteos de
runs/items/objetos/eventos y proyecciones no nulas permanecieron de `0` a `0`;
el driver fue MariaDB y mantenimiento estaba desactivado. El comando C3 apareció
exactamente una vez, help devolvió exit 0, mostró `--run` y `--execute` y no
mostró las opciones específicas prohibidas. La falta de `--execute` y
`--limit` no soportado devolvieron exit 2, y el comando read-only con
`--execute` conservó exit 2.

La forma mutante válida con UUID canónico y `--execute` sí invocó C3 y alcanzó
el primer gate del coordinador. En staging y producción devolvió
`MaintenanceRequired / exit 3`, attempt ausente, cero items recorridos y
progreso durable `no`. `ReconciliationJournal` conservó cinco métodos públicos,
Barrier V2 se observó puntualmente `clear` y los nueve conteos finales siguieron
a cero.

La aceptación compartida fue de runtime, wiring y rechazo fail-closed. No
activó mantenimiento ni inició un intento durable. Tampoco creó eventos o
proyecciones, ejecutó una reconciliación no-effect o forward real, leyó un
objeto concreto ni mutó storage. El smoke de producción reutilizó el mismo
script y su encabezado pegado seguía diciendo `STAGING ACCEPTANCE`; la
ejecución y el deployment de la tabla anterior corresponden efectivamente a
producción.

## Cierre de P1.D.2 y frontera preservada tras D3

P1.D.2 queda cerrado hasta producción mediante la cadena completa D2-A,
D2-B1, D2-B2, D2-B3, D2-C1, D2-C2 y D2-C3. Este cierre no incorpora cleanup ni
ausencia confirmada, no habilita forward, no resuelve la congelación global de
writers y no acredita una reconciliación mutante real en staging o producción.

P1.D.3 ejecutó después exactamente un APPLY autorizado y limitado a `news#1`
en staging. El run `cf2a56f9-6af0-400d-8af6-55304aa2544b` quedó `completed`,
con un item `published`, cinco objetos `created`, cleanup `not_required`, cero
eventos de reconciliación y Barrier V2 `clear`. El dry-run posterior quedó sin
candidatas ni blockers y la inspección global read-only mostró cero runs
activos, items sin terminar u objetos no resueltos, cero mutaciones de journal,
cero escrituras/borrados de storage y exit 0. Por ello no se ejecutó ni era
necesaria una reconciliación mutante para el canary exitoso.

Esta aceptación del camino normal no prueba las rutas de recuperación.
`ManagedMediaWriterFreezeGuard` continúa fallando cerrado y mantiene forward
no disponible; cleanup y ausencia confirmada siguen sin implementar, y un
blocker de cleanup no puede despejarse automáticamente. Tampoco hubo retry ni
cleanup. Estas limitaciones no son fallos de D3.

P1.D queda completado con el cierre documental D3. No se ejecutó APPLY ni
reconciliación mutante en producción y no se requiere ninguna mutación de datos
productivos para cerrarlo. Cualquier APPLY productivo futuro será una migración
operacional distinta, explícitamente autorizada y precedida por inventario,
freeze y gates nuevos; no es un bloque de implementación pendiente de P1.D.
