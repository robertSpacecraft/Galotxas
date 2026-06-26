# Testing — Galotxas

## Propósito

Este documento describe la estrategia de pruebas del proyecto Galotxas.

Su objetivo es garantizar que la evolución del sistema no comprometa el comportamiento funcional, la seguridad ni el contrato de la API.

---

# 1. Principios

- Las pruebas forman parte del desarrollo.
- Todo cambio relevante debe valorar la incorporación de tests.
- Las pruebas deben ser repetibles y aisladas.
- Nunca deben ejecutarse pruebas destructivas sobre la base de desarrollo.

---

# 2. Entornos

## Desarrollo

Utiliza la base MariaDB principal del proyecto.

No debe emplearse para ejecutar `RefreshDatabase` ni `migrate:fresh`.

## Pruebas

Las pruebas de integración utilizan una instancia MariaDB aislada mediante Docker.

Esta base es temporal y puede destruirse sin afectar al entorno de desarrollo.

---

# 3. Tecnologías

Backend:
- PHPUnit
- Laravel Testing
- RefreshDatabase cuando proceda
- Docke

Frontend:
- La estrategia de pruebas del frontend se documentará conforme aumente la cobertura.

---

# 4. Tipos de pruebas

## Unitarias

Verifican lógica aislada.

Ejemplos:
- algoritmos;
- utilidades;
- Services con dependencias simuladas.

## Integración

Comprueban la interacción entre:
- rutas;
- middleware;
- controllers;
- Services;
- modelos;
- base de datos.

## Funcionales

Validan flujos completos desde el punto de vista del usuario.

Incluyen tanto pruebas automáticas como verificaciones manuales.

---

# 5. Prioridades

Alta prioridad:
- autenticación;
- autorización;
- usuarios activos;
- inscripción;
- área privada “Mi Panel”;
- resultados;
- rankings;
- Resources;
- contrato API.

Prioridad media:
- panel administrativo;
- filtros;
- búsquedas.

Prioridad baja:
- vistas simples sin lógica.

---

# 6. Flujo recomendado

1. Implementar.
2. Ejecutar tests afectados.
3. Ejecutar la suite completa cuando el cambio sea relevante.
4. Revisar `git diff --check`.
5. Actualizar documentación si cambia el comportamiento.

---

# 7. Comandos habituales

Suite backend:

`docker compose --profile test run --rm test`

Comprobación de sintaxis:

`php -l`

Comprobación de espacios y conflictos:

`git diff --check`

---

# 8. Pruebas manuales

Antes de cerrar funcionalidades importantes se recomienda ejecutar flujos completos de usuario y administración para validar la experiencia de uso.

Las pruebas manuales complementan, pero no sustituyen, las pruebas automáticas.

## Validación manual de auth/token frontend

Para cambios en la autenticación de React deben validarse al menos estos flujos:

- login correcto y almacenamiento de `token` y `user`;
- acceso a `/player` con sesión válida;
- logout correcto, incluyendo llamada a backend cuando existe token;
- acceso a `/player` tras logout, que debe redirigir a login;
- comportamiento con token ausente o inválido, limpiando `token` y `user`;
- consola del navegador sin errores relevantes.

Estas comprobaciones deben acompañarse de `npm run lint`, `npm run build` y `git diff --check` cuando el cambio afecte al frontend.

## Validación manual de CMS público React

Para cambios en el renderizado público de páginas CMS deben validarse al menos estos flujos:

- carga correcta de `/contenidos` con el índice de páginas publicadas;
- carga correcta de `/contenidos/{slug}` para una página publicada;
- navegación desde el navbar hacia `/contenidos/prensa-media`, `/contenidos/nosotros`, `/contenidos/federaciones`, `/contenidos/academy` y `/contenidos`;
- exclusión en el índice de páginas borrador y páginas con `published_at` futuro;
- renderizado de bloques `heading`, `text`, `list`, `button` y `document_link` cuando existan;
- estado de carga;
- estado vacío del índice cuando no existan páginas publicadas;
- estado de página inexistente o no publicada;
- consola del navegador sin errores relevantes.

Estas comprobaciones deben acompañarse de `npm run lint`, `npm run build` y `git diff --check`.

---

# 9. Cobertura Feature actual

El backend dispone de cobertura automatizada Feature para componentes críticos del dominio:

## Panel Administrativo
- acceso seguro y protección de sesiones activas;
- contadores de solicitudes pendientes y aprobadas sin categoría en el dashboard;
- enlace desde el dashboard a la sección específica de solicitudes e inscripciones;
- ausencia de formularios operativos de aprobación o asignación en el dashboard;
- acceso administrativo y protección frente a usuarios no administradores en la nueva sección;
- visualización de solicitudes pendientes y aprobadas sin categoría en sus bloques correspondientes;
- exclusión de solicitudes rechazadas y aprobadas ya asignadas;
- aprobación y rechazo desde la sección reutilizando las rutas existentes;
- formulario de asignación rápida con las categorías del campeonato correspondiente;
- preselección de la categoría sugerida cuando pertenece al campeonato;
- asignación desde la sección reutilizando `CategoryRegistrationController@store`;
- desaparición de la solicitud del bloque pendiente tras crear `CategoryRegistration`;
- rechazo de asignaciones a categorías de otro campeonato;
- ausencia del formulario cuando el campeonato no tiene categorías disponibles;

## Competición
- regresión de finales de copa: semifinales validadas con GameMatch.status casteado a enum generan final y tercer puesto;
- protección frente a generación de finales con semifinales no validadas;
- regeneración de finales de copa sin duplicados;
- cobertura de generación normal de semifinales de copa.

## Área Privada "Mi Panel"
- datos del usuario autenticado;
- consulta, creación y actualización del perfil de jugador;
- solicitudes de inscripción propias;
- partidos propios;
- calendario;
- rankings;
- comportamiento de usuarios sin perfil de jugador;
- rechazo de acceso no autenticado.

## Flujo de Inscripción y Administración (Fase 3 Core)
- prevención de inscripciones si el campeonato está cerrado;
- prevención de inscripciones duplicadas;
- creación exitosa de inscripciones en estado pendiente;
- rechazo de usuarios sin perfil de jugador al intentar inscribirse;
- acciones administrativas (aprobar/rechazar/devolver a pendiente);
- asignación de categoría exclusiva para jugadores con solicitudes aprobadas;
- prevención de asignaciones duplicadas a la misma categoría;
- protección de accesos administrativos frente a usuarios normales.

## CMS público
- listado público de páginas publicadas en `GET /api/v1/cms/pages`;
- exclusión de borradores y páginas futuras del listado público;
- ausencia de bloques y campos internos en el listado público;
- orden estable del listado por `published_at` descendente e `id` descendente;
- lectura pública de una página publicada por `slug`;
- respuesta `404` para páginas inexistentes;
- respuesta `404` para páginas en borrador;
- respuesta `404` para páginas publicadas con fecha futura;
- serialización de bloques ordenados;
- ocultación de campos internos del CMS en el Resource público.

## CMS administrativo
- acceso del administrador al listado de páginas CMS;
- creación de página CMS desde panel admin;
- edición de página CMS desde panel admin;
- validación de unicidad de `slug`;
- conservación del propio `slug` durante edición;
- protección de acceso frente a usuarios no administradores.
- visualización de bloques CMS de una página;
- creación, edición y eliminación de bloques CMS;
- ordenación de bloques por `sort_order`;
- validación de tipo de bloque;
- validación de datos mínimos según tipo de bloque;
- protección frente a edición de bloques desde una página ajena;
- comprobación de que los bloques creados desde admin salen por el endpoint público.

## CMS público React
- consumo del endpoint `GET /api/v1/cms/pages` desde el cliente API existente;
- ruta pública `/contenidos`;
- consumo del endpoint `GET /api/v1/cms/pages/{slug}` desde el cliente API existente;
- ruta pública `/contenidos/:slug`;
- enlaces institucionales del navbar apuntando a rutas CMS;
- índice público con estados de carga, error, vacío y contenido;
- renderizado controlado de bloques sin HTML libre;
- estados de carga y no encontrado.

## CMS seeders
- creación no destructiva de páginas institucionales base;
- slugs institucionales `prensa-media`, `nosotros`, `federaciones`, `academy`, `documentos` y `federarse`;
- creación de bloques mínimos `heading` y `text` para páginas nuevas;
- preservación de páginas CMS existentes con el mismo slug.


# 10. Evolución

La cobertura de pruebas debe crecer junto con el proyecto.

Las nuevas funcionalidades relevantes deberían incorporar pruebas desde su primera implementación.

La evolución prevista incluye extender la cobertura funcional del módulo CMS conforme crezca, validando:
- gestión administrativa de contenidos;
- validaciones de seguridad de archivos adjuntos;
- prevención de spam en formularios públicos.

---

## Mantenimiento

Cuando cambie la estrategia de pruebas, el entorno Docker o el proceso de validación, este documento deberá actualizarse.
