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

**Estado tras 5B:** existen contrato editorial, validación, compilador determinista, artefacto canónico completo y proyección pública versionada con los 40 documentos `Vigente` de cuatro colecciones. REG-001–REG-008 han recibido aprobación editorial humana como Reglamento inicial. React importa sólo la proyección, mediante un repositorio y renderer de nodos seguros, y publica la landing de Aprende, el Manual y sus documentos sin API Laravel ni CRUD Blade.

La normalización técnica sólo puede cambiar estructura expresamente autorizada, nunca reformular reglas, términos o referencias. Las revisiones editoriales futuras deberán ser conscientes, actualizar `ultima_revision` y revisar la versión conforme al alcance semántico del cambio. Un documento `Vigente` sólo puede referenciar otro documento `Vigente`; un borrador permanece en el artefacto canónico, pero ni él ni sus metadatos, rutas o referencias pueden entrar en la proyección o el bundle.

## 5. Arquitectura pública aprobada

La navegación pública de primer nivel queda contratada en cinco rutas:

- Inicio (`/`);
- Competición (`/competicion`);
- Aprende a jugar (`/aprende-a-jugar`);
- Escuela de Galotxas (`/escuela`);
- Club (`/club`).

La identidad del usuario, Mi Panel y el cierre de sesión permanecerán en una zona autenticada separada.

Estas áreas son la arquitectura objetivo. Tras 5B están registradas `/`, la landing dinámica `/competicion` y `/aprende-a-jugar`, y las tres forman el Navbar editorial actual. Competición utiliza datos públicos reales; Aprende consume la proyección compilada de Knowledge. Escuela y Club no se consideran implementadas por aparecer en documentación ni se muestran como enlaces deshabilitados.

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

### Escuela de Galotxas

Sección pública propia para niños, familias, centros educativos, docentes, monitores, iniciación, programas educativos y actividad real de la Escuela. No es una subsección del Manual y no debe denominarse públicamente “Academy”, salvo para explicar una referencia legada durante la migración.

Su arquitectura será híbrida:

- presentación, metodología, ejercicios y recursos pedagógicos estables desde una futura colección de `knowledge/`;
- actividades, convocatorias, noticias, fechas, centros, galerías, documentos e inscripciones desde el backend CMS.

La ruta canónica futura es `/escuela`. No se aprueba `/manual/academy`. Las posibles subáreas se definirán cuando exista alcance funcional y contenido real.

### Club

Agrupa Nosotros, Federarse, Federaciones, Prensa y medios y Contacto. Su contenido administrable tendrá como fuente el backend CMS. El código garantiza actualmente los slugs sembrados `nosotros`, `federarse`, `federaciones` y `prensa-media`; no existe un slug sembrado `contacto`. También existe `documentos`, cuya asignación definitiva requiere clasificación. La duplicidad entre `/nosotros` estático y `/contenidos/nosotros` se resolverá a favor del CMS sólo después de migrar el contenido y aprobar compatibilidad.

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
| Escuela: actividad | Backend CMS | Administrador | Sí | Sí | Operativa |
| Club | Backend CMS | Administrador | Sí | Sí | Institucional |
| Prensa y medios | Backend CMS genérico auditado; contrato específico pendiente | Administrador | Genérico actual | Genérica actual | Editorial |
| Contenidos legado | Backend CMS | Administrador | Existente y auditado | Existente y auditado | Legada |

“Sí” expresa el flujo aprobado o el módulo deportivo actual según la fila; no garantiza que una sección editorial concreta ya esté implementada. La Fase 1 verificó las capacidades y límites del CMS genérico; cada vertical futura todavía debe definir y probar su contrato específico.

## 7. Responsables de edición

- El equipo de dominio y administración deportiva modifica reglas ejecutables y datos competitivos en backend o mediante los flujos Blade autorizados.
- Los administradores editoriales modifican contenido CMS desde Blade, dentro de sus permisos.
- Las personas responsables del conocimiento editan `knowledge/` mediante Git, revisión y validación editorial.
- Esas personas ejecutan `knowledge:check`, regeneran los artefactos con `knowledge:build` y entregan fuente y JSON juntos; nunca editan archivos de `generated/` a mano.
- Un cambio de estado requiere aprobación editorial. La autorización de 5A.1 se limita a REG-001–REG-008 y no constituye permiso general para publicar futuros borradores.
- Las normalizaciones estructurales preservan texto, reglas, terminología, títulos, IDs, slugs y referencias; cualquier cambio semántico se tramita como revisión editorial versionada.
- El equipo frontend mantiene estructura, accesibilidad y presentación; no altera la fuente editorial para resolver necesidades de contenido.
- Los cambios con impacto cruzado requieren coordinación y actualización documental en el mismo bloque.

## 8. Elección entre `knowledge/` y backend CMS

Debe utilizarse `knowledge/` cuando el contenido sea canónico, estable, revisable mediante Git, no necesite publicación inmediata por un administrador y forme parte del reglamento, vocabulario, historia o pedagogía estable.

Debe utilizarse el backend CMS cuando el contenido cambie con frecuencia, necesite borradores, programación, permisos editoriales, archivos administrables, fechas, convocatorias, noticias o actualización sin despliegue.

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

## 15. Contenido relacionado con menores

La Escuela puede tratar imágenes o información de menores. Antes de publicar se deben definir autorización verificable, finalidad, alcance, vigencia, responsables, privacidad, retirada y canales de respuesta. La ausencia de consentimiento o procedencia clara impide incorporar el material.

Las vistas públicas, metadatos, galerías y documentos deben minimizar datos personales y evitar información que permita localizar o perfilar innecesariamente a un menor.

## 16. Integración frontend/backend

- React consume contenido dinámico mediante servicios, no mediante llamadas Axios dispersas en componentes.
- Los endpoints se verifican antes de crear consumidores.
- Los Resources constituyen el contrato de salida y entregan solo información publicable.
- Las vistas remotas contemplan `loading`, `error`, `empty` y `content`.
- Las futuras landings reutilizan contenedor, cabecera, acciones, secciones y destinos de `PublicLanding` sin convertir esos componentes en fuente editorial o adaptador de datos.
- Los estados remotos comunes sólo se abstraen cuando al menos dos consumidores compartan semántica y comportamiento. Fases 4A–4C mantienen ciclos específicos por recurso; compartir composición o navegación contextual no los convierte en una abstracción remota global.
- Los artefactos de `knowledge/` se validan y generan mediante los comandos build-time de 5A–5B; no se copian manualmente a JSX. El compilador exige un H1 inicial único, jerarquía coherente y referencias desde documentos vigentes exclusivamente hacia destinos vigentes. React no importa `knowledge.json`: consume sólo `public-knowledge.json`, ya filtrado y transformado a nodos seguros. `dev` y `build` no se acoplan todavía porque falta confirmar el contexto de CI/despliegue.
- Las rutas públicas mantienen estabilidad, accesibilidad, navegación por teclado y comportamiento responsive.
- Las cinco áreas, sus rutas y familias activas respetan `09-public-navigation.md`; la cuenta permanece fuera del árbol editorial.
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
- Uso y destino editorial de `academy` y clasificación de `documentos`.
- Creación de Contacto: no existe slug sembrado ni contenido verificable actual.
- Necesidades editoriales de noticias, actividades, galerías, documentos y formularios.
- Estrategia de almacenamiento persistente y ciclo de vida de archivos.
- Modelo de consentimiento y privacidad para contenido de menores.
- Consumo React, renderer, rutas públicas e integración automática de la canalización con CI/despliegue.
- URLs de detalle, aliases, redirects, canonical, sitemap, 404 y SEO de la migración pública.
- Roles, permisos, trazabilidad y vista previa requeridos por los editores.

## Mantenimiento

Toda nueva fuente o sección pública debe actualizar esta gobernanza antes o junto con su implementación. Si el comportamiento real difiere de la decisión aprobada, se debe registrar de forma explícita el estado, la deuda y el plan de reconciliación.
