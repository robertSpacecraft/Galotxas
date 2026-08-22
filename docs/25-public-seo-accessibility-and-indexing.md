# SEO, accesibilidad e indexación pública

## 1. Propósito

Este documento registra el cierre técnico de la Fase 7D.3. Define una política
única para metadatos, canonical, indexación y accesibilidad transversal de la
SPA pública, sin activar todavía la indexación real ni desplegar la aplicación.

## 2. Alcance

El bloque cubre el router React real, las proyecciones públicas de Knowledge y
Legal, las fachadas CMS, la competición, Escuela, Club, Cuenta, la ruta de
token, el fallback 404, `robots.txt`, `sitemap.xml`, foco y anuncio de
navegación SPA, teclado, reflow y pruebas automatizadas.

## 3. Fuera de alcance

No configura dominio, DNS, infraestructura, correo, secretos, backups,
scheduler, analytics, SSR, prerender, redirects HTTP, imágenes sociales,
consentimientos de imagen, exportación/importación CMS ni aceptación visual
humana. No cambia las fuentes editoriales, la API o las reglas deportivas.

## 4. Inventario real

El inventario procede de `frontend/src/App.jsx`, de las cuatro colecciones del
artefacto público Knowledge, de las tres entradas Legal allowlisted y de los
mapas de rutas de Escuela y Club. No se deducen destinos a partir del Navbar.

| Superficie | Ruta real | Cantidad o forma |
| --- | --- | --- |
| Inicio | `/` | 1 |
| Competición principal | `/competicion` | 1 |
| Aprende | `/aprende-a-jugar`, `/aprende-a-jugar/manual` | 2 |
| Reglamento | `/aprende-a-jugar/manual/reglamento/:slug` | 8 documentos públicos |
| Conceptos: elementos | `/aprende-a-jugar/manual/conceptos/elementos/:slug` | 12 documentos públicos |
| Conceptos: personas | `/aprende-a-jugar/manual/conceptos/personas/:slug` | 4 documentos públicos |
| Conceptos: juego | `/aprende-a-jugar/manual/conceptos/juego/:slug` | 16 documentos públicos |
| Escuela | `/escuela` | 1 |
| Club | `/club/quienes-somos`, `/club/contacto`, `/club/federarse`, `/club/documentos` | 4 |
| Legal | `/legal/aviso-legal`, `/legal/privacidad`, `/legal/cookies` | 3 |
| Competición dinámica | `/torneos`, `/torneos/:championshipId`, `/categories/:categoryId`, `/categories/:categoryId/standings`, `/categories/:categoryId/schedule`, `/matches/:matchId`, `/rankings` | 7 formas |
| CMS legado | `/contenidos`, `/contenidos/:slug`, `/nosotros` | 3 formas |
| Cuenta | `/login`, `/register`, `/forgot-password`, `/reset-password`, `/player` | 5 |
| Token | `/public-identity/confirm` | 1 |
| Fallback | `*` | cualquier ruta no reconocida |

No hay rutas administrativas en React. `/aprende`, `/club`, `/glosario` y sus
equivalentes desconocidos permanecen en el fallback 404; Aprende y Club son
disclosures y no reciben una landing implícita.

## 5. Clasificación

`frontend/src/seo/seoManifest.js` es la única tabla y función de resolución.
Aplica estas seis categorías:

| Clasificación | Rutas |
| --- | --- |
| `INDEXABLE_CANONICAL` | Inicio, Competición principal, Aprende a jugar, Manual, los 40 documentos Knowledge vigentes, Escuela, las cuatro rutas Club y las tres legales |
| `INDEXABLE_ALIAS` | `/nosotros` y los cuatro slugs institucionales exactos bajo `/contenidos` |
| `NOINDEX_PUBLIC` | rutas deportivas dinámicas, Rankings, índice CMS y cualquier página CMS genérica no adoptada como alias |
| `NOINDEX_PRIVATE` | login, registro, recuperación, restablecimiento y Mi Panel |
| `TOKEN_OR_TRANSIENT` | confirmación de identidad pública |
| `NOT_FOUND` | wildcard, incluidas `/aprende`, `/club`, `/glosario` y rutas Knowledge inexistentes tras resolver el documento |

Las superficies deportivas dinámicas quedan deliberadamente fuera del sitemap:
son volátiles y pueden presentar identidad deportiva minimizada. Esta decisión
no reduce su acceso público ni sustituye la visibilidad efectiva del backend.

## 6. Canonical

La resolución normaliza casing, slash final, query y fragmento para elegir una
ruta, pero no modifica la URL visible ni añade redirects. Con indexación
habilitada genera exactamente un canonical absoluto, sin query ni hash, para
rutas canónicas y aliases. Sin indexación configurada no emite canonical, para
evitar URLs incompletas o inventadas.

## 7. Aliases

| Compatibilidad conservada | Canonical |
| --- | --- |
| `/nosotros` | `/club/quienes-somos` |
| `/contenidos/nosotros` | `/club/quienes-somos` |
| `/contenidos/contacto` | `/club/contacto` |
| `/contenidos/federarse` | `/club/federarse` |
| `/contenidos/documentos` | `/club/documentos` |

Los aliases continúan respondiendo, usan `noindex, follow` cuando la indexación
está activa y quedan fuera del sitemap. No se han añadido aliases ni redirects.
Otros slugs CMS siguen accesibles como legado, sin canonical y con `noindex`.

## 8. URL base

`VITE_PUBLIC_SITE_URL` acepta sólo un origen HTTP(S) absoluto, sin
credenciales, ruta, query o fragmento, y elimina la barra final. No contiene un
valor por defecto. Si se activa indexación debe ser HTTPS y no puede ser un
host local. El host ficticio reservado `https://example.test` se usa sólo en
pruebas.

## 9. Flag de indexación

`VITE_PUBLIC_INDEXING_ENABLED=false` es el default fail-closed. `true` sin URL
válida detiene check, dev o build. La activación real y sus variables pertenecen
a 7F; este cierre no vuelve indexable ningún entorno.

## 10. Metadatos

`SeoProvider`, el manifiesto y `PageMetadata` forman una única capa. Gestionan
title, description, robots, canonical, Open Graph y JSON-LD, sustituyen las
etiquetas anteriores en navegación SPA y evitan duplicados. La convención es
`Página | Club Galotxes Monòver`; Home conserva sólo el nombre público.

## 11. CMS

Club y las páginas CMS usan `seo_title` y `seo_description` cuando la API los
proporciona; si no, emplean el título real y un fallback neutro. React no extrae
descripciones de bloques ni duplica contenido editorial. Los errores y 404
anulan el canonical y permanecen `noindex`.

## 12. Knowledge

Los títulos y rutas proceden de `public-knowledge.json`; sólo sus 40 documentos
vigentes entran en el sitemap. El manifiesto aporta un fallback seguro durante
la carga y la página aplica el título real al resolver el repositorio. Un slug
o grupo ausente termina en la experiencia 404 y no queda indexable.

## 13. Legal

Las tres rutas, títulos, summaries y fechas proceden de la proyección Legal
allowlisted. El sitemap usa `publishedAt` como `lastmod`; no usa la fecha del
build. Los avisos de formulario no son páginas y no entran en rutas o sitemap.

## 14. Competición

Sólo `/competicion` es canonical indexable. Torneos, categorías, standings,
schedule, partidos y rankings se conservan públicos con `noindex, follow` bajo
indexación activa y sin sitemap o canonical. Sus metadatos son genéricos y no
incorporan nombres de participantes ni reconstruyen reglas del backend.

## 15. Privacidad

Cuenta, autenticación y token carecen de canonical. La confirmación añade
`noarchive`. Los títulos, descriptions, OG, JSON-LD, sitemap y pruebas no
publican teléfono privado ni nombres completos de menores. La política no usa
`robots.txt` como control de acceso.

## 16. Open Graph

Las rutas canónicas indexables reciben `og:type`, `og:site_name`, `og:title`,
`og:description` y `og:url`. No se inventa `og:image` ni se añade Twitter/X
Card sin un recurso aprobado y estable.

## 17. Limitación de la SPA

Los metadatos se actualizan en cliente. Crawlers o generadores de previews que
no ejecuten JavaScript pueden ver sólo los fallbacks de `index.html`; por ello
Open Graph dinámico no se considera una garantía universal. Evaluar metadata
por respuesta, prerender o SSR queda como decisión de despliegue o post-MVP.

## 18. JSON-LD

Home emite un único `SportsClub` sólo con indexación y URL válidas. Incluye
denominación pública y jurídica, constitución, correo, URL, domicilio social y
redes confirmadas. Excluye teléfonos, coordenadas, horarios, registros,
responsables y participantes.

## 19. Sitemap

El generador compone 12 rutas estáticas canónicas y 40 documentos Knowledge,
ordena las 52 entradas, escapa XML, rechaza duplicados y rutas no canónicas y
sólo usa fechas canónicas disponibles. Excluye aliases, CMS genérico, rutas
deportivas volátiles, cuenta, token, privadas, errores y 404. No se genera si
la indexación está desactivada.

## 20. Robots

El plugin Vite genera el asset en memoria según el entorno. El default es
`User-agent: *` y `Disallow: /`. Con indexación válida permite rastreo y enlaza
el sitemap absoluto. No existe un `public/robots.txt` dependiente del entorno.

## 21. `seo:check`

`npm run seo:check` funciona sin red y valida configuración, duplicados,
canonical, targets de aliases, títulos, descriptions, rutas privadas/token,
Club, las tres legales, los 40 documentos Knowledge, sitemap, robots y ausencia
de teléfonos. El build ejecuta antes `legal:check` y `seo:check`.

## 22. 404 y errores

La 404 comparte presentación y metadatos centralizados: título propio, un H1,
acciones de recuperación, `noindex, nofollow`, sin canonical ni metadata
heredada. Los estados remotos de error tampoco heredan indexación. En una SPA,
el hosting aún puede devolver HTTP 200 para el fallback; resolver el estado HTTP
real requiere configuración de 7F y no se confunde con el fallback React.

## 23. Skip link

El enlace `Saltar al contenido principal` permanece como primer foco útil, se
hace visible al foco y apunta a `main#main-content`. No se duplica por ruta.

## 24. Landmarks

El layout conserva header, navegaciones etiquetadas, un único `main` global y
footer. `PublicContentSurface` y los renderers no añaden `main` anidados.

## 25. H1

Las superficies finales muestran un H1 principal. Los headings de bloques CMS
se limitan a H2–H6 porque la página aporta el H1. Knowledge conserva la
jerarquía compilada; loading puede usar estado anunciado sin inventar un H1.

## 26. Foco SPA

Al cambiar realmente de pathname, el foco pasa a `main#main-content`, que usa
`tabIndex=-1`. No se mueve en el primer render ni por cambios de estado o
interacciones internas de la misma ruta.

## 27. Announcer

Existe un único `aria-live="polite"` y atómico. Anuncia el título seguro del
manifiesto al navegar, sin identidad deportiva ni datos privados, y no se
duplica por renders de la misma ruta.

## 28. Disclosures

Aprende y Club continúan como botones reales, con `aria-expanded`, panel
relacionado, exclusión mutua, cierre exterior, cierre al navegar y retorno de
foco con Escape. No crean `/aprende` ni `/club`.

## 29. Teclado

Skip link, navegación, disclosures, CTAs, formularios y enlaces usan controles
nativos. Enter, Espacio, Escape, Tab y retorno de foco están cubiertos sin
introducir un patrón `menubar`.

## 30. Formularios

Login/Cuenta, Escuela, Contacto y confirmación mantienen labels, estados y
mensajes accesibles. Contacto no monta campos cuando su configuración pública
está desactivada. La autorización de menores continúa fail-closed en backend y
la metadata nunca refleja datos introducidos.

## 31. Tablas

Competición, rankings, clasificación, Manual y Legal conservan sus wrappers de
scroll horizontal local cuando son necesarios. La página no debe adquirir
overflow horizontal global.

## 32. Imágenes

Este bloque no añade ni modifica imágenes. Se conserva la exigencia de `alt`
real o vacío decorativo y el frente independiente de procedencia, autorización
y retirada antes de publicación de nuevos recursos.

## 33. Contraste

Se mantiene el sistema visual normalizado y el objetivo WCAG AA. La cobertura
automatizada de este bloque no sustituye una revisión perceptual humana de
contraste en navegador y dispositivo reales.

## 34. Movimiento reducido

La hoja global respeta `prefers-reduced-motion: reduce`, acorta animaciones y
transiciones y evita scroll suave forzado, sin eliminar estados de foco.

## 35. Responsive

La matriz automatizada cubre 320, 375, 768, 1024, 1280 y 1600 px en superficies
públicas, CMS, Legal, Manual y tablas. Valida que no exista overflow global.

## 36. Zoom

Playwright comprueba reflow equivalente al 200 % y ausencia de overflow global.
La comprobación visual manual a zoom real continúa como gate humano.

## 37. Tests frontend

Vitest/RTL cubre configuración fail-closed, manifiesto, canonicalización,
metadata, limpieza de tags, OG, JSON-LD, sitemap, robots, aliases, 404, token,
auth, rutas canónicas, foco y announcer. Las suites previas conservan skip link,
landmarks, H1, disclosures, formularios y superficies.

## 38. E2E

Playwright usa MariaDB y servicios aislados. Un servidor Vite usa
`https://example.test` como origen SEO ficticio y otro verifica el modo
noindex. Los recorridos cubren las 45 comprobaciones exigidas, incluida la
ausencia real de una ruta Glosario, recursos remotos, datos privados, Contacto
desactivado, menores fail-closed, 320 px y zoom.

La suite ejecuta 61 tests en 7 archivos, 8 de ellos propios de 7D.3, sobre el
stack Docker/MariaDB aislado. Los 61 pasan en 2,5 minutos y el runner elimina
contenedores y red al terminar.

## 39. Revisión manual pendiente

No se afirma una inspección visual humana. Debe revisarse en Chromium de
escritorio y móvil: teclado completo, foco y announcer, ambos disclosures,
formularios, Manual, Legal, CMS, Competición, 320 px, zoom 200 % y contraste.

## 40. Gates de 7F

Antes de activar indexación: confirmar dominio y HTTPS; configurar
`VITE_PUBLIC_SITE_URL` y `VITE_PUBLIC_INDEXING_ENABLED=true`; exigir build y
`seo:check`; comprobar `robots.txt`, `sitemap.xml`, canonical y previews reales;
decidir respuesta HTTP 404/redirects; y mantener gates de correo, secretos,
backups, restore, scheduler, logs, staging y rollback.

## 41. Riesgos residuales

Permanecen la limitación client-side de metadata/OG, el HTTP real del fallback,
redirects de aliases, dominio e indexación productiva, auditoría automática
completa de accesibilidad, revisión humana/multibrowser, imágenes y
consentimientos, paridad CMS entre entornos, export/import, backups y operación.

## 42. Criterios de cierre

7D.3 y 7D quedan cerradas técnicamente cuando manifiesto, canonical, modo
fail-closed, assets, accesibilidad, tests, E2E, build y hashes pasan sin tocar
fuentes, `dist`, API o datos. Fase 7 y el MVP permanecen abiertos: 7E, 7F, 7G y
los gates humanos/productivos no se consideran resueltos por este documento.
La validación completa cumple estos criterios y cierra 7D.3 y 7D.

## 43. Regresión de Fase 7E

7E no cambia la clasificación, canonical, robots, sitemap ni indexación de
`/escuela`. El contenido administrable y los estados `closed` o `unavailable`
usan la metadata prudente de la ruta y nunca incorporan teléfono, correo,
formulario enviado, confirmación, inscripción o datos individuales.

`seo:check` conserva 26 rutas, 52 URLs canónicas y el modo noindex por defecto.
La regresión completa crece a 63 escenarios E2E y pasa en 2,6 minutos; mantiene
los ocho escenarios propios de 7D.3 y añade la operación ampliada de Escuela.
La revisión perceptual humana, dominio, HTTPS y activación indexable siguen en
7F/7G.

## 44. Seguimiento de Fase 7F.2A

7F.2A mantiene `/rankings` en `NOINDEX_PUBLIC`, sin canonical ni sitemap. La
ruta continúa usando `noindex, follow` cuando la indexación está habilitada y
no publica nombres deportivos en title, description, Open Graph o datos
estructurados. Sólo se actualiza su descripción genérica para explicar los
cuatro ámbitos disponibles: histórico, temporada, campeonato y categoría.

El nuevo disclosure Competición no crea rutas, aliases o canonical. Sus tres
hijos exactos son `/competicion`, `/torneos` y `/rankings`; los detalles
deportivos activan sólo el padre y no atribuyen `aria-current` a un hijo
incorrecto. Los tres disclosures comparten exclusión mutua, cierre al navegar,
clic exterior y Escape con retorno de foco. La regresión completa de
`develop` conserva 63 E2E verdes, incluidos noindex/canonical de Rankings,
teclado y ausencia de overflow a 320 px. La revisión humana y la nueva
aceptación de staging siguen pendientes.

## 45. Colaboradores institucionales de 7F.2C

La franja de Colaboradores forma parte del shell de rutas públicas ordinarias,
pero no crea ruta, detalle, canonical, sitemap, metadata, JSON-LD u `og:image`.
Se omite en cuenta, ruta token/transient y 404. Si la API está vacía, falla o
incumple el contrato, no deja heading ni espacio residual.

El heading H2 etiqueta una región secundaria; cada logo conserva dimensiones,
proporción y `alt` igual al nombre. Los destinos externos son exclusivamente
HTTPS, abren en pestaña nueva con indicación accesible y usan
`rel="sponsored noopener noreferrer"`. La rejilla no introduce overflow a
320 px, animación, carousel o tracking. Esta integración no modifica el
contrato SEO ni subsana la incidencia separada del hero de Home.

## 46. Foto de perfil privada de 7F.2D

La foto de `User` sólo aparece en `/player`, una ruta privada no indexable. No
se incorpora a metadata, Open Graph, JSON-LD, sitemap, CMS, colaboradores o
superficies deportivas públicas. La ruta binaria autenticada responde con
`X-Robots-Tag: noindex, nofollow`, `Cache-Control: private, no-store` y
`nosniff`; su URL estable no contiene identificador ni object key.

Mi Panel reserva un contenedor circular, aplica `object-fit: cover`, ofrece
texto alternativo con el nombre de la cuenta y usa iniciales o un símbolo
neutral con nombre accesible cuando no hay imagen. Upload, sustitución,
borrado, feedback y error recuperable funcionan por teclado y sin overflow a
320 px. Esta capacidad privada no modifica el contrato SEO público.

## 47. Noticias de 7F.2E

`/noticias` es `INDEXABLE_CANONICAL`, canonicaliza a sí misma, usa título y
descripción editoriales estables y Open Graph `website`. Entra en el sitemap
estático, que pasa a 53 URLs canónicas con la configuración actual.

`/noticias/:slug` empieza en `NOINDEX_PUBLIC`, sin canonical ni JSON-LD. Sólo
tras recibir una noticia pública con contrato válido pasa a indexable, fija el
canonical por slug, usa SEO explícito o los fallbacks título/extracto, publica
Open Graph `article`, `og:image`, `article:published_time` y un JSON-LD
`NewsArticle` cuyo autor y publisher son `Club Galotxes Monòver`. No se inventa
autor individual, teléfono o logo institucional. Loading, error, contrato
inválido y 404 limpian toda metadata dinámica y permanecen noindex.

El endpoint estable de portada es apto para `og:image`; la URL S3 firmada nunca
se guarda en metadata. Los slugs runtime no entran en el sitemap MVP porque el
build de la SPA no consulta Laravel. Un sitemap dinámico de artículos queda
registrado como deuda P1, sin bloquear 7F.2E. La cobertura automática incluye
navegación artículo→404/otra ruta, teclado, headings, `article`, `time`, alt,
fallback de imagen y reflow a 320 px.

## 48. Navegación CMS controlada de 7F.2F

Un placement no crea una ruta ni cambia su clasificación SEO. Todos sus
destinos continúan resolviendo la ruta existente `/contenidos/:slug`, que es
`NOINDEX_PUBLIC`, sin canonical propio ni sitemap. No se consulta Laravel
durante el build y no se añaden slugs dinámicos a `sitemap.xml`.

La composición del Navbar no modifica title, description, Open Graph,
JSON-LD, robots o canonical. Sólo amplía el estado activo de Club para URLs
exactamente asignadas y usa `aria-current="page"` en el hijo exacto. Respuesta
vacía/error/contrato inválido conserva navegación estructural y política SEO.
El E2E local confirma el meta `noindex`, teclado, foco y reflow a 320 px; la
aceptación sobre dominio real corresponde al gate de staging.
