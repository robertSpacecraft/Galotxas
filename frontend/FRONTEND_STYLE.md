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

---

# 17. Estado de la arquitectura visual

Este documento distingue dos niveles:

- **estilo vigente**: las reglas de las secciones 1–16, aplicables al frontend
  actual;
- **arquitectura visual aprobada**: la gate post-MVP de las secciones 18–30,
  aceptada el 2026-08-29 y todavía pendiente de implementación en 5.6 y 6.G.

La aprobación de la gate no significa que Liquid Glass, dark mode o el selector
de tema existan hoy. Los valores ópticos de opacidad, blur y sombra son bandas
iniciales pendientes de calibración durante el piloto.

---

# 18. Alcance aprobado y frontera con administración

Liquid Glass y los temas se aplicarán exclusivamente al frontend React:

- web pública;
- autenticación React;
- Mi Panel;
- navegación React;
- páginas y componentes React.

El panel administrativo Blade bajo `/admin` queda fuera de esta arquitectura:

- conserva su diseño actual;
- no adopta Liquid Glass;
- no adopta dark mode;
- no incorpora el selector `Light / Dark / System`;
- no se rediseña como efecto indirecto de esta gate.

Los contextos público y autenticado podrán compartir el sistema visual sin
dejar de ser contextos funcionales distintos.

---

# 19. Dirección Liquid Glass

El lenguaje se inspira en Liquid Glass, pero no pretende clonar iOS. Debe
aportar profundidad, separación entre contenido y controles, ligereza,
jerarquía, movimiento contenido, legibilidad e identidad propia.

El cristal se reserva principalmente para:

- navegación;
- controles, filtros y selectores;
- toolbars y menús;
- overlays y modales;
- acciones flotantes;
- elementos sobre imágenes.

Las superficies estables se mantienen para:

- tablas y clasificaciones;
- formularios largos;
- textos y noticias;
- listados densos.

No se utilizará glass-on-glass salvo una decisión deliberada y validada. El
efecto visual nunca tendrá prioridad sobre lectura, interacción o rendimiento.

---

# 20. Arquitectura de tokens

La futura implementación utilizará tres niveles. Los componentes no conocerán
directamente si están en light o dark: consumirán tokens semánticos y
materiales.

## 20.1. Primitivos

Incluirán colores base, spacing, radios, blur, sombras, opacidades, motion y
z-index.

Escala inicial de spacing:

```text
4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 px
```

Radios iniciales:

```text
small   8
medium  12
large   18
xlarge  24
pill    999
```

Blur inicial:

```text
none      0
subtle   12
regular  20
elevated 28
```

Duraciones iniciales:

```text
fast        120 ms
normal      180 ms
expressive  260 ms
```

No se animarán continuamente blur, filtros o sombras grandes.

## 20.2. Semánticos

El vocabulario deberá cubrir conceptos equivalentes a:

```text
background-canvas
background-subtle

surface-content
surface-content-raised
surface-interactive

text-primary
text-secondary
text-muted
text-inverse
text-link

border-subtle
border-default
border-strong

brand-primary
brand-secondary
brand-accent

state-info
state-success
state-warning
state-danger

interactive-default
interactive-hover
interactive-pressed
interactive-selected
focus-ring
disabled
```

## 20.3. Materiales

No existirá una clase `.glass` genérica de uso indiscriminado. La taxonomía
aprobada es:

```text
glass-regular
glass-elevated
glass-clear
glass-border
glass-highlight
glass-shadow
glass-blur-regular
glass-blur-elevated
```

---

# 21. Paleta inicial calibrable

Referencias de marca iniciales:

```text
brand profundo     #003366
brand interactivo  #005EA8
highlight          #58A6FF
```

Neutros light iniciales:

```text
canvas              #F8F9FA
texto principal     #1F2937
texto secundario    #4B5563
borde               #DBE3EB
```

Son puntos de partida, no valores ópticos inmutables antes del piloto. Los
estados visuales de dominio serán `info`, `success`, `warning` y `danger`, y
nunca transmitirán significado exclusivamente mediante color.

---

# 22. Superficies y calibración

Bandas iniciales:

| Superficie | Tratamiento inicial |
|---|---|
| Canvas | Sin glass. |
| Content | 95–100 % visualmente opaco. |
| Glass Regular | 70–82 %. |
| Glass Elevated | 78–90 %. |
| Glass Clear | 35–55 %. |

No se cerrarán valores definitivos hasta probar cada material sobre:

1. fondo claro;
2. fondo oscuro;
3. fotografía;
4. scroll o contenido dinámico;
5. móvil.

---

# 23. Tipografía

Se mantendrá inicialmente la pila de sistema:

```text
system-ui
-apple-system
BlinkMacSystemFont
Segoe UI
sans-serif
```

No se añadirá una webfont sólo para imitar Liquid Glass.

Escala inicial aproximada:

```text
display    40–48 / 700
h1         32 / 700
h2         24 / 700
h3         20 / 600
body       16 / 400
secondary  14 / 400–500
caption    12 / 500
```

Resultados, puntuaciones, rankings y columnas numéricas usarán números
tabulares cuando aporte alineación y lectura.

---

# 24. Estados interactivos

Todo control contemplará, cuando proceda:

- default;
- hover;
- focus-visible;
- pressed;
- selected;
- disabled;
- loading.

Una pequeña reducción de escala en `pressed` es opcional. Hover y pressed no
introducirán desplazamientos importantes. El foco será siempre visible,
disabled conservará legibilidad y loading no provocará cambios geométricos
innecesarios ni permitirá duplicar acciones.

---

# 25. Light, Dark y System

Las preferencias serán exactamente:

```text
system
light
dark
```

`system` será el valor inicial y resolverá dinámicamente la preferencia del
sistema operativo. Una elección explícita se conservará localmente. La futura
implementación deberá evitar, cuando sea razonable, el flash perceptible de un
tema incorrecto.

Contexto y tema son dimensiones separadas:

```text
public + light
public + dark
authenticated + light
authenticated + dark
```

El panel Blade no forma parte de esta matriz.

---

# 26. Accesibilidad y compatibilidad

La implementación deberá contemplar:

- contraste;
- teclado y `focus-visible`;
- áreas táctiles suficientes;
- ausencia de información transmitida sólo mediante color;
- `prefers-reduced-motion`;
- `prefers-contrast`;
- reducción de transparencia cuando sea posible;
- fallback sin transparencia o blur.

`prefers-reduced-transparency` podrá aprovecharse donde exista soporte, pero
no será la única defensa. `backdrop-filter` será progressive enhancement. Sin
soporte, o cuando la accesibilidad lo requiera, el material se resolverá como:

```text
superficie más opaca
+ borde
+ sombra
```

La funcionalidad y la legibilidad deben permanecer intactas.

---

# 27. Rendimiento

La implementación inicial se resolverá mediante CSS. No se usarán como base:

- Canvas;
- WebGL;
- shaders;
- refracción física;
- librerías externas de animación.

Se evitarán blur animado durante scroll, blur masivo en filas o tarjetas,
filtros grandes full-screen, exceso de superficies glass simultáneas y glass
sobre glass. En móvil habrá menos capas, blur más limitado y sombras discretas,
priorizando rendimiento y lectura.

El gate visual comparará rendimiento antes y después del rediseño.

---

# 28. Secuencia de implementación aprobada

5.6 seguirá este orden:

1. normalizar los tokens existentes;
2. separar primitivos, semánticos y materiales;
3. implementar Liquid Glass en light;
4. migrar componentes estructurales seleccionados;
5. validar la dirección visual;
6. cerrar los valores definitivos;
7. implementar equivalentes dark;
8. implementar el selector Light/Dark/System;
9. validar ambos temas;
10. retirar estilos y tokens heredados que resulten obsoletos.

No se realizará primero una conversión completa de la web actual a dark.

---

# 29. Rama experimental y piloto obligatorio

Antes de iniciar 5.6 se partirá de `develop` y se creará:

```text
feature/liquid-glass
```

La rama aislará el experimento y podrá aprovechar una Vercel Preview de rama si
está disponible. Antes de migrar toda la web se validarán tres contextos:

1. **Home + Navbar + hero:** fotografía, Glass Clear, navegación y composición
   expresiva.
2. **Competición / clasificación:** datos densos, tablas, legibilidad y
   superficies Content.
3. **Mi Panel + navegación móvil:** contexto autenticado, controles,
   responsive y Glass Regular/Elevated.

Si no existe aceptación humana, se abandonará la rama sin modificar `develop`.
Si existe aceptación, se completará la fase aprobada, se revisará, se integrará
en `develop`, se validará en staging y se promoverá después mediante el flujo
normal. El experimento no se aplicará a `/admin`.

---

# 30. Criterio de cierre futuro

La gate arquitectónica está cerrada, pero 5.6 y 6.G sólo podrán considerarse
implementadas después del piloto, calibración, accesibilidad, responsive,
comparación de rendimiento, validación de light y dark, pruebas proporcionales
y aceptación humana. ADR-047 conserva la decisión y `docs/06-roadmap.md` su
orden operativo.
