# Auditoría y contrato de Escuela de Galotxas

## 1. Propósito

Este documento cierra la Fase 6A: audita las capacidades reales relacionadas con la Escuela de Galotxas y define el contrato que deberá guiar su futura implementación. Separa el conocimiento pedagógico estable, la información operativa administrable y cualquier proceso transaccional.

La Fase 6A es exclusivamente documental. No crea modelos, migraciones, rutas, endpoints, pantallas Blade, componentes React, colecciones de `knowledge/`, formularios ni contenido público.

## 2. Estado actual

La Escuela de Galotxas no está implementada como vertical funcional:

- no existen entidades `School*` o equivalentes en Laravel;
- no existe administración Blade específica;
- no existe endpoint público o administrativo de Escuela;
- no existe `/escuela` en React ni en el Navbar;
- `knowledge/` no contiene una colección de Escuela;
- el CMS genérico incluye una página sembrada con slug `academy`, pero no acredita contenido real de un entorno ni cubre datos estructurados;
- Home y la página React estática `/nosotros` contienen menciones hardcodeadas, no administrables y no verificadas como contrato editorial;
- las inscripciones existentes son solicitudes deportivas a campeonatos y no representan alumnado o solicitudes escolares.

La arquitectura objetivo sigue siendo híbrida:

```text
knowledge/ ── compilación validada ──► React
   contenido pedagógico estable

Laravel ── API pública ──► React
   programa, grupos, horarios, ubicaciones y estado de inscripción

Blade ── Laravel/MariaDB
   administración de la información operativa
```

## 3. Auditoría backend

### Inventario relacionado

| Área | Implementación localizada | Capacidad real | Límite para Escuela |
|---|---|---|---|
| CMS | `CmsPage`, `CmsBlock`, controladores, Form Requests, Resources, vistas y rutas CMS | Páginas y bloques genéricos con borrador, publicación inmediata o programada | No estructura grupos, horarios, ubicaciones, periodos ni solicitudes |
| Usuarios | `User`, autenticación Sanctum y middleware de usuario activo | Cuenta, rol `user`/`admin`, sesión y zona privada | No representa alumno, tutor o responsable escolar |
| Jugadores | `Player` y perfil privado | Identidad deportiva, licencia y atributos de jugador | Recoge datos excesivos y presupone cuenta; no debe reutilizarse como perfil de alumno |
| Inscripción deportiva | `ChampionshipRegistrationRequest`, `CategoryRegistration` y Services/controladores asociados | Solicitud de participación en campeonato y asignación posterior | Finalidad, sujeto, datos y riesgos distintos a una solicitud escolar |
| Ubicaciones deportivas | `Venue` y CRUD Blade | Pista usada por partidos | No debe suponerse que una sede escolar es una pista de competición |
| Archivos | `config/filesystems.php` y campos de ruta aislados | Configuración técnica de discos | No existe flujo administrado de subida, autorización, borrado o persistencia de medios |
| Seguridad | `auth`, `EnsureUserIsActive`, `IsAdmin` y limitadores de auth/resultados | Separación básica de público, cuenta y admin activo | No hay Policies ni limitador, consentimiento o conservación específicos de Escuela |
| Notificaciones | Recuperación de contraseña | Notificación propia del framework | No existe notificación escolar |

Las migraciones, modelos, factories, seeders, Requests, controladores, Resources, rutas y tests no contienen un dominio `School` o `Academy`. Tampoco existen modelos de contacto, aviso, programa formativo, grupo escolar, horario escolar, solicitud de información o matrícula.

Trazas principales inspeccionadas:

- `backend/database/migrations/2026_06_25_000000_create_cms_pages_table.php` y `2026_06_25_000001_create_cms_blocks_table.php`;
- migraciones de `users`, `players`, `venues`, `category_registrations` y `championship_registration_requests`;
- `backend/app/Models/{CmsPage,CmsBlock,User,Player,Venue,CategoryRegistration,ChampionshipRegistrationRequest}.php`;
- controladores y Form Requests CMS, perfiles, registros deportivos y administración;
- `backend/app/Services/ChampionshipRegistrationRequestService.php`;
- Resources públicos CMS, de jugador y de solicitudes deportivas;
- `backend/routes/api.php`, `backend/routes/web.php`, middleware y limitadores de `AppServiceProvider`;
- factories, `DatabaseSeeder`, `InstitutionalCmsPageSeeder`, `E2ESmokeSeeder` y sus tests Feature.

### Patrones reutilizables

Pueden reutilizarse patrones técnicos, no entidades ni semántica:

- Form Requests para validar whitelists;
- Services cuando una transición o regla se use en más de un contexto;
- controladores web y vistas Blade protegidos por administrador activo;
- Resources públicos específicos y consultas que filtren antes de serializar;
- factories y tests Feature sobre MariaDB;
- respuestas y feedback administrativo coherentes;
- limitación de frecuencia como mecanismo, con una clave y umbral propios si se aprueba un formulario.

No deben copiarse automáticamente los estados de competición, la publicación editorial del CMS, `Player`, `Venue` o `ChampionshipRegistrationRequest`.

## 4. Auditoría frontend

`frontend/src/App.jsx` no registra `/escuela`; el wildcard muestra la 404 y un test exige expresamente que `/escuela` y `/club` no se publiquen como placeholders. `publicNavigation.js` y `Navbar.jsx` exponen sólo Inicio, Competición y Aprende a jugar, con cuenta separada.

El estado actual relevante es:

| Elemento | Estado | Reutilización futura |
|---|---|---|
| `PublicLanding` y componentes asociados | Contenedor, cabecera, secciones, acciones y tarjetas accesibles | Sí, como estructura sin contenido editorial embebido |
| `PageMetadata` | Título, descripción y `robots` reversibles | Sí |
| Navbar y contrato activo | Configuración única, desktop/móvil, Escape, cierre al navegar y `aria-current` | Sí; se ampliará sólo cuando `/escuela` sea funcional |
| Servicio CMS | Lista y detalle bajo `/cms/pages` | Sólo para contenido CMS genérico que siga siendo canónico |
| Estados de Competición/CMS | Patrones de carga, error, vacío y contenido | Sí como referencia; la composición parcial de Escuela será específica |
| Home | Tarjeta visual “Academy” sin enlace | No como fuente ni como prueba de funcionalidad |
| `/nosotros` | Texto e imagen hardcodeados sobre la Escuela | Material pendiente de revisión editorial, no fuente reutilizable |
| Aprende a jugar | Consumidor de `public-knowledge.json` y Manual | Sí para enlaces al conocimiento ya publicado; no como contenedor de Escuela |
| Formularios deportivos | Inscripción a campeonato ligada a cuenta y jugador | No para solicitudes escolares |

No hay servicio, hook, repositorio, página, formulario, estado remoto o E2E específico de Escuela.

Las trazas principales fueron `frontend/src/App.jsx`, `navigation/publicNavigation.js`, `components/Navbar/`, `pages/Home/Home.jsx`, `pages/Nosotros/`, `pages/Learn/`, `pages/CmsPage*`, `api/cms.js`, `components/CmsBlocks/`, `components/PublicLanding/`, sus tests Vitest y `frontend/e2e/mvp-smoke.spec.js`. El smoke E2E cubre el CMS genérico, no Escuela.

## 5. Auditoría de `knowledge/`

Existen cuatro colecciones públicas: Reglamento, Conceptos de elementos, Conceptos de personas y Conceptos de juego. El corpus actual contiene 40 documentos `Vigente` y alimenta Aprende a jugar y el Manual.

No existen:

- `knowledge/escuela/`;
- programa pedagógico;
- metodología docente;
- itinerarios de iniciación;
- ejercicios o sesiones;
- recursos para familias, centros o monitores;
- contenido institucional de la Escuela.

Los documentos `Golpe` y `Bolea` mencionan que técnicas y aprendizaje se desarrollarán en una futura “Academy”. Estas frases expresan deuda editorial y no constituyen contenido pedagógico publicable.

La Escuela puede enlazar al Manual actual para reglas, vocabulario y elementos del juego. Sólo necesitará una colección canónica nueva cuando exista material pedagógico real, revisado y con responsable editorial. Esa colección requerirá ampliar conscientemente el contrato y el compilador; no se crea vacía ni se sustituye con copy React.

## 6. CMS heredado y `academy`

`InstitutionalCmsPageSeeder` declara una página:

- slug: `academy`;
- título: `Academy`;
- estado inicial: `published`;
- `published_at`: momento de siembra;
- descripción: información pública sobre aprendizaje, escuela y actividades formativas;
- bloques iniciales para una página nueva: heading H2 y texto genérico.

El seeder usa `firstOrCreate`: garantiza el mínimo anterior sólo al crear. No permite conocer ni sobrescribe el contenido, estado, fechas o bloques de una base real preexistente. No se inspecciona la base de desarrollo en esta auditoría.

`InstitutionalCmsPageSeeder` es explícito y no forma parte de `DatabaseSeeder`; `E2ESmokeSeeder` crea otra página CMS de prueba y no garantiza `academy`. Por tanto, ni siquiera la existencia del registro puede inferirse para todos los entornos sólo desde el código.

La página está disponible en el CRUD Blade genérico y, si cumple el criterio público, mediante `GET /api/v1/cms/pages/academy` y `/contenidos/academy`. React no enlaza actualmente esa URL desde Home o Navbar; las referencias visuales de Home no son enlaces.

| Aspecto | Estado actual | Problema | Decisión recomendada |
|---|---|---|---|
| Modelo | `CmsPage` + `CmsBlock` genéricos | Sin estructura escolar ni pertenencia de área | No convertirlos en el dominio operativo |
| Datos sembrados | Copy mínimo, no destructivo | No demuestra datos reales ni vigencia editorial | Inventariar cada entorno antes de migrar |
| Nombre | `Academy` | No coincide con la etiqueta pública cerrada | Usar “Escuela de Galotxas” en la experiencia futura |
| Estado y fecha | Publicado al crear; variable si ya existía | Puede ser públicamente visible sin satisfacer el contrato de Escuela | Conservar de momento; revisar en la migración |
| API | Endpoint CMS por slug | Expone sólo bloques genéricos | No usarlo para grupos, horarios o solicitudes |
| URL React | `/contenidos/academy` | Técnica, legada y no canónica | Mantener compatibilidad hasta migrar contenido y consumidores |
| Administración | CRUD CMS genérico | No ofrece validaciones ni relaciones operativas | Crear vertical Blade específica en 6B |
| Tests | Seeder, CRUD, bloques y lectura pública | Validan CMS, no Escuela | Añadir cobertura propia en 6B/6C |

El CMS genérico puede seguir alojando una página o aviso simple sin estructura cuando esa pieza tenga una fuente editorial única. No debe forzarse para horarios, grupos, plazas, periodos o solicitudes.

## 7. Necesidades

La clasificación siguiente no convierte todos los elementos en requisitos.

| Necesidad | Prioridad | Existe actualmente | Fuente adecuada | Persistencia | Fase prevista |
|---|---|---|---|---|---|
| Presentación general | MVP, con contenido real | Sólo menciones no gobernadas | `knowledge/` si es estable | Git + artefacto público | 6C, condicionada |
| Objetivos | Recomendable | No como pieza canónica | `knowledge/` | Git + artefacto público | 6C o posterior |
| Metodología | Recomendable | No; sólo promesas editoriales | `knowledge/` | Git + artefacto público | Posterior o 6C si existe contenido aprobado |
| Valores | Pendiente de decisión humana | Texto institucional disperso | `knowledge/` o CMS según estabilidad | Por decidir una única vez | 6C o posterior |
| Relación con el Manual | MVP | Manual funcional | `knowledge/` existente | Artefacto generado | 6C |
| Destinatarios | MVP si están confirmados | Afirmaciones hardcodeadas no validadas | Laravel para oferta actual; `knowledge/` para marco estable | MariaDB y/o Git, sin duplicar | 6B/6C |
| Niveles o grupos | MVP | No | Laravel | MariaDB | 6B |
| Horarios | MVP para grupos publicados | No | Laravel | MariaDB | 6B |
| Ubicaciones | MVP para horarios presenciales | No como sede escolar | Laravel | MariaDB | 6B |
| Calendario de curso | Recomendable | No | Laravel | MariaDB | Iteración posterior, salvo necesidad real en 6B |
| Plazas/capacidad | Pendiente humana | No | Laravel | MariaDB | 6B sólo si se gestiona realmente |
| Estado de inscripción | MVP | Sólo campeonato | Laravel | MariaDB | 6B |
| Contacto | MVP condicionado a canal organizativo aprobado | No | Laravel o CMS si es una página genérica | MariaDB | 6B |
| Responsables | Pendiente humana | No gobernado | Laravel, sólo si debe publicarse | MariaDB | Posterior o 6B tras minimización |
| Avisos | Recomendable | CMS genérico disponible | CMS para avisos simples; entidad propia sólo si se necesita estructura | MariaDB | Posterior |
| Preguntas frecuentes | Recomendable | No | `knowledge/` si son estables; CMS si cambian | Git o MariaDB | Posterior |
| Solicitud de información | Siguiente iteración | No | Laravel transaccional | MariaDB | Bloque independiente tras 6B |
| Solicitud de inscripción | Siguiente iteración | Sólo un flujo deportivo no equivalente | Laravel transaccional | MariaDB | Bloque independiente tras 6B |
| Imágenes | Opcional, no bloquea MVP | Assets y URLs CMS sin gestión integral | Medio administrado con metadatos y permisos | Almacenamiento persistente desacoplado | Posterior |
| Vídeos | Futuro | No | Proveedor/medio aprobado | Referencia persistida, no Git para subidas | Futuro |
| Documentos descargables | Futuro | Bloque CMS por URL | CMS o dominio según finalidad | Almacenamiento persistente | Futuro |

Quedan descartados para esta vertical: plataforma académica completa, expedientes, calificaciones, asistencia, pagos, estadísticas, mensajería interna y perfiles públicos de alumnos.

## 8. Fuentes de verdad

| Tipo de información | Fuente de verdad | Editor | Consumidor | Motivo |
|---|---|---|---|---|
| Reglas y vocabulario ya publicados | `knowledge/` actual | Revisión editorial por Git | Compilador y React | Ya son canónicos y versionables |
| Metodología, iniciación o recursos estables futuros | Nueva colección de `knowledge/`, sólo con contenido real | Responsable editorial por Git | Compilador y React | No cambian con el curso y requieren trazabilidad |
| Programa/edición actual | Laravel | Administrador activo desde Blade | API pública y React | Es operativo y cambia sin despliegue |
| Grupos, horarios y ubicaciones | Laravel | Administrador activo desde Blade | API pública y React | Requieren estructura, validación y visibilidad |
| Estado o ventana de inscripción | Laravel | Administrador activo desde Blade | API pública y React | Es temporal y no puede inferirse en JSX |
| Solicitudes futuras | Laravel transaccional independiente | Persona solicitante y administrador autorizado | Respuesta opaca y panel Blade | Contiene datos no públicos y requiere ciclo de vida |
| Aviso simple no estructurado | CMS genérico, si se aprueba y queda clasificado | Administrador activo | API CMS y React | Reutilización válida sólo si no necesita relaciones escolares |
| Labels, mensajes de estado y navegación | React | Desarrollo | Navegador | Son copy de interfaz, no contenido editorial |

No se mantendrá una misma pieza editable en `knowledge/`, CMS, tablas escolares, seeders y JSX. React compone resultados ya autorizados y no decide reglas de publicación.

## 9. Contenido estable

El MVP puede enlazar al Manual actual sin duplicar sus explicaciones. La presentación, objetivos, metodología, ejercicios o materiales propios de la Escuela sólo se publicarán desde `knowledge/` cuando:

- exista contenido real;
- se aprueben responsable, colección, metadatos, orden y rutas;
- el compilador y la proyección pública soporten esa colección;
- la revisión humana determine que no contiene información personal u operativa;
- se actualicen fuente y artefactos de forma coordinada.

La ausencia de una colección nueva no debe resolverse con placeholders o copy editorial en React. La landing podrá mostrar los datos operativos reales y un enlace contextual al Manual.

## 10. Contenido operativo

Laravel será la fuente del curso vigente y de lo que un administrador necesite cambiar sin desplegar:

- programa o edición;
- grupos y destinatarios actuales;
- horarios con su contexto;
- ubicaciones;
- periodo y estado de inscripción;
- canal organizativo de contacto, si se aprueba;
- avisos estructurados futuros, sólo si el CMS genérico deja de ser suficiente.

La información pública se obtendrá mediante consultas específicas; las filas privadas, incompletas o fuera de vigencia no se serializarán. Los seeders podrán preparar datos controlados de desarrollo/E2E, pero no serán fuente editorial viva.

## 11. Funcionalidad transaccional

El MVP recomendado es informativo-operativo y no incluye todavía un formulario de información o inscripción. El proceso real, la necesidad de cuenta, los participantes, los datos mínimos, el tratamiento de menores, el consentimiento, la conservación y el canal de respuesta necesitan decisión humana previa.

| Aspecto | Campeonato | Escuela |
|---|---|---|
| Finalidad | Solicitar participación deportiva en un campeonato | Pedir información o plaza en una actividad formativa |
| Solicitante | Usuario activo con perfil `Player` | Por determinar: persona adulta, tutor o participante |
| Datos requeridos | Usuario, jugador, campeonato, categoría sugerida y comentario | No definidos; deberán minimizarse tras confirmar el proceso |
| Estado | Enum deportivo de solicitud y pago | Ciclo independiente por definir; no reutilizar enum |
| Aprobación | Administración deportiva y posible asignación a categoría | Responsable escolar y reglas operativas por confirmar |
| Temporalidad | Ventana del campeonato | Curso, grupo o periodo escolar por definir |
| Relación con usuario | Obligatoria | Pendiente; no debe imponerse una cuenta sin necesidad |
| Menores | No modelados como flujo específico | Riesgo central; requiere responsable, consentimiento y exposición mínima |
| Consentimiento | No forma parte del contrato deportivo auditado | Debe definirse antes de recoger datos |
| Administración | Listado y cambios de estado deportivos | Vista y permisos propios, sin mezclar con campeonatos |

Se pueden reutilizar infraestructura y patrones técnicos de Requests, Services, rate limiting, middleware, Resources y tests. Debe existir un modelo escolar independiente si se aprueba el formulario. No se reutilizarán `ChampionshipRegistrationRequest`, `CategoryRegistration` ni `Player`.

Un futuro endpoint de escritura deberá devolver una confirmación opaca, no permitir enumerar solicitudes, aplicar limitación propia y evitar adjuntos en su primera versión.

## 12. Alcance MVP

| Capacidad | Decisión para MVP |
|---|---|
| Landing pública | Sí, en 6C y sólo tras disponer de datos reales o contenido estable útil |
| Información estable | Sí mediante enlaces al Manual; colección propia sólo si hay material aprobado |
| Grupos activos | Sí |
| Horarios | Sí para cada grupo público, una vez confirmada su estructura temporal |
| Ubicaciones | Sí cuando un horario presencial las requiera |
| Estado de inscripción | Sí, como dato operativo; no como formulario |
| Contacto | Condicionado a un canal organizativo y publicable confirmado; el bloque se omite si falta |
| Solicitud de información o inscripción | No en el MVP; siguiente iteración independiente |

No son requisitos de salida: avisos, noticias, galería, vídeos, descargas, plazas restantes, perfiles de responsables o alumnado, pagos ni área privada escolar.

## 13. Modelo de dominio propuesto

Se recomienda un vertical Laravel específico y pequeño, no una adaptación de `CmsPage` ni de Competición. El núcleo candidato es:

```text
SchoolProgram
├── SchoolGroup
│   └── SchoolSchedule ──► SchoolLocation
└── SchoolEnrollmentPeriod
```

Los nombres y campos son provisionales hasta cerrar las preguntas humanas. Antes de crear migraciones, 6B debe confirmar qué representa un programa, si el horario es semanal o por sesiones y si la inscripción se abre para el programa o para cada grupo.

`SchoolEnrollmentRequest`, `SchoolNotice` y un posible `SchoolContact` no forman parte del núcleo MVP. Sólo se añadirán en bloques posteriores si su necesidad real supera una referencia de contacto o una página CMS simple.

| Alternativa | Evaluación |
|---|---|
| Sólo CMS genérico | Descartada: no expresa relaciones, completitud o visibilidad efectiva de la oferta |
| Sólo `knowledge/` | Descartada: fechas y oferta operativa exigirían despliegue y no admitirían transacciones |
| CMS más tablas escolares para la misma pieza | Descartada: crearía dos autoridades y sincronización |
| Dominio Laravel específico + `knowledge/` estable | Recomendada: separa ritmo editorial, estructura operativa y privacidad |

## 14. Entidades

| Entidad | Responsabilidad | Campos mínimos propuestos | Relaciones | Administrable | Pública |
|---|---|---|---|---|---|
| `SchoolProgram` | Representar una edición u oferta operativa de Escuela | nombre, inicio/fin si existen, estado operativo aprobado, `is_public` | tiene grupos y periodos | Sí | Resumen filtrado |
| `SchoolGroup` | Agrupar una oferta real para un público concreto | programa, nombre, descripción pública de destinatarios, estado operativo, `is_public`, orden; capacidad sólo si se gestiona | pertenece a programa; tiene horarios | Sí | Sólo rama efectiva |
| `SchoolSchedule` | Estructurar cuándo se reúne un grupo | grupo, ubicación, definición temporal confirmada, vigencia si aplica, `is_public`, orden | pertenece a grupo y ubicación | Sí | Sólo con contexto completo |
| `SchoolLocation` | Identificar una sede escolar publicable | nombre, dirección o indicación pública mínima, `is_public` | tiene horarios | Sí | Sólo datos públicos |
| `SchoolEnrollmentPeriod` | Expresar apertura/cierre de inscripción sin recibir solicitudes | programa o grupo —a confirmar—, inicio/fin, estado operativo, `is_public` | pertenece al ámbito aprobado | Sí | Estado derivado y fechas necesarias |

No se congela la forma exacta de la “definición temporal”: si la operación es semanal podrá modelarse por día y horas; si usa fechas irregulares se necesitarán sesiones. Elegir una forma antes de obtener esa respuesta produciría campos especulativos.

Entidades evaluadas y aplazadas:

- `SchoolEnrollmentRequest`: siguiente iteración, modelo independiente si se aprueba el formulario;
- `SchoolNotice`: usar CMS simple o aplazar; crear entidad sólo si requiere ámbito, vigencia u orden propios;
- `SchoolContact`: evitar entidad para un único canal; valorar campos mínimos en programa o configuración tras decidir responsable y exposición;
- perfil de alumno: descartado;
- reutilización de `Venue`: condicionada a demostrar identidad semántica entre sede escolar y pista deportiva; por defecto son conceptos separados.

## 15. Relaciones

- Un programa tiene cero o más grupos; un grupo pertenece a un programa.
- Un grupo tiene cero o más horarios; un horario pertenece a un único grupo.
- Un horario usa una ubicación escolar; una ubicación puede servir a varios horarios.
- Un periodo de inscripción pertenece al ámbito real que se confirme antes de 6B: programa o grupo, pero no ambos de forma ambigua.
- Una futura solicitud pertenecerá al periodo y, si procede, al grupo; no se relacionará con campeonato o categoría.

Se deben definir claves foráneas y restricciones de borrado conservadoras. No se borrará una entidad con dependencias operativas sin una decisión explícita; archivar u ocultar será preferible cuando exista historial.

## 16. Estados y visibilidad

El estado operativo y la intención pública deben ser dimensiones separadas, pero no se copiarán los enums de Competición ni `draft`/`published` del CMS.

Contrato propuesto:

- `is_public` booleano, privado por defecto, para cada entidad que pueda aparecer públicamente;
- enum operativo sólo en programa, grupo y periodo cuando el flujo real confirme sus valores;
- fechas de vigencia únicamente donde representen hechos reales;
- sin publicación programada editorial en el MVP salvo necesidad demostrada;
- sin global scopes.

Visibilidad efectiva:

- programa: propio `is_public`;
- grupo: propio `is_public` y programa efectivo;
- horario: propio `is_public`, grupo/programa efectivos y ubicación pública;
- ubicación: propio `is_public`; se descubre desde un horario efectivo;
- periodo: propio `is_public`, padre efectivo y estado/ventana coherentes.

Blade impedirá hacer público un hijo bajo un padre privado y publicar registros incompletos. Ocultar un padre no necesita reescribir hijos: conservar flags facilita restaurar la rama, pero esta semántica deberá aprobarse y probarse dentro del dominio escolar, no heredarse sólo por analogía. La API filtra antes del Resource.

“Inscripción abierta” no se infiere sólo de que exista un formulario o un grupo. Debe resultar del periodo operativo aprobado y sus fechas coherentes. Un periodo cerrado puede mostrarse como cerrado cuando sea útil, pero nunca como abierto.

## 17. Administración

6B necesitará controladores web, Form Requests, vistas Blade y rutas bajo el grupo administrativo actual:

| Bloque | CRUD/acción | Validaciones y visibilidad | Permisos, feedback y tests |
|---|---|---|---|
| Programa | Índice, alta, detalle, edición; borrado sólo si es seguro | campos mínimos, fechas coherentes, estado permitido, privado por defecto | administrador activo; mensajes de éxito/error y cobertura de roles |
| Grupos | CRUD anidado en programa | padre inmutable, destinatario explícito, orden, completitud para publicar | misma autorización; errores de jerarquía comprensibles |
| Horarios | CRUD anidado en grupo | forma temporal aprobada, inicio anterior a fin, ubicación válida, sin contexto huérfano | feedback y tests de conflicto sólo si existe regla real |
| Ubicaciones | CRUD reutilizable | nombre y dato público mínimo; no asumir `Venue` | impedir borrados con horarios o definir archivo |
| Periodos | CRUD/acciones de estado | ámbito único, cronología y coherencia de apertura | no mostrar como abierto lo que está cerrado |
| Contacto | Configuración mínima si se aprueba | canal organizativo validado y exposición explícita | no publicar datos personales por defecto |
| Solicitudes | No en MVP; futuro listado/detalle/transición, no CRUD indiscriminado | minimización, estados y conservación aprobados | permiso específico, auditoría y respuestas opacas |
| Avisos | CMS genérico o futuro bloque específico | fuente única, fecha/ámbito si se estructura | evitar duplicidad entre CMS y dominio escolar |

Los controladores usarán exclusivamente `validated()` y persistencia explícita. No se añade una arquitectura excesiva ni se ofrece edición de campos técnicos o relaciones mediante payload manipulado.

## 18. API pública

Para el MVP se recomienda una única lectura agregada. Reduce estados inconsistentes y entrega exactamente la composición operativa necesaria para `/escuela`.

| Método y ruta propuesta | Responsabilidad | Resource | Visibilidad | Consumidor |
|---|---|---|---|---|
| `GET /api/v1/school` | Resumen de programas efectivos con grupos, horarios, ubicaciones y estado de inscripción | `PublicSchoolOverviewResource` y Resources hijos cerrados | Sólo ramas efectivamente públicas; sin flags ni campos admin | Landing React `/escuela` |

El contrato deberá conservar el envelope API del proyecto y distinguir colección vacía de fallo. No expondrá IDs innecesarios, notas internas, capacidad si no es publicable, datos personales, solicitantes, responsables privados, flags, timestamps administrativos o filas ocultas.

No se justifican inicialmente endpoints públicos genéricos para alumnos, contactos, ubicaciones aisladas, solicitudes o avisos. Si el volumen o las rutas de detalle futuras lo exigen, se diseñarán tras medir el caso real.

Un eventual `POST /api/v1/school/enrollment-requests` queda fuera del MVP y requiere un contrato separado de seguridad, validación y privacidad.

## 19. API administrativa

No se necesita API administrativa para 6B: Blade puede operar mediante controladores web Laravel, como interfaz oficial de administración. Añadir un CRUD JSON duplicaría autorización y contratos sin consumidor real.

Si en el futuro existe un cliente administrativo distinto de Blade, se diseñarán rutas y Resources administrativos propios. Nunca se reutilizará el Resource público ni se expondrán solicitudes a través de endpoints de colección sin autorización estricta.

## 20. Experiencia pública

La landing de 6C reutilizará la estructura de `PublicLanding`, sin convertir sus props en fuente editorial. Sólo montará bloques con datos reales:

1. cabecera con H1 y explicación procedente de la fuente aprobada;
2. acceso contextual al Manual;
3. grupos públicos con destinatarios confirmados;
4. horarios acompañados de grupo y ubicación;
5. estado de inscripción;
6. canal de contacto aprobado;
7. avisos sólo si hay una fuente clasificada y datos vigentes.

La metodología o presentación estable aparecerán sólo si existe una colección pública aprobada. No habrá tarjetas vacías, edades inventadas, responsables ficticios, horarios de muestra, ubicaciones simuladas o CTA sin destino.

## 21. Ruta recomendada

La ruta canónica será `/escuela`.

Es breve, coherente con `/competicion` y `/aprende-a-jugar`, ya está reservada por el contrato público y evita fijar el nombre completo en todas las subrutas. La etiqueta y H1 serán “Escuela de Galotxas”; el título básico será “Escuela de Galotxas | Galotxas”.

`/escuela-de-galotxas` no aporta una ventaja que compense una URL más larga. No se crea alias ni redirect en 6A.

## 22. Navegación

Cuando 6C supere sus gates, “Escuela de Galotxas” ocupará la cuarta posición del Navbar, después de Aprende a jugar y antes de Club. Su criterio activo cubrirá `/escuela` y las subrutas que se aprueben.

Relaciones:

- Aprende a jugar ofrece el conocimiento general y el Manual;
- Escuela presenta la oferta formativa real y puede enlazar al Manual;
- Club conserva la información institucional;
- el CMS genérico puede aportar una pieza clasificada, pero `/contenidos/academy` no es la ruta canónica.

Actualmente Escuela sigue ausente del Navbar y `/escuela` continúa resolviendo a la 404. Esta ausencia es correcta hasta que la landing tenga fuente, datos, estados y tests.

## 23. Estados remotos

La futura landing separará la carga operativa de la disponibilidad del conocimiento compilado:

| Situación | Comportamiento |
|---|---|
| Carga | Estado anunciado en el bloque operativo; no sustituye el H1 por un placeholder |
| Error total de API | Mensaje recuperable y reintento; conservar enlaces estáticos al Manual si están disponibles |
| Datos parciales | Mostrar bloques válidos y aislar el error o ausencia local |
| Sin programas/grupos públicos | Estado neutral “sin grupos publicados”, sin simular oferta |
| Inscripción cerrada | Mostrar cerrada con contexto real; no renderizar CTA de solicitud |
| Sin horarios | Omitir horario o indicar ausencia dentro del grupo; nunca mostrar una tabla huérfana |
| Sin avisos | Omitir por completo el bloque |
| Sin contenido estable propio | Mantener sólo enlaces reales al Manual y datos operativos; no crear copy de relleno |
| Esquema API inválido | Tratar como error del bloque, no intentar adivinar campos |

Sólo bloquea toda la información operativa un fallo que impida validar el agregado. Un error en avisos, contacto opcional o contenido estable no debe ocultar grupos y horarios válidos.

## 24. Privacidad y seguridad

Principios obligatorios:

- recoger sólo datos necesarios para el proceso aprobado;
- no crear perfiles públicos de alumnado;
- no exigir cuenta o `Player` sin necesidad confirmada;
- tratar nombres de menores, tutores y responsables como privados por defecto;
- separar Resources públicos, administrativos y, si llega a existir, del propio solicitante;
- impedir enumeración mediante identificadores o respuestas diferenciadas;
- aplicar rate limiting y medidas antispam propias a escrituras anónimas;
- validar y autorizar en backend, no confiar en el formulario React;
- evitar datos personales y contenido de solicitudes en logs ordinarios;
- definir acceso administrativo, transiciones, exportación y conservación antes de almacenar solicitudes;
- empezar sin adjuntos;
- probar anónimo, usuario, administrador activo e inactivo y acceso directo;
- no dar asesoramiento legal desde el software ni inventar requisitos; las decisiones organizativas y de privacidad requieren revisión humana.

La cuenta deportiva y el perfil `Player` no constituyen consentimiento ni autorización para procesos escolares.

## 25. Multimedia

Necesidades probables: imagen institucional de portada, fotografías de actividades, eventualmente vídeo y documentos. No son necesarias para publicar el MVP.

Antes de implementarlas se requiere:

- procedencia, titularidad, permiso de uso, responsable y posibilidad de retirada;
- autorización específica cuando aparezcan menores;
- texto alternativo significativo;
- portada y orden editorial explícitos;
- variantes/tamaños y límites de formato;
- almacenamiento persistente desacoplado del contenedor y de Git;
- subida, reemplazo y eliminación administradas sin dejar referencias rotas.

Las galerías CMS actuales sólo almacenan URLs y no aportan por sí mismas procedencia, consentimiento, alt por imagen o ciclo de vida. No se usarán como atajo para fotografías escolares.

## 26. Migración de `academy`

Estrategia recomendada, sin ejecutar en 6A:

1. conservar temporalmente la página y `/contenidos/academy`;
2. inventariar en cada entorno sus datos, bloques, estado, enlaces y tráfico;
3. revisar editorialmente qué contenido es útil y clasificarlo como estable, operativo o descartable;
4. migrar cada pieza a una única fuente nueva y verificar paridad;
5. retirar cualquier enlace legado cuando `/escuela` sea funcional;
6. decidir despublicación y un redirect futuro sólo con destino equivalente, pruebas, canonical y plan SEO;
7. eliminar la página únicamente cuando no queden datos útiles, consumidores ni compatibilidad pendiente.

No se recomienda redirigir ahora: `/escuela` no existe y el contenido genérico de `academy` no es equivalente. La URL, los datos y la navegación se migran como problemas separados.

## 27. Preguntas abiertas

Prioridad bloqueante para 6B:

1. ¿Qué representa un programa: curso anual, campaña, actividad o varias ofertas simultáneas?
2. ¿Cuáles son los grupos reales y cómo se describen sus destinatarios sin inventar edades o niveles?
3. ¿Los horarios son semanales recurrentes, sesiones con fecha o una combinación?
4. ¿Cuáles son las ubicaciones escolares reales y coinciden o no con `Venue`?
5. ¿La inscripción se abre por programa o por grupo y cuáles son sus estados reales?
6. ¿Qué canal organizativo de contacto puede publicarse y quién lo mantiene?
7. ¿El MVP puede publicarse sin recibir solicitudes, como recomienda esta auditoría?

Prioridad previa a una iteración transaccional:

8. ¿Quién presenta la solicitud: adulto participante, tutor o centro educativo?
9. ¿Se necesita cuenta o debe admitirse una petición anónima?
10. ¿Qué datos son estrictamente necesarios y durante cuánto tiempo se conservan?
11. ¿Cómo se documentan consentimiento, respuesta, retirada y tratamiento de menores?
12. ¿Qué flujo y estados usa realmente el responsable de la Escuela?
13. ¿Existen capacidad/plazas y deben mostrarse o sólo gestionarse internamente?

Prioridad editorial y multimedia:

14. ¿Existe material pedagógico revisado para una colección propia?
15. ¿Quién aprueba y mantiene ese contenido?
16. ¿Qué contenido real tiene `academy` en cada entorno?
17. ¿Qué imágenes o vídeos tienen procedencia y permisos documentados?
18. ¿Se necesitan avisos propios o basta una pieza CMS genérica?

## 28. Plan 6B

Fase 6B permanece pendiente y conviene dividirla:

### 6B.1 — Cierre funcional y esquema

- obtener respuestas humanas bloqueantes;
- fijar vocabulario, entidades, campos, enums y regla temporal;
- confirmar contacto, ámbito de inscripción y semántica de visibilidad;
- decidir independencia o relación explícita con `Venue`;
- actualizar contratos antes de migrar.

### 6B.2 — Dominio y administración operativa

- nuevas migraciones MariaDB reversibles;
- modelos, relaciones, casts, factories y seeders sólo para desarrollo/E2E;
- Form Requests, persistencia explícita y reglas jerárquicas;
- controladores, rutas y vistas Blade para el núcleo aprobado;
- permisos de administrador activo, feedback y restricciones de borrado;
- nuevos registros privados y protección de registros incompletos;
- tests Feature administrativos, de modelo y migración.

### 6B.3 — Lectura pública

- scopes o consultas efectivas explícitas;
- endpoint agregado y Resources públicos cerrados;
- contratos de vacío, orden y fechas;
- exclusión de información privada y campos administrativos;
- tests de listados, ramas ocultas, acceso directo y regresión;
- documentación de API/Resources cuando el contrato exista.

Un formulario de solicitud no se añade a estos subbloques salvo que las decisiones de privacidad y proceso se cierren primero. En ese caso se planificará como subbloque transaccional separado, no como ampliación incidental.

## 29. Plan 6C

Fase 6C permanece pendiente:

1. decidir si se enlaza sólo el Manual o si existe contenido suficiente para ampliar `knowledge/`;
2. si procede, definir colección, metadatos, rutas lógicas, validación, proyección y responsabilidad editorial antes de crear documentos;
3. crear servicio y hook React para el agregado público con validación del contrato;
4. registrar `/escuela` y componer la landing con `PublicLanding`;
5. implementar estados de carga, error, parcial y vacío por bloque;
6. montar sólo secciones con datos reales;
7. añadir metadatos y navegación contextual accesible;
8. incorporar “Escuela de Galotxas” al Navbar en cuarta posición y cubrir estado activo, móvil y Escape;
9. añadir formulario sólo si un bloque transaccional previo lo ha aprobado e implementado;
10. probar componentes, integración, responsive, teclado, E2E y regresión;
11. ejecutar la migración de `academy` únicamente con paridad y compatibilidad verificadas;
12. actualizar documentación y mantener fuera los placeholders.

## 30. Testing

El plan `SCHOOL-CONTRACT-AUDIT-1` no representa pruebas de código existentes. Para 6B/6C prevé:

- modelos, casts, relaciones, factories, migraciones y defaults;
- autorización de admin activo, inactivo, usuario y anónimo;
- Form Requests, whitelists, cronología, estados y jerarquía;
- alta/edición/ocultación y prevención de publicación incompleta;
- persistencia y feedback Blade;
- visibilidad efectiva, ramas privadas y Resources sin campos administrativos;
- endpoint agregado con contenido, vacío, datos parciales y orden determinista;
- ausencia pública de personas, flags internos y solicitudes;
- servicio/hook React y estados loading/error/retry/empty/partial/content;
- una sola H1, metadatos, semántica, teclado, foco y responsive;
- Navbar desktop/móvil, orden, estado activo, cierre al navegar y Escape;
- enlaces al Manual y cualquier extensión del compilador de `knowledge/`;
- E2E administrativo-público sobre MariaDB y datos controlados;
- rate limiting, enumeración, permisos y conservación si se aprueba el formulario;
- pruebas de compatibilidad antes de redirigir o retirar `academy`.

No se ejecutan suites en 6A porque no se modifica código.

## 31. Deuda futura

- formulario de información o inscripción y su privacidad;
- avisos estructurados si el CMS genérico no basta;
- capacidad o plazas reales;
- calendario no semanal;
- cuenta/seguimiento privado del solicitante, sólo si se demuestra necesario;
- notificaciones;
- almacenamiento persistente, multimedia y retirada;
- permisos administrativos granulares y auditoría;
- exportación y conservación de solicitudes;
- URLs secundarias, SEO, canonical y redirect de `academy`;
- colección pedagógica de Escuela y evolución del compilador;
- traducciones;
- limpieza de menciones hardcodeadas en Home y `/nosotros`;
- integración o separación definitiva respecto a `Venue`.

No forman parte del objetivo: pagos, asistencia, expedientes, calificaciones, estadísticas escolares, mensajería interna o plataforma educativa.

## 32. Criterios de aceptación

Fase 6A se considera cerrada documentalmente cuando:

- el estado backend, frontend, CMS y `knowledge/` está auditado;
- `academy` está localizado y dispone de estrategia no ejecutada;
- necesidades, fuentes de verdad y MVP están clasificados;
- existe una propuesta de dominio provisional sin confundir Escuela y competición;
- Blade, API pública, experiencia React, estados, privacidad y multimedia están planificados;
- las preguntas humanas bloqueantes son explícitas;
- 6B y 6C tienen un plan, pero siguen pendientes;
- no se ha creado `/escuela`, enlace de Navbar, formulario, modelo, endpoint o contenido;
- Fase 6 permanece abierta;
- la documentación refleja con claridad estado actual y objetivo;
- `git diff --check` no devuelve errores.
