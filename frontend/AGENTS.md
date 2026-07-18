# Frontend AGENTS — Galotxas

## Propósito

Este documento contiene las reglas específicas para el desarrollo del frontend del proyecto Galotxas.

Complementa al archivo `/AGENTS.md` de la raíz y aplica únicamente al frontend React.

No debe contener roadmap ni estado del proyecto.

---

# Stack tecnológico

- React
- Vite
- React Router
- CSS Modules
- Axios
- JavaScript

---

# Arquitectura

El frontend consume la API del backend.

No implementa reglas deportivas ni lógica de dominio.

React tampoco constituye una fuente editorial. El contenido administrable no debe escribirse directamente en JSX.

Las responsabilidades del frontend son:

- navegación;
- experiencia de usuario;
- presentación;
- validación básica de formularios;
- consumo de la API;
- gestión de estado de interfaz.

Las fuentes de contenido se integran según su naturaleza:

- contenido dinámico o administrable mediante servicios que consumen contratos API verificados;
- contenido canónico y estable mediante artefactos generados y validados desde `knowledge/`;
- estructura de interfaz y presentación mediante componentes React.

El compilador de `knowledge/` y sus artefactos solo pueden utilizarse cuando estén implementados y documentados; no se deben asumir como capacidades actuales.

---

# Backend como fuente de verdad

Nunca calcular en React:

- rankings;
- puntuaciones;
- elegibilidad;
- categorías deportivas;
- límites reglamentarios;
- estados deportivos.

React representa información; el backend decide.

---

# Organización

Mantener la estructura existente y favorecer la agrupación por funcionalidad.

Las páginas coordinan.

Los componentes representan.

Los hooks encapsulan comportamiento reutilizable.

Los servicios realizan llamadas HTTP.

---

# Componentes

- Componentes en PascalCase.
- Un componente por archivo.
- CSS Module asociado cuando tenga estilos propios.
- Componentes pequeños y reutilizables.

No crear componentes gigantes con múltiples responsabilidades.

---

# Estilos

Los estilos generales se documentan en:

`frontend/FRONTEND_STYLE.md`

No introducir librerías CSS nuevas sin decisión expresa.

---

# API

Consumir siempre la API mediante los servicios existentes.

No llamar directamente a Axios desde componentes nuevos. Si una funcionalidad todavía no dispone de servicio, crear o ampliar la capa de servicio correspondiente.

No asumir endpoints ni cambios de contrato sin verificarlos en backend y en la documentación aplicable.

La seguridad de publicación pertenece al backend: React no debe recibir borradores o contenido no publicable para ocultarlo después.

---

# Calidad

Todo componente con datos remotos debería contemplar:

- loading;
- error;
- empty;
- content.

Evitar duplicar lógica entre páginas.

El contenido generado desde `knowledge/` debe consumirse desde su artefacto validado. No se mantienen copias manuales equivalentes en componentes.

---

# Validaciones

La validación visual pertenece al frontend.

La validación de negocio pertenece al backend.

Nunca confiar únicamente en validaciones del navegador.

---

# Accesibilidad

Toda nueva interfaz debe contemplar:

- labels asociados;
- botones reales;
- navegación mediante teclado;
- mensajes de error claros;
- estructura semántica y foco comprensibles;
- comportamiento responsive razonable.

Las rutas públicas deben ser estables. Un cambio de slug o URL requiere revisar enlaces, navegación, compatibilidad y estrategia de migración.

Las funcionalidades pesadas deben usar lazy loading cuando su inclusión pueda perjudicar de forma relevante al bundle inicial.

---

# Testing y coordinación

- Añadir pruebas unitarias, de integración y E2E según el riesgo y el alcance del cambio.
- Probar los estados `loading`, `error`, `empty` y `content` cuando existan datos remotos.
- Actualizar la documentación cuando cambien navegación, rutas, fuentes de contenido o contratos de presentación.
- Consultar `/backend/AGENTS.md` cuando el cambio consuma o requiera dominio, administración o API.
- Consultar `/knowledge/AGENTS.md` cuando el cambio consuma conocimiento canónico o artefactos derivados.

---

# Validación antes de cerrar un bloque

Revisar:

- consistencia visual;
- consumo correcto de la API;
- ausencia de lógica de dominio;
- CSS Modules cuando proceda;
- funcionamiento responsive razonable;
- navegación por teclado y estados accesibles;
- pruebas y documentación proporcionales al impacto.
