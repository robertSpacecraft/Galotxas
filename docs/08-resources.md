# Resources — Galotxas

## Propósito

Este documento define la estrategia de serialización utilizada por la API de Galotxas.

Los Resources constituyen la capa de traducción entre el dominio interno del backend y el contrato público de la API.

No describen cómo se almacenan los datos, sino cómo deben exponerse a cada consumidor.

---

# 1. Principios

- Los Resources forman parte del contrato público de la API.
- Un cambio en un Resource puede romper consumidores.
- El dominio y la API pueden evolucionar de forma parcialmente independiente.
- Los nuevos endpoints no deben devolver modelos Eloquent directamente.

---

# 2. Estado actual

El proyecto se encuentra en una migración progresiva hacia una serialización homogénea mediante Resources.

Actualmente conviven:

- endpoints completamente serializados mediante Resources;
- endpoints heredados pendientes de adaptación;
- distintas estrategias de serialización según la antigüedad del módulo.

Esta situación es conocida y aceptada.

La migración debe realizarse de forma incremental, aprovechando la evolución natural de cada módulo.

No se pretende una reescritura masiva únicamente para homogeneizar la serialización.

---

# 3. Responsabilidades

Un Resource debe:

- seleccionar los datos que se exponen;
- ocultar información interna;
- transformar relaciones cuando sea necesario;
- mantener un formato estable;
- representar únicamente el contexto para el que fue diseñado.

No debe:

- ejecutar reglas de negocio;
- consultar la base de datos;
- decidir permisos;
- calcular rankings;
- implementar lógica deportiva.

---

# 4. Resources por contexto

La estrategia oficial del proyecto es:

**Un contexto funcional ⇒ un Resource.**

No reutilizar un Resource únicamente porque comparte parte de los datos.

Ejemplo implementado actualmente:

- PublicMatchResource

Ejemplos de Resources que podrán existir conforme evolucione el proyecto:

- ParticipantMatchResource
- AdminMatchResource

Cada uno representa un contrato distinto.

---

# 5. Contextos habituales

## Público

Información visible sin autenticación.

Debe excluir:

- emails;
- comentarios internos;
- usuarios responsables;
- trazabilidad administrativa.

## Participante autenticado

Información necesaria para la experiencia del jugador autenticado.

## Administrador

Información completa necesaria para gestionar la competición.

---

# 6. Relaciones

Las relaciones deben exponerse únicamente cuando aporten valor al consumidor.

Evitar:

- árboles excesivamente profundos;
- relaciones completas innecesarias;
- dependencias circulares.

Seleccionar únicamente los campos relevantes.

---

# 7. Enums

Los enums deben serializarse de forma explícita y estable.

No depender del comportamiento automático de Eloquent cuando ello pueda afectar al contrato.

---

# 8. Seguridad

Los Resources constituyen una capa adicional de protección.

Aunque una ruta esté protegida por middleware, el Resource solo debe exponer la información necesaria para ese contexto.

---

# 9. Relación con la API

El contrato API depende directamente de los Resources.

Antes de modificar un Resource debe comprobarse:

- compatibilidad con React;
- impacto sobre otros consumidores;
- tests existentes;
- documentación.

---

# 10. Buenas prácticas

Preferir:

- Resources pequeños;
- responsabilidad única;
- nombres descriptivos;
- serialización explícita;
- estabilidad del contrato.

Evitar:

- condicionales según contexto;
- reutilización excesiva;
- exposición accidental de datos internos;
- devolver modelos Eloquent completos.

---

# 11. Evolución prevista

El objetivo arquitectónico es que todos los endpoints relevantes utilicen Resources específicos y coherentes con su contexto funcional.

La adopción se realizará de forma progresiva conforme evolucionen los distintos módulos del proyecto.

---

## Mantenimiento

Cuando cambie la estrategia de serialización deberá actualizarse este documento y, cuando corresponda, `03-api-contract.md`.