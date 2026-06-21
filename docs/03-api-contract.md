# Contrato API — Galotxas

## Propósito

Este documento describe el contrato actual de la API REST de Galotxas y las decisiones que afectan a su evolución.

No pretende sustituir una especificación OpenAPI. Su objetivo es documentar los principios del contrato utilizados por el proyecto y el estado real de la API.

---

# 1. Principios

- La API es el contrato entre backend y consumidores.
- El backend controla el dominio.
- Los consumidores no deben depender de modelos Eloquent.
- Los cambios incompatibles deben realizarse de forma planificada.
- La estabilidad del contrato tiene prioridad sobre cambios cosméticos.

---

# 2. Consumidores actuales

Actualmente la API es utilizada por:

- Frontend React.
- Herramientas de pruebas.
- Clientes HTTP de desarrollo.

En el futuro podrán añadirse aplicaciones móviles u otras integraciones.

---

# 3. Versionado

La API se expone actualmente bajo:

`/api/v1`

Los cambios incompatibles deberán introducirse mediante una nueva versión cuando resulte necesario.

---

# 4. Estado actual del contrato

Actualmente la API refleja una fase de consolidación.

El proyecto mantiene compatibilidad con los consumidores existentes mientras evoluciona progresivamente hacia un contrato más homogéneo.

Situación actual:

- conviven endpoints completamente serializados mediante Resources con otros heredados;
- existen respuestas que todavía no siguen un formato completamente uniforme;
- el proyecto utiliza actualmente nombres de campos en `snake_case`;
- no existe todavía un envelope completamente homogéneo para todas las respuestas;
- la prioridad es mantener la compatibilidad con el frontend antes que realizar cambios puramente estéticos.

La normalización completa del contrato constituye una fase específica del roadmap.

---

# 5. Resources

Siempre que un endpoint exponga información estructurada deberá utilizar Resources cuando resulte razonable.

Los Resources forman parte del contrato público.

Un mismo modelo puede disponer de distintos Resources según el contexto:

- público;
- participante autenticado;
- administrador.

---

# 6. Seguridad

La API distingue claramente entre:

- endpoints públicos;
- endpoints autenticados;
- endpoints administrativos.

Los datos sensibles nunca deben exponerse mediante endpoints públicos.

---

# 7. Formato de las respuestas

Actualmente la mayoría de los endpoints propios del proyecto utilizan un formato similar al siguiente:

```json
{
    "message": null,
    "data": { ... }
}
```

No obstante, todavía existen respuestas que utilizan directamente el formato estándar de Laravel, especialmente en:

- errores de validación;
- autenticación;
- rate limiting;
- determinadas excepciones del framework.

Esta heterogeneidad es conocida y forma parte del estado actual del proyecto.

La normalización completa del contrato API constituye una fase específica del roadmap.

---

# 8. Compatibilidad

Antes de modificar un endpoint debe comprobarse:

- si React lo consume;
- si existe un Resource asociado;
- si existen tests;
- si el cambio rompe el contrato actual.

---

# 9. Evolución prevista

Se consideran mejoras futuras:

- normalización completa del envelope;
- documentación OpenAPI;
- paginación homogénea;
- normalización de errores;
- metadatos comunes;
- revisión completa de serialización.

Estas mejoras deberán abordarse de forma coordinada y no mezclarse con pequeños desarrollos funcionales.

---

# 10. Relación con otros documentos

- `00-glossary.md`
- `01-domain.md`
- `02-architecture.md`
- `08-resources.md`

---

## Mantenimiento

Siempre que se modifique el contrato público de un endpoint deberá revisarse este documento y, cuando proceda, la documentación específica correspondiente.