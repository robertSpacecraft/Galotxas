# Auditoría de preparación de la vertical institucional Club

## 1. Propósito

Este documento registra la subfase **7C.0**, una auditoría estática y
exclusivamente documental de la futura vertical institucional `Club`. Su fin es
determinar qué soporte existe, qué contenido puede trasladarse sin inventarlo y
qué decisiones o materiales siguen bloqueando la Fase 7C.

La auditoría no implementa rutas, componentes, menús, aliases, redirects,
modelos, migraciones, endpoints, seeders, bloques, contenido, datos ni recursos.
Las cuatro rutas canónicas continúan siendo objetivo futuro:

- `/club/quienes-somos`;
- `/club/contacto`;
- `/club/federarse`;
- `/club/documentos`.

`Club` continúa siendo un disclosure sin ruta propia. El CMS es la fuente
editorial prevista; React sólo compondrá la experiencia pública y
`knowledge/` mantiene el conocimiento canónico del juego.

## 2. Estado Git

Precondiciones comprobadas antes de la inspección:

| Comprobación | Resultado |
|---|---|
| Rama | `develop` |
| HEAD | `d40db1f docs(mvp): cerrar navegación y preparación editorial` |
| Fase 7A | Presente en `fc68eac docs(mvp): auditar la paridad entre backend y frontend` |
| Fase 7B | Presente, commiteada y en HEAD |
| `git status --short` inicial | Sin salida; árbol limpio |
| Cambios preexistentes | Ninguno |

Se inspeccionaron los últimos 24 commits. No se usaron `git add`, commit, merge,
push, stash, reset ni checkout destructivo.

## 3. Fuentes revisadas

Se revisaron íntegramente los `AGENTS.md` de raíz, `backend/`, `frontend/` y
`knowledge/`; no se localizó otro `AGENTS.md`. También se consultaron:

- los README de raíz, backend, frontend y Knowledge;
- `backend/BACKEND_STYLE.md` y `frontend/FRONTEND_STYLE.md`;
- `docs/README.md` y `docs/00-glossary.md`–`docs/15-mvp-editorial-and-navigation-contract.md`, incluidos los dos documentos históricos `09-*`;
- `CHANGELOG.md`;
- el corpus completo de `knowledge/`;
- modelos, enums, migraciones, factories, seeder, Requests, controladores,
  Resources, rutas, Blade y tests del CMS;
- router, navegación, Home, `/nosotros`, cliente y renderer CMS, componentes
  comunes, metadatos, estados remotos, 404, estilos y tests frontend/E2E;
- todos los recursos versionados de `frontend/public`,
  `frontend/src/assets`, `backend/public`, la estructura versionada de
  `backend/storage` y el historial de nombres de archivo relevante.

No se levantó Docker ni se consultó una base de datos. Por ello, esta auditoría
distingue expresamente entre la capacidad del código y el contenido que pueda
existir en un entorno concreto. Un seeder o una factory no acreditan contenido
real publicado.

## 4. Knowledge

El inventario actual contiene 44 archivos Markdown: 40 documentos compilables
del Reglamento y Conceptos y cuatro archivos de instrucciones, índices o
metodología excluidos de publicación. Las búsquedas abarcaron nombre del club,
grafías, municipio, historia, fundación, modalidad, pistas, federación,
documentos, Escuela, contacto, cargos y personas.

Resultados relevantes:

- `Galotxas` aparece como denominación del proyecto o del juego en 42 archivos;
- no aparece `Galotxes`;
- no aparecen `Monóvar`, `Monòver` ni `Monover`;
- no aparecen la fecha de fundación aportada, Jorge Sánchez Romero, el correo,
  el teléfono, `FEDPIVAL` ni la denominación completa aportada de la federación;
- el corpus sí contiene modalidad, reglas, pistas, juego, jugadores y conceptos
  estables;
- no contiene una historia institucional del club, directorio de cargos,
  contacto, proceso real de federación, documentos descargables, fotos ni
  material del fundador;
- `knowledge/metodologia.md` contempla categorías futuras, pero no constituye
  por sí misma contenido histórico publicable.

Clasificación de fuentes:

| Contenido | Fuente correcta | Motivo |
|---|---|---|
| Reglas, modalidad, elementos de juego, pistas y conceptos deportivos estables | `knowledge/` | Es conocimiento canónico ya compilado y revisado |
| Identidad, historia, actividad, cargos, afiliación, contacto y documentos institucionales | CMS | Es contenido institucional administrable y sujeto a revisión |
| Programa, niveles, horarios, ubicaciones e inscripciones de Escuela | Dominio Laravel | Es información operativa con reglas propias |
| Arquitectura, contratos, roadmap y decisiones | `docs/` | Describen el sistema, no el contenido público |
| Datos aportados aún no confirmados, textos históricos no incorporados e imágenes sin derechos | Ninguna todavía | Requieren fuente, aprobación o procedencia antes de persistirse |

No se ha detectado una razón para modificar `knowledge/` ni para copiar el
Reglamento al CMS.

Matriz de contraste exigida para Knowledge:

| Dato aportado | Knowledge | Docs | CMS/código | Estado | Acción |
|---|---|---|---|---|---|
| `Club Galotxes Monóvar` | No aparece; el corpus usa `Galotxas` para proyecto/juego | Identidad pendiente | JSX y logo contienen otras variantes | Grafía pendiente | Confirmar nombre legal, público e idioma |
| Fundación 31-03-1980 | No aparece | No documentada | No aparece | Requiere fuente | Aportar acreditación o aprobación responsable |
| Presidencia de Jorge Sánchez Romero | No pertenece | No documentada | El JSX sólo tiene placeholder | Requiere fuente | Confirmar vigencia y consentimiento |
| Correo y teléfono | No pertenecen | Contacto pendiente | No aparecen | No pertenece a Knowledge | Confirmar canal oficial y cargarlo en CMS |
| Centro Polideportivo de Monóvar | No pertenece | No documentado | El JSX menciona de forma general el polideportivo municipal | Compatible | Confirmar denominación y dirección exactas |
| Disponibilidad todo el día | No pertenece | No documentada | No aparece | Requiere fuente | Elegir una expectativa real de atención |
| Instagram y Facebook | No pertenecen | Redes condicionales | No aparecen | Ausente | Aportar URLs oficiales o no publicarlas |
| Federación de Pilota Valenciana / FEDPIVAL | No aparece | Relación federativa pendiente | Home usa otra fórmula no acreditada | Requiere fuente | Confirmar denominación, relación y enlace |
| Reglamento público | Ocho reglamentos interactivos canónicos | Fuente Knowledge aprobada | Ruta pública real | Ya documentado | Enlazar el Manual desde CMS sin duplicarlo |
| Fotografías | No pertenecen | Derechos pendientes | Hay assets distintos sin procedencia | No pertenece a Knowledge | Seleccionar y acreditar recursos para CMS |
| Material histórico del fundador | No aparece | Registrado sólo como aportación pendiente | No localizado | Ausente | Reaportar, acreditar autoría y revisar |

## 5. Backend

El backend ya dispone de un CMS genérico completo para páginas y bloques:

- `backend/app/Models/CmsPage.php` y `CmsBlock.php`;
- enums `CmsPageStatus`, `CmsPagePublicationState` y `CmsBlockType`;
- migraciones `2026_06_25_000000_create_cms_pages_table.php` y
  `2026_06_25_000001_create_cms_blocks_table.php`;
- Requests administrativos `StoreCmsPageRequest`, `UpdateCmsPageRequest` y
  `SaveCmsBlockRequest`;
- controladores Blade `Admin/CmsPageController` y
  `Admin/CmsBlockController`;
- controlador público `Api/V1/CmsPageController`;
- Resources públicos de resumen, página y bloque;
- administración Blade en `backend/resources/views/admin/cms-*`;
- tests `AdminCmsPageTest`, `AdminCmsBlockTest`, `PublicCmsPageTest` e
  `InstitutionalCmsPageSeederTest`.

Una página persiste slug único, título, estado, fecha de publicación, título y
descripción SEO. Los bloques se ordenan y guardan datos JSON. La creación es
siempre borrador; publicar exige al menos un bloque; la fecha futura representa
programación y la lectura pública sólo devuelve páginas publicadas cuya fecha
ya sea efectiva. No existe borrado administrativo de página. El último bloque
de una página publicada no puede eliminarse.

La administración se protege en rutas por autenticación y administrador activo.
No hay Policy específica del CMS, taxonomía de Club, propietarios editoriales,
flujo de aprobación múltiple, versionado, revisión, trazabilidad editorial,
alias, canonical ni vigencia estructurada.

## 6. CMS

Los bloques disponibles son `heading`, `text`, `list`, `image`, `gallery`,
`button` y `document_link`. Las URLs aceptadas son rutas internas que empiezan
por `/` o URLs HTTP(S); se rechazan rutas `//`. `image` permite `alt` opcional.
`gallery` sólo guarda URLs y no dispone de texto alternativo individual.

El contrato real de `document_link` es exactamente:

```json
{
  "label": "Texto del enlace",
  "url": "/ruta-interna-o-https"
}
```

No guarda ni sube archivos. Tampoco modela tipo, versión, fecha, vigencia,
responsable, accesibilidad, tamaño o formato. Estos datos sólo podrían
explicarse hoy mediante bloques de texto adyacentes.

| Página | Bloques útiles actuales | Bloques o capacidad necesarios | ¿Backend nuevo? | ¿Migración? | Carga o recurso | Límites |
|---|---|---|---|---|---|---|
| Quiénes somos | `heading`, `text`, `list`, `image`, `gallery` | Los existentes cubren un MVP textual y visual | No para el mínimo | No | Página/bloques manuales e imágenes con URL estable | Sin procedencia, derechos, revisión ni alt por imagen en galería |
| Contacto | `heading`, `text`, `list`, `button` | Texto basta; enlaces de email/teléfono clicables exigirían aceptar `mailto:`/`tel:` | No para texto; sí, ampliación pequeña, si se exigen enlaces clicables | No; los datos son JSON | Crear manualmente el slug `contacto` | `mailto:` y `tel:` no pasan la validación actual; no debe añadirse formulario |
| Federarse | `heading`, `text`, `list`, `button`, `document_link` | Los existentes cubren proceso, requisitos y enlaces | No para el mínimo | No | Contenido y URLs oficiales cargados manualmente | Sin campos estructurados de vigencia, coste, revisión o responsable |
| Documentos | `heading`, `text`, `list`, `document_link` | Enlace al Manual y enlaces externos controlados | No para el mínimo | No | Página manual; archivo externo sólo si se ofrece descarga | Sin almacenamiento ni metadatos documentales estructurados |

El seeder `InstitutionalCmsPageSeeder` crea, sólo si faltan, seis páginas
publicadas con texto genérico: `prensa-media`, `nosotros`, `federaciones`,
`academy`, `documentos` y `federarse`. No crea `contacto` y no sustituye
contenido existente. Sus textos son arranque técnico, no contenido acreditado.

## 7. API

La única superficie pública CMS es:

- `GET /api/v1/cms/pages`;
- `GET /api/v1/cms/pages/{slug}`.

No hay endpoints específicos de Club. El detalle expone mediante allowlist
`slug`, `title`, `seo_title`, `seo_description`, `published_at` y `blocks`. El
listado añade una URL heredada `/contenidos/{slug}`. Borradores y publicaciones
futuras no se devuelven; el detalle no hace fallback a una página privada.

El contrato es suficiente para que futuras fachadas React consulten los cuatro
slugs sin duplicar cuerpos editoriales. La ruta canónica pública deberá quedar
desacoplada del slug CMS. Si se requiere correo o teléfono clicable, el cambio
pertenece al contrato de bloques y a su renderer, no a un endpoint de Contacto.

## 8. Frontend

Estado real inspeccionado:

- `frontend/src/App.jsx` registra `/nosotros`, `/contenidos` y
  `/contenidos/:slug`, pero ninguna ruta `/club/*`;
- `publicNavigation.js` ofrece Inicio, Competición, Aprende a jugar y Escuela
  como enlaces planos; todavía no implementa los disclosures Aprende y Club;
- `Navbar.jsx` separa cuenta, cierra el menú móvil al navegar y con Escape, pero
  aún no tiene submenús;
- `CmsPage.jsx` carga por slug y distingue loading, error, 404 y contenido;
- no ofrece reintento y su estado vacío sólo cubre una página pública sin
  bloques;
- aplica `document.title`, pero no reutiliza `PageMetadata` ni sincroniza la
  meta descripción;
- `CmsBlockRenderer.jsx` soporta los siete tipos, ignora tipos desconocidos y
  filtra URLs; los enlaces internos usan `<a>` y recargan la SPA;
- el renderer puede producir otro H1 desde un bloque `heading`; la gobernanza
  editorial debe impedir una jerarquía inválida;
- `image` usa el alt opcional también como pie; `gallery` fuerza alt vacío;
- `PublicLanding`, `LandingHeader`, `LandingSection`, acciones, tarjetas,
  `PageMetadata` y la 404 son piezas reutilizables;
- `/nosotros` y School están diferidos de forma distinta: School usa carga
  diferida, mientras `Nosotros` y la página CMS genérica entran directamente en
  el router;
- hay tests unitarios/RTL del CMS, Navbar, metadatos, landings y 404 y un E2E
  genérico de CMS, pero no tests de Club, paridad, aliases ni documentos
  institucionales.

La futura vertical puede reutilizar el cliente `cmsService`, el renderer y el
sistema de landings, pero necesita una fachada de ruta por slug, estados
recuperables, metadatos comunes, navegación SPA para enlaces internos y carga
diferida de la rama.

## 9. Rutas

| Ruta | Existe | Fuente/implementación actual | Decisión 7C.0 |
|---|---:|---|---|
| `/club` | No | Ninguna | No crear; Club es disclosure |
| `/club/quienes-somos` | No | Objetivo sobre slug CMS `nosotros` | Pendiente de contenido y paridad |
| `/club/contacto` | No | Slug CMS `contacto` tampoco existe en seeder | Pendiente de creación y contenido manual |
| `/club/federarse` | No | Objetivo sobre `federarse` | Pendiente de proceso real |
| `/club/documentos` | No | Objetivo sobre `documentos` | Puede partir de un enlace al Manual, tras aprobación |
| `/nosotros` | Sí | JSX hardcodeado | Conservar hasta acreditar paridad; alias temporal futuro |
| `/contenidos/nosotros` | Sí, genérica | API CMS si el slug está publicado | Alias temporal futuro a la misma fuente |
| `/contenidos/federarse` | Sí, genérica | API CMS si el slug está publicado | Alias temporal futuro |
| `/contenidos/documentos` | Sí, genérica | API CMS si el slug está publicado | Alias temporal futuro |
| `/contenidos/prensa-media` | Sí, genérica | API CMS si está publicada | Mantener fuera de Navbar |
| `/contenidos/federaciones` | Sí, genérica | API CMS si está publicada | Mantener fuera de Navbar |
| `/contenidos/academy` | Sí, genérica | Legado CMS independiente | No asociar a Escuela ni Club |
| `/contenidos` | Sí | Índice técnico de páginas CMS públicas | Mantener como legado; no es arquitectura final |

No se encontraron rutas raíz `/contacto`, `/federarse` o `/documentos`. No hay
compatibilidad que justifique crearlas. Aliases temporales, redirects HTTP,
canonical e indexación siguen siendo decisiones separadas.

## 10. `/nosotros`

`frontend/src/pages/Nosotros/Nosotros.jsx` es completamente hardcodeada. No
consume CMS, no usa `PageMetadata` y contiene copy institucional no acreditado:

- “Club de Galotxetes de Monóvar”, distinto del nombre aportado;
- misión, visión y valores;
- actividad en la Galotxeta del Polideportivo Municipal de Monóvar;
- origen del deporte en el siglo XIX y práctica en cuadras y calles;
- Escuela activa desde nueve años y jugadores de más de setenta;
- afirmaciones sobre un “nuevo club”, igualdad, centros educativos, C.O. El
  Molinet, convivencias y exhibiciones;
- junta directiva con Presidencia, Secretaría, Tesorería y Vocalías rellenadas
  literalmente con `Nombre y Apellidos`.

Usa cinco imágenes locales, todas con alt, pero sin procedencia, licencia,
consentimiento ni relación acreditada con el club. `escuela_grupo.png` muestra
de forma visible otra identidad, “CLUB PILOTA ALZIRA”, por lo que no es apta
para representar al club de Monóvar. Algunas imágenes incluyen personas y
menores aparentes.

Todo el texto estructural puede representarse con bloques CMS, pero ningún claim
debe copiarse automáticamente. Tras cargar y aprobar una versión CMS, deberá
compararse párrafo, heading, lista, imagen, alt, metadato y estado remoto. El
JSX y sus assets sólo podrán retirarse cuando no tengan consumidores y la
compatibilidad haya sido aceptada.

## 11. Quiénes somos

| Bloque editorial | Existe | Fuente | Calidad | Conflictos | Falta |
|---|---:|---|---|---|---|
| Nombre oficial | No | — | Ausente | `Galotxas`, `Galotxes`, `Galotxetes`; `Monóvar`/`Monòver` | Denominación legal acreditada |
| Nombre público | Aportado | Usuario | No verificado | JSX y logo usan variantes distintas | Aprobación literal y denominación corta |
| Presentación | Sí, parcial | `/nosotros` y seeder | No acreditada/genérica | Puede atribuir oficialidad o identidad incorrecta | Copy aprobado |
| Propósito | Sí | `/nosotros` | No acreditado | No hay fuente o fecha | Validación editorial |
| Actividad | Sí, parcial | `/nosotros`; usuario indica fotos | No verificada | Claims de Escuela, colegios y eventos | Actividad actual y alcance |
| Fundación | Aportada | Usuario: 31-03-1980 | No verificada | No aparece en repo | Fuente y forma de publicación |
| Historia | Parcial | JSX; material externo indicado | JSX no acreditado; material ausente | Relato del deporte no equivale a historia del club | Reincorporar texto del fundador, autoría y revisión |
| Modalidad | Sí | Knowledge | Canónica para el juego | No debe duplicarse extensamente | Enlace o resumen institucional mínimo |
| Ubicación | Parcial | Usuario y JSX | Compatible en términos generales | “Centro” frente a “Polideportivo Municipal”; sin dirección | Denominación y dirección exactas |
| Organización | Placeholder | `/nosotros` | No publicable | Estructura de cargos no confirmada | Organigrama que se quiera publicar |
| Presidente | Aportado | Usuario: Jorge Sánchez Romero | No verificado | Placeholder en JSX | Confirmación del cargo y consentimiento |
| Otros cargos | No | — | Ausente | JSX sólo ofrece placeholders | Nombres/cargos o decisión de omitirlos |
| Afiliación | Aportada | Usuario | No verificada | Home menciona otra “Federación” genérica | Relación jurídica/deportiva y nombre oficial |
| Imágenes | Sí, técnicas | Assets React; otras aportadas fuera del repo | Sin procedencia | Una imagen identifica a Alzira | Selección, derechos, consentimientos, captions y alt |
| Fecha de revisión | No | — | Ausente | CMS no la estructura | Fecha y próxima revisión |
| Responsable editorial | No | — | Ausente | CMS no lo estructura | Persona o rol aprobador |

La página no está lista para publicación. El soporte técnico mínimo existe, pero
el nombre, la historia y la selección de recursos requieren decisión humana.

## 12. Contacto

| Dato | Estado | Riesgo o límite | Acción requerida |
|---|---|---|---|
| Correo | Aportado: `clubgalotxesmonover@hotmail.com` | No aparece en repo; `mailto:` no está admitido por el CMS | Confirmar que es oficial, atendido y publicable; decidir texto o enlace clicable |
| Teléfono | Aportado: `687 524 083` | No aparece en repo; dato personal/organizativo y `tel:` no admitido | Confirmar titularidad, formato público y atención |
| Ubicación | Aportada: Centro Polideportivo de Monóvar | Compatible de forma general con el JSX, no exacta | Confirmar nombre, dirección y si basta ubicación sin dirección postal |
| Horario | “cualquier hora del día” | Promesa operativa amplia y no acreditada | El usuario debe elegir: no publicar horario, “atención según disponibilidad” o un horario real |
| Mapa | No | No hay URL ni coordenadas | Opcional; aportar URL oficial si se desea |
| Instagram | Sólo nombre “Galotxes Monóvar” | No identifica una URL inequívoca | Aportar URL HTTPS exacta y confirmar oficialidad |
| Facebook | Sólo nombre “Galotxes Monóvar” | No identifica una URL inequívoca | Aportar URL HTTPS exacta y confirmar oficialidad |
| Persona/departamento | No | Presidente no implica responsable de contacto | Indicar canal o rol atendente, u omitir nombre |
| Privacidad | No necesaria para una página sin formulario | Sí aplica a cualquier formulario futuro | Mantener MVP informativo sin formulario |
| CMS | No hay slug sembrado `contacto` | La base lo soporta, pero no existe contenido acreditado | Crear y completar manualmente en borrador en 7C |

No se decide en esta auditoría cómo sustituir “cualquier hora del día”. Las tres
opciones deben someterse a quien vaya a atender realmente los canales.

## 13. Federarse

El repositorio no contiene un proceso real publicable. El seeder sólo aporta
una descripción genérica y Knowledge explica el juego, no la gestión de
licencias.

| Necesidad | Estado | Observación |
|---|---|---|
| Quién puede federarse | Ausente | No inferir categorías, edades ni residencia |
| Organismo competente | Aportado, no verificado | Confirmar denominación oficial y competencia |
| Relación del club con el organismo | Aportada de forma genérica | Afiliación no equivale a tramitar licencias |
| Pasos | Ausentes | Deben proceder del club o del organismo vigente |
| Documentación | Ausente | No confundir con reglamentos de juego |
| Seguro y licencia | Ausentes | No inferir cobertura ni obligatoriedad |
| Costes | Ausentes | Omitir hasta confirmar importe y vigencia |
| Plazos | Ausentes | Omitir hasta confirmar calendario real |
| Enlaces | Ausentes | Se requieren URLs oficiales exactas |
| Contacto | Parcial | El canal general no se presume responsable de federaciones |
| Vigencia/revisión | Ausente | Imprescindible para presentar el proceso como actual |

La página está bloqueada por datos, no por esquema de base de datos. Los bloques
actuales pueden presentarla cuando el proceso haya sido proporcionado y
revisado.

## 14. Documentos

Knowledge aporta un Manual/Reglamento interactivo: ocho reglamentos y 32
documentos conceptuales compilados y publicados bajo `/aprende-a-jugar`. No se
han encontrado PDF, DOC, hoja de cálculo, presentación, ZIP ni otro documento
descargable versionado en el repositorio.

Debe distinguirse:

- el Manual/Reglamento interactivo, cuya fuente sigue siendo Knowledge;
- una página CMS `Documentos`, que cataloga y contextualiza enlaces;
- un archivo descargable, que necesita alojamiento externo o futuro soporte de
  archivos;
- documentos federativos, que requieren propietario, vigencia y URL oficial;
- documentos legales, que pertenecen al bloque legal posterior;
- documentos internos, que no deben publicarse por defecto.

La página puede implementarse con contenido mínimo enlazando mediante
`document_link` a `/aprende-a-jugar/manual`, sin copiar el Reglamento. Antes hay
que confirmar que ése es el “reglamento” que el usuario desea ofrecer. Si se
espera un PDF oficial o federativo, faltan archivo/URL, versión, fecha,
vigencia, responsable, accesibilidad, tamaño y formato.

## 15. Recursos

El inventario versionado no contiene documentos descargables ni multimedia en
backend/Knowledge. `backend/storage` conserva únicamente estructura ignorada;
`backend/public` contiene infraestructura web, no recursos institucionales.

| Archivo o grupo | Ruta | Uso actual | Procedencia | Decisión |
|---|---|---|---|---|
| Favicon | `frontend/public/flaticon.svg` | `frontend/index.html` | No registrada | Válido técnicamente; identidad pendiente |
| Sprite/colección SVG | `frontend/public/icons.svg` | Sin consumidor localizado | No registrada | Pendiente de revisión; no necesario para 7C |
| Logo React/Vite | `frontend/src/assets/react.svg`, `vite.svg` | Plantilla/sin uso institucional | Estándar técnico | No apto para Club |
| Ilustración genérica | `frontend/src/assets/hero.png` | Sin consumidor localizado | No registrada | Placeholder o huérfana; fuera de 7C |
| Hero deportivo | `frontend/src/assets/galotxas_hero.png` | Home/Hero | No registrada | Posiblemente reutilizable sólo tras revisar derechos y contexto |
| Logo actual | `frontend/src/assets/images/Logo_Galotxas_Femenino.png` | Navbar | No registrada | Requiere resolver identidad y autorización |
| Cinco imágenes de Nosotros | `frontend/src/assets/*.png` concretadas abajo | `/nosotros` | No registrada | Revisar una por una; no migrar automáticamente |

Los cinco archivos de `/nosotros` tienen extensión `.png` pero contenido JPEG
de 640×640. No se propone convertirlos en esta fase.

## 16. Imágenes actuales

| Archivo | Ruta exacta | Uso actual | Sección | Procedencia registrada | Alt actual | Decisión |
|---|---|---|---|---|---|---|
| `nosotros_hero.png` | `frontend/src/assets/nosotros_hero.png` | Hero de `/nosotros` | Instalaciones | No | “Galotxeta de Monóvar” | Posiblemente reutilizable sólo si se confirma lugar, autoría y derechos |
| `pelota_trapo.png` | `frontend/src/assets/pelota_trapo.png` | Bloque de historia | Material de juego | No | “Pelota de trapo artesanal” | Posiblemente reutilizable; mejor mantener el contenido técnico en Knowledge |
| `escuela_grupo.png` | `frontend/src/assets/escuela_grupo.png` | Actividad/escuela | Personas | No | “Escuela de Galotxas” | No apta para representar al club: muestra “CLUB PILOTA ALZIRA” y personas menores aparentes |
| `igualdad_jugadora.png` | `frontend/src/assets/igualdad_jugadora.png` | Igualdad | Persona jugando | No | “Igualdad en el deporte” | Pendiente de revisión de identidad, consentimiento y claim |
| `convivencia_gatxamiga.png` | `frontend/src/assets/convivencia_gatxamiga.png` | Convivencia | Actividad social | No | “Jornada de convivencia” | Pendiente; aparecen personas y otra rotulación de club sin contexto acreditado |
| `galotxas_hero.png` | `frontend/src/assets/galotxas_hero.png` | Hero de Home | Juego | No | “Galotxas Hero” | Posiblemente reutilizable; alt actual poco descriptivo |
| `Logo_Galotxas_Femenino.png` | `frontend/src/assets/images/Logo_Galotxas_Femenino.png` | Marca del Navbar | Identidad | No | “Galotxas” | Pendiente: la imagen dice “CLUB GALOTXES Monòver”, divergente de otras grafías |
| `hero.png` | `frontend/src/assets/hero.png` | Sin uso localizado | Genérica | No | Ninguno | Placeholder/no apta para Club |

No hay un registro de autor, licencia, fecha, consentimiento, lugar, evento o
persona representada. Un alt existente describe la intención del JSX, pero no
acredita la imagen.

## 17. Imágenes propuestas

Ninguna imagen es obligatoria para publicar un MVP institucional útil. Si se
usan, se recomienda incorporarlas manualmente bajo un directorio público
estable que el CMS pueda referenciar, no bajo imports privados de React:

| Sección | Descripción | Nombre exacto recomendado | Directorio exacto | Uso | Alt provisional | Derechos/procedencia | Prioridad |
|---|---|---|---|---|---|---|---|
| Quiénes somos | Vista general de las canchas o instalaciones reales | `club-instalaciones.jpg` | `frontend/public/media/club/` | Contextualizar ubicación y actividad | “Vista general de las canchas donde desarrolla su actividad el club.” | Confirmar lugar, autor, licencia y fecha | Opcional recomendada |
| Quiénes somos | Actividad real del club, preferentemente con adultos identificables sólo si consienten | `club-actividad.jpg` | `frontend/public/media/club/` | Mostrar práctica actual | “Personas jugando a Galotxes durante una actividad del club.” | Confirmar identidades, consentimiento y uso de menores | Opcional recomendada |
| Historia | Fotografía histórica documentada | `club-historia.jpg` | `frontend/public/media/club/` | Acompañar un hecho histórico acreditado | Debe redactarse con evento, lugar y fecha reales | Confirmar propietario, autor, fecha y permiso de reproducción | Opcional y condicionada |
| Identidad | Logotipo oficial autorizado | `club-logotipo.png` | `frontend/public/media/club/` | Identidad institucional, sólo si aporta respecto de la marca global | “Logotipo oficial de [nombre público aprobado].” | Confirmar versión oficial, titular y autorización | Opcional y condicionada |

Los nombres evitan fijar `Galotxes`/`Galotxas` o `Monóvar`/`Monòver` antes de
resolver la identidad. Si se reutiliza una imagen actual, deberá revisarse y
colocarse manualmente en la ruta pública estable sólo durante 7C y sin borrar
el original hasta cerrar la paridad.

## 18. Información aportada

| Información aportada | Confirmada en repo | Compatible | Conflicto | Falta evidencia | Destino |
|---|---|---|---|---|---|
| `Club Galotxes Monóvar` | No; sólo hay variantes | Parcialmente | Sí: `Galotxas`, `Galotxetes`, `Galotxes`, `Monóvar` y `Monòver` | Nombre legal/público e idioma | CMS + identidad global aprobada |
| Fundación 31-03-1980 | No | Sí, como dato editorial posible | Ninguno probado | Acta, publicación o validación responsable | CMS Quiénes somos |
| Presidente Jorge Sánchez Romero | No | Sí, como dato editorial posible | El JSX mantiene un placeholder, no otro presidente | Cargo vigente y consentimiento | CMS Quiénes somos, si se decide publicar |
| `clubgalotxesmonover@hotmail.com` | No | Sí | Ninguno probado | Oficialidad, responsable y capacidad de atención | CMS Contacto |
| `687 524 083` | No | Sí | Ninguno probado | Titularidad, consentimiento y horario | CMS Contacto |
| Centro Polideportivo de Monóvar | Parcial: el JSX menciona el Polideportivo Municipal | Sí | Denominación no coincidente exactamente | Nombre y dirección exactos | CMS Quiénes somos/Contacto |
| “Cualquier hora del día” | No | Técnicamente sí | Riesgo frente a capacidad operativa real | Compromiso de atención | CMS Contacto u omisión |
| Instagram “Galotxes Monóvar” | No | Sí | Ninguno probado | URL HTTPS y oficialidad | CMS Contacto/footer futuro |
| Facebook “Galotxes Monóvar” | No | Sí | Ninguno probado | URL HTTPS y oficialidad | CMS Contacto/footer futuro |
| Federación de Pilota Valenciana / FEDPIVAL | No | Posiblemente | Sí: Home usa una fórmula federativa distinta y no acreditada | Nombre oficial, vínculo, competencia y URL | CMS Quiénes somos/Federarse |
| Reglamento público | Sí: Manual/Reglamento interactivo | Sí | No | Confirmar que es la pieza deseada | CMS Documentos como enlace |
| Fotos del club/pistas/jugadores/actividad | No como conjunto identificado; hay otros assets | Sí | Algunas imágenes actuales muestran otra identidad | Archivos, selección, derechos y consentimientos | CMS por URL pública estable |
| Texto histórico de un fundador | No; tampoco se localizó por nombres históricos relevantes | Sí | No | Reaportar texto, autoría, fuente y permiso | CMS Quiénes somos |

Los datos no encontrados no se rechazan: conservan la categoría “aportados por
el usuario” hasta que una persona responsable confirme su literalidad,
vigencia y publicación.

## 19. Conflictos

1. **Nombre y grafía:** el usuario aporta `Club Galotxes Monóvar`; `/nosotros`
   usa `Club de Galotxetes de Monóvar`; el logo visible dice `CLUB GALOTXES
   Monòver`; Knowledge y el producto usan `Galotxas`. No son equivalentes por
   defecto.
2. **Municipio:** conviven `Monóvar` y `Monòver`; no se ha definido idioma,
   denominación legal ni regla de presentación.
3. **Identidad federativa:** el usuario aporta Federación de Pilota Valenciana
   y `FEDPIVAL`; Home muestra “Federación de Galotxas - Monóvar y comarca”. La
   relación y ambas denominaciones carecen de fuente en el repo.
4. **Quiénes somos:** la junta del JSX es placeholder, mientras el usuario
   aporta una presidencia concreta. No debe sustituirse automáticamente.
5. **Imágenes:** al menos `escuela_grupo.png` identifica visualmente a Alzira;
   otras imágenes con personas no acreditan club, contexto o consentimientos.
6. **Historia:** el JSX ofrece un relato general no fechado y el usuario indica
   material de un fundador que no está en el repositorio. No puede acreditarse
   paridad histórica.
7. **Contacto:** “cualquier hora del día” puede contradecir la capacidad real de
   respuesta; el CMS tampoco permite hoy enlaces `mailto:`/`tel:`.
8. **Documentos:** “reglamento” puede significar el Manual interactivo, un PDF
   oficial o un documento federativo. Sólo el primero está identificado.

No se detectó una contradicción con la arquitectura aprobada; no procede un ADR
nuevo. Los conflictos son editoriales, de contenido o de contrato menor.

## 20. Duplicidades

- Quiénes somos existe como JSX en `/nosotros` y como página CMS genérica
  `nosotros`; Home añade claims institucionales independientes.
- Las reglas pueden duplicarse si Documentos copia el Manual en vez de
  enlazarlo; queda prohibido hacerlo.
- El logo, el nombre del producto, el H1 de `/nosotros` y el footer local de Home
  expresan identidades diferentes sin una fuente aprobada.
- `/contenidos/{slug}` expone contenido CMS por URL técnica y las futuras rutas
  Club ofrecerán fachadas canónicas sobre la misma página. Deben compartir una
  carga, no crear copias.
- Contacto general del club y contacto operativo de Escuela son fuentes
  distintas; sólo se compartirán si la entidad lo decide explícitamente.

La duplicidad se resuelve por consolidación en CMS y aliases conservadores, no
copiando el contenido a nuevos componentes React.

## 21. Información faltante

| Grupo | Información mínima faltante | Prioridad |
|---|---|---|
| 1. Identidad oficial | Nombre legal, nombre público literal, denominación corta, grafía del juego y del municipio, identidad/logo autorizado | Bloqueante |
| 2. Quiénes somos | Presentación aprobada, actividad vigente, fuente de la fundación, texto histórico reaportado, organización que se desea publicar y responsable | Bloqueante |
| 3. Contacto | Confirmación pública de email/teléfono, ubicación/dirección exacta, política de horario, responsable de atención y URLs sociales | Canales y horario: bloqueante; mapa/redes: opcional |
| 4. Federarse | Público destinatario, organismo/relación, pasos, requisitos, documentos, licencia/seguro, enlaces, contacto, vigencia y responsable | Bloqueante |
| 5. Documentos | Confirmación de que el Manual cubre el reglamento; cualquier pieza adicional con URL/archivo, versión, fecha, vigencia, responsable y accesibilidad | Bloqueante para inventario completo; el Manual permite mínimo |
| 6. Imágenes | Archivos finales, selección por página, captions y alt basados en hechos | Recomendable; no bloquea una página textual |
| 7. Derechos y procedencia | Autor/propietario, licencia o permiso, fecha/lugar, consentimiento de personas y menores, autorización del logo | Bloqueante para cada imagen usada |
| 8. Revisión editorial | Responsable editorial, aprobador, fecha de revisión, próxima revisión y aceptación de paridad | Bloqueante |

## 22. Readiness por página

| Página | Código disponible | CMS disponible | Contenido mínimo | Imágenes | Gate principal | Estado |
|---|---|---|---|---|---|---|
| Quiénes somos | Cliente, renderer y componentes reutilizables | Sí: slug `nosotros` sembrable | No acreditado; hay datos parciales y copy conflictivo | No acreditadas | Identidad, historia, aprobación y paridad | Requiere decisión humana y está bloqueada por datos |
| Contacto | Cliente/renderer; enlaces clicables limitados | Sí, pero slug `contacto` debe crearse manualmente | Email, teléfono y ubicación aportados, no confirmados | No necesarias | Oficialidad, atención, horario y decisión sobre `mailto:`/`tel:` | Lista con contenido mínimo tras decisión humana |
| Federarse | Cliente/renderer suficientes | Sí: slug sembrable | No existe proceso real | No necesarias | Proceso, organismo, enlaces, vigencia y responsable | Bloqueada por datos |
| Documentos | Cliente/renderer y Manual real | Sí: slug sembrable | Enlace al Manual disponible | No necesarias | Confirmar alcance del “reglamento” y revisión | Lista con contenido mínimo |

No hay bloqueo de migración o esquema para el MVP textual. Sí hay una posible
ampliación contractual pequeña para enlaces `mailto:`/`tel:` y mejoras frontend
comunes. Ninguna justifica publicar sin superar antes los gates editoriales.

## 23. Gates

### Bloqueantes antes de escribir código público

- aprobar literalmente nombre legal/público y grafías;
- designar responsable editorial y aceptar la fuente CMS;
- aprobar contenido mínimo de las cuatro páginas;
- aportar el proceso vigente de Federarse;
- confirmar canales de Contacto y política de horario;
- definir qué representa “reglamento” y qué enlaces se publicarán;
- decidir si 7C debe admitir `mailto:`/`tel:` o presentar los canales como texto;
- acordar criterios de paridad y conservación de `/nosotros`.

### Bloqueantes antes de usar imágenes

- archivos finales identificados;
- procedencia, titularidad/licencia y autorización;
- consentimiento de personas y tratamiento específico de menores;
- lugar, evento, fecha y alt verificables;
- logo y nombre visual aprobados.

### Bloqueantes antes de publicar

- páginas cargadas primero en borrador y revisadas;
- vigencia, responsable y enlaces comprobados;
- paridad de `/nosotros` aceptada sin copiar placeholders;
- rutas, publicación/ocultación, metadatos, accesibilidad, responsive y
  compatibilidad cubiertos por tests;
- aceptación humana final.

Redes sociales, mapa, otros cargos e imágenes decorativas son omitibles. Su
ausencia no debe sustituirse por placeholders.

## 24. Recomendación

Se elige una única estrategia: **B, dividir 7C en 7C.1 y 7C.2**.

### 7C.1 — Cierre editorial y preparación privada

- resolver las preguntas bloqueantes de identidad, Contacto, Federarse y
  Documentos;
- reaportar, revisar y aprobar el material histórico;
- seleccionar imágenes y acreditar derechos sólo si se usarán;
- decidir el ajuste menor de enlaces de contacto;
- crear o completar manualmente las cuatro páginas CMS en **borrador**;
- realizar revisión editorial y definir la paridad esperada, sin registrar
  rutas públicas nuevas ni despublicar legados.

### 7C.2 — Implementación técnica y publicación controlada

- implementar las fachadas canónicas, estados remotos, metadatos,
  accesibilidad, responsive y carga diferida;
- añadir aliases temporales sólo después de demostrar equivalencia;
- ejecutar pruebas unitarias, de integración y E2E;
- publicar las páginas tras aceptación y conservar el legado hasta el bloque de
  retirada acordado.

No se recomienda C, infraestructura/rutas aisladas, porque violaría el gate de
no crear destinos sin contenido funcional. Tampoco A, porque Quiénes somos y
Federarse carecen de contenido acreditado. D sería innecesariamente absoluta:
el soporte CMS ya permite preparar en privado y Documentos dispone de un mínimo
real reutilizable.

## 25. Alcance futuro de 7C

### Implementación automática por Codex en 7C.2

- registrar las cuatro rutas canónicas y resolver cada una contra su slug CMS;
- crear una configuración única de fachadas Club, sin cuerpo editorial JSX;
- incorporar loading, error con reintento, 404, vacío y contenido;
- usar metadatos básicos y heading único;
- adaptar navegación interna del renderer a React Router;
- ampliar `mailto:`/`tel:` sólo si la decisión humana lo exige, con validación,
  allowlist, seguridad y tests;
- cargar de forma diferida la rama;
- preservar `/contenidos`, `academy`, Prensa y Federaciones;
- incorporar aliases temporales de `/nosotros` y `/contenidos/{slug}` acordados
  sin redirects permanentes;
- comparar y documentar la paridad de `/nosotros`;
- cubrir teclado, foco, semántica, imágenes, 320–1440 px y zoom 200 %;
- actualizar documentación técnica afectada.

### Acciones manuales

- confirmar todas las decisiones editoriales;
- crear/completar páginas y bloques en borrador mediante Blade;
- colocar imágenes aprobadas bajo `frontend/public/media/club/` con los nombres
  acordados, si se usan;
- alojar externamente cualquier documento descargable y aportar URL estable;
- revisar preview/contenido, enlaces, derechos, cargos, vigencia y publicación;
- aceptar paridad y autorizar el paso a público.

### Testing futuro

- backend Feature para publicación/ocultación, allowlists y cualquier cambio de
  protocolos de enlace;
- frontend RTL para configuración de rutas, servicio/hook, estados remotos,
  renderer, metadatos, headings, enlaces internos y 404;
- integración de las cuatro fachadas con slugs y aliases;
- E2E con MariaDB/CMS real para contenido público, borrador/ausente, reintento,
  navegación directa, recarga, responsive, teclado y compatibilidad;
- regresión de `/contenidos`, `/nosotros`, `academy`, Knowledge y rutas
  deportivas.

### Fuera de alcance

Navbar y footer finales de 7D, Home, páginas legales, formulario de Contacto,
uploads, multimedia administrada, API específica de Club, migraciones de datos,
redirects permanentes, canonical/SEO completo, sitemap/robots, retirada física
del legado, migración de `academy`, noticias, Prensa/Federaciones canónicas y
cambios de Knowledge.

## 26. Preguntas para el usuario

### 1. Identidad oficial

1. **Bloqueante:** ¿Cuál es la denominación legal exacta y qué documento o
   responsable la acredita?
2. **Bloqueante:** ¿Qué nombre público debe mostrarse literalmente y cuál es su
   forma corta?
3. **Bloqueante:** ¿Deben usarse `Galotxes`, `Galotxas` o `Galotxetes`, y
   `Monóvar`, `Monòver` o ambas según idioma/contexto?
4. **Recomendable:** ¿El logo actual es oficial y está autorizado? ¿Cuál es su
   archivo maestro vigente?

### 2. Quiénes somos

5. **Bloqueante:** ¿Puede reaportarse el texto histórico del fundador con
   nombre de autor, fecha aproximada y permiso de publicación?
6. **Bloqueante:** ¿Qué fuente o responsable confirma la fundación el 31 de
   marzo de 1980?
7. **Bloqueante:** ¿Qué presentación, propósito y actividades actuales quedan
   aprobados y cuáles de los claims de `/nosotros` deben descartarse?
8. **Bloqueante:** ¿Se confirma a Jorge Sánchez Romero como presidente vigente
   y se autoriza publicar nombre y cargo?
9. **Opcional:** ¿Deben publicarse otros cargos? Si sí, ¿cuáles y con qué
   consentimiento?

### 3. Contacto

10. **Bloqueante:** ¿Se confirman como oficiales, atendidos y publicables el
    correo y teléfono aportados?
11. **Bloqueante:** ¿Se publicará sin horario, como “atención según
    disponibilidad” o con qué horario real? No se publicará “cualquier hora del
    día” sin confirmación expresa.
12. **Bloqueante:** ¿Cuál es el nombre y, si procede, la dirección exacta de la
    ubicación?
13. **Recomendable:** ¿Se desean correo y teléfono clicables (`mailto:`/`tel:`)
    o basta mostrarlos como texto?
14. **Opcional:** ¿Cuál es la URL HTTPS exacta de Instagram, de Facebook y de un
    mapa oficial?

### 4. Federarse

15. **Bloqueante:** ¿Quién puede federarse a través del club y cuál es el
    proceso vigente paso a paso?
16. **Bloqueante:** ¿Cuál es el nombre oficial del organismo, qué significa la
    relación del club con él y puede aportarse su URL oficial?
17. **Bloqueante:** ¿Qué requisitos, documentación, licencia, seguro, plazos y
    canal responsable aplican?
18. **Recomendable:** ¿Hay costes publicables? Si los hay, ¿importe, fecha de
    vigencia y responsable de revisión?

### 5. Documentos

19. **Bloqueante:** ¿“Reglamento” designa el Manual interactivo ya publicado en
    `/aprende-a-jugar/manual`?
20. **Recomendable:** ¿Existen además PDFs o enlaces federativos públicos? Para
    cada uno: nombre, propósito, URL/archivo, versión, fecha, vigencia,
    responsable, accesibilidad, tamaño y formato.
21. **Opcional:** ¿Hay documentos internos que deban registrarse sólo para
    excluirlos expresamente de publicación?

### 6. Imágenes

22. **Recomendable:** ¿Qué archivos concretos se proponen para instalaciones,
    actividad, historia y logo, y qué hecho representa cada uno?
23. **Opcional:** ¿Se desea un MVP sólo textual mientras se revisan las fotos?

### 7. Derechos y procedencia

24. **Bloqueante si se usa cada imagen:** ¿Quién es autor/titular, qué permiso o
    licencia existe, dónde/cuándo se tomó y quién autoriza su publicación?
25. **Bloqueante si aparecen personas:** ¿Existe consentimiento suficiente,
    especialmente para menores, y debe ocultarse alguna identidad?

### 8. Revisión editorial

26. **Bloqueante:** ¿Quién será responsable editorial y quién dará la aceptación
    final de las cuatro páginas?
27. **Bloqueante:** ¿Qué fecha de revisión, periodicidad o evento de revisión se
    aplicará a identidad, Contacto, Federarse y Documentos?
28. **Bloqueante:** ¿Se acepta la estrategia 7C.1/7C.2 y el criterio de mantener
    `/nosotros` y `/contenidos/*` hasta acreditar paridad?

## 27. Criterios para comenzar implementación

7C.1 puede comenzar cuando exista una respuesta explícita a las preguntas
bloqueantes, se haya designado responsable editorial y se acepte trabajar en
borrador. Una imagen omitida no bloquea una página textual; una imagen incluida
sí exige todos sus derechos y consentimientos.

7C.2 no debe comenzar hasta que:

1. los cuatro contenidos mínimos estén cargados o aprobados para carga en CMS;
2. nombre, grafías y relación federativa no tengan contradicciones abiertas;
3. Contacto tenga al menos un canal oficial y una expectativa de atención real;
4. Federarse describa un proceso vigente o se haya decidido excluir la página
   de la publicación, lo que obligaría a revisar el alcance contractual antes de
   crear su ruta;
5. Documentos identifique el Manual y cualquier enlace adicional sin duplicar
   Knowledge;
6. cada imagen elegida tenga procedencia, derechos, consentimiento y alt;
7. esté decidido el tratamiento técnico de `mailto:`/`tel:`;
8. se hayan aprobado matriz de paridad, aliases temporales y conservación del
   legado;
9. estén aceptados alcance, pruebas y acciones manuales de la sección 25.

7C sólo podrá cerrarse tras la implementación y validación de 7C.2. Esta
auditoría completa 7C.0, mantiene 7C y el MVP pendientes y no acredita contenido
ni comportamiento público nuevos.
