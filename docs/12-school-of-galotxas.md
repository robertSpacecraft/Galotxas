# Contrato funcional de Escuela de Galotxas

## 1. Propósito

Este documento registra la auditoría de Fase 6A, el cierre funcional aprobado en Fase 6A.1 y las implementaciones 6B.1–6C: núcleo operativo, inscripciones, centros/actividades, lectura pública y experiencia React. Fase 6C.1 remedia el aislamiento Docker y revalida ese cierre sin cambiar el dominio escolar.

Fase 6A.1 fue exclusivamente documental. Fase 6B.1 crea programa, niveles, ubicaciones y horarios. Fase 6B.2 añade `SchoolEnrollment`, administración Blade y el POST público. Fase 6B.3 añade centros y actividades. Fase 6B.4 añade `GET /api/v1/school`. Fase 6C añade componentes React, formulario, Navbar y un fixture exclusivo E2E; no crea contenido en `knowledge/` ni seeders de desarrollo o producción. La aceptación de 6C se suspendió tras el incidente de limpieza y se restableció sólo después de la revalidación aislada de 6C.1.

## 2. Estado actual

La Escuela de Galotxas dispone de núcleo operativo, inscripciones, centros y actividades administrativas:

- existen `SchoolProgram`, `SchoolLevel`, `SchoolLocation` y `SchoolSchedule`, con relaciones, visibilidad efectiva, defaults seguros y administración Blade;
- existe `SchoolEnrollment` con solicitudes pendientes, participantes activos, rechazos y bajas, sin eliminación normal;
- las siete áreas Blade están protegidas por sesión, CSRF y administrador activo;
- existe `GET /api/v1/school`, anónimo y de sólo lectura, con visibilidad efectiva y allowlist;
- existe `POST /api/v1/school/enrollments`, anónimo y con cuenta opcional, limitado y sin respuesta enumerable;
- existen `EducationalCenter` y `EducationalActivity`, exclusivamente administrativos;
- React registra `/escuela` y el disclosure Aprende la enlaza;
- `knowledge/` no contiene una colección pedagógica de Escuela;
- el CMS genérico conserva el slug legado `academy`, que no equivale a este dominio;
- las inscripciones deportivas exigen cuenta y perfil `Player`, por lo que no representan el flujo escolar.

Fase 6C publica la sección React `/escuela`, el consumidor, el formulario y su acceso en Navbar/Home sobre los contratos existentes.

## 3. Auditoría de capacidades reutilizables

| Área actual | Capacidad reutilizable | Límite |
|---|---|---|
| Laravel y MariaDB | Modelos, relaciones, Services, Form Requests y transacciones | Núcleo, inscripciones, centros y actividades implementados |
| Blade | Interfaz administrativa oficial y middleware de administrador activo | Programa, niveles, ubicaciones, horarios, inscripciones, centros y actividades implementados |
| API | Resources por contexto y envelopes existentes | POST escolar implementado; lectura pendiente |
| Rate limiting | Mecanismo ya usado en auth y resultados | `school-enrollments` implementado con clave no reversible |
| `User` | Asociación opcional con una persona autenticada | Nunca será requisito ni fuente de los datos enviados |
| `Player` | Ninguna reutilización semántica | Es un perfil deportivo con datos no necesarios |
| Inscripción a campeonato | Patrones técnicos de validación, servicio, transición y tests | Finalidad, identidad, estados y datos son distintos |
| CMS | Página o aviso simple no estructurado | No almacena niveles, horarios, alumnos, centros o actividades |
| `knowledge/` | Manual, reglas y conceptos enlazables | No existe pedagogía específica de Escuela |
| React | `PublicLanding`, metadatos, navegación y estados remotos | No es fuente editorial ni barrera de seguridad |

Las solicitudes escolares podrán reutilizar infraestructura técnica, pero no `ChampionshipRegistrationRequest`, `CategoryRegistration`, `Player`, categorías competitivas o equipos.

## 4. Decisión sobre ubicaciones

No existe un modelo `Location`. El modelo real es `Venue`, con `name`, `location` y `description`, administrado públicamente como “Pistas”.

`Venue` no es suficientemente genérico para Escuela:

- `GenerateLeagueScheduleService` toma todos los registros de `venues` como pistas utilizables por la competición;
- `GameMatch` y las solicitudes de reprogramación dependen directamente de él;
- `Venue::isInUse()` sólo contempla partidos y reprogramaciones;
- su CRUD, seeder y feedback están expresados en términos de pistas deportivas;
- carece de activación y de un contrato de uso por módulos.

Guardar en `venues` un colegio u otra ubicación puntual permitiría que el generador la asignase a partidos. Reutilizarlo exigiría rediseñar primero su semántica y todas sus consultas, algo fuera del dominio escolar.

Decisión implementada: 6B.1 crea `SchoolLocation` para los horarios de la Escuela permanente y 6B.3 incorpora su relación opcional con las actividades de centros. No se modifica `Venue`, que continúa reservado al contrato competitivo actual.

Campos de `SchoolLocation` en 6B.1:

- `name`;
- `locality`;
- `address`, nullable;
- `is_active`;
- `sort_order`;
- `admin_notes`, nullable y nunca pública;
- timestamps.

No necesita `is_public`: sólo se descubre públicamente cuando una programación efectiva la referencia. Desactivarla impide asociaciones nuevas, excluye los horarios relacionados de la lectura pública y conserva horarios y actividades históricos.

## 5. Arquitectura híbrida

```text
knowledge/ ── compilador ──► React
  Manual y futura pedagogía estable

Blade ──► Laravel/MariaDB ──► API pública ──► React
  programa, niveles, horarios, ubicaciones e inscripción

Blade ──► Laravel/MariaDB
  centros y actividades educativas, sólo administración en el MVP
```

Responsabilidades:

- `knowledge/`: reglas, conceptos y futura metodología estable;
- Laravel: datos operativos, datos personales, estados y restricciones;
- Blade: administración;
- API: proyección pública cerrada y recepción segura de solicitudes;
- React: presentación, validación básica y estados de interfaz;
- CMS genérico: sólo piezas simples no estructuradas que no dupliquen el dominio.

## 6. Dos subdominios

### Escuela permanente

Gestiona:

- configuración pública;
- niveles formativos;
- horarios semanales;
- ubicaciones;
- apertura y cierre de inscripciones;
- solicitudes pendientes;
- participantes activos;
- rechazos y bajas.

### Centros y actividades educativas

Gestiona:

- centros educativos persistentes;
- actividades múltiples por centro;
- fechas, horarios, ubicación y alumnado previsto;
- estados planificada, completada y cancelada;
- información exclusivamente administrativa en el MVP.

No se registran nominalmente participantes de las actividades con centros y no se crean inscripciones escolares por cada asistente.

## 7. Escuela permanente

La Escuela es una actividad permanente. El MVP no la divide en cursos, temporadas, convocatorias académicas o programas temporales sucesivos.

`SchoolProgram` representa su configuración operativa. El modelo admite varios registros para no imponer un singleton rígido, pero el MVP permite como máximo un programa público. MariaDB garantiza esa exclusividad mediante una columna generada nullable e índice único; el servicio añade transacción, bloqueo y un error administrativo comprensible. La futura API expondrá únicamente ese programa público.

No se almacenan fechas de inicio o fin del programa. Una futura coexistencia de programas públicos exigirá revisar el contrato de selección y la API, no crear ahora una plataforma académica.

## 8. Participantes menores y adultos

La Escuela se orienta principalmente a menores, pero admite solicitudes de adultos.

La condición de menor se calcula desde `participant_birth_date` respecto de `requested_at`, usando 18 años como umbral funcional de mayoría de edad. No se almacena edad calculada y no se fija una edad mínima o máxima global de admisión.

Las edades mínima y máxima de un nivel son opcionales. Si se configuran, describen ese nivel y se validan al solicitarlo; no impiden que administración asigne otro nivel durante la revisión.

## 9. Representante condicional

El formulario es único.

Para una persona menor de 18 años en la fecha de solicitud:

- `guardian_name` es obligatorio;
- `guardian_relationship` es obligatorio;
- `contact_phone` y `contact_email` representan normalmente el contacto del adulto responsable.

Para una persona adulta:

- representante y relación no son obligatorios;
- teléfono y correo continúan siendo obligatorios;
- si llegan valores de representante sin necesidad funcional, el Request los normalizará a `null` para no conservar datos personales innecesarios.

La validación decisiva se ejecuta en backend usando `requested_at` generado por el servidor. React sólo anticipará el feedback.

## 10. Contacto de solicitud y contacto público

Toda solicitud exige:

- `contact_phone`;
- `contact_email`.

Estos campos pertenecen a `SchoolEnrollment` y nunca son públicos.

El canal operativo del programa es distinto de los contactos declarados por una
solicitud. `SchoolProgram.contact_phone` y `contact_email` son privados y
editables desde Blade; 7E deja de exponerlos en la API. Teléfono y correo de la
solicitud continúan siendo obligatorios y exclusivamente privados.

No se inventan teléfonos, correos institucionales, textos legales o consentimientos.

## 11. Niveles

La denominación técnica cerrada es `SchoolLevel`. Evita confusión con las categorías de Competición y no usa `Category` sin contexto.

Un nivel:

- pertenece a un programa;
- clasifica la oferta;
- agrupa horarios;
- puede orientar por edad;
- permite solicitar o asignar participantes;
- dispone de activación, visibilidad y orden.

No necesita slug en el MVP: no existe ruta de detalle por nivel y el identificador público será el necesario para seleccionar el nivel en una solicitud. Si en el futuro un nivel se divide en varios grupos con horarios propios, podrá evaluarse `SchoolGroup` como ampliación; no forma parte del modelo 6B actual.

## 12. Nivel inicial

La situación inicial tendrá conceptualmente un nivel “Infantil/juvenil”.

Esto no implica:

- seeder en 6A.1;
- edades mínima o máxima inventadas;
- exclusión de adultos del dominio;
- clasificación deportiva;
- imposibilidad de añadir niveles infantiles, juveniles o de adultos en el futuro.

El nivel se crea mediante el flujo administrativo disponible desde 6B.1; no se incorpora un seeder.

## 13. Horarios semanales

`SchoolSchedule` representa una recurrencia semanal simple:

- nivel;
- día de la semana;
- hora de inicio;
- hora de finalización;
- ubicación;
- activo/inactivo;
- orden.

Contrato de `day_of_week`: entero ISO 8601 de 1 a 7, donde 1 es lunes y 7 domingo. Es compacto, independiente del idioma, compatible con las utilidades de fecha del backend y evita persistir labels traducibles. Blade, API y React traducirán el número a una etiqueta.

Validaciones:

- día entre 1 y 7;
- `starts_at` anterior a `ends_at`;
- nivel perteneciente a un programa y activo al crear o reactivar;
- ubicación escolar existente y activa;
- orden entero no negativo;
- combinación exacta de nivel, ubicación, día y horas no duplicada;
- no crear sesiones individuales, reglas RFC, festivos, excepciones o recuperaciones.

Los solapamientos parciales se permiten deliberadamente: puede haber niveles distintos simultáneos y no existe una restricción humana aprobada sobre recursos compartidos.

## 14. Apertura y cierre

`SchoolProgram.enrollments_open` es sólo la apertura declarada. Desde 7E la
recepción depende de `SchoolEnrollmentAvailabilityService`.

Cuando sea `false`:

- la información pública puede seguir visible;
- `POST /api/v1/school/enrollments` rechazará nuevas solicitudes;
- React mostrará el estado cerrado y no ofrecerá un formulario operativo;
- las solicitudes existentes y su administración no cambian.

La apertura pública efectiva requiere simultáneamente:

- programa público;
- `enrollments_open = true`;
- `SCHOOL_ENROLLMENT_ENABLED = true`;
- contenido, ubicación, nivel/horario y aviso vigente completos.

Un programa privado o incompleto no puede guardarse administrativamente como
abierto. Una configuración completa puede conservar apertura declarada mientras
la flag del entorno sigue cerrada; la API la representa como `closed`.

## 15. Solicitud pública

El backend de inscripción pública se implementa en 6B.2 y su formulario React pertenece a 6C:

- no requiere registro ni autenticación;
- crea un `SchoolEnrollment` en estado pendiente;
- no crea un alumno activo, `Player` o permiso;
- requiere revisión administrativa;
- permite solicitar opcionalmente un nivel activo y público;
- administración puede cambiar o asignar el nivel al aprobar.

Se adopta la opción A: el nivel puede solicitarse públicamente y modificarse al aprobar. Con un único nivel, el campo puede omitirse o preseleccionarse en interfaz; mantenerlo opcional evita rediseñar el formulario cuando existan varios.

`school_level_id` conserva el nivel solicitado mientras la inscripción está pendiente y el nivel asignado tras la activación. No se crea un segundo campo ni historial de cambios en el MVP.

## 16. Cuenta opcional

`SchoolEnrollment.user_id` es nullable.

- una solicitud anónima lo deja a `null`;
- si existe una sesión Sanctum válida, el controlador asigna el usuario actual;
- el cliente nunca podrá enviar un `user_id` arbitrario;
- la vinculación no sobrescribe nombre, nacimiento, contacto o representante enviados;
- no crea `Player`;
- no concede zona privada escolar ni acceso adicional;
- no altera el mismo flujo de aprobación.

## 17. Ciclo de inscripción

Se utilizará un enum PHP respaldado por string y una columna string:

| Valor técnico | Etiqueta | Semántica |
|---|---|---|
| `pending` | Pendiente | Solicitud recibida, todavía no resuelta |
| `active` | Activa | Participante admitido y actualmente inscrito |
| `rejected` | Rechazada | Solicitud no aceptada |
| `withdrawn` | Baja | Participante previamente activo que dejó la Escuela |

Transiciones MVP:

```text
pending ──► active ──► withdrawn
    └─────► rejected
```

No se permiten activación directa desde rechazada o baja, rechazo de una inscripción activa ni retorno automático a pendiente.

Fechas:

- `requested_at`: siempre, asignada por servidor;
- `activated_at`: obligatoria al pasar a activa;
- `rejected_at`: obligatoria al rechazar;
- `withdrawn_at`: obligatoria al dar de baja;
- las demás fechas de transición permanecen nulas cuando no aplican.

No hay eliminación física desde el flujo normal. Una futura reinscripción creará un nuevo `SchoolEnrollment` pendiente y conservará el anterior como baja o rechazado. La vinculación histórica entre intentos y los periodos múltiples se aplaza hasta existir una necesidad real.

## 18. Plazas, pagos y exclusiones

El MVP no gestiona:

- capacidad;
- plazas libres;
- lista de espera;
- bloqueos o asignación automática por cupo;
- cuotas o pagos;
- renovaciones anuales;
- asistencia;
- expedientes;
- calificaciones;
- historial médico;
- documentos de identidad;
- dirección postal completa;
- imágenes o adjuntos;
- mensajería interna;
- estadísticas escolares.

La falta de plazas y los pagos permanecen como posibles ampliaciones, no como campos ocultos o estados anticipados.

## 19. Centros educativos

Desde 6B.3, `EducationalCenter` representa un centro reutilizable en múltiples actividades.

Contrato mínimo:

- `name` y `locality` obligatorios;
- `contact_name`, `contact_phone` y `contact_email` nullable;
- `is_active`;
- `admin_notes` nullable;
- timestamps.

Nace inactivo para exigir revisión antes de recibir una actividad. No es público en el MVP. No se añade CIF, código de centro, dirección completa, datos fiscales o adjuntos.

No se impone unicidad global ni compuesta en base de datos. Dos centros pueden compartir nombre, incluso en una misma localidad; Blade deberá mostrar contexto suficiente y podrá advertir de coincidencias sin bloquearlas.

## 20. Actividades educativas

Desde 6B.3, `EducationalActivity` pertenece a un centro y registra:

- nombre libre obligatorio;
- fecha obligatoria;
- hora de inicio y fin opcionales, ambas presentes o ambas ausentes;
- número previsto de alumnos;
- ubicación escolar opcional;
- estado;
- observaciones administrativas;
- timestamps.

Los nombres “Jornada de convivencia”, “Clase de Galotxas” o “Introducción a las Galotxas” son ejemplos, no enums ni seeders.

Estados mediante enum PHP respaldado por string:

| Valor técnico | Etiqueta |
|---|---|
| `planned` | Planificada |
| `completed` | Completada |
| `cancelled` | Cancelada |

`expected_students` es nullable mientras la actividad está planificada y debe ser un entero positivo al marcarla como completada. Cero no es un valor válido: una actividad sin participación debe permanecer planificada, cancelarse o corregirse. Una cancelada puede conservar el último valor previsto.

Si hay horas, `starts_at` será anterior a `ends_at`. La ubicación debe existir y estar activa al asignarla. Desactivar después el centro o la ubicación no borra la actividad histórica.

Toda alta nace `planned`. `EducationalActivityService` aplica en transacciones únicamente `planned → completed` o `planned → cancelled`; repetir o revertir una transición se rechaza. El formulario general no acepta `status`.

No existe formulario público para centros, reservas, cuentas, aprobación externa, asistentes nominales, asistencia, facturación, pagos o adjuntos.

## 21. Modelo funcional definitivo

```text
SchoolLocation
├── SchoolProgram (default location)
├── SchoolSchedule
└── EducationalActivity

SchoolProgram
├── SchoolLevel
│   ├── SchoolSchedule
│   └── SchoolEnrollment (nullable while pending)
└── SchoolEnrollment

EducationalCenter
└── EducationalActivity
```

Entidades cerradas para 6B:

| Entidad | Responsabilidad | Pública en MVP |
|---|---|---|
| `SchoolProgram` | Configuración de la Escuela permanente | Sí, si es pública |
| `SchoolLevel` | Oferta formativa y asignación | Sí, si es efectiva |
| `SchoolSchedule` | Horario semanal | Sí, si es efectivo |
| `SchoolLocation` | Ubicación común al dominio escolar | Sólo mediante horarios efectivos |
| `SchoolEnrollment` | Solicitud, alta, rechazo y baja | Nunca |
| `EducationalCenter` | Centro reutilizable | No |
| `EducationalActivity` | Actividad con un centro | No |

No forman parte del modelo los periodos de inscripción separados, solicitudes distintas de `SchoolEnrollment`, alumnos nominales de centros o un perfil escolar separado.

## 22. Campos mínimos

| Entidad | Campos |
|---|---|
| `SchoolProgram` | `name`, `public_description`, `enrollment_information`, `is_public`, `enrollments_open`, `default_school_location_id` nullable, contacto operativo privado nullable, `sort_order`, `public_slot` generado, timestamps |
| `SchoolLevel` | `school_program_id`, `name`, `minimum_age` nullable, `maximum_age` nullable, `is_active`, `is_public`, `sort_order`, timestamps |
| `SchoolSchedule` | `school_level_id`, `day_of_week`, `starts_at`, `ends_at`, `school_location_id`, `is_active`, `sort_order`, timestamps |
| `SchoolLocation` | `name`, `locality`, `address` nullable, `is_active`, `sort_order`, `admin_notes` nullable, timestamps |
| `SchoolEnrollment` | relaciones, PII nullable tras anonimización, estado y ciclo, aviso/aceptación, actores de transición/corrección, vencimiento, hold, anonimización, notas privadas y timestamps |
| `EducationalCenter` | `name`, `locality`, `contact_name` nullable, `contact_phone` nullable, `contact_email` nullable, `is_active`, `admin_notes` nullable, timestamps |
| `EducationalActivity` | `educational_center_id`, `name`, `activity_date`, `starts_at` nullable, `ends_at` nullable, `expected_students` nullable condicional, `school_location_id` nullable, `status`, `admin_notes` nullable, timestamps |

Defaults seguros:

- programa privado y con inscripciones cerradas;
- nivel inactivo y privado;
- horario inactivo;
- ubicación inactiva;
- inscripción pendiente;
- centro inactivo;
- actividad planificada.

No se añaden slugs porque no existen rutas públicas de detalle para estas entidades.

## 23. Relaciones y consistencia

Relaciones:

- `SchoolProgram hasMany SchoolLevel`;
- `SchoolProgram hasMany SchoolEnrollment`;
- `SchoolProgram belongsTo SchoolLocation` como ubicación habitual nullable;
- `SchoolLevel belongsTo SchoolProgram`;
- `SchoolLevel hasMany SchoolSchedule`;
- `SchoolLevel hasMany SchoolEnrollment`;
- `SchoolSchedule belongsTo SchoolLevel`;
- `SchoolSchedule belongsTo SchoolLocation`;
- `SchoolEnrollment belongsTo SchoolProgram`;
- `SchoolEnrollment belongsTo SchoolLevel`, nullable;
- `SchoolEnrollment belongsTo User`, nullable;
- `EducationalCenter hasMany EducationalActivity`;
- `EducationalActivity belongsTo EducationalCenter`;
- `EducationalActivity belongsTo SchoolLocation`, nullable.

Se conservan `school_program_id` y `school_level_id` en `SchoolEnrollment`:

- el programa debe conocerse aunque la persona no solicite un nivel;
- el nivel puede ser nullable durante la revisión;
- cuando exista nivel, debe pertenecer al mismo programa;
- el POST no acepta `school_program_id`: el backend asigna el único programa público;
- cambiar el nivel desde Blade validará la misma consistencia.

La ubicación habitual sólo preselecciona nuevos horarios o sirve de dato general. Cambiarla no modifica horarios ni actividades existentes.

Los borrados serán conservadores:

- no borrar programa, nivel, ubicación o centro con relaciones;
- no borrar inscripciones desde el flujo normal;
- desactivar preserva el histórico;
- una actividad histórica conserva su centro y ubicación aunque se desactiven;
- sólo una actividad que continúa `planned` puede borrarse; `completed` y `cancelled` se conservan.

## 24. Visibilidad efectiva

Política efectiva implementada como scopes internos y consumida por la API de 6B.4:

- programa visible: `is_public = true`;
- nivel visible: programa visible, `is_active = true` e `is_public = true`;
- horario visible: nivel efectivo, `is_active = true` y ubicación activa;
- ubicación: se expone únicamente dentro de un horario efectivo;
- inscripciones abiertas: disponibilidad central `open`; la flag parte de
  `false` y React nunca la decide.

Casos:

| Estado declarado | Resultado público |
|---|---|
| Programa privado + nivel público | No se expone programa, nivel ni horarios |
| Nivel privado + horario activo | No se expone nivel ni horario |
| Ubicación inactiva | Se excluyen los horarios que la usan |
| Programa privado + inscripciones abiertas | No hay formulario efectivo y el POST rechaza |
| Programa público + inscripciones cerradas | Información visible, formulario cerrado |

No se copian cascadas de Competición. Ocultar o desactivar un padre no reescribe flags hijos; los scopes aplican la conjunción efectiva. Blade impide activar/publicar un hijo bajo un padre no válido, pero permite ocultar el padre conservando configuración.

`EducationalCenter` y `EducationalActivity` son administrativos. Su estado operativo nunca implica publicación y no necesitan `is_public` en el MVP.

## 25. Administración Blade

### Programa — implementado en 6B.1

- listado, alta y edición;
- nombre, visibilidad, apertura/cierre, ubicación habitual, contacto público y orden;
- impedir un segundo programa público;
- mostrar por separado visibilidad y apertura efectiva.

### Niveles — implementado en 6B.1

- listado por programa, alta, edición, activación, publicación y orden;
- edades nullable con `minimum_age <= maximum_age`;
- borrado bloqueado cuando tenga horarios o inscripciones;
- sin slug.

### Horarios — implementado en 6B.1

- listado por nivel, alta, edición, activación y orden;
- día ISO, horas y ubicación activa;
- sin sesiones ni excepciones.

### Ubicaciones — implementado en 6B.1

- listado, alta, edición, activación y uso;
- mostrar dependencias con programas y horarios;
- borrado bloqueado mientras tenga relaciones.

### Inscripciones — implementado en 6B.2

- filtros para pendientes, activas, rechazadas y bajas;
- detalle privado;
- aprobar y asignar/reasignar nivel;
- rechazar;
- dar de baja;
- observaciones privadas;
- fechas de transición;
- sin eliminación física normal.

El listado incluye contadores y filtros por programa, nivel y estado con orden estable por solicitud e ID. El alta manual crea una pendiente; la edición no cambia programa, nivel, cuenta, estado o fechas. Aprobar exige un nivel activo del programa, aunque sea privado; una activa puede reasignarse o darse de baja. Rechazadas y bajas no se reactivan.

### Centros — implementado en 6B.3

- listado, alta, edición, activación y detalle;
- contacto y notas privadas;
- historial de actividades;
- filtros por estado y localidad;
- contador y fecha de última actividad;
- coincidencias de nombre permitidas, sin restricción de unicidad;
- borrado bloqueado cuando existe cualquier actividad.

### Actividades — implementado en 6B.3

- listado por centro, estado e intervalo; alta, detalle y edición;
- fecha obligatoria, horas emparejadas, alumnado previsto, ubicación opcional y observaciones privadas;
- nombre libre;
- creación siempre planificada y acciones explícitas de completar o cancelar;
- centro y ubicación activos para asociaciones nuevas, con relaciones históricas preservadas;
- borrado sólo mientras continúa planificada;
- sin dashboard analítico.

Los flujos implementados usan administrador activo, Form Requests, `validated()`, persistencia explícita, feedback y tests de autorización. Los bloques pendientes deberán mantener el mismo contrato. No se añade API administrativa mientras Blade sea el único consumidor.

## 26. API pública

### Lectura

`GET /api/v1/school`

Entrega:

- programa público;
- niveles activos y públicos;
- horarios efectivos;
- ubicaciones asociadas;
- contenido público administrable;
- estado efectivo de inscripción y aviso vigente.

La consulta se centraliza en `SchoolPublicOverviewService`. Restringe las columnas, reutiliza los scopes efectivos, carga de forma anticipada ubicación habitual, niveles, horarios y ubicaciones, y aplica:

- niveles por `sort_order`, `id`;
- horarios por `day_of_week`, `starts_at`, `sort_order`, `id`.

`PublicSchoolResource`, `PublicSchoolLevelResource`, `PublicSchoolScheduleResource` y `PublicSchoolLocationResource` serializan mediante allowlist. Teléfono y correo del programa no se publican; la ubicación habitual sólo aparece si está activa; un nivel efectivo sin horarios continúa con `schedules: []`; el día es ISO 1–7 y las horas usan `HH:MM`.

No entrega ID de programa, flags administrativos, órdenes, claves foráneas, ranura generada, notas, timestamps, alumnado, solicitudes, fechas de nacimiento, contactos privados, usuarios, centros o actividades. Sólo se exponen los IDs mínimos de nivel, horario y ubicación.

Si no existe programa público, responde `200`:

```json
{
  "message": null,
  "data": null
}
```

No selecciona programas privados ni diferencia ausencia y ocultación. Desde
7E, un programa público incompleto sigue siendo una respuesta válida con
`enrollment_status: unavailable`, contenido visible y formulario cerrado.

### Escritura

`POST /api/v1/school/enrollments`

Payload público:

- `participant_name`;
- `participant_birth_date`;
- `contact_phone`;
- `contact_email`;
- `guardian_name`, condicional;
- `guardian_relationship`, condicional;
- `school_level_id`, opcional.

El backend:

- resuelve el único programa público;
- exige inscripción efectiva abierta;
- asigna `requested_at` y estado pendiente;
- calcula minoría de edad sin almacenar edad;
- valida representante;
- acepta sólo un nivel activo, público y del programa;
- toma `user_id` de la sesión opcional, nunca del payload;
- aplica rate limiting y medidas antispam;
- devuelve `201` con confirmación genérica, sin ID administrativo.

Respuestas:

- `422` para validación;
- `409` con un mensaje único si no existe un programa público abierto, sin distinguir ausencia, privacidad o cierre;
- `429` al superar cinco intentos por minuto mediante una clave HMAC de IP y correo normalizado.

La respuesta `201` sólo contiene `message` y `data: null`; no incluye identificador, nombre, correo, nivel, estado o timestamps. No existen listado público, consulta por ID, estado individual, endpoints de alumnos, centros o actividades. Blade opera con rutas web, no con API administrativa.

## 27. Experiencia pública

Fase 6C implementa `/escuela` con:

1. H1 “Escuela de Galotxas”;
2. enlace al Manual;
3. niveles públicos;
4. horarios semanales y ubicaciones;
5. inscripción abierta o cerrada;
6. formulario público cuando esté abierta;
7. primera capa de privacidad versionada;
8. estados remotos y confirmación opaca.

La landing reutiliza `PublicLanding` y `PageMetadata`, y “Escuela de Galotxas” ocupa la cuarta posición del Navbar. No publica centros, actividades o alumnos.

Estados:

| Situación | Comportamiento |
|---|---|
| Loading | Estado anunciado del bloque operativo |
| Error de lectura | Mensaje recuperable y reintento; conservar enlace al Manual |
| `data: null` en lectura | Estado sin configuración pública, no datos simulados |
| Datos parciales | Mostrar niveles/horarios válidos y omitir bloques ausentes |
| Inscripción cerrada | Mensaje claro, sin formulario enviable |
| Error de envío | Mantener valores no sensibles y errores por campo |
| Éxito | Confirmación genérica sin ID o consulta de estado |
| Configuración incompleta | Estado no disponible y formulario cerrado |

React calcula localmente la minoría sólo para adaptar la interfaz; Laravel conserva la decisión final. El cliente no confía en ocultar el formulario como control de seguridad y no persiste contenido editorial o datos personales.

## 28. Privacidad y seguridad

Nunca serán públicos:

- nombres de participantes;
- fechas de nacimiento;
- representante o relación;
- teléfonos y correos de solicitudes;
- estados individuales;
- observaciones;
- `user_id`;
- centros, contactos de centros y actividades del MVP.

Controles:

- minimización de campos;
- Request público cerrado;
- asociación de usuario sólo desde sesión;
- validación condicional en backend;
- respuesta sin ID;
- sin endpoint de consulta;
- rate limiting y antispam;
- datos personales fuera de logs ordinarios;
- administrador activo para toda consulta o transición;
- Resources públicos separados;
- sin exportaciones ni adjuntos;
- conservación y textos de aceptación pendientes de aprobación antes de producción.

No se redactan textos legales, política de privacidad, consentimiento o autorización de imagen. Al no existir textos aprobados, 6C no inventa checkbox o aceptación. Su aprobación y operación son deuda obligatoria antes de abrir inscripciones en producción; los programas nacen cerrados por defecto.

## 29. Contenido editorial y `academy`

La landing podrá:

- enlazar al Manual;
- mostrar datos operativos de Laravel;
- incluir copy breve de interfaz.

No copiará reglas o conceptos a MariaDB, CMS o JSX y no inventará metodología. Una colección de Escuela en `knowledge/` sólo se creará con contenido real, revisado y un contrato editorial.

`academy`:

- no equivale a Escuela de Galotxas;
- no es el dominio operativo;
- no se elimina, despublica, redirige o migra automáticamente;
- permanece accesible según las reglas CMS actuales;
- se revisará editorialmente después de que `/escuela` tenga implementación y paridad.

La URL, los datos, la navegación y el SEO se migrarán como problemas separados.

## 30. Plan 6B

Fase 6B quedó completada en cuatro bloques:

### 6B.1 — Núcleo operativo — completada

- migraciones, modelos, relaciones, enums y factories;
- `SchoolProgram`, `SchoolLevel`, `SchoolSchedule` y `SchoolLocation`;
- decisión ya cerrada de no reutilizar `Venue`;
- administración Blade;
- activación, visibilidad efectiva y apertura;
- tests y documentación.

El bloque implementa las cuatro tablas y modelos, enum de día, factories, validación, servicio transaccional del programa, CRUD Blade, filtros, integridad relacional y cobertura sobre MariaDB. No incluye datos sembrados ni superficie pública.

### 6B.2 — Inscripciones y participantes — completada

- `SchoolEnrollment` y enum de estado;
- `POST /api/v1/school/enrollments`;
- programa abierto y nivel opcional;
- mayoría de edad, representante condicional, teléfono y correo;
- asociación opcional con usuario;
- aprobación, rechazo, baja y reasignación;
- rate limiting, administración Blade, tests y documentación.

El bloque implementa migración, enum, modelo, factory, cálculo histórico de minoría de edad, coherencia programa–nivel en servicio y MariaDB, transiciones transaccionales, POST anónimo con sesión opcional, limitador, privacidad, administración Blade y tests. En aquel bloque no añadió seeders, lectura pública o frontend; 6C consume posteriormente el POST.

### 6B.3 — Centros y actividades — completada

- `EducationalCenter` y `EducationalActivity`;
- administración Blade;
- enums, fechas, horas, alumnado previsto y ubicación;
- validaciones, estados, tests y documentación;
- sin API pública.

El bloque implementa dos migraciones reversibles, modelos, enum, factories, Form Requests, servicio transaccional, CRUD Blade, filtros y protección de borrados. Los centros nacen inactivos y las actividades planificadas; completar exige alumnado previsto positivo. No añade seeders, asistentes nominales, API pública o administrativa, frontend o contenido pedagógico.

### 6B.4 — API pública de lectura — completada

- `GET /api/v1/school`;
- Resources públicos;
- programa principal;
- niveles, horarios, ubicaciones, inscripción y contacto;
- visibilidad efectiva, contratos, tests y documentación.

El bloque implementa la ruta anónima, `SchoolPublicOverviewService`, controlador invocable, cuatro Resources allowlist y cobertura de contrato, visibilidad, orden, datos parciales, formato horario, privacidad y N+1. El POST, su limitador y su respuesta permanecen intactos. No añade migraciones, seeders, administración, frontend o contenido pedagógico.

## 31. Implementación 6C, incidente y revalidación

6C queda completada con:

- servicio Axios, normalización ligera y hook local;
- landing diferida `/escuela`;
- programa, niveles, horarios, ubicaciones, contacto entonces público y apertura;
- formulario con validación básica, menores, adultos, representante condicional y nivel opcional;
- estados de lectura y envío, foco y anuncios accesibles;
- Navbar en cuarta posición, Home sin la referencia pública “Academy” y enlace al Manual;
- responsive 320–1440 px, zoom 200 %, unitarios y E2E;
- `academy` preservado sin migración, despublicación o redirect;
- cierre de Fase 6.

`SCHOOL-CORE-ADMIN-1` cubre el núcleo y `SCHOOL-ENROLLMENT-ADMIN-1` añade 38 tests y 283 aserciones para migración, modelo, factories, consistencia programa–nivel, edad histórica, representante, contacto, apertura, sesión opcional, transiciones, fechas, Blade, privacidad, limitador y ausencia de GET o borrado. `SCHOOL-EDUCATIONAL-ACTIVITIES-1` añade 27 tests y 213 aserciones para migraciones, modelos, factories, relaciones, validación, transiciones, borrados, Blade, permisos y ausencia de API. La regresión escolar finaliza con 93 tests y 678 aserciones; la suite backend completa, con 348 tests y 2654 aserciones.

`SCHOOL-PUBLIC-READ-API-1` cubre en 6B.4:

- ausencia o privacidad del programa con el mismo `data: null`;
- contrato mínimo, completo y parcial;
- apertura efectiva, contacto entonces nullable y ubicación habitual activa;
- niveles efectivos, niveles sin horarios y orden estable;
- horarios efectivos, día ISO, horas `HH:MM` y ubicación activa;
- allowlists recursivas sin datos administrativos, inscripciones, usuarios, centros o actividades;
- cantidad constante y acotada de consultas al crecer la jerarquía;
- ruta anónima, ausencia de mutación y regresión del POST.

La regresión explícita de Escuela, centros, actividades, inscripciones, permisos y rate limiting completa 111 tests y 783 aserciones. La suite backend completa finaliza con 356 tests y 2708 aserciones sobre MariaDB.

`SCHOOL-PUBLIC-EXPERIENCE-1` añade 312 tests frontend totales en 51 archivos y 21 escenarios E2E totales. Cubre servicio, hook, contrato ligero, edad y 29 de febrero, landing completa/parcial/ausente/cerrada, formulario, errores `409`/`422`/`429`, foco, metadata, lazy loading, Navbar/Home, responsive y 404. El build genera un chunk School diferido de 15,37 kB (4,84 kB gzip), sin incorporar Knowledge ni emitir aviso de tamaño.

La aceptación inicial quedó suspendida al eliminar una limpieza Docker el volumen local de desarrollo compartido por el antiguo proyecto Compose. La auditoría no demostró cambios ni fallos en modelos, servicios, Resources o contratos School; los cambios funcionales de 6C permanecieron íntegros y sin commit durante la remediación.

6C.1 separa desarrollo, backend test y E2E en proyectos, archivos, redes, almacenamiento y bases distintos. La prueba con red centinela acredita que el cleanup E2E no alcanza recursos ajenos. No se levantó, migró, sembró, restauró o reconstruyó la base local.

La regresión final conserva 80 tests y 557 aserciones para `--filter=School`, y 356 tests y 2708 aserciones para la suite backend completa. Frontend mantiene 312 tests y Playwright completa los 21 escenarios. `knowledge:check`, ESLint, build, sintaxis y Pint también son correctos; los hashes de Knowledge permanecen intactos. `SCHOOL-CONTRACT-AUDIT-1` queda cerrado.

## 32. Deuda y criterios de cierre

Deuda futura no bloqueante:

- responsable y canales operativos reales para atender inscripciones;
- revisión humana de la información de privacidad vigente;
- operación programada de conservación y procedimiento de borrado extraordinario;
- reglas de duplicados o reinscripción compleja;
- varios programas públicos;
- subdivisión futura de niveles en grupos;
- plazas, lista de espera, cuotas y pagos;
- excepciones de horarios y calendario;
- asistencia o métricas de actividades;
- API o formulario para centros;
- multimedia, permisos y almacenamiento persistente;
- colección pedagógica propia;
- SEO, canonical y migración de `academy`;
- roles administrativos granulares.

Fase 6A.1 quedó cerrada documentalmente al cumplir:

- la Escuela permanente, menores, adultos, niveles y horarios están definidos;
- los niveles han sustituido la agrupación provisional del MVP;
- ubicación, inscripción, estados, centros y actividades tienen un contrato único;
- no se gestionan plazas, pagos o asistentes nominales de centros;
- relaciones, consistencia, visibilidad, Blade y API están planificados;
- 6B.1–6B.4 y 6C seguían pendientes en ese cierre;
- no se modificó código o `knowledge/` en aquella fase;
- `/escuela`, Navbar, modelos y endpoints continuaban sin implementar;
- Fase 6 permanece abierta;
- `git diff --check` no devuelve errores.

Fase 6C actualiza ese estado: los cuatro modelos del núcleo, `SchoolEnrollment`, `EducationalCenter`, `EducationalActivity`, su administración, el POST, el GET, `/escuela`, Navbar y formulario React existen. Su cierre quedó condicionado a 6C.1 después del incidente Docker. Completadas las guardas, la prueba de no destrucción y toda la regresión backend/frontend/E2E, 6C y la Fase 6 quedan completadas. El contenido pedagógico propio, privacidad operativa aprobada, SEO y migración de `academy` continúan como deuda posterior.

## 33. Seguimiento 7D.2C2A

La privacidad de la inscripción se acepta ahora mediante campos versionados
propios. Para menores, y sólo con flag backend, el mismo POST admite una
solicitud opcional y separada de identidad pública. Elegir `anonymous` u omitir
el objeto no bloquea Escuela. `alias` y `name_initial` requieren declaración de
autoridad y confirmación posterior; ninguna solicitud escolar queda vinculada
automáticamente a `Player`.

Blade enlaza desde el detalle escolar a la cola específica, donde un
administrador verifica nacimiento y jugador. La aprobación escolar sigue
siendo independiente. La lectura pública de Escuela sólo añade configuración
allowlisted del aviso cuando el flag está activo; no expone solicitudes,
estados, personas o evidencia. El contrato completo se encuentra en
`23-verifiable-minor-public-identity.md`.

## 34. Seguimiento 7E — preparación operativa

`SCHOOL-OPERATIONAL-READINESS-1` centraliza en Laravel una apertura
fail-closed con estados públicos `open`, `closed` y `unavailable`. El estado
`open` exige flag de entorno, programa público, presentación y proceso
administrables, ubicación habitual activa, correo operativo privado válido,
nivel público con horario y ubicación activos y el aviso vigente
`NOTICE-SCHOOL-ENROLLMENT` 1.0.0. `SCHOOL_ENROLLMENT_ENABLED` permanece en
`false` por defecto y React no decide la apertura.

El agregado público deja de publicar teléfono o correo. Presentación y proceso
proceden de `SchoolProgram`; los datos operativos reales siguen pendientes de
carga y revisión humana en Blade. La ubicación conocida se limita a `Centro
Polideportivo de Monóvar`, sin inventar pista, punto de encuentro u horario.

La inscripción conserva teléfono y correo obligatorios como datos privados,
incorpora primera capa versionada, honeypot, payload cerrado y limitador con
HMAC. La autorización de identidad de menores sigue siendo opcional e
independiente. Los plazos ya publicados se aplican mediante vencimiento,
holds, anonimización y `school:purge-expired --dry-run`: seis meses para
solicitudes rechazadas o no formalizadas y dos años desde la baja para antiguos
alumnos. El comando no se programa todavía.

La trazabilidad administrativa registra actores y fechas de corrección,
activación, rechazo, baja y hold. Centros y actividades permanecen sólo en
Blade y una actividad conserva únicamente el número previsto de alumnos. La
matriz completa, los gates humanos y los límites de 7F se documentan en
`26-school-operational-readiness.md`.
