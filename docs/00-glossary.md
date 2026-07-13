# Glosario — Galotxas

## Propósito

Este documento define el significado funcional de los términos utilizados en el proyecto Galotxas.

Su objetivo es que toda la documentación, el código y los agentes de IA utilicen el mismo vocabulario.

Las definiciones describen el significado dentro del dominio deportivo, no la implementación técnica.

Cuando exista conflicto entre una interpretación coloquial y este documento, prevalece este glosario.

---

# Usuario (User)

Cuenta autenticada del sistema.

Puede acceder a funcionalidades privadas según su rol.

Un usuario puede existir sin ser jugador.

---

# Jugador (Player)

Perfil deportivo asociado a un usuario.

Contiene la información necesaria para participar en competiciones.

Todo jugador pertenece a un único usuario.

No todo usuario tiene por qué convertirse en jugador.

---

# Administrador

Usuario con permisos para gestionar el sistema.

Puede administrar temporadas, campeonatos, categorías, inscripciones, equipos, partidos y resultados.

El administrador no constituye una entidad deportiva independiente.

---

# Temporada (Season)

Agrupa todas las competiciones desarrolladas durante un periodo determinado.

Una temporada contiene uno o varios campeonatos.

---

# Campeonato (Championship)

Competición perteneciente a una temporada.

Un campeonato puede ser de individuales (Singles) o dobles (Doubles).

La inscripción del jugador siempre se realiza al campeonato, nunca directamente a una categoría.

---

# Categoría (Category)

División competitiva perteneciente a un campeonato.

Representa el grupo real donde compiten los participantes.

Cada campeonato puede contener varias categorías.

La asignación a una categoría siempre la realiza un administrador.

---

# Asignación a categoría (CategoryRegistration)

Asignación administrativa de un jugador aprobado a una categoría concreta de un campeonato.

Controla que el administrador haya ubicado al jugador en una categoría determinada.

No equivale necesariamente al participante competitivo final, especialmente en campeonatos de dobles, donde la unidad competitiva será un equipo representado mediante `CategoryEntry`.

En el código, este concepto está representado por `CategoryRegistration`.

---

# Participante competitivo (CategoryEntry)

Unidad deportiva que compite realmente dentro de una categoría.

En campeonatos de individuales referencia a un jugador.

En campeonatos de dobles referencia a un equipo.

Es la entidad que participa en partidos, clasificaciones y resultados.

No debe confundirse con:

- usuario;
- jugador;
- solicitud de inscripción;
- asignación administrativa a categoría.

En el código está representada por `CategoryEntry`.

---

# Equipo (Team)

Agrupación de dos jugadores que compite en campeonatos de dobles.

---

# Solicitud de inscripción

Petición realizada por un jugador para participar en un campeonato.

Inicialmente queda pendiente de revisión administrativa.

No implica que el jugador ya participe en la competición.

---

# Estado de la solicitud

Estado administrativo de una solicitud de inscripción.

Actualmente puede encontrarse, al menos, en:

- Pendiente.
- Aprobada.
- Rechazada.

Este estado es independiente del pago.

---

# Estado del pago

Situación administrativa del pago asociado a una inscripción.

No determina por sí mismo si un jugador participa o no.

El pago y la aprobación son conceptos independientes.

---

# Inscripción

Proceso completo mediante el cual un jugador pasa de solicitar participar a quedar habilitado para competir.

De forma general comprende:

1. Solicitud.
2. Revisión administrativa.
3. Aprobación o rechazo.
4. Asignación a una categoría.

---

# Liga

Primera fase competitiva de una categoría.

Sirve para determinar la clasificación de los participantes competitivos.

---

# Copa

Fase eliminatoria posterior a la liga.

Actualmente se genera a partir de la clasificación obtenida en la fase de liga.

---

# Partido (Match)

Encuentro deportivo entre dos participantes competitivos.

Puede pertenecer a una fase de liga o de copa.

---

# Pista (Venue)

Espacio físico configurable donde se programa un partido.

Una pista dispone de nombre y, opcionalmente, ubicación y descripción. Puede utilizarse tanto en la programación original como en una solicitud de reprogramación.

---

# Resultado enviado

Resultado comunicado por un participante competitivo.

Todavía no ha sido validado definitivamente.

---

# Resultado validado

Resultado aceptado oficialmente.

Solo los resultados validados afectan a clasificaciones y rankings.

---

# Conflicto de resultado

Situación en la que existen discrepancias entre resultados enviados o se requiere intervención administrativa.

---

# Reporte de resultado (MatchResultReport)

Declaración inmutable del tanteo realizada por uno de los dos lados de un partido.

En dobles, el reporte de cualquiera de los integrantes representa a todo su lado. Puede estar `submitted`, `validated` o `conflict` y no equivale al resultado oficial mientras el partido no esté validado.

---

# Acción pendiente de partido

Resumen calculado por el backend para indicar qué intervención corresponde al jugador en Mi Panel.

Puede ser enviar resultado (`submit_result`), confirmar o revisar el reporte rival (`confirm_result`) o consultar una discrepancia no editable (`under_review`).

---

# Solicitud de reprogramación (MatchRescheduleRequest)

Propuesta de un lado participante para cambiar la fecha, hora y pista de un partido.

El cambio solo se aplica cuando el lado rival confirma la misma propuesta. El backend dispone de este flujo, aunque el frontend React del MVP todavía no ofrece su interfaz.

---

# Ranking de categoría

Clasificación correspondiente únicamente a una categoría concreta.

---

# Ranking de campeonato

Clasificación agregada de todos los jugadores participantes en un campeonato.

---

# Ranking de temporada

Clasificación agregada correspondiente a todos los campeonatos de una temporada.

---

# Ranking histórico

Clasificación global acumulada del sistema.

Utiliza un algoritmo propio independiente del ranking de categoría.

---

# Backend

Aplicación Laravel responsable de reglas de negocio, persistencia, autenticación, API REST y panel administrativo Blade.

Es la fuente de verdad del dominio.

---

# Frontend

Aplicación React consumidora de la API.

Representa información y experiencia de usuario.

No implementa reglas deportivas.

---

# Panel administrativo

Interfaz web Blade utilizada por los administradores.

Es independiente del frontend React.

---

# API

Contrato de comunicación entre el backend y sus consumidores.

---

# Resource

Clase responsable de serializar datos para la API.

Un mismo modelo puede tener distintos Resources según el contexto.

---

# Dominio

Conjunto de reglas deportivas y funcionales propias de Galotxas.

Pertenece exclusivamente al backend.

---

## Mantenimiento

Cuando aparezcan nuevos conceptos relevantes durante el desarrollo, este documento deberá actualizarse antes de incorporarlos al resto de la documentación.
