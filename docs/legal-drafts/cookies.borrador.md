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
| `localStorage.user` | Eliminado en 7D.2B; cualquier valor legado se borra sin migración | No aplica | No se usa en runtime |
| Google Fonts | Eliminado; frontend con pila de sistema | No aplica | Sin petición automática observada |
| jsDelivr | Eliminado; panel Blade con CSS/JS locales | No aplica | Sin petición automática observada |
| Bunny Fonts | Eliminado de la vista raíz Laravel | No aplica | Sin petición automática observada |

No se localizaron usos runtime de `sessionStorage`, IndexedDB, Cache Storage,
service workers, preferencias persistentes, analítica, píxeles, publicidad,
vídeos, mapas o iframes. Tampoco se observó `remember-me` activo.

## Activación

La sesión y CSRF se activan al usar el backend web. React escribe únicamente el
Bearer tras registro/login y restaura el perfil en memoria mediante `/me`.
Google Fonts, Bunny Fonts y jsDelivr ya no se solicitan. Facebook e Instagram
son meros enlaces, sin SDK, píxel o iframe observado.

## Conclusión provisional

No se observan en el código revisado recursos no esenciales de terceros que se
activen antes de una acción del usuario. Permanecen sesión, CSRF y Bearer como
mecanismos técnicos pendientes de reflejar en la política definitiva. Esta
conclusión técnica no decide si corresponde un mecanismo jurídico y este
borrador no implementa ni prescribe un banner.

## Pendientes

Nombre y dominio reales de cookies, configuración HTTPS/SameSite, riesgo XSS
del Bearer, proveedores productivos, datos técnicos tratados, duraciones
efectivas, bases, transferencias, consentimiento, retirada y texto final:
`PENDIENTE DE VALIDACIÓN JURÍDICA Y DEL DESPLIEGUE`.
