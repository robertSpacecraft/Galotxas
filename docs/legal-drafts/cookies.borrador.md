BORRADOR INTERNO — NO PUBLICAR
Pendiente de validación jurídica y operativa.

# Cookies y almacenamientos — borrador técnico

## Inventario observado

| Elemento | Finalidad | Duración técnica | Clasificación provisional |
|---|---|---|---|
| Sesión Laravel | Autenticación Blade y estado de sesión | 120 minutos de inactividad por defecto | Primera parte, esencial técnica |
| CSRF de Blade | Token oculto ligado a sesión; no se observa cookie CSRF propia consumida por React | Vinculada a sesión/flujo | Primera parte, seguridad |
| Bearer Sanctum | Autenticación API React | Sin expiración global; revocado el actual al logout | Primera parte; token, no cookie |
| `localStorage.token` | Persistir autenticación React | Sin caducidad propia | Almacenamiento local esencial para el diseño actual |
| `localStorage.user` | Hidratar identidad y perfil en React; puede incluir datos deportivos de impacto elevado | Sin caducidad propia | Almacenamiento local funcional, sujeto a minimización |
| Google Fonts | Fuente tipográfica del frontend | Caché del navegador/tercero no determinada | Petición de tercera parte no esencial |
| jsDelivr | Bootstrap del panel Blade | Caché no determinada | CDN de tercera parte |
| Bunny Fonts | Fuente de la vista raíz Laravel | Caché no determinada | Petición de tercera parte no esencial |

No se localizaron usos runtime de `sessionStorage`, IndexedDB, Cache Storage,
service workers, preferencias persistentes, analítica, píxeles, publicidad,
vídeos, mapas o iframes. Tampoco se observó `remember-me` activo.

## Activación

La sesión y CSRF se activan al usar el backend web. El almacenamiento React se
escribe tras registro/login. Las fuentes Google/Bunny y el CDN jsDelivr se
solicitan al cargar las superficies que los referencian, antes de cualquier
mecanismo de consentimiento. Facebook e Instagram son meros enlaces, sin SDK,
pixel o iframe observado.

## Conclusión provisional

**Se observa necesidad de mecanismo.** Debe revisarse jurídicamente la carga
previa de recursos de terceros no esenciales. Antes de publicar se decidirá
entre eliminarlos/autocustodiarlos o aplicar la información y el mecanismo que
corresponda. Este borrador no implementa ni prescribe un banner.

## Pendientes

Nombre y dominio reales de cookies, configuración HTTPS/SameSite, proveedores,
datos técnicos tratados, duraciones efectivas, bases, transferencias,
consentimiento, retirada y texto final: `PENDIENTE DE VALIDACIÓN JURÍDICA Y
DEL DESPLIEGUE`.
