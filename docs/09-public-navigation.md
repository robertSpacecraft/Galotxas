# Contrato de navegación y rutas públicas — Galotxas

## 1. Objetivo

Este documento fija el contrato de arquitectura de información pública y registra su aplicación funcional. Parte de la auditoría de Fase 3A y refleja la navegación de Fase 3B, el sistema común de landings de Fase 3C y la landing dinámica de Competición de Fase 4A implementados y validados sobre `develop`.

Las fases 3B, 3C y 4A modifican únicamente React, sus pruebas y la documentación: no cambian backend, CMS, `knowledge/`, despliegue ni redirects. Las rutas objetivo pendientes no se consideran implementadas hasta que existan con contenido real, fuente verificable y pruebas.

## 2. Principios de navegación

1. El primer nivel público tendrá exactamente cinco áreas: Inicio, Competición, Aprende a jugar, Escuela de Galotxas y Club.
2. Identidad, acceso, registro, Mi Panel y cierre de sesión forman una zona de cuenta separada del menú editorial.
3. Una ruta no se publica para mostrar un placeholder vacío o un mensaje genérico de próxima disponibilidad.
4. React puede definir nombres, labels, ayudas funcionales y estructura, pero no será fuente de contenido administrable o conocimiento canónico.
5. Las rutas funcionales de competición se conservan mientras tengan consumidores, contratos y valor de compatibilidad. No es necesario moverlas bajo `/competicion`.
6. `/contenidos` es una infraestructura pública del CMS heredada, no un área editorial de primer nivel.
7. Cada migración de URL requiere contenido equivalente, inventario de enlaces, estrategia SEO, pruebas y una decisión de redirect en la capa adecuada.
8. Desktop y móvil deben exponer la misma arquitectura de información y los mismos permisos.
9. El backend decide publicación, visibilidad y reglas deportivas antes de serializar; el frontend no filtra borradores ni reconstruye visibilidad efectiva.
10. Las rutas autenticadas de participantes pueden mostrar información propia aunque su rama deportiva no forme parte de la experiencia pública, conforme al contrato backend actual.

## 3. Arquitectura de información de primer nivel

El contrato definitivo del primer nivel es el siguiente. La abreviatura móvil coincide con la etiqueta completa: el diseño debe adaptarse al contenido y no recortar los nombres de área.

| Orden | Escritorio | Móvil | Nombre accesible | Título de página propuesto | Ruta | Estado activo esperado |
|---:|---|---|---|---|---|---|
| 1 | Inicio | Inicio | Inicio | Inicio \| Galotxas | `/` | Sólo en `/` |
| 2 | Competición | Competición | Competición | Competición \| Galotxas | `/competicion` | En la landing y en las rutas funcionales de competición |
| 3 | Aprende a jugar | Aprende a jugar | Aprende a jugar | Aprende a jugar \| Galotxas | `/aprende-a-jugar` | En la landing y en sus futuras rutas formativas |
| 4 | Escuela de Galotxas | Escuela de Galotxas | Escuela de Galotxas | Escuela de Galotxas \| Galotxas | `/escuela` | En la landing y en sus futuras rutas pedagógicas u operativas |
| 5 | Club | Club | Club | Club \| Galotxas | `/club` | En la landing y, durante la migración, en las páginas institucionales asociadas |

El estado activo visual se acompaña de `aria-current="page"` cuando el enlace representa la URL exacta y de `aria-current="location"` cuando una ruta secundaria activa semánticamente su área. Sólo puede existir un elemento editorial activo.

Las cinco rutas son canónicas como contrato. En 3B están registradas `/` y `/competicion`; Aprende a jugar, Escuela y Club no existen todavía ni aparecen como enlaces deshabilitados.

## 4. Inventario de rutas actuales

`frontend/src/App.jsx` utiliza `BrowserRouter`, `Routes` y `Route`. Registra 17 rutas explícitas planas y un wildcard final, sin nesting, loaders, acciones de router ni lazy loading. Navbar se renderiza fuera de `Routes` y aparece también ante una URL desconocida.

| Ruta | Componente | Acceso | Fuente de datos | Enlaces entrantes verificados | Estado y comportamiento sin datos |
|---|---|---|---|---|---|
| `/` | `pages/Home/Home.jsx` | Público | Estructura y copy estáticos en React | Logo, Navbar | Canónica actual. `Hero` aporta el `h1`; sus tarjetas no son enlaces. |
| `/competicion` | `pages/Competition/CompetitionPage.jsx` | Público | `GET /seasons` mediante servicio y hook, más estructura React | Navbar | Canónica desde 3B, adaptada al sistema común en 3C y dinámica desde 4A. Presenta temporadas y campeonatos públicos, estados remotos, detalles contextuales y accesos a Torneos y Rankings. |
| `/nosotros` | `pages/Nosotros/Nosotros.jsx` | Público | Contenido estático en React | Ningún enlace interno actual localizado | Duplicada y heredada; conserva contenido único como material de migración. |
| `/torneos` | `pages/Torneos/TournamentList.jsx` | Público | `GET /championships` y `GET /seasons` | Landing de Competición, CTA de Home, Mi Panel, detalles | Funcional secundaria. Tiene carga y vacío; un error de red termina presentándose como colección vacía. |
| `/torneos/:championshipId` | `pages/Torneos/TournamentDetail.jsx` | Público; acciones de inscripción autenticadas | Campeonato, ranking e inscripción desde API | Tarjetas de torneo, Mi Panel, regreso desde categoría | Funcional secundaria. Un fallo del detalle presenta “Torneo no encontrado”, sin 404 de documento. |
| `/categories/:categoryId` | `pages/Torneos/CategoryDetail.jsx` | Público | Categoría, standings y schedule | Detalle de torneo; regreso desde partido | Funcional secundaria. Solapa clasificación y calendario con dos rutas dedicadas. |
| `/categories/:categoryId/standings` | `pages/Standings.jsx` | Público | Categoría y clasificación | Navegación cruzada desde schedule; `CategoryCard` no montada | Funcional secundaria. Tiene navegación local, pero su otro consumidor localizado pertenece a una Home huérfana. |
| `/categories/:categoryId/schedule` | `pages/Schedule.jsx` | Público | Categoría y jornadas/calendario | Navegación cruzada desde standings; smoke E2E | Funcional secundaria. Distingue carga, error, vacío y contenido. |
| `/matches/:matchId` | `pages/MatchDetails.jsx` | Público; workflow ampliado para participante autenticado | Partido público y, con sesión, workflow de resultado | Tarjetas de partido y acciones pendientes | Funcional secundaria. Regresa a categoría si existe contexto o a `/torneos`; el backend responde 404 si la rama no es pública para un visitante. |
| `/rankings` | `pages/Rankings/Rankings.jsx` | Público | Temporadas, ranking histórico y por temporada | Landing de Competición | Funcional secundaria de Competición. |
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

| Ubicación | Texto visible | Destino | Desktop | Móvil | Observación |
|---|---|---|---|---|---|
| Navbar, logo | Imagen “Galotxas” | `/` | Sí | Sí | Cierra el menú y conserva la marca como acceso a Inicio. |
| Navbar editorial | Inicio | `/` | Sí | Sí | Activo sólo en `/`, con `aria-current="page"`. |
| Navbar editorial | Competición | `/competicion` | Sí | Sí | Activo en la landing con `page` y en toda la rama deportiva con `location`. |
| Navbar, cuenta anónima | Iniciar sesión | `/login` | Sí | Sí | Pertenece al grupo accesible Cuenta, fuera de la lista editorial. |
| Navbar, cuenta autenticada | Mi Panel | `/player` | Sí | Sí | Acompañado de saludo y botón Salir. |
| Navbar, cuenta autenticada | Salir | Acción `logout` | Sí | Sí | Botón, no enlace. |
| Menú móvil | Menú | Abre/cierra `public-navigation` | No | Sí, hasta 1024 px | Declara `aria-expanded` y `aria-controls`; cierra al navegar y con Escape, que restaura el foco. |
| Landing Competición | Torneos | `/torneos` | Sí | Sí | Destino secundario con descripción funcional. |
| Landing Competición | Rankings | `/rankings` | Sí | Sí | Destino secundario con descripción funcional. |
| Hero de Home | Ver Torneos | `/torneos` | Sí | Sí | CTA funcional cubierto por test y E2E. |
| Tarjetas de Home | Prensa & Media, Federaciones, Academy | Sin destino | Sí | Sí | Son bloques informativos, no enlaces. |
| Footer de Home | GALOTXAS y textos legales | Sin destino | Sí | Sí | No hay navegación de footer; el footer sólo se monta en Home. |
| Tarjeta de torneo | Ver Torneo | `/torneos/{id}` | Sí | Sí | Mismo destino que “Inscribirme”. |
| Tarjeta de torneo | Inscribirme | `/torneos/{id}` | Sí | Sí | La inscripción real se decide en el detalle. |
| Detalle de torneo | Volver al listado | `/torneos` | Sí | Sí | Navegación de retorno. |
| Detalle de torneo | Ver categoría | `/categories/{id}` | Sí | Sí | Una entrada por categoría pública. |
| Detalle de categoría | Volver al torneo | `/torneos/{championship_id}` | Sí | Sí | Requiere contexto de campeonato. |
| Standings | Clasificación | `/categories/{id}/standings` | Sí | Sí | Marca activo sólo con clase CSS local. |
| Standings | Calendario & Resultados | `/categories/{id}/schedule` | Sí | Sí | Navegación cruzada. |
| Schedule | Clasificación | `/categories/{id}/standings` | Sí | Sí | Navegación cruzada. |
| Schedule | Calendario & Resultados | `/categories/{id}/schedule` | Sí | Sí | Marca activo sólo con clase CSS local. |
| Tarjeta de partido | Ver partido: A contra B | `/matches/{id}` | Sí | Sí | Nombre accesible contextual; no enlaza si falta ID. |
| Detalle de partido | Volver al calendario | `/categories/{id}` o `/torneos` | Sí | Sí | El label no distingue que el fallback lleva al listado de torneos. |
| Detalle de partido | Iniciar sesión | `/login` | Sí | Sí | Sólo para visitante; no conserva explícitamente `state.from` aquí. |
| Acciones pendientes | Enviar/Confirmar resultado o Ver revisión | `/matches/{id}` | Autenticado | Autenticado | Generado desde el contrato backend de Mi Panel. |
| Índice CMS | Ver contenido | `page.url` o `/contenidos/{slug}` | Sí | Sí | `page.url` lo construye actualmente el Resource backend. |
| Bloque CMS botón/documento | Label administrado | URL interna o HTTP(S) validada | Sí | Sí | Usa `<a>`; una URL interna recarga la SPA. Externas abren nueva pestaña. |
| Login | Regístrate | `/register` | Sí | Sí | Flujo de cuenta. |
| Login | No puedo iniciar sesión | `/forgot-password` | Sí | Sí | Flujo de cuenta. |
| Registro | Inicia sesión | `/login` | Sí | Sí | Flujo de cuenta. |
| Recuperación | Volver al inicio de sesión | `/login` | Sí | Sí | Aparece en éxito y formulario. |
| Reset inválido | Solicitar uno nuevo | `/forgot-password` | Sí | Sí | El reset válido suele tener entrada desde correo. |
| Mi Panel | Ver torneo | `/torneos/{championship_id}` | Autenticado | Autenticado | Desde inscripciones propias. |
| Mi Panel | Ver Torneos Disponibles | `/torneos` | Autenticado | Autenticado | Estado vacío de inscripciones. |

No hay desplegables editoriales, breadcrumbs ni enlaces de footer. Desktop y móvil consumen exactamente la misma configuración de dos destinos editoriales. Las rutas retiradas del Navbar siguen accesibles. La cabecera permanece en una fila entre 1025 y 1500 px y se convierte en menú colapsable a 1024 px; la matriz automatizada no detecta overflow ni solapamientos entre 320 y 1440 px.

## 5. Clasificación de rutas

| Clasificación | Rutas | Estado tras Fase 3C |
|---|---|---|
| Canónicas implementadas | `/`, `/competicion` | Inicio se conserva; Competición aporta una landing dinámica de temporadas y campeonatos sobre el sistema común. |
| Canónicas futuras | `/aprende-a-jugar`, `/escuela`, `/club` | Reservadas como contrato; no se registran ni enlazan sin contenido mínimo. |
| Funcionales secundarias | `/torneos`, `/torneos/:championshipId`, `/categories/:categoryId`, sus rutas de standings/schedule, `/matches/:matchId`, `/rankings` | Conservar rutas y contratos. Relacionarlas semánticamente con Competición. |
| Cuenta | `/login`, `/register`, `/forgot-password`, `/reset-password`, `/player` | Conservar separadas del menú editorial. |
| Técnica heredada | `/contenidos`, `/contenidos/:slug` | Retirar del primer nivel cuando existan destinos canónicos, pero mantener acceso y CMS hasta completar la migración. |
| Duplicada | `/nosotros` y `/contenidos/nosotros` | Elegir CMS como fuente futura; conservar ambas hasta migración y paridad verificadas. |
| Solapamiento funcional | `/categories/:id` frente a `/categories/:id/standings` y `/schedule` | No consolidar ni redirigir sin revisar usos, navegación y E2E. |
| Sin consumidor interno | `/nosotros` | Mantener por contenido, posibles marcadores y migración; medir antes de retirar. |
| Rota latente | `/dashboard` como destino de una rama no usada de `ProtectedRoute` | Corregir o eliminar sólo en una fase de código; no afecta al contrato público activo. |
| Fallback/error React | `*` | Implementado como vista accesible; la respuesta HTTP 404 del hosting sigue pendiente. |

También existen módulos React no montados: `pages/Home.jsx` y `CategoryCard`, pares `Schedule`/`useSchedule`, `Standings`/`useStandings`, `ConflictDashboard`/`useConflicts` y `MyMatches`/`useMyMatches`. No son rutas. Deben revisarse como código huérfano antes de reutilizarlos o eliminarlos; `pages/Home.jsx` no es la Home que importa `App.jsx`.

## 6. Contrato de rutas canónicas

| Ruta | Responsabilidad | Fuente de verdad | Contenido inicial mínimo | Subrutas o destinos | Estado actual |
|---|---|---|---|---|---|
| `/` | Home pública y puerta de entrada actual | Estructura React más fuentes conectadas según cada bloque | `h1`, propuesta de valor y CTA deportivo existente | Navbar y `/torneos` | Implementada y sin rediseño; el Navbar aporta el acceso a Competición. |
| `/competicion` | Landing funcional de actividad deportiva pública | API pública del dominio Laravel | `h1`, introducción, temporadas y campeonatos públicos, estados remotos y enlaces reales; sin recalcular reglas | `/torneos`, detalles, categorías, standings, schedule, partidos y `/rankings` | Fase 4A implementada. Rankings integrados y cierre funcional quedan pendientes en 4B y 4C. |
| `/aprende-a-jugar` | Entrada divulgativa a introducción, cómo se juega, Manual, Reglamento, Conceptos e Historia | Artefactos compilados desde `knowledge/` | `h1`, introducción validada y al menos un recorrido real generado; no copy editorial duplicado en JSX | Namespace formativo por definir con el contrato de Knowledge; no se ratifican todavía slugs de detalle | No. Depende de normalizar metadatos, implementar compilador y disponer de contenido para las colecciones anunciadas. |
| `/escuela` | Identidad, públicos y actividad real de la Escuela | Híbrida: `knowledge/` futuro para pedagogía estable y CMS/backend para actividad temporal | `h1`, contenido pedagógico aprobado y/o actividad publicable real con responsabilidades diferenciadas | Se definirán al existir vertical editorial, privacidad y contenido real | No. No existe colección de Escuela ni contrato CMS específico; `academy` no satisface el requisito. |
| `/club` | Landing institucional que agrupa páginas editables | CMS administrado en Blade y API pública | `h1` y enlaces a un conjunto publicado y clasificado de páginas institucionales; estado vacío controlado | Futuras páginas de Nosotros, Federarse, Federaciones, Prensa y medios, Contacto y, si se aprueba, Documentos | Parcial. El CMS y cuatro piezas existen, pero faltan el mapeo canónico, Contacto y resolver la duplicidad de Nosotros. |

Los namespaces internos de Aprende a jugar, Escuela y Club sólo se cerrarán cuando sus contratos de contenido puedan garantizar URLs estables. Los ejemplos de documentos anteriores como `/aprende` o `/manual` nunca se implementaron y no sustituyen este primer nivel.

## 7. Rutas secundarias

| Ruta o familia | Área | Tratamiento |
|---|---|---|
| `/torneos` | Competición | Mantener como listado funcional y destino de `/competicion`. No renombrar por coherencia visual. |
| `/torneos/:championshipId` | Competición | Mantener; conserva inscripciones, ranking y categorías. |
| `/categories/:categoryId` | Competición | Mantener como detalle agregado mientras tenga consumidores. |
| `/categories/:categoryId/standings` | Competición | Mantener como URL compartible de clasificación. Revisar el solapamiento, no redirigir todavía. |
| `/categories/:categoryId/schedule` | Competición | Mantener como URL compartible de calendario y resultados. |
| `/matches/:matchId` | Competición | Mantener; combina consulta pública y workflow autorizado sin exponer datos privados a visitantes. |
| `/rankings` | Competición | Mantener y enlazar desde la landing. “Rankings” deja de ser nombre de primer nivel, no deja de ser funcionalidad. |
| Rutas de cuenta | Cuenta | Mantener fuera de las cinco áreas y conservar los retornos de autenticación. |
| `/player` | Cuenta | Mantener como Mi Panel, no como subruta editorial de Competición. |

“Competición” será el único nombre de primer nivel para el dominio deportivo. “Torneos”, “Campeonatos”, “Calendarios”, “Clasificaciones” y “Rankings” son labels funcionales secundarios.

## 8. Rutas técnicas y heredadas

`/contenidos` y `/contenidos/:slug` son útiles para consumir el CMS actual, pero revelan la estructura técnica en la URL y el índice mezcla cualquier página publicada sin taxonomía de área. Deben desaparecer del primer nivel cuando las landings canónicas tengan contenido, no antes.

El contrato API refuerza hoy esa URL: `PublicCmsPageSummaryResource` genera `url: /contenidos/{slug}`. Una futura fachada bajo `/club` o `/escuela` requerirá coordinar Resource, enlaces guardados en bloques, React, tests, canonical y compatibilidad. No basta con cambiar Navbar.

La ruta estática `/nosotros` es heredada y duplicada, pero no está vacía. Su ausencia de enlaces internos no demuestra ausencia de tráfico externo ni autoriza su borrado.

`academy` es un slug CMS sembrado y un nombre todavía presente en Home, además de haber formado parte del Navbar anterior a 3B. No es sinónimo contractual de Escuela de Galotxas ni de Aprende a jugar. Se conservará sin reinterpretación automática hasta inventariar y migrar su contenido real.

## 9. Matriz de compatibilidad

| Ruta actual | Rol futuro | Menú de primer nivel | Compatibilidad | Condición para retirar o cambiar |
|---|---|---|---|---|
| `/` | Inicio | Sí | Canónica | No aplica. |
| `/competicion` | Competición | Sí | Canónica dinámica desde 4A | Completar 4B y 4C sin cambiar sus destinos actuales. |
| `/torneos` | Secundaria de Competición | No | Se conserva | Nueva necesidad funcional demostrada y plan de enlaces. |
| `/rankings` | Secundaria de Competición | No | Se conserva | Nueva necesidad funcional demostrada y plan de enlaces. |
| Detalles de torneo, categoría y partido | Secundarias de Competición | No | Se conservan | Consumidores migrados y equivalencia completa. |
| `/contenidos` | Índice técnico | No | Se conserva accesible temporalmente | Inventario CMS clasificado, landings completas, enlaces migrados y decisión SEO. |
| `/contenidos/:slug` | Lectura CMS heredada | No como familia; destinos canónicos por contenido | Se conserva temporalmente | Página canónica equivalente, redirect probado y enlaces/Resource actualizados. |
| `/nosotros` | Material de migración hacia Club/CMS | No | Se conserva temporalmente | CMS canónico con paridad, revisión editorial y redirect aprobado. |
| Rutas de auth | Zona de cuenta | Separadas | Se conservan | Sólo cambios propios del flujo de cuenta. |
| `/player` | Mi Panel | Separada | Se conserva | No se migra al árbol editorial. |

No se ha localizado un enlace público activo cuyo destino carezca hoy de `Route`. La excepción es la rama inactiva hacia `/dashboard` ya descrita. Sí faltan enlaces entrantes para `/nosotros` y rutas para las tres landings futuras, que todavía no deben enlazarse.

## 10. Propuesta de redirects futuros

No se implementa ningún redirect en 3B.

| Origen | Destino propuesto | Tipo por ahora | Momento | Motivo y condición |
|---|---|---|---|---|
| `/torneos` | Sin redirect | Sin redirect | Indefinido | Es una ruta funcional secundaria, no una landing obsoleta. |
| `/rankings` | Sin redirect | Sin redirect | Indefinido | Conserva una funcionalidad y enlaces directos. |
| Rutas de detalle de competición | Sin redirect | Sin redirect | Indefinido | Los IDs, workflows y enlaces existentes son válidos. |
| `/contenidos` | Por decidir | Decisión aplazada | Migración posterior | No existe todavía un índice canónico equivalente. |
| `/contenidos/nosotros` | `/club/nosotros` | Alias temporal y posterior redirect por decidir | Tras crear Club y verificar paridad | La fuente será CMS; deben revisarse Resource, canonical y enlaces guardados. |
| `/nosotros` | `/club/nosotros` | Redirect permanente candidato | Tras migración editorial y medición | Elimina la fuente React duplicada sin perder marcadores. |
| `/contenidos/federarse` | `/club/federarse` | Alias temporal candidato | Tras crear la página canónica | Preservar URLs y contenido CMS. |
| `/contenidos/federaciones` | `/club/federaciones` | Alias temporal candidato | Tras crear la página canónica | Preservar URLs y contenido CMS. |
| `/contenidos/prensa-media` | `/club/prensa-media` | Alias temporal candidato | Tras crear la página canónica | Mantener el slug actual reduce cambios innecesarios. |
| `/contenidos/documentos` | `/club/documentos` u otra área aprobada | Decisión aplazada | Tras clasificar el contenido | “Documentos” puede servir a más de un área. |
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
| Aprende a jugar | `knowledge/` | Presentar artefactos generados | Compilador futuro valida y genera; Laravel no sirve el Manual v1. |
| Escuela de Galotxas | `knowledge/` futuro + CMS/backend | Componer ambas fuentes sin duplicarlas | Compilador para pedagogía estable; CMS para actividad y publicación. |
| Club | CMS | Landing y presentación de páginas públicas | Blade administra; API excluye borradores y publicaciones futuras. |
| Cuenta | Dominio Laravel autenticado | Formularios y Mi Panel | Autenticación, autorización y datos propios. |

## 13. CMS y páginas institucionales

El inventario se basa en código, seeders y tests, sin consultar la base de desarrollo. `InstitutionalCmsPageSeeder` garantiza seis páginas publicadas sólo cuando el slug no existía; no sobrescribe contenido previo. `E2ESmokeSeeder` añade `e2e-publicada` exclusivamente a la base temporal E2E. Los slugs de factories y casos de prueba no forman un catálogo editorial.

| Contenido | Ruta actual verificable | Fuente | Duplicado | Ruta futura propuesta |
|---|---|---|---|---|
| Prensa y medios | `/contenidos/prensa-media` | CMS; seeder y Navbar anterior a 3B | No localizado | `/club/prensa-media` después de migración |
| Nosotros | `/nosotros` y `/contenidos/nosotros` | React estático + CMS | Sí | `/club/nosotros`, con CMS como fuente canónica |
| Federaciones | `/contenidos/federaciones` | CMS; seeder y Navbar anterior a 3B | No localizado | `/club/federaciones` |
| Federarse | `/contenidos/federarse` | CMS; seeder, sin enlace actual de Navbar | No localizado | `/club/federarse` |
| Documentos | `/contenidos/documentos` | CMS; seeder, sin enlace actual de Navbar | No localizado | `/club/documentos` sólo si la clasificación editorial lo confirma |
| Academy | `/contenidos/academy` | CMS; seeder, Navbar anterior a 3B y copy estático de Home | Hay representación duplicada en la interfaz, no una segunda página completa | Decisión aplazada; no equivale a `/escuela` |
| Contacto | No existe slug sembrado, enlace ni ruta específica | Sin fuente actual verificada | No | `/club/contacto` cuando exista contenido y flujo real |
| Índice CMS | `/contenidos` | API de páginas publicables | No aplica | No será área de primer nivel; destino final por decidir |
| Página E2E | `/contenidos/e2e-publicada` sólo en E2E | Seeder temporal | No aplica | Nunca forma parte del catálogo de producción |

Los tests emplean además slugs como `borrador`, `programada`, `federarse`, `academy` y otros valores sintéticos para validar publicación. Su presencia no acredita contenido real. El endpoint CMS aplica el mismo scope `published` en índice y detalle: estado `published` y `published_at` nulo o no futuro.

## 14. Knowledge y Aprende a jugar

La estructura actual contiene:

- nueve documentos en `knowledge/reglamento/`, incluida la metodología;
- 33 Markdown bajo `knowledge/conceptos/`, incluido su README, repartidos entre elementos, juego y personas;
- IDs, títulos y versiones en el front matter;
- sólo 27 archivos con `slug`; el reglamento, `pilota` y las cuatro fichas de personas carecen de slug;
- ninguna colección de Historia, Escuela, multimedia o referencias;
- ningún compilador ni artefacto generado para React.

Por tanto, Reglamento y Conceptos aportan material real para el futuro, pero todavía no un contrato navegable completo. Antes de registrar `/aprende-a-jugar` deben normalizarse los metadatos, definir relaciones y orden, validar slugs y generar artefactos deterministas. Historia no debe aparecer como enlace vacío hasta disponer de una colección aprobada.

El Manual será una organización y un consumidor de `knowledge/`, no una copia editable en JSX, base de datos o CMS. La ruta de la landing queda fijada; las rutas de detalle se cerrarán con el contrato editorial para no prometer slugs incompatibles.

## 15. Escuela híbrida

Escuela de Galotxas es una sección distinta de Academy y del Manual. Su parte estable podrá incluir metodología, ejercicios y recursos pedagógicos desde una colección futura de `knowledge/`. La actividad real —talleres, centros, docentes, fechas, noticias, galerías, documentos o inscripciones— requerirá una vertical CMS/backend con estados, permisos y API adecuados.

Antes de publicar `/escuela` se requieren al menos:

1. propósito y audiencias aprobados;
2. contenido real mínimo con propietario editorial;
3. separación explícita entre material estable y actividad operativa;
4. modelo de privacidad, consentimiento y retirada para datos o imágenes de menores;
5. URLs y Resources de la actividad publicable;
6. estados remotos, accesibilidad y pruebas.

El CMS genérico demuestra una infraestructura, pero el slug `academy` y dos bloques sembrados no demuestran estas capacidades verticales.

## 16. Competición funcional

La landing `/competicion` dispone de dependencias funcionales suficientes y la API pública verificada ofrece:

| Necesidad | Endpoint | Consumidor React actual |
|---|---|---|
| Temporadas y jerarquía pública | `GET /api/v1/seasons` | Landing de Competición y rankings mediante servicio |
| Listado de campeonatos | `GET /api/v1/championships` | `/torneos` |
| Detalle de campeonato | `GET /api/v1/championships/{id}` | `/torneos/:championshipId` |
| Ranking de campeonato | `GET /api/v1/championships/{id}/ranking` | Detalle de torneo |
| Detalle de categoría | `GET /api/v1/categories/{id}` | Detalle, standings y schedule |
| Clasificación | `GET /api/v1/categories/{id}/standings` | Detalle y ruta dedicada |
| Calendario y resultados | `GET /api/v1/categories/{id}/schedule` | Detalle y ruta dedicada |
| Partido | `GET /api/v1/matches/{id}` | Detalle de partido |
| Ranking de temporada | `GET /api/v1/seasons/{id}/ranking` | `/rankings` |
| Ranking histórico | `GET /api/v1/rankings/all-time` | `/rankings` |
| Inscripción | `GET .../registration` y `POST .../register` | Detalle de torneo autenticado |

Los listados, detalles, relaciones y datos derivados aplican visibilidad efectiva en backend. Fase 4A elige `GET /api/v1/seasons` como única fuente del resumen porque su envelope ya incluye temporadas ordenadas por fecha de inicio descendente, campeonatos públicos asociados y `categories_count`. No se solicita `/championships`, no se llama a detalles y no existe N+1 en React.

`championshipsService` interpreta el envelope, `useCompetitionOverview` gestiona loading, error, retry, vacío y desmontaje, y los componentes específicos presentan temporada, estado, fechas disponibles, campeonatos y enlaces `/torneos/{id}`. React conserva el orden recibido, no usa el `slug` nullable de Season, no muestra ni vuelve a filtrar `is_public` y no infiere estados a partir de fechas. Torneos y Rankings siguen visibles en todos los estados. La integración de Rankings queda en 4B y el resto del cierre funcional en 4C.

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

Playwright cubre 320, 375, 768, 1024, 1280 y 1440 px con una identidad deliberadamente larga. No detecta desbordamiento horizontal ni solapamiento entre los grupos visibles del Navbar. La futura ampliación a cinco áreas deberá repetir esta matriz.

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

Los tests actuales de Navbar cubren la lista exacta de dos enlaces, cuenta anónima/autenticada, matcher de toda la rama deportiva, estado visual, ARIA, Escape, foco y cierres. Las pruebas de App y páginas cubren `/competicion`, wildcard, rutas dinámicas, regresiones y landmarks. El E2E cubre navegación desktop/móvil, separación de cuenta, estado activo, 404, matriz responsive, CMS, CTA, calendario, partidos, Mi Panel y resultados. Canonical, migración institucional, multibrowser y las tres landings futuras siguen pendientes.

PUBLIC-LANDING-SYSTEM-1 añade en 3C tests de contenedor, cabecera, acciones, secciones, rejilla, tarjetas y metadatos; verifica IDs estables, `aria-labelledby`, un solo `h1`, ausencia de `<main>` anidado y controles anidados, restauración de description/robots, ausencia de llamadas API y de rutas placeholder. Playwright añade una matriz específica de la landing a 320–1440 px, comprueba legibilidad, overflow, foco por Tab y navegación con Enter.

Línea base de Fase 3A, 2026-07-19: `npm run test:run` completó 65 tests en 18 archivos; `npm run lint` y `npm run build` finalizaron sin errores; `npm run e2e` completó sus nueve escenarios Chromium sobre el stack Docker temporal. No se ejecutó la suite backend completa porque no se modificó backend.

Validación de Fase 3B, 2026-07-19: `npm run test:run` completó 105 tests en 23 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 13 escenarios Chromium y la matriz 320–1440 px sobre el stack temporal. Backend no se modificó ni se ejecutó su suite completa.

Validación de Fase 3C, 2026-07-19: `npm run test:run` completó 118 tests en 25 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 14 escenarios Chromium, incluidos responsive y teclado de la landing, sobre el stack temporal. Backend no se modificó ni se ejecutó su suite completa.

COMPETITION-LANDING-DATA-1 añade en 4A pruebas de servicio, hook y página para éxito, loading, error, retry, vacíos global y local, nullables, etiquetas deportivas, fechas, enlace contextual y ausencia de campos técnicos. Playwright consume los datos públicos reales de `E2ESmokeSeeder`, abre el detalle por teclado y repite la matriz 320–1440 px sobre la jerarquía dinámica.

Validación de Fase 4A, 2026-07-19: `npm run test:run` completó 134 tests en 28 archivos; lint y build finalizaron sin errores; `npm run e2e` completó 14 escenarios Chromium sobre MariaDB temporal. Backend no se modificó ni se ejecutó su suite completa.

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

Home no se rediseña: conserva su CTA directo a Torneos y usa Navbar como entrada a Competición. El footer continúa exclusivo de Home. La rama `/dashboard` no consumida, Reset Password y estas diferencias de estructura permanecen como deuda explícita.

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

No se creó un estado remoto común porque Torneos, Rankings, CMS y Mi Panel no ofrecen todavía dos adopciones compatibles sin cambiar contratos. Tampoco se registraron `/aprende-a-jugar`, `/escuela` o `/club`; la base queda disponible para cuando sus fuentes reales superen los gates. Fase 3C no sustituye el contrato y compilador de Knowledge, la vertical de Escuela ni el desarrollo completo de Competición previsto en Fase 4.

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

Fase 4 continúa abierta. La integración de Rankings corresponde a 4B; 4C conserva el resto del cierre de la experiencia deportiva pública. Ninguno de ambos subbloques se inició en 4A.

## 23. Deuda aplazada

- normalizar slugs y metadatos de `knowledge/` e implementar su compilador;
- definir colecciones reales de Historia y Escuela;
- crear contrato CMS operativo de Escuela, con privacidad de menores;
- consolidar el contenido institucional y clasificar `documentos` y `academy` antes de migrarlos, sin equivalencias automáticas;
- crear y administrar Contacto;
- migrar Nosotros y resolver su duplicidad;
- decidir URLs de detalle bajo Aprende a jugar, Escuela y Club;
- definir aliases, redirects, canonical e indexación de `/contenidos` tras verificar paridad;
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
- 4B y 4C continúan pendientes y Fase 4 no se marca completa.

### Implementación posterior

- los cinco destinos enlazados existen y aportan contenido real;
- ningún enlace de primer nivel depende de `/contenidos`;
- desktop y móvil exponen el mismo árbol y la cuenta permanece separada;
- rutas secundarias de competición conservan URL y funcionalidad;
- estado activo, teclado, foco, landmarks y headings cumplen el contrato;
- metadatos, canonical, 404, fallback y redirects son coherentes entre React y hosting;
- pruebas frontend y E2E cubren navegación, permisos, compatibilidad y fuentes remotas;
- no se duplica contenido editable entre React, CMS y `knowledge/`.

## Mantenimiento

Este contrato debe actualizarse antes o junto con cualquier cambio visible de Navbar, rutas canónicas, aliases, redirects o fuente editorial. Una ruta futura pasa a implementada sólo cuando código, contenido, despliegue y pruebas lo demuestran.
