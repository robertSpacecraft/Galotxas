# Auditoría de paridad backend/frontend y plan del MVP completo

## 1. Propósito

Este documento registra `MVP-PARITY-AUDIT-1`, la auditoría documental de Fase
7A. Su finalidad es comparar las capacidades reales de Laravel, Blade, API y
React, separar lo público de la autogestión y la administración, y definir qué
falta para publicar una primera versión útil de Galotxas.

La auditoría no implementa rutas, componentes, modelos, endpoints, contenido,
datos ni configuración de despliegue. Las recomendaciones y fases pendientes
no deben leerse como funcionalidad existente.

## 2. Estado actual

- Las fases 0–6C.1 están integradas en `develop`.
- Competición, Aprende a jugar y Escuela disponen de recorridos públicos
  funcionales respaldados por sus fuentes de verdad.
- El área autenticada permite las tareas principales de participación y
  resultados, pero no toda la capacidad que ya existe en el backend.
- Blade concentra correctamente la administración de dominio, CMS y Escuela.
- El área institucional sigue dividida entre una página React hardcodeada y
  páginas CMS genéricas no incorporadas a una arquitectura pública canónica.
- No existe una página institucional de Contacto acreditada en el repositorio.
- No hay configuración versionada suficiente para considerar preparado un
  despliegue de producción separado entre Railway y Vercel.
- El candidato técnico anterior no equivale a un MVP público completo. Fase 7
  permanece abierta hasta cerrar los bloqueantes P0 y la validación final.

La inspección parte de un árbol limpio en `develop`, con Fase 6, Fase 6C.1 y el
aislamiento Docker presentes.

## 3. Alcance

Se han inspeccionado:

- modelos, enums, migraciones, relaciones, factories y seeders;
- controladores, Services, Form Requests, Resources y middleware;
- rutas públicas, autenticadas, administrativas API y administrativas Blade;
- rate limiters, notificaciones, autorización y tests backend;
- router React, navegación, páginas, features, servicios, hooks y formularios;
- tests Vitest/RTL, Playwright, carga diferida, accesibilidad y responsive;
- CMS, Knowledge, Escuela y contenido institucional;
- archivos de entorno, Docker local y ausencia de contratos Railway/Vercel.

Quedan fuera los datos reales de cualquier base de datos: no se han ejecutado
migraciones, seeders ni consultas sobre la base de desarrollo. Por ello, la
existencia de contenido editorial real o de una configuración operativa real
debe validarse por entorno y por una persona responsable.

## 4. Metodología

1. Preflight Git y verificación de los commits de Fase 6/6C.1.
2. Lectura de todos los `AGENTS.md`, guías de estilo, runners seguros y
   documentación vigente.
3. Inventario estático de archivos, referencias, rutas y consumidores.
4. Ejecución de `php artisan route:list --except-vendor --json`, sin iniciar
   contenedores ni acceder a datos.
5. Comparación vertical: persistencia → dominio → administración → API →
   React → tests.
6. Clasificación de cada capacidad por audiencia, prioridad, dependencia y
   riesgo.
7. Revisión de requisitos operativos actuales de Railway y Vercel mediante sus
   fuentes oficiales.

No se han ejecutado suites: este bloque verifica cobertura existente por
inspección y conserva como última instantánea validada la registrada tras
6C.1.

## 5. Inventario backend

### 5.1. Resumen por dominio

| Dominio | Función y fuente de verdad | Blade | API pública | API autenticada | React | Clase | Estado |
|---|---|---:|---:|---:|---:|---|---|
| Usuario y autenticación | `User`, Sanctum, recuperación de contraseña | Sí | Registro, login y reset | Sesión API y `/me` | Sí | B/D | Funcional |
| Perfil deportivo | `Player`, relación 1:1 opcional con `User` | Sí | No | Consulta, creación y edición parcial propia | Creación y consulta | B/C | Parcial en React |
| Temporada | `Season`, estado operativo y visibilidad explícita | Sí | Listado, detalle y ranking | Lecturas personales indirectas | Sí | A/C | Funcional |
| Campeonato | `Championship`, reglas de inscripción y visibilidad | Sí | Listado, detalle y ranking | Inscripción y estado propio | Sí | A/B/C | Funcional |
| Categoría | `Category`, nivel, género, modalidad y visibilidad | Sí | Detalle, standings y schedule | Participación propia indirecta | Sí | A/B/C | Funcional |
| Ronda | `Round` | Mediante calendario/partidos | Anidada | Anidada | Sí | A/C | Funcional |
| Partido | `GameMatch`, pista, horario y estado | Sí | Detalle de partido público efectivo | Partidos y calendario propios | Sí | A/B/C | Funcional |
| Resultados | `MatchResultReport` y Services de workflow | Conflictos y validación | Resultado validado en partido | Reportar y confirmar | Sí | A/B/C | Funcional |
| Clasificaciones | `RankingService` y consultas de categoría | Vista administrativa | Categoría/campeonato/temporada/histórico | Ranking propio | Sí | A/B/C | Funcional |
| Equipos y entradas | `Team`, `CategoryEntry`, asignaciones | Sí | Participantes/equipos en contextos deportivos | Relación indirecta en partidos/ranking | Parcial | A/B/C | Sin resumen propio |
| Inscripción deportiva | `ChampionshipRegistrationRequest` y `CategoryRegistration` | Sí | Inicio anónimo controlado por campeonato | Solicitud y estado propios | Sí | B/C | Funcional básico |
| Pistas deportivas | `Venue` y generadores | Sí | Incluidas cuando corresponde | Incluidas en partidos propios | Sí | A/C | Funcional |
| Reprogramación | `MatchRescheduleRequest` | Supervisión indirecta | No | Solicitar y confirmar | No | B/C | Backend sin React |
| CMS | `CmsPage` y `CmsBlock` estructurados | Sí | Índice y detalle publicado | No | Renderer y rutas genéricas | A/C/E | Genérico funcional |
| Programa escolar | `SchoolProgram` | Sí | Agregado `/school` | No | Sí | A/C | Código funcional |
| Niveles | `SchoolLevel` | Sí | Dentro del agregado | No | Sí | A/C | Código funcional |
| Horarios y ubicaciones | `SchoolSchedule`, `SchoolLocation` | Sí | Sólo efectivos en agregado | No | Sí | A/C | Código funcional |
| Inscripción escolar | `SchoolEnrollment` | Sí, privada | POST anónimo sin lectura | Vinculación opcional, sin consulta propia | Formulario anónimo | B/C | Funcional y privado |
| Centros educativos | `EducationalCenter` | Sí | No | No | No | C | Correctamente interno |
| Actividades educativas | `EducationalActivity` | Sí | No | No | No | C | Correctamente interno |

Clases: **A** pública, **B** autogestión autenticada, **C** administración,
**D** infraestructura y **E** obsoleta o heredada. Un mismo dominio puede
tener responsabilidades de varias clases.

### 5.2. Administración y autorización

El panel Blade cubre usuarios, jugadores, competición completa, pistas,
calendarios, partidos, conflictos, CMS y toda la vertical administrativa de
Escuela. Las rutas se protegen mediante autenticación, usuario activo y
administrador. La API administrativa de competición también exige
administrador activo y usa Form Requests y Resources dedicados.

No existen clases `Policy` o `Gate` en el código actual. La autorización se
resuelve con middleware de rol/actividad y comprobaciones de participante o
`authorize()` en los flujos específicos. Esto no abre por sí mismo una carencia
MVP, pero obliga a mantener explícito el límite entre administración y
autogestión al ampliar el producto.

Las sesiones de administrador activo están cubiertas por middleware y
regresiones específicas; la deuda histórica de sesión administrativa inactiva
ya no sigue abierta.

### 5.3. Infraestructura de dominio

- Los Services concentran rankings, generación de liga/copa, resultados,
  reprogramaciones, inscripciones deportivas y operaciones de Escuela.
- Los Resources públicos limitan campos en partidos, CMS y Escuela.
- Los rate limiters cubren autenticación, resultados e inscripción escolar.
- La reprogramación autenticada no dispone aún de Form Request dedicado ni de
  rate limiter equivalente.
- La recuperación de contraseña construye la URL desde `FRONTEND_URL`; un
  proveedor de correo real es requisito operativo de producción.
- `image_path` existe en entidades deportivas, pero no hay un flujo de subida o
  almacenamiento persistente. Los bloques CMS de imagen/documento almacenan
  URLs, no ficheros administrados.

### 5.4. Factories, seeders y datos iniciales

Las factories expresan estados públicos/privados y escenarios suficientes para
las suites. `E2ESmokeSeeder` está restringido al entorno y base E2E.

`DatabaseSeeder` crea datos de demostración y una cuenta administrativa
predecible. No puede ejecutarse en producción. Tampoco constituye contenido
editorial aprobado: no configura Escuela ni CMS institucional. El seeder CMS
institucional separado crea un catálogo genérico, no prueba que los textos
reales estén aprobados ni cargados en cada entorno.

La creación inicial del administrador de producción necesita un procedimiento
seguro, explícito y auditable distinto de los seeders de demo.

## 6. Inventario API

`route:list` registra 185 rutas de aplicación: 58 bajo `/api/v1` y 127 web. De
las rutas API, 40 están protegidas por Sanctum y 18 no usan Sanctum. La
clasificación funcional es:

| Superficie | Capacidades | Audiencia | Estado |
|---|---|---|---|
| Lectura pública deportiva | Temporadas, campeonatos, categorías, partidos, standings, schedule y rankings | Visitante | Funcional y filtrada por visibilidad efectiva |
| CMS público | Índice y detalle por slug de páginas publicadas | Visitante | Funcional y genérico |
| Escuela pública | Agregado de programa y POST de solicitud | Visitante | Funcional; lectura sin datos privados |
| Autenticación anónima | Registro, login, recuperación y reset | Visitante | Funcional; correo depende de operación |
| `/me` | Usuario, perfil, inscripciones, partidos, calendario y rankings | Usuario activo | Funcional |
| Resultados | Envío, confirmación y acciones pendientes | Participante activo | Funcional |
| Reprogramación | Solicitud y confirmación | Participante activo | Sin consumidor React |
| Inscripción deportiva | Estado y solicitud propia | Usuario activo | Funcional |
| API administrativa | CRUD deportivo y operaciones de revisión | Administrador activo | Interna; no debe publicarse como autogestión |

No existe API pública de centros, actividades, alumnado, solicitudes escolares
o notas administrativas. Esa ausencia es intencionada. Tampoco existe endpoint
de contacto, noticias o formulario institucional.

## 7. Inventario frontend y matriz de rutas

| Ruta | Audiencia | Fuente | Estado | Carencias | Prioridad |
|---|---|---|---|---|---|
| `/` | Pública | Copy React | Funcional parcial | Afirmaciones sin acreditación, tarjetas sin destino y footer sólo local | P0 |
| `/competicion` | Pública | API deportiva | Funcional | Falta E2E de inscripción desde el recorrido | P0 de validación |
| `/torneos` | Pública | API deportiva | Funcional secundaria | Nombre/ruta heredados respecto a Competición | Deuda |
| `/torneos/:championshipId` | Pública/autenticada | API deportiva | Funcional | Registro poco cubierto en frontend/E2E | P0 de validación |
| `/categories/:id` | Pública | API deportiva | Funcional | Sin bloqueo MVP | — |
| `/categories/:id/standings` | Pública | API deportiva | Funcional | Sin bloqueo MVP | — |
| `/categories/:id/schedule` | Pública | API deportiva | Funcional | Sin bloqueo MVP | — |
| `/matches/:id` | Pública/autenticada | API pública y propia | Funcional | Reprogramación no expuesta | P1 |
| `/rankings` | Pública | API deportiva | Funcional | Sin bloqueo MVP | — |
| `/aprende-a-jugar` | Pública | Proyección Knowledge | Funcional y diferida | Debe integrarse en la IA propuesta | P0 de navegación |
| `/aprende-a-jugar/manual` y descendientes | Pública | Proyección Knowledge | Funcional y diferida | Sin bloqueo funcional | — |
| `/escuela` | Pública | API School | Funcional y diferida | Requiere datos, contacto y privacidad reales | P0 operativo |
| `/login` | Anónima | API auth | Funcional | Sin bloqueo | — |
| `/register` | Anónima | API auth | Funcional | Sin bloqueo | — |
| `/forgot-password` | Anónima | API auth | Funcional | Correo real no configurado | P0 operativo |
| `/reset-password` | Anónima | API auth | Funcional | Depende de URL y correo de producción | P0 operativo |
| `/player` | Autenticada | `/me` | Funcional como Mi Panel | Edición de perfil existente y equipo propio incompletos | P1 |
| `/contenidos` | Pública | CMS API | Genérica legada | No es arquitectura institucional canónica | Deuda |
| `/contenidos/:slug` | Pública | CMS API | Genérica legada | Sin aliases/canonical y no integrada en Club | P0 institucional |
| `/nosotros` | Pública | JSX hardcodeado | Heredada | Copy, cargos placeholder, imágenes y duplicidad CMS | P0 |
| `*` | Pública | React Router | Funcional | Hosting debe conservar fallback SPA | P0 despliegue |
| `/club/quienes-somos`, `/club/contacto`, `/club/federarse`, `/club/documentos` | Pública | CMS futuro | Ausentes | Contrato cerrado en 7B; contenido e implementación pendientes | P0 |

El router mantiene las rutas deportivas actuales y una 404 React. La carga
diferida de Aprende y Escuela evita reincorporar Knowledge al chunk inicial.
La última instantánea de build validada tras 6C.1 mantiene School separado,
Knowledge fuera del chunk inicial y no presenta la incidencia histórica de
tamaño. No se ha repetido el build en esta auditoría documental.

## 8. Área autenticada y matriz de autogestión

| Función personal | Backend/API | Frontend | Acción MVP |
|---|---|---|---|
| Registro, login y logout | Sí | Sí | Mantener y validar en producción |
| Recuperación y reset | Sí | Sí | Configurar correo y URL reales; smoke obligatorio |
| Usuario actual | Sí | Sí | Mantener |
| Crear perfil `Player` | Sí | Sí | Mantener |
| Consultar perfil | Sí | Sí | Mantener |
| Editar perfil existente | Parcial: nickname, mano dominante y notas | No hay flujo completo | P1; no bloquea la primera publicación |
| Solicitar inscripción deportiva | Sí | Sí | Mantener; añadir E2E crítico |
| Consultar inscripción propia | Sí | Sí | Mantener |
| Consultar equipos propios | Sólo aparece indirectamente en partidos/rankings | Sin resumen propio | P1; aclarar participación, no autoadministrar equipos |
| Consultar partidos/calendario/ranking propios | Sí | Sí | Mantener |
| Reportar/confirmar resultados | Sí | Sí | Mantener |
| Solicitar/confirmar reprogramación | Sí | No | P1 con endurecimiento backend |
| Consultar solicitud escolar propia | No por contrato de privacidad | No | Descartada para MVP |
| Acceder a funciones administrativas | Sí, sólo administradores | No | Debe permanecer fuera de React |

La autogestión mínima útil ya permite participar en competición y tramitar
resultados. El MVP no necesita convertir React en panel administrativo ni dar
al usuario control de equipos, cuadros, estados o solicitudes de terceros.

## 9. Competición de extremo a extremo

El recorrido permite:

1. localizar temporadas y campeonatos públicos desde `/competicion`;
2. entrar en campeonato y consultar sus categorías;
3. ver detalle, clasificación y calendario de categoría;
4. consultar partidos y resultados;
5. ver participantes/equipos cuando forman parte del contrato contextual;
6. solicitar inscripción si el campeonato la admite;
7. consultar el estado propio en Mi Panel;
8. consultar partidos, calendario, ranking y acciones de resultado propias.

La API excluye ramas privadas mediante visibilidad efectiva. Los estados
remotos principales distinguen carga, error recuperable, vacío y contenido.

Carencias:

- el flujo real de inscripción deportiva desde campeonato hasta Mi Panel no
  está cubierto como recorrido Playwright completo;
- la reprogramación existe en backend, pero no en React;
- no hay un resumen directo de “mi equipo”, aunque el equipo aparece en
  contextos de participación;
- persisten nombres de URL heredados (`/torneos`) que no bloquean el MVP;
- debe confirmarse humanamente qué identidad de participantes puede mostrarse
  públicamente en rankings y competiciones.

La mejora visual general queda después del MVP si no impide comprender entidad,
estado, fecha, jerarquía o acción.

## 10. Escuela

### 10.1. Código implementado

- programa, niveles, ubicaciones y horarios administrables;
- publicación exclusiva y visibilidad efectiva;
- agregado público `GET /api/v1/school`;
- solicitud anónima `POST /api/v1/school/enrollments`;
- menores y adultos, representante condicional, teléfono y correo obligatorios;
- administración privada de solicitudes;
- centros y actividades exclusivamente internos;
- landing React, formulario, estados remotos, accesibilidad, responsive y E2E.

### 10.2. Configuración operativa pendiente

El repositorio no acredita datos reales. Antes de publicar, el administrador
debe:

1. crear o verificar una ubicación escolar activa;
2. crear un programa, mantenerlo privado y cerrado durante la preparación;
3. completar únicamente nombre, contacto y ubicación aprobados;
4. crear niveles reales con edades y orden confirmados;
5. crear horarios reales vinculados a niveles y ubicaciones activas;
6. revisar el agregado público con el programa aún cerrado;
7. publicar el programa;
8. abrir inscripciones sólo tras la aprobación de privacidad y capacidad
   operativa para responder;
9. verificar periódicamente horarios, contacto y tratamiento de solicitudes;
10. cerrar inscripciones cuando la organización no pueda tramitarlas.

No deben ejecutarse seeders para sustituir esta configuración.

### 10.3. Contenido editorial pendiente

Nombres, presentación, edades, horarios, profesorado, metodología y contacto
requieren aportación humana. Knowledge no contiene hoy una colección escolar
aprobada; Escuela puede enlazar al Manual, pero no inventar una metodología ni
duplicar contenido pedagógico.

### 10.4. Privacidad operativa pendiente

La solicitud trata fecha de nacimiento, teléfono, correo y, para menores,
representante. Antes de abrirla en producción deben estar aprobados el
responsable, finalidad, base jurídica, información al interesado, conservación,
canal de derechos, acceso administrativo y procedimiento de borrado. Cuando
participan menores, la edad y la intervención del representante deben revisarse
con el responsable legal y de privacidad de la organización.

La Agencia Española de Protección de Datos recuerda que el tratamiento de
datos de menores exige atender a la edad y, cuando corresponda, al
consentimiento de quienes ejercen patria potestad o tutela. También exige
informar de forma clara sobre responsable, fines, legitimación y derechos. Este
documento no sustituye la revisión jurídica:

- <https://www.aepd.es/preguntas-frecuentes/10-menores-y-educacion/FAQ-1001-cual-es-la-edad-para-que-los-menores-puedan-prestar-consentimiento-para-tratar-sus-datos-personales>
- <https://www.aepd.es/preguntas-frecuentes/10-menores-y-educacion/FAQ-1002-se-puede-recabar-y-tratar-datos-personales-de-menores>
- <https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/6-el-deber-de-informacion>

## 11. Inventario CMS y contenido institucional

### 11.1. Matriz de páginas

| Slug/área | Uso actual | Navegado | Contenido real acreditado | Decisión MVP |
|---|---|---:|---:|---|
| `nosotros` | CMS genérico y duplicado por `/nosotros` React | No desde Navbar | No | Migrar/verificar como fuente de “Quiénes somos”; retirar duplicidad sólo con paridad |
| `prensa-media` | Página CMS sembrable | No | No | Footer o navegación secundaria si existe responsable y contenido real |
| `federaciones` | Página CMS sembrable | No | No | Footer/secundaria; no ocupar el menú principal por defecto |
| `academy` | Página CMS legada | No | No | Mantener sin modificar ni redirigir; auditar en bloque futuro |
| `documentos` | Página CMS sembrable | No | No | Integrar en Club tras inventario de piezas y URL canónica |
| `federarse` | Página CMS sembrable | No | No | Integrar en Club si existe proceso real vigente |
| Contacto | No existe slug acreditado | No | No | Crear como contenido CMS real en fase institucional |

Las páginas del seeder son estructura de demostración, no contenido aprobado.
Los datos reales no se han consultado. Antes de migrar se deben inventariar por
entorno slugs, estados, bloques, enlaces y consumidores.

### 11.2. “Quiénes somos”

`/nosotros` contiene actualmente JSX editorial extenso, imágenes, afirmaciones
institucionales y placeholders de cargos. React no debe ser la fuente editable
de ese contenido. Además de validar veracidad y vigencia, se deben acreditar
procedencia, licencia y, si aparecen personas o menores, consentimiento y
posibilidad de retirada.

La recomendación es una única fuente CMS, una URL canónica dentro de Club y una
migración conservadora. `/nosotros` y `/contenidos/nosotros` no deben retirarse
o redirigirse hasta disponer de paridad, pruebas y decisión de compatibilidad.

### 11.3. Federarse, Documentos, Prensa y Federaciones

- **Federarse** pertenece a Club si describe un proceso institucional real. Un
  formulario nuevo queda fuera hasta definir datos, responsable y operativa.
- **Documentos** pertenece a Club, pero el CMS actual sólo referencia URLs; la
  carga y ciclo de vida de ficheros siguen sin resolver.
- **Prensa/Media** y **Federaciones** deben ir al footer o navegación
  institucional secundaria, no al primer nivel, salvo evidencia de uso que
  justifique otro lugar.
- Ninguna tarjeta o enlace debe publicarse sin destino y contenido real.

### 11.4. `academy`

`academy` no es la Escuela pública actual. Debe permanecer como legado sin
modificación en este bloque. Su inventario editorial, compatibilidad y posible
migración o retirada son deuda posterior independiente.

## 12. Contacto

No se ha encontrado página canónica, formulario, endpoint, modelo o dato
institucional general verificable. El contacto opcional del programa escolar
pertenece a Escuela y no debe reutilizarse como contacto de todo el club.

| Alternativa | Privacidad/spam | Operación | Encaje MVP |
|---|---|---|---|
| Texto estático en React | Baja recogida de datos, pero fuente duplicada | Requiere despliegue para editar | Descartada |
| Página CMS | Sin recogida de datos | Editable en Blade | Recomendada |
| Formulario almacenado | Alto: retención, acceso, derechos y spam | Requiere gestión diaria | Posterior |
| Formulario por correo | Alto: spam, entrega y privacidad | Requiere proveedor y buzón | Posterior |
| Contacto visible sin formulario | Riesgo bajo y operación simple | Exige canal real atendido | Recomendación histórica de 7A; sustituida por ADR-034 |

Contrato propuesto: una página CMS “Contacto” con al menos un canal oficial,
vigente y atendido, aportado por la organización. Si se requieren enlaces
`mailto:` o `tel:`, el renderer CMS debe admitirlos de forma cerrada y probada;
no se deben introducir URLs o datos inventados. El formulario queda fuera del
MVP salvo decisión operativa expresa.

## 13. Navegación

### 13.1. Estado actual

El Navbar editorial comparte configuración entre desktop y móvil y muestra:

1. Inicio.
2. Competición.
3. Aprende a jugar.
4. Escuela de Galotxas.

La cuenta permanece separada. Torneos, Rankings, CMS y `/nosotros` existen como
destinos secundarios o directos. Club no existe.

### 13.2. Alternativas evaluadas

- Mantener cinco enlaces planos: es simple, pero escala mal al añadir Club y
  separa tres destinos de aprendizaje relacionados.
- Eliminar la landing Aprende y enlazar sólo el Manual: reduce un nivel, pero
  pierde la orientación, el acceso por colecciones y la entrada a contenidos
  formativos distintos.
- Integrar Escuela dentro del Manual: es incorrecto; sus datos y solicitudes
  pertenecen al dominio Laravel, no a Knowledge.
- Agrupar Aprende y Club mediante menús de revelación: mantiene fuentes y rutas
  independientes, reduce longitud y ofrece una arquitectura extensible.

### 13.3. Recomendación única de 7A, cerrada en 7B

```text
Inicio
Competición
Aprende
├── Aprende a jugar
├── Manual y reglas
└── Escuela de Galotxas
Club
├── Quiénes somos
├── Contacto
├── Federarse
└── Documentos
Cuenta
```

Rutas propuestas:

| Etiqueta | Destino |
|---|---|
| Aprende a jugar | `/aprende-a-jugar` |
| Manual y reglas | `/aprende-a-jugar/manual` |
| Escuela de Galotxas | `/escuela` |
| Quiénes somos | `/club/quienes-somos` |
| Contacto | `/club/contacto` |
| Federarse | `/club/federarse` |
| Documentos | `/club/documentos` |

Aprende a jugar y Manual no son redundantes: la primera página orienta y
presenta colecciones; la segunda cataloga el corpus. Escuela puede agruparse
por el modelo mental del visitante, pero conserva ruta, dominio y fuente
independientes.

En 7A esta recomendación no modificaba todavía el contrato de cinco áreas.
Fase 7B la acepta mediante ADR-033, elige Club sólo como disclosure y fija
`/club/quienes-somos`. La implementación, el contenido real y la
compatibilidad continúan pendientes.

### 13.4. Comportamiento requerido

- Los grupos serán botones de revelación, no enlaces vacíos ni menús sólo por
  hover.
- Desktop y móvil expondrán el mismo árbol; móvil podrá usar acordeones.
- `aria-expanded` y `aria-controls` describirán el estado.
- El enlace exacto tendrá `aria-current="page"` y el grupo conservará estado
  activo visual cuando una ruta descendiente esté abierta.
- Escape cerrará el nivel abierto y devolverá el foco al disparador.
- Navegar, pulsar fuera y cerrar el menú móvil cerrarán los submenús.
- El orden de tabulación y el foco visible se conservarán a 320–1440 px y
  200 % de zoom.
- Cuenta seguirá fuera del árbol editorial.
- Las rutas deportivas actuales se preservarán.

## 14. Home

Home debe explicar, con copy aprobado, qué ofrece Galotxas y dirigir a tareas
reales sin reproducir el menú completo:

1. acción principal hacia la competición vigente;
2. acceso de aprendizaje hacia Aprende/Manual;
3. acceso operativo hacia Escuela;
4. acceso institucional secundario hacia Club/Contacto;
5. competición actual o avisos sólo si existe una consulta o fuente editorial
   real.

El estado actual necesita revisión P0: hay tarjetas de Prensa y Federaciones
sin destino y afirmaciones estáticas no acreditadas. Deben eliminarse o
reemplazarse por destinos reales. No se redacta aquí copy definitivo ni se
propone una entidad de noticias sin fuente.

## 15. Footer

Falta un footer funcional y global. El existente forma parte de Home y no
resuelve la navegación institucional.

El contrato mínimo debe incluir:

- Quiénes somos, Contacto, Federarse y Documentos;
- Prensa/Media y Federaciones si tienen contenido real;
- privacidad y términos o aviso legal aprobados;
- copyright con titular confirmado;
- redes sociales sólo cuando se faciliten URLs oficiales;
- accesibilidad sólo si existe una declaración real mantenida.

Debe aparecer en rutas públicas, autenticadas y 404 sin duplicar landmarks ni
alterar la separación de cuenta. No se publicarán enlaces vacíos, datos
inventados o páginas legales genéricas.

## 16. Contenido no dependiente del backend deportivo

| Necesidad | Fuente recomendada | Condición |
|---|---|---|
| Presentación, historia y organización | CMS | Aportación y aprobación humana |
| Modalidad, reglas y conceptos | Knowledge | Corpus canónico vigente |
| Programa, niveles, horarios y ubicación escolar | Laravel School | Configuración operativa real |
| Metodología escolar futura | Knowledge | Sólo si existe colección aprobada |
| Contacto institucional | CMS | Canal real atendido |
| Federarse | CMS | Proceso vigente confirmado |
| Documentos institucionales | CMS + URLs | Procedencia, vigencia y ciclo de vida |
| Preguntas frecuentes institucionales | CMS | Responsable editorial |
| Privacidad, términos/aviso legal | CMS o documento controlado | Revisión humana/jurídica |
| Accesibilidad | CMS o documento controlado | Declaración real y mantenida |
| Mensajes de error, carga y vacío | Copy de interfaz React | Breve, no editorial |
| Noticias/avisos | CMS futuro sólo si hay flujo real | No crear entidad por anticipación |

## 17. Matriz de paridad backend/frontend

| Capacidad | Backend/Blade | API | React | Resultado y acción |
|---|---|---|---|---|
| Auth y recuperación | Completo | Completa | Completo | P0 sólo configuración productiva |
| Perfil `Player` | Completo | Edición parcial propia | Creación/lectura | P1 edición |
| Competición pública | Completo | Completa | Completa | Validar inscripción E2E |
| Inscripción deportiva propia | Completo | Completa | Presente | Validación crítica pendiente |
| Equipos propios | Completo admin | Indirecta | Indirecta | P1 resumen |
| Resultados propios | Completo | Completa | Completo | Mantener |
| Reprogramación propia | Completo | Completa | Ausente | P1 |
| CMS publicado | Completo | Completa | Genérico | P0 integración institucional |
| Escuela pública | Completo | Completa | Completa | P0 datos/privacidad/operación |
| Centros/actividades School | Completo | Intencionadamente ausente | Ausente | Correcto: sólo administración |
| Contacto institucional | Ausente como contenido real | Ausente | Ausente | P0 CMS |
| Footer/legal | CMS capaz, contenido ausente | Genérica | Ausente global | P0 |
| Despliegue | Docker local/test | N/A | Build local | P0 contratos Railway/Vercel |

## 18. Carencias priorizadas

El coste es relativo: **S** pequeño, **M** medio, **L** grande. No representa
horas.

### 18.1. P0 — bloquea el MVP

| Carencia | Coste | Dependencias | Riesgo | Pruebas necesarias |
|---|---:|---|---|---|
| Aprobar arquitectura Club/Aprende y URLs | S | Decisión humana | Navegación incompatible | Tests de contrato |
| Sustituir la fuente hardcodeada de `/nosotros` | M | Inventario y copy aprobado | Información falsa, duplicidad, imágenes sin procedencia | CMS/API/React/E2E |
| Publicar Club mínimo: Quiénes somos, Contacto, Federarse y Documentos reales | M | Contenido y responsable editorial | Rutas vacías o desactualizadas | Backend CMS, frontend, E2E |
| Añadir navegación agrupada, footer global y Home veraz | M | Club y legal disponibles | Accesibilidad, enlaces rotos | RTL, responsive, teclado, E2E |
| Aportar privacidad/aviso legal y revisar identidad pública de participantes | M | Responsables humano/jurídico | Datos personales y menores | Revisión humana + regresión de allowlists |
| Configurar Escuela real y mantener inscripción cerrada hasta aprobación | S/M operativo | Datos y privacidad | Solicitudes sin capacidad de respuesta | Smoke con datos controlados |
| Preparar Railway/Vercel/MariaDB, correo, CORS, sesiones, logs y backups | L | Infraestructura y secretos | Servicio no arrancable o pérdida de datos | Staging, restore y smoke |
| Cubrir inscripción deportiva e institucional como recorridos críticos | M | Datos E2E y rutas finales | Regresión invisible | Feature/RTL/Playwright |

### 18.2. P1 — necesaria poco después

| Carencia | Coste | Dependencias | Riesgo | Pruebas |
|---|---:|---|---|---|
| Edición de perfil existente | M | Aclarar campos editables | Inconsistencia User/Player | API + RTL + E2E |
| Resumen de equipo/participación propia | M | Contrato API mínimo | Exposición excesiva | Resource + RTL |
| Reprogramación React y hardening API | M | Form Request, limiter y UX | Abuso/estados incoherentes | Feature + RTL + E2E |
| Semántica de headings e internal links del CMS | S/M | Contrato de bloques | H1 múltiples/recargas | Renderer + E2E |
| Política explícita de nombres de participantes | S | Decisión humana | Privacidad | Contract tests |
| Matriz de navegador adicional | M | Infraestructura E2E | Compatibilidad | Playwright |

### 18.3. P2 — mejora

- SEO completo, canonical, sitemap y robots.
- Portada con competición o avisos dinámicos cuando haya fuente real.
- Formulario institucional de contacto con antispam, sólo si la operación lo
  requiere.
- Métricas administrativas y editoriales.
- Pipeline de multimedia persistente.
- Refinamiento visual y auditoría automatizada de accesibilidad más amplia.
- Normalización adicional de envelopes y documentación OpenAPI.

### 18.4. Deuda futura

- migración de autenticación desde Bearer en `localStorage` a cookies seguras;
- roles/policies granulares y trazabilidad editorial;
- consolidación de API administrativa;
- `academy`, aliases, redirects y retirada de rutas heredadas;
- limpieza de componentes, hooks y rutas no montadas;
- concurrencia de generación y disponibilidad global de pistas;
- pagos, notificaciones y otras funciones no necesarias para el MVP.

### 18.5. Descartadas para el MVP

- panel administrativo React;
- publicación de centros, actividades o alumnado escolar;
- seguimiento público de solicitudes escolares;
- autoadministración de equipos;
- formulario de contacto por defecto;
- páginas placeholder;
- duplicar contenido CMS o Knowledge en JSX;
- sustituir MariaDB por MySQL sin una decisión de arquitectura.

## 19. Definición observable de MVP completo

### 19.1. Visitante público

- Puede entender el propósito real del sitio y navegar sin enlaces vacíos.
- Accede a Competición, Aprende/Manual, Escuela, Club, Contacto y legal.
- Recibe estados de carga, vacío, error y 404 comprensibles.
- No ve borradores, datos privados, placeholders o afirmaciones no aprobadas.

### 19.2. Usuario autenticado

- Puede registrarse, iniciar/cerrar sesión y recuperar la contraseña con correo
  real.
- Puede crear su perfil deportivo, inscribirse y consultar el estado propio.
- Puede consultar partidos, calendario, ranking y acciones de resultado propias.
- No obtiene acceso a administración ni datos personales de terceros.

La edición avanzada de perfil, el resumen de equipo y la reprogramación visual
son P1 y no bloquean esta primera versión.

### 19.3. Administrador

- Sólo un administrador activo accede a Blade.
- Puede configurar y publicar competición, CMS y Escuela.
- Puede tramitar inscripciones, resultados y solicitudes escolares privadas.
- Dispone de procedimiento seguro de alta inicial, recuperación y operación.
- No necesita seeders de demo en producción.

### 19.4. Escuela

- Muestra únicamente programa, niveles, horarios, ubicaciones y contacto reales.
- Diferencia ausencia de programa, cierre y apertura.
- El formulario sólo se abre tras aprobar privacidad y capacidad operativa.
- Centros, actividades y solicitudes permanecen privados.

### 19.5. Competición

- El recorrido landing → campeonato → categoría → standings/schedule → partido
  es funcional y respeta visibilidad efectiva.
- La inscripción y consulta propia funcionan extremo a extremo.
- Estados, fechas, equipos/participantes y resultados se entienden en contexto.

### 19.6. Contenido institucional

- Club dispone de Quiénes somos, Contacto, Federarse y Documentos reales.
- Cada pieza tiene fuente CMS única y responsable editorial.
- `/nosotros` y `/contenidos` se conservan hasta resolver compatibilidad.
- `academy` permanece fuera de la migración MVP.

### 19.7. Navegación y responsive

- Desktop y móvil comparten árbol, cuenta separada y estados activos.
- Submenús funcionan por teclado, Escape devuelve foco y las rutas descendientes
  conservan contexto.
- Home y footer ofrecen destinos reales a 320–1440 px y 200 % de zoom.

### 19.8. Despliegue

- Frontend y backend tienen builds reproducibles y variables correctas.
- MariaDB persistente dispone de backups y restauración probada.
- CORS, correo, sesiones, logs, salud, migraciones y administrador inicial están
  resueltos.
- Los deep links SPA y API HTTPS funcionan desde dominios reales.
- Staging y producción superan el checklist y el smoke con datos controlados.

## 20. Plan de implementación

### Fase 7A — Auditoría de paridad

- **Objetivo:** inventario, definición observable del MVP y plan.
- **Alcance:** sólo documentación.
- **Fuera:** código, contenido, configuración y datos.
- **Backend/frontend/contenido:** inspección sin cambios.
- **Tests:** `route:list`, revisión de cobertura y `git diff --check`.
- **Documentación:** este informe y referencias coordinadas.
- **Cierre:** matrices, P0–P2, fases y checklist revisables.
- **Merge a `main`:** sí, tras revisión humana, como línea base documental.

### Fase 7B — Decisiones y preparación editorial — completada

- **Objetivo:** cerrar la navegación, URLs, responsabilidades, plantillas y
  gates de los materiales reales.
- **Alcance:** aprobar IA y compatibilidad; inventariar CMS, Home, Escuela e
  identidad deportiva; definir plantillas de copy, contacto, legal, imágenes y
  datos operativos sin aportarlos.
- **Fuera:** implementar rutas o cargar producción.
- **Backend/frontend:** sólo contratos afectados.
- **Contenido:** aportación y revisión humana obligatorias.
- **Tests:** criterios editoriales, de privacidad y trazabilidad.
- **Documentación:** ADR-033 y
  `15-mvp-editorial-and-navigation-contract.md`.
- **Cierre:** arquitectura y paquete de preparación listos; contenido, legal,
  identidad deportiva y datos reales continúan como gates humanos.
- **Merge a `main`:** sí si genera documentación/contenido versionado aprobado;
  la configuración de base de datos se promociona por un procedimiento aparte.

### Fase 7C — Vertical institucional Club

La auditoría preparatoria 7C.0, registrada en
`16-club-vertical-readiness-audit.md`, confirma que el soporte CMS mínimo existe
pero la identidad, la historia, el proceso de Federarse y la procedencia de
imágenes no están acreditados. Recomienda ejecutar 7C como dos pasos: cierre y
carga editorial privada (7C.1), seguidos de implementación, paridad, tests y
publicación controlada (7C.2). Esta recomendación queda pendiente de aceptación
humana y no marca 7C como iniciada ni completada.

Seguimiento 7C.1: la preparación técnica se implementa y documenta en
`17-club-technical-preparation-and-contact.md`. Incluye auditoría de assets y
CMS, dominio/API/Blade de contacto desactivados por defecto y servicio React
aislado. La carga editorial manual no se ejecuta; 7C.2, privacidad, contenido,
imágenes y publicación continúan pendientes. ADR-034 sustituye la exclusión
inicial del formulario sin modificar las rutas canónicas.

- **Objetivo:** ofrecer Club con fuente CMS única y contenido real.
- **Alcance:** Quiénes somos, Contacto, Federarse y Documentos; cuatro URLs
  canónicas; aliases temporales y migración conservadora de Nosotros.
- **Fuera de 7C.1:** `academy`, redirects definitivos, interfaz pública de
  contacto y multimedia administrada.
- **Backend:** base de contacto ya preparada; ampliar contratos CMS sólo si la
  composición real lo exige.
- **Frontend:** rutas Club, consumo CMS, formulario y estados remotos en 7C.2.
- **Contenido:** carga aprobada y revisión de publicación.
- **Tests:** publicación/ocultación, renderer, rutas, 404, privacidad y E2E.
- **Documentación:** navegación, gobernanza, API si cambia y compatibilidad.
- **Cierre:** cuatro destinos funcionales sin retirar legados prematuramente.
- **Merge a `main`:** sí.

### Fase 7D — Navegación, Home, footer y legal

- **Objetivo:** convertir las áreas funcionales en una experiencia pública
  coherente.
- **Alcance:** menús Aprende/Club, cuenta separada, Home veraz, footer global y
  accesos legales reales con contenido aprobado.
- **Fuera:** SEO completo, redirects y noticias sin fuente.
- **Backend:** ninguno salvo contrato CMS imprescindible.
- **Frontend:** disclosure desktop/móvil, activo, foco, Escape, responsive,
  Home y footer.
- **Contenido:** copy y enlaces ya aprobados en 7B/7C.
- **Tests:** RTL, teclado, foco, zoom, matriz responsive y Playwright.
- **Documentación:** contrato de navegación y accesibilidad.
- **Cierre:** cero placeholders/enlaces vacíos y mismo árbol en ambos modos.
- **Merge a `main`:** sí.

### Fase 7E — Preparación operativa de Escuela

- **Objetivo:** publicar Escuela con datos y privacidad reales.
- **Alcance:** carga manual privada/cerrada por Blade, validación del agregado,
  contenido, privacidad, conservación, capacidad de gestión, solicitud de
  prueba y apertura controlada.
- **Fuera:** nuevos modelos/endpoints, metodología inventada y contenido
  público de centros/actividades.
- **Backend/frontend:** cambios mínimos sólo si la revisión humana detecta un
  incumplimiento contractual.
- **Contenido/datos:** carga operativa separada, primero privada y cerrada.
- **Tests:** regresión School y smoke con datos controlados.
- **Documentación:** runbook de alta, apertura, cierre y conservación.
- **Cierre:** responsable acepta contenido, privacidad y operación; se abre sólo
  cuando todo está vigente.
- **Merge a `main`:** sí para código/documentos; no se versionan datos reales ni
  secretos.

### Fase 7F — Preparación de despliegue

- **Objetivo:** desplegar de forma reproducible en Railway y Vercel.
- **Alcance:** contratos de build/start, MariaDB, variables, CORS, correo,
  sesiones, logs, salud, backups, migraciones, administrador inicial, SPA y
  Knowledge.
- **Fuera:** cambiar de motor o desplegar sin staging.
- **Backend:** imagen/comando productivo y predeploy seguro.
- **Frontend:** build reproducible, variable API y rewrite SPA.
- **Contenido:** procedimiento de promoción; nunca seeders demo.
- **Tests:** staging, restauración, rollback y smoke.
- **Documentación:** runbook y matriz de variables/secretos.
- **Cierre:** checklist completo en un entorno equivalente a producción.
- **Merge a `main`:** sí.

### Fase 7G — Validación y cierre del MVP

- **Objetivo:** demostrar todos los criterios observables.
- **Alcance:** suites completas, recorridos críticos deportivos,
  institucionales, School, usuario y administración; QA responsive/accesible,
  prioridad multibrowser, aceptación editorial/privacidad y smoke productivo.
- **Fuera:** P1/P2 salvo defectos bloqueantes.
- **Backend/frontend/contenido:** sólo correcciones surgidas de la validación.
- **Tests:** Laravel sobre MariaDB, Vitest/RTL, lint/build/Knowledge, Playwright,
  smoke y checks operativos.
- **Documentación:** resultados, limitaciones aceptadas y release.
- **Cierre:** cero P0, decisiones firmadas y rollback disponible.
- **Merge a `main`:** sí; tag/release únicamente después de la aceptación.

### Después del MVP

- **Fase 8A:** edición de perfil y claridad de equipos/participación.
- **Fase 8B:** reprogramación React y endurecimiento del contrato.
- **Fase 8C:** `academy`, redirects permanentes, aliases aún no contratados,
  SEO, multimedia y limpieza heredada.

Cada bloque debe ser pequeño. Las decisiones humanas y la carga de contenido no
se deben ocultar dentro de una fase de código.

## 21. Dependencias y decisiones humanas

Antes de implementar o cerrar 7C–7F se necesita:

- aceptar el contrato 7B de Aprende/Club, sus URLs y compatibilidad;
- nombrar responsables de contenido, privacidad y operación;
- proporcionar copy, contacto, documentos, datos School y enlaces oficiales;
- revisar imágenes, licencias y consentimientos;
- decidir el uso público de nombres de participantes;
- elegir dominio, proveedor de correo y estrategia de secretos;
- decidir MariaDB gestionada externamente o servicio MariaDB propio con volumen;
- definir backup, restauración, retención, monitoring y rollback;
- confirmar si Contacto sin formulario cubre la operativa MVP; resuelto después
  a favor de un formulario local desactivado mediante ADR-034.

## 22. Auditoría de despliegue

### 22.1. Estado encontrado

- No hay `railway.json`, `railway.toml`, `vercel.json`, `Procfile` ni contrato
  equivalente de producción.
- El Dockerfile backend actual es base de desarrollo PHP-FPM: no copia la
  aplicación, no instala el artefacto final ni define arranque autónomo.
- Laravel ofrece `/up`, útil como health endpoint.
- `.env.example` conserva defaults locales (`APP_DEBUG=true`, mail `log`,
  sesiones/cache de fichero y URLs locales).
- El frontend admite `VITE_API_BASE_URL`, pero Vercel necesita root/build/output
  y fallback SPA explícitos.
- `knowledge:check` necesita acceso al directorio raíz `knowledge/`. Un root de
  proyecto limitado a `frontend/` no puede asumir acceso fuera de él; el build o
  CI debe conservar esa validación.
- No se acredita una política CORS productiva para dominios separados.
- El proyecto sólo soporta MariaDB. La oferta MySQL de Railway no debe
  sustituirla silenciosamente.
- No hay almacenamiento persistente de uploads; hoy no se necesita mientras no
  se anuncie soporte multimedia.

### 22.2. Contrato recomendado

**Railway/backend**

- servicio construido desde `backend/` o imagen productiva explícita;
- instalación reproducible de Composer, cachés compatibles y proceso web;
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` y secretos protegidos;
- `FRONTEND_URL` real, CORS limitado y proxy/TLS verificados;
- sesiones persistentes, preferentemente base de datos, no filesystem efímero;
- logs a stderr, health `/up` y monitorización externa;
- proveedor de correo real y entrega de reset probada;
- migraciones `--force` en paso controlado tras backup;
- bootstrap de administrador seguro, sin `DatabaseSeeder`.

**MariaDB**

- MariaDB 11.4 compatible, como servicio propio con volumen en
  `/var/lib/mysql` o proveedor externo aprobado;
- credenciales separadas, acceso privado y backups programados;
- restauración verificada y criterio de rollback antes de migrar.

**Vercel/frontend**

- build `npm ci` + `knowledge:check` + `npm run build` desde un root que pueda
  leer `knowledge/`, o gate de CI equivalente antes del despliegue;
- output `frontend/dist` según el root elegido;
- `VITE_API_BASE_URL=https://…/api/v1` durante el build;
- rewrite de rutas no-fichero a `index.html` para deep links de BrowserRouter;
- preview y producción con variables separadas.

Referencias operativas consultadas:

- Railway, Laravel: <https://docs.railway.com/guides/laravel>
- Railway, healthchecks: <https://docs.railway.com/deployments/healthchecks>
- Railway, MySQL: <https://docs.railway.com/databases/mysql>
- Railway, volúmenes y backups:
  <https://docs.railway.com/volumes/reference> y
  <https://docs.railway.com/volumes/backups>
- Vercel, Vite y configuración de build:
  <https://vercel.com/docs/frameworks/frontend/vite> y
  <https://vercel.com/docs/builds/configure-a-build>
- Vercel, rewrite SPA:
  <https://examples.vercel.com/docs/project-configuration/vercel-ts>

El healthcheck de Railway sirve como gate de despliegue, pero no reemplaza la
monitorización continua. Los volúmenes tampoco sustituyen una estrategia de
backup y restauración.

## 23. Testing

La cobertura existente auditada incluye Feature sobre MariaDB, Vitest/RTL y 21
escenarios Playwright Chromium. La última instantánea validada tras 6C.1 es:

- backend completo: 356 tests y 2.708 aserciones;
- frontend: 51 archivos y 312 tests;
- E2E: 21 escenarios;
- Knowledge, lint y build correctos;
- ningún recurso Docker temporal residual.

Fase 7A no ha vuelto a ejecutar esas suites y no atribuye cobertura a Club,
Contacto, footer global, navegación agrupada, despliegue o flujos aún ausentes.

Gates mínimos para 7G:

1. Feature de publicación/privacidad para cualquier ampliación CMS.
2. RTL de submenús, rutas Club, Home, footer, estados remotos y formularios.
3. E2E de navegación desktop/móvil, inscripción deportiva, Club/Contacto/legal,
   Escuela configurada, auth/reset y 404 tras recarga.
4. Laravel completo sobre MariaDB aislada.
5. Knowledge, frontend tests, lint y build.
6. Playwright mediante el runner seguro y sin tocar desarrollo.
7. Smoke de staging/producción, backup/restore y rollback.
8. Revisión humana de copy, imágenes, privacidad y operación.

## 24. Checklist de publicación MVP

### Producto y contenido

- [ ] Cero P0 abiertos.
- [ ] Navegación y URLs aprobadas.
- [ ] Home, Club, Contacto, Federarse, Documentos y footer con contenido real.
- [ ] `/nosotros` sin placeholders ni duplicidad no controlada.
- [ ] Prensa/Federaciones sólo enlazados si son reales.
- [ ] `academy` identificado como legado y sin migración accidental.
- [ ] Privacidad, aviso legal, copyright y canal de derechos aprobados.
- [ ] Identidad pública de participantes aceptada.

### Escuela

- [ ] Programa, niveles, horarios, ubicación y contacto verificados.
- [ ] Programa revisado primero privado/cerrado.
- [ ] Responsable y procedimiento de solicitudes definidos.
- [ ] Información de privacidad y conservación aprobada.
- [ ] Apertura realizada sólo tras aceptación operativa.

### Backend y datos

- [ ] MariaDB productiva, credenciales, volumen y acceso privado.
- [ ] Backup automático y restauración probada.
- [ ] Migraciones ensayadas y rollback documentado.
- [ ] Administrador inicial creado por procedimiento seguro.
- [ ] No se ejecutan seeders de demo.
- [ ] CORS, proxy, TLS, sesiones, logs, rate limits y `/up` verificados.
- [ ] Correo y reset de contraseña entregan de extremo a extremo.

### Frontend y hosting

- [ ] Build reproducible y `knowledge:check` como gate.
- [ ] `VITE_API_BASE_URL` correcto por entorno.
- [ ] Rewrite SPA y deep links verificados.
- [ ] 404, loading, error, vacío y retry correctos.
- [ ] Desktop, móvil, teclado, Escape, foco y zoom aprobados.

### Calidad y operación

- [ ] Suites completas verdes con runners aislados.
- [ ] Recorridos críticos Playwright y smoke productivo verdes.
- [ ] Monitoring, alertas, logs y responsable de incidencias definidos.
- [ ] Rollback de aplicación y base de datos ensayado.
- [ ] Documentación y limitaciones actualizadas.
- [ ] Revisión humana y autorización de release registradas.

## 25. Criterios de cierre de Fase 7A

Fase 7A queda documentalmente cerrada cuando:

- backend, API, frontend, CMS y autogestión están inventariados;
- administración y funciones públicas están separadas;
- Competición y Escuela se han auditado extremo a extremo;
- navegación, Home, footer, contacto y Club tienen recomendación;
- MVP, prioridades, fases, dependencias y decisiones humanas son observables;
- despliegue dispone de checklist;
- las deudas y el incidente Docker histórico están registrados;
- no se ha modificado código, Knowledge, configuración, datos o dependencias;
- `git diff --check` no produce salida.

El aislamiento Docker está resuelto y la pérdida del volumen local permanece
como incidente histórico documentado en `13-docker-environment-isolation.md`.
Fase 7 sigue abierta y el MVP completo no debe declararse hasta cerrar 7G.

## 26. Seguimiento de Fase 7B

Fase 7B reaudita en lectura la navegación, Home, Nosotros, CMS, Escuela y la
identidad expuesta por los Resources públicos. El resultado completo se
encuentra en `15-mvp-editorial-and-navigation-contract.md` y se formaliza en
ADR-033.

Quedan cerrados documentalmente:

- Inicio y Competición como enlaces directos;
- Aprende como disclosure de Aprende a jugar, Manual y Escuela, sin fusionar
  rutas o fuentes;
- Club como disclosure de Quiénes somos, Contacto, Federarse y Documentos, sin
  landing `/club`;
- Cuenta separada;
- rutas canónicas `/club/...`, aliases temporales conservadores y redirects
  posteriores a paridad;
- CMS institucional y Documentos mediante bloques de enlaces; la decisión
  posterior ADR-034 prepara un formulario local desactivado para Contacto;
- Prensa y Federaciones fuera del Navbar y condicionales en footer;
- footer obligatorio, plantillas editoriales, matriz legal, checklist School y
  plan refinado 7C–7G.

Continúan abiertos como gates humanos:

- contenido real de Quiénes somos, Contacto, Federarse, Documentos y Home;
- procedencia de imágenes, identidad oficial y copyright;
- privacidad, aviso legal y aplicabilidad de cookies;
- política de identidad pública y tratamiento de menores en Competición;
- datos, contenido, conservación y capacidad operativa de Escuela;
- infraestructura y aceptación de despliegue.

7B no añade menús, rutas, CMS, aliases, redirects, contenido, datos o
configuración. 7C–7G permanecen pendientes, Fase 7 abierta y el MVP incompleto.

## 27. Seguimiento de Fase 7C

7C.1 preparó el dominio de Contacto y 7C.2 implementa las cuatro fachadas Club
con slugs CMS cerrados, estados completos, metadatos y formulario condicionado.
La carga local fue manual y los fixtures ficticios sólo existen bajo guard E2E.
`/nosotros`, `/contenidos/:slug`, `academy`, Prensa y Federaciones se conservan;
no hay `/club`, aliases, redirects o cambio de Navbar.

Esto resuelve la ausencia técnica de las cuatro rutas inventariada en 7A, pero
no acredita datos productivos, privacidad, correo, operación, publicación por
entorno, Home/footer/legal, despliegue o aceptación. Fase 7, 7D–7G y el MVP
siguen abiertos.

## 28. Seguimiento de Fase 7D.1

7D.1 resuelve la deuda estructural de descubrimiento inventariada por 7A:
Navbar usa una configuración única con disclosures Aprende/Club y Cuenta
separada; Home ofrece sólo recorridos reales; y Footer es global con las cuatro
rutas Club y redes confirmadas. La aplicación conserva rutas deportivas,
Knowledge, Escuela, CMS, `/nosotros`, `/contenidos` y carga diferida.

Este cierre no acredita contenido productivo ni resuelve privacidad, legal,
correo, operación de Contacto, datos School, identidad deportiva o despliegue.
No crea aliases, redirects, canonical o SEO completo. 7D.2, 7E–7G, Fase 7 y el
MVP permanecen abiertos; la definición observable de 7A no se reduce.

## 29. Seguimiento de Fase 7D.3

Tras cerrar 7D.2, 7D.3 inventaría y clasifica el router completo, centraliza
metadata/canonical, mantiene aliases sin redirect, genera robots y sitemap de
forma fail-closed y añade foco y anuncio SPA. La indexación real continúa
desactivada hasta confirmar dominio HTTPS en 7F. Los 61 escenarios E2E pasan y
cierran 7D.3 y 7D. Permanecen abiertos 7E–7G, imágenes, operación productiva,
aceptación humana, Fase 7 y MVP; la definición observable de 7A no se reduce.

## 30. Seguimiento de Fase 7E

7E resuelve la preparación técnica inventariada por 7A sin afirmar que existan
datos productivos. Laravel centraliza la disponibilidad de inscripción,
permanece cerrado por defecto mediante `SCHOOL_ENROLLMENT_ENABLED=false` y
rechaza la apertura administrativa si faltan contenido, ubicación, nivel,
horario, contacto operativo privado o aviso vigente. React presenta el estado
resultante y ya no recibe teléfono o correo del programa.

La primera capa `NOTICE-SCHOOL-ENROLLMENT` y la automatización manual de
vencimiento, holds y anonimización aplican los plazos ya publicados. Los datos,
horarios, niveles, responsable y canales reales, así como la revisión humana,
continúan como gates previos a activar un entorno. Correo, scheduler, backups,
restore, staging y rollback pertenecen a 7F. Por tanto 7E puede cerrarse
técnicamente con producción cerrada; 7F, 7G, Fase 7 y el MVP siguen abiertos.


---

**Nota de seguimiento posterior (Fase 7F.2):** Tras la aceptación de staging, ciertas decisiones (como el modelo de navegación en Competición y el aplazamiento de noticias y multimedia persistente) han sido promovidas o refinadas en la Fase 7F.2. Ver `docs/28-preproduction-product-refinement.md` y `ADR-042`.
