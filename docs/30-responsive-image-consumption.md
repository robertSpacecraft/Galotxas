# Consumo responsive de imágenes — P1.C

## Estado

P1.C está implementado y probado automáticamente en local. La implementación
ha superado la reauditoría humana local; la validación en staging sigue pendiente. Este bloque no ejecuta backfill,
no despliega y no cambia almacenamiento, lifecycle, CMS, workers, migraciones o
el modelo de autorización del avatar.

## Contrato aditivo

Noticias, sponsors y portadas de temporada, campeonato y categoría conservan
siempre la URL Laravel de la master en `url`. Cuando el manifest privado se
lee y valida correctamente, el descriptor añade:

```json
{
  "url": "https://api.example.test/api/v1/categories/12/image",
  "width": 1600,
  "height": 900,
  "variants": [
    {
      "url": "https://api.example.test/api/v1/categories/12/image/640",
      "width": 640,
      "height": 360,
      "mime_type": "image/webp"
    }
  ]
}
```

Las keys internas, manifests y URLs temporales no forman parte del JSON. Un
manifest ausente, ilegible, incompleto, inválido o de otro perfil produce el
descriptor master anterior sin inventar variantes. Los registros de
competición legacy pueden continuar sin `width` y `height` hasta P1.D.

La resolución del manifest usa una caché local a esta responsabilidad:

- manifest válido: 600 segundos;
- ausencia o resultado inválido: 60 segundos.

El TTL negativo corto permite que un backfill posterior publique variantes de
una master existente sin retener la ausencia durante diez minutos. Los fallos
al adquirir, leer o escribir el store de caché no impiden la lectura directa
del manifest ni la conservación del contrato master.

## Entrega y seguridad

Cada variante dispone de una ruta Laravel estable terminada en su anchura. El
controlador vuelve a comprobar la publicación efectiva y sólo entrega un
`ManifestImage` obtenido de un manifest validado. Noticias y competición
mantienen `Cache-Control: public, max-age=60`. Las variantes de sponsor
conservan la semántica de su master: `private, no-store` y
`X-Robots-Tag: noindex, nofollow`, con `X-Accel-Redirect` local o redirect S3
temporal corto.

El avatar conserva las rutas autenticadas bajo `/api/v1/me/profile-photo/image`.
Sus variantes 128 y 256 se descargan como blobs mediante el cliente Axios con
Bearer; no se insertan tokens, query strings, object keys o URLs firmadas en el
DOM. El frontend parte del tamaño renderizado de 7 rem y del DPR para escoger
la menor anchura disponible que cubra el objetivo; si 128 y 256 son
insuficientes, descarga directamente la master autenticada. Un error de red o
de decodificación de una variante reintenta una sola vez esa master. El
serving privado conserva `private, no-store`; en S3 también se transmite a
través de Laravel sin redirect firmado.

## React y fallback

Noticias, sponsors y competición usan `src={url}` como master, y sólo añaden
`srcSet` y `sizes` cuando el contrato de variantes completo es válido. Cada
contexto declara `sizes` a partir de sus columnas, ancho máximo y breakpoints
reales. Si se conoce una anchura master positiva, la URL master se incorpora
como último candidato por anchura del `srcset`; una anchura ya existente se
sustituye sin duplicarla y se conserva el orden ascendente.

La máquina de fallback común tiene tres estados:

1. intenta el candidato elegido de `srcset`;
2. ante `error`, compara de forma normalizada `currentSrc` con la URL master;
3. si falló una derivada, elimina `srcset` y `sizes` y asigna explícitamente la
   master a `src` una sola vez;
4. si el candidato fallido ya era la master, o si falla ese reintento explícito,
   activa directamente el fallback visual del contexto.

El último estado elimina el `<img>` fallido, por lo que no puede reiniciar la
misma cascada. Noticias muestra su bloque «Imagen no disponible», sponsors
muestra el nombre y competición conserva el comportamiento previo de omitir la
portada.

Las portadas de competición mantienen un frame con `aspect-ratio: 16 / 9`
antes de montar el `<img>`. Los layouts de detalle conservan su relación 3:1 en
desktop y 16:9 en móvil. Así, una master legacy sin dimensiones no colapsa el
espacio inicial ni cambia la geometría al dispararse `load`.

## Validación local

La cobertura automática incluye manifest válido, ausente e inválido; contrato
master; TTL positivo y negativo; rutas y cabeceras de variantes; autenticación
del avatar; contratos frontend; `srcset`/`sizes`; dimensiones; frame legacy; y
las secuencias variante → master → fallback sin loops. La validación local final
del 9 de septiembre de 2026 ejecutó 1.022 tests backend con 10.878 aserciones
sobre MariaDB aislada y 732 tests frontend en 95 archivos, todos correctos.

## Checklist pendiente de staging

- abrir noticias, sponsors y competición con manifests válidos y comprobar
  `img.currentSrc` en DevTools;
- repetir con viewports móvil, tablet y desktop, DPR 1, DPR 2 y DPR 3, y confirmar que
  cambian las variantes cuando corresponde;
- comparar bytes transferidos de master y variantes y verificar que la
  selección se aproxima al ancho renderizado;
- comprobar `Content-Type` de cada variante y que el contenido visual coincide
  con la master;
- probar una master legacy sin manifest y confirmar ausencia de `srcset`;
- probar una competición legacy sin dimensiones y observar la geometría del
  frame antes y después de cargar, además de revisar CLS;
- provocar un 404 controlado en la variante elegida y comprobar el segundo
  intento a la master;
- provocar después un fallo de master y comprobar el fallback visual sin
  solicitudes repetidas;
- revisar LCP y CLS de los recorridos principales con una captura limpia de
  red;
- iniciar sesión, variar DPR y comprobar que el avatar solicita 128 o 256 sólo
  cuando cubren el ancho físico, usa la master privada cuando no bastan o si
  falla una variante y no expone tokens ni URLs firmadas.

## Pendiente de P1.D o posterior

P1.D debe definir y ejecutar el backfill de masters legacy, con inventario,
idempotencia, observabilidad y recuperación propios. Tras verificar formalmente
que una sustitución crea siempre una master key nueva y que ningún derivado se
sobrescribe bajo la misma key, podrá estudiarse por separado
`Cache-Control: public, max-age=31536000, immutable`. P1.C mantiene el max-age
corto y no afirma validación de staging.
