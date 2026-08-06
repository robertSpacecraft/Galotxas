# Endurecimiento de privacidad e identidad pública

## 1. Propósito

Este documento registra `PRIVACY-HARDENING-PUBLIC-IDENTITY-1`, la Fase 7D.2B.
Describe el contrato técnico aplicado antes de publicar textos legales o
activar Contacto. No constituye validación jurídica.

## 2. Alcance

El bloque minimiza la identidad deportiva anónima, elimina la persistencia del
perfil React, retira recursos remotos automáticos, reaudita almacenamiento y
añade pruebas backend, frontend y E2E.

## 3. Fuera de alcance

No se crean rutas ni enlaces legales, banner, consentimiento, proveedor de
correo, despliegue, contenido CMS, imágenes, cambios en Knowledge o activación
de Contacto. La migración de Bearer a cookies queda fuera de este bloque.

Seguimiento 7D.2C1: esa fase posterior crea la fuente `legal/`, tres rutas y
los enlaces de footer sin modificar el endurecimiento aquí descrito. No
reactiva terceros, no persiste `localStorage.user`, no cambia Resources y no
habilita Contacto. Consentimientos verificables, imágenes, correo y despliegue
siguen pendientes.

## 4. Hallazgos de 7D.2A

Las APIs deportivas públicas serializaban IDs y objetos de jugador con alias,
nombre y apellidos; React podía reconstruir el nombre completo. Además,
`localStorage.user` podía conservar el perfil autenticado y las cargas
automáticas incluían Google Fonts, Bunny Fonts y Bootstrap desde jsDelivr.

## 5. Endpoints auditados

Se revisaron listados y detalles de temporadas, campeonatos y categorías;
`GET /categories/{category}/standings`, `GET /categories/{category}/schedule`,
`GET /matches/{gameMatch}`, los rankings de campeonato, temporada e histórico,
el fallback público del workflow de partido y los contratos autenticados y
administrativos. No existe un buscador público de personas ni una ruta pública
de jugador/equipo.

## 6. Contrato público

Laravel produce una única propiedad de presentación:
`public_display_name`. React la muestra literalmente y sólo aplica
`Participante` cuando falta o está vacía; nunca combina campos privados. Los
Resources públicos son allowlists y no serializan modelos completos.

## 7. Allowlist

- La entrada pública de competición contiene sólo `entry_type` y
  `public_display_name`.
- Clasificación de categoría contiene posición, proyección y métricas
  deportivas; no contiene `entry_id`, `entry`, jugador o equipo anidado.
- Rankings agregados contienen posición, proyección, métricas y listas de
  contexto deportivo; no contienen `player_id`, `player` o `name` heredado.
- Calendario conserva datos de ronda y partido necesarios para navegación y
  presentación; cada participante usa la entrada pública cerrada.
- Partido conserva su ID de ruta, estado, fecha, tanteo publicable, contexto de
  ronda/categoría/campeonato/temporada y nombre de pista; elimina IDs técnicos
  de entradas, jugador, equipo, ganador y pista que React no necesita.

Los Resources de cuenta, participante y administración conservan sus campos
privados. `ParticipantMatchResource` añade la misma proyección pública para que
el marcador visible no vuelva a reconstruir identidad después de una acción
autenticada.

## 8. Adultos

`PublicPlayerIdentityService` exige una fecha de nacimiento conocida y mayoría
de edad en la fecha de consulta. Para una persona adulta devuelve el alias
deportivo normalizado si existe; en otro caso, nombres de pila normalizados más
la inicial Unicode del primer apellido. Si falta alias y el nombre civil está
incompleto, falla a `Participante`.

## 9. Menores

`Player.birth_date` permite distinguir edad cuando existe, pero el dominio no
dispone de autorización explícita de identidad pública, alias autorizado o
exclusión pública. Por ello, todo menor y toda fecha ausente devuelven
`Participante`, incluso si existe alias. No se expone edad, nacimiento ni el
motivo. Publicar otra identidad de menores requiere decisión de dominio,
migración y validación jurídica posteriores; no se infiere por categoría o
participación.

## 10. Junta

La Junta institucional no utiliza esta proyección deportiva. Cuando su página
CMS sea aprobada podrá presentar nombre y apellidos más cargo conforme a su
propio registro de fuentes y autorizaciones.

## 11. Identificadores

React no necesitaba IDs de jugador, equipo o entrada para rankings, tablas o
tarjetas públicas, por lo que se retiraron. Se mantienen sólo IDs de entidades
deportivas que sostienen rutas o contexto. No se crean slugs personales ni
URLs nuevas.

## 12. Frontend

Clasificación, rankings, preview de Competición, calendario y detalle público
consumen `public_display_name`. Un helper fail-closed centraliza el único
fallback. Los componentes privados de workflow mantienen su contrato
autenticado y no convierten esa forma privada en fuente pública.

## 13. Almacenamiento

La abstracción `authSession` es el único acceso al almacenamiento de sesión.
`localStorage.user` deja de escribirse o leerse. Cualquier valor legado se
elimina sin parsearlo ni migrarlo. Formularios de Escuela y Contacto siguen sin
persistir sus campos en URL o web storage. No se observa uso runtime de
`sessionStorage`, IndexedDB, Cache Storage o service worker.

## 14. Token

La arquitectura actual conserva exclusivamente el Bearer en
`localStorage.token`. Login y registro almacenan el token y mantienen el perfil
sólo en memoria. Logout, `401` y `419` limpian token, dato legado y estado
React. Un `403` de autorización ordinario conserva Cuenta y propaga el error;
la excepción explícita es `El usuario está inactivo.`, porque
`EnsureUserIsActive` revoca ese token en servidor antes de responder `403`.
El `419` se reserva para expiración de sesión/CSRF cuando aplique; las rutas API
Bearer actuales no lo emiten de forma ordinaria.
El token legible por JavaScript mantiene un riesgo XSS residual: este bloque no
lo presenta como resuelto.

## 15. Sanctum

`config/sanctum.php` mantiene `expiration = null`; no se inventa caducidad.
`POST /auth/logout` revoca el token de acceso actual. El bootstrap con token
consulta `GET /me`; una respuesta que acredita credencial o sesión inválida
limpia la autenticación. Un `403` ordinario no redirige a login ni elimina el
perfil en memoria. Un fallo remoto inesperado no persiste ni registra el
perfil.

## 16. Recursos externos

La revisión estática y E2E no localiza scripts, estilos, iframes, analítica,
píxeles, mapas o vídeos externos automáticos en las superficies revisadas.
Los enlaces a Facebook, Instagram y FedPiVal siguen siendo destinos activados
por el usuario y no widgets embebidos.

## 17. Fuentes

El frontend retira el import de Google Fonts y utiliza una pila de sistema. La
vista Laravel raíz retira Bunny Fonts. No se descargan ni versionan ficheros de
fuente y no se introduce otro proveedor.

## 18. Blade

El layout administrativo sustituye Bootstrap/jsDelivr por
`public/css/admin.css` y `public/js/admin.js`. Los recursos locales cubren
layout, formularios, tablas, feedback, navegación responsive, disclosure de
Escuela, Escape, foco y atributos ARIA sin dependencia nueva.

## 19. Cookies

Se mantienen la sesión Laravel de primera parte para Blade y el token CSRF
ligado a formularios. React continúa con Bearer en web storage y no introduce
cookie propia. El nombre, dominio, flags y duración efectivos deben verificarse
en despliegue; el estado técnico no decide por sí solo qué información legal o
mecanismo corresponde.

## 20. Terceros

Google Fonts, Bunny Fonts y jsDelivr dejan de recibir peticiones por la carga
del código versionado. Hosting, MariaDB gestionada, correo, backups, logs y sus
condiciones continúan sin proveedor productivo acreditado. Las redes sociales
son enlaces externos voluntarios.

## 21. Imágenes

No se modifica ninguna imagen. Los assets existentes siguen bloqueados para
publicación productiva hasta completar procedencia, derechos, personas,
menores, metadatos, alcance y proceso de retirada.

## 22. Contacto

`CONTACT_FORM_ENABLED=false` continúa siendo el default. No se crean primera
capa, consentimiento real, destinatario, notificación ni ruta adicional. La
activación E2E permanece limitada al entorno temporal de prueba.

## 23. Testing

Los Feature tests cubren adultos con y sin alias, nombres compuestos, espacios,
datos incompletos, menores, edad ausente, allowlists de partido, calendario y
rankings, equipos, prevención de lazy loading y regresión de `/me` y Blade.
Vitest cubre proyección fail-closed, login, bootstrap `/me`, legado, logout,
`401`, `419`, `403` ordinario, `403` de usuario inactivo, continuidad del Bearer,
errores sin payload privado y ausencia de perfil persistido.

## 24. E2E

Playwright verifica identidad minimizada en respuesta y UI, partido,
clasificación, equipo, login real, recarga, ausencia de `localStorage.user`,
logout, 401, Cuenta, Club, Contacto oculto, 404 legal y ausencia de peticiones a
los hosts remotos retirados. También verifica que un `403` ordinario muestra su
estado de error sin cerrar Cuenta y que la petición autenticada posterior
conserva el Bearer. Todos los datos son ficticios.

## 25. Bundle

La compilación temporal debe conservar Knowledge, Club y School en chunks
diferidos, no generar warnings Vite ni incorporar fuentes remotas o recursos
grandes nuevos. `frontend/dist` permanece ignorado e inalterado.

## 26. Riesgos residuales

Permanecen el Bearer accesible a JavaScript, Sanctum sin expiración global,
tokens anteriores no revocados automáticamente al iniciar otra sesión,
ausencia de CSP versionada, identidad autorizada de menores sin modelar,
políticas de retirada/retención pendientes y proveedores productivos sin
definir. La revisión del repositorio no sustituye auditoría de red del
despliegue final ni validación jurídica.

## 27. Gates transferidos a 7D.2C

Antes de publicar legal o activar Contacto se requieren aprobación profesional
de textos y primera capa; responsables, bases, derechos, conservación,
destinatarios, encargados y transferencias confirmados; decisión operativa de
menores; registro de imágenes; configuración real de cookies/HTTPS; proveedor
de correo y destinatario; pruebas de entrega, borrado y atención; auditoría del
despliegue y aceptación humana.

7D.2C1 resuelve la publicación de los tres textos y su conservación versionada.
Los requisitos de primera capa, Contacto, consentimientos verificables,
imágenes y producción continúan en 7D.2C2 y 7F.

## 28. Criterios de cierre

7D.2B queda técnicamente cerrada cuando pasan backend, frontend, lint, build,
Pint, PHP lint, Knowledge, hashes y E2E; no hay recursos remotos conocidos,
`localStorage.user` ni campos privados en respuestas anónimas; en el cierre
histórico de ese bloque Contacto y legal no se publicaron. CMS, imágenes,
estatutos, Knowledge y `frontend/dist` permanecen intactos. Tras 7D.2C1,
Contacto, 7D.2C2, 7D, Fase 7, despliegue y MVP continúan pendientes.
