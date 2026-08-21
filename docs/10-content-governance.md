# Gobernanza de contenidos y arquitectura pública — Galotxas

## 1. Propósito

Este documento define cómo se decide, edita, publica y consume el contenido público de Galotxas. Es la referencia central para evitar fuentes duplicadas, contenido administrable incrustado en código y diferencias entre la navegación, el panel Blade, la API, React y `knowledge/`. El inventario y contrato concreto de URLs, enlaces y compatibilidad se mantiene en `09-public-navigation.md`.

La decisión original descrita aquí se aprobó como arquitectura objetivo en la Fase 0, que fue exclusivamente documental y no creó rutas, componentes, endpoints, modelos, migraciones, pantallas Blade, compiladores ni carpetas de conocimiento.

## 2. Lectura del estado

En este documento se distinguen cuatro niveles:

- **Actual**: capacidad comprobada en el repositorio.
- **Aprobado**: decisión que orienta las siguientes implementaciones.
- **Futuro**: implementación necesaria para materializar una decisión aprobada.
- **Pendiente**: deuda o cuestión que necesita auditoría antes de decidir o implementar.

Una decisión aprobada no debe interpretarse como una capacidad ya disponible.

## 3. Tipos de contenido

### Dominio competitivo

Datos y reglas funcionales sobre temporadas, campeonatos, categorías, inscripciones, equipos, partidos, calendarios, resultados y rankings. El backend Laravel es su fuente de verdad y React se limita a representar el contrato API.

### Conocimiento normativo

Formulación canónica y estable de las reglas de las Galotxas. Reside editorialmente en `knowledge/reglamento/`. Puede servir de referencia a la implementación deportiva, pero no sustituye las reglas ejecutables y validadas por el backend.

### Contenido conceptual

Vocabulario, elementos, personas y definiciones propios del deporte. Reside en `knowledge/conceptos/` y debe conservar identificadores, terminología y relaciones estables.

### Contenido pedagógico

Explicaciones, metodología, ejercicios, iniciación y materiales estables para aprender o enseñar. Podrá formar parte de futuras colecciones de `knowledge/` cuando exista contenido real y un contrato editorial aprobado.

### Contenido institucional

Información del Club como Nosotros, Federarse, Federaciones, Prensa y medios o Contacto. Cuando deba editarla un administrador sin desplegar código, su fuente será el backend CMS.

### Contenido editorial temporal

Noticias, actividades, jornadas, talleres, convocatorias, fechas, galerías y documentos que cambian con frecuencia. Requiere administración, persistencia, publicación segura y API pública; no pertenece a componentes React ni a `knowledge/`.

## 4. Fuentes de verdad y arquitectura híbrida

La arquitectura aprobada dispone de tres canales principales.

### 4.1. Dominio funcional

`Laravel → API → React`

Laravel decide las reglas y consulta la persistencia de competición. La API expone un contrato por contexto y React presenta los datos sin recalcular resultados, rankings, elegibilidad o estados deportivos.

Temporadas, campeonatos y categorías incorporan una visibilidad declarada explícita mediante `is_public`, administrada desde Blade y separada de sus estados operativos. Los registros nuevos son privados por defecto y los existentes se preservan como públicos durante la migración. La declaración respeta la jerarquía Temporada → Campeonato → Categoría al activar flags, pero ocultar un padre no reescribe los de sus hijos.

Esta visibilidad pertenece al contenido funcional de competición y no reutiliza los estados editoriales `draft`/`published` del CMS. Laravel aplica la conjunción de la rama en listados, detalles, relaciones y datos derivados antes de serializar; `is_public` no forma parte de los Resources públicos. React consume ese resultado y no replica ni compensa la política de ocultación. Administración y datos personales relacionados conservan su contexto interno.

### 4.2. Contenido administrable

`Panel Blade → base de datos → API pública → React`

Se utiliza cuando un administrador debe editar contenido, cuando existen borradores o publicación programada, cuando cambia con frecuencia o cuando incluye archivos operativos. El backend filtra el contenido no publicable antes de responder.

**Estado actual verificado:** existen páginas y bloques CMS estructurados, administración Blade, estados persistidos `draft` y `published`, fecha de publicación, Resources públicos, endpoints de lectura y las rutas React legadas `/contenidos` y `/contenidos/:slug`. La creación es siempre en borrador; publicar exige al menos un bloque válido; `published_at = null` significa publicación inmediata y una fecha futura se presenta como Programada sin añadir un estado persistido. El último bloque de una página `published` queda protegido. No existe todavía subida administrada de archivos ni se considera resuelta la adecuación del CMS genérico a todas las nuevas áreas.

### 4.3. Conocimiento canónico y estable

`knowledge/ → compilador validado → datos generados → React`

Se utilizará para el Manual, Reglamento, Conceptos, terminología y otros contenidos pedagógicos estables. `knowledge/` será la única fuente editorial de esas colecciones.

**Estado actual:** `knowledge/reglamento/` y `knowledge/conceptos/` existen.

**Estado tras 5C:** existen contrato editorial, validación, compilador determinista, artefacto canónico completo y proyección pública versionada con los 40 documentos `Vigente` de cuatro colecciones. REG-001–REG-008 han recibido aprobación editorial humana como Reglamento inicial. React importa sólo la proyección, mediante un repositorio y renderer de nodos seguros, y publica la landing de Aprende, el Manual y sus documentos sin API Laravel ni CRUD Blade. La interfaz deriva recuentos, colecciones, índices y anterior/siguiente de ese contrato; no crea una fuente editorial adicional. La rama completa se carga de forma diferida para mantener el corpus fuera del JavaScript inicial.

La normalización técnica sólo puede cambiar estructura expresamente autorizada, nunca reformular reglas, términos o referencias. Las revisiones editoriales futuras deberán ser conscientes, actualizar `ultima_revision` y revisar la versión conforme al alcance semántico del cambio. Un documento `Vigente` sólo puede referenciar otro documento `Vigente`; un borrador permanece en el artefacto canónico, pero ni él ni sus metadatos, rutas o referencias pueden entrar en la proyección o el bundle.

## 5. Arquitectura pública aprobada

Fase 7B contrata cuatro controles editoriales:

- Inicio (`/`), enlace;
- Competición (`/competicion`), enlace;
- Aprende, disclosure de Aprende a jugar (`/aprende-a-jugar`), Manual y reglas
  (`/aprende-a-jugar/manual`) y Escuela de Galotxas (`/escuela`);
- Club, disclosure de `/club/quienes-somos`, `/club/contacto`,
  `/club/federarse` y `/club/documentos`.

La identidad del usuario, Mi Panel y el cierre de sesión permanecerán en una zona autenticada separada.

Esta es la arquitectura de ADR-033. Tras 7D.1 están registradas `/`,
`/competicion`, `/aprende-a-jugar`, su Manual, `/escuela` y las cuatro rutas
Club, y el Navbar las descubre mediante los disclosures Aprende/Club.
Competición utiliza datos públicos reales; Aprende a jugar y Manual consumen
Knowledge; Escuela consume Laravel; y Club consume sólo páginas CMS publicadas
mediante slugs cerrados. Home y Footer contienen únicamente copy de interfaz,
arquitectura de enlaces, identidad y redes confirmadas; no duplican CMS o
Knowledge. 7D.2A preparó borradores internos y gates. Desde 7D.2C1, `legal/` es una cuarta
fuente explícita y acotada: contiene exactamente tres textos públicos
versionados, compilados a una proyección propia y enlazados sólo desde el
footer. 7D.2C2A incorpora además un aviso de formulario allowlisted bajo
`legal/notices/`; se compila por separado y no crea una página pública. Legal
no es CMS, Knowledge ni copy JSX.

Los componentes de `frontend/src/components/PublicLanding/` son infraestructura de presentación, no una cuarta fuente de contenido. Pueden recibir datos ya autorizados del dominio Laravel, artefactos compilados desde `knowledge/` o contenido público del CMS, pero no conocen esas fuentes ni deciden visibilidad, publicación o reglas. Sus props admiten estructura, copy breve de interfaz y contenido procedente de la fuente canónica; no deben usarse para hardcodear contenido administrable como sustituto temporal del CMS o de `knowledge/`.

### Inicio

Landing híbrida: estructura React, conocimiento estable cuando corresponda y elementos dinámicos destacados procedentes del backend.

### Competición

Agrupa Torneos, Rankings, Calendarios, Clasificaciones, Resultados e información útil para jugadores. Depende principalmente del dominio Laravel y de su API. Las rutas actuales de Torneos y Rankings pueden mantenerse durante la migración, aunque dejen de ser áreas independientes de primer nivel.

Desde 4A, las temporadas y campeonatos de la landing proceden exclusivamente de `GET /api/v1/seasons`. Laravel aplica la visibilidad efectiva y serializa la jerarquía pública; React conserva el orden, presenta estados, fechas disponibles, recuentos y enlaces, pero no consulta ni filtra `is_public`. No existen temporadas o campeonatos hardcodeados en JSX, seeders frontend ni componentes comunes.

Desde 4B, el preview histórico procede de `GET /api/v1/rankings/all-time` mediante el mismo servicio de `/rankings`, pero con carga y estados independientes respecto a temporadas. React conserva el orden deportivo recibido, corta visualmente tras cinco filas y no calcula puntos, posición u oficialidad; el enlace a la vista completa permanece disponible.

Fase 4C cierra el recorrido público sin cambiar fuentes: la landing prioriza Torneos, temporadas y ranking sin accesos duplicados; campeonato, categoría, clasificación, calendario, partido y rankings conservan URLs y contratos. La navegación contextual no convierte datos funcionales en contenido editorial. React presenta etiquetas, fechas y estados remotos, pero Laravel sigue decidiendo posiciones, resultados, visibilidad y reglas. El detalle de categoría no duplica las colecciones de standings o schedule de sus vistas dedicadas.

### Aprende a jugar

Puerta de entrada divulgativa al Manual, Reglamento y Conceptos en su primera versión funcional. Su landing y el Manual cumplen funciones distintas.

La landing canónica es `/aprende-a-jugar`; el índice se publica en `/aprende-a-jugar/manual`, los reglamentos en `/manual/reglamento/:slug` dentro de esa rama y los conceptos en `/manual/conceptos/:group/:slug`, con `group` limitado a elementos, personas y juego. Los ejemplos anteriores `/aprende` y `/manual` en raíz nunca se implementaron. No se crean enlaces de Historia mientras no exista esa colección.

El cierre 5C mantiene esas rutas y fuentes. La landing presenta los recuentos obtenidos del repositorio; el Manual enlaza sus cuatro colecciones; y cada documento usa exclusivamente headings compilados para su tabla de contenidos, conserva fragmentos estables y permite avanzar o retroceder sólo dentro de la colección. La navegación contextual es local a Aprende y no introduce breadcrumbs globales. Ninguno de estos controles interpreta Markdown, inventa orden o modifica el corpus.

### Escuela de Galotxas

Sección pública propia para una Escuela permanente orientada principalmente a
menores y abierta también a solicitudes de adultos. Se agrupa bajo Aprende
exclusivamente para facilitar su descubrimiento: no es una subsección del
Manual, no cambia de ruta o fuente y no debe denominarse públicamente
“Academy”, salvo para explicar una referencia legada durante la migración.

Su arquitectura es híbrida y se implementa por bloques:

- metodología, iniciación, ejercicios y recursos pedagógicos estables desde una futura colección de `knowledge/`, sólo cuando exista contenido real y aprobado;
- programa permanente, niveles, horarios semanales, ubicaciones y apertura declarada desde el dominio Laravel administrado con Blade implementado en 6B.1;
- solicitudes, participantes, representantes, contactos, estados y fechas del ciclo exclusivamente en Laravel, implementados en 6B.2;
- centros educativos y sus actividades en un subdominio administrativo Laravel separado de las inscripciones individuales, implementado en 6B.3;
- avisos o páginas simples desde el CMS genérico cuando no necesiten relaciones propias.

La Escuela enlaza al Manual existente, pero no lo copia ni se anida dentro de Aprende a jugar. React compone únicamente los datos operativos autorizados; no existe todavía una colección pedagógica escolar. El CMS, `knowledge/` y JSX nunca almacenan niveles, horarios, alumnos, solicitudes, centros o actividades como fuente alternativa, y la inscripción a campeonatos no se reutiliza como inscripción escolar.

La lectura pública `GET /api/v1/school` expone mediante allowlists únicamente
nombre, presentación y proceso administrados, estado de apertura, aviso,
ubicación habitual activa, niveles activos/públicos y horarios efectivos. No
expone teléfono o correo del programa. La ausencia se representa como
`data: null`; una configuración incompleta usa `unavailable`. Nombres,
nacimiento, representante, contactos, estado individual y observaciones de
solicitudes nunca son públicos. El POST devuelve confirmación genérica y no
permite consultar solicitudes.

Desde 7D.2C2A, ese POST puede registrar por separado una autorización opcional
de identidad deportiva. El texto procede de `legal/notices/`, la evidencia y
el vínculo con `Player` pertenecen a Laravel y la revisión pertenece a Blade.
La inscripción no se convierte en autorización y React no recibe estados,
correos, nacimientos o motivos: Competición conserva exclusivamente
`public_display_name`.

Los centros y actividades de 6B.3 también permanecen en MariaDB y Blade y no se publican en el MVP. Contactos de centros y `admin_notes` son privados; no se duplican en CMS o Knowledge. Las actividades conservan únicamente un número previsto, nunca asistentes nominales, y no forman parte de `GET /api/v1/school`.

La ruta canónica es `/escuela`. No se aprueba `/manual/academy`. Fases 6A y 6A.1 definen el contrato; 6B.1–6B.4 implementan núcleo, inscripciones, centros/actividades y lectura pública; 6C completa ruta, Navbar y formulario React. La colección pedagógica continúa ausente y no se sustituye con contenido hardcodeado.

### Club

Club es un disclosure, no una landing `/club`. Agrupa únicamente Quiénes somos,
Contacto, Federarse y Documentos. Su contenido administrable tiene como fuente
el backend CMS y se presenta mediante las cuatro rutas canónicas desde 7C.2.

El código garantiza actualmente los slugs sembrados `nosotros`, `federarse` y
`documentos`; no existe un slug sembrado `contacto`. La duplicidad entre
`/nosotros` estático y `/contenidos/nosotros` se resolverá a favor del CMS sólo
después de inventariar, aprobar, acreditar paridad y mantener aliases
temporales. React no copiará ese cuerpo.

`prensa-media` y `federaciones` siguen siendo páginas CMS legadas, pero quedan
fuera del Navbar y de Club en el MVP. Sólo podrán aparecer en el footer como
enlaces condicionales cuando exista contenido real y responsable.

7C.1 confirma que los cuatro destinos pueden componerse con bloques CMS y
documenta su carga manual, pero no crea ni publica páginas. Los bloques de
enlace admiten `mailto:` validado; no admiten `tel:`. Los assets de
`public/media/club` son fuentes estables candidatas, no contenido aprobado, y
requieren procedencia, derechos, alt y revisión de metadatos antes de usarse.

7C.2 añade fachadas React diferidas sin copy institucional: el título, bloques y
metadatos proceden del CMS; sólo fallbacks neutros y etiquetas funcionales son
interfaz. La carga local fue manual y cada entorno conserva su propio estado de
publicación. Los fixtures CMS añadidos existen exclusivamente bajo la guarda
E2E y no son fuente editorial o seeder productivo.

El formulario de Contacto tampoco altera la fuente editorial: canales y cuerpo
pertenecen al CMS, las etiquetas funcionales pertenecen a React y cada
`ContactRequest` es privado en MariaDB. La interfaz existe, pero permanece
oculta con el default productivo. Privacidad, retención, destinatario, operación
y activación siguen bloqueando su uso real, no el cierre técnico de 7C.2.

### Contenidos legado

`/contenidos` y sus páginas constituyen una estructura actual y legada, no el destino final de la arquitectura de información. Permanecen accesibles tras retirarse del primer nivel en 3B; no se han eliminado, migrado ni redirigido. El backend excluye borradores y fechas futuras tanto del índice como del acceso por slug. El seeder institucional garantiza seis slugs sin sobrescribir páginas existentes: `prensa-media`, `nosotros`, `federaciones`, `academy`, `documentos` y `federarse`. Esta infraestructura verificada no convierte el índice técnico ni `academy` en áreas canónicas.

## 6. Matriz de gobernanza

La tabla diferencia la fuente aprobada de las capacidades actuales que todavía requieren auditoría.

| Área | Fuente principal | Edición | Admin Blade | API | Naturaleza |
|---|---|---|---|---|---|
| Inicio | Híbrida | Código + fuentes conectadas | Parcial | Parcial | Landing |
| Competición | Backend de dominio | Administración deportiva | Sí, según módulo | Sí | Funcional |
| Torneos | Backend de dominio | Administración deportiva | Sí | Sí | Dinámica |
| Rankings | Backend de dominio | Reglas y resultados | Sí, según flujo | Sí | Dinámica |
| Aprende a jugar | `knowledge/` | Git y revisión | No inicialmente | No | Estable |
| Manual | `knowledge/` | Git y revisión | No inicialmente | No | Canónica |
| Reglamento | `knowledge/reglamento/` | Git y revisión | No | No | Normativa |
| Conceptos | `knowledge/conceptos/` | Git y revisión | No | No | Canónica |
| Escuela: contenido estable | `knowledge/` futuro | Git y revisión | No inicialmente | No | Pedagógica |
| Escuela: programa, contenido, niveles, ubicaciones y horarios | Dominio Laravel | Administrador desde Blade | Sí, reforzada en 7E | Sí, lectura cerrada desde 6B.4 y estado fail-closed desde 7E | Operativa/editorial administrable |
| Escuela: inscripciones | `SchoolEnrollment` | Solicitante público + administrador autorizado | Sí, desde 6B.2 | Sólo POST, sin lectura | Personal y privada |
| Escuela: centros y actividades | `EducationalCenter` y `EducationalActivity` | Administrador desde Blade | Sí, desde 6B.3 | No en MVP | Operativa y privada |
| Escuela: avisos simples | CMS genérico, si se aprueba | Administrador | Genérico actual | Genérica actual | Editorial |
| Club | Backend CMS | Administrador | Sí | Sí | Institucional |
| Prensa y medios | Backend CMS genérico auditado; contrato específico pendiente | Administrador | Genérico actual | Genérica actual | Editorial |
| Federaciones | Backend CMS genérico auditado; publicación MVP condicional | Administrador | Genérico actual | Genérica actual | Institucional secundaria |
| Legal y privacidad | `legal/` | Git + revisión responsable/profesional | No | No | Legal versionada |
| Contenidos legado | Backend CMS | Administrador | Existente y auditado | Existente y auditado | Legada |

“Sí” expresa el flujo aprobado o el módulo deportivo actual según la fila; no garantiza que una sección editorial concreta ya esté implementada. La Fase 1 verificó las capacidades y límites del CMS genérico; cada vertical futura todavía debe definir y probar su contrato específico.

## 7. Responsables de edición

- El equipo de dominio y administración deportiva modifica reglas ejecutables y datos competitivos en backend o mediante los flujos Blade autorizados.
- Los administradores editoriales modifican contenido CMS desde Blade, dentro de sus permisos.
- Las personas responsables del conocimiento editan `knowledge/` mediante Git, revisión y validación editorial.
- Las personas responsables de legal editan `legal/`, actualizan versión y
  fechas, ejecutan `legal:check`/`legal:build` y revisan fuente y proyección en
  el mismo cambio; no usan Blade ni editan el JSON generado a mano.
- Esas personas ejecutan `knowledge:check`, regeneran los artefactos con `knowledge:build` y entregan fuente y JSON juntos; nunca editan archivos de `generated/` a mano.
- Un cambio de estado requiere aprobación editorial. La autorización de 5A.1 se limita a REG-001–REG-008 y no constituye permiso general para publicar futuros borradores.
- Las normalizaciones estructurales preservan texto, reglas, terminología, títulos, IDs, slugs y referencias; cualquier cambio semántico se tramita como revisión editorial versionada.
- El equipo frontend mantiene estructura, accesibilidad y presentación; no altera la fuente editorial para resolver necesidades de contenido.
- Los cambios con impacto cruzado requieren coordinación y actualización documental en el mismo bloque.

## 8. Elección entre `knowledge/` y backend CMS

Debe utilizarse `knowledge/` cuando el contenido sea canónico, estable, revisable mediante Git, no necesite publicación inmediata por un administrador y forme parte del reglamento, vocabulario, historia o pedagogía estable.

Debe utilizarse el backend CMS cuando el contenido cambie con frecuencia,
necesite borradores, programación, permisos editoriales, enlaces controlados a
documentos, fechas, convocatorias, noticias o actualización sin despliegue. El
CMS actual no almacena archivos: una futura subida requiere un contrato propio.

Una misma pieza no puede mantenerse de forma editable en ambos canales. Si un contenido combina partes estables y operativas, se divide por responsabilidad y se conectan ambas fuentes en la interfaz sin duplicarlas.

React, seeders y páginas CMS duplicadas no son fuentes alternativas. Los seeders pueden preparar datos controlados, pero no deben convertirse en una segunda edición de contenido vivo.

## 9. Definición previa de nuevas secciones

Antes de implementar una sección pública se deben definir y documentar:

1. finalidad;
2. audiencia;
3. fuente de verdad;
4. quién puede modificar el contenido;
5. necesidad de administración Blade;
6. necesidad de persistencia;
7. necesidad de API;
8. borradores, publicación y visibilidad;
9. slugs y URLs estables;
10. imágenes, vídeos y documentos;
11. permisos;
12. tests backend, frontend y E2E;
13. documentación afectada;
14. mecanismo para evitar duplicación entre fuentes.

## 10. Flujo administrativo Blade

Una sección administrable se implementa como bloque vertical completo, adaptado a la arquitectura existente:

1. modelo y migración, si son necesarios;
2. Form Requests;
3. Actions o Services;
4. CRUD Blade;
5. autorización administrativa;
6. estados de publicación y visibilidad;
7. slugs estables;
8. API Resource;
9. endpoint o controlador público;
10. exclusión de borradores en backend;
11. integración React;
12. tests backend;
13. tests frontend;
14. E2E;
15. documentación.

Blade es la interfaz administrativa oficial. No se creará un segundo panel administrativo en React.

## 11. Estados de publicación

Todo contenido editorial administrable debe definir de forma explícita sus estados y transiciones. Como mínimo se debe decidir si necesita borrador, publicación, despublicación, programación, archivo y vista previa.

El CMS actual persiste `draft`, `published` y `published_at` con esta semántica:

- `draft`: puede estar vacío y nunca es visible públicamente;
- `published` con `published_at = null`: publicación inmediata;
- `published` con fecha pasada o igual al momento actual: publicada;
- `published` con fecha futura: Programada, como estado de presentación derivado y no persistido.

Una página necesita al menos un bloque válido para pasar a `published`. El último bloque de una página con ese estado no puede eliminarse hasta que vuelva expresamente a borrador. El listado y el acceso directo por `slug` aplican el mismo filtro temporal en backend. El formulario interpreta `published_at` según `config('app.timezone')` y comunica esa zona al administrador.

## 12. Seguridad editorial

- La autorización se aplica en backend tanto a pantallas como a acciones.
- Los datos públicos se seleccionan mediante Resources específicos.
- El endpoint público excluye borradores, publicaciones futuras y contenido no visible.
- Los flujos administrativos impiden publicar páginas vacías y conservar una página `published` sin bloques.
- React no recibe contenido prohibido para ocultarlo después.
- El acceso directo a una URL no elude el estado de publicación.
- Los bloques estructurados no admiten HTML ejecutable o arbitrario.
- Los permisos, cambios de estado, archivos y acciones sensibles deben quedar cubiertos por pruebas y, cuando el producto lo requiera, trazabilidad.

## 13. Slugs y URLs estables

- Cada colección define reglas de unicidad y formato antes de publicar.
- Un slug no debe cambiar por una corrección cosmética sin valorar enlaces existentes.
- Los cambios de URL requieren inventario de consumidores, enlaces internos, navegación, SEO y estrategia de migración.
- Las relaciones editoriales canónicas usan IDs estables cuando exista un contrato para ellos.
- Las rutas conceptuales de este documento no autorizan su creación inmediata.

## 14. Multimedia y persistencia

Los recursos estáticos pequeños y adecuados para versionado pueden formar parte del repositorio. Los archivos cargados desde Blade no pueden depender del filesystem efímero del despliegue: antes de habilitar cargas en producción debe definirse almacenamiento persistente y desacoplado.

Cada tipo de archivo debe definir permisos, propietario, procedencia, licencia, texto alternativo, sustitución, borrado y limpieza de huérfanos. Los vídeos pesados no se almacenarán normalmente en Git. Esta fase no implementa almacenamiento externo.

Para Escuela, una imagen o galería debe registrar además el responsable editorial y la posibilidad de retirada. Los bloques CMS actuales basados en URLs no resuelven procedencia, consentimiento, texto alternativo individual de galería o ciclo de vida. La portada, el orden y cualquier medio con menores se omiten hasta disponer de permisos verificables; las subidas administrativas no se guardarán en Git.

Los patrocinadores de 7F.2C son contenido institucional funcional administrado
en Blade/MariaDB, no CMS editorial, Knowledge ni publicidad de terceros. Cada
`Sponsor` tiene nombre público, logo privado, web HTTPS opcional, orden,
activación y ventana temporal. Todos los efectivos aparecen simultáneamente
antes del footer; el nombre aporta el alt y el enlace se cualifica como
`sponsored noopener noreferrer`. No hay copy comercial, HTML, CTA, placement,
rotación, métricas, scripts o cookies. La procedencia/licencia del logo sigue
siendo responsabilidad editorial del club antes de activarlo.

La foto de perfil de 7F.2D es un dato funcional privado de la cuenta `User`, no
contenido editorial, identidad deportiva, CMS, Knowledge ni material de
Sponsor. El propio usuario la carga y retira; el backend normaliza, elimina
metadata y controla sustitución/borrado sobre storage privado. React sólo
mantiene el blob y su object URL en memoria. Subir una foto no autoriza su
publicación, tampoco para una persona adulta, y
`public_competition_identity` no cubre imágenes de menores. Cualquier uso
público futuro exige finalidad, consentimiento y gate propios.

## 15. Contenido relacionado con menores

La Escuela puede tratar imágenes o información de menores. La identidad
deportiva dispone desde 7D.2C2A de autorización verificable únicamente para
`public_competition_identity`, con versión, confirmación, revisión y retirada.
No autoriza imágenes, vídeos, redes o publicidad. Antes de publicar cualquiera
de esos medios se deben definir su propia finalidad, alcance, vigencia,
responsables, privacidad, retirada y canal. La ausencia de autorización o
procedencia clara impide incorporarlos.

Las vistas públicas, metadatos, galerías y documentos deben minimizar datos personales y evitar información que permita localizar o perfilar innecesariamente a un menor.

Una futura solicitud escolar recogerá únicamente los datos confirmados por su proceso real, usará Resources separados por contexto, respuestas que no permitan enumeración, limitación de frecuencia propia y acceso administrativo restringido. No se exigirá una cuenta o perfil `Player` por analogía, no se registrará el contenido personal en logs ordinarios y no se admitirán adjuntos en su primera versión.

## 16. Integración frontend/backend

- React consume contenido dinámico mediante servicios, no mediante llamadas Axios dispersas en componentes.
- Los endpoints se verifican antes de crear consumidores.
- Los Resources constituyen el contrato de salida y entregan solo información publicable.
- Las vistas remotas contemplan `loading`, `error`, `empty` y `content`.
- Las futuras landings reutilizan contenedor, cabecera, acciones, secciones y destinos de `PublicLanding` sin convertir esos componentes en fuente editorial o adaptador de datos.
- Los estados remotos comunes sólo se abstraen cuando al menos dos consumidores compartan semántica y comportamiento. Fases 4A–4C mantienen ciclos específicos por recurso; compartir composición o navegación contextual no los convierte en una abstracción remota global.
- Los artefactos de `knowledge/` se validan y generan mediante los comandos build-time de 5A–5B; no se copian manualmente a JSX. El compilador exige un H1 inicial único, jerarquía coherente y referencias desde documentos vigentes exclusivamente hacia destinos vigentes. React no importa `knowledge.json`: consume sólo `public-knowledge.json`, ya filtrado y transformado a nodos seguros. `dev` y `build` no se acoplan todavía porque falta confirmar el contexto de CI/despliegue.
- Las rutas públicas mantienen estabilidad, accesibilidad, navegación por teclado y comportamiento responsive.
- Los enlaces y disclosures editoriales, sus rutas y familias activas respetan
  `09-public-navigation.md`; la cuenta permanece fuera del árbol editorial.
- Eliminar un enlace del primer nivel no elimina su URL. Aliases, canonical y redirects se aplican sólo tras paridad y pruebas.
- Las features pesadas valoran lazy loading para proteger el bundle inicial.

## 17. Requisitos de testing

El alcance concreto depende del riesgo, pero una sección administrable debe valorar:

- tests Feature de autorización, validación, estados, fechas y unicidad de slugs;
- exclusión de borradores y publicaciones futuras en listados y acceso directo;
- contrato de Resources y ausencia de campos administrativos;
- tests frontend de estados remotos y renderizado por tipo;
- integración entre servicio, ruta y vista;
- E2E para publicación administrativa y consumo público cuando el flujo sea crítico;
- accesibilidad, teclado y responsive;
- validación del contrato editorial, headings, relaciones por estado, slugs, privacidad, parser seguro y sincronía de los dos artefactos de `knowledge/`; la cubren KNOWLEDGE-COMPILER-1, KNOWLEDGE-PUBLICATION-READINESS-1 y KNOWLEDGE-PUBLIC-CONSUMER-1.

Las pruebas existentes del CMS básico se documentan en `05-testing.md`. Las pruebas anteriores son requisitos para futuras ampliaciones y no implican que todas estén implementadas hoy.

## 18. Migración de la sección legada Contenidos

La migración se realizará de forma incremental y sin borrar contenido antes de auditarlo:

1. inventariar páginas, bloques, slugs, estados, enlaces y consumidores;
2. identificar pruebas, borradores, contenido vigente y duplicados;
3. asignar cada pieza a Inicio, Aprende a jugar, Escuela, Club u otra fuente aprobada;
4. elegir una única fuente canónica para cada contenido;
5. definir URLs objetivo y compatibilidad;
6. migrar por secciones con pruebas;
7. retirar navegación y rutas legadas solo cuando no tengan consumidores ni contenido pendiente.

La Fase 3A no elimina `/contenidos`, no crea redirects, no cambia su API ni borra páginas. Sólo fija el contrato que deberá guiar esa migración.

## 19. Cuestiones pendientes de implementación o decisión

- Inventario editorial de los datos reales de cada entorno antes de migrarlos; el catálogo del seeder no sustituye ese inventario.
- Resolución y compatibilidad de Nosotros entre página estática y CMS.
- Contenido real de `academy` por entorno, clasificación de sus piezas,
  consumidores y momento futuro de migración, despublicación o redirect.
- Canal organizativo público de contacto de Escuela.
- Primera capa y consentimiento operativo de los formularios antes de activar
  recogidas productivas; las políticas generales publicadas no sustituyen esa
  implementación ni autorizan por sí solas nuevos tratamientos.
- Política de conservación y borrado extraordinario de solicitudes.
- Reglas futuras para reinscripciones complejas o varios programas públicos.
- Existencia y responsable editorial de material pedagógico suficiente para una colección de Escuela.
- Carga, revisión y publicación de los cuatro slugs Club en cada entorno; la
  carga local manual y los fixtures E2E no acreditan producción. El formulario
  continuará desactivado hasta superar privacidad y operación.
- Necesidades editoriales de noticias, actividades, galerías, documentos y formularios.
- Estrategia de almacenamiento persistente y ciclo de vida de archivos.
- Modelo de consentimiento y privacidad para contenido de menores.
- Integración automática de las canalizaciones de Knowledge en CI/despliegue;
  Legal ya bloquea el build cuando su proyección no está sincronizada.
- Gates productivos de Contacto en 7F e imágenes en un frente independiente; aliases tras paridad y,
  posteriormente, redirects permanentes, canonical, sitemap, 404 HTTP y SEO
  completo. Las cuatro URLs Club ya se descubren desde Navbar/footer en 7D.1.
- Operación productiva, renovación y retirada de las autorizaciones de identidad
  de menores implementadas en 7D.2C2A.
- Roles, permisos, trazabilidad y vista previa requeridos por los editores.

## 20. Gates editoriales y operativos del MVP completo

La auditoría `MVP-PARITY-AUDIT-1` confirma que el código no acredita por sí solo
contenido real ni preparación de producción. Antes de cerrar el MVP:

| Área | Fuente única | Aportación humana | Gate de publicación |
|---|---|---|---|
| Quiénes somos e historia | CMS | Copy, vigencia, imágenes y responsables | Sin placeholders ni duplicidad JSX |
| Contacto institucional | CMS + `ContactRequest` + aviso Git versionado | Canal oficial atendido, privacidad, retención y operación | Contenido en CMS; formulario fail-closed y desactivado hasta configuración productiva |
| Federarse | CMS | Proceso real y responsable | Destino vigente |
| Documentos | CMS con URLs controladas | Piezas, vigencia, procedencia | Ciclo de vida y acceso verificados |
| Prensa/Federaciones | CMS | Contenido real | Footer/secundaria, nunca tarjeta vacía |
| Legal y privacidad | `legal/` | Revisión responsable/jurídica y versionado | Tres rutas y footer implementados; toda nueva versión debe pasar compilador y revisión |
| Escuela operativa | Dominio Laravel | Programa, contenido, contacto operativo, niveles, horarios y ubicación | 7E preparada; cargar datos reales y mantener flag cerrada hasta 7F |
| Reglas y modalidad | Knowledge | Revisión editorial existente | No copiar en CMS o React |
| Mensajes de interfaz | React | Microcopy funcional | No convertirse en fuente editorial |

`/nosotros` hardcodeado y `/contenidos/nosotros` no deben coexistir como fuentes
editables definitivas. La migración a Club será conservadora y mantendrá las
rutas legadas hasta acreditar paridad y compatibilidad. `academy` permanece
fuera de esa migración.

ADR-033 y `15-mvp-editorial-and-navigation-contract.md` cierran la arquitectura
de Aprende, Club, Cuenta, rutas canónicas y footer. ADR-034 sustituye únicamente
su decisión inicial de Contacto sin formulario por persistencia local,
notificación opcional y desactivación por defecto. 7C.2 implementa la interfaz
condicionada sin autorizar activación productiva o publicación editorial.
7E incorpora la primera capa específica de inscripción, la conservación y la
disponibilidad centralizada. La aceptación humana, los datos reales, el correo
y la activación productiva permanecen pendientes de 7F/7G.

## 21. Plantillas, revisión y vigencia del MVP

Cada pieza institucional debe registrar antes de publicarse:

- responsable editorial;
- fuente o persona que acredita el contenido;
- fecha de revisión;
- fecha de vigencia cuando describa un proceso, coste, documento o requisito;
- próxima revisión o evento que obliga a revisarla;
- imágenes con procedencia, licencia/consentimiento y texto alternativo;
- enlaces con propietario y comprobación;
- estado de aprobación legal cuando corresponda.

Plantillas mínimas:

- Quiénes somos: nombre oficial, presentación, propósito, actividad, historia,
  organización, cargos opcionales e imágenes;
- Contacto: correo, teléfono opcional, ubicación, horario, departamento y
  enlaces oficiales;
- Federarse: proceso, requisitos, organismo, enlaces, contacto, documentos,
  costes confirmados y vigencia;
- Documentos: nombre, tipo, propósito, URL, versión, fecha, vigencia,
  responsable, accesibilidad, tamaño y formato;
- Escuela: datos operativos Blade y contenido editorial separados;
- Home: propuesta de valor, audiencia, CTAs, fuente de cada claim e imágenes.

Privacidad, aviso legal, cookies cuando apliquen, registro, inscripción School
e identidad deportiva requieren responsable de la entidad y revisión
profesional o jurídica. Las plantillas completas, matriz legal, checklist
School y gates están en `15-mvp-editorial-and-navigation-contract.md`.

## 22. Fuentes legales y privacidad tras 7D.2C1

La identidad institucional usa fuentes con precedencia explícita: documento
jurídico original, dato legal confirmado por el club, acuerdo formal,
documentación administrativa vigente, CMS, documentación técnica, tradición e
inferencia. La matriz vigente se mantiene en
`20-legal-privacy-and-cookies-readiness.md`.

Los borradores de `docs/legal-drafts/` son historia interna no vigente y no se
cargan en CMS ni runtime. La fuente pública es `legal/`, validada y compilada a
un artefacto React sin Markdown crudo. `EST-REF-001` es una referencia
histórica restringida bajo `knowledge/referencias/`, fuera del Manual y sin
vigencia jurídica inferida. Reglamento/Manual y estatutos del club son fuentes
distintas.

El CMS institucional no es destino de estos textos jurídicos. CIF y domicilio social están confirmados
por el club como datos legales y administrativos; no constituyen por sí solos
una certificación registral. Representación legal general, registro deportivo,
inscripción y mandato de la Junta, bases, conservación, destinatarios,
encargados y transferencias productivas no se completan por inferencia. El teléfono
confirmado es privado y no pertenece al CMS ni a la interfaz pública.

## 23. Identidad deportiva pública tras 7D.2B y 7D.2C2A

La identidad en datos funcionales de Competición es una proyección de dominio,
no contenido editorial. Laravel decide `public_display_name` y los Resources
anónimos exponen únicamente campos deportivos allowlisted; React sólo presenta
esa cadena. Adultos usan alias o nombres de pila más inicial del primer
apellido. Menores, nacimiento ausente o datos insuficientes quedan en
`Participante`, salvo que Laravel acredite una autorización efectiva de
`alias` o `name_initial`. La evidencia nace opcionalmente desde Escuela, se
vincula de forma manual e inequívoca, se confirma, revisa y puede revocarse. La
fuente del aviso es `legal/notices/public-identity-minors.md`; ni el aviso ni la
evidencia pertenecen a CMS, Knowledge o JSX.

Esta política no se aplica a la Junta: nombres y cargos institucionales siguen
perteneciendo al CMS y requieren su propio control editorial. 7D.2B no publica
los borradores legales, imágenes, estatutos ni datos institucionales, y no
habilita Contacto. 7D.2C2A tampoco autoriza ni publica imágenes.

## 24. Contacto tras 7D.2C2B

El copy institucional continúa exclusivamente en el CMS. La primera capa es
una fuente legal Git independiente (`NOTICE-CONTACT-FORM`) y los datos
transaccionales viven sólo en Laravel/MariaDB. React consume ambas proyecciones,
pero no edita ni duplica ninguna. Los datos no pasan a Knowledge, seeders de
desarrollo, componentes editoriales o páginas legales nuevas.

La persistencia acredita recepción. El correo auxiliar, estados, reintentos,
historial, holds y purga son dominio operativo privado. La API pública limita
la configuración a disponibilidad e identificación del aviso, y el acuse a
`received`. Producción permanece cerrada hasta los gates de 7F.

## 25. Metadata e indexación tras 7D.3

El manifiesto SEO no crea una quinta fuente editorial. React consume títulos,
summaries y rutas de CMS, Knowledge y Legal, mientras el dominio deportivo sólo
aporta datos funcionales y recibe metadata genérica para evitar identidad
personal. Las descripciones estáticas del manifiesto son copy de interfaz y no
sustituyen contenido administrable o canónico.

Los aliases conservan compatibilidad, pero canonical y sitemap señalan sólo las
fachadas adoptadas. El CMS genérico permanece `noindex`; no se copia su cuerpo
en JSX ni en el generador. `robots.txt` y `sitemap.xml` son proyecciones de
build dependientes de configuración, no fuentes tracked ni mecanismos de
autorización. El contrato completo está en
`25-public-seo-accessibility-and-indexing.md`.

## 26. Noticias dedicadas tras 7F.2E

Noticias tiene una fuente editorial única en MariaDB mediante `NewsArticle` y
una interfaz oficial en Blade. No pertenece a `CmsPage`/`CmsBlock`, Knowledge,
JSX, seeders ordinarios ni `/contenidos/prensa-media`. React consume una
proyección pública cerrada y no es editor ni copia del contenido.

La publicación combina estado `published`, fecha efectiva y ausencia de soft
delete. El extracto es manual y el cuerpo es texto plano. La portada reside en
storage privado bajo una key opaca; sólo se publica su ruta Laravel estable,
dimensiones, alt y crédito opcional. Procedencia, confirmación, responsable y
object key son trazabilidad privada. Reemplazar la portada reinicia el gate de
derechos y la confirmación sólo registra evidencia revisada: no crea
consentimiento ni reutiliza avatar o identidad deportiva.

El índice y detalle React, la API, metadata y sitemap son proyecciones de esta
fuente. El sitemap build-time incluye `/noticias`; resolver slugs runtime en un
sitemap dinámico queda como deuda P1. Tags, categorías, autoría pública,
newsletter, rich text y navegación CMS pertenecen a evoluciones posteriores.

## Mantenimiento

Toda nueva fuente o sección pública debe actualizar esta gobernanza antes o junto con su implementación. Si el comportamiento real difiere de la decisión aprobada, se debe registrar de forma explícita el estado, la deuda y el plan de reconciliación.


---

**Nota de seguimiento posterior (Fase 7F.2):** Tras la aceptación de staging, ciertas decisiones (como el modelo de navegación en Competición y el aplazamiento de noticias y multimedia persistente) han sido promovidas o refinadas en la Fase 7F.2. Ver `docs/28-preproduction-product-refinement.md` y `ADR-042`.
