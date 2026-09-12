# Reconciliación de backfill responsive — P1.D.2

> **D2-A IMPLEMENTADO LOCALMENTE / PENDIENTE DE REVISIÓN HUMANA Y PROMOCIÓN.**
> P1.D.1C-B3 permanece aceptado hasta producción. No se ha autorizado ni
> ejecutado ningún APPLY real.

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
como inconsistente.

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
variantes, masters preservados u objetos ajenos. No incorpora estados durables
de reconciliación y no puede despejar ningún predicado de la barrera. La
igualdad de contenido es evidencia funcional actual, no prueba suficiente para
un borrado futuro.

## Siguiente bloque

D2-B definirá el mínimo contrato de estados y API de resolución durable, sólo
después de la aceptación humana de D2-A. Debe preservar la distinción entre
observación y resolución y seguir fallando cerrado para filas históricas sin
identidad suficiente. El diseño y la primitiva de cleanup seguro, local o S3,
no existen todavía y permanecen fuera de D2-A.
