# Primitivas de seguridad para backfill responsive — P1.D.1B

## Alcance y decisiones

P1.D.1B añade una fundación interna de backend: journal APPLY en MariaDB,
identidad de entorno/storage, lock de sesión dedicado, guard de mantenimiento,
creación exclusiva y escritura journalizada de un objeto. No ejecuta backfill,
no tiene comando Artisan, CLI, runner, publicación de conjuntos, enumeración de
media ni reconciliación. P1.D y P1 siguen abiertos; el cierre documental
compuesto corresponde a P1.D.3.

P1.D.1C-B1 amplía esta fundación interna con el contrato tipado de rango,
checkpoint y barrera de recuperación que compondrá un runner posterior. B1 no
añade APPLY al comando, no recorre referencias y no publica ni elimina media.

El dry-run de P1.D.1C-A tiene **cero escrituras totales**: ni journal, ni
dominio, ni media, y se documenta en
[33-responsive-backfill-runner.md](33-responsive-backfill-runner.md).
`ApplyJournal::createApplyRun()` es exclusivamente APPLY y no admite un
argumento de modo alternativo. Ningún consumidor dry-run está conectado a estas
primitivas.

Fuera de `testing`, el futuro apply requiere una ventana deliberada sin
mutaciones y mantenimiento real de Laravel. No se añade barrera cooperativa a
los servicios P1.B ni bypass online/force. El operador deberá detener también
workers u otros escritores que no dependan del middleware HTTP: el guard
comprueba mantenimiento, no demuestra por sí solo la ausencia de esos procesos.

Frontend, API, administración, lifecycle P1.B y Knowledge no cambian: el journal
es información operacional privada, no una fuente editorial ni de dominio.
Las masters históricas y la política HTTP permanecen intactas.

## Tablas e invariantes

La migración `2026_09_09_000000_create_media_backfill_journal_tables.php` crea
exactamente tres tablas. No usa ENUM SQL ni FKs hacia tablas de dominio:

| Tabla | Información | Restricciones e índices |
| --- | --- | --- |
| `media_backfill_runs` | UUID canónico PK, modo/state, opciones JSON, hash de identidad, revisión Git nullable de 40 hex, upper bounds/checkpoints/summary JSON nullable, tiempos y error_code | `(state, started_at)` |
| `media_backfill_items` | ID bigint, run, dominio/entidad, referencia/hash/muestra, manifest key, clasificación/razones, fase/resultado, hashes fuente/candidato, JSON candidato y tiempos | UNIQUE `(run_id, domain, entity_id)`; `(run_id, phase)`; `(master_key_hash, phase)` |
| `media_backfill_objects` | ID bigint, item, target key, kind, SHA-256/tamaño/MIME esperados, estado de escritura/cleanup, resultado original `create_state` nullable, ETag/version y tiempos | UNIQUE `(item_id, object_key)`; `(cleanup_state, updated_at)` |

`items.run_id → runs.run_id` y `objects.item_id → items.id` son FKs con RESTRICT
en DELETE y UPDATE. El historial no se borra por cascada. `down()` elimina las
tablas en orden inverso; su prueba se limita a MariaDB aislada.

P1.D.2-B1 añade de forma aditiva
`2026_09_12_000000_add_media_backfill_reconciliation_representation.php`: una
tabla append-only `media_backfill_reconciliation_events` y proyecciones
nullable en runs, items y objetos. Los eventos referencian run y, opcionalmente,
item; cada proyección puede referenciar el evento que la establezca. Todos los
FKs nuevos son RESTRICT y los punteros empiezan en `NULL`. El modelo no cambia
ni rellena los hechos APPLY existentes. La migración comprueba todas las
proyecciones y la tabla de eventos antes de ejecutar DDL en `down()` y rechaza
el rollback si ya existe cualquier procedencia durable; sólo un esquema B1
vacío puede revertirse físicamente.

Los estados y resultados usan backed enums PHP. No se añaden modelos Eloquent,
Resources ni endpoints. Los métodos de lectura devuelven filas privadas del
query builder; no deben serializarse en respuestas públicas. No existe API de
actualización arbitraria de columnas.

`ApplyRunSelection` exige exactamente un `ManagedMediaDomain`, `after_id >= 0`,
`limit` entre 1 y 1000 y `upper_bound >= 0`. `createApplyRun()` persiste en el
mismo insert transaccional estas formas JSON deterministas:

- `options_json`: `{"domain":"news","after_id":12,"limit":25}`;
- `upper_bounds_json`: `{"news":300}`;
- `checkpoints_json`: `{"news":12}`.

La key real sustituye a `news` para el único dominio seleccionado. El checkpoint
inicial es el límite inferior exclusivo ya descartado. `upper_bound < after_id`
representa deliberadamente un rango vacío: conserva `after_id` como checkpoint,
no crea referencias ficticias y no permite avance. El resumen acepta contadores
no negativos bajo clasificaciones/resultados conocidos.

Las keys canónicas se conservan completas. Para un string inválido se calcula
SHA-256 sobre todos sus bytes, pero la muestra sólo contiene tipo y longitud.
Para null/arrays/objetos se usa un hash estable de tipo y una muestra de tipo:
no se recorren arrays cíclicos, no se invocan serializadores y no se pretende
identificar su contenido. Ningún payload inválido se convierte en `master_key`.
Esas referencias nunca pueden revalidarse para escritura.

`candidate_manifest_json` se limita en la aplicación a **16.384 bytes**, antes
de decodificarlo, y se valida con `ResponsiveManifest`, la referencia canónica y
el perfil del dominio. Su hash corresponde a los bytes exactos almacenados,
incluido whitespace JSON válido. Los hashes se validan como 64 hex minúsculos.
Los errores propios sólo incluyen `SafetyError`: no encadenan mensajes SQL/SDK
con DSNs, credenciales, URLs o payloads privados. No se añaden logs de secretos.

## API del journal y transiciones

`Backfill/Safety/ApplyJournal` proporciona:

- `createApplyRun()` con `ApplyRunSelection`, `snapshot()`,
  `advanceCheckpoint()` y `planObject()`;
- `markRevalidated()` y `commitIntent()`;
- `recordReceipt()` para created, rejected, fallo conocido sin creación o unknown;
- `updateCleanup()`, `finishItem()`, `heartbeat()` y `finishRun()`;
- `run()`, `item()`, `object()`, `target()`, `activeRuns()`, `unfinishedItems()` y
  `unresolvedObjects()` para lectura/recovery posterior;
- `recoveryBarrier()` como consulta read-only de estado `clear`/`blocked`.

Cada mutación usa una transacción corta, sin reintentos, con locks de fila en
orden run → item → object. No hace I/O de storage. Rechaza transacciones externas
abiertas, tanto en Laravel como en PDO, para garantizar un commit independiente.
Usa begin/commit/rollback explícitos: la versión instalada de Laravel puede
reducir su contador sin rollback PDO si falla un evento `committing` dentro de
`transaction(..., 1)`. Un fallo de rollback desconecta la sesión de journal.

El snapshot queda limitado al dominio, intervalo `(after_id, upper_bound]` y
cantidad máxima persistidos en el run. `advanceCheckpoint()` bloquea primero el
run activo, admite igualdad como no-op y sólo avanza dentro del límite superior
hasta un item `finished`, sin items journalizados no terminados en el intervalo.
Funciona con IDs dispersos: acredita evidencia terminal contigua de la
invocación journalizada, no reconstrucción exhaustiva del historial de la tabla
ni autorización de resume. Un run terminal no admite avance.

Hasta B2, `recoveryBarrier()` usaba lecturas `EXISTS` sobre la conexión de
escritura y no materializaba historiales. Devolvía `blocked` ante cualquier run
activo, item no terminado, escritura `intent`/`unknown` o cleanup
`pending`/`failed`/`unknown`.
Un historial terminal completamente resuelto, incluido fallo conocido sin
escritura o colisión rechazada terminal, devuelve `clear`. La consulta no hace
I/O de storage, no muta ni reconcilia; el futuro runner deberá ejecutarla antes
de crear su propio run activo. Un fallo del journal sigue siendo un
`JournalUnavailable`, no un resultado de barrera.

Las proyecciones de D2-B1 son únicamente visibles en lectura y no intervienen
todavía en esta consulta. Hasta Barrier V2 en D2-B3, incluso una proyección no
nula deja intactos los cuatro predicados históricos anteriores. B1 tampoco
añade un repositorio/API capaz de insertar eventos o modificar proyecciones.

D2-B2 sí añade ese repositorio interno,
`Backfill/Reconciliation/ReconciliationJournal`. El commit
`64af3f2358afdaad08ac34bfe8d758121d54711d` está desplegado y aceptado en
staging y producción; no requirió ninguna migración propia sobre el esquema
B1 ya instalado. Es un bloque de librería sin llamador operacional: no tiene
comando, endpoint, job ni provider que pueda invocarlo.
Escribe exclusivamente eventos append-only y las proyecciones nullable de item y
objeto, nunca columnas de APPLY, y no adquiere ni libera el lock. Reutiliza las
primitivas de seguridad de este documento: exige conexión MariaDB, ausencia de
transacción ambiente, identidad de storage coincidente con el contexto, el
handle del lock exclusivo y el run durable, mantenimiento según
`ApplyMaintenanceGuard` y propiedad del lock antes de mutar y de nuevo
inmediatamente antes del commit. Además, toda resolución exige un evento
`attempt_started` semánticamente válido del mismo intento y run, anclado al hash
de identidad de storage durable de ese run, y cualquier
puntero de reconciliación no nulo en el run deja las operaciones B2 fuera de
alcance: el cierre tardío sigue siendo de D2-B3. La validación semántica de
eventos y punteros vive en un ayudante read-only compartido con la inspección.
El contrato completo se documenta en
[35-responsive-backfill-reconciliation.md](35-responsive-backfill-reconciliation.md).
`recoveryBarrier()` no cambia en B2.

P1.D.2-B3 está **implementado localmente y pendiente de auditoría humana y
promoción**. Convierte esa consulta en Barrier V2 mediante un validador
semántico compartido y DB-only. Conserva los cuatro predicados históricos, con
estos únicos overrides:

- un item no `finished` sólo deja de bloquear si su par resultado/puntero nombra
  un `item_forward_accepted` o `item_no_effect_closed` válido para ese mismo
  run, item, intento e identidad;
- una escritura `intent`/`unknown` sólo deja de bloquear si el item tiene un
  `forward_accepted` válido y el objeto tiene `forward_retained` con el mismo
  `reconciliation_event_id` durable.

Un run `active` siempre bloquea hasta el cierre tardío B3. Cleanup `pending`,
`failed` o `unknown` nunca admite override. Valores desconocidos, pares
valor/puntero parciales, eventos ausentes, parentage o procedencia discordantes,
IDs cruzados, evidencia stale/corrupta y los `NULL` pre-D2 sobre un predicado
históricamente bloqueante fallan cerrado. La barrera no observa storage.

Además, `ApplyJournal` rechaza con `SafetyError::ReconciliationRequired` toda
mutación normal del run exacto desde que existe cualquier evento o proyección
de reconciliación. `ApplyItemPublisher` aplica el mismo guard antes de toda
revalidación de dominio/storage. El writer y el creador exclusivo no cambian:
su commit de intent ya atraviesa el guard autoritativo antes de llamar a
storage.

| Registro | Transiciones |
| --- | --- |
| Run | `active → completed / failed / interrupted`; terminal no vuelve a active |
| Item | `inspected → revalidated → writing → finished`; cierre sin escrituras desde inspected/revalidated |
| Escritura | `planned → intent → created / rejected / unknown`; sin reintento ni reinterpretación automática |
| Cleanup | `not_required → pending → deleted / failed / unknown`; failed/unknown pueden volver a pending; deleted es terminal |

El snapshot sólo puede actualizarse en inspected y antes de planificar targets.
Revalidated exige clasificación legacy_backfillable, referencia válida y
hash/candidato disponibles; es una atestación del llamador, no hace la
revalidación de dominio/storage que implementará D1C. Los targets deben compartir
identidad con el item y pertenecer al candidato, con tamaño y MIME concordantes.
Nunca se planifica una master.

Resultados de item: `published`, `skipped`, `reference_changed`,
`collision_detected`, `failed_compensated`, `failed_cleanup_incomplete`,
`failed_no_writes`, `publication_unknown`. Las precondiciones evitan declarar
published con objetos pendientes, faltantes o en cleanup; compensado exige
created con cleanup deleted; publication_unknown exige intent/unknown. Un run
completed no puede tener items pendientes ni publication_unknown. Un run
failed/interrupted puede conservar evidencias pendientes.

`create_state` es un VARCHAR nullable validado con el enum PHP `CreateState`.
El recibo persiste conjuntamente `write_state` y el resultado original:
created → created/created, rejected → rejected/rejected, unknown → unknown/unknown
y failed → rejected/failed. Este último representa un fallo conocido sin efecto
de storage, sin añadir estados al vocabulario de `ObjectWriteState`.
`collision_detected` exige un recibo con `create_state=rejected`;
`create_state=failed` permite `failed_no_writes`, pero nunca acredita colisión.
Recovery/D2 puede distinguir ambos casos al recargar la fila. Antes del recibo,
incluido cuando falla su persistencia, `create_state` permanece null.

Cleanup sólo admite objetos con recibo created: intent/unknown no demuestran
propiedad. Esta API sólo registra metadatos; D1C/D2 deberán acreditar y ejecutar
la limpieza que registren. No hay código de borrado de media en D1B.
`unresolvedObjects()` incluye intent/unknown y cleanup pendiente/fallido/desconocido
incluso tras cerrar el item o run. Sus consultas tienen límites explícitos y
paginación por ID; no ejecutan recuperación.

## Identidad y lock MariaDB

`StorageIdentity` calcula SHA-256 de un JSON con orden fijo y versión interna:
entorno Laravel; driver MariaDB, host normalizado, puerto, base, socket y prefijo;
nombre/driver de media; raíz local canónica mediante realpath, o bucket, región,
endpoint normalizado y path-style S3. El root local debe existir. Se rechazan
topologías read/write o hosts múltiples y prefijos S3 no soportados por esta
fundación. La configuración actual es de una conexión primaria MariaDB y keys
S3 sin prefijo.

La URL de DB se resuelve antes de seleccionar campos. Usuario/password DB,
access key, secret, tokens y userinfo/query/fragment del endpoint no entran en
el hash. Sólo se conserva el hash, nunca el JSON de configuración. Cambiar base,
entorno o destino de storage cambia la identidad. El nombre de lock es
`galotxas:media:bf:` más 40 caracteres del hash (58 caracteres en total).

`MariaDbBackfillLock` crea una conexión dedicada con `ConnectionFactory` a partir
de la primaria configurada, sin registrarla en `DatabaseManager`, con PDO no
persistente y excepciones activadas. Captura PDO y `CONNECTION_ID()`; toda
consulta posterior de ownership/release va directamente a ese PDO. No hay
Cache::lock, transacción retenida, adquisición recursiva ni reconexión de handle.

`GET_LOCK(nombre, 0)` devuelve acquired para 1, busy para 0, failed para NULL o
error. El llamador recibe `LockAcquisition`; D1C mapeará busy a exit 4 y failed
a exit 5, sin CLI en este bloque. `assertOwned()` comprueba CONNECTION_ID e
IS_USED_LOCK con el mismo PDO. Un fallo fija el handle como perdido y lanza
`lock_lost`; no lo sustituye ni vuelve a adquirirlo.

El uso debe liberar en finally con `release()`: exige ownership y resultado 1 de
RELEASE_LOCK. Tras una liberación confirmada, repetir `release()` es un no-op.
Si falla `assertOwned()`, conserva `lock_lost`; sólo una excepción, NULL o 0 de
RELEASE_LOCK después de verificar ownership produce `lock_release_failed`.
Todos los caminos terminales cierran recursos. `close()` y el destructor sueltan
tanto la conexión como PDO, liberando el lock de sesión, pero no acreditan un
RELEASE_LOCK exitoso: llamar después a `release()` conserva `lock_lost`.
Las pruebas con sesiones MariaDB reales cubren exclusión, liberación, cierre,
destrucción y KILL de la sesión sin sleeps.

## Mantenimiento y creación exclusiva

`ApplyMaintenanceGuard::assertAllowed()` permite testing; en cualquier otro
entorno exige `Application::isDownForMaintenance()`. Nunca activa ni desactiva
mantenimiento. No existe bypass.

`TargetObject` reutiliza `ResponsiveMediaKeys`, profiles y widths V1. Sólo acepta
`variants/v1/<purpose>/<uuid>/w<width>.png|webp` o `manifest.json`. Comprueba hash,
tamaño, MIME real y width de imágenes; valida manifests con el contrato vigente.
JPEG sólo puede ser master preservada dentro del manifest, nunca target.

`ExclusiveObjectCreator` acepta únicamente los drivers/adaptadores actuales
media_local/media_s3 privados. Rechaza discrepancias entre configuración y disco
cacheado, prefijos inesperados y adaptadores no soportados. No existe fallback
exists()+put ni invocación de Flysystem put.

Local crea los directorios predecibles del target con permisos privados, rechaza
padres symlink y usa `fopen(..., 'x+b')` (O_CREAT|O_EXCL), con archivo 0600. Un
segundo create colisiona sin cambiar bytes. Escribe hasta completar, hace fflush
y fsync, y confirma cierre. Si falla después de obtener el path exclusivo,
conserva ese archivo exacto como unknown, posiblemente parcial: no hace cleanup
incierto ni crea namespaces temporales. La exclusividad evita sobrescrituras;
no se afirma tolerancia a fallos del hardware/filesystem más allá de esas
operaciones ni se coordina con escritores externos a la ventana de mantenimiento.

S3 usa el `S3Client` real del `AwsS3V3Adapter` Laravel y verifica que el modelo
instalado de PutObject contiene IfNoneMatch. Para el mapping actual sin root ni
prefix, la key del SDK es exactamente la journalizada. El cliente conserva
bucket, endpoint, región y path-style del disco. Envía PutObject con Body,
ContentType, **`IfNoneMatch => '*'`** y **`@retries => 0`**. No envía ACL pública
ni modifica la privacidad del bucket. ETag/version_id se guardan como metadata
opaca, nunca como sustitutos de SHA-256.

Éxito HTTP 2xx → created; 412/PreconditionFailed → rejected; 409,
transport/timeout y respuestas inciertas → unknown. Driver/adapter/modelo
incompatible conocido antes de dispatch → failed, sin fallback. Una respuesta
NotImplemented después de dispatch se trata conservadoramente como unknown.
No se reintenta automáticamente ninguna petición ambigua.

## Orden de escritura y publicación ambigua

`JournaledObjectWriter::create()` actúa sobre **un objeto**:

1. Recarga el target esperado y valida key, bytes, SHA-256, tamaño y MIME.
2. Comprueba identidad del entorno, run y handle; verifica mantenimiento y lock.
3. Confirma duraderamente planned → intent.
4. Repite mantenimiento y ownership inmediatamente antes del efecto.
5. Ejecuta create exclusivo y persiste el recibo o unknown.

Un intent que no se puede persistir implica cero llamadas de escritura. Un
fallo después del commit pero antes de dispatch deja intent como evidencia
conservadora. Un error inesperado del escritor se considera unknown.
Si storage pudo crear el objeto y falla persistir el recibo, se aborta con
`publication_unknown`, sin borrar, reintentar ni devolver created/rejected.
El intent previo contiene key/hash/tamaño/MIME para investigar. Una pérdida de
confirmación del propio commit puede requerir releer MariaDB: nunca se deduce
un recibo a partir de la excepción.

D1B no impone manifest-last ni publica conjuntos. D1C compondrá estas primitivas
para revalidar ownership/referencias y publicar todas las variantes antes del
manifest. D2 resolverá reconciliación e incertidumbres inspeccionando key y hash
exactos; no se implementa aquí.

## Validación y aceptación de capacidad

Las pruebas se ejecutan sólo mediante `bash backend/scripts/run-tests.sh`,
runner oficial con Docker y MariaDB `galotxas_testing`. Los objetos locales son
fixtures bajo `/tmp/galotxas-backfill-test-*`; S3 usa el cliente SDK real con
transporte HTTP simulado, sin red. Se verifica el header HTTP If-None-Match,
mapping bucket/key/body/MIME, ausencia de ACL pública, recibos y una sola petición
incluso ante 409/timeout.

Validación local del bloque: focales 53 tests / 537 aserciones (2,13 s), suite
backend completa 1.182 tests / 11.786 aserciones (108,29 s), ambos con exit 0.
`php -l` sobre los 27 PHP afectados (incluida la migración), Pint limitado a
esos archivos y `git diff --check`: PASS. Los logs completos quedan en
`/tmp/galotxas-p1d1b-focused.log` y `/tmp/galotxas-p1d1b-full.log`.

Tras la corrección quirúrgica de `create_state` y `release()`, los tests afectados
de journal y lock pasan: 31 tests / 460 aserciones (1,92 s), exit 0. Sintaxis de
los 5 PHP cambiados, Pint affected y diff-check: PASS. Log:
`/tmp/galotxas-p1d1b-correction-focused.log`. La suite completa indicada arriba
corresponde a la validación anterior; no se repitió para estas dos correcciones.

La capacidad real de create condicional del proveedor S3 compatible fue
verificada en staging mediante un probe controlado con objetos de prueba. El
primer create condicional tuvo éxito. Un segundo create contra el mismo target
fue rechazado con HTTP 412 / PreconditionFailed y el contenido existente
permaneció intacto. La limpieza controlada de esos objetos terminó
correctamente. **El gate de capacidad de create condicional exigido por D1B
queda por tanto aceptado.**

Esa aceptación no autoriza por sí sola un apply operacional. B1 sólo aporta
primitivas internas de rango, checkpoint y barrera; el APPLY completo sigue sin
implementarse y sus precondiciones operativas deben cumplirse igualmente. Fuera
de ese probe controlado no se ha ejecutado backfill ni escritura de media real.
