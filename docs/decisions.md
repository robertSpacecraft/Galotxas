# Architectural Decisions — Galotxas

Este documento registra las decisiones arquitectónicas relevantes tomadas durante el diseño y desarrollo del proyecto.

Su objetivo es mantener coherencia técnica y evitar que decisiones fundamentales cambien sin justificación.

Cada decisión incluye:

- contexto
- decisión tomada
- consecuencias

---

# AD-001 — Arquitectura desacoplada

## Contexto

El sistema debe servir tanto para web pública como para futuras aplicaciones móviles y posibles integraciones externas.

## Decisión

Adoptar una arquitectura desacoplada:

Frontend → API REST → Backend → Base de datos

El backend no genera HTML.

## Consecuencias

Ventajas:

- reutilización del backend
- soporte natural para aplicaciones móviles
- separación clara de responsabilidades

Costes:

- mayor trabajo inicial
- necesidad de definir API desde el principio

---

# AD-002 — Monorepo

## Contexto

El proyecto incluye múltiples componentes:

- backend
- frontend
- infraestructura

## Decisión

Usar un **monorepositorio** con esta estructura:
/backend
/frontend
/docker
/docs


## Consecuencias

Ventajas:

- control completo del sistema
- versionado coherente
- facilidad para agentes que trabajan sobre el repositorio

Costes:

- repositorio más grande

---

# AD-003 — Backend Laravel

## Contexto

Se requiere un framework robusto para:

- API REST
- autenticación
- gestión de dominio
- ORM

## Decisión

Utilizar **Laravel** como framework backend.

## Consecuencias

Ventajas:

- ecosistema maduro
- Eloquent ORM
- soporte para APIs
- integración sencilla con Docker

---

# AD-004 — Frontend React

## Contexto

Se necesita una interfaz moderna que consuma una API REST.

## Decisión

Utilizar **React con Vite** para el frontend.

## Consecuencias

Ventajas:

- ecosistema amplio
- gran comunidad
- integración sencilla con APIs

---

# AD-005 — Clasificaciones calculadas

## Contexto

Las clasificaciones dependen de resultados y reglas que pueden evolucionar.

## Decisión

No almacenar la clasificación como tabla persistente inicialmente.

Las clasificaciones se calcularán dinámicamente a partir de los resultados validados.

## Consecuencias

Ventajas:

- evita inconsistencias
- permite modificar reglas

Costes:

- cálculo adicional en backend

Si el rendimiento lo requiere en el futuro se podrá implementar:

- caché
- tabla materializada

---

# AD-006 — Resultados con validación administrativa

## Contexto

Los jugadores introducen resultados pero debe existir control administrativo.

## Decisión

Los resultados siguen un flujo:

1. resultado enviado por jugador
2. estado `submitted`
3. validación administrativa
4. estado `validated`

## Consecuencias

Ventajas:

- control de calidad de datos
- trazabilidad

---

# AD-007 — API versionada

## Contexto

Las APIs evolucionan con el tiempo.

## Decisión

Todas las rutas deben incluir versión.
/api/v1/...


## Consecuencias

Permite introducir nuevas versiones sin romper clientes existentes.

---

# AD-008 — Backend como fuente de verdad

## Contexto

Las reglas del deporte deben ser consistentes.

## Decisión

Toda la lógica de dominio reside en el backend.

El frontend nunca calcula:

- clasificaciones
- reglas de puntuación
- validación de resultados

## Consecuencias

Ventajas:

- consistencia del sistema
- clientes más simples

---

# AD-009 — Identidad de jugadores separada de usuarios

## Contexto

Un jugador puede existir en el sistema aunque no tenga cuenta de usuario.

## Decisión

Separar las entidades:

- Player
- User

Un usuario puede vincularse a un jugador.

## Consecuencias

Permite:

- gestionar históricos
- registrar jugadores sin cuenta
- vincular cuentas posteriormente