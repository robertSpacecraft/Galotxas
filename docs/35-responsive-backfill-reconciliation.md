# Reconciliación de backfill responsive — P1.D.2

> **D2-A COMPLETADO / ACEPTADO HASTA PRODUCCIÓN.**
> **D2-B1 IMPLEMENTADO LOCALMENTE / PENDIENTE DE ACEPTACIÓN Y PROMOCIÓN.**
> P1.D.1C-B3 permanece aceptado hasta producción. No se ha autorizado ni
> ejecutado ningún APPLY real.

## Aceptación hasta producción

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

## Validación local de D2-B1

La validación focal MariaDB cubre journal/rango, esquema/rollback,
clasificadores, representación durable e inspector: 120 tests y 1.156
aserciones, exit 0. La suite backend completa pasa una vez con 1.460 tests y
14.188 aserciones, exit 0. También pasan la sintaxis PHP de todos los archivos
PHP afectados, Pint limitado a esos archivos y `git diff --check`.

## Siguiente bloque

D2-B2 será el siguiente bloque sólo después de aceptar y promover D2-B1. Deberá
introducir las APIs mínimas, específicas y transaccionales para resolución
forward/no-effect; B1 no las anticipa con setters genéricos. Barrier V2 y el
cierre de run quedan para D2-B3. El diseño y las primitivas de cleanup seguro,
ausencia confirmada local o S3 no existen todavía. Toda reconciliación mutante
sigue siendo trabajo futuro. P1.D.2 y P1.D no están completos.
