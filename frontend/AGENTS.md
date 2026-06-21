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

Las responsabilidades del frontend son:

- navegación;
- experiencia de usuario;
- presentación;
- validación básica de formularios;
- consumo de la API;
- gestión de estado de interfaz.

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

No llamar directamente a Axios desde componentes nuevos.

No asumir cambios en el contrato API sin revisar backend.

---

# Calidad

Todo componente con datos remotos debería contemplar:

- loading;
- error;
- empty;
- content.

Evitar duplicar lógica entre páginas.

---

# Validaciones

La validación visual pertenece al frontend.

La validación de negocio pertenece al backend.

Nunca confiar únicamente en validaciones del navegador.

---

# Accesibilidad

Siempre que sea posible:

- labels asociados;
- botones reales;
- navegación mediante teclado;
- mensajes de error claros.

---

# Validación antes de cerrar un bloque

Revisar:

- consistencia visual;
- consumo correcto de la API;
- ausencia de lógica de dominio;
- CSS Modules cuando proceda;
- funcionamiento responsive razonable.