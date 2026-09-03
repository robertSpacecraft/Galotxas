# Dominio funcional — Galotxas

## Propósito

Este documento describe el funcionamiento funcional del sistema Galotxas.

Su objetivo es explicar cómo se desarrolla una competición desde el punto de vista del negocio, independientemente de su implementación técnica.

Las entidades se definen en `00-glossary.md`. Este documento se centra en los procesos.

---

# 1. Principios del dominio

El dominio deportivo ejecutable de Galotxas se basa en los siguientes principios:

- El backend constituye la fuente de verdad de las reglas ejecutables y los datos funcionales del sistema.
- Las reglas deportivas nunca se implementan en el frontend.
- La organización de las competiciones corresponde al administrador.
- La participación de un jugador siempre está supervisada mediante un flujo administrativo.
- Los rankings únicamente utilizan resultados oficialmente validados.
- La estructura deportiva debe ser consistente y trazable.

---

# 2. Actores

## Usuario

Persona con una cuenta autenticada en la plataforma.

Puede acceder a funcionalidades privadas según su rol.

## Jugador

Perfil deportivo asociado a un usuario.

Es la persona que puede solicitar participar en campeonatos y competir cuando la administración lo autoriza.

## Administrador

Responsable de gestionar la competición.

Entre sus funciones se encuentran:

- crear temporadas;
- crear campeonatos;
- crear categorías;
- revisar solicitudes;
- asignar jugadores;
- crear equipos de dobles;
- generar calendarios;
- validar resultados;
- gestionar incidencias.

---

# 3. Flujo principal

El flujo habitual de una competición es el siguiente:

1. El administrador crea una temporada.
2. Dentro de la temporada crea uno o varios campeonatos.
3. Cada campeonato contiene una o varias categorías.
4. Un usuario se registra en la plataforma.
5. Si desea competir, crea su perfil de jugador.
6. El jugador solicita la inscripción a un campeonato abierto.
7. El administrador aprueba o rechaza la solicitud.
8. Si la solicitud es aprobada, el administrador asigna al jugador a una categoría mediante `CategoryRegistration`.
9. En campeonatos de dobles se crean los equipos correspondientes.
10. Se crean los participantes competitivos (`CategoryEntry`).
11. Se generan las fases de competición.
12. Los partidos enfrentan participantes competitivos.
13. Los resultados se validan.
14. Se actualizan clasificaciones y rankings.

---

# 4. Modalidades deportivas

Actualmente el sistema contempla dos modalidades:

## Singles

La unidad competitiva final es un participante competitivo que referencia a un jugador.

## Doubles

La unidad competitiva final es un participante competitivo que referencia a un equipo.

Aunque ambas modalidades comparten gran parte del flujo, algunos procesos son específicos de dobles.

---

# 5. Organización de la competición

La jerarquía deportiva es:

Temporada
→ Campeonato
→ Categoría
→ Participante competitivo (`CategoryEntry`)
→ Fase (Liga / Copa)
→ Partido

Esta estructura constituye la organización oficial del dominio.

## Ciclo de vida de temporada y oficialidad

`SeasonStatus` admite `planned`, `active`, `finished` y `cancelled`. Las nuevas temporadas nacen `planned`; el default histórico `pending` ya no forma parte de este contrato. El dominio admite cero o una temporada `active`, pero nunca dos o más. Backend y MariaDB son la fuente de verdad de esta invariante: React no la impone y puede conservar el fallback defensivo de presentación introducido en 6.F.2 sin convertirlo en una regla deportiva del cliente.

Los estados `finished` y `cancelled`, tanto en `Season` como en `Championship`, describen el ciclo administrativo u operativo. No certifican un resultado, no crean un snapshot oficial y no exigen que todos los resultados deportivos existan. En particular, una entidad `cancelled` nunca requiere resultado oficial; `finished` tampoco implica ni garantiza oficialidad y no sustituye el dominio de resultados oficiales previsto desde 6.F.3B.

## Visibilidad pública de la competición

Temporadas, campeonatos y categorías disponen de una visibilidad pública declarada mediante `is_public`. Esta propiedad representa la intención administrativa de que la entidad pueda formar parte de la experiencia pública y es independiente de su estado operativo, sus fechas, la apertura de inscripciones, el calendario o los resultados.

Los nuevos registros son privados por defecto. La incorporación inicial del campo conserva como públicos los registros que ya existían para no alterar su accesibilidad previa.

La declaración respeta la jerarquía Temporada → Campeonato → Categoría:

- una temporada puede marcarse pública o privada libremente;
- un campeonato sólo puede marcarse público si su temporada es pública;
- una categoría sólo puede marcarse pública si su campeonato y la temporada del campeonato son públicos.

La visibilidad efectiva es la conjunción de la rama completa: una temporada exige su propio flag; un campeonato, su flag y el de su temporada; una categoría, su flag y los de campeonato y temporada. Partidos, calendarios, clasificaciones, rankings e inicio de inscripciones públicas heredan el requisito de la entidad de la que dependen. Las consultas públicas aplican esta regla sin inferirla del estado operativo.

Ocultar una temporada o un campeonato no modifica automáticamente los flags de sus descendientes. La rama queda efectivamente privada por su padre, pero los valores propios se conservan. Al restaurar el padre reaparecen únicamente los descendientes que continúan declarados públicos.

---

# 6. Gestión de resultados

Los resultados forman parte del dominio deportivo.

Solo los resultados validados tienen efectos oficiales.

El flujo oficial es el siguiente:

1. Un participante de cualquiera de los dos lados envía el primer reporte de un partido `scheduled` y el partido pasa a `submitted`.
2. El lado rival puede confirmar el mismo tanteo. Ambos reportes pasan a `validated`, el partido pasa a `validated` y se fijan el tanteo y el ganador oficiales.
3. Si el lado rival comunica un tanteo diferente, ambos reportes pasan a `conflict` y el partido queda `under_review`, sin tanteo ni ganador oficiales.
4. Un administrador resuelve el conflicto fijando el resultado oficial. Esta operación solo es válida mientras el partido está `under_review`, vuelve a validar las reglas deportivas del tanteo y registra al administrador en `validated_by`.

La resolución administrativa es atómica: bloquea el partido, fija tanteo y ganador, y lo mueve a `validated`. Los dos reportes originales permanecen inmutables en estado `conflict`, incluidos sus autores, comentarios y tanteos, como trazabilidad del desacuerdo. El modelo actual no dispone de un campo adicional para un motivo administrativo.

Cada lado dispone de un único reporte inmutable por partido. En dobles, cualquiera de los miembros representa al lado: una vez que uno ha reportado, su compañero no puede sustituir el reporte ni confirmar el del rival. Tampoco el mismo jugador puede reenviar y sobrescribir su reporte.

Los tanteos son enteros no negativos, no admiten empate y deben respetar el objetivo de la modalidad: 10 juegos en individuales y 12 en dobles. El comentario es opcional y tiene un máximo de 2.000 caracteres.

Los estados `validated`, `cancelled`, `postponed` y `under_review` están cerrados al envío o confirmación de nuevos reportes. El proceso de envío, comparación y cambio de estado se ejecuta atómicamente para no dejar reportes o estados parciales ante un error.

Mi Panel resume la intervención que corresponde al jugador en cada partido. Un partido programado sin reporte de su lado genera la acción de enviar resultado; si solo existe el reporte rival, genera la acción de confirmarlo o revisarlo desde el workflow. Cuando el partido está `under_review`, puede aparecer como aviso informativo, pero nunca como acción editable. Los estados `validated`, `cancelled` y `postponed` no generan acciones pendientes.


## Particularidades de Copa

La Copa utiliza el mismo `GameMatch`, workflow de reportes y resolución de
conflictos que la Liga. No existe una segunda lógica de resultados para las
eliminatorias.

El flujo implementado tiene dos pasos manuales:

1. tras completar operativamente la Liga, se generan las semifinales desde las
   cuatro primeras posiciones: 1.º contra 4.º y 2.º contra 3.º;
2. cuando existen exactamente dos semifinales validadas, con ambos tanteos
   oficiales y sin empate, se generan la Final con sus ganadores y el partido
   por el tercer y cuarto puesto con sus perdedores.

Las rondas quedan identificadas estructuralmente como `phase=cup` y con
`stage=semifinal`, `stage=final` o `stage=third_place`; el nombre visible no es
la autoridad para la experiencia pública. Los partidos nuevos nacen
`scheduled`, sin fecha ni pista, y el administrador completa después ambos
datos obligatorios. La regeneración sustituye únicamente las finales previas y
no crea duplicados.

Un resultado administrativo sólo admite tanteos con estado `submitted` o
`validated`; combinar tanteos con `scheduled`, `postponed`, `cancelled` o
`under_review` se rechaza en validación en vez de descartarlos silenciosamente.
El campeón de Copa es el `winner_entry` oficial de una Final validada, nunca un
cálculo de React a partir del marcador.

La experiencia pública separa las dos fases sin alterar este dominio común:
`/categories/{id}/schedule` presenta exclusivamente las rondas de Liga y
`/categories/{id}/cup` presenta el cuadro de Copa. Una ronda sólo pertenece a
la vista de Copa cuando declara conjuntamente `type=cup`, `phase=cup` y un
`stage` admitido; ni el nombre ni el orden permiten inferir una Copa legada.
## Reprogramación

El backend dispone de un workflow independiente para proponer y confirmar reprogramaciones:

1. un participante propone fecha, hora y pista;
2. el lado rival confirma la propuesta existente en lugar de crear otra;
3. al confirmar, ambas solicitudes quedan `validated` y se actualizan la fecha y la pista del partido;
4. la operación usa transacción y bloqueo del partido;
5. se rechazan colisiones exactas de pista y fecha/hora dentro del mismo campeonato.

La restricción única es por partido y lado. La implementación actual permite que el mismo jugador actualice su propuesta mientras no exista una rival; su compañero de dobles no puede sustituirla. Los partidos `validated`, `cancelled`, `postponed` o `under_review` no admiten reprogramación.

Este contrato existe en la API, pero no tiene interfaz React en el MVP. El endurecimiento adicional del workflow y su rate limiting quedan para una fase posterior.

Entre otros procesos permiten:

- determinar clasificaciones;
- alimentar rankings;
- generar fases posteriores;
- resolver posiciones finales.

Las incidencias pueden requerir intervención administrativa.

---

# 7. Rankings

El sistema mantiene distintos niveles de ranking.

Actualmente existen:

- ranking de categoría;
- ranking de campeonato;
- ranking de temporada;
- ranking histórico.

Cada uno utiliza criterios propios definidos por el dominio.

Los algoritmos concretos pertenecen a la implementación del backend.

## Reparto base de puntos

Todo partido validado distribuye exactamente tres puntos de clasificación. Si quien pierde suma menos de 8 juegos, el reparto es `3-0`; si alcanza 8 o más, el reparto es `2-1`. La regla es simétrica respecto al lado local o visitante y se aplica tanto a individuales como a dobles. Los empates no son resultados deportivos válidos y el cálculo falla explícitamente si recibe uno.

Los rankings de categoría aplican esos puntos a la entrada competitiva. Los rankings agregados de campeonato, temporada e histórico parten del mismo reparto base, lo distribuyen entre jugadores según la contribución ya definida para individuales o roles de dobles y aplican después el multiplicador del nivel de categoría para obtener los puntos ponderados. Mi Panel reutiliza el ranking de categoría y no mantiene una fórmula propia.

El alcance de rondas no cambia con esta regla: categoría agrega únicamente rondas de liga, mientras campeonato, temporada e histórico agregan todos los partidos validados de su ámbito. Los rankings se calculan dinámicamente desde `game_matches`, por lo que los resultados históricos reflejan la regla vigente en la siguiente consulta y no requieren migración ni backfill.

Por tanto, una Copa validada no altera la clasificación de categoría ni Mi
Panel, que reutiliza ese cálculo de Liga, pero sí contribuye a los agregados de
campeonato, temporada e histórico. Semifinal, Final, tercer puesto y título de
campeón no conceden bonus: se conserva exactamente el reparto base, la
contribución individual o de dobles y el multiplicador de nivel ya definidos.

La generación de semifinales de copa consume el orden actual del ranking de categoría. Los cruces ya generados están persistidos y no se vuelven a sembrar automáticamente cuando cambia el cálculo; cualquier regeneración debe ser una decisión operativa explícita.

## Desempates del ranking de categoría

El ranking de categoría conserva este orden:

1. puntos, de mayor a menor;
2. si exactamente dos participantes están empatados a puntos, enfrentamiento directo entre ambos;
3. diferencia de juegos, de mayor a menor;
4. juegos a favor, de mayor a menor;
5. nombre y, si todavía existe igualdad total, `entry_id` ascendente como criterio técnico estable.

Cuando tres o más participantes empatan a puntos, no se comparan enfrentamientos directos por parejas. Se aplican directamente diferencia de juegos, juegos a favor, nombre e identificador. El identificador solo garantiza un resultado reproducible y no concede una ventaja deportiva adicional.

La regla se aplica por igual a entradas individuales y de equipo. Los participantes aprobados sin partidos aparecen con estadísticas numéricas a cero.

## Porcentaje del ranking histórico

`win_rate` representa el porcentaje de victorias sobre partidos jugados y se expresa siempre en escala `0–100`. Por ejemplo, una victoria en dos partidos equivale a `50`, que las interfaces muestran como `50 %`.

El backend evita la división por cero. En el ranking histórico solo se crean filas para jugadores con contribuciones en partidos validados; en rankings de categoría, las entradas sin partidos mantienen estadísticas a cero.

---

# 8. Responsabilidades del administrador

El administrador mantiene la coherencia de la competición.

Entre otras tareas:

- configurar competiciones;
- admitir participantes;
- organizar categorías;
- supervisar resultados;
- resolver incidencias.

---

# 9. Responsabilidades del jugador

El jugador puede:

- gestionar su cuenta;
- crear su perfil deportivo y consultar sus datos;
- solicitar inscripciones;
- consultar calendarios;
- consultar rankings;
- consultar resultados.

La API permite editar parcialmente apodo, mano dominante y notas. El frontend React del MVP no ofrece todavía una edición completa del perfil existente.

No modifica directamente la estructura deportiva.

---

# 10. Reglas generales del dominio

Las siguientes reglas forman parte del comportamiento esperado del sistema:

- un usuario puede existir sin ser jugador;
- todo jugador pertenece a un único usuario;
- la inscripción siempre se solicita al campeonato;
- la asignación administrativa se representa mediante `CategoryRegistration`;
- la unidad competitiva se representa mediante `CategoryEntry`;
- en individuales, `CategoryEntry` referencia a un jugador;
- en dobles, `CategoryEntry` referencia a un equipo;
- los partidos se disputan entre participantes competitivos;
- la asignación a categoría siempre la realiza un administrador;
- la aprobación de la solicitud y el pago son procesos independientes;
- los rankings utilizan únicamente resultados oficiales;
- el frontend representa el dominio, pero no lo determina.

---

# 11. Pistas

Una pista (`Venue`) representa el espacio físico donde se programa un partido.

Reglas actuales:

- el administrador puede listar, crear y editar pistas desde el panel Blade;
- el nombre es obligatorio y no puede repetirse a través de los formularios administrativos;
- la ubicación y la descripción son opcionales;
- el modelo actual no dispone de estado activo, por lo que VENUE-1 no incorpora activación ni desactivación;
- una pista asociada a un partido o a una solicitud de reprogramación no puede eliminarse desde el panel, preservando el calendario y su trazabilidad;
- una pista sin relaciones puede eliminarse;
- `DefaultVenueSeeder` crea por nombre el conjunto mínimo `Pista 1` a `Pista 5` sin sobrescribir registros existentes, pero el generador no depende de ese seeder ni de esos nombres;
- la generación de liga utiliza todas las pistas existentes, ordenadas de forma estable por ID;
- si no existe ninguna pista, la generación se detiene antes de crear jornadas o partidos y solicita al administrador configurar al menos una;
- cada pista conserva los siete huecos semanales existentes: viernes a las 17:00, 18:00, 19:00 y 20:00, y sábado a las 17:30, 18:00 y 19:00;
- una pista puede reutilizarse dentro de la jornada únicamente en horas distintas;
- si los cruces de una jornada superan los huecos disponibles, la generación falla sin dejar datos parciales.

La modalidad, el nivel o el nombre de una pista no restringen su uso automático mientras el esquema no disponga de una configuración explícita de elegibilidad.

La garantía de no colisión se aplica a la categoría generada. La coordinación de ocupación entre calendarios de categorías distintas conserva el comportamiento heredado y requiere un bloque futuro de disponibilidad compartida.

---

# 12. Contenidos públicos CMS

El sistema incorpora una primera base técnica para páginas públicas gestionables mediante CMS.

Conceptos:

- Página CMS: contenido público identificado por `slug`, con título, estado y metadatos SEO mínimos.
- Estado de página: una página puede estar en borrador (`draft`) o publicada (`published`).
- Bloque CMS: unidad estructurada de contenido asociada a una página.

Reglas:

- Solo las páginas publicadas pueden leerse desde la API pública.
- Las páginas en borrador no son visibles públicamente.
- Una página `published` sin `published_at` se publica inmediatamente y conserva la fecha nula.
- Una página `published` con fecha futura queda programada y todavía no es visible públicamente.
- Los borradores pueden estar vacíos, pero una página necesita al menos un bloque válido para pasar a `published`.
- El último bloque de una página con estado `published` no puede eliminarse hasta que la página vuelva a borrador.
- Los bloques se devuelven ordenados por el backend.
- El contenido de los bloques se almacena como datos estructurados, no como HTML libre.
- Los bloques pertenecen siempre a una única página CMS.
- El panel administrativo permite crear, editar, ordenar manualmente y eliminar bloques.

Tipos iniciales de bloque:

- `heading`: `text` obligatorio y `level` controlado entre 1 y 6;
- `text`: `text` obligatorio;
- `list`: `items` como lista de textos;
- `image`: `url` obligatoria y `alt` opcional;
- `gallery`: `urls` como lista simple de URLs o rutas internas;
- `button`: `label` y `url` obligatorios;
- `document_link`: `label` y `url` obligatorios.

El panel administrativo crea cada página CMS como borrador con título, `slug` y metadatos SEO mínimos. Tras añadir contenido, la edición permite elegir el estado y `published_at`: una fecha vacía publica inmediatamente y una futura programa la publicación según `config('app.timezone')`. También permite gestionar sus bloques estructurados. La subida de documentos o imágenes todavía no forma parte de esta base técnica; los bloques de imagen, galería y documento trabajan con URLs o rutas ya existentes.

---

# 13. Ámbitos de contenido y responsabilidades públicas

La arquitectura pública aprobada distingue ámbitos que se relacionan con el dominio, pero no comparten necesariamente su persistencia ni sus responsables de edición.

## Dominio competitivo

Incluye temporadas, campeonatos, categorías, participantes, inscripciones, partidos, calendarios, resultados y rankings. Laravel decide sus reglas y React consume sus contratos API. Esta es la responsabilidad funcional principal descrita en las secciones anteriores.

## Conocimiento normativo

El Reglamento es la formulación editorial canónica de las reglas y reside en `knowledge/reglamento/`. Sirve de referencia al dominio ejecutable, pero un cambio editorial con impacto funcional exige revisar el backend y sus pruebas; el frontend nunca interpreta por sí mismo una regla para convertirla en comportamiento.

## Contenido conceptual

Los Conceptos reúnen vocabulario y definiciones canónicas en `knowledge/conceptos/`. No son registros CMS ni reglas deportivas calculadas por React.

## Contenido pedagógico

El Manual será un consumidor público del conocimiento estable de `knowledge/`. Su función será organizar y explicar Reglamento y Conceptos, no duplicarlos. La landing Aprende a jugar será una puerta de entrada divulgativa y no debe confundirse con el Manual.

La Escuela de Galotxas es una sección distinta del Manual. Su metodología, ejercicios y recursos docentes estables podrán proceder de una futura colección de `knowledge/`; los datos operativos y personales pertenecen a Laravel y se administran desde Blade cuando su bloque está implementado. El CMS genérico sólo podrá conservar piezas simples no estructuradas. El contrato funcional y el estado de cada bloque se documentan en `12-school-of-galotxas.md`.

Ese dominio tendrá dos subdominios independientes:

1. **Escuela permanente:** `SchoolProgram`, niveles `SchoolLevel`, horarios semanales, ubicaciones escolares y `SchoolEnrollment`. Admite participantes menores y adultos; la solicitud pública no exige cuenta, comienza pendiente y requiere aprobación. Un menor necesita representante, mientras teléfono y correo son obligatorios en todos los casos.
2. **Centros y actividades educativas:** `EducationalCenter` registra cada centro una sola vez y `EducationalActivity` sus actividades planificadas, completadas o canceladas. Sólo se conserva el número previsto de alumnos, nunca asistentes nominales.

El participante individual de la Escuela no es un `Player`, un centro educativo no es un usuario o equipo y una actividad con un centro no genera inscripciones individuales. `SchoolLevel` tampoco reutiliza las categorías de campeonatos. La inscripción deportiva y la escolar comparten únicamente patrones técnicos.

Desde 6B.1 existe el núcleo operativo de la Escuela permanente:

- `SchoolProgram` conserva configuración, visibilidad, apertura declarada,
  presentación pública, explicación del proceso, contacto operativo privado,
  ubicación habitual y orden. Los registros nacen privados y cerrados. MariaDB
  garantiza como máximo un programa público mediante una ranura generada
  nullable e índice único; el servicio de persistencia añade transacción,
  bloqueo y feedback administrativo.
- `SchoolLevel` pertenece a un programa, admite edades mínima y máxima opcionales, distingue activación y visibilidad y no utiliza slug.
- `SchoolLocation` pertenece exclusivamente al dominio escolar, exige nombre y localidad y admite dirección, activación, orden y notas administrativas privadas. No reutiliza `Venue`, que continúa reservado a pistas competitivas.
- `SchoolSchedule` pertenece a un nivel y una ubicación, usa día ISO 1–7, exige hora inicial anterior a la final y bloquea únicamente duplicados exactos. Los solapamientos parciales continúan permitidos.

La visibilidad efectiva está centralizada en backend: un programa exige `is_public`; un nivel exige programa público y sus propios flags activo y público; un horario exige además horario y ubicación activos. Ocultar o desactivar un padre no modifica flags hijos.

Desde 6B.2, `SchoolEnrollment` registra solicitudes y participantes sin crear `Player`. Pertenece siempre a un programa, puede tener nivel y cuenta opcionales, y conserva nombre, nacimiento, teléfono, correo, representante condicional, estado, fechas del ciclo y notas privadas. El estado nace `pending` y sólo admite `pending → active`, `pending → rejected` y `active → withdrawn`; una baja conserva `activated_at` y una reinscripción futura crea otro registro.

La minoría de edad se calcula de forma determinista con nacimiento y `requested_at`, nunca con la fecha de consulta ni mediante edad persistida. Quien cumple 18 años el día de la solicitud ya se considera adulto; para nacimientos del 29 de febrero se usa la fecha equivalente sin desbordamiento. Un menor exige nombre y relación del representante. En adultos esos campos se normalizan a `null`; teléfono y correo siguen siendo obligatorios.

Las claves foráneas usan borrado restrictivo para programas y niveles. Una restricción compuesta garantiza que el nivel asignado pertenezca al programa; eliminar una cuenta deja `user_id = null` y conserva el histórico. El flujo administrativo no ofrece eliminación normal de inscripciones y los servicios transaccionales asignan estados y fechas.

Desde 6B.3, el segundo subdominio dispone de persistencia y administración propias. `EducationalCenter` exige nombre y localidad, admite contacto opcional, nace inactivo y conserva múltiples `EducationalActivity`. Los centros homónimos son válidos y su orden administrativo es localidad, nombre e ID.

`EducationalActivity` pertenece siempre a un centro y opcionalmente a `SchoolLocation`, usa nombre libre, fecha obligatoria, horas emparejadas, alumnado previsto positivo nullable y el enum `planned`, `completed` o `cancelled`. Toda alta nace planificada. Sólo se admite `planned → completed` o `planned → cancelled`; completar exige alumnado previsto positivo y no existe reactivación. Un centro o ubicación inactivos no admiten asociaciones nuevas, pero las relaciones históricas continúan editables si no se cambian.

Los borrados son conservadores: un centro o ubicación con actividades no se elimina; sólo una actividad planificada creada por error puede borrarse. Una actividad completada o cancelada conserva su histórico. Este subdominio no registra alumnos nominales, no crea `SchoolEnrollment` y no tiene API pública o administrativa.

La lectura pública `GET /api/v1/school` resuelve el único programa público y
proyecta mediante Resources cerrados su contenido administrado, estado
`open|closed|unavailable`, aviso vigente, ubicación habitual activa, niveles
activos/públicos y horarios efectivos. Teléfono y correo permanecen privados.
Si no existe programa público, responde `200` con `data: null`.

`POST /api/v1/school/enrollments` exige disponibilidad centralizada y la flag
`SCHOOL_ENROLLMENT_ENABLED`, cerrada por defecto. Admite nivel opcional, toma
la cuenta exclusivamente de una sesión Sanctum opcional y crea una solicitud
pendiente con aviso versionado, retención inicial de seis meses, rate limit y
honeypot. Desde 6C, `/escuela` consume la lectura y escritura públicas: React
adapta el formulario, pero el backend decide disponibilidad y edad. No existe
seguimiento por ID ni API administrativa.

Desde 7D.2C2A, la inscripción conserva por separado la versión de Privacidad
aceptada y puede originar una `PublicIdentityAuthorization` opcional para el
único alcance `public_competition_identity`. La autorización usa los estados
`pending`, `approved`, `denied`, `revoked` y `expired`, y los modos `alias`,
`name_initial` y `anonymous`. Nace ligada a la inscripción, no al jugador: la
vinculación sólo se completa administrativamente cuando `Player.birth_date`
coincide exactamente. Confirmación del representante, revisión y, entre 14 y
17 años, conformidad registrada son requisitos acumulativos. Sin cualquiera de
ellos, con flag desactivado o tras revocación, la proyección es `Participante`.
Al alcanzar 18 años deja de aplicarse la autorización del representante y rige
la política adulta vigente.

Desde 7E, `SchoolEnrollment` registra actores y fechas de corrección,
activación, rechazo y baja. Pendientes no formalizadas y rechazadas vencen a
los seis meses; una inscripción activa no vence y una baja vence a los dos
años. Holds motivados suspenden la anonimización. `school:purge-expired`
admite `--dry-run`, es idempotente y elimina PII sin borrar el histórico de
programa, nivel, estado, aviso o transiciones. No está programado en producción.

Los únicos datos sembrados de Escuela se limitan al escenario aislado `E2ESmokeSeeder`, protegido por `APP_ENV=e2e` y la base desechable `galotxas_e2e`; no son datos de desarrollo o producción.

## Contenido institucional

El área Club agrupará Nosotros, Federarse, Federaciones, Prensa y medios y Contacto. El contenido que deba modificar un administrador tendrá como fuente el backend CMS. Debe resolverse previamente la duplicidad actual entre la página estática de Nosotros y su posible versión CMS.

Desde 7C.1, el contenido de Contacto y la recepción de mensajes se mantienen en
ámbitos separados. Títulos, dirección, canales, enlaces y copy pertenecen a
`CmsPage`/`CmsBlock`; una solicitud enviada pertenece al dominio funcional
`ContactRequest` y no es contenido editorial. Conserva nombre, correo, asunto,
mensaje, estado `new|read|closed`, aviso/versión, instante de consentimiento,
estado de notificación y un HMAC temporal de la IP. No conserva IP en claro,
teléfono, archivos, DNI, user agent o cookies.

La solicitud se persiste antes de intentar una notificación auxiliar; por ello
la persistencia acredita recepción. El formulario nace desactivado y aplica
gates fail-closed. Cerrar inicia 12 meses de retención. Blade permite leer,
cerrar, reintentar, colocar un hold y anonimizar al vencer, sin editar el
original ni falsificar consentimiento. El HMAC es purgable a 30 días. Esta
capacidad no crea contenido editorial ni activa producción.

## Contenido editorial temporal

Noticias, actividades, talleres, jornadas, convocatorias, galerías y documentos administrables requieren persistencia, permisos y publicación segura. No deben hardcodearse en React ni mantenerse como copia paralela en `knowledge/`.

Desde 7F.2E, Noticias es la primera capacidad de este grupo implementada de
extremo a extremo. `NewsArticle` es un agregado editorial cronológico separado
de `CmsPage`/`CmsBlock`: persiste título, slug, extracto manual, cuerpo de texto
plano, imagen, metadata SEO, estado y fecha de publicación. Los únicos estados
persistidos son `draft` y `published`; “Programada” se deriva de una fecha
futura y la consulta pública exige publicación efectiva. La primera asignación
de `published_at` reserva de forma permanente el slug, y el soft delete conserva
esa reserva.

La imagen es obligatoria al publicar y requiere alt, procedencia privada y una
confirmación administrativa de derechos preexistentes. Esa confirmación no
crea consentimiento, no reutiliza el avatar privado ni amplía la autorización
de identidad deportiva. Actividades, galerías y documentos siguen siendo
arquitectura objetivo. La matriz de fuentes y el flujo editorial se detallan
en `10-content-governance.md`.

---

# 14. Funcionalidades previstas

Las siguientes capacidades forman parte del roadmap y no deben considerarse implementadas salvo que se indique expresamente:

- pagos online;
- asignación automática de categorías;
- sugerencias inteligentes de categoría;
- notificaciones automáticas;
- mejoras avanzadas de rankings;
- gestión de documentos públicos;
- activación productiva del formulario institucional de contacto.

---

## Mantenimiento

Cuando cambie el flujo funcional de una competición, este documento deberá actualizarse antes o junto con la implementación correspondiente.
