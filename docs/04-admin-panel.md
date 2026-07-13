# Panel administrativo — Galotxas

## Propósito

Este documento describe el funcionamiento del panel administrativo Blade de Galotxas.

El panel administrativo es la herramienta principal para gestionar la competición desde el backend Laravel.

Describe procesos funcionales, no detalles visuales. Las reglas de estilo se encuentran en `backend/BACKEND_STYLE.md`.

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
- Permite crear páginas con título, `slug`, estado, `published_at` y metadatos SEO mínimos.
- Permite editar esos mismos campos.
- La publicación y despublicación se realiza modificando el campo de estado.
- Si una página se marca como publicada sin `published_at`, el backend completa la fecha con el momento actual.
- Desde el detalle de una página permite gestionar sus bloques CMS.
- Las páginas institucionales recomendadas para el MVP usan los slugs `prensa-media`, `nosotros`, `federaciones`, `academy`, `documentos` y `federarse`.
- En entornos de desarrollo puede crearse una base mínima no destructiva con `php artisan db:seed --class=InstitutionalCmsPageSeeder`.

### Bloques CMS

Las rutas `/admin/cms/pages/{cmsPage}/blocks/*` centralizan la gestión básica de bloques de una página CMS.

- Los bloques se muestran dentro del detalle de la página ordenados por `sort_order` e `id`.
- Permite crear, editar y eliminar bloques.
- El orden se edita manualmente mediante `sort_order`.
- El formulario valida el tipo de bloque y los campos mínimos de `data` según el tipo seleccionado.
- La eliminación de un bloque usa confirmación simple y no elimina la página.
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

---

# 1. Naturaleza del panel

El panel administrativo es una interfaz web Blade independiente del frontend React.

Forma parte oficial de la arquitectura del proyecto y permite gestionar el dominio de competición sin depender de la API pública.

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
- páginas públicas CMS.

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
- crear páginas;
- editar título, `slug`, estado, fecha de publicación y campos SEO;
- cambiar entre borrador y publicada mediante el formulario de edición;
- mantener visible el número de bloques asociados;
- crear, editar, ordenar manualmente y eliminar bloques estructurados.
- mantener los slugs institucionales enlazados desde la navegación pública cuando se quiera editar su contenido.

La subida real de imágenes y documentos se incorporará en una fase posterior.

---

# 15. Funcionalidades futuras

- pagos online;
- notificaciones;
- asignación automática de categoría;
- sugerencias de categoría;
- filtros avanzados;
- auditoría ampliada;
- panel de métricas avanzado;
- administración avanzada de contenidos públicos (noticias);
- gestión de documentos públicos (subida segura, visibilidad);
- recepción y gestión de formularios públicos de interés.

---

## Mantenimiento

Cuando cambie el flujo administrativo este documento deberá actualizarse.
