# Dry-run del backfill responsive — P1.D.1C-A

## Alcance

P1.D.1C-A incorpora exclusivamente el comando read-only:

```bash
php artisan media:responsive-backfill
```

El comando inspecciona referencias y prepara candidatos sólo en memoria. No
implementa APPLY, journal, publicación, compensación, resume ni reconciliación.
No escribe en storage, dominio, caché o logs de aplicación, y no crea filas en
las tablas `media_backfill_*`.

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
`legacy_backfillable`; este último identifica una candidata para el futuro
APPLY. Las demás clasificaciones actuales, y cualquier clasificación futura no
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
límite para ofrecer un resumen conjunto. P1.D.1C-A no expone `--apply`; los
códigos y flujos APPLY permanecen sin implementar.
