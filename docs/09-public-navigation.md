# Contrato de navegación y rutas públicas — Galotxas

## 1. Objetivo

Este documento fija el contrato de arquitectura de información pública y
registra su aplicación funcional. Parte de la auditoría de Fase 3A, refleja la
navegación de Fase 3B, el sistema común de landings de Fase 3C, la Fase 4 de
Competición, Aprende a jugar cerrada en Fase 5C y la Escuela pública cerrada en
Fase 6C. Fase 7B sustituye la topología plana inicial por el contrato agrupado
de ADR-033. 7C.2 implementa las cuatro rutas hijas de Club y 7D.1 aplica el
disclosure del Navbar, Home y footer estructural. 7D.2C1 añade al footer y al
router los tres destinos legales versionados; la activación productiva de
7D.2C2B completa la primera capa y operación técnica de Contacto sin cambiar
el árbol ni activar producción. Fase 7F.2A aplica después ADR-042 en `develop`:
Competición pasa a ser un tercer disclosure y `/rankings` consolida los cuatro
ámbitos deportivos sin crear rutas o contratos API.

Las fases 3B, 3C, 4A–4C y 5B–5C modifican únicamente compilación/frontend, sus pruebas y la documentación: no cambian backend, CMS, contenido canónico, despliegue ni redirects. 7C.2 añade fachadas sobre el CMS y mantiene la publicación editorial separada por entorno; una ruta documental no se considera implementada hasta existir con fuente verificable y pruebas.

## 2. Principios de navegación

1. El primer nivel editorial tiene un enlace, Inicio, y tres grupos de
   revelación, Competición, Aprende y Club; Cuenta permanece separada.
2. Identidad, acceso, registro, Mi Panel y cierre de sesión forman una zona de cuenta separada del menú editorial.
3. Una ruta no se publica para mostrar un placeholder vacío o un mensaje genérico de próxima disponibilidad.
4. React puede definir nombres, labels, ayudas funcionales y estructura, pero no será fuente de contenido administrable o conocimiento canónico.
5. Las rutas funcionales de competición se conservan mientras tengan consumidores, contratos y valor de compatibilidad. No es necesario moverlas bajo `/competicion`.
6. `/contenidos` es una infraestructura pública del CMS heredada, no un área editorial de primer nivel.
7. Cada migración de URL requiere contenido equivalente, inventario de enlaces, estrategia SEO, pruebas y una decisión de redirect en la capa adecuada.
8. Desktop y móvil deben exponer la misma arquitectura de información y los mismos permisos.
9. El backend decide publicación, visibilidad y reglas deportivas antes de serializar; el frontend no filtra borradores ni reconstruye visibilidad efectiva.
10. Las rutas autenticadas de participantes pueden mostrar información propia aunque su rama deportiva no forme parte de la experiencia pública, conforme al contrato backend actual.

## 2.1. Arquitectura bilingüe aprobada para 6.H

Esta gate fue aprobada el 2026-08-29, pero no describe rutas disponibles hoy.
El inventario de las secciones siguientes continúa reflejando únicamente el
router vigente.

- El castellano (`es`) es el idioma fuente y por defecto. Sus URLs actuales se
  conservan sin prefijo: `/`, `/noticias`, `/aprende-a-jugar` y
  `/aprende-a-jugar/manual`, entre otras.
- El valenciano se identifica internamente como `ca-valencia` cuando proceda y
  usará el prefijo público `/va/`: `/va/`, `/va/noticias`,
  `/va/aprende-a-jugar` y `/va/aprende-a-jugar/manual`.
- `va` es un prefijo de URL, no el código lingüístico interno. No se migrará el
  castellano a `/es/`.
- En la primera implementación bilingüe se compartirán los slugs. Por ejemplo,
  `/noticias/nueva-web` y `/va/noticias/nueva-web` identificarán versiones
  lingüísticas equivalentes. Traducir slugs queda como evolución opcional.
- Navbar desktop y móvil incorporarán un selector explícito `ES | VA`, o una
  alternativa accesible `Castellano / Valencià`. Una bandera nunca será el
  único identificador. El selector navegará a la ruta equivalente cuando esa
  traducción exista y no habrá redirección automática basada sólo en el idioma
  del navegador.
- Cada versión publicada exigirá canonical propio, `hreflang`, equivalencia
  castellano/valenciano, `x-default` cuando corresponda y sitemap coherente. No
  se publicará como valenciana una página cuyo contenido principal continúe en
  castellano.
- Una página o noticia podrá existir temporalmente sólo en castellano; el
  lanzamiento bilingüe no exige traducir todo el histórico.
- `/admin` queda fuera del contrato i18n: Blade continuará en castellano y sin
  selector. La persistencia futura de traducciones administrables podrá vivir
  en backend sin convertir la interfaz administrativa en bilingüe.

ADR-046 contiene la decisión completa. El contrato técnico de equivalencias,
fallbacks y SEO se cerrará durante la auditoría de implementación de 6.H sin
duplicar CMS, Noticias o Knowledge en JSX.

## 3. Arquitectura de información de primer nivel

El contrato vigente tras ADR-042 es:

```text
Inicio                                  /
Competición                             disclosure
├── Vista general                       /competicion
├── Campeonatos                         /torneos
└── Rankings                            /rankings
Aprende                                 disclosure
├── Aprende a jugar                     /aprende-a-jugar
├── Manual y reglas                     /aprende-a-jugar/manual
└── Escuela de Galotxas                 /escuela
Club                                    disclosure
├── Quiénes somos                       /club/quienes-somos
├── Contacto                            /club/contacto
├── Federarse                           /club/federarse
└── Documentos                          /club/documentos
Cuenta                                  grupo separado
```

| Orden | Control | Tipo | Estado activo |
|---:|---|---|---|
| 1 | Inicio | Enlace | Sólo `/` |
| 2 | Competición | Disclosure | Landing y rutas deportivas funcionales |
| 3 | Aprende | Disclosure | Cualquiera de sus tres ramas |
| 4 | Club | Disclosure | Cualquiera de sus cuatro rutas canónicas |
| Separado | Cuenta | Grupo de sesión | Nunca activa el menú editorial |

Los padres disclosure no tienen ruta. En particular, no se implementará una
landing `/club` sin propósito independiente. Cada enlace hijo exacto utiliza
`aria-current="page"`; los descendientes activan visualmente su rama sin
anunciar otra URL como página actual.

El Navbar actual enlaza Inicio y revela mediante botones los hijos de
Competición, Aprende y Club. El footer es global y usa las cuatro rutas Club
canónicas y un grupo legal con tres documentos reales. Cuenta permanece
separada; Prensa y Federaciones no se publican como enlaces vacíos.

## 4. Inventario de rutas actuales

`frontend/src/App.jsx` utiliza `BrowserRouter`, `Routes` y `Route`. Registra rutas explícitas planas y un wildcard final, sin nesting, loaders o acciones de router. Las tres páginas de Aprende, la landing de Escuela y la feature Club se importan mediante `React.lazy` con fallbacks `Suspense` propios; las demás rutas conservan su carga anterior. Navbar se renderiza fuera de `Routes` y aparece también ante una URL desconocida.

| Ruta | Componente | Acceso | Fuente de datos | Enlaces entrantes verificados | Estado y comportamiento sin datos |
|---|---|---|---|---|---|
| `/` | `pages/Home/Home.jsx` | Público | Estructura y copy de interfaz en React | Logo, Navbar | `h1` aprobado, dos CTAs y cuatro recorridos con destinos reales; sin carga remota o Knowledge. |
| `/competicion` | `pages/Competition/CompetitionPage.jsx` | Público | `GET /seasons` y `GET /rankings/all-time` mediante servicios y hooks | Navbar y Home | Canónica y cerrada en 4C. Prioriza propósito, Torneos, temporadas/campeonatos y ranking histórico, sin duplicar el acceso a Rankings. |
| `/aprende-a-jugar` | `pages/Learn/LearnPage.jsx` diferida | Público | Copy breve de interfaz y recuentos derivados del repositorio público | Disclosure Aprende y Home | Landing funcional con 40 documentos, cuatro colecciones y acceso al Manual, sin placeholders de Historia, Escuela, cursos o vídeos. |
| `/aprende-a-jugar/manual` | `pages/Learn/ManualPage.jsx` diferida | Público | Repositorio local sobre `public-knowledge.json` | Landing y contexto de documentos | Agrupa cuatro colecciones, ofrece anchors locales y enlaza 40 documentos en orden canónico. |
| `/aprende-a-jugar/manual/reglamento/:slug` | `pages/Learn/KnowledgeDocumentPage.jsx` diferida | Público | Repositorio Knowledge, headings y bloques seguros | Manual, vecinos y referencias | Detalle con contexto, tabla de contenidos, deep links y vecinos de Reglamento; slug ausente o no público conserva URL y muestra la 404. |
| `/aprende-a-jugar/manual/conceptos/:group/:slug` | `pages/Learn/KnowledgeDocumentPage.jsx` diferida | Público | Repositorio Knowledge, headings y bloques seguros | Manual, vecinos y referencias | Admite sólo `elementos`, `personas` y `juego`; navegación y vecinos no cruzan grupos, y un grupo o slug inválido muestra la 404. |
| `/escuela` | `features/school/SchoolPage.jsx` diferida | Público | `GET /school` y `POST /school/enrollments` mediante servicio y hook locales | Disclosure Aprende, Home | Landing funcional. `data: null` y cierre son estados válidos; el formulario aparece sólo con apertura efectiva. |
| `/club/quienes-somos`, `/club/contacto`, `/club/federarse`, `/club/documentos` | `features/club/ClubPage.jsx` diferida | Público | Página CMS publicada del slug cerrado; Contacto suma config y POST condicionados | Disclosure Club, Home y footer | Fachadas funcionales con carga, error/retry, 404, inválido y vacío. `/club` y descendientes desconocidos usan wildcard. |
| `/legal/aviso-legal`, `/legal/privacidad`, `/legal/cookies` | `features/legal/LegalPage.jsx` diferida | Público | `public-legal.json`, generado desde `legal/` | Footer y navegación legal interna | Tres rutas exactas; versión, fecha y metadatos. `/legal` y descendientes desconocidos usan wildcard. Sin API, CMS o Knowledge. |
| `/nosotros` | `pages/Nosotros/Nosotros.jsx` | Público | Contenido estático en React | Ningún enlace interno actual localizado | Duplicada y heredada; conserva contenido único como material de migración. |
| `/torneos` | `pages/Torneos/TournamentList.jsx` | Público | `GET /championships` y `GET /seasons` | Landing de Competición, Mi Panel, detalles | Funcional secundaria. Distingue carga, error con retry y vacío filtrado; cada tarjeta tiene una única acción al detalle. |
| `/torneos/:championshipId` | `pages/Torneos/TournamentDetail.jsx` | Público; acciones de inscripción autenticadas | Campeonato, ranking e inscripción desde API | Tarjetas de torneo, Mi Panel, regreso desde categoría | Funcional secundaria. Campeonato y ranking tienen disponibilidad independiente; las tarjetas enlazan resumen, clasificación y calendario, y desde la navegación local de categoría se accede también a Copa. |
| `/categories/:categoryId` | `pages/Torneos/CategoryDetail.jsx` | Público | Detalle de categoría | Detalle de torneo y navegación contextual | Resumen de entidad y padres. No descarga ni duplica standings o schedule. |
| `/categories/:categoryId/standings` | `pages/Standings.jsx` | Público | Categoría y clasificación | Navegación cruzada desde schedule; `CategoryCard` no montada | Funcional secundaria. Tiene navegación local, pero su otro consumidor localizado pertenece a una Home huérfana. |
| `/categories/:categoryId/schedule` | `pages/Schedule.jsx` | Público | Categoría y rondas de Liga del calendario | Navegación local de categoría; smoke E2E | Funcional secundaria. Distingue carga, error, vacío y contenido y no renderiza Copa. |
| `/categories/:categoryId/cup` | `pages/Cup/CupPage.jsx` diferida | Público | Categoría y rondas estructurales de Copa del schedule común | Navegación local de categoría | Vista dedicada con carga, error/retry, vacío, semifinales, Final, 3.º/4.º y campeón oficial; no infiere datos legados ambiguos. |
| `/matches/:matchId` | `pages/MatchDetails.jsx` | Público; workflow ampliado para participante autenticado | Partido público y, con sesión, workflow de resultado | Tarjetas de partido y acciones pendientes | Funcional secundaria. Regresa a categoría si existe contexto o a `/torneos`; el backend responde 404 si la rama no es pública para un visitante. |
| `/rankings` | `pages/Rankings/Rankings.jsx` | Público | Rankings histórico, por temporada, campeonato y categoría mediante los endpoints deportivos existentes | Disclosure y bloque histórico de Competición | Centro funcional con cuatro pestañas accesibles, selectores jerárquicos, orden backend, invalidación de respuestas obsoletas y estados recuperables. |
| `/contenidos` | `pages/CmsPageIndex/CmsPageIndex.jsx` | Público | `GET /cms/pages` | Acceso directo y enlaces heredados externos | Técnica y heredada. Lista toda página publicable, sin agrupación por área pública. |
| `/contenidos/:slug` | `pages/CmsPage/CmsPage.jsx` | Público | `GET /cms/pages/{slug}` | Índice CMS, URLs de Resource y accesos directos | Técnica y heredada. Muestra carga, 404 de vista, error y bloques, pero la SPA sigue entregando el documento base. |
| `/login` | `pages/Login.jsx` | Público/anónimo | Auth API y estado de retorno | Zona de cuenta, páginas de auth, partido | Ruta de cuenta. Un usuario ya autenticado se redirige a `/player`. |
| `/register` | `pages/Register.jsx` | Público/anónimo | Auth y perfil API | Login | Ruta de cuenta. Tras éxito fuerza navegación a `/player`. |
| `/forgot-password` | `pages/ForgotPassword.jsx` | Público/anónimo | Auth API | Login y reset inválido | Ruta de cuenta. |
| `/reset-password` | `pages/ResetPassword.jsx` | Público/anónimo | Query `email` y `token`; Auth API | Enlace enviado por correo | Ruta de cuenta con entrada externa prevista. Usa `h2`, no `h1`, y redirige a login tras éxito. |
| `/player` | `pages/Dashboard.jsx` dentro de `ProtectedRoute` | Autenticado | Endpoints `/me`, perfil, inscripciones, partidos, calendario, rankings y acciones | Zona de cuenta, login, registro e inscripción | Mi Panel. El visitante se redirige a `/login`; no pertenece al menú editorial. |
| `*` | `pages/NotFound/NotFoundPage.jsx` | Público | Estructura local | Cualquier URL React no reconocida | Fallback accesible con `h1` y enlaces de recuperación; no redirige ni cambia el estado HTTP inicial del documento SPA. |

No existe ruta React administrativa. El panel administrador es Blade bajo `/admin`.

Existe una ruta wildcard React final. Una URL no reconocida conserva Navbar y muestra una 404 dentro del único `<main>` global, sin interceptar rutas dinámicas válidas. El hosting puede continuar devolviendo `index.html` con HTTP 200: la respuesta HTTP real queda pendiente. `ProtectedRoute` conserva una rama `requireAdmin` sin consumidores que enviaría a `/dashboard`; no se ha creado esa ruta y la rama continúa como deuda latente.

### Inventario de navegación actual

| Ubicación | Control | Destino o hijos | Estado |
|---|---|---|---|
| Navbar, logo | Galotxas | `/` | Cierra todo y vuelve a Inicio. |
| Navbar editorial | Inicio | `/` | `page` sólo en `/`. |
| Navbar editorial | Competición | Vista general, Campeonatos, Rankings | Disclosure sin ruta; activo en `/competicion`, `/torneos`, `/rankings`, categorías y partidos. Sólo los tres destinos exactos marcan su hijo con `page`. |
| Navbar editorial | Aprende | Aprende a jugar, Manual y reglas, Escuela | Disclosure sin ruta; activo en `/aprende-a-jugar/*` y `/escuela/*`. |
| Navbar editorial | Club | Quiénes somos, Contacto, Federarse, Documentos | Disclosure sin ruta; activo en `/club/*`. |
| Cuenta anónima | Iniciar sesión | `/login` | Grupo hermano, nunca activa el menú. |
| Cuenta autenticada | Mi Panel / Salir | `/player` / `logout` | Conserva saludo e identidad actual. |
| Menú móvil | Menú | `public-navigation` | Mismo árbol, cierre total al navegar y Escape en dos niveles. |
| Home | Ver competición / Aprender a jugar | `/competicion` / `/aprende-a-jugar` | CTAs principales. |
| Home | Cuatro recorridos | Competición, Aprende/Manual, Escuela, Quiénes somos/Contacto | Todos disponen de destino real. |
| Footer global | Club | Cuatro rutas `/club/...` | Se monta tras el `main` en todas las rutas. |
| Footer global | Facebook / Instagram | URLs externas confirmadas | Nueva pestaña, `noopener noreferrer` e indicación accesible. |

Los destinos deportivos, CMS, auth y Mi Panel conservan sus enlaces internos
previos. No hay breadcrumbs globales. La navegación contextual del Manual y de
Competición sigue siendo local a cada experiencia. La cabecera se convierte en
menú colapsable a 1024 px; desktop y móvil consumen el mismo árbol y los
paneles cerrados usan `hidden`.

## 5. Clasificación de rutas

| Clasificación | Rutas | Estado tras Fase 6C |
|---|---|---|
| Canónicas implementadas | `/`, `/competicion`, `/aprende-a-jugar`, `/aprende-a-jugar/manual`, `/escuela` | Inicio, Competición y las tres hijas futuras de Aprende ya tienen experiencias funcionales. |
| Canónicas Club implementadas | `/club/quienes-somos`, `/club/contacto`, `/club/federarse`, `/club/documentos` | Registradas en 7C.2 sobre CMS publicado y descubribles desde Navbar/footer en 7D.1. |
| Funcionales secundarias | `/torneos`, `/torneos/:championshipId`, `/categories/:categoryId`, sus rutas de standings/schedule/cup, `/matches/:matchId`, `/rankings` | Conservar rutas y contratos. Relacionarlas semánticamente con Competición. |
| Cuenta | `/login`, `/register`, `/forgot-password`, `/reset-password`, `/player` | Conservar separadas del menú editorial. |
| Técnica heredada | `/contenidos`, `/contenidos/:slug` | Retirar del primer nivel cuando existan destinos canónicos, pero mantener acceso y CMS hasta completar la migración. |
| Duplicada | `/nosotros` y `/contenidos/nosotros` | Elegir CMS como fuente futura; conservar ambas hasta migración y paridad verificadas. |
| Vistas complementarias | `/categories/:id` frente a `/categories/:id/standings`, `/schedule` y `/cup` | Resumen, clasificación, Liga y Copa quedan separados y conectados sin redirects. |
| Sin consumidor interno | `/nosotros` | Mantener por contenido, posibles marcadores y migración; medir antes de retirar. |
| Rota latente | `/dashboard` como destino de una rama no usada de `ProtectedRoute` | Corregir o eliminar sólo en una fase de código; no afecta al contrato público activo. |
| Fallback/error React | `*` | Implementado como vista accesible; la respuesta HTTP 404 del hosting sigue pendiente. |

También existen módulos React no montados: `pages/Home.jsx` y `CategoryCard`, pares `Schedule`/`useSchedule`, `Standings`/`useStandings`, `ConflictDashboard`/`useConflicts` y `MyMatches`/`useMyMatches`. No son rutas. Deben revisarse como código huérfano antes de reutilizarlos o eliminarlos; `pages/Home.jsx` no es la Home que importa `App.jsx`.

## 6. Contrato de rutas canónicas

| Ruta | Responsabilidad | Fuente de verdad | Contenido inicial mínimo | Subrutas o destinos | Estado actual |
|---|---|---|---|---|---|
| `/` | Home pública y puerta de entrada actual | Estructura y copy de interfaz React | `h1`, introducción, dos CTAs y cuatro recorridos reales | Competición, Aprende/Manual, Escuela y Club | Rediseñada en 7D.1 sin peticiones remotas, Knowledge directo ni CMS duplicado. |
| `/competicion` | Landing funcional de actividad deportiva pública | API pública del dominio Laravel | `h1`, acceso principal, temporadas/campeonatos y preview histórico con estados independientes; sin recalcular reglas | Rama deportiva completa y `/rankings` | Fase 4 completada con 4A–4C. |
| `/aprende-a-jugar` | Entrada divulgativa al Manual, Reglamento y Conceptos | Proyección pública compilada desde `knowledge/` | `h1`, resumen derivado, acceso al Manual y recorrido real; no copy editorial duplicado en JSX | `/manual`, `/manual/reglamento/:slug` y `/manual/conceptos/:group/:slug` | Completada en 5C con 40 documentos, contexto, índice, vecinos, fragmentos y carga diferida. |
| `/escuela` | Escuela permanente, niveles, horarios e inscripción pública | Híbrida: `knowledge/` futuro para pedagogía estable y dominio Laravel específico para operación y solicitudes | `h1`, enlace al Manual, niveles, horarios, ubicaciones, apertura y formulario cuando esté abierto | El MVP no requiere subrutas | Implementada en 6C sobre los contratos 6B.1–6B.4; `academy` permanece independiente. |
| `/club/quienes-somos` | Presentación institucional | CMS `nosotros` | Identidad, propósito, actividad e imágenes aprobados | Alias futuro de `/nosotros`; `/contenidos/nosotros` se conserva | Fachada implementada; paridad y publicación dependen de cada entorno. |
| `/club/contacto` | Canales institucionales y formulario condicionado | CMS `contacto` + `ContactRequest` funcional | Canal oficial, fecha de revisión y formulario sólo tras privacidad/activación | Footer y Club pendientes de 7D | Fachada implementada; formulario oculto por default productivo. |
| `/club/federarse` | Proceso vigente para federarse | CMS `federarse` | Requisitos, organismo, enlaces, contacto y vigencia | `/contenidos/federarse` conservada | Fachada implementada; contenido/publicación dependen de cada entorno. |
| `/club/documentos` | Inventario de documentos vigentes | CMS `documentos` con enlaces | Nombre, propósito, versión, vigencia, formato y responsable | `/contenidos/documentos` conservada | Fachada implementada; contenido/publicación dependen de cada entorno. |

Los namespaces de Aprende a jugar quedan cerrados para el Manual inicial. La
ruta única del MVP de Escuela es `/escuela`; no necesita subrutas hasta que
existan destinos reales con URLs estables. Club es sólo el grupo que revela las
cuatro rutas de la tabla y no registra `/club`. Los ejemplos `/aprende`,
`/manual`, `/contacto`, `/federarse` o `/documentos` en raíz no se implementan
ni se crean como aliases sin evidencia de consumidores.

### Gate específico de Escuela

La etiqueta pública y el H1 son “Escuela de Galotxas”. En la navegación
contractual es la tercera hija de Aprende y su estado activo abarca `/escuela`
y cualquier descendiente futuro. Sólo la landing exacta está registrada:
`/escuela/alumno` y otras subrutas no aprobadas conservan la 404.

Fase 6C verifica para la ruta:

- contenido estable real o, como mínimo, un enlace útil al Manual sin duplicarlo;
- consumo React de la API pública de lectura/escritura ya implementada para niveles, horarios, ubicaciones, apertura y solicitud;
- estados de carga, error, parcial y vacío;
- tests frontend, accesibilidad, responsive y regresión del Navbar.

No se localizó una política de privacidad o texto de aceptación aprobado. Por tanto, React no inventa política, consentimiento o checkbox. Aprobar esos textos y su operación es deuda obligatoria antes de configurar inscripciones abiertas en producción; el default cerrado de `SchoolProgram` permite mantener el formulario ausente hasta entonces.

Escuela se relaciona con Aprende mediante descubrimiento y conocimiento, y con
Club mediante la organización responsable, pero no se integra editorial ni
técnicamente en ninguno. El contrato funcional completo se mantiene en
`12-school-of-galotxas.md`. ADR-033 aprueba su agrupación visual bajo Aprende
sin alterar fuentes, dominio, ruta o contrato.

## 7. Rutas secundarias

| Ruta o familia | Área | Tratamiento |
|---|---|---|
| `/torneos` | Competición | Mantener como listado funcional y destino de `/competicion`. No renombrar por coherencia visual. |
| `/torneos/:championshipId` | Competición | Mantener; conserva inscripciones, ranking y categorías. |
| `/categories/:categoryId` | Competición | Mantener como resumen de la entidad y acceso a vistas dedicadas. |
| `/categories/:categoryId/standings` | Competición | Mantener como URL compartible y única representación completa de clasificación. |
| `/categories/:categoryId/schedule` | Competición | Mantener como URL compartible de calendario y resultados. |
| `/matches/:matchId` | Competición | Mantener; combina consulta pública y workflow autorizado sin exponer datos privados a visitantes. |
| `/rankings` | Competición | Mantener y enlazar desde la landing. “Rankings” deja de ser nombre de primer nivel, no deja de ser funcionalidad. |
| Rutas de cuenta | Cuenta | Mantener fuera de los cuatro controles editoriales y conservar los retornos de autenticación. |
| `/player` | Cuenta | Mantener como Mi Panel, no como subruta editorial de Competición. |

“Competición” será el único nombre de primer nivel para el dominio deportivo. “Torneos”, “Campeonatos”, “Calendarios”, “Clasificaciones” y “Rankings” son labels funcionales secundarios.

## 8. Rutas técnicas y heredadas

`/contenidos` y `/contenidos/:slug` son útiles para consumir el CMS actual, pero revelan la estructura técnica en la URL y el índice mezcla cualquier página publicada sin taxonomía de área. Deben desaparecer del primer nivel cuando las landings canónicas tengan contenido, no antes.

El contrato API refuerza hoy esa URL: `PublicCmsPageSummaryResource` genera `url: /contenidos/{slug}`. Una futura fachada bajo `/club` o `/escuela` requerirá coordinar Resource, enlaces guardados en bloques, React, tests, canonical y compatibilidad. No basta con cambiar Navbar.

La ruta estática `/nosotros` es heredada y duplicada, pero no está vacía. Su ausencia de enlaces internos no demuestra ausencia de tráfico externo ni autoriza su borrado.

`academy` es un slug CMS sembrado y formó parte del Navbar anterior a 3B. Home
ya sustituyó esa etiqueta por un enlace funcional a Escuela, sin alterar la
página CMS. No es sinónimo contractual de Escuela de Galotxas ni de Aprende a
jugar. Se conservará sin reinterpretación automática hasta inventariar su
contenido real. Después se clasificarán y migrarán las piezas útiles, se
comprobarán consumidores y paridad, y sólo entonces podrán decidirse
despublicación, canonical o redirect; datos, URL, navegación y SEO se tratan
como problemas separados.

## 9. Matriz de compatibilidad

| Ruta actual | Rol futuro | Menú de primer nivel | Compatibilidad | Condición para retirar o cambiar |
|---|---|---|---|---|
| `/` | Inicio | Sí | Canónica | No aplica. |
| `/competicion` | Competición | Sí | Canónica dinámica, Fase 4 completada | Evolucionar sólo mediante un nuevo bloque aprobado y datos funcionales reales. |
| `/torneos` | Secundaria de Competición | No | Se conserva | Nueva necesidad funcional demostrada y plan de enlaces. |
| `/rankings` | Secundaria de Competición | No | Se conserva | Nueva necesidad funcional demostrada y plan de enlaces. |
| Detalles de torneo, categoría y partido | Secundarias de Competición | No | Se conservan | Consumidores migrados y equivalencia completa. |
| `/contenidos` | Índice técnico | No | Se conserva accesible temporalmente | Inventario CMS clasificado, landings completas, enlaces migrados y decisión SEO. |
| `/contenidos/:slug` | Lectura CMS heredada | No como familia; destinos canónicos por contenido | Se conserva temporalmente | Página canónica equivalente, redirect probado y enlaces/Resource actualizados. |
| `/nosotros` | Material de migración hacia `/club/quienes-somos` | No | Se conserva temporalmente | CMS canónico con paridad, revisión editorial y redirect aprobado. |
| Cuatro rutas `/club/...` | Hijas canónicas de Club | Dentro del disclosure futuro | Implementadas por acceso directo | Publicación por entorno; Navbar, aliases y aceptación en 7D. |
| Rutas de auth | Zona de cuenta | Separadas | Se conservan | Sólo cambios propios del flujo de cuenta. |
| `/player` | Mi Panel | Separada | Se conserva | No se migra al árbol editorial. |

No se ha localizado un enlace público activo cuyo destino carezca hoy de `Route`. La excepción es la rama inactiva hacia `/dashboard` ya descrita. Sí faltan enlaces entrantes para `/nosotros`; Club todavía no debe enlazarse.

## 10. Propuesta de aliases y redirects futuros

No se implementa ningún alias ni redirect en 7B. Fase 7C podrá introducir
aliases React reversibles; los redirects HTTP quedan para después de acreditar
paridad, migrar enlaces y coordinar canonical.

| Origen | Destino propuesto | Tipo por ahora | Momento | Motivo y condición |
|---|---|---|---|---|
| `/torneos` | Sin redirect | Sin redirect | Indefinido | Es una ruta funcional secundaria, no una landing obsoleta. |
| `/rankings` | Sin redirect | Sin redirect | Indefinido | Conserva una funcionalidad y enlaces directos. |
| Rutas de detalle de competición | Sin redirect | Sin redirect | Indefinido | Los IDs, workflows y enlaces existentes son válidos. |
| `/contenidos` | Por decidir | Decisión aplazada | Migración posterior | No existe todavía un índice canónico equivalente. |
| `/contenidos/nosotros` | `/club/quienes-somos` | Alias temporal; redirect permanente posterior | En 7C tras verificar paridad; redirect después de aceptación | La fuente será CMS; deben revisarse canonical y enlaces guardados. |
| `/nosotros` | `/club/quienes-somos` | Alias temporal; redirect permanente posterior | Alias tras paridad; redirect tras retirar JSX y medir | Elimina la fuente React duplicada sin perder marcadores. |
| `/contenidos/federarse` | `/club/federarse` | Alias temporal candidato | Tras crear la página canónica | Preservar URLs y contenido CMS. |
| `/contenidos/documentos` | `/club/documentos` | Alias temporal candidato | Tras inventariar enlaces y crear la página canónica | Preservar URLs y contenido CMS. |
| `/contenidos/federaciones` | Sin destino canónico MVP | Sin alias | Revisión posterior | Sólo URL directa o footer condicional si existe contenido real. |
| `/contenidos/prensa-media` | Sin destino canónico MVP | Sin alias | Revisión posterior | Sólo URL directa o footer condicional si existe contenido real. |
| `/contenidos/academy` | Sin destino automático | Decisión aplazada | Tras auditoría editorial | No redirigir a `/escuela` ni a `/aprende-a-jugar` sólo por el nombre. |
| `/aprende` o `/manual` | Ninguno | Sin redirect por ahora | Sólo si aparecen consumidores reales | No son rutas implementadas; no se crean aliases sin evidencia. |

`BrowserRouter` puede realizar navegación o redirects en cliente después de cargar la aplicación, pero no produce por sí mismo una respuesta HTTP 3xx ni garantiza el estado correcto a crawlers. El hosting debe devolver `index.html` para rutas SPA que React vaya a resolver. Los redirects de migración con efecto SEO deben configurarse además en servidor, CDN o plataforma de despliegue, con código temporal o permanente elegido tras comprobar equivalencia. Un fallback SPA y un redirect HTTP son mecanismos distintos.

## 11. Separación entre navegación editorial y autenticación

Navbar ya representa los enlaces públicos en un `<ul>` y la cuenta en un contenedor hermano `authSection`. El contrato mantiene esa separación:

- anónimo: acción de acceso claramente separada; registro puede permanecer dentro del flujo de login;
- autenticado: identidad abreviable de forma accesible, Mi Panel y Salir;
- el estado abierto/cerrado del menú móvil no debe ocultar permisos ni cambiar las opciones de cuenta;
- Mi Panel no se convierte en un sexto elemento editorial;
- volver desde login o creación de perfil debe conservar el destino funcional seguro.

En móvil puede compartirse la misma cabecera visual, pero deben mantenerse grupos y nombres accesibles diferenciados.

## 12. Fuentes de verdad por sección

| Área | Fuente principal | Papel de React | Papel de backend/compilador |
|---|---|---|---|
| Inicio | Híbrida | Estructura y composición | Entrega sólo elementos dinámicos publicables; Knowledge aporta artefactos cuando existan. |
| Competición | Dominio Laravel | Presentar y enlazar datos | Aplicar visibilidad, estados y reglas; serializar Resources públicos. |
| Aprende: Aprende a jugar y Manual | `knowledge/` | Presentar exclusivamente la proyección pública | Compilador build-time valida, filtra y genera; Laravel no sirve el Manual v1. |
| Aprende: Escuela de Galotxas | `knowledge/` futuro + dominio Laravel específico | Componer fuentes autorizadas y el formulario sin duplicar reglas | Compilador para pedagogía futura; Blade/API para programa, niveles, horarios, ubicaciones e inscripciones. |
| Club | CMS | Rutas canónicas y presentación de páginas públicas, sin landing padre | Blade administra; API excluye borradores y publicaciones futuras. |
| Cuenta | Dominio Laravel autenticado | Formularios y Mi Panel | Autenticación, autorización y datos propios. |

## 13. CMS y páginas institucionales

El inventario se basa en código, seeders y tests, sin consultar la base de desarrollo. `InstitutionalCmsPageSeeder` garantiza seis páginas publicadas sólo cuando el slug no existía; no sobrescribe contenido previo. `E2ESmokeSeeder` añade `e2e-publicada` exclusivamente a la base temporal E2E. Los slugs de factories y casos de prueba no forman un catálogo editorial.

| Contenido | Ruta actual verificable | Fuente | Duplicado | Ruta futura propuesta |
|---|---|---|---|---|
| Prensa y medios | `/contenidos/prensa-media` | CMS; seeder y Navbar anterior a 3B | No localizado | Sin canónica MVP; URL directa o footer sólo con contenido real |
| Nosotros | `/nosotros` y `/contenidos/nosotros` | React estático + CMS | Sí | `/club/quienes-somos`, con CMS como fuente canónica |
| Federaciones | `/contenidos/federaciones` | CMS; seeder y Navbar anterior a 3B | No localizado | Sin canónica MVP; URL directa o footer sólo con contenido real |
| Federarse | `/contenidos/federarse` | CMS; seeder, sin enlace actual de Navbar | No localizado | `/club/federarse` |
| Documentos | `/contenidos/documentos` | CMS; seeder, sin enlace actual de Navbar | No localizado | `/club/documentos` |
| Academy | `/contenidos/academy` | CMS; seeder y Navbar anterior a 3B | Home ya enlaza Escuela; el CMS legado permanece | Decisión aplazada; no equivale a `/escuela` |
| Contacto | No existe slug sembrado, enlace ni ruta específica | Sin fuente actual verificada | No | `/club/contacto` cuando exista contenido y flujo real |
| Índice CMS | `/contenidos` | API de páginas publicables | No aplica | No será área de primer nivel; destino final por decidir |
| Página E2E | `/contenidos/e2e-publicada` sólo en E2E | Seeder temporal | No aplica | Nunca forma parte del catálogo de producción |

Los tests emplean además slugs como `borrador`, `programada`, `federarse`, `academy` y otros valores sintéticos para validar publicación. Su presencia no acredita contenido real. El endpoint CMS aplica el mismo scope `published` en índice y detalle: estado `published` y `published_at` nulo o no futuro.

## 14. Knowledge y Aprende a jugar

Las fases 5A, 5A.1, 5B y 5C determinan:

- 40 documentos compilables: ocho de Reglamento y 32 Conceptos repartidos entre elementos, personas y juego;
- cuatro exclusiones explícitas: instrucciones, README raíz, índice README de Conceptos y la metodología `REG-000`;
- seis metadatos obligatorios, con IDs, slugs, versiones, estados y fechas validados;
- cuatro namespaces y un orden determinista;
- un artefacto canónico JSON de esquema v1 y una proyección pública independiente, ambos sin HTML, MDX, rutas absolutas o tiempo de generación;
- ninguna colección de Instalaciones independiente, Historia, Escuela, multimedia o referencias.

Reglamento y Conceptos disponen de contrato, proyección exclusiva de documentos `Vigente`, repositorio frontend y renderer semántico sin HTML inyectado. El H1 procede del título documental y los bloques compilados conservan headings internos, párrafos, énfasis, listas, tabla y separadores. Las referencias explícitas resuelven a rutas públicas antes de escribir el JSON.

El Manual es una organización y un consumidor de `knowledge/`, no una copia editable en JSX, base de datos o CMS. `/aprende-a-jugar` y `/aprende-a-jugar/manual` cumplen funciones distintas; las rutas de detalle sólo admiten las cuatro colecciones actuales. La landing deriva sus recuentos, el índice enlaza anchors estables por colección y cada documento presenta navegación contextual, tabla de contenidos a partir de H2–H6 compilados y vecinos limitados a su colección.

Los fragmentos conservan los IDs del artefacto y funcionan en navegación SPA, carga directa y recarga. `App.jsx` difiere las tres páginas de Aprende, de modo que el corpus, el repositorio y el renderer no entran en el JavaScript inicial; el fallback anunciado no crea otro `<main>` o H1 ni usa la 404. Historia no aparece como enlace vacío y continúa pendiente de una colección y un bloque futuro aprobados.

## 15. Escuela híbrida

Escuela de Galotxas es una sección distinta del `academy` legado y del Manual. Su parte estable podrá incluir metodología, ejercicios y recursos pedagógicos desde una colección futura de `knowledge/`, únicamente cuando exista contenido aprobado. Fase 6B.1 implementa el dominio Laravel y Blade; 6B.2 añade solicitudes anónimas y POST público; 6B.3 incorpora centros y actividades exclusivamente administrativos; 6B.4 añade la lectura pública; 6C publica `/escuela` y su formulario React. El formulario no exige cuenta, crea una solicitud pendiente y sólo está operativo cuando el backend confirma inscripciones abiertas. Centros, actividades, inscripciones y alumnado no se publican en la lectura.

El cierre de `/escuela` acredita:

1. propósito y audiencias aprobados;
2. contenido real mínimo con propietario editorial;
3. separación explícita entre material estable y actividad operativa;
4. consumo React del modelo Laravel, administración, endpoint y Resources ya implementados;
5. estados remotos, accesibilidad y pruebas.

Los textos de aceptación y política de conservación permanecen sin aprobar y son un gate operativo para abrir producción, no contenido que React pueda inventar. El CMS genérico demuestra una infraestructura, pero el slug `academy` y dos bloques sembrados no demuestran estas capacidades verticales.

## 16. Competición funcional

La landing `/competicion` dispone de dependencias funcionales suficientes y la API pública verificada ofrece:

| Necesidad | Endpoint | Consumidor React actual |
|---|---|---|
| Temporadas y jerarquía pública | `GET /api/v1/seasons` | Landing de Competición y rankings mediante servicio |
| Listado de campeonatos | `GET /api/v1/championships` | `/torneos` |
| Detalle de campeonato | `GET /api/v1/championships/{id}` | `/torneos/:championshipId` y carga de categorías del ámbito Categoría de `/rankings` |
| Ranking de campeonato | `GET /api/v1/championships/{id}/ranking` | Detalle de torneo y ámbito Campeonato de `/rankings` |
| Detalle de categoría | `GET /api/v1/categories/{id}` | Resumen, standings, schedule y Copa como contexto |
| Clasificación | `GET /api/v1/categories/{id}/standings` | Ruta dedicada de standings y ámbito Categoría de `/rankings` |
| Calendario común | `GET /api/v1/categories/{id}/schedule` | Schedule selecciona Liga y la ruta dedicada Copa selecciona eliminatorias por metadatos estructurales |
| Partido | `GET /api/v1/matches/{id}` | Detalle de partido |
| Ranking de temporada | `GET /api/v1/seasons/{id}/ranking` | Ámbito Temporada de `/rankings` |
| Ranking histórico | `GET /api/v1/rankings/all-time` | Landing y `/rankings` |
| Inscripción | `GET .../registration` y `POST .../register` | Detalle de torneo autenticado |

Los listados, detalles, relaciones y datos derivados aplican visibilidad efectiva en backend. Fase 4A elige `GET /api/v1/seasons` como única fuente del resumen porque su envelope ya incluye temporadas ordenadas por fecha de inicio descendente, campeonatos públicos asociados y `categories_count`. No se solicita `/championships`, no se llama a detalles y no existe N+1 en React.

`championshipsService` interpreta el envelope, `useCompetitionOverview` gestiona loading, error, retry, vacío y desmontaje, y los componentes específicos presentan temporada, estado, fechas disponibles, campeonatos y enlaces `/torneos/{id}`. React conserva el orden recibido, no usa el `slug` nullable de Season, no muestra ni vuelve a filtrar `is_public` y no infiere estados a partir de fechas. El acceso a Torneos y el enlace completo de Rankings siguen disponibles aunque fallen sus bloques remotos.

Fase 7F.2A reutiliza esa jerarquía en `/rankings`: Histórico es el ámbito por
defecto; Temporada selecciona una temporada; Campeonato selecciona temporada
y campeonato; Categoría selecciona temporada, campeonato y una categoría
obtenida del detalle público del campeonato. Cambiar un padre restablece de
forma determinista sus hijos y las respuestas tardías no pueden sobrescribir
la selección vigente. Cada ámbito distingue carga, error con reintento, vacío
y contenido. React no ordena filas, renumera posiciones ni calcula puntos.

Fase 4C centraliza las raíces y generadores reutilizados en `competitionRoutes`; el refinamiento de Copa amplía después `CategoryNavigation` a Resumen, Clasificación, Calendario y resultados y Copa. Schedule selecciona exclusivamente rondas `type=league`; CupPage exige `type=cup`, `phase=cup` y un stage admitido sobre el mismo endpoint, carga en paralelo contexto y colección y ofrece estados recuperables. Ninguna vista renumera posiciones, calcula puntos, infiere una Copa por nombre/orden o deriva al campeón desde el tanteo.

## 17. Requisitos de accesibilidad

### Estado tras 3B

- Navbar usa `<nav aria-label="Navegación principal">`.
- El botón móvil declara tipo, nombre dinámico, `aria-expanded` y `aria-controls`.
- El menú se cierra por botón, Escape, selección de enlace y cambio de pathname.
- Desktop y móvil usan el mismo árbol DOM; no hay dos listas divergentes.
- Un matcher centralizado aplica una clase activa y `aria-current="page"` o `location` sin permitir dos elementos activos.
- Escape cierra el menú y devuelve explícitamente el foco al botón.
- El listado editorial y el grupo Cuenta tienen nombres accesibles y son hermanos semánticos.
- No existen breadcrumbs.
- Home y el índice CMS reutilizan el único `<main>` global de `App`.
- La landing de Competición y la 404 aportan un único `h1`.
- Cada vista deportiva aporta un `h1` descriptivo; la navegación de categoría identifica la vista actual con `aria-current="page"` y los retornos no dependen de `navigate(-1)`.
- Las tablas de clasificación y rankings usan caption, headers con scope y una
  región enfocable para su scroll horizontal; las cuatro pestañas de Rankings
  exponen `tablist`, `tab`, `tabpanel`, `aria-selected`, IDs estables y un
  único panel activo, y los selectores tienen labels explícitos.
- Reset Password usa `h2` como encabezado principal.
- No se ha ejecutado una auditoría automática de contraste; los colores deben validarse, no darse por conformes sólo por inspección.

### Resultado de los criterios 3B

1. Nombre y estado activo perceptibles sin depender sólo del color.
2. `aria-current` correcto, foco visible y retorno de foco al cerrar el menú con teclado.
3. Orden de tabulación lógico y activación con teclado de todos los destinos.
4. Un solo landmark `<main>` y un `h1` único y descriptivo por landing y estado de error principal.
5. Autenticación agrupada y nombrada sin mezclarse con el listado editorial.
6. El menú cerrado usa `display: none` en el breakpoint móvil y Playwright confirma que sus enlaces no permanecen visibles o enfocables; no existe un segundo árbol.
7. Los destinos incorporan foco visible y los controles móviles un tamaño mínimo de 44 px; una auditoría completa de contraste sigue pendiente.

## 18. Requisitos responsive

### Estado tras 3B

Navbar muestra sus dos enlaces en una sola fila por encima de 1024 px y activa el menú colapsable a 1024 px. A 640 px oculta visualmente la palabra “Menú”, conservando el nombre accesible. La cuenta queda fuera de la lista colapsable. El logo mide 140 px en escritorio, 100 px en tablet y 80 px en móvil.

Playwright cubre 320, 375, 768, 1024, 1280 y 1440 px con una identidad deliberadamente larga. No detecta desbordamiento horizontal ni solapamiento entre los grupos visibles del Navbar. La futura implementación de los disclosures Aprende/Club deberá repetir esta matriz.

Fase 4C repite esa matriz en landing, listado de Torneos, campeonato, categoría, standings, schedule, partido y Rankings. Las tablas conservan su overflow dentro del contenedor sin provocar overflow documental; tarjetas, acciones y textos largos se adaptan hasta 320 px. El E2E comprueba además foco visible y una ampliación visual del 200 % en la navegación de categoría.

### Resultado de los criterios 3B

- misma jerarquía, labels y permisos en todos los tamaños;
- sin scroll horizontal a 320, 375, 768, 1024, 1280 y 1440 px;
- comportamiento comprobado con identidad de usuario larga;
- targets táctiles suficientes y menú que no queda detrás del contenido;
- cierre tras navegación, Escape y cambio de ruta sin perder contexto;
- foco visible y sin quedar en contenido oculto;
- las áreas todavía no implementadas no consumen espacio ni requieren abreviaturas o controles deshabilitados;
- validación específica de la franja intermedia, que ya no divide accidentalmente la cabecera en dos filas.

## 19. Requisitos SEO

### Estado auditado y aplicación 3C

- `frontend/index.html` declara `lang="en"` aunque la interfaz es española y usa el título genérico `frontend`.
- El índice y detalle CMS conservan su actualización directa heredada de `document.title`.
- `PageMetadata` gestiona en `/competicion` y 404 un título y una meta description únicos, restaura los valores anteriores al salir y no crea una segunda descripción.
- La 404 aplica `noindex` sólo mientras está montada y lo retira al navegar; no existe una política robots global.
- El resto de rutas todavía puede heredar títulos anteriores. No hay Open Graph, Twitter Cards, canonical, sitemap ni React Helmet o equivalente.
- No existe `robots.txt` en el frontend. `backend/public/robots.txt` permite todo, pero sólo gobierna el host que lo sirve.
- Existe una 404 global React; la respuesta HTTP 404 coordinada para rutas desconocidas sigue pendiente de hosting.

### Contrato mínimo

1. Cada landing y detalle indexable tendrá título único, `h1`, descripción y canonical coherentes.
2. El documento declarará español mediante `lang="es"` salvo decisión lingüística posterior.
3. Los metadatos se actualizarán al cambiar de ruta y se limpiarán al salir de ella.
4. La 404 tendrá vista accesible y el hosting entregará un estado HTTP apropiado cuando sea posible; el fallback a `index.html` no debe convertir cualquier URL inexistente en contenido indexable válido.
5. `/contenidos` no debe indexarse como catálogo editorial definitivo. La decisión `noindex`, canonical o retirada se aplicará sólo con la estrategia de migración.
6. Las URLs heredadas conservarán canonical propio hasta que exista equivalencia; después, canonical y redirect deben apuntar al mismo destino.
7. Un sitemap futuro incluirá sólo rutas canónicas y detalles públicos descubribles, nunca borradores, páginas futuras ni rutas de cuenta.

No se instala una dependencia SEO en 3B ni 3C. `PageMetadata` cubre únicamente el contrato básico de las rutas adoptadas; la cobertura completa y la elección de prerender/SSR pertenecen al diseño de implementación y despliegue.

## 20. Estrategia de testing

La línea base existente combina Vitest/React Testing Library y un smoke Playwright con stack temporal React–Laravel–MariaDB–Blade.

PUBLIC-NAVIGATION-1 cubre en 3B:

- test unitario de la lista exacta, orden, labels y destinos de primer nivel;
- tests de estado activo para la ruta exacta y las familias secundarias;
- tests anónimo/autenticado que demuestren separación de cuenta;
- teclado, Escape, retorno de foco, cierre al navegar y atributos ARIA;
- tests de `/competicion`, su `h1` único y sus destinos funcionales;
- test wildcard 404 y enlaces de recuperación;
- E2E desktop y móvil de los destinos disponibles, rutas secundarias y retorno desde autenticación.

Para los bloques posteriores de contenido y compatibilidad se requerirán:

- tests de compatibilidad de cada alias antes de activar redirects;
- E2E CMS que pruebe que un borrador o publicación futura no se descubre ni se resuelve;
- comprobación de URLs directas sobre el hosting con fallback y respuestas/redirects HTTP esperados;
- validación de artefactos de `knowledge/` antes de probar sus rutas.

Los tests actuales de Navbar cubren el árbol agrupado, padres sin ruta,
cuenta anónima/autenticada, matchers, estado visual, ARIA, teclado, Escape,
foco, click exterior y cierres. Home y Footer cubren destinos reales, redes
seguras y ausencias deliberadas. Las pruebas de App y páginas conservan
`/competicion`, Aprende, Manual, Escuela, Club, wildcard, rutas dinámicas,
landmarks y fallbacks diferidos. El E2E añade disclosures desktop/móvil,
Home/footer y mantiene Knowledge, Escuela, Club/Contacto, CMS, competición, Mi
Panel y resultados. Canonical, migración institucional y multibrowser siguen
pendientes.

PUBLIC-LANDING-SYSTEM-1 añade en 3C tests de contenedor, cabecera, acciones, secciones, rejilla, tarjetas y metadatos; verifica IDs estables, `aria-labelledby`, un solo `h1`, ausencia de `<main>` anidado y controles anidados, restauración de description/robots, ausencia de llamadas API y de rutas placeholder. Playwright añade una matriz específica de la landing a 320–1440 px, comprueba legibilidad, overflow, foco por Tab y navegación con Enter.

Línea base de Fase 3A, 2026-07-19: `npm run test:run` completó 65 tests en 18 archivos; `npm run lint` y `npm run build` finalizaron sin errores; `npm run e2e` completó sus nueve escenarios Chromium sobre el stack Docker temporal. No se ejecutó la suite backend completa porque no se modificó backend.

Validación de Fase 3B, 2026-07-19: `npm run test:run` completó 105 tests en 23 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 13 escenarios Chromium y la matriz 320–1440 px sobre el stack temporal. Backend no se modificó ni se ejecutó su suite completa.

Validación de Fase 3C, 2026-07-19: `npm run test:run` completó 118 tests en 25 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 14 escenarios Chromium, incluidos responsive y teclado de la landing, sobre el stack temporal. Backend no se modificó ni se ejecutó su suite completa.

COMPETITION-LANDING-DATA-1 añade en 4A pruebas de servicio, hook y página para éxito, loading, error, retry, vacíos global y local, nullables, etiquetas deportivas, fechas, enlace contextual y ausencia de campos técnicos. Playwright consume los datos públicos reales de `E2ESmokeSeeder`, abre el detalle por teclado y repite la matriz 320–1440 px sobre la jerarquía dinámica.

Validación de Fase 4A, 2026-07-19: `npm run test:run` completó 134 tests en 28 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 14 escenarios Chromium sobre MariaDB temporal. Backend no se modificó ni se ejecutó su suite completa.

COMPETITION-RANKING-NAVIGATION-1 añade en 4B pruebas del servicio y hook históricos, respuesta tardía frente a retry, límite visual y orden backend, independencia de estados, rutas contextuales y regresión de la tabla completa. Playwright valida el vacío inicial real, las filas posteriores a resultados validados, el enlace completo y el recorrido categoría → standings → schedule.

Validación de Fase 4B, 2026-07-19: `npm run test:run` completó 151 tests en 33 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 14 escenarios Chromium sobre MariaDB temporal. Backend no se modificó ni se ejecutó su suite completa.

COMPETITION-UX-CLOSURE-1 añade en 4C pruebas de prioridad visual, acciones únicas, labels y fallbacks, fechas parciales, rutas y retornos deterministas, estados remotos con retry, independencia de detalle y ranking, ausencia de cargas derivadas en el resumen de categoría, posiciones backend y semántica de tablas y pestañas. Playwright recorre Inicio → Competición → Campeonato → Categoría → Clasificación → Calendario → Partido, valida Rankings, vacío filtrado, foco, zoom y la matriz 320–1440 px en toda la rama.

Validación de Fase 4C, 2026-07-19: `npm run test:run` completó 166 tests en 36 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 15 escenarios Chromium sobre MariaDB temporal. Backend, API, Resources, rutas, seeders, Home, Navbar y `knowledge/` no se modificaron; no se ejecutó la suite backend completa.

## 21. Estado de implementación de Fase 3

### Fase 3B — estructura navegable

Fase 3B está completada con:

1. configuración única consumida por el único árbol desktop/móvil;
2. Inicio y Competición como únicos elementos editoriales, con cuenta separada;
3. matcher activo exacto, ARIA, teclado, foco y cierre móvil;
4. fallback 404 accesible sin redirect automático;
5. `/competicion` como landing mínima basada en `/torneos` y `/rankings` reales;
6. rutas deportivas, CMS, institucionales y de cuenta conservadas;
7. ausencia de placeholders para Aprende a jugar, Escuela y Club;
8. Vitest, lint, build, Playwright y matriz responsive validados.

En el cierre histórico de 3B Home no se rediseña: conserva su CTA directo a
Torneos y el footer exclusivo. 7D.1 sustituye después ambos elementos. La rama
`/dashboard` no consumida y Reset Password permanecen como deuda explícita.

### Fase 3C — sistema común de landings

Fase 3C está completada con:

1. `PublicLanding` como `<article>` responsive dentro del único `<main>` global;
2. cabecera con `h1`, introducción y acciones opcionales, más secciones con `h2` e IDs explícitos estables;
3. acciones, rejilla y tarjetas mediante `Link`, sin interacciones anidadas y con foco visible;
4. `PageMetadata` para título, description y `noindex` local reversible de 404;
5. componentes que reciben props o `children` y no importan API, CMS, `knowledge/` ni slugs editoriales;
6. adopción real en `/competicion`, manteniendo `/torneos` y `/rankings`, sin API, datos simulados ni funcionalidad de Fase 4;
7. reutilización acotada de acciones y metadatos en 404, sin convertirla en landing editorial;
8. Vitest, lint, build, 14 E2E y matriz 320–1440 px validados.

En 3C no se creó un estado remoto común porque Torneos, Rankings, CMS y Mi Panel no ofrecían dos adopciones compatibles sin cambiar contratos. Tampoco se registraron entonces `/aprende-a-jugar`, `/escuela` o `/club`; 5B incorpora la primera y 6C la segunda, mientras Club continúa sujeto a sus gates. Fase 3C no sustituye el contrato y compilador de Knowledge, la vertical de Escuela ni el desarrollo completo de Competición previsto en Fase 4.

Con 3A, 3B y 3C completadas, la Fase 3 queda cerrada.

### Posterior a 3C

Quedan para bloques posteriores la consolidación institucional, la migración de Nosotros, aliases, redirects, canonical, indexación de `/contenidos`, SEO completo, sitemap y robots, limpieza de código huérfano y migración de `academy` y `documentos`. Estas tareas no forman parte de la estructura común de 3C.

Fase 4A desarrolla el primer bloque dinámico de `/competicion` a partir de la landing mínima de 3B y de los patrones comunes de 3C.

## 22. Estado de implementación de Fase 4

### Fase 4A — landing dinámica de Competición

Fase 4A está completada con:

1. una petición a `GET /api/v1/seasons` como fuente primaria, sin duplicar `/championships` ni detalles;
2. servicio, hook y componentes de presentación separados;
3. temporadas y campeonatos efectivamente públicos presentados en el orden de la API, sin filtrar `is_public` en React;
4. etiquetas deportivas, fechas disponibles, recuentos de categorías y enlaces de detalle por ID;
5. loading, error, retry, vacío global y temporada sin campeonatos, manteniendo Torneos y Rankings;
6. nullables seguros, headings `h1`–`h4`, IDs estables, foco visible, teclado y responsive 320–1440 px;
7. 134 tests Vitest, lint, build y 14 E2E correctos sin modificar backend, seeders ni rutas.

### Fase 4B — ranking histórico y rutas contextuales

Fase 4B está completada con:

1. una petición independiente a `GET /api/v1/rankings/all-time`, reutilizando el servicio y el contrato previos;
2. preview de hasta cinco filas en el orden exacto del backend, con nombre, posición cuando existe, puntos ponderados y categorías disponibles, sin mostrar IDs ni recalcular el ranking;
3. loading, error, retry, vacío y contenido propios, sin bloquear los estados de temporadas y campeonatos;
4. enlace `Ver ranking completo` disponible en todos los estados y `/rankings` preservado como experiencia sin el límite de cinco;
5. generadores defensivos para las rutas existentes de campeonato, detalle de categoría, standings y schedule;
6. accesos explícitos desde campeonato y categoría a detalle, clasificación y calendario, sin añadir rutas, aliases o redirects;
7. tests Vitest, lint, build y E2E sobre datos reales del stack temporal, sin modificar backend, API, seeders, Home, Navbar o metadatos.

### Fase 4C — cierre de la experiencia pública

Fase 4C está completada con:

1. orden final de `/competicion`: acceso principal a Torneos, temporadas/campeonatos y ranking histórico, con un solo enlace a Rankings;
2. Torneos con error recuperable separado del vacío y una única acción por tarjeta;
3. detalles de campeonato y categoría con jerarquía, enums y fechas legibles, y sin duplicar standings o schedule;
4. navegación común de categoría, `aria-current` y retornos deterministas hasta partido;
5. clasificación, calendario y rankings con estados remotos, retry, tablas semánticas, posiciones backend y fallbacks seguros;
6. metadatos básicos en las vistas deportivas y responsive validado a 320–1440 px, foco visible y zoom;
7. 166 tests Vitest, lint, build y 15 E2E correctos sin modificar backend, API, rutas, seeders, Home, Navbar o `knowledge/`.

Con 4A, 4B y 4C completadas, la Fase 4 queda cerrada. No se incorporan en la landing standings, resultados, próximos partidos, actividad reciente, filtros ni nuevas rutas: el cierre se apoya en las vistas funcionales existentes.

## 23. Deuda aplazada

- integrar la regeneración de los dos artefactos en CI/despliegue cuando la raíz del monorepo esté garantizada;
- definir colecciones reales de Historia y Escuela;
- crear contrato CMS operativo de Escuela, con privacidad de menores;
- consolidar el contenido institucional, inventariar `documentos` ya asignado a
  Club y clasificar `academy` sin equivalencias automáticas;
- crear y cargar manualmente Contacto; implementar su UI sólo después de los
  gates de privacidad y activación;
- migrar Nosotros y resolver su duplicidad;
- decidir sólo futuras URLs de detalle bajo Escuela; las cuatro rutas Club ya
  están contratadas y cualquier nueva colección de Aprende requiere un contrato
  posterior propio;
- implementar los aliases Club contratados tras verificar paridad; redirects,
  canonical e indexación de `/contenidos` continúan posteriores;
- corregir `/dashboard` latente y revisar componentes huérfanos;
- decidir si se consolida el detalle agregado de categoría con standings/schedule;
- completar SEO, sitemap, robots y respuesta 404 HTTP en hosting más allá de los metadatos básicos y fallback React;
- revisar enlaces internos de bloques CMS, hoy renderizados como `<a>` y no como navegación React;
- ampliar accesibilidad, contraste y matriz responsive/multibrowser;
- mantener la futura subida de multimedia fuera de cualquier filesystem efímero.

## 24. Criterios de aceptación

### Fase 3A

- inventarios completos y trazables a código, sin consultar datos de desarrollo;
- cinco nombres y rutas de primer nivel definidos sin presentarlos como implementados;
- fuentes y mínimos de contenido explícitos por landing;
- rutas funcionales, técnicas, duplicadas, heredadas y de cuenta clasificadas;
- redirects aplazados con condiciones y capa de ejecución documentadas;
- riesgos actuales de accesibilidad, responsive y SEO registrados;
- plan 3B/3C alineado con los gates de contenido y con la estructura común de landings;
- sólo documentación y `CHANGELOG.md` modificados.

### Fase 3B

- una única configuración produce los dos destinos editoriales funcionales;
- desktop y móvil comparten árbol, estado activo y separación de cuenta;
- la rama deportiva conserva URLs y activa Competición de forma inequívoca;
- `/competicion` aporta un `h1` y acceso real a Torneos y Rankings;
- wildcard, foco, landmarks y matriz responsive están cubiertos;
- Aprende a jugar, Escuela y Club no tienen rutas, enlaces deshabilitados ni placeholders;
- rutas heredadas, backend, CMS y `knowledge/` permanecen funcionalmente intactos;
- 105 tests Vitest, lint, build y 13 escenarios E2E completan correctamente.

### Fase 3C

- existe una estructura común desacoplada de las fuentes de contenido y sin Layout o `<main>` paralelos;
- cabecera, secciones, acciones y destinos cumplen jerarquía, semántica, foco y navegación por teclado;
- `/competicion` conserva sus destinos reales y utiliza los componentes sin llamadas API ni datos simulados;
- 404 conserva recuperación y obtiene metadatos específicos reversibles;
- Home, Navbar, matchers y rutas existentes no cambian;
- Aprende a jugar, Escuela y Club siguen sin rutas o placeholders;
- 118 tests Vitest, lint, build y 14 escenarios E2E completan correctamente.

### Fase 4A

- `/competicion` consume una única fuente pública real y presenta temporadas con sus campeonatos asociados;
- Laravel conserva la decisión de visibilidad y React no muestra ni filtra `is_public`;
- servicio, hook, componentes y página mantienen responsabilidades separadas;
- loading, error, retry, vacío global, temporada vacía y datos nullable están cubiertos;
- enlaces de detalle, Torneos y Rankings continúan siendo funcionales y accesibles;
- backend, seeders, Home, Navbar, rutas y contratos API no cambian;
- 134 tests Vitest, lint, build y 14 escenarios E2E completan correctamente;
- el alcance de 4A no adelantó rankings ni el cierre deportivo; su continuación se registra separadamente en 4B y 4C.

### Fase 4B

- el ranking histórico se solicita mediante el servicio existente y en paralelo lógico a la carga de temporadas;
- el preview conserva el orden del backend y limita únicamente la presentación a cinco filas;
- oficiales y provisionales no se reinterpretan, y una `position` nula no se convierte en una posición visual inventada;
- loading, error, retry, vacío y contenido del ranking no bloquean el resumen de temporadas, ni a la inversa;
- `Ver ranking completo` lleva a `/rankings`, cuya tabla conserva más de cinco resultados;
- el detalle de campeonato y el de categoría ofrecen enlaces accesibles a las rutas reales de categoría, standings y schedule;
- backend, API, Resources, seeders, rutas, Home, Navbar, metadatos y `knowledge/` no cambian;
- 151 tests Vitest, lint, build y 14 escenarios E2E completan correctamente;
- el bloque conserva su alcance histórico; su continuación se completa en 4C.

### Fase 4C

- la prioridad visual de la landing y los labels de acciones no duplican destinos;
- el usuario puede identificar Temporada, Campeonato, Categoría y vista actual, y regresar por una ruta determinista;
- loading, error, retry, vacío y contenido son distinguibles por recurso sin ocultar bloques independientes;
- las posiciones y valores deportivos proceden del backend y los desconocidos se presentan con fallback neutral;
- el detalle de categoría no solicita ni representa las colecciones completas de standings y schedule;
- tablas, tarjetas, foco, teclado, zoom y responsive 320–1440 px se validan en unitarios y E2E;
- backend, API, Resources, rutas, seeders, Home, Navbar, `knowledge/` y dependencias no cambian;
- 166 tests Vitest, lint, build y 15 escenarios E2E completan correctamente y cierran Fase 4.

### Fase 5B

- Navbar expone Inicio, Competición y Aprende a jugar en el mismo orden para desktop y móvil, con cuenta separada y una única rama activa;
- landing, Manual y documentos disponen de un único H1, metadatos básicos, rutas compartibles y retorno determinista;
- el Manual agrupa las cuatro colecciones públicas y enlaza los 40 documentos en orden, sin exponer estado, `sourcePath` o Markdown;
- Reglamento y Conceptos se resuelven mediante el repositorio frontend y las referencias explícitas usan enlaces internos ya validados;
- slug, grupo, colección o forma inválidos conservan la URL y muestran la experiencia 404 con `noindex` reversible;
- tabla, listas, headings, foco, teclado, zoom y responsive 320–1440 px se validan sin overflow global;
- backend, API, CMS, base de datos, seeders, contenido canónico y dependencias no cambian;
- 261 tests Vitest, lint, build y 16 escenarios E2E completan correctamente; 5C y la Fase 5 permanecen abiertas.

### Fase 5C

- la landing obtiene del repositorio los 40 documentos y cuatro colecciones sin duplicar contenido editorial;
- el Manual conserva orden, añade navegación de colecciones y permite regresar al contexto exacto;
- cada documento presenta contexto local, índice H2–H6, fragmentos estables y anterior/siguiente sin wrap o cruces;
- una activación del índice desplaza y enfoca su heading; la carga directa y recarga resuelven el mismo fragmento sin cambiar metadatos;
- sólo la rama Aprende se carga con `React.lazy`; el corpus queda ausente del JS inicial y el fallback no añade landmarks, H1 o falsas 404;
- Navbar, rutas, 404, tabla, referencias, responsive, teclado y contenido público conservan su contrato;
- backend, API, CMS, base de datos, seeders, contenido canónico, artefactos, esquema y dependencias no cambian;
- 271 tests Vitest, lint, build y 16 escenarios E2E completan correctamente y cierran la Fase 5.

### Fase 6C

- `/escuela` carga de forma diferida y consume `GET /api/v1/school` sin importar el corpus Knowledge;
- programa, apertura, niveles, horarios, ubicaciones y contacto conservan orden y nulabilidad del backend;
- el formulario anónimo cubre menores, adultos, representante condicional, nivel opcional y respuestas `201`, `409`, `422`, `429` o fallo general sin persistir datos personales;
- `data: null`, cierre y datos parciales son estados válidos; el Manual permanece disponible y los descendientes no registrados muestran la 404;
- Navbar expone cuatro destinos en desktop y móvil; Home enlaza Escuela y deja de presentar “Academy”, sin modificar `/contenidos/academy`;
- 312 tests Vitest, lint, build y 21 escenarios E2E completan correctamente y cierran 6C y la Fase 6.

### Implementación posterior

- los cinco destinos enlazados existen y aportan contenido real;
- ningún enlace de primer nivel depende de `/contenidos`;
- desktop y móvil exponen el mismo árbol y la cuenta permanece separada;
- rutas secundarias de competición conservan URL y funcionalidad;
- estado activo, teclado, foco, landmarks y headings cumplen el contrato;
- metadatos, canonical, 404, fallback y redirects son coherentes entre React y hosting;
- pruebas frontend y E2E cubren navegación, permisos, compatibilidad y fuentes remotas;
- no se duplica contenido editable entre React, CMS y `knowledge/`.

## 25. Auditoría 7A y contrato 7B

### Estado actual

Tras 6C, la configuración única del Navbar contiene Inicio, Competición, Aprende
a jugar y Escuela de Galotxas. La cuenta permanece separada. Torneos, Rankings,
CMS y `/nosotros` conservan acceso por rutas secundarias o directas. Club no
está registrado ni se muestra como placeholder.

### Navegación contractual aprobada

Fase 7A recomienda dos grupos de revelación:

```text
Inicio
Competición
Aprende
├── Aprende a jugar        /aprende-a-jugar
├── Manual y reglas        /aprende-a-jugar/manual
└── Escuela de Galotxas    /escuela
Club
├── Quiénes somos          /club/quienes-somos
├── Contacto               /club/contacto
├── Federarse              /club/federarse
└── Documentos             /club/documentos
Cuenta
```

Aprende a jugar orienta y presenta colecciones; Manual cataloga documentos. No
son duplicados. Escuela comparte contexto de descubrimiento, pero mantiene ruta,
dominio Laravel y contrato independientes de Knowledge. Club reúne el contenido
institucional administrable y es sólo disclosure: no enlaza una landing
`/club`. Prensa/Media y Federaciones quedan fuera del Navbar y se omiten del
footer mientras no dispongan de contenido real y responsable.

ADR-033 aprueba esta topología y sustituye parcialmente el contrato plano de
ADR-028. Fase 7B no la implementa: requiere contenido real, aceptación del
paquete editorial y los bloques 7C/7D.

### Interacción requerida

- botones de revelación, nunca interacción sólo por hover;
- mismo árbol en desktop y móvil, con acordeón permitido en móvil;
- `aria-expanded`, `aria-controls`, foco visible y orden lógico;
- `aria-current="page"` sólo en el enlace exacto y estado visual de rama para
  descendientes;
- Escape cierra el grupo abierto y devuelve el foco a su disparador;
- abrir un grupo cierra el otro;
- navegación y cierre móvil cierran los grupos;
- un segundo Escape cierra el menú móvil y devuelve foco a `Menú`;
- cuenta separada y rutas deportivas actuales preservadas.

### Home y footer

Home debe aportar propuesta de valor aprobada y accesos prioritarios a
Competición, Aprende/Manual y Escuela, con Club/Contacto como acceso secundario.
No debe duplicar el menú ni mostrar tarjetas, noticias o afirmaciones sin fuente
real.

El footer debe ser global y enlazar contenido institucional y legal real:
Quiénes somos, Contacto, Federarse, Documentos, privacidad, aviso legal,
identidad oficial y copyright aprobado. Prensa, Federaciones, redes y
accesibilidad sólo se publicarán con destinos y responsables confirmados;
cookies sólo cuando la revisión técnica/legal determine que aplica.

Las rutas heredadas coexistirán mediante aliases temporales sólo después de
acreditar paridad. Los redirects permanentes se aplazan hasta migrar enlaces,
coordinar canonical y configurar servidor/CDN. El detalle de plantillas,
matrices, alternativas, gates y fases está en
`15-mvp-editorial-and-navigation-contract.md`.

### Seguimiento 7C.1

7C.1 no modifica este árbol ni registra rutas Club. Audita la fuente real de
assets, confirma `dist` como salida generada, valida el CMS y añade únicamente
la infraestructura de contacto desactivada y un servicio React sin UI. ADR-034
sustituye el punto de ADR-033 que descartaba el formulario: `/club/contacto`
podrá incorporarlo en 7C.2 sólo tras privacidad, contenido y activación
controlada. Las cuatro rutas futuras continúan resolviendo la 404 actual.

### Seguimiento 7C.2

7C.2 registra las cuatro rutas exactas mediante una única feature diferida y un
mapeo ruta/slug inmutable. No registra `/club` ni patrones descendientes. El
contenido procede sólo del CMS público y `/club/contacto` conserva ese contenido
aunque falle la configuración; el formulario aparece sólo con `enabled: true`.
`/nosotros` y `/contenidos/:slug` se mantienen porque no se acreditó paridad
editorial para retirarlos. En el cierre de 7C.2 el Navbar continuó inalterado;
7D.1 aplica después el disclosure sin modificar esas fuentes.

### Aplicación 7D.1

7D.1 aplica el árbol aprobado mediante una configuración que declara enlaces,
disclosures, hijos, exactos, prefijos, visibilidad y audiencia. Aprende y Club
son botones sin ruta; sólo uno permanece abierto. Desktop y móvil reutilizan el
mismo marcado, cierran al navegar o hacer click fuera y aplican Escape en dos
niveles con retorno de foco. Cuenta continúa separada.

Home sustituye claims y tarjetas sin destino por el copy aprobado y recorridos
a Competición, Aprende, Manual, Escuela, Quiénes somos y Contacto. No carga
datos remotos ni Knowledge. El footer pasa a ser global y enlaza las cuatro
rutas Club y las redes confirmadas con apertura externa segura. Privacidad,
aviso legal y cookies no se publican como enlaces vacíos: tras la auditoría
7D.2A y el endurecimiento técnico 7D.2B, su eventual publicación pertenece a
7D.2C.
No se añaden `/aprende`, `/club`, aliases, redirects o cambios de CMS.

El contrato de implementación, bundle, pruebas y gates se encuentra en
`19-navigation-home-and-footer.md`. Fase 7D y el MVP permanecen abiertos.

### Aplicación 7F.2A

7F.2A sustituye sólo el enlace directo de Competición descrito en el cierre
histórico de 7D.1. La configuración vigente tiene tres disclosures mutuamente
excluyentes: Competición, Aprende y Club. Competición revela Vista general
(`/competicion`), Campeonatos (`/torneos`) y Rankings (`/rankings`); Home sigue
enlazando directamente a `/competicion` y Cuenta permanece fuera del árbol
editorial.

El padre Competición se activa en la landing, el listado y detalle de
campeonatos, Rankings, categorías y partidos. Sus hijos reciben
`aria-current="page"` sólo en sus tres rutas exactas; una vista de detalle no
marca falsamente Campeonatos. Desktop y móvil comparten estructura,
interacción, cierre exterior, cierre al navegar y Escape con retorno de foco.

`/rankings` conserva una única ruta y expone Histórico, Temporada, Campeonato
y Categoría. Los selectores dependientes consumen temporadas, campeonatos y
categorías públicas en el orden recibido; cambiar un padre restablece el hijo
y descarta respuestas tardías. No se añaden endpoints, rutas, aliases,
redirects o cálculos deportivos React. La implementación y sus 508 tests
frontend y 63 E2E están cerrados en `develop`; staging y aceptación humana
siguen pendientes.

### Seguimiento 7D.2A

7D.2A no altera el árbol, el router ni el footer. Consolida en
`20-legal-privacy-and-cookies-readiness.md` la identidad jurídica y pública,
los tratamientos, la auditoría de cookies/almacenamientos y los borradores
internos. Privacidad, Aviso legal y Cookies siguen sin ruta o enlace. Su
validación jurídica, eventual publicación y pruebas corresponden a 7D.2C;
Contacto permanece desactivado.

### Seguimiento 7D.2B

7D.2B no altera el árbol, el router, las rutas Club ni los enlaces del footer.
Endurece la identidad que ya presentan las rutas públicas de Competición,
restaura Cuenta mediante `/me` sin persistir el perfil y elimina cargas
automáticas a proveedores de fuentes y jsDelivr. Las rutas legales siguen
resolviendo la 404 de React y Contacto continúa oculto con su configuración por
defecto. Publicación legal y activación operativa pertenecen a 7D.2C.

### Seguimiento 7D.2C1

El router registra exactamente `/legal/aviso-legal`, `/legal/privacidad` y
`/legal/cookies` con una frontera lazy propia. El footer añade sus enlaces en
un grupo separado; Navbar, Home y Cuenta no cambian. `/legal`, descendientes y
aliases continúan en 404. Contacto permanece oculto y su activación pertenece a
7D.2C2B.

### Seguimiento 7D.2C2A

La ruta exacta `/public-identity/confirm` se registra como frontera lazy
aislada: no aparece en Navbar, Home o footer, no admite descendientes y oculta
la navegación y el pie globales. El token llega por fragmento, se retira al
montar y la página usa `noindex`. Escuela incorpora una sección opcional sin
cambiar `/escuela`; Competición conserva sus rutas y sólo recibe de Laravel el
`public_display_name` ya resuelto. Contacto y el árbol editorial no cambian.

### Seguimiento 7D.2C2B

El árbol, Navbar, Home, footer, rutas Club y legado permanecen sin cambios.
`/club/contacto` conserva el CMS y sólo añade la primera capa/formulario cuando
API y aviso compilado coinciden. La Política se abre sin sustituir la ruta ni
crear un cuarto documento legal. El default productivo sigue oculto.

### Aplicación 7D.3

Fase 7D.3 sustituye la cobertura SEO parcial de 3C por un manifiesto único que
clasifica todo el router. Inicio, Competición principal, Aprende, los 40
documentos Knowledge, Escuela, Club canónico y Legal son indexables sólo bajo
configuración explícita. Las rutas deportivas dinámicas y el CMS genérico son
`noindex`; Cuenta y token no tienen canonical; `/aprende`, `/club`, `/glosario`
y cualquier desconocida permanecen 404.

`/nosotros` y los cuatro slugs institucionales de `/contenidos` continúan como
compatibilidad, pero apuntan al canonical Club, usan `noindex, follow` y se
excluyen del sitemap. No se implementan redirects. La base pública y la
indexación fallan cerradas hasta 7F; robots, sitemap, metadata, foco y anuncio
SPA se describen en `25-public-seo-accessibility-and-indexing.md`. Los 61
escenarios E2E pasan sobre el stack aislado y cierran 7D.3 y 7D; Fase 7 y el
MVP siguen abiertos.

## Mantenimiento

Este contrato debe actualizarse antes o junto con cualquier cambio visible de Navbar, rutas canónicas, aliases, redirects o fuente editorial. Una ruta futura pasa a implementada sólo cuando código, contenido, despliegue y pruebas lo demuestran.


---

**Nota de seguimiento posterior (Fase 7F.2):** Tras la aceptación de staging, ciertas decisiones (como el modelo de navegación en Competición y el aplazamiento de noticias y multimedia persistente) han sido promovidas o refinadas en la Fase 7F.2. Ver `docs/28-preproduction-product-refinement.md` y `ADR-042`.

### Seguimiento 7F.2E — Noticias

La configuración única incorpora `Noticias` como enlace estructural top-level
entre Competición y Aprende, con destino exacto `/noticias`. El enlace obtiene
`aria-current="page"` sólo en el índice y el estado visual del elemento cubre
también `/noticias/:slug`. Desktop y móvil reutilizan cierre al navegar,
disclosures, Escape y foco ya existentes.

Las rutas lazy `/noticias` y `/noticias/:slug` son funcionales y no dependen
del CMS institucional. Home y footer no reciben feed, tarjeta ni enlace extra.
`/contenidos/prensa-media` continúa intacta. 7F.2F gestiona slots CMS limitados,
pero no puede eliminar o sustituir esta ruta estructural.

### Aplicación 7F.2F — Placements CMS al final de Club

La configuración estructural continúa siendo la única fuente de Inicio,
Competición, Noticias, Aprende y Club, así como de Cuenta. Al montar Navbar se
realiza una sola lectura de `/api/v1/cms-navigation`; los placements válidos
del slot `club` se ordenan y añaden después de Quiénes somos, Contacto,
Federarse y Documentos. Los demás items y sus objetos no se modifican.

Cada hijo dinámico tiene ID determinista, ruta exacta
`/contenidos/{slug}` y `aria-current="page"` sólo en ese destino. Su URL amplía
el criterio activo del padre Club; una página `/contenidos/x` no asignada no lo
activa. Desktop y móvil consumen el mismo árbol compuesto y conservan click,
cierre al navegar, exterior y Escape con retorno de foco.

Loading, respuesta vacía, error API, raíz inválida, slot desconocido, URL no
segura, etiqueta inválida, slug reservado o duplicado degradan al árbol
estructural sin toast, placeholder o retry. Home y footer no consumen este
endpoint. 7F.2F está validada localmente y pendiente de staging.
