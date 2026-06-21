# Frontend Style — Galotxas

## Propósito

Este documento define los criterios de estilo para el desarrollo del frontend de Galotxas.

Su objetivo es que cualquier componente nuevo mantenga coherencia con la arquitectura y el lenguaje visual existentes.

No pretende describir todo el código actual, sino el patrón recomendado para el código futuro.

---

# 1. Alcance

Aplica a:

- páginas;
- componentes;
- layouts;
- hooks;
- contextos;
- servicios API;
- CSS Modules;
- estilos globales;
- formularios;
- tablas;
- navegación;
- estados visuales.

Las reglas generales del frontend se encuentran en `frontend/AGENTS.md`.

---

# 2. Arquitectura

La arquitectura actual es híbrida, con tendencia a organizar el código por funcionalidad (feature).

## Responsabilidades

### Página

Coordina la funcionalidad de una pantalla:

- obtiene datos;
- llama a servicios;
- compone componentes;
- gestiona navegación.

### Componente

Representa un bloque visual reutilizable.

Debe recibir datos mediante props y contener la mínima lógica posible.

### Hook

Encapsula comportamiento reutilizable.

No representa interfaz.

### Servicio

Toda llamada HTTP debe realizarse desde servicios o utilidades API.

Los componentes nuevos no deben llamar directamente a Axios.

---

# 3. Organización

Siempre que sea posible:

- un componente por archivo;
- un CSS Module por componente con estilos propios;
- nombres en PascalCase.

Ejemplo:

TournamentCard.jsx
TournamentCard.module.css

---

# 4. CSS

## CSS Modules

Son la norma para estilos locales.

Cada componente debe tener sus propios estilos cuando los necesite.

Las clases deben nombrarse en camelCase.

Evitar selectores excesivamente profundos.

## CSS global

Debe limitarse a:

- reset;
- variables;
- tipografía;
- layout general;
- utilidades compartidas.

No añadir estilos específicos de un componente al CSS global.

---

# 5. Contextos visuales

Actualmente existen dos contextos principales:

- zona pública;
- zona autenticada.

Cada uno puede evolucionar visualmente, pero dentro de cada contexto debe mantenerse coherencia.

No mezclar estilos sin motivo.

---

# 6. Componentes

Los componentes deben:

- tener una responsabilidad clara;
- evitar cientos de líneas;
- reutilizar otros componentes cuando sea razonable;
- evitar duplicar marcado HTML.

Evitar componentes que mezclen:

- llamadas API;
- lógica de dominio;
- presentación compleja.

---

# 7. Formularios

Los formularios deben:

- usar estado controlado;
- mostrar errores de validación;
- deshabilitar acciones durante envíos;
- asociar correctamente labels e inputs.

La validación de negocio pertenece al backend.

---

# 8. Tablas y listados

Toda tabla debe:

- ser responsive;
- contemplar estado vacío;
- mantener alineación consistente;
- utilizar badges para estados.

Evitar tablas que desborden en dispositivos pequeños.

---

# 9. Estados remotos

Todo componente que cargue datos debería contemplar cuatro estados:

- loading;
- error;
- empty;
- content.

No reutilizar el mismo diseño para estados diferentes.

---

# 10. Badges y estados

Los estados deben resolverse mediante un vocabulario común.

No traducir ni colorear un mismo estado de formas distintas según el componente.

Evitar hardcodear colores en múltiples lugares.

---

# 11. Consumo de API

Los componentes no deberían conocer la estructura completa de Axios.

Preferentemente:

servicio → payload funcional

componente → datos listos para representar

No duplicar clientes HTTP.

No asumir cambios del contrato API.

---

# 12. Dominio

React no debe convertirse en autoridad del dominio.

No calcular:

- rankings;
- clasificaciones;
- elegibilidad;
- reglas deportivas;
- límites reglamentarios.

Si una regla pertenece al deporte, pertenece al backend.

---

# 13. Accesibilidad

Siempre que sea posible:

- labels asociados;
- botones reales;
- navegación mediante teclado;
- mensajes claros;
- contraste suficiente.

---

# 14. Responsive

Todo componente nuevo debe comprobarse al menos en:

- escritorio;
- tablet;
- móvil.

Especial atención a:

- tablas;
- navegación;
- formularios.

---

# 15. Código heredado

Existen componentes y estilos que ya no representan el patrón actual.

No deben utilizarse como referencia para desarrollos nuevos.

Si un componente antiguo necesita evolucionar, aproximarlo gradualmente al patrón recomendado en lugar de copiar sus decisiones históricas.

---

# 16. Checklist para nuevos componentes

Antes de cerrar un bloque comprobar:

- ¿La responsabilidad está bien delimitada?
- ¿Existe CSS Module cuando corresponde?
- ¿El componente evita llamadas HTTP directas?
- ¿Se contemplan loading, error, empty y content?
- ¿No hay lógica deportiva?
- ¿El responsive es razonable?
- ¿Se mantiene coherencia visual con el contexto?
- ¿Debe actualizarse la documentación?