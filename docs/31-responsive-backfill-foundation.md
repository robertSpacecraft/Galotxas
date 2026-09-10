# Fundación de lectura para backfill responsive — P1.D.1A

## Alcance

P1.D.1A incorpora preparación en memoria, manifest preserved, registro de
referencias e inspección operacional. No publica, reemplaza ni elimina media;
no actualiza referencias ni metadata de dominio. No incorpora comando Artisan,
runner, journal, migraciones, locks, maintenance guard ni reconciliación.

Los consumidores P1.C conservan Resources, rutas, autorizaciones y cabeceras.
Frontend y Knowledge no requieren cambios: esta responsabilidad pertenece al
backend y no introduce contenido editorial.

## Manifest privado

`ResponsiveManifest::fromPrepared()` sigue creando schema 1, con su shape y
validación anteriores. JPEG continúa prohibido y Photo exige WebP también para
la master. La posibilidad histórica de listas parciales en schema 1 no cambia.

`ResponsiveManifest::fromPreservedMaster()` crea schema 2. Sus ocho campos
exactos son `schema_version`, `policy_version`, `profile`, `preparation_policy`,
`purpose`, `master_mode`, `master` y `variants`. `master_mode` sólo admite
`preserved`; no incluye información del run ni procedencia operacional.

La master puede ser JPEG (`.jpg`), PNG o WebP. `preparation_policy` gobierna
derivados: Photo produce exclusivamente WebP82; Graphic selecciona el menor
entre PNG y WebP lossless, con empate a favor de PNG. Ninguna variante es JPEG.
La validación conserva identidad, MIME/extensión, dimensiones, proporciones,
orden y allowlist estrictos. Schema 2 exige todas las widths V1 menores que la
master; lista vacía sólo cuando no existe ninguna.

`policy_version` sigue siendo `v1` y las keys siguen bajo `variants/v1/`.
Un consumidor antiguo que sólo entiende schema 1 degrada a master-only.
El consumidor actual acepta ambos a través del mismo objeto validado.

## Preparación de master almacenada

`ExistingMasterPreparer::prepare()` recibe key, bytes, perfil y política; no
crea un UploadedFile. Devuelve `PreparedResponsiveDerivatives`: descriptor y
SHA-256 de la master preservada, variantes normalizadas y manifest candidato.
No devuelve bytes de una master nueva. Decodifica una sola vez y usa clones
para los derivados, sin crop ni upscale. `ImageDerivativeEncoder` es compartido
con uploads y conserva su política de codificación.

El límite `media.stored_master_max_bytes` es 32 MiB, independiente de
`input_max_kb` HTTP. Se comprueban MIME real, extensión, dimensiones y producto
de píxeles antes del decode. Límites de output históricos:

| Perfil | Máximo |
| --- | --- |
| Avatar | 512 × 512 |
| NewsCover | 1920 × 1080 |
| SponsorLogo | 1200 × 600 |
| Banner | 1920 × 1920 |

El normalizador legacy auto-orientaba y eliminaba metadata. El preparer
almacenado desactiva auto-orientación para no modificar ese contrato visual y
rechaza orientación JPEG no trivial detectada en EXIF. Intervention/GD extrae
EXIF sólo para JPEG entre los formatos soportados; por ello los chunks EXIF
reales de PNG/WebP se rechazan conservadoramente como
`unsupported_master_metadata`, incluso si pudieran declarar orientación 1.
Se inspeccionan límites de chunks, no substrings arbitrarios de píxeles.
No se copian metadatos a derivados. JPEG requiere la extensión EXIF.

`InvalidStoredMaster` separa imagen inválida, límites inseguros, orientación y
fallo de codificación. Un fallo de decode no se interpreta como corrupción del
storage ni del manifest.

## Inspección estricta

`Backfill/ResponsiveMediaInspector` no utiliza el resolver, caché, logger ni
`ResponsiveMediaStorage::readManifest()`. Este último conserva su contrato
público fail-soft.

Sólo usa keys exactas y `fileExists()`, evitando `exists()` y su posible
comprobación de directorios. Lee streams hasta límite + 1, verifica longitud
contra tamaño anunciado y cierra streams en `finally`. Manifest usa 16 KiB;
objetos usan el límite de master almacenada. Lectura truncada, timeout, 403 y
otros fallos de transporte producen `inspection_failed`; JSON leído pero
inválido produce `invalid`. Errores externos se reducen a códigos saneados.

Estados de objeto: `present`, `missing`, `inspection_failed`.
Estados de manifest: `missing`, `valid`, `invalid`, `inspection_failed`.

El preflight compara bytes/tamaño/cabecera de master y variantes con sus
descriptores, y comprueba completitud incluso para schema 1. No demuestra la
procedencia visual de una variante a partir de su master ni hace un segundo
decode completo de cada derivado existente.

Inspecciona PNG y WebP para cada width V1 del perfil, incluidas widths mayores
o iguales que la master. No enumera el bucket ni adivina otras widths.
Manifest válido con residuo conocido es `responsive_incomplete`; sin manifest,
un target presente es `partial_collision`. Manifest inválido mantiene su
clasificación principal aunque haya residuos o un fallo secundario.

## Referencias y ownership

`ManagedMediaDomain` contiene exclusivamente avatar, news, sponsor, season,
championship y category. `ManagedMediaReferenceRegistry` proporciona queries,
batches por PK ascendente y relectura por PK. News se enumera con soft-deleted
para excluirlos explícitamente: su lifecycle ya elimina la media. Inactividad,
borrador, programación, expiración y privacidad no eliminan ownership live.
CMS Content está excluido porque no dispone de uploader managed.

La identidad derivada es purpose/UUID, sin extensión. `liveOwners()` busca las
tres extensiones canónicas en los seis dominios, independientemente de la
selección del llamador, mediante lotes de 100 identidades. Las comparaciones
finales validan keys y agrupan strings exactos; no confían en la collation.
Dos owners live de la misma key o de aliases de esa identidad son conflicto.
Una referencia snapshot cuyo owner desapareció o cambió se considera inválida.

## Preflight

`ResponsiveBackfillPreflight::inspect()` clasifica una referencia.
`inspectBatch()` comparte consultas de ownership; conserva derivados sólo para
resultados `legacy_backfillable`. Los llamadores deben limitar el tamaño del
lote considerando la memoria ocupada por esos derivados, y liberar resultados
al consumirlos. Todavía no existe un runner que gestione ese presupuesto.

Precedencia:

1. `excluded_deleted`, `excluded_null`.
2. `invalid_reference`.
3. `reference_conflict` o fallo al inspeccionar ownership.
4. `master_missing`, `master_unprocessable` o fallo de lectura.
5. `manifest_invalid`, `responsive_incomplete` o fallo de inspección.
6. `partial_collision`; residuos con manifest válido son `responsive_incomplete`.
7. `metadata_mismatch`.
8. `responsive_ok`, `legacy_backfillable`.

Los fallos de inspección usan `inspection_failed`. Las evidencias conocidas
mantienen su prioridad y pueden incorporar razones secundarias. No se
clasifica por el orden accidental de excepciones.

News admite dimensiones null; un valor presente no positivo o discordante es
anomalía. Sponsor exige dimensiones positivas coincidentes. No se inventan
dimensiones DB para avatar/competición ni se corrige metadata. P1.C prefiere
las dimensiones DB positivas sobre el manifest, por lo que no debe declararse
elegible una referencia con metadata discordante.

La inspección es una observación, no una reserva ni una autorización para
publicar posteriormente sin revalidación. Las condiciones pueden cambiar
entre consultas y lecturas.

## Frontera con siguientes bloques

El dry-run implementado en P1.D.1C-A tiene cero escrituras de
journal/media/dominio y se documenta en
[33-responsive-backfill-runner.md](33-responsive-backfill-runner.md). Journal
MariaDB sólo para apply, con tablas runs/items/objects. Apply exigirá ventana
operativa sin mutaciones, maintenance mode fuera de testing, single-flight y
revalidación de ownership antes de publicar. Ninguna de esas capacidades se
implementa en D.1A.

Las rutas públicas siguen identificadas por entidad/width: no admiten cache
anual immutable porque reemplazar una imagen conserva esa URL.

Las pruebas de integración se ejecutan exclusivamente con el runner oficial
`backend/scripts/run-tests.sh`, MariaDB aislada y objetos controlados en memoria.

Las primitivas APPLY de P1.D.1B se documentan por separado en
[32-responsive-backfill-safety.md](32-responsive-backfill-safety.md). Añaden el
journal y las defensas previas a escritura, sin incorporar runner, CLI ni
backfill y sin alterar esta fundación read-only.
