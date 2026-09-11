# Runner CLI del backfill responsive — P1.D.1C-A / B3-B local

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

## Wiring APPLY local — P1.D.1C-B3-B

`--apply` existe únicamente en la implementación local B3-B pendiente de
revisión humana y promoción; no debe darse por disponible en staging o
producción. La invocación es no interactiva y exige exactamente un
`--domain` canónico explícito y `--limit` entre 1 y 1000. `--after-id` es
opcional, decimal no negativo y exclusivo, con valor por defecto cero. No se
define `--resume`.

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

No se ha autorizado ni ejecutado ningún APPLY operacional. B3-B permanece
local y pendiente de revisión/promoción; P1.D.1C-B3, P1.D.1C-B, P1.D, D2 y D3
continúan abiertos.
