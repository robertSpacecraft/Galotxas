# Panel administrativo — Galotxas

## Propósito

Este documento describe el funcionamiento del panel administrativo Blade de Galotxas.

El panel administrativo es la herramienta oficial para gestionar la competición y el contenido administrable desde el backend Laravel.

Describe procesos funcionales, no detalles visuales. Las reglas de estilo se encuentran en `backend/BACKEND_STYLE.md`.

## Tipos de administración

El panel distingue dos responsabilidades:

- **Administración deportiva:** mantiene usuarios, jugadores y datos o procesos del dominio competitivo. Las reglas reutilizables pertenecen a Services y no a las vistas.
- **Administración editorial:** mantiene contenido que debe cambiar sin editar código ni desplegar React. El backend controla permisos, estados, slugs, persistencia y visibilidad pública.

Blade es la interfaz administrativa oficial para ambas responsabilidades. No se prevé un panel administrativo React paralelo.

## Criterio para incorporar una sección

Una sección necesita gestión Blade cuando una persona administradora deba editarla con frecuencia, gestionar borradores o publicación, cargar recursos, mantener información temporal o actualizarla sin Git ni despliegue.

La incorporación futura debe cubrir de forma coordinada Form Requests, autorización, estados de publicación, slugs, persistencia, Resources, filtro público, API, React, tests y documentación según corresponda. El flujo vertical completo se define en `10-content-governance.md`.

No todo contenido público pertenece al panel: Reglamento, Conceptos, Manual y contenido pedagógico estable tienen su fuente editorial en `knowledge/`. Tampoco se debe crear un endpoint solo para suplir una mala separación de responsabilidades en React.

## Estado y auditoría

Las pantallas descritas a continuación son capacidades actuales documentadas. La Fase 1 verificó las rutas, permisos, estados, API, contenido y pruebas del CMS genérico; la Fase 2A ha endurecido su invariancia editorial. La adecuación de ese CMS a cada futura área específica sigue necesitando un bloque propio antes de ampliarlo.

Noticias, actividades de la Escuela, carga persistente de archivos, formularios públicos y nuevas pantallas editoriales son capacidades futuras; no se consideran implementadas.

## Vistas Principales

### Dashboard

El panel de inicio actúa como el centro de mando (hub) para la gestión del sistema.

- **Resumen operativo**: Muestra el número total de solicitudes pendientes de revisión y de solicitudes aprobadas pendientes de categoría.
- **Acceso a gestión**: Incluye un enlace claro a la sección específica de solicitudes e inscripciones.
- No contiene formularios de aprobación, rechazo o asignación de categoría; esas acciones se concentran en la pantalla operativa correspondiente.
- Contiene accesos rápidos a la creación y listado de Temporadas, Campeonatos, Usuarios y Jugadores.

### Solicitudes e inscripciones

La ruta `/admin/registration-requests` centraliza la gestión operativa del flujo de inscripción.

- Muestra las últimas 20 solicitudes pendientes y permite aprobarlas o rechazarlas mediante las rutas existentes.
- Muestra las últimas 20 solicitudes aprobadas cuyo jugador todavía no tiene categoría en el campeonato.
- Permite seleccionar una categoría del campeonato y asignar al jugador reutilizando `CategoryRegistrationController@store`.
- Preselecciona la categoría sugerida cuando pertenece al campeonato.
- Si el campeonato no tiene categorías, ofrece enlaces para crearlas o gestionarlas.

### Páginas CMS

La ruta `/admin/cms/pages` centraliza la gestión básica de páginas públicas CMS.

- Muestra el listado de páginas con título, `slug`, estado, fecha de publicación y número de bloques.
- Crea cada página como borrador con título, `slug` y metadatos SEO mínimos.
- El flujo editorial es: crear el borrador, añadir al menos un bloque válido y editar la página para publicarla.
- La edición permite modificar título, `slug`, estado, `published_at` y metadatos SEO.
- Una página vacía puede permanecer en borrador, pero no puede pasar a `published`.
- `published` con `published_at = null` significa publicación inmediata y conserva el valor nulo.
- `published` con una fecha futura significa publicación programada y no es todavía visible por API.
- El panel presenta los estados derivados Borrador, Programada y Publicada sin añadir un tercer estado a la base de datos.
- El campo `datetime-local` se interpreta según `config('app.timezone')`, que se muestra en el formulario; no usa una zona horaria hardcodeada.
- Desde el detalle de una página permite gestionar sus bloques CMS.
- Las páginas institucionales recomendadas para el MVP usan los slugs `prensa-media`, `nosotros`, `federaciones`, `academy`, `documentos` y `federarse`.
- En entornos de desarrollo puede crearse una base mínima no destructiva con `php artisan db:seed --class=InstitutionalCmsPageSeeder`.

Estos slugs y sus enlaces pertenecen a la estructura pública legada. En particular, `academy` no define el nombre público futuro de Escuela de Galotxas. La auditoría decidirá el destino de cada página sin borrar o migrar contenido en esta fase.

### Bloques CMS

Las rutas `/admin/cms/pages/{cmsPage}/blocks/*` centralizan la gestión básica de bloques de una página CMS.

- Los bloques se muestran dentro del detalle de la página ordenados por `sort_order` e `id`.
- Permite crear, editar y eliminar bloques.
- El orden se edita manualmente mediante `sort_order`.
- El formulario valida el tipo de bloque y los campos mínimos de `data` según el tipo seleccionado.
- La eliminación de un bloque usa confirmación simple y no elimina la página.
- El último bloque de una página con estado `published` no puede eliminarse; antes debe pasarse expresamente la página a borrador.
- El detalle de página muestra el feedback de creación, actualización, eliminación y rechazo de bloques.
- La subida real de imágenes o documentos no forma parte de esta pantalla; los bloques usan URLs o rutas ya existentes.

### Pistas

La ruta `/admin/venues` centraliza la configuración básica de pistas.

- El listado muestra nombre, ubicación, descripción y número de partidos asociados.
- Permite crear y editar los campos reales `name`, `location` y `description`.
- El nombre es obligatorio y único en los formularios; ubicación y descripción son opcionales.
- El modelo actual no incluye estado activo, por lo que la pantalla no ofrece activación o desactivación.
- Una pista sin uso puede eliminarse con confirmación.
- El borrado se deshabilita y se rechaza en backend si existen partidos o solicitudes de reprogramación asociadas.
- La navegación principal del panel incluye el acceso “Pistas”.
- El conjunto mínimo de desarrollo se crea explícitamente con `php artisan db:seed --class=DefaultVenueSeeder`; repetir el comando no duplica ni sobrescribe pistas.

### Temporadas

La ruta `/admin/seasons` centraliza el CRUD Blade de temporadas.

- El formulario gestiona los campos reales `name`, `status`, `is_public`, `start_date` y `end_date`.
- El nombre y el estado son obligatorios; el estado se valida contra los casos reales de `SeasonStatus`.
- Las fechas de inicio y fin son opcionales y se editan mediante controles HTML `date`.
- Cuando se informan ambas fechas, la fecha de fin debe ser igual o posterior a la fecha de inicio.
- La creación y actualización reciben exclusivamente datos validados y persisten explícitamente los cuatro campos, incluidos los valores nulos al limpiar las fechas.
- La edición presenta el estado casteado correcto y da prioridad a `old()` después de un error de validación.
- El listado conserva la presentación de estado y fechas, y el acceso requiere una sesión de administrador activo.
- El checkbox «Visible públicamente» se envía siempre como booleano, crea temporadas privadas por defecto y se presenta por separado del estado como Pública o Privada.
- Ocultar una temporada está permitido aunque tenga campeonatos declarados públicos; sus flags no se modifican automáticamente.

### Campeonatos

Las rutas `/admin/championships` y `/admin/seasons/{season}/championships/create` centralizan el CRUD Blade de campeonatos.

- El formulario gestiona los campos reales `season_id`, `name`, `description`, `type`, `status`, `is_public`, `start_date`, `end_date`, `registration_status`, `registration_starts_at` y `registration_ends_at`.
- La temporada se elige entre registros existentes. Nombre, tipo, estado del campeonato y estado de inscripciones son obligatorios; los valores se validan contra los casos admitidos por el dominio actual.
- Descripción, fechas del campeonato y fechas de inscripción son opcionales. Cada fecha final debe ser igual o posterior a la fecha inicial de su mismo intervalo cuando ambas se informan.
- La creación y actualización reciben exclusivamente datos validados y persisten de forma explícita todos los campos administrables, incluidos los valores nulos al limpiar campos opcionales.
- La edición recupera todos los valores persistidos y da prioridad a `old()` después de un error de validación.
- El `slug` continúa derivándose del nombre. Los identificadores y timestamps permanecen gestionados por Laravel.
- `image_path` existe en persistencia, pero la gestión multimedia no forma parte de este formulario: no se ofrece entrada ni subida y una actualización conserva el valor previo.
- No existe un booleano de apertura en la tabla. `registration_is_open` continúa calculándose a partir del estado y las fechas de inscripción y no es un campo editable.
- El control modifica la visibilidad declarada; la API pública exige además que la temporada asociada sea pública y filtra las categorías privadas.
- Las opciones de temporada indican si son públicas o privadas. Un campeonato sólo puede marcarse público bajo una temporada pública; mantenerlo privado es válido bajo cualquier temporada.
- Ocultar un campeonato no cambia automáticamente `is_public` en sus categorías.

### Categorías

Las rutas `/admin/championships/{championship}/categories/*` y `/admin/categories/{category}/*` centralizan el CRUD Blade de categorías.

- La creación permanece anidada bajo un campeonato existente y toma `championship_id` exclusivamente del modelo resuelto por la ruta. La edición no permite mover una categoría a otro campeonato.
- El formulario gestiona los campos reales `name`, `description`, `level`, `gender`, `status` e `is_public`.
- Nombre, género y estado son obligatorios. El género se valida contra `CategoryGender` y el estado contra los valores administrativos reales `pending` y `active`.
- Descripción y nivel son opcionales conforme al esquema. La descripción admite hasta 5.000 caracteres y el nivel, cuando se informa, debe estar entre 1 y 10.
- La creación y actualización reciben exclusivamente datos validados y persisten de forma explícita todos los campos administrables, incluidos los valores nulos al limpiar descripción o nivel.
- La edición recupera todos los valores persistidos y da prioridad a `old()` después de un error de validación.
- El `slug` continúa derivándose del nombre y conserva la unicidad por campeonato existente en base de datos.
- `image_path` existe en persistencia, pero la gestión multimedia no forma parte de este formulario: no se ofrece entrada ni subida y una actualización conserva el valor previo.
- Las relaciones con inscripciones, participantes, equipos, rondas y partidos no forman parte del formulario y permanecen intactas durante una actualización ordinaria.
- El control modifica la visibilidad declarada; la API pública exige además que campeonato y temporada sean públicos.
- El formulario muestra la visibilidad del campeonato y de su temporada. Una categoría sólo puede marcarse pública cuando ambos padres son públicos; puede mantenerse privada bajo cualquier combinación.

### Semántica común de visibilidad competitiva

- Estado operativo y visibilidad son dimensiones independientes. El panel usa Pública o Privada, no Publicada.
- Los tres formularios incluyen un checkbox accesible y un valor oculto para persistir `false` cuando queda desmarcado; `old()` prevalece tras un error.
- Los nuevos registros son privados por defecto.
- Activar un campeonato exige una temporada pública. Activar una categoría exige campeonato y temporada públicos.
- Ocultar una temporada o un campeonato siempre está permitido y no propaga cambios a los flags de sus descendientes.
- Los listados y detalles muestran conjuntamente estado operativo y visibilidad declarada para evitar que el administrador los confunda.
- La API pública aplica la conjunción de la rama: ocultar un padre oculta efectivamente sus descendientes sin alterar sus flags. Al restaurarlo reaparecen los descendientes que continúan declarados públicos.
- Administración, generación y áreas personales no quedan limitadas por esta política pública.

### Correspondencia con la API administrativa

Los formularios Blade no han cambiado en la Fase 2B.5. La API administrativa de temporadas, campeonatos y categorías reutiliza sus mismas reglas de campos, enums, fechas y jerarquía de visibilidad mediante Form Requests. Ambos canales persisten únicamente atributos validados y asignan `is_public` de forma explícita.

La API mantiene acceso a registros privados y no aplica scopes públicos. `image_path` continúa fuera de los formularios y del payload API, se conserva al editar y requiere un bloque multimedia futuro para administrarse. La creación API plana de una categoría exige el campeonato existente; las actualizaciones, igual que Blade, no permiten cambiar esa relación.

### Inventario de pantallas implementadas

El panel web actual dispone de estas áreas reales:

| Área | Pantallas y acciones |
|---|---|
| Autenticación | login y logout de administrador |
| Dashboard | contadores de solicitudes, asignaciones pendientes y conflictos; accesos rápidos |
| Solicitudes | listado operativo, aprobación, rechazo y asignación rápida de categoría |
| Temporadas | listado, alta, detalle, edición, borrado y campeonatos de la temporada |
| Campeonatos | listado, alta dentro de temporada, detalle, edición, borrado, solicitudes y pago manual |
| Categorías | listado, alta, detalle, edición, borrado, asignaciones, equipos, liga, copa y finales |
| Partidos | edición dentro del detalle de categoría; no existe un índice Blade independiente de todos los partidos |
| Conflictos | listado, detalle comparativo y resolución de partidos `under_review` |
| Pistas | listado, alta, edición y borrado seguro |
| Jugadores | listado, alta, detalle, edición y borrado |
| Usuarios | listado, alta, detalle, edición y borrado |
| Rankings | vista del ranking histórico; los demás rankings aparecen en los contextos de temporada, campeonato o categoría |
| CMS | listado/alta/detalle/edición de páginas y alta/edición/borrado de bloques |

No existen actualmente pantallas Blade específicas para una cola de solicitudes de reprogramación, métricas avanzadas, noticias, subida de archivos o formularios públicos. La fecha y pista de un partido pueden editarse desde la categoría y los conflictos de resultados tienen su flujo propio.

---

# 1. Naturaleza del panel

El panel administrativo es una interfaz web Blade independiente del frontend React.

Forma parte oficial de la arquitectura del proyecto y permite gestionar el dominio de competición sin depender de la API pública.

En pantallas estrechas, la barra superior se contrae detrás de un botón con nombre accesible «Abrir menú de administración». El control identifica la navegación mediante `aria-controls` y expone su estado abierto o cerrado con `aria-expanded`, actualizado por el componente Collapse de Bootstrap.

---

# 2. Acceso y seguridad

El panel requiere:

- usuario autenticado;
- rol de administrador;
- usuario activo.

Si un administrador es desactivado mientras mantiene una sesión abierta, el sistema debe invalidar la sesión y expulsarlo del panel.

---

# 3. Responsabilidades principales

El administrador puede gestionar:

- usuarios;
- jugadores;
- temporadas;
- campeonatos;
- categorías;
- solicitudes de inscripción;
- asignaciones a categorías;
- participantes competitivos;
- equipos de dobles;
- calendarios;
- partidos;
- resultados;
- rankings;
- páginas públicas CMS;
- pistas.

---

# 4. Ciclo de vida de la competición

1. Crear temporada.
2. Crear campeonatos.
3. Crear categorías.
4. Revisar solicitudes.
5. Asignar jugadores mediante `CategoryRegistration`.
6. Crear equipos cuando proceda.
7. Crear participantes competitivos (`CategoryEntry`).
8. Generar liga.
9. Generar copa.
10. Gestionar partidos.
11. Validar resultados.
12. Revisar clasificaciones y rankings.

---

# 5. Ciclo de vida del jugador

Desde el punto de vista administrativo un jugador pasa por:

1. Usuario registrado.
2. Perfil de jugador creado.
3. Solicitud presentada.
4. Solicitud pendiente.
5. Solicitud aprobada o rechazada.
6. Asignación administrativa mediante `CategoryRegistration`.
7. Creación del participante competitivo (`CategoryEntry`).
8. Participación en partidos.

Una solicitud aprobada no implica competir automáticamente.

La asignación administrativa y la creación del participante competitivo son pasos independientes.

En individuales el `CategoryEntry` referencia a un jugador.

En dobles referencia a un equipo.

---

# 6. Solicitudes de inscripción

Las solicitudes representan la intención de un jugador de participar en un campeonato.

El panel debe diferenciar claramente:

- solicitudes pendientes;
- solicitudes aprobadas;
- solicitudes rechazadas;
- estado del pago.

La aprobación y el pago son procesos independientes.

Actualmente el pago puede gestionarse manualmente.

---

# 7. Asignación a categorías

Una vez aprobada una solicitud el administrador asigna al jugador mediante `CategoryRegistration`.

La asignación puede realizarse desde la vista de una categoría o desde la sección específica de solicitudes e inscripciones. Ambos formularios reutilizan la misma ruta y el mismo controlador, por lo que aplican idénticas validaciones, creación de participantes competitivos y reglas de exclusividad dentro del campeonato.

La pantalla de solicitudes solo ofrece categorías pertenecientes al campeonato de la solicitud y mantiene el enlace a la gestión completa de categorías.

Reglas funcionales:

- solo jugadores con solicitud aprobada;
- un jugador no puede quedar asignado a dos categorías del mismo campeonato;
- `CategoryRegistration` representa la asignación administrativa;
- `CategoryEntry` representa la unidad competitiva final;
- en individuales el `CategoryEntry` referencia al jugador;
- en dobles referencia al equipo;
- debe respetarse la estructura del campeonato.

---

# 8. Equipos de dobles

En campeonatos de dobles el administrador crea equipos con jugadores aprobados.

Posteriormente se crean los correspondientes `CategoryEntry` que competirán en la categoría.

---

# 9. Calendario y fases

El administrador puede generar:

## Liga

Fase base de la competición.

## Copa

Fase eliminatoria generada a partir de la clasificación de liga.

La lógica pertenece a Services, no a Blade.

## Configuración de pistas

El administrador debe crear al menos una pista antes de generar una liga. El generador utiliza todas las pistas existentes en orden estable y no depende de sus nombres ni de que se haya ejecutado el seeder.

Si no existen pistas, el panel muestra: “No hay pistas configuradas. Crea al menos una pista desde el panel de administración antes de generar la liga.” No se crean jornadas ni partidos.

Una pista puede reutilizarse en los distintos horarios de una jornada sin colisiones. Si el número de cruces supera los siete huecos disponibles por pista y jornada, el panel informa de que no hay suficientes pistas configuradas y la operación completa se revierte.

---

# 10. Ciclo de vida del partido

1. Generado.
2. Programado.
3. Disputado.
4. Resultado enviado.
5. Resultado confirmado o en conflicto.
6. Resultado validado.
7. Cierre para efectos de rankings.

Estas etapas representan el ciclo funcional del partido y no implican necesariamente una correspondencia directa con un único campo o enum del modelo.

---

# 11. Resultados

Solo los resultados validados producen efectos oficiales.

El panel permite:

- revisar envíos;
- detectar conflictos;
- resolver discrepancias;
- validar resultados;
- mantener trazabilidad.

Cuando dos lados reportan tanteos diferentes, el partido queda `under_review` sin resultado oficial. Solo un administrador puede resolverlo indicando un tanteo deportivo válido. La resolución establece `home_score`, `away_score`, ganador, estado `validated` y administrador validador.

Los dos reportes originales permanecen en estado `conflict`, con sus autores, comentarios y tanteos intactos. Esta trazabilidad permite auditar qué comunicó cada lado; la resolución no falsifica una confirmación que nunca ocurrió. Tras validarse el partido, el resultado pasa a rankings y demás cálculos oficiales.

La sección **Conflictos** de la navegación administra este flujo mediante:

- `GET /admin/match-conflicts`: listado exclusivo de partidos `under_review`, con competición, categoría, jornada, participantes, fecha, pista y los dos reportes;
- `GET /admin/match-conflicts/{gameMatch}`: detalle de contexto y comparación de tanteos, autores y comentarios originales;
- `POST /admin/match-conflicts/{gameMatch}/resolve`: resolución mediante tanteo oficial local y visitante.

El formulario muestra el objetivo deportivo calculado por el backend —10 en individuales y 12 en dobles— y exige confirmación antes del envío. Los valores negativos, empates, tanteos que incumplen el objetivo y partidos que ya no estén `under_review` se rechazan sin producir cambios parciales. La resolución se ejecuta con transacción y bloqueo de fila para impedir dobles validaciones concurrentes.

El dashboard muestra el número de conflictos pendientes y enlaza al listado. Las vistas no exponen correos electrónicos ni identificadores internos. El modelo actual no dispone de un campo de motivo administrativo, por lo que la trazabilidad se compone del administrador guardado en `validated_by` y de los dos reportes originales inmutables.

---

# 12. Rankings

Blade muestra información calculada por el backend.

No implementa algoritmos de clasificación.

---

# 13. UX administrativa

Debe priorizar:

- claridad;
- tareas pendientes;
- acciones seguras;
- separación entre estados administrativos y deportivos;
- coherencia visual;
- prevención de errores.

---

# 14. Gestión CMS

El panel administrativo incluye gestión básica de páginas y bloques CMS.

Responsabilidades actuales:

- listar páginas CMS;
- crear páginas siempre como borrador;
- editar título, `slug`, estado, fecha de publicación y campos SEO después del alta;
- cambiar entre borrador y publicada mediante el formulario de edición;
- presentar como Programada una página `published` cuya fecha sea futura;
- impedir la publicación de páginas sin bloques y proteger el último bloque de las páginas `published`;
- interpretar las fechas de edición en la zona configurada por Laravel;
- mantener visible el número de bloques asociados;
- crear, editar, ordenar manualmente y eliminar bloques estructurados.
- mantener los slugs institucionales enlazados desde la navegación pública cuando se quiera editar su contenido.

La subida real de imágenes y documentos se incorporará en una fase posterior.

La gestión genérica ya ha sido auditada y endurecida, pero debe diseñarse el contrato específico antes de asignarle nuevas áreas públicas. La existencia del CRUD no confirma por sí sola que Prensa y medios, Club o la actividad de la Escuela dispongan de los campos, permisos, flujos y contratos que necesitan.

---

# 15. Funcionalidades futuras

- pagos online;
- notificaciones;
- asignación automática de categoría;
- sugerencias de categoría;
- filtros avanzados;
- auditoría ampliada;
- panel de métricas avanzado;
- cola administrativa específica de reprogramaciones;
- coordinación de disponibilidad entre categorías;
- administración avanzada de contenidos públicos (noticias);
- gestión de documentos públicos (subida segura, visibilidad);
- recepción y gestión de formularios públicos de interés.

---

## Mantenimiento

Cuando cambie el flujo administrativo este documento deberá actualizarse.
