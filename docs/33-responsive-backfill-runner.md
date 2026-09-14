# Runner CLI del backfill responsive — P1.D.1C-A / B3-B

## Alcance

P1.D.1C-A incorporó y mantiene el comando read-only:

```bash
php artisan media:responsive-backfill
```

Sin `--apply`, el comando inspecciona referencias y prepara candidatos sólo en
memoria. No escribe en storage, dominio, caché o logs de aplicación, y no crea
filas en las tablas `media_backfill_*`. Este comportamiento y sus exits no
cambian con B3-B.

## Selección y recorrido

`--domain` es repetible. Sin esa opción se recorren avatar, news, sponsor,
season, championship y category en el orden canónico de
`ManagedMediaDomain::cases()`. Los valores desconocidos y duplicados son
inválidos; el orden indicado por el operador no altera el orden canónico.

`--after-id` es un límite inferior exclusivo, decimal y no negativo. Sólo se
admite cuando se selecciona exactamente un dominio. No acepta signos,
decimales, espacios, notación científica ni valores vacíos.

`--limit` es opcional y acepta un entero decimal entre 1 y 1000. Cuenta todas
las referencias obtenidas de base de datos, incluidas exclusiones, skips,
bloqueos y candidatas. Es global cuando se seleccionan varios dominios. Si
trunca un recorrido multidominio, la continuación debe ejecutarse por dominio;
no existe un cursor compuesto.

Antes de recorrer se captura mediante SELECT el `MAX(id)` de cada dominio. Un
dominio vacío usa cero. Sólo se consultan referencias con `id` mayor que
`after-id` y menor o igual que ese límite, por lo que altas posteriores no
entran en la invocación. Los IDs pueden ser dispersos. Cada referencia se
inspecciona individualmente y sus derivados preparados se liberan antes de
avanzar.

## Clasificación y salida

Son resultados normales `responsive_ok`, `excluded_null`, `excluded_deleted` y
`legacy_backfillable`; este último identifica una candidata para APPLY. Las
demás clasificaciones actuales, y cualquier clasificación futura no
incluida explícitamente como normal, bloquean el dry-run.

La salida contiene modo, dominios, límites superiores, total observado,
contadores, candidatas, bloqueos, anomalías saneadas, truncación, última
referencia y confirmación de cero escrituras de storage. Los contadores son
exactos, pero el detalle conserva y muestra como máximo las primeras 100
anomalías; si existen más, indica exactamente cuántas se omitieron. No muestra
referencias inválidas, bytes, manifests, configuración privada ni objetos
completos.

Exit codes:

- `0`: inspección completada sin clasificaciones bloqueantes.
- `1`: fallo inesperado o no clasificado antes de completar la inspección.
- `2`: opciones inválidas o al menos una clasificación bloqueante.

Aunque aparezca un bloqueo, el comando completa el rango seleccionado hasta su
límite para ofrecer un resumen conjunto.

## Wiring APPLY — P1.D.1C-B3-B

`--apply` existe en staging y producción desde el commit
`a39a0b15f42eb74817855675ec4479992ce92317`. La invocación es no interactiva
y exige exactamente un `--domain` canónico explícito y `--limit` entre 1 y
1000. `--after-id` es opcional, decimal no negativo y exclusivo, con valor por
defecto cero. No se define `--resume`.

Antes de llamar al coordinador, el CLI advierte que el mantenimiento de Laravel
no demuestra que workers u otros escritores externos estén detenidos y que el
operador debe detenerlos. Después construye `ApplyInvocation`, llama una sola
vez a `ResponsiveBackfillApply::run()` y presenta únicamente los hechos seguros
de `ApplyReport`. B3-A sigue siendo la fuente exclusiva de la orquestación de
seguridad y negocio; el comando no inspecciona, journaliza, bloquea ni publica
por su cuenta.

Los errores de binding se normalizan a exit 2 sólo si los tokens crudos expresan
APPLY o contienen el `--resume` prohibido. Una opción desconocida en un dry-run
puro conserva el comportamiento Symfony previo, normalmente exit 1. Un
`Throwable` inesperado que escape del coordinador produce exit 7 saneado, sin
reintento ni inferencias sobre publicación.

| Exit | Resultado APPLY |
| --- | --- |
| 0 | `success` |
| 2 | argumentos inválidos o `first_pass_blocked` |
| 3 | `maintenance_required` |
| 4 | `lock_busy` |
| 5 | `lock_acquire_failed` |
| 6 | `safe_failure` |
| 7 | `reconciliation_required` o ruptura inesperada del contrato tipado |

## Aceptación no destructiva de B3-B

En staging y producción se desplegó el commit exacto, help mostró `--apply` y
no `--resume`, y el `--resume` prohibido con APPLY devolvió exit 2. Laravel no
estaba en mantenimiento durante el smoke con forma válida
`--apply --domain=news --limit=1`: se mostró la advertencia sobre writers
externos y B3-A devolvió `maintenance_required`, exit 3, antes de crear un run
o publicar media. El journal permaneció en runs/items/objects `0/0/0` antes y
después en ambos entornos.

Esta prueba histórica no verificó que workers estuvieran detenidos ni autorizó
un APPLY real. B3-B y el compuesto P1.D.1C-B3 quedaron completados y aceptados
hasta producción. P1.D.2 se cerró después y la aceptación operacional D3 se
registra a continuación.

## Aceptación operacional D3 en staging

La auditoría preoperacional concluyó `D3 READINESS AUDIT PASS — READY FOR
READ-ONLY STAGING DISCOVERY`. La clasificación inicial fue `D — NO-GO` por
falta de información operacional. Tras el descubrimiento exclusivamente
read-only y la confirmación explícita de congelación de writers, pasó a
`B — GO ONLY FOR A STRICTLY LIMITED CANARY`.

### Runtime y estado descubiertos antes de mutar

- `APP_ENV=staging`, PHP `8.2.33` y Laravel `12.63.0`;
- conexión y driver DB `mariadb`, servidor
  `11.4.12-MariaDB-ubu2404`, con todas las migraciones ejecutadas;
- mantenimiento inicialmente desactivado, driver de mantenimiento `file`, cola
  `sync`, scheduler del deployment desactivado y `schedule:list` sin tareas;
- disco `media_s3`, driver `s3`, visibilidad privada, sin prefix ni remapeo de
  root, backend `s3` e identidad SHA-256
  `e51f116a7a4b4f634d7073c7b369b91140abaf2c13dcbc9b1e3ce1e37339e695`;
- `StorageObservationCapability=pass`, adapter Laravel
  `Illuminate\Filesystem\AwsS3V3Adapter`, adapter Flysystem
  `League\Flysystem\AwsS3V3\AwsS3V3Adapter` y capacidad del modelo SDK
  `PutObject.IfNoneMatch=pass`;
- journal inicial runs/items/objects/events `0/0/0/0`, runs activos `0`, items
  sin terminar `0`, objetos ambiguos `0` y atención de cleanup `0`;
- Barrier V2 inicialmente `clear`; la reconciliación global read-only terminó
  con exit 0, cero mutaciones de journal y cero escrituras/borrados de storage.

El inventario read-only clasificó como `legacy_backfillable` 4 avatares, 1
noticia, 5 sponsors, 0 temporadas, 1 campeonato y 1 categoría. Todos los
dry-runs por dominio terminaron con cero blockers y exit 0. Se eligió
exclusivamente `news#1`, con el slice
`--domain=news --after-id=0 --limit=1`: una referencia observada, una
`legacy_backfillable`, una candidata y cero blockers. La preparación produjo
variantes de anchuras 320, 640, 960 y 1280, con 252.388 bytes totales, un
manifest de 832 bytes y cinco objetos por crear.

### Freeze y gates inmediatamente anteriores

El operador confirmó que las credenciales de media de staging nunca se habían
copiado a otro lugar, que ninguna otra persona, servicio o script las usaba y
que no existía otro writer externo conocido sobre el storage de media de
staging. Los writes
normales de la aplicación pasan por el backend —administración para media de
temporada, campeonato, categoría, noticia, sponsor y equivalentes; frontend
para la foto de perfil— y sólo existía un usuario administrador. El backup de
producción era independiente y no afectaba staging.

Inmediatamente antes del APPLY, mantenimiento era `true`, el HTTP público
devolvía `503` y las conexiones PHP-FPM establecidas eran `0`. MariaDB, cola
`sync`, scheduler desactivado, `media_s3`, la identidad de storage sin cambios,
los gates de observación y create condicional, los conteos de journal a cero y
Barrier V2 `clear` coincidían con el descubrimiento. El dry-run exacto conservó
una referencia observada, una candidata `legacy_backfillable`, cero blockers y
exit 0.

Con autorización explícita para exactamente una ejecución se invocó:

```text
media:responsive-backfill \
  --apply \
  --domain=news \
  --after-id=0 \
  --limit=1
```

### Resultado durable y comprobaciones posteriores

El APPLY comenzó a `2026-09-14T16:27:50Z`, terminó a
`2026-09-14T16:27:53Z` y devolvió exit 0. Sus hechos fueron `domain=news`,
`after_id=0`, `upper_bound=2`, `limit=1`, `observed=1`,
`legacy_backfillable=1`, `published=1`, `checkpoint=1`, estado del run
`completed`, reconciliación requerida `no` y outcome `success`.

El run durable exacto fue
`cf2a56f9-6af0-400d-8af6-55304aa2544b`, modo `apply`, estado `completed`,
`checkpoints_json={"news":1}`, `error_code=null`, inicio
`2026-09-14 16:27:50` y fin `2026-09-14 16:27:53`. El journal quedó en
runs/items/objects/events `1/1/5/0`, con cero runs activos, items sin terminar,
objetos ambiguos o atención de cleanup, y Barrier V2 `clear`. El item
`news#1` conservó preflight `legacy_backfillable`, fase `finished` y resultado
`published`; el manifest y las cuatro variantes quedaron con
`write_state=created`, `create_state=created` y
`cleanup_state=not_required`.

El dry-run exacto posterior clasificó `news#1` como `responsive_ok`, sin
`legacy_backfillable`, candidatas o blockers, y terminó con exit 0. La
inspección global de reconciliación mostró Barrier V2 `clear`, cero runs
activos, items sin terminar u objetos no resueltos, declaró cero mutaciones de
journal y cero escrituras/borrados de storage, y terminó con exit 0.
Mantenimiento permaneció activo durante todas estas comprobaciones. Después el
operador restauró staging y el usuario confirmó que la imagen de la noticia se
renderizaba perfectamente, lo que constituye la aceptación visual humana del
resultado responsive.

No hubo retry, cleanup ni reconciliación mutante. El canary acredita el camino
normal exitoso bajo el freeze auditado, no un backfill completo de staging ni
una migración productiva, y no habilita mutaciones automáticas más amplias. No
se ejecutó APPLY ni mutación de datos en producción; ninguna mutación productiva
es necesaria para cerrar P1.D.
