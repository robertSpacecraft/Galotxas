# Backend Style — Galotxas

## Propósito

Este documento define los criterios de estilo y organización para el backend de Galotxas.

Debe guiar la escritura de código nuevo y la evolución gradual del código existente.

No describe todo lo que existe actualmente, sino el patrón que debe seguirse a partir de ahora.

---

# 1. Alcance

Este documento aplica a:

- API Laravel.
- Panel administrativo Blade.
- Services de dominio.
- Form Requests.
- Resources.
- Middleware.
- Middleware.
- Futuras Policies cuando se incorporen al proyecto.
- Tests backend.
- Organización general del código PHP.

Las instrucciones generales del backend se encuentran en `backend/AGENTS.md`.

---

# 2. Principios generales

- El backend es la fuente de verdad del dominio.
- Los controladores deben coordinar, no concentrar lógica compleja.
- La lógica deportiva debe estar en Services.
- Las respuestas API deben serializarse mediante Resources cuando exista estructura de datos relevante.
- Las vistas Blade deben presentar datos, no calcular reglas de negocio.
- No introducir modelos Eloquent crudos en nuevos endpoints.
- No introducir compatibilidad con SQLite.
- No modificar varias capas sin necesidad clara.

---

# 3. Controladores

## Patrón recomendado

Los controladores deben:

- recibir la petición;
- delegar validación en Form Requests cuando proceda;
- delegar lógica compleja en Services;
- devolver Resources o respuestas API controladas;
- mantenerse pequeños y legibles.

## Evitar

- consultas complejas dentro del controlador;
- lógica deportiva dentro del controlador;
- validaciones manuales repetidas;
- respuestas inconsistentes;
- devolver directamente modelos Eloquent en endpoints nuevos.

---

# 4. Services

Los Services deben concentrar lógica de dominio o procesos reutilizables.

Ejemplos adecuados:

- generación de liga;
- generación de copa;
- cálculo de rankings;
- resolución de participantes;
- validación de resultados;
- inscripción a campeonatos;
- asignación de jugadores a categorías.

Un Service debe tener una responsabilidad clara.

Si un método empieza a mezclar demasiadas reglas, debe dividirse.

---

# 5. Form Requests

Usar Form Requests para:

- creación y edición de entidades;
- operaciones admin;
- validación de resultados;
- inscripción;
- reprogramaciones;
- operaciones con payload relevante.

Evitar validar reglas complejas directamente en Blade o React.

Las reglas de validación deben reflejar el dominio real y no solo el formato del formulario.

---

# 6. Resources

Los Resources son parte del contrato de salida.

Regla principal:

Un contexto funcional debe tener su propio Resource cuando cambien los datos que deben exponerse.

## Contextos habituales

- Público.
- Participante autenticado.
- Admin.
- Interno o trazabilidad.

## Ejemplos

Implementado actualmente:

- `PublicMatchResource`

Ejemplos de futuros Resources por contexto:

- `ParticipantMatchResource`
- `AdminMatchResource`

## Evitar

- un único Resource con muchos condicionales de contexto;
- exponer emails, comentarios internos o trazabilidad en recursos públicos;
- serializar enums sin controlar su valor visible;
- devolver relaciones completas si solo se necesitan campos concretos.

## Enums

Cuando un campo sea enum, el Resource debe devolver un valor estable.

Preferencia:

- valor interno estable para el contrato;
- etiqueta visible solo si el endpoint la necesita.

No depender de cómo Eloquent serialice automáticamente un enum.

---

# 7. API

El contrato API actual debe mantenerse salvo decisión expresa.

Actualmente, muchos consumidores esperan payload funcional en:

`response.data.data`

Por tanto:

- no cambiar la profundidad de `data` en cambios pequeños;
- no introducir `success`, `errors` o `meta` globalmente sin bloque específico;
- no normalizar errores globales durante tareas funcionales;
- no modificar nombres de campos consumidos por React sin revisar consumidores.

La normalización global del contrato API debe hacerse en una fase propia.

---

# 8. Seguridad

Mantener como mínimos:

- autenticación Sanctum;
- middleware de usuario activo en API privada;
- revalidación de usuario activo en panel admin;
- rate limiting en endpoints sensibles;
- no enumeración de emails en recuperación de contraseña;
- Resources públicos sin datos sensibles;
- permisos mediante Middleware y comprobaciones explícitas.

La incorporación de Policies forma parte de la arquitectura objetivo y podrá realizarse cuando aporte una mejora clara respecto a la implementación existente.

Nunca resolver un problema funcional saltándose autorización.

---

# 9. Panel administrativo Blade

## Sistema visual

El panel admin usa Bootstrap 5.3.

No introducir otro framework visual en Blade.

## Layout

Toda vista admin debe extender el layout administrativo existente.

El patrón visual dominante es:

- barra superior oscura;
- fondo gris claro;
- contenedor principal;
- cards blancas;
- tablas responsive;
- botones Bootstrap;
- alerts Bootstrap.

## Estructura recomendada de página

Una vista admin nueva debería tener:

1. cabecera con título;
2. descripción breve si ayuda;
3. acciones principales alineadas;
4. contenido en cards;
5. feedback mediante alerts;
6. tablas o formularios dentro de cards.

## Cards

Usar cards para agrupar bloques funcionales.

Preferir el estilo ya consolidado del panel:

- card blanca;
- sombra suave cuando corresponda;
- cabecera clara;
- cuerpo con espaciado consistente.

No crear bloques visuales aislados sin card si son secciones relevantes de administración.

## Tablas

Las tablas de gestión deben usar:

- wrapper responsive;
- tabla Bootstrap;
- acciones al final;
- botones pequeños;
- estados mediante badges;
- estado vacío claro si no hay registros.

Las tablas estadísticas o rankings pueden usar una variante más densa, pero deben mantener legibilidad.

## Formularios

Los formularios nuevos deben usar:

- `form-label`;
- `form-control`;
- `form-select`;
- grid Bootstrap (`row`, `g-3`, columnas);
- errores por campo con `is-invalid` e `invalid-feedback`;
- botón principal claro;
- acción secundaria de volver o cancelar.

No usar inputs HTML sin clases Bootstrap en vistas nuevas.

## Botones

Jerarquía recomendada:

- acción principal: `btn-primary`;
- acción positiva explícita: `btn-success` o `btn-outline-success`;
- acción secundaria: `btn-outline-secondary`;
- acción destructiva: `btn-outline-danger`;
- acciones menores de fila: botones pequeños.

Evitar que varias acciones parezcan igualmente principales.

## Confirmaciones

Las acciones destructivas o irreversibles deben pedir confirmación.

Actualmente se aceptan confirmaciones nativas del navegador.

Preferir confirmación en `onsubmit` para formularios.

## Alerts y estados vacíos

Usar alerts Bootstrap para:

- mensajes flash;
- errores generales;
- información contextual;
- estados vacíos importantes.

No mezclar loading, vacío y error como si fueran el mismo estado.

## Badges y estados

Usar badges para estados.

Preferencia Bootstrap 5:

- `text-bg-warning` para pendiente;
- `text-bg-success` para aprobado, pagado o validado;
- `text-bg-danger` para rechazado, conflicto o error;
- `text-bg-secondary` para cancelado, inactivo o desconocido;
- `text-bg-info` para información o en revisión.

Los valores internos de enums no deben cambiarse desde Blade.

Las etiquetas visibles sí pueden traducirse.

## Lógica en Blade

Permitido:

- condicionales simples de presentación;
- elegir una clase visual según estado;
- mostrar u ocultar acciones según datos ya preparados.

Evitar:

- consultas a base de datos;
- cálculos de ranking;
- validación deportiva;
- filtros complejos repetidos;
- normalizaciones largas de enums;
- vistas monolíticas difíciles de mantener.

Si una vista crece demasiado o acumula varias secciones, valorar partials.

---

# 10. Código heredado

Algunas vistas antiguas pueden no seguir el patrón actual.

No deben usarse como referencia para vistas nuevas:

- formularios Blade sin clases Bootstrap;
- vistas con HTML básico sin cards;
- estilos residuales no importados.

El código heredado puede mantenerse si funciona, pero no debe copiarse.

---

# 11. Tests backend

Todo cambio relevante debe valorar tests.

Prioridad de test alta:

- seguridad;
- autenticación;
- permisos;
- inscripción;
- resultados;
- rankings;
- Resources;
- contratos consumidos por React.

Los tests de integración deben ejecutarse con MariaDB aislado:

`docker compose --profile test run --rm test`

No ejecutar migraciones destructivas contra la base de desarrollo.

---

# 12. Checklist para cambios backend

Antes de cerrar un bloque, revisar:

- ¿La lógica de dominio está fuera del controlador?
- ¿Hay Form Request si el payload lo justifica?
- ¿La respuesta API usa Resource o estructura controlada?
- ¿Se evita exponer datos sensibles?
- ¿La vista Blade mantiene el estilo Bootstrap actual?
- ¿Hay tests cuando el cambio afecta a seguridad, dominio o contrato?
- ¿Se ha ejecutado la validación razonable?
- ¿La documentación debe actualizarse?