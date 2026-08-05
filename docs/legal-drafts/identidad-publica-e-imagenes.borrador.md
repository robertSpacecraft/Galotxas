BORRADOR INTERNO — NO PUBLICAR
Pendiente de validación jurídica y operativa.

# Identidad pública e imágenes — borrador de política

## Participantes adultos

Proyección objetivo:

1. usar el alias deportivo cuando exista;
2. si no existe, mostrar el nombre y la inicial del primer apellido.

La regla debe implementarse una sola vez en backend y aplicarse a rankings,
clasificaciones, calendarios, partidos, equipos y cualquier metadato o búsqueda
pública. Los Resources actuales todavía exponen campos adicionales; este
borrador no autoriza datos reales hasta cerrar la allowlist y las pruebas.

## Menores

No aplicar automáticamente la regla adulta. Criterio público, minimización,
autorización específica, casos sin nacimiento, cambio de edad, retirada y
tratamiento de históricos: `PENDIENTE DE VALIDACIÓN JURÍDICA, DEPORTIVA Y
OPERATIVA`. En ausencia de decisión aprobada, la identidad no es publicable.

## Junta directiva

La composición actual está confirmada por el club para publicación
institucional y usa nombre y apellidos completos más cargo, sin alias
deportivo: Jorge Sánchez Romero — Presidente; Carlos Bernabé — Vicepresidente;
Abel Payá — Secretario; José Carlos Payá — Tesorero; Antonio Bernabé — Vocal;
Álvaro Marhuenda — Vocal; y Óscar Colomer — Vocal. La inscripción registral de
la Junta, el periodo de mandato, la base e información jurídica y la fecha de
revisión quedan pendientes. Jorge Sánchez Romero está confirmado como
presidente y responsable web, no como representante legal general sin
acreditación expresa adicional.

## Imágenes

Ningún asset queda aprobado por estar en el repositorio o en `public/`. Cada
imagen requiere procedencia, autor/cedente, actividad, fecha, personas
identificables, menores, autorización, canales, vigencia, retirada, custodio,
archivo inequívoco y estado publicable.

## Retirada y trazabilidad

El procedimiento debe localizar original, copias, build, CDN, CMS, redes e
historial técnico; registrar solicitud, decisión, responsable y fecha; y
definir qué copias no pueden eliminarse inmediatamente. Canal y plazos:
`PENDIENTE DE VALIDACIÓN JURÍDICA Y OPERATIVA`.

## Gate

Antes de producción: auditar datos reales, completar alias y nacimiento donde
sea necesario, aprobar la regla de menores, reducir Resources, revisar URLs y
metadatos, registrar autorizaciones de imagen y cubrir backend/frontend/E2E.
