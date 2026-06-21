# Roadmap — Galotxas

## Propósito

Este documento representa el plan oficial de evolución del proyecto.

A diferencia del resto de documentos de `/docs`, este archivo es dinámico y deberá actualizarse conforme avance el desarrollo.

No describe el funcionamiento del sistema; únicamente indica el estado del proyecto, la deuda técnica conocida y el orden recomendado de implementación.

---

# Estado actual

## Completado

### Documentación

- Estructura documental reorganizada.
- AGENTS global y específicos.
- Guías de estilo para backend y frontend.
- Glosario.
- Dominio.
- Arquitectura.
- Contrato API.
- Panel administrativo.
- Estrategia de testing.
- ADR.
- Estrategia de Resources.

### Backend

- Autenticación.
- Recuperación segura de contraseña.
- Logout API.
- Usuarios activos en API.
- Usuarios activos en panel Blade.
- Rate limiting.
- Flujo completo de inscripción.
- Gestión administrativa de solicitudes.
- Gestión de categorías.
- Equipos de dobles.
- Generación de competición.
- Rankings.
- Separación de Resources públicos.

### Infraestructura

- Docker.
- MariaDB como único motor soportado.
- Entorno de pruebas aislado.

---

# Fase 0 — Validación funcional

Objetivo:

Verificar manualmente todos los flujos principales desde la perspectiva del usuario y del administrador.

Estado:

Parcialmente completada.

Pendientes:

- continuar validando UX conforme evolucionen nuevas funcionalidades;
- revisar consistencia del panel administrativo.

---

# Fase 1 — Consolidación técnica

Objetivo:

Eliminar deuda técnica inmediata sin modificar el comportamiento funcional.

Pendientes prioritarios:

- homogeneidad de respuestas API;
- normalización progresiva mediante Resources;
- revisión de controladores con lógica excesiva;
- limpieza de rutas duplicadas;
- revisión de seguridad restante.

---

# Fase 2 — Panel del participante

Objetivo:

Completar la experiencia privada del usuario.

Incluye:

- Mi Panel;
- calendario;
- partidos;
- rankings;
- estado de inscripciones;
- validación UX.

---

# Fase 3 — Panel administrativo

Objetivo:

Mejorar productividad del administrador.

Estado:
En progreso.

Líneas previstas:

- [x] Tareas pendientes en el dashboard (Bloque 1).
- [x] Acciones rápidas (Bloque 2).
- [x] Vista de huérfanos sin categoría (Bloque 3).
- [ ] Asignación rápida de categoría (Bloque 4).
- [ ] Métricas y filtros avanzados (Bloque 5).

---

# Fase 4 — Contrato API

Objetivo:

Congelar el contrato público.

Incluye:

- normalización del envelope;
- revisión de nombres;
- serialización;
- paginación;
- errores homogéneos;
- documentación OpenAPI futura.

---

# Fase 5 — Cobertura de pruebas

Objetivo:

Endurecer la cobertura antes de considerar el proyecto funcionalmente estable.

Incluye:

- auditoría de cobertura;
- Services;
- Resources;
- Middleware;
- Policies;
- flujos completos;
- frontend cuando la interfaz se estabilice;
- regresiones.

---

# Fase 6 — Funcionalidades futuras

No prioritarias actualmente:

- pagos online;
- pasarela de pago;
- sugerencia automática de categoría;
- asignación automática;
- notificaciones;
- mejoras avanzadas de rankings;
- aplicación móvil;
- API administrativa completa;
- contenidos dinámicos.

---

# Observaciones abiertas

Durante la documentación se han identificado varias posibles evoluciones:

- revisar periódicamente que la terminología del dominio permanezca alineada con las entidades reales (`User`, `Player`, `CategoryRegistration`, `CategoryEntry` y `Team`) conforme evolucione el proyecto.
- documentar la API mediante casos de uso completos;
- ampliar el catálogo de ADR;
- ampliar la documentación del contrato API con ejemplos reales;
- mantener una estrategia estricta de Resources por contexto.

Estas observaciones no constituyen tareas obligatorias, pero deberán revisarse cuando resulte oportuno.

---

# Criterios para cerrar una fase

Antes de considerar cerrada una fase deberían cumplirse:

- funcionalidad implementada;
- pruebas razonables;
- validación manual;
- documentación actualizada;
- commit independiente;
- merge realizado cuando corresponda.

---

## Mantenimiento

Este documento deberá revisarse al finalizar cada bloque importante de desarrollo para reflejar el estado real del proyecto.