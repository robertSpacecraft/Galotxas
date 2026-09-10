# Diseño de APPLY para backfill responsive — P1.D.1C-B

> **DISEÑO APROBADO / TODAVÍA NO IMPLEMENTADO.**
> Este documento registra el contrato cerrado en la auditoría de diseño de
> P1.D.1C-B. No describe código existente. APPLY no está implementado: el único
> comando disponible hoy es el dry-run read-only de P1.D.1C-A documentado en
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

## Extensiones mínimas esperadas de la API D1B

- creación de run capaz de persistir atómicamente dominio tipado, `after_id`,
  `limit`, límite superior, checkpoint inicial y los datos ya existentes de
  identidad y revisión de código;
- `advanceCheckpoint(...)` monótono y contiguo;
- consulta read-only de barrera de recuperación sobre la evidencia de journal
  anterior no resuelta;
- no se requiere ningún estado nuevo de run;
- no se requiere primitiva de borrado seguro en D1C.

### Discrepancias con la API D1B vigente

Estas diferencias se registran sin modificar el comportamiento actual. Las
invariantes de seguridad anteriores se mantienen tal como están enunciadas.

- `ApplyJournal::createApplyRun()` acepta hoy identidad, revisión de código y
  dominios tipados. No acepta `after_id`, `limit`, límite superior ni checkpoint
  inicial. La persistencia atómica de esos campos es una extensión pendiente.
- Las columnas de límites superiores y checkpoints existen en el esquema pero
  quedaron reservadas para D1C. D1B no implementa su avance, por lo que
  `advanceCheckpoint(...)` no existe todavía.
- D1B ofrece `activeRuns()`, `unfinishedItems()` y `unresolvedObjects()` como
  lecturas de recuperación paginadas. No existe una consulta única de barrera de
  recuperación. La barrera puede componerse sobre esas lecturas o añadirse como
  consulta read-only dedicada, sin ejecutar recuperación.

## Barrera de recuperación

Una invocación nueva de APPLY debe rechazarse mientras exista evidencia previa
activa, sin terminar, en intent, desconocida o con cleanup pendiente que
requiera reconciliación.

La barrera es de sólo lectura: observa evidencia, no la resuelve.

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

APPLY no está implementado. El gate de capacidad de create condicional S3 de
D1B ya está aceptado y registrado en
[32-responsive-backfill-safety.md](32-responsive-backfill-safety.md).

Aun así, este documento no autoriza ninguna ejecución operativa: P1.D.1C-B no
está implementado y sus precondiciones de seguridad en ejecución siguen siendo
obligatorias.
