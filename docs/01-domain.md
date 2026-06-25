# Dominio funcional — Galotxas

## Propósito

Este documento describe el funcionamiento funcional del sistema Galotxas.

Su objetivo es explicar cómo se desarrolla una competición desde el punto de vista del negocio, independientemente de su implementación técnica.

Las entidades se definen en `00-glossary.md`. Este documento se centra en los procesos.

---

# 1. Principios del dominio

El dominio deportivo de Galotxas se basa en los siguientes principios:

- El backend constituye la fuente de verdad del sistema.
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

---

# 6. Gestión de resultados

Los resultados forman parte del dominio deportivo.

Solo los resultados validados tienen efectos oficiales.

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
- mantener su perfil deportivo;
- solicitar inscripciones;
- consultar calendarios;
- consultar rankings;
- consultar resultados.

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

# 11. Contenidos públicos CMS

El sistema incorpora una primera base técnica para páginas públicas gestionables mediante CMS.

Conceptos:

- Página CMS: contenido público identificado por `slug`, con título, estado y metadatos SEO mínimos.
- Estado de página: una página puede estar en borrador (`draft`) o publicada (`published`).
- Bloque CMS: unidad estructurada de contenido asociada a una página.

Reglas:

- Solo las páginas publicadas pueden leerse desde la API pública.
- Las páginas en borrador no son visibles públicamente.
- Una página con fecha de publicación futura todavía no es visible públicamente.
- Si un administrador marca una página como publicada sin indicar `published_at`, el backend completa la fecha con el momento actual.
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

El panel administrativo permite crear y editar páginas CMS con título, `slug`, estado, `published_at` y metadatos SEO mínimos. También permite gestionar sus bloques estructurados. La subida de documentos o imágenes todavía no forma parte de esta base técnica; los bloques de imagen, galería y documento trabajan con URLs o rutas ya existentes.

---

# 12. Funcionalidades previstas

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
