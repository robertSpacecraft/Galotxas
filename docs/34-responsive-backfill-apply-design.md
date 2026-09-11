# Diseño de APPLY para backfill responsive — P1.D.1C-B

> **DISEÑO APROBADO / APPLY OPERACIONAL TODAVÍA NO IMPLEMENTADO.**
> P1.D.1C-B1 está completado y aceptado hasta producción, pero sólo aporta las
> primitivas internas de rango, checkpoint y barrera de recuperación. B2 está
> completado y aceptado hasta producción como publicador interno de un item. No
> existe CLI APPLY ni runner de rango. El único comando
> disponible sigue siendo el dry-run read-only de P1.D.1C-A documentado en
> [33-responsive-backfill-runner.md](33-responsive-backfill-runner.md).

## Alcance

P1.D.1C-B implementará el APPLY real componiendo la fundación de lectura de
[31-responsive-backfill-foundation.md](31-responsive-backfill-foundation.md) y
las primitivas de seguridad de
[32-responsive-backfill-safety.md](32-responsive-backfill-safety.md).

Condiciones de invocación:

- exactamente un dominio por invocación de APPLY;
- `--limit` obligatorio, entero entre 1 y 1000;
- `--after-id` opcional, límite inferior exclusivo;
- fuera de `testing` se exige mantenimiento real de Laravel;
- validación de identidad de storage antes de operar;
- lock consultivo exclusivo durante toda la invocación;
- barrera de recuperación previa;
- no existe `--resume` en D1C.

El mantenimiento y el lock no demuestran por sí solos la congelación operativa.
El operador debe detener además workers y cualquier otro escritor que no dependa
del middleware HTTP. Esta responsabilidad es del operador, no del comando.

## Recorrido y rango

- Se captura un límite superior estable antes de recorrer.
- Se journaliza la invocación validada, sus opciones, el límite superior y el
  checkpoint inicial.
- La primera pasada toma un snapshot de las referencias seleccionadas y de su
  clasificación **antes de cualquier escritura de storage**.
- `responsive_ok`, `excluded_null` y `excluded_deleted` son skips normales.
- `legacy_backfillable` es la única clasificación candidata.
- Cualquier otra clasificación bloquea la publicación en storage para esa
  invocación seleccionada.
- Los bytes de derivados preparados en la primera pasada se descartan.

## Procesamiento de candidatas

El procesamiento es **atómico por item**, nunca atómico por rango. Los items ya
completados no se revierten cuando falla un item posterior.

Para cada candidata:

1. Relectura fresca de la entidad y preparación inmediatamente antes de
   procesarla.
2. Equivalencia exacta con el snapshot en referencia, ownership, master, SHA de
   origen y bytes/hash del manifest candidato.
3. Planificación de todos los targets del item antes de su primera escritura de
   storage.
4. El estado del target debe seguir siendo aceptable.
5. Marcado `revalidated` sólo después de superar las comprobaciones de barrera
   requeridas.
6. Publicación de variantes primero; el manifest estrictamente el último.
7. Inmediatamente antes del manifest, segunda barrera: referencia, ownership,
   SHA de la master, identidad, mantenimiento, lock, ausencia de manifest y
   verificación por lectura y hash de cada variante creada contra su SHA-256
   journalizado.
8. El item se cierra antes de avanzar el checkpoint.

El checkpoint es exclusivamente evidencia terminal contigua. Nunca constituye
una autorización automática de reanudación.

El heartbeat debe ser por evento, no por temporizador.

### Contrato interno implementado por B2

`ApplyItemPublisher::publish(runId, itemId, lock)` recibe un item ya
journalizado y un `AdvisoryLockHandle` adquirido por el llamador. Exige run
APPLY activo, pertenencia exacta, fase `inspected`, clasificación
`legacy_backfillable`, snapshot íntegro e identidades de run/lock/storage
concordantes. No crea runs ni adquiere o libera el lock.

La primera revalidación relee la entidad y ejecuta el preflight D1A completo.
Sólo continúa si la referencia sigue siendo la misma, el owner live es único,
la clasificación sigue siendo `legacy_backfillable`, el SHA de la master
coincide y los bytes y SHA del manifest recién preparado son idénticos al
snapshot. El mapeo terminal sin escrituras es:

- entidad ausente, referencia/owner/deleted/metadata cambiados, o clasificación
  `excluded_null`, `excluded_deleted`, `invalid_reference`,
  `reference_conflict` o `metadata_mismatch` → `reference_changed`;
- cualquier otra clasificación no candidata, fallo de inspección/preparación,
  cambio de SHA de master, cambio de bytes/hash candidato o target presente/no
  verificable → `failed_no_writes`.

Después se planifican en orden todas las variantes del candidato y el manifest,
sin master ni targets adicionales. Una nueva barrera de referencia, ownership,
master y ausencia exacta de manifest/residuos precede a `markRevalidated()`.
Todos los objetos siguen `planned` al entrar en el primer writer. Las variantes
se envían en el orden del manifest mediante `JournaledObjectWriter`; el manifest
se envía estrictamente el último.

Tras crear todas las variantes, la segunda barrera relee referencia completa,
owner, master y targets exactos; exige ausencia del manifest, ausencia de
residuos adicionales y lectura/hash/descriptor exactos de cada variante cuyo
recibo journalizado acredita `created`. Finalmente vuelve a comprobar identidad,
mantenimiento y ownership del lock antes del dispatch del manifest.

| Recibo | Sin variante anterior creada | Con variantes anteriores creadas |
| --- | --- | --- |
| `created` | continúa; si es manifest, `published` | continúa; manifest `created` cierra `published` |
| `rejected` | `collision_detected` | sólo variantes acreditadas pasan a cleanup `pending`; `failed_cleanup_incomplete` |
| `failed` | `failed_no_writes` | sólo variantes acreditadas pasan a cleanup `pending`; `failed_cleanup_incomplete` |
| `unknown` o excepción `publication_unknown` | `publication_unknown` | `publication_unknown`; las variantes no pasan a cleanup sólo por esa incertidumbre |

Un fallo conocido de la segunda barrera después de variantes acreditadas deja
esas variantes en cleanup `pending` y cierra `failed_cleanup_incomplete`; sin
variantes conserva el resultado seguro sin escrituras correspondiente. Un fallo
de identidad/mantenimiento/lock se propaga tras registrar ese resultado cuando
el journal lo permite. Si ya existe intent/unknown, se conserva como
`publication_unknown`; un fallo del propio journal se propaga sin fabricar
estado. B2 no borra físicamente, no reintenta y no atribuye ownership de cleanup
a intent/unknown.

B2 termina exclusivamente el item. No avanza checkpoint, no finaliza el run y
no autoriza resume ni ejecución operacional.

## Fallo y recuperación

- D1C-B no realiza borrado físico ni compensación.
- Un fallo conocido posterior a variantes probadamente creadas deja evidencia de
  cleanup pendiente o incompleto y exige D2.
- Cualquier escritura desconocida, o cualquier ambigüedad al persistir el
  recibo, exige D2.
- Nunca se reintenta ni se borra a ciegas después de una publicación
  desconocida.
- La incertidumbre sobre la publicación del manifest no puede provocar el
  borrado de variantes.
- El exit 7 tiene precedencia siempre que se requiera reconciliación D2.
- `CreateState::Failed` no se trata como colisión. Sólo la colisión por rechazo
  o precondición tiene semántica de colisión.
- D2 es propietario de la reconciliación y de cualquier borrado seguro futuro,
  condicional o por versión.

## Extensiones internas aportadas por B1

- creación de run capaz de persistir atómicamente dominio tipado, `after_id`,
  `limit`, límite superior, checkpoint inicial y los datos ya existentes de
  identidad y revisión de código;
- `advanceCheckpoint(...)` monótono y contiguo;
- consulta read-only de barrera de recuperación sobre la evidencia de journal
  anterior no resuelta;
- no se requiere ningún estado nuevo de run;
- no se requiere primitiva de borrado seguro en D1C.

### Discrepancias D1B resueltas por B1

Las tres diferencias de API identificadas por el diseño quedan resueltas sin
relajar las invariantes de seguridad anteriores:

- `ApplyJournal::createApplyRun()` recibe un `ApplyRunSelection` de un único
  dominio y persiste atómicamente identidad/revisión y
  `options_json={"domain":"…","after_id":N,"limit":N}`,
  `upper_bounds_json={"<domain>":N}` y
  `checkpoints_json={"<domain>":after_id}`.
- `advanceCheckpoint(...)` aplica avance monótono sobre el run activo y exige
  item target terminado y ausencia de items journalizados no terminados en el
  intervalo. La igualdad es no-op; los IDs dispersos son válidos. Si el límite
  superior queda por debajo de `after_id`, el rango es vacío y el checkpoint
  permanece en `after_id` sin evidencia ficticia.
- `recoveryBarrier()` devuelve el estado tipado `clear`/`blocked` mediante
  consultas `EXISTS` read-only sobre la conexión de escritura. Un fallo de
  journal se distingue mediante el error tipado existente.

## Barrera de recuperación

Una invocación nueva de APPLY debe rechazarse mientras exista evidencia previa
activa, sin terminar, en intent, desconocida o con cleanup pendiente que
requiera reconciliación.

La barrera es de sólo lectura: observa evidencia, no la resuelve. B1 bloquea por
run activo, item no terminado, escritura `intent`/`unknown` o cleanup
`pending`/`failed`/`unknown`. No bloquea sólo por un run terminal ni por un recibo
terminal `failed` sin escritura o `rejected` por colisión. El futuro runner la
invocará antes de crear su propio run activo.

## Códigos de salida objetivo

| Código | Significado |
| --- | --- |
| 0 | éxito |
| 2 | argumentos inválidos o preflight bloqueante de primera pasada |
| 3 | mantenimiento ausente |
| 4 | lock ocupado |
| 5 | fallo de adquisición del lock |
| 6 | fallo conocido y seguro, sin trabajo pendiente de publicación o recuperación |
| 7 | estado no resuelto, ambiguo o que requiere reconciliación |

Este mapeo es coherente con D1B, que ya preveía lock ocupado como 4 y fallo de
adquisición como 5. El dry-run de P1.D.1C-A reserva además el código 1 para un
fallo inesperado o no clasificado.

## Estado

Las primitivas internas B1 están completadas y aceptadas hasta producción. Sus
smokes read-only en staging y producción validaron el JSON tipado de
`ApplyRunSelection` y devolvieron `recoveryBarrier()=clear`, ambos con exit 0.
Ese valor `clear` describe el estado observado durante cada smoke, no una
propiedad permanente del entorno. El publicador interno B2 está completado y
aceptado hasta producción. Sus smokes no destructivos en staging y
producción confirmaron en ese momento su resolución por DI, la firma de tres
parámetros de `publish()`, `recoveryBarrier()=clear`, exit 0 y la ausencia de
`--apply`; el valor `clear` tampoco constituye una propiedad permanente del
entorno. APPLY operacional no está implementado. El gate de capacidad de create
condicional S3 de D1B ya está
aceptado y registrado en
[32-responsive-backfill-safety.md](32-responsive-backfill-safety.md).

B3 conserva la responsabilidad de componer rango e invocación y de aportar el
CLI y el gate operacional.

Aun así, este documento no autoriza ninguna ejecución operativa: el runner, el
flujo CLI de publicación y el APPLY completo de P1.D.1C-B no están implementados
y sus precondiciones de seguridad en ejecución siguen siendo obligatorias.
