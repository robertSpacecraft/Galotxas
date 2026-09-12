# Reconciliación de backfill responsive — P1.D.2

> **D2-A COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-B1 COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-B2 IMPLEMENTADO LOCALMENTE / PENDIENTE DE AUDITORÍA HUMANA Y PROMOCIÓN.**
> P1.D.1C-B3 permanece aceptado hasta producción. No se ha autorizado ni
> ejecutado ningún APPLY real.

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
incluido `publication_unknown`, permanece sin cambios y la revalidación de
referencia, owner live y master queda explícitamente pendiente para D2-C.

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

> **IMPLEMENTADO LOCALMENTE / PENDIENTE DE AUDITORÍA HUMANA Y PROMOCIÓN.**
> No está desplegado, no tiene llamador operacional y no se ha ejecutado
> ninguna reconciliación real.

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

`ForwardItemEvidence` es el snapshot tipado que D2-C deberá construir tras
observar y revalidar. El repositorio **no hace I/O de storage**: valida la
evidencia contra los hechos inmutables del journal.

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

## Siguiente bloque

D2-B2 está implementado localmente y pendiente de auditoría humana y
promoción. D2-B3 es el siguiente bloque: cierre tardío de run, Barrier V2 y
guards de APPLY frente a proyecciones reconciliadas. D2-C, que observará y
revalidará para construir la evidencia que B2 valida, sigue siendo trabajo
futuro. El diseño y las primitivas de cleanup seguro, ausencia confirmada local
o S3 no existen todavía y la reconciliación destructiva sigue siendo trabajo
posterior. D2-B, P1.D.2 y P1.D no están completos.
