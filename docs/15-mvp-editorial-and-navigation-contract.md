# Contrato editorial y de navegación del MVP

## 1. Propósito

Este documento registra `MVP-EDITORIAL-NAVIGATION-CONTRACT-1`, el cierre
documental de la Fase 7B. Convierte las recomendaciones de la auditoría 7A en
un contrato verificable para las fases 7C–7G.

El contrato define:

- la navegación editorial final del MVP;
- las URLs institucionales canónicas y su convivencia temporal con el legado;
- la fuente de verdad de cada contenido;
- las plantillas que deben completar responsables humanos;
- los gates editoriales, legales, de privacidad y operación;
- el orden de implementación y validación restante.

No implementa rutas, componentes, menús, aliases, redirects, páginas CMS,
contenido, datos, despliegue ni pruebas funcionales.

## 2. Estado

Fase 7B queda **cerrada documentalmente**. Fase 7 continúa abierta y el MVP
permanece pendiente hasta completar y aceptar 7C–7G.

Quedan aprobadas como arquitectura objetivo:

- navegación con enlaces directos Inicio y Competición;
- grupo de revelación `Aprende`;
- grupo de revelación `Club`;
- cuenta separada;
- cuatro rutas institucionales canónicas bajo `/club`;
- CMS como fuente institucional;
- Contacto CMS con formulario local opcional, desactivado hasta aprobar
  privacidad y operación según ADR-034;
- footer global con un núcleo obligatorio y enlaces condicionales;
- migración conservadora mediante aliases temporales antes de redirects.

Seguimiento 7D.2A: para participantes adultos se aprueba alias deportivo y, en
su ausencia, nombre más inicial del primer apellido. La regla de menores, la
implementación backend y la revisión jurídica siguen abiertas. Los borradores
legales internos no son textos aprobados ni publicables. El club confirma CIF,
domicilio social, fecha de constitución, presidente/responsable web y Junta
publicable; siguen abiertas la representación legal general, las acreditaciones
registrales y el mandato de la Junta.

Seguimiento 7D.2B: Laravel aplica ya la regla adulta mediante
`public_display_name` y Resources cerrados. Como no existe autorización
específica de identidad para menores, éstos y los casos sin fecha de nacimiento
usan una etiqueta neutra fail-closed. React no reconstruye nombres, el perfil
autenticado deja de persistirse y las cargas automáticas a proveedores de
fuentes y jsDelivr se eliminan. La publicación legal, la activación de Contacto
y una eventual identidad autorizada de menores permanecen como gates de
7D.2C; Fase 7 y el MVP siguen abiertos.

Seguimiento 7D.2C1: los tres textos aprobados se publican desde la fuente Git
`legal/` mediante una proyección build-time propia y rutas exactas enlazadas en
el footer. Los borradores quedan como historia interna. Contacto continúa
desactivado y 7D.2C2, correo, consentimiento verificable de menores, imágenes,
despliegue, Fase 7 y MVP permanecen abiertos.

Seguimiento 7D.2C2A: el consentimiento verificable de identidad de menores se
implementa como autorización específica `public_competition_identity`,
independiente de Escuela, con aviso versionado, confirmación, revisión,
conformidad 14–17 y revocación. Laravel mantiene `Participante` ante cualquier
duda y React sólo presenta `public_display_name`. Correo productivo, Contacto,
imágenes, 7D.2C2B, despliegue, Fase 7 y MVP siguen abiertos.

Seguimiento 7D.2C2B: Contacto incorpora primera capa versionada, consentimiento
trazable, config fail-closed, correo auxiliar, retención, holds y anonimización.
El CMS y la navegación no cambian. La capacidad técnica y 7D.2 quedan cerradas,
pero producción continúa desactivada; en ese punto 7D.3, imágenes, 7F, Fase 7 y
MVP seguían abiertos. El seguimiento 34 registra el cierre posterior de 7D.3.

En el momento del cierre documental de 7B el código no cambió: el Navbar
continuaba plano, no existía `/club`, las rutas canónicas institucionales no
estaban registradas y el footer global no estaba implementado.

## 3. Decisiones heredadas

Este contrato conserva las siguientes decisiones:

- Laravel es la fuente de verdad del dominio funcional.
- React presenta estructura, navegación y copy de interfaz; no es una fuente
  editorial administrable.
- El CMS Blade persiste y publica contenido institucional mediante la base de
  datos y la API pública.
- `knowledge/` es la fuente canónica del Manual y del conocimiento estable del
  juego.
- Escuela mantiene dominio Laravel propio y ruta independiente. Su agrupación
  bajo Aprende mejora el descubrimiento, pero no la convierte en contenido de
  Knowledge ni en subsección técnica del Manual.
- La cuenta no pertenece al menú editorial.
- Las URLs deportivas actuales se conservan.
- `/contenidos` y `/contenidos/:slug` son infraestructura heredada, no la
  arquitectura pública final.
- `academy` no equivale a Escuela y no se migra por similitud nominal.
- No se publica una ruta sin contenido funcional y fuente acreditada.
- Un alias temporal, un redirect HTTP y una URL canónica son decisiones
  distintas.

ADR-033 sustituye únicamente la topología plana y la landing `/club`
contempladas en ADR-028. El resto de sus decisiones de compatibilidad,
separación de cuenta, conservación deportiva y fuentes continúa vigente.

## 4. Navegación final propuesta

La propuesta única aprobada para el MVP es:

```text
Inicio                                  /
Competición                             /competicion
Aprende                                 disclosure
├── Aprende a jugar                     /aprende-a-jugar
├── Manual y reglas                     /aprende-a-jugar/manual
└── Escuela de Galotxas                 /escuela
Club                                    disclosure
├── Quiénes somos                       /club/quienes-somos
├── Contacto                            /club/contacto
├── Federarse                           /club/federarse
└── Documentos                          /club/documentos
Cuenta                                  grupo separado
```

`Inicio` y `Competición` son enlaces. `Aprende` y `Club` son botones de
revelación sin destino propio. La marca puede seguir enlazando Inicio.

Desktop y móvil deben consumir una única configuración y mantener nombres,
orden, rutas, permisos y cálculo activo idénticos. Sólo cambia la presentación:
submenú en desktop y grupo anidado o acordeón dentro del menú móvil.

Reglas comunes:

- el click o activación del padre abre o cierra su grupo;
- no existe interacción exclusiva por `hover`;
- al abrir un grupo se cierra el otro;
- navegar cierra grupos y menú móvil;
- Escape cierra primero el grupo abierto y devuelve foco a su disparador;
- si no hay grupo abierto, Escape cierra el menú móvil y devuelve foco al botón
  `Menú`;
- cerrar el menú móvil cierra también sus grupos;
- el orden de tabulación sigue el orden visual;
- el foco siempre es visible;
- los botones declaran `aria-expanded` y `aria-controls`;
- el enlace exacto usa `aria-current="page"`;
- una rama descendiente conserva estado visual sin recibir `aria-current`;
- el botón padre activo se diferencia visualmente, pero no recibe
  `aria-current` porque no representa una página.

## 5. Aprende

La etiqueta exacta es **Aprende**. Es breve, funciona como categoría y evita
que el padre se confunda con la landing hija “Aprende a jugar”.

Orden y responsabilidad:

1. **Aprende a jugar** — `/aprende-a-jugar`: landing orientativa y puerta de
   entrada a las colecciones.
2. **Manual y reglas** — `/aprende-a-jugar/manual`: catálogo documental; sus
   documentos mantienen las rutas ya implementadas.
3. **Escuela de Galotxas** — `/escuela`: programa operativo independiente.

Estado activo:

- `/aprende-a-jugar` activa exactamente “Aprende a jugar”;
- `/aprende-a-jugar/manual` y cualquier documento descendiente activan
  “Manual y reglas”;
- `/escuela` y cualquier futura ruta escolar descendiente activan “Escuela de
  Galotxas”;
- cualquiera de esas ramas activa visualmente el padre `Aprende`.

No se fusionan rutas, datos ni fuentes. Aprende a jugar y Manual proceden de la
proyección compilada de `knowledge/`; Escuela consume Laravel operativo y sólo
podrá combinar contenido pedagógico futuro cuando exista una fuente aprobada.

## 6. Club

### Etiqueta

| Alternativa | Ventaja | Problema | Decisión |
|---|---|---|---|
| Club | Breve, reconocible y agrupa entidad, pertenencia y servicios | Requiere que la identidad oficial confirme esa denominación | Elegida |
| La entidad | Precisa en contexto jurídico | Abstracta y administrativa para navegación pública | Descartada |
| Sobre nosotros | Cercana | Duplica “Quiénes somos” y no abarca contacto, federación o documentos | Descartada |
| Información | Flexible | Demasiado genérica; no expresa pertenencia institucional | Descartada |

La etiqueta contractual es **Club**. El nombre oficial completo de la entidad
debe confirmarse por separado y aparecer en contenido, legal y footer.

### Comportamiento del padre

Se elige la opción **A: sólo disclosure**.

No se implementará una landing `/club` en el MVP porque los cuatro destinos
cubren las tareas institucionales y no existe contenido independiente que
justifique otra página. Crear la landing duplicaría navegación o produciría un
placeholder.

El grupo se considera activo en las cuatro rutas canónicas y sus descendientes.
Cada hija utiliza `page` o `location` conforme a la regla general. `/club` no
se reserva como redirect mientras no exista un destino único inequívoco.

## 7. Cuenta

Cuenta es un grupo accesible hermano de la navegación editorial, no un quinto
destino editorial ni una hija de Club.

Estado esperado:

- visitante: `Iniciar sesión`;
- autenticado: saludo acotado, `Mi Panel` y botón `Salir`;
- registro, recuperación y restablecimiento permanecen vinculados desde el
  flujo de autenticación;
- las rutas de cuenta no activan Inicio, Competición, Aprende o Club;
- la visibilidad de controles depende de la sesión, no del CMS.

La configuración editorial no debe contener permisos, saludo o acciones de
sesión.

## 8. Rutas canónicas

| Área | URL canónica futura | Fuente | Slug CMS previsto | Fase |
|---|---|---|---|---|
| Quiénes somos | `/club/quienes-somos` | CMS | `nosotros` | 7C |
| Contacto | `/club/contacto` | CMS | `contacto` por crear mediante el flujo editorial | 7C |
| Federarse | `/club/federarse` | CMS | `federarse` | 7C |
| Documentos | `/club/documentos` | CMS | `documentos` | 7C |
| Privacidad | `/privacidad` | CMS o documento controlado aprobado | Por definir al cargar contenido | 7D |
| Aviso legal | `/aviso-legal` | CMS o documento controlado aprobado | Por definir al cargar contenido | 7D |
| Cookies, si aplica | `/cookies` | CMS o documento controlado aprobado | Por definir tras auditoría legal/técnica | 7D |

Las rutas Club son fachadas públicas estables sobre páginas CMS concretas. El
slug de persistencia no forma parte de la URL canónica y puede seguir siendo
`nosotros` durante la migración.

`Prensa y Media` y `Federaciones` no reciben una nueva URL canónica en el MVP.
Permanecen aplazadas y accesibles sólo por sus URLs heredadas si sus páginas
están publicadas.

## 9. Rutas heredadas

| URL heredada o actual | Estado | Alias temporal previsto | Redirect futuro |
|---|---|---|---|
| `/nosotros` | JSX duplicado y no enlazado internamente | A `/club/quienes-somos` tras acreditar paridad | Permanente después de retirar JSX y migrar enlaces |
| `/contenidos/nosotros` | CMS publicado según estado | A `/club/quienes-somos` en 7C | Permanente cuando la canónica esté aceptada |
| `/contenidos/federarse` | CMS genérico | A `/club/federarse` en 7C | Permanente tras paridad y enlaces |
| `/contenidos/documentos` | CMS genérico | A `/club/documentos` en 7C | Permanente tras inventario documental |
| `/contenidos/prensa-media` | CMS genérico | Ninguno en el MVP | Decisión posterior si se crea canónica |
| `/contenidos/federaciones` | CMS genérico | Ninguno en el MVP | Decisión posterior si se crea canónica |
| `/contenidos/academy` | CMS legado independiente | Ninguno | No redirigir a Escuela sin auditoría editorial |
| `/contenidos` | Índice técnico CMS | Ninguno | Retirada/indexación posterior, no 7C |

No existen actualmente `/contacto`, `/federarse` o `/documentos` en raíz. No se
crearán como aliases adicionales: aumentarían las variantes sin aportar
compatibilidad demostrada.

Un alias temporal significa que React resuelve la URL heredada y la canónica
contra la misma página CMS sin cambiar aún la dirección del navegador. Se
implementará sólo con pruebas de paridad, 404 y estado editorial. El redirect
permanente pertenece a servidor/CDN y se aplicará después de:

1. publicar y aceptar la ruta canónica;
2. inventariar y migrar enlaces internos y externos controlables;
3. comprobar contenido, metadatos y estados remotos;
4. definir canonical e indexación;
5. observar la transición y disponer de rollback.

## 10. CMS

El CMS es la fuente única de:

- Quiénes somos;
- Contacto;
- Federarse;
- Documentos y su inventario de enlaces;
- Prensa y Media, si se mantiene;
- Federaciones, si se mantiene;
- privacidad y legal si el responsable elige CMS como soporte controlado.

Capacidad real auditada:

- páginas con slug, título, estado, fecha y metadatos básicos;
- creación en borrador y publicación inmediata o programada;
- bloques `heading`, `text`, `list`, `image`, `gallery`, `button` y
  `document_link`;
- URLs HTTP(S) o rutas internas para imágenes y enlaces;
- administración Blade y lectura pública que excluye borradores y publicaciones
  futuras;
- seis slugs sembrables: `prensa-media`, `nosotros`, `federaciones`, `academy`,
  `documentos` y `federarse`.

Límites:

- no existe taxonomía de área, alias, canonical o vigencia;
- no existe slug `contacto` sembrado;
- no hay subida ni almacenamiento de archivos;
- imágenes, galerías y documentos son referencias URL;
- el índice `/contenidos` mezcla toda página publicada;
- el seeder crea descripciones iniciales genéricas y no acredita contenido real.

React compondrá rutas, estados, semántica y navegación; no copiará el cuerpo
institucional a JSX. `knowledge/` no recibirá contenido institucional.

## 11. Quiénes somos

La auditoría encuentra tres representaciones:

- `/nosotros`: JSX extenso con afirmaciones institucionales, cinco imágenes y
  una junta directiva con “Nombre y Apellidos” como placeholder;
- `/contenidos/nosotros`: página CMS sembrable con descripción genérica;
- Home: claims de “plataforma oficial” y “Federación” sin fuente editorial
  acreditada.

El código y los assets no demuestran vigencia, autoría, licencia, consentimiento
ni aprobación de nombres, misión, actividad o imágenes.

Estrategia obligatoria, sin ejecutar en 7B:

1. inventariar cada párrafo, cargo, imagen, alt y claim de `/nosotros`, CMS y
   Home;
2. validar con responsable editorial qué material es real, vigente y publicable;
3. cargar en CMS una versión completa y comparar paridad funcional y semántica;
4. registrar `/club/quienes-somos` como ruta canónica sobre el CMS;
5. mantener aliases temporales y, tras aceptación, aplicar redirects futuros;
6. eliminar el JSX y los assets duplicados sólo cuando no tengan consumidores.

No se migran automáticamente los textos o imágenes actuales.

## 12. Contacto

ADR-034 sustituye la estrategia inicial de esta sección. La estrategia vigente
es:

- página institucional administrada mediante CMS;
- uno o varios canales oficiales revisados;
- formulario con persistencia local separada del contenido;
- honeypot y rate limiting HMAC, sin CAPTCHA en 7C.1;
- bandeja Blade y notificación opcional posterior al guardado;
- flags desactivados por defecto;
- ninguna interfaz pública hasta aprobar privacidad, retención, responsable,
  destinatario y capacidad de respuesta.

La base técnica queda cubierta en 7C.1. El CMS no almacena solicitudes y
`ContactRequest` no almacena copy institucional. La UI y activación pertenecen
a 7C.2 y no deben publicarse como forma de completar los gates pendientes.

La ruta será `/club/contacto`. No se publicará hasta aportar al menos un canal
oficial y un responsable de revisión.

Seguimiento 7D.2A: el correo público queda confirmado; el teléfono es privado y
se excluye. Jorge Sánchez Romero está confirmado como responsable web; siguen
pendientes el responsable de revisión jurídica, el destinatario interno del
formulario, el proveedor de correo, la conservación y la validación jurídica.
El formulario continúa desactivado.

## 13. Federarse

El contenido mínimo aprobado como plantilla es:

- proceso vigente y ámbito al que se aplica;
- requisitos;
- organismo o federación competente;
- enlaces oficiales;
- persona, departamento o canal de contacto;
- documentación necesaria;
- costes únicamente si están confirmados;
- fecha de vigencia;
- fecha de revisión y responsable.

El responsable debe comprobar enlaces, costes, plazos y organismo antes de cada
publicación. La ausencia de fecha de vigencia o de responsable impide presentar
el flujo como actual. CMS es la fuente y `/club/federarse` la ruta canónica.

## 14. Documentos

Cada documento necesita:

- nombre;
- tipo;
- propósito;
- archivo o URL;
- versión;
- fecha;
- vigencia;
- responsable;
- estado de accesibilidad;
- tamaño y formato.

En el MVP se utilizarán **bloques CMS `document_link` con enlaces controlados**.
El CMS gestiona nombre, contexto y URL, pero no aloja el archivo.

No se usarán recursos del repositorio como fuente editorial porque no son
administrables por Blade y mezclarían deploy con publicación. Un almacenamiento
externo o administrado podrá incorporarse en el futuro con permisos,
persistencia, sustitución, borrado, seguridad y backups. Hasta entonces, cada
URL externa debe tener propietario, estabilidad y revisión.

## 15. Prensa

`Prensa y Media` queda fuera del Navbar y del submenú Club. La recomendación
única para el MVP es:

- omitirla del footer mientras sólo exista contenido sembrado o no haya
  responsable;
- mantener acceso directo heredado `/contenidos/prensa-media` si la página está
  publicada;
- incorporarla como enlace recomendable del footer únicamente si hay contenido
  real, vigente y mantenido;
- aplazar URL canónica, alias y redirect.

No se crea una sección de noticias ni se promete actualidad sin fuente y flujo
editorial.

## 16. Federaciones

`Federaciones` queda fuera del Navbar y del submenú Club. La recomendación única
es la misma política condicional:

- omitirla del footer hasta validar organismo, alcance, enlaces y responsable;
- conservar acceso directo `/contenidos/federaciones` mientras la página esté
  publicada;
- añadirla como enlace recomendable del footer sólo con contenido real;
- aplazar URL canónica, alias y redirect.

No se confunde `Federaciones` con el proceso concreto de `Federarse`.

## 17. Footer

El footer será global y común a todas las rutas públicas. No duplicará el
Navbar ni dependerá del `Layout` exclusivo de Home.

| Elemento | Clasificación | Condición |
|---|---|---|
| Quiénes somos | Obligatorio MVP | Ruta canónica y CMS aprobado |
| Contacto | Obligatorio MVP | Canal oficial vigente |
| Federarse | Obligatorio MVP | Proceso revisado |
| Documentos | Obligatorio MVP | Inventario vigente |
| Privacidad | Obligatorio MVP | Texto profesional/responsable aprobado |
| Aviso legal | Obligatorio MVP | Texto e identidad legal aprobados |
| Identidad del club | Obligatorio MVP | Denominaciones jurídica y pública confirmadas; acreditación legal pendiente |
| Copyright | Obligatorio MVP | Titular y fórmula aprobados; no hardcodear año/entidad sin revisión |
| Prensa y Media | Recomendable | Sólo con contenido mantenido |
| Federaciones | Recomendable | Sólo con contenido mantenido |
| Accesibilidad | Recomendable | Sólo si existe declaración real y responsable |
| Redes sociales | Recomendable | Sólo perfiles oficiales confirmados |
| Cookies | Futuro/condicional | Obligatorio sólo si la auditoría técnica/legal determina su aplicación |

Todos los elementos continúan pendientes de contenido salvo las rutas
contractuales. Un enlace sin destino real se omite; nunca se deshabilita ni se
rellena con `#`.

## 18. Legal

No se han localizado páginas públicas ni textos aprobados de privacidad, aviso
legal o cookies. El formulario School y el registro de usuario recogen datos
personales sin que el repositorio acredite el texto jurídico que debe
acompañarlos. Este documento no redacta asesoramiento ni cláusulas.

| Necesidad | Existe | Responsable humano | Bloquea MVP | Ruta futura |
|---|---|---|---|---|
| Privacidad general | No | Responsable de la entidad y revisión profesional/jurídica | Sí | `/privacidad` |
| Aviso legal e identidad del titular | No | Representación legal/jurídica | Sí | `/aviso-legal` |
| Cookies | Inventario técnico 7D.2A; mecanismo observado como necesario de forma provisional | Jurídico + responsable técnico | Sí hasta eliminar/autocustodiar terceros o aprobar el tratamiento aplicable | `/cookies` futura, sólo si se aprueba |
| Privacidad de inscripción escolar | No hay texto aprobado | Jurídico/privacidad + responsable de Escuela | Sí para abrir inscripciones | `/privacidad` y aviso contextual en `/escuela` |
| Privacidad de registro de usuarios | No hay texto aprobado | Jurídico/privacidad + responsable de cuentas | Sí para registro productivo | `/privacidad` y aviso contextual en `/register` |
| Identidad pública en competición | Regla adulta y autorización verificable de menores implementadas; activación productiva pendiente | Dirección, responsable deportivo y privacidad | Sí para publicar datos reales | `/legal/privacidad` y aviso `NOTICE-PUBLIC-IDENTITY-MINORS` |

Los textos legales deben identificar versión, fecha de vigencia, responsable y
procedimiento de revisión. El CMS puede publicarlos, pero la validación humana
no se sustituye con tests.

## 19. Identidad pública

### Exposición actual

| Superficie | API pública actual | Presentación React actual |
|---|---|---|
| Clasificación de categoría | `name` resuelto como apodo o nombre completo; IDs de entrada/jugador/usuario; apodo, nombre y apellidos; para equipos, nombre y plantilla con los mismos campos | Muestra `row.name` |
| Ranking de campeonato y temporada | `player_id`, `name`, objeto jugador con ID, apodo, ID de usuario, nombre y apellidos | Muestra `name` |
| Ranking histórico | Los mismos campos de identidad, además de métricas y listas deportivas | Preview y tabla muestran `name` |
| Equipos | Nombre de equipo; en clasificación y detalle de partido pueden viajar IDs y miembros con nombre, apellidos y apodo | Generalmente muestra nombre del equipo |
| Calendario | Jugador: ID y apodo; equipo: ID y nombre | Prioriza apodo, después fallbacks disponibles |
| Detalle de partido y resultado | Entradas con IDs; individuales con nombre, apellidos y apodo; equipos con miembros, IDs y `role_in_team` | Prioriza apodo y después nombre completo; muestra contrincantes y resultado validado |

La interfaz no muestra todos los campos que viajan por API. La política debe
aplicarse primero en backend mediante Resources o proyección pública, no sólo
ocultarse en React.

### Alternativas auditadas en 7B y decisión posterior

| Opción | Utilidad deportiva | Privacidad | Impacto frontend | Impacto backend y migración | Riesgos y decisión humana |
|---|---|---|---|---|---|
| A. Nombre completo | Máxima identificación | Exposición alta, especialmente en menores | Pequeño | Normalizar un único nombre público y retirar campos redundantes; revisar datos/consentimientos | Suplantación, indexación y exposición duradera; exige aprobación expresa |
| B. Nombre y primera inicial | Buena diferenciación local | Reduce apellidos, no evita reidentificación | Pequeño | Formateador público y Resources cerrados; revisar casos compuestos | Colisiones y falsas expectativas de anonimato; exige criterio lingüístico |
| C. Nombre deportivo o alias | Alta si el alias es conocido | Mejor separación respecto de identidad civil | Pequeño | Alias obligatorio/aprobado, unicidad o fallback seguro; completar datos existentes | Alias ausente, ofensivo o identificable; exige gobernanza |
| D. Iniciales | Baja o media | Minimiza, pero puede reidentificar en grupos pequeños | Pequeño | Formateador determinista; retirar nombre/apellidos y revisar colisiones | Clasificaciones ambiguas; exige aceptar menor utilidad |
| E. Política diferenciada para menores | Conserva utilidad adulta y protege más a menores | Potencialmente la más proporcional | Medio | Regla backend basada en `birth_date`, tratamiento de valores nulos, cambio de edad y consentimientos; migración/auditoría de perfiles | Edad incompleta, inferencia de minoría y política desigual; exige revisión profesional y deportiva |

7D.2A cierra la combinación adulta: alias deportivo cuando exista y, si no,
nombre más inicial del primer apellido. No cierra una política para menores.
Antes de publicar competición con datos reales, la entidad debe:

1. elegir y validar el tratamiento específico de menores;
2. definir base, información y retirada;
3. decidir fallbacks para apodo y fecha de nacimiento ausentes;
4. reducir los Resources públicos a la allowlist aprobada;
5. aplicar la misma proyección a ranking, standings, schedule y partido;
6. cubrir backend, frontend y E2E.

Este es un **gate de publicación del MVP**, no un bloqueo para documentar o
implementar la vertical institucional 7C.

7D.2C2A cierra técnicamente esos seis puntos: el backend usa allowlists y una
autorización versionada con vínculo explícito, modos sin fallback, confirmación,
conformidad 14–17, revisión y revocación. La operación productiva y la revisión
de datos reales continúan siendo gates; fotos y redes no forman parte de este
alcance.

## 20. Escuela operativa

Checklist exacto de configuración mediante Blade:

1. Crear y revisar `SchoolLocation` con nombre, localidad, dirección y estado.
2. Crear y revisar `SchoolProgram` sin publicarlo inicialmente.
3. Crear `SchoolLevel` con edades orientativas, estado y orden.
4. Crear `SchoolSchedule` con nivel, día, horas, ubicación, estado y orden.
5. Configurar y verificar el correo operativo privado; el teléfono y el correo
   no forman parte del agregado público.
6. Revisar visibilidad efectiva de programa, niveles, horarios y ubicaciones.
7. Mantener inscripciones cerradas hasta superar todos los gates y probar el
   cierre efectivo.
8. Revisar campos, mensajes, menores, adultos, representante y nivel opcional
   del formulario público.
9. Aprobar privacidad e información contextual antes de abrir.
10. Definir conservación, acceso, rectificación y borrado extraordinario.
11. Confirmar responsable, buzón, tiempos y capacidad de tramitar solicitudes.
12. En entorno controlado, enviar una solicitud de prueba, comprobar recepción
    privada, transición administrativa y ausencia de exposición pública.

Plantilla de datos reales:

**Ubicación**

- nombre:
- localidad:
- dirección:
- activa: sí/no

**Programa**

- nombre:
- teléfono operativo privado:
- correo operativo privado:
- ubicación habitual:
- público: sí/no
- inscripciones abiertas: sí/no

**Nivel**

- nombre:
- edad mínima opcional:
- edad máxima opcional:
- activo: sí/no
- público: sí/no
- orden:

**Horario**

- nivel:
- día:
- hora inicial:
- hora final:
- ubicación:
- activo: sí/no
- orden:

No se publicará el programa sólo para probar la interfaz. Primero se configura
privado y cerrado; la apertura es el último paso humano.

## 21. Escuela editorial

| Bloque | Fuente | Aportación pendiente |
|---|---|---|
| Presentación institucional | `SchoolProgram` | Texto humano aprobado, sin metodología inventada |
| Destinatarios narrativos | `SchoolProgram` | Explicación humana; las edades operativas siguen en Laravel |
| Objetivos pedagógicos | `knowledge/` futuro | Validación pedagógica y canónica |
| Funcionamiento general | `SchoolProgram` | Proceso estable; horarios y apertura siguen en Laravel |
| Material necesario | `knowledge/` futuro | Contenido técnico/pedagógico aprobado |
| Relación con el Manual | React copy y enlace; destino en Knowledge | Etiqueta breve, sin copiar reglas |
| Preguntas frecuentes | CMS futuro, si se aprueba | Preguntas y respuestas reales mantenidas |
| Contacto | Laravel operativo privado | Teléfono/correo necesarios para la operación; no se serializan públicamente |
| Fecha de revisión | Control editorial humano/CMS cuando exista soporte | Fecha, responsable y próxima revisión |

No existe hoy una colección pedagógica de Escuela en `knowledge/`. Hasta que se
apruebe, objetivos y material permanecen ausentes; no se sustituyen por JSX o
por la página legacy `academy`.

Plantilla editorial:

- presentación:
- destinatarios:
- objetivos validados:
- funcionamiento estable:
- material necesario:
- relación/enlaces al Manual:
- preguntas frecuentes:
- contacto responsable:
- fecha de revisión:
- responsable editorial:
- fuentes o aprobación:

## 22. Home

### Auditoría

| Elemento actual | Clasificación | Tratamiento futuro |
|---|---|---|
| Hero, imagen y “La emoción de las Galotxas” | Copy/asset React sin acreditación editorial | Validar claim, procedencia, licencia y alt |
| CTA “Ver Torneos” | Funcional y real | Mantener o priorizar Competición según arquitectura final |
| “Plataforma Oficial” | Claim no acreditado | Retirar o aprobar con titular verificable |
| Texto sobre prensa y actualidad | Promesa sin fuente dinámica | Retirar salvo flujo CMS real |
| Card Prensa & Media sin destino | Placeholder funcional | Omitir hasta contenido y ruta aprobados |
| Card Federaciones sin destino | Placeholder funcional | Omitir hasta contenido y ruta aprobados |
| Card Escuela | Destino real | Mantener dentro de la jerarquía editorial final |
| Footer local “Federación…”, copyright | Identidad no acreditada y no global | Sustituir por footer contractual con datos aprobados |

Contenido mínimo del MVP:

1. propuesta de valor breve y aprobada;
2. acceso principal a Competición;
3. acceso a Aprende a jugar y Manual;
4. acceso a Escuela;
5. acceso secundario a Club y Contacto;
6. contenido remoto sólo si existe fuente estable y estado loading/error/vacío;
7. ninguna noticia, cifra o afirmación de oficialidad sin responsable.

Plantilla Home:

- nombre oficial y denominación corta:
- audiencia principal:
- propuesta de valor:
- afirmaciones y fuente de cada una:
- CTA primario:
- CTA secundarios y prioridad:
- bloque Competición y fuente:
- bloque Aprende/Manual:
- bloque Escuela:
- bloque Club/Contacto:
- imágenes, propietario, licencia/consentimiento y alt:
- contenido que debe omitirse si no hay datos:
- fecha de revisión:
- responsable editorial:

## 23. Plantillas editoriales

### Quiénes somos

- nombre oficial:
- presentación:
- propósito:
- actividad:
- historia resumida:
- organización:
- cargos reales que se desea publicar:
- imágenes disponibles:
- procedencia/licencia/consentimiento por imagen:
- texto alternativo por imagen:
- fecha de revisión:
- responsable editorial:

### Contacto

- correo oficial:
- teléfono: privado; no publicar:
- dirección o ubicación:
- horario de atención:
- responsable o departamento:
- enlaces oficiales:
- fecha de revisión:
- responsable editorial:

### Federarse

- proceso vigente:
- requisitos:
- organismo/federación:
- enlaces oficiales:
- contacto:
- documentación:
- costes confirmados:
- fecha de vigencia:
- fecha de revisión:
- responsable:

### Documentos

| Nombre | Tipo | Propósito | Archivo/URL | Versión | Fecha | Vigencia | Responsable | Accesibilidad | Tamaño/formato |
|---|---|---|---|---|---|---|---|---|---|
| Pendiente | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |

Las plantillas de Escuela y Home se encuentran en las secciones 20–22. Ningún
valor “Pendiente” se publicará como contenido.

## 24. Decisiones humanas

| Prioridad | Decisión o aportación | Estado tras 7B | Momento límite |
|---:|---|---|---|
| 1 | Navegación final | Cerrada por este contrato; requiere aceptación de revisión | Antes de 7C/7D |
| 2 | Etiqueta `Club` | Cerrada por este contrato | Antes de 7C |
| 3 | Rutas canónicas | Cerradas por este contrato | Antes de 7C |
| 4 | Formulario de Contacto local y desactivado | Modificada por ADR-034; base técnica completada | Privacidad y activación antes de 7C.2 |
| 5 | Núcleo y enlaces condicionales del footer | Cerrados por este contrato | Antes de 7D |
| 6 | Prensa y Federaciones condicionales, fuera del Navbar | Cerrada por este contrato | Antes de 7D |
| 7 | Política de identidad pública | Adultos y dominio verificable de menores implementados; operación productiva abierta | Antes de publicar competición real/7G |
| 8 | Datos reales de Escuela | Abiertos; 7E prepara su carga sin inventarlos | Antes de activar en 7F |
| 9 | Contacto oficial | Correo público confirmado; destinatario, envío y operación del formulario abiertos | Antes de activar Contacto/7D.2C2B |
| 10 | Textos y responsables legales | Versión pública 1.0.0 cerrada en 7D.2C1; revisión futura versionada | Antes de cada nueva versión y del despliegue |
| 11 | Contenido de Quiénes somos e imágenes | Abierto | Antes de cerrar 7C |
| 12 | Documentos vigentes | Abiertos | Antes de cerrar 7C |

También faltan acreditación documental de la identidad legal, copyright, política de imágenes, responsable de
Federarse, datos sociales opcionales, responsables de contenido, operación de
Escuela y privacidad.

## 25. Gates

### Bloqueantes para 7C

- aceptación humana de este contrato;
- nombre oficial y responsable editorial;
- inventario y aprobación de `/nosotros`, CMS e imágenes;
- contenido real de Quiénes somos;
- canal oficial de Contacto;
- proceso vigente de Federarse;
- inventario de Documentos y URLs accesibles;
- páginas CMS en borrador listas para validación;
- criterios de paridad y compatibilidad aceptados.

### Bloqueantes para despliegue/MVP

- privacidad general y aviso legal aprobados;
- aplicabilidad y tratamiento de cookies resueltos;
- política de identidad pública deportiva y allowlists implementadas;
- privacidad de registro y Escuela;
- Escuela configurada y cerrada hasta superar su checklist;
- responsable y capacidad de atender inscripciones;
- contenido e imágenes con procedencia;
- identidad legal, contacto y copyright;
- infraestructura, correo, backups, staging y rollback de 7F;
- suites, recorridos y aceptación de 7G.

### No bloqueantes si se omiten

- Prensa y Media;
- Federaciones como página secundaria;
- redes sociales;
- declaración de accesibilidad no disponible;
- contenido pedagógico escolar futuro;
- almacenamiento administrado de documentos;
- landing `/club`;
- aliases/redirects de Prensa, Federaciones y `academy`.

## 26. Plan 7C

**Objetivo:** vertical institucional Club con fuente CMS única.

Seguimiento 7C.0: la inspección de readiness en
`16-club-vertical-readiness-audit.md` no cambia las rutas ni fuentes aprobadas,
pero confirma que no deben implementarse aún destinos públicos. La estrategia
única recomendada, pendiente de aceptación humana, divide la ejecución en:

1. **7C.1:** preparar técnicamente assets, CMS y contacto, y documentar la
   carga manual sin crear contenido o rutas; completada según
   `17-club-technical-preparation-and-contact.md`;
2. **7C.2:** implementar fachadas, aliases, estados, metadatos, accesibilidad,
   responsive y el formulario React; publicar sólo después de la aceptación.

La creación o actualización manual de las cuatro páginas en borrador queda
entre 7C.1 y 7C.2 y no la realiza el código. ADR-034 modifica la decisión
inicial de no incluir formulario: la persistencia, antispam, bandeja y
notificación opcional están preparadas, pero desactivadas por defecto.

Contacto y Documentos pueden alcanzar un mínimo con los datos y el Manual ya
identificados, una vez confirmados. Quiénes somos y Federarse siguen bloqueados
por datos. Las imágenes son omitibles, pero cualquier imagen utilizada exige
procedencia, derechos y consentimientos.

Alcance:

- registrar `/club/quienes-somos`, `/club/contacto`, `/club/federarse` y
  `/club/documentos`;
- consumir slugs CMS aprobados con estados remotos y 404 coherentes;
- crear/cargar `contacto` mediante el flujo editorial, no JSX;
- acreditar paridad de Quiénes somos;
- incorporar aliases temporales para las rutas heredadas acordadas;
- preservar `/contenidos`, `academy`, Prensa y Federaciones;
- cubrir publicación/ocultación, rutas, renderer, metadatos básicos,
  accesibilidad y E2E;
- actualizar arquitectura, API sólo si cambia y gobernanza.

Fuera de 7C.2:

- Navbar final, footer, Home, legal, redirects permanentes, SEO completo,
  uploads y contenido inventado.

Cierre:

- cuatro destinos con contenido real;
- una única fuente CMS;
- `/nosotros` sin ser todavía retirado;
- aliases reversibles;
- gates editoriales firmados.

## 27. Plan 7D

**Objetivo:** aplicar la navegación contractual y cerrar Home, footer y legal.

Alcance:

- configuración única con enlaces directos y grupos Aprende/Club;
- desktop y móvil con estados, foco, Escape, cierre al navegar y cuenta
  separada;
- Home veraz basada en la plantilla aprobada;
- footer global y rutas legales con contenido aprobado;
- omisión condicional de Prensa, Federaciones, redes, accesibilidad y cookies;
- responsive 320–1440 px, zoom 200 %, teclado y ausencia de overflow;
- tests unitarios/RTL y E2E de rutas y menús.

Fuera:

- noticias sin fuente, formulario de Contacto, SEO completo y redirects
  permanentes.

## 28. Plan 7E

**Objetivo:** preparar técnicamente Escuela para recibir datos, contenido y
configuración reales, manteniendo cerrada la activación productiva.

Alcance:

- carga manual privada/cerrada por Blade, sin inventar datos;
- validación central y fail-closed de programa, contenido, ubicación, nivel,
  horario, contacto operativo privado y aviso vigente;
- presentación y proceso administrables en `SchoolProgram`;
- primera capa versionada y conservación técnica de los plazos publicados;
- trazabilidad, holds, anonimización y purga manual en dry-run;
- prueba integral con fixtures exclusivamente E2E;
- apertura productiva sólo tras configuración real y aceptación explícita en
  7F;
- regresión completa de backend, frontend y E2E.

Fuera:

- nuevos modelos/endpoints, centros públicos, metodología inventada,
  `academy`, pagos, plazas o contenido de menores no autorizado;
- datos, horarios, responsables o canales productivos inventados;
- proveedor de correo, scheduler, backups, restore, staging o despliegue.

## 29. Plan 7F

**Objetivo:** despliegue reproducible y reversible.

Alcance:

- Railway para backend y proceso productivo;
- Vercel para frontend, build y fallback SPA;
- MariaDB soportada y persistente;
- variables y secretos por entorno;
- CORS, proxy/TLS, correo, sesiones, logs y health;
- backups, restauración y migraciones controladas;
- administrador inicial sin seeders demo;
- staging equivalente, smoke y rollback;
- conservación del aislamiento de desarrollo, test y E2E.

Fuera:

- cambiar de motor, versionar secretos o desplegar sin restore probado.

## 30. Plan 7G

**Objetivo:** demostrar el MVP observable y autorizar el release.

Alcance:

- suite Laravel completa sobre MariaDB aislada;
- frontend tests, Knowledge, lint y build;
- E2E crítico de inscripción deportiva, institucional, Escuela, usuario,
  administración y 404;
- responsive, teclado, zoom y prioridades multibrowser acordadas;
- smoke de staging/producción;
- revisión de identidad, privacidad, contenido e imágenes;
- release candidate, rollback y checklist firmados.

No se absorben P1/P2 salvo defectos bloqueantes. Fase 7 y el MVP sólo se cierran
cuando no quede ningún P0.

## 31. Criterios de aceptación

Fase 7B queda aceptable cuando:

- existe una única navegación, con Aprende, Club y Cuenta definidos;
- el comportamiento desktop, móvil y accesible es verificable;
- Club es disclosure y no landing;
- rutas canónicas, legado, aliases y redirects están separados;
- CMS, Knowledge, Laravel y React tienen responsabilidades inequívocas;
- Quiénes somos, Contacto, Federarse y Documentos tienen estrategia y plantilla;
- Prensa y Federaciones tienen ubicación condicional;
- footer y legal tienen contrato y matriz;
- la exposición actual de identidad está inventariada sin cerrar política;
- identidad se registra como gate;
- Escuela tiene checklist exacto y plantilla de datos;
- Escuela editorial y Home tienen fuentes y plantillas;
- decisiones humanas y gates están priorizados;
- 7C–7G tienen alcance, cierre y exclusiones;
- Fase 7 sigue abierta y el MVP pendiente;
- no se ha modificado código, `knowledge/`, datos, Docker, dependencias o locks;
- no se han ejecutado suites, migraciones, seeders, frontend, build o E2E;
- `git diff --check` no produce salida.

## 32. Seguimiento de Fase 7C

7C queda cerrada técnicamente con las fachadas diferidas
`/club/quienes-somos`, `/club/contacto`, `/club/federarse` y
`/club/documentos`. El cuerpo y los metadatos continúan en CMS; React sólo
aplica el mapa ruta/slug, estados y presentación. Contacto conserva el CMS y
monta campos únicamente con `enabled: true`; el default productivo no cambia.

No se implementan todavía los disclosures aprobados, Home, footer o legal. No
se retiran `/nosotros` ni `/contenidos/:slug` porque la paridad no se ha
acreditado, y no existen redirects o canonical. Por tanto el plan 7D–7G, los
gates humanos y el estado abierto de Fase 7/MVP permanecen sin cambios.

## 33. Seguimiento de Fase 7D.1

7D se divide para no publicar destinos legales vacíos. 7D.1 implementa la
parte estructural ya aprobada: configuración única, disclosures Aprende/Club,
Cuenta separada, estados y teclado desktop/móvil, Home veraz y footer global con
identidad, rutas institucionales y redes confirmadas. Los padres no reciben
ruta y `aria-current="page"` se reserva a coincidencias exactas; las ramas
descendientes mantienen sólo estado visual.

7D.2A completa la auditoría y los borradores internos sin rutas ni enlaces.
7D.2B completa el endurecimiento técnico de identidad pública, sesión y
recursos externos. 7D.2C1 publica privacidad, aviso legal y cookies desde una
fuente versionada propia. 7D.2C2A implementa la identidad verificable de
menores y 7D.2C2B completa la operación técnica de Contacto. Las imágenes
quedan en un frente independiente; proveedor, entrega y activación pertenecen
a 7F. El formulario productivo de Contacto continúa desactivado.
No cambian CMS, contenido, `/nosotros`, `/contenidos`, aliases, redirects,
canonical o despliegue. Por tanto 7D, Fase 7 y el MVP siguen abiertos.

## 34. Seguimiento de Fase 7D.3

7D.3 no cambia el árbol editorial ni las fuentes. Clasifica las rutas, adopta
las cuatro fachadas Club como canonical, conserva `/nosotros` y los slugs CMS
institucionales como aliases `noindex`, y mantiene el CMS genérico fuera del
sitemap. Metadata, robots y sitemap quedan bloqueados por defecto hasta una URL
HTTPS explícita. La accesibilidad transversal añade foco y anuncio de cambios
de ruta sin crear `/aprende` o `/club`.

Los 61 escenarios E2E pasan sobre el stack aislado y cierran 7D.3 y 7D.
Dominio, redirects HTTP, activación indexable, correo, backups, restore,
scheduler, staging, rollback y aceptación pertenecen a 7F/7G; Fase 7 y el MVP
siguen abiertos.

## 35. Seguimiento de Fase 7E

El plan 7E se ejecuta como preparación técnica, no como carga de valores
productivos. `SCHOOL_ENROLLMENT_ENABLED=false` es el default y Laravel exige
configuración completa antes de responder `open`; `closed` mantiene visible el
contenido y `unavailable` no filtra causas técnicas al visitante. Presentación
y proceso se editan en `SchoolProgram`, no en React ni en el CMS `academy`.

`NOTICE-SCHOOL-ENROLLMENT` 1.0.0 separa la privacidad obligatoria de la
autorización opcional de identidad pública. Retención, holds, trazabilidad y
anonimización quedan preparados sin scheduler. Los datos, horarios, niveles,
responsable y canales reales deben cargarse y aprobarse antes de activar el
flag. El detalle técnico y la matriz humana pendiente se encuentran en
`26-school-operational-readiness.md`; 7F, 7G, Fase 7 y MVP siguen abiertos.
