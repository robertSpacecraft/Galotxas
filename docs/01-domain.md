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

La Escuela de Galotxas es una sección distinta del Manual. Su metodología, ejercicios y recursos docentes estables podrán proceder de una futura colección de `knowledge/`; sus actividades, fechas, convocatorias y demás información operativa pertenecerán al backend CMS. Esa colección y esos flujos todavía no están implementados.

## Contenido institucional

El área Club agrupará Nosotros, Federarse, Federaciones, Prensa y medios y Contacto. El contenido que deba modificar un administrador tendrá como fuente el backend CMS. Debe resolverse previamente la duplicidad actual entre la página estática de Nosotros y su posible versión CMS.

## Contenido editorial temporal

Noticias, actividades, talleres, jornadas, convocatorias, galerías y documentos administrables requieren persistencia, permisos y publicación segura. No deben hardcodearse en React ni mantenerse como copia paralela en `knowledge/`.

Estas responsabilidades están aprobadas como arquitectura objetivo. Las áreas públicas y rutas futuras no se consideran implementadas por su aparición en este documento. La matriz de fuentes, el flujo editorial y la migración de `/contenidos` se detallan en `10-content-governance.md`.

---

# 14. Funcionalidades previstas

Las siguientes capacidades forman parte del roadmap y no deben considerarse implementadas salvo que se indique expresamente:

- pagos online;
- asignación automática de categorías;
- sugerencias inteligentes de categoría;
- notificaciones automáticas;
- mejoras avanzadas de rankings;
- noticias mediante bloques de contenido;
- gestión de documentos públicos;
- formularios públicos de interés.

---

## Mantenimiento

Cuando cambie el flujo funcional de una competición, este documento deberá actualizarse antes o junto con la implementación correspondiente.
