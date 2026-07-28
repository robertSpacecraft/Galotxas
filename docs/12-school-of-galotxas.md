# Contrato funcional de Escuela de Galotxas

## 1. Propósito

Este documento registra la auditoría de Fase 6A y el cierre funcional aprobado en Fase 6A.1. Define el dominio que deberá implementar 6B y la experiencia pública que deberá implementar 6C sin presentar ninguna de esas capacidades como existente.

Fase 6A.1 es exclusivamente documental. No crea modelos, migraciones, enums, factories, seeders, controladores, Form Requests, Services, Resources, rutas, API, pantallas Blade, componentes React, formularios ni contenido en `knowledge/`.

## 2. Estado actual

La Escuela de Galotxas continúa sin implementación funcional:

- no existen entidades `School*`, `EducationalCenter` o `EducationalActivity`;
- no existe administración Blade específica;
- no existen `GET /api/v1/school` o `POST /api/v1/school/enrollments`;
- React no registra `/escuela` y el Navbar no la enlaza;
- `knowledge/` no contiene una colección pedagógica de Escuela;
- el CMS genérico conserva el slug legado `academy`, que no equivale a este dominio;
- las inscripciones deportivas exigen cuenta y perfil `Player`, por lo que no representan el flujo escolar.

Las decisiones de 6A.1 cierran el contrato de implementación, no cambian este estado.

## 3. Auditoría de capacidades reutilizables

| Área actual | Capacidad reutilizable | Límite |
|---|---|---|
| Laravel y MariaDB | Modelos, relaciones, Services, Form Requests y transacciones | No existen entidades escolares |
| Blade | Interfaz administrativa oficial y middleware de administrador activo | No existen pantallas de Escuela |
| API | Resources por contexto y envelopes existentes | No existen contratos escolares |
| Rate limiting | Mecanismo ya usado en auth y resultados | La inscripción necesitará una clave y límites propios |
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

Decisión cerrada: 6B creará `SchoolLocation`, compartida por los horarios de la Escuela permanente y las actividades con centros. No se modificará ni duplicará un mismo lugar dentro de los dos subdominios escolares. `Venue` continuará reservado al contrato competitivo actual.

Campos mínimos de `SchoolLocation`:

- `name`;
- `location`, texto público de dirección o localidad;
- `description`, nullable y publicable;
- `is_active`;
- timestamps.

No necesita `is_public`: sólo se descubrirá públicamente cuando una programación efectiva la referencie. Desactivarla impedirá nuevas asociaciones y excluirá los horarios relacionados de la lectura pública, sin borrar el historial administrativo.

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
- estados planificada, realizada y cancelada;
- información exclusivamente administrativa en el MVP.

No se registran nominalmente participantes de las actividades con centros y no se crean inscripciones escolares por cada asistente.

## 7. Escuela permanente

La Escuela es una actividad permanente. El MVP no la divide en cursos, temporadas, convocatorias académicas o programas temporales sucesivos.

`SchoolProgram` representa su configuración operativa. El modelo admitirá varios registros para no imponer un singleton rígido, pero el MVP administrará un solo programa permanente y permitirá como máximo un programa público. La API expondrá únicamente ese programa público.

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

El canal público general es distinto y todavía no está confirmado. `SchoolProgram` tendrá `contact_phone` y `contact_email` nullable, editables desde Blade. La API omitirá cada campo ausente y la landing omitirá el bloque completo si no existe ningún canal aprobado; esa ausencia no bloqueará el resto de la experiencia.

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

El nivel se creará mediante el flujo administrativo cuando 6B exista.

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
- no crear sesiones individuales, reglas RFC, festivos, excepciones o recuperaciones.

No se define todavía una regla de solapamiento: puede haber niveles distintos simultáneos y no existe una restricción humana aprobada sobre recursos compartidos.

## 14. Apertura y cierre

`SchoolProgram.enrollments_open` controla la recepción de solicitudes.

Cuando sea `false`:

- la información pública puede seguir visible;
- `POST /api/v1/school/enrollments` rechazará nuevas solicitudes;
- React mostrará el estado cerrado y no ofrecerá un formulario operativo;
- las solicitudes existentes y su administración no cambian.

La apertura pública efectiva requiere simultáneamente:

- programa público;
- `enrollments_open = true`.

Un programa privado puede conservar internamente `enrollments_open = true`, pero la API lo tratará como cerrado/no disponible y el POST rechazará la solicitud. Esta conservación evita modificar configuración al ocultar temporalmente el programa.

## 15. Solicitud pública

La inscripción pública forma parte de las fases 6B.2 y 6C:

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
- si existe una sesión autenticada válida, el controlador podrá asignar el usuario actual;
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

`EducationalCenter` representa un centro reutilizable en múltiples actividades.

Contrato mínimo:

- `name` y `locality` obligatorios;
- `contact_name`, `contact_phone` y `contact_email` nullable;
- `is_active`;
- `admin_notes` nullable;
- timestamps.

No será público en el MVP. No se añade CIF, código de centro, dirección completa, datos fiscales o adjuntos.

No se impone unicidad global ni compuesta en base de datos. Dos centros pueden compartir nombre, incluso en una misma localidad; Blade deberá mostrar contexto suficiente y podrá advertir de coincidencias sin bloquearlas.

## 20. Actividades educativas

`EducationalActivity` pertenece a un centro y registra:

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
| `completed` | Realizada |
| `cancelled` | Cancelada |

`expected_students` será nullable mientras la actividad esté planificada y deberá ser un entero positivo al marcarla como realizada. Cero no es un valor válido: una actividad sin participación debe permanecer planificada, cancelarse o corregirse. Una cancelada puede conservar el último valor previsto.

Si hay horas, `starts_at` será anterior a `ends_at`. La ubicación debe existir y estar activa al asignarla. Desactivar después el centro o la ubicación no borra la actividad histórica.

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
| `SchoolProgram` | `name`, `is_public`, `enrollments_open`, `default_location_id` nullable, `contact_phone` nullable, `contact_email` nullable, `sort_order`, timestamps |
| `SchoolLevel` | `school_program_id`, `name`, `minimum_age` nullable, `maximum_age` nullable, `is_active`, `is_public`, `sort_order`, timestamps |
| `SchoolSchedule` | `school_level_id`, `day_of_week`, `starts_at`, `ends_at`, `school_location_id`, `is_active`, `sort_order`, timestamps |
| `SchoolLocation` | `name`, `location`, `description` nullable, `is_active`, timestamps |
| `SchoolEnrollment` | `school_program_id`, `school_level_id` nullable, `user_id` nullable, `participant_name`, `participant_birth_date`, `contact_phone`, `contact_email`, `guardian_name` nullable condicional, `guardian_relationship` nullable condicional, `status`, `requested_at`, `activated_at` nullable, `rejected_at` nullable, `withdrawn_at` nullable, `admin_notes` nullable, timestamps |
| `EducationalCenter` | `name`, `locality`, `contact_name` nullable, `contact_phone` nullable, `contact_email` nullable, `is_active`, `admin_notes` nullable, timestamps |
| `EducationalActivity` | `educational_center_id`, `name`, `activity_date`, `starts_at` nullable, `ends_at` nullable, `expected_students` nullable condicional, `school_location_id` nullable, `status`, `admin_notes` nullable, timestamps |

Defaults seguros:

- programa privado y con inscripciones cerradas;
- nivel inactivo y privado;
- horario inactivo;
- ubicación inactiva;
- inscripción pendiente;
- centro y actividad con estados explícitos definidos por el flujo administrativo.

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
- una actividad histórica conserva su centro y ubicación aunque se desactiven.

## 24. Visibilidad efectiva

Política pública:

- programa visible: `is_public = true`;
- nivel visible: programa visible, `is_active = true` e `is_public = true`;
- horario visible: nivel efectivo, `is_active = true` y ubicación activa;
- ubicación: se expone únicamente dentro de un horario efectivo;
- inscripciones abiertas: programa visible y `enrollments_open = true`.

Casos:

| Estado declarado | Resultado público |
|---|---|
| Programa privado + nivel público | No se expone programa, nivel ni horarios |
| Nivel privado + horario activo | No se expone nivel ni horario |
| Ubicación inactiva | Se excluyen los horarios que la usan |
| Programa privado + inscripciones abiertas | No hay formulario efectivo y el POST rechaza |
| Programa público + inscripciones cerradas | Información visible, formulario cerrado |

No se copian cascadas de Competición. Ocultar o desactivar un padre no reescribe flags hijos; la consulta pública aplica la conjunción efectiva. Blade impedirá activar/publicar un hijo bajo un padre no válido, pero permitirá ocultar el padre conservando configuración.

`EducationalCenter` y `EducationalActivity` son administrativos. Su estado operativo nunca implica publicación y no necesitan `is_public` en el MVP.

## 25. Administración Blade

### Programa

- listado y edición del único programa MVP;
- nombre, visibilidad, apertura/cierre, ubicación habitual, contacto público y orden;
- impedir un segundo programa público;
- mostrar por separado visibilidad y apertura efectiva.

### Niveles

- listado por programa, alta, edición, activación, publicación y orden;
- edades nullable con `minimum_age <= maximum_age`;
- borrado bloqueado cuando tenga horarios o inscripciones;
- sin slug.

### Horarios

- listado por nivel, alta, edición, activación y orden;
- día ISO, horas y ubicación activa;
- sin sesiones ni excepciones.

### Ubicaciones

- listado, alta, edición, activación y uso;
- mostrar dependencias con programas, horarios y actividades;
- borrado bloqueado mientras tenga relaciones.

### Inscripciones

- filtros para pendientes, activas, rechazadas y bajas;
- detalle privado;
- aprobar y asignar/reasignar nivel;
- rechazar;
- dar de baja;
- observaciones privadas;
- fechas de transición;
- sin eliminación física normal.

### Centros

- listado, alta, edición, activación y detalle;
- contacto y notas privadas;
- historial de actividades;
- coincidencias de nombre informativas, no restricción de unicidad.

### Actividades

- listado, alta, edición, estado, fecha, horas, alumnado previsto, ubicación y observaciones;
- nombre libre;
- sin dashboard analítico.

Todos los flujos usarán administrador activo, Form Requests, `validated()`, persistencia explícita, feedback y tests de autorización. No se añade API administrativa mientras Blade sea el único consumidor.

## 26. API pública

### Lectura

`GET /api/v1/school`

Entregará:

- programa público;
- niveles activos y públicos;
- horarios efectivos;
- ubicaciones asociadas;
- estado efectivo de inscripción;
- contacto público cuando exista.

No entregará flags administrativos, notas, alumnado, solicitudes, fechas de nacimiento, contactos privados, centros o actividades. Si no existe programa público, responderá `404`; React lo tratará como ausencia de configuración operativa y podrá conservar el enlace al Manual.

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

Respuestas previstas:

- `422` para validación;
- `404` si no existe programa público;
- `409` si las inscripciones están cerradas;
- `429` al superar el límite.

No existirán listado público, consulta por ID, estado individual, endpoints de alumnos, centros o actividades. Blade operará con rutas web, no con API administrativa.

## 27. Experiencia pública

Fase 6C implementará `/escuela` con:

1. H1 “Escuela de Galotxas”;
2. enlace al Manual;
3. niveles públicos;
4. horarios semanales y ubicaciones;
5. inscripción abierta o cerrada;
6. formulario público cuando esté abierta;
7. contacto general sólo si está configurado;
8. estados remotos y confirmación opaca.

La landing reutilizará `PublicLanding` y `PageMetadata`, y “Escuela de Galotxas” ocupará la cuarta posición del Navbar. No publicará centros, actividades o alumnos.

Estados:

| Situación | Comportamiento |
|---|---|
| Loading | Estado anunciado del bloque operativo |
| Error de lectura | Mensaje recuperable y reintento; conservar enlace al Manual |
| `404` de lectura | Estado sin configuración pública, no datos simulados |
| Datos parciales | Mostrar niveles/horarios válidos y omitir bloques ausentes |
| Inscripción cerrada | Mensaje claro, sin formulario enviable |
| Error de envío | Mantener valores no sensibles y errores por campo |
| Éxito | Confirmación genérica sin ID o consulta de estado |
| Sin contacto público | Omitir bloque sin afectar el resto |

React no calculará mayoría de edad como decisión final, no confiará en ocultar el formulario y no persistirá contenido editorial.

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

No se redactan textos legales, política de privacidad, consentimiento o autorización de imagen. El formulario deberá incorporar las aceptaciones necesarias sólo cuando sus textos estén aprobados.

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

Fase 6B continúa pendiente y se divide así:

### 6B.1 — Núcleo operativo

- migraciones, modelos, relaciones, enums y factories;
- `SchoolProgram`, `SchoolLevel`, `SchoolSchedule` y `SchoolLocation`;
- decisión ya cerrada de no reutilizar `Venue`;
- administración Blade;
- activación, visibilidad efectiva y apertura;
- tests y documentación.

### 6B.2 — Inscripciones y participantes

- `SchoolEnrollment` y enum de estado;
- `POST /api/v1/school/enrollments`;
- programa abierto y nivel opcional;
- mayoría de edad, representante condicional, teléfono y correo;
- asociación opcional con usuario;
- aprobación, rechazo, baja y reasignación;
- rate limiting, administración Blade, tests y documentación.

El POST pertenece a 6B.2 porque es parte del mismo flujo transaccional. React lo consumirá en 6C.

### 6B.3 — Centros y actividades

- `EducationalCenter` y `EducationalActivity`;
- administración Blade;
- enums, fechas, horas, alumnado previsto y ubicación;
- validaciones, estados, tests y documentación;
- sin API pública.

### 6B.4 — API pública de lectura

- `GET /api/v1/school`;
- Resources públicos;
- programa principal;
- niveles, horarios, ubicaciones, inscripción y contacto;
- visibilidad efectiva, contratos, tests y documentación.

No se inicia ningún subbloque con este documento.

## 31. Plan 6C y testing

6C permanece pendiente:

- servicio y hook;
- landing `/escuela`;
- niveles, horarios y ubicación;
- estado de inscripción;
- formulario y validación básica;
- estados de lectura y envío;
- Navbar;
- accesibilidad, responsive y E2E;
- enlace al Manual;
- revisión posterior de `academy`;
- cierre de Fase 6.

`SCHOOL-CONTRACT-AUDIT-1` prevé para 6B/6C:

- defaults, casts, relaciones y consistencia programa/nivel;
- adulto y menor calculados en la fecha de solicitud;
- representante condicional;
- teléfono y correo obligatorios;
- solicitud anónima y asociación autenticada opcional sin sobrescritura;
- nivel omitido, válido, privado, inactivo o de otro programa;
- día ISO, cronología, ubicación activa y orden;
- programa privado, niveles privados y ubicación inactiva;
- apertura/cierre protegidos en backend;
- transiciones, fechas, rechazo de transiciones inválidas y ausencia de borrado;
- centros con nombres repetidos;
- actividad con nombre libre, estados, horas y `expected_students`;
- ausencia de asistentes nominales, plazas, pagos y API de centros;
- permisos Blade;
- Resources sin datos personales;
- `GET` y `POST`, rate limiting, no enumeración y contratos de error;
- React, formulario, estados remotos, teclado, foco, responsive, Navbar y E2E.

Este plan no representa tests implementados o ejecutados en 6A.1.

## 32. Deuda y criterios de cierre

Deuda futura no bloqueante:

- canal público de contacto definitivo;
- textos aprobados de privacidad y aceptaciones;
- política de conservación y borrado extraordinario;
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

Fase 6A.1 queda cerrada documentalmente cuando:

- la Escuela permanente, menores, adultos, niveles y horarios están definidos;
- los niveles han sustituido la agrupación provisional del MVP;
- ubicación, inscripción, estados, centros y actividades tienen un contrato único;
- no se gestionan plazas, pagos o asistentes nominales de centros;
- relaciones, consistencia, visibilidad, Blade y API están planificados;
- 6B.1–6B.4 y 6C siguen pendientes;
- no se ha modificado código o `knowledge/`;
- `/escuela`, Navbar, modelos y endpoints continúan sin implementar;
- Fase 6 permanece abierta;
- `git diff --check` no devuelve errores.
