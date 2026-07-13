# Knowledge

La carpeta `knowledge` contiene toda la documentación relacionada con el dominio funcional del proyecto Galotxas.

A diferencia de la carpeta `docs`, que documenta la arquitectura y el desarrollo del software, `knowledge` recopila el conocimiento sobre el propio deporte: sus reglas, terminología, historia, variantes y cualquier otra información necesaria para comprenderlo, preservarlo y trasladarlo correctamente a la aplicación.

Esta documentación constituye la fuente de referencia del proyecto para todas aquellas funcionalidades relacionadas con las Galotxas.

---

# Objetivos

Los objetivos principales de esta documentación son:

- Preservar el conocimiento tradicional de las Galotxas.
- Documentar el deporte de forma rigurosa y estructurada.
- Servir de base para el desarrollo del software.
- Facilitar la generación de reglamentos, manuales y contenido divulgativo.
- Mantener una única fuente de verdad para todos los conceptos relacionados con el deporte.

---

# Organización

La documentación se divide en dos grandes bloques.

## canonical

Contiene la documentación consolidada y considerada oficial dentro del proyecto.

Toda la información utilizada como referencia por el software deberá encontrarse en este bloque.

## working

Contiene notas, investigaciones, entrevistas, borradores y cualquier otro material de trabajo que todavía no forme parte de la documentación consolidada.

---

# Estructura

```text
knowledge/

├── README.md

├── canonical/
│
│   ├── 00_metodologia.md
│
│   ├── reglamento/
│   │
│   ├── glosario/
│   │
│   ├── historia/
│   │
│   └── multimedia/
│
└── working/
    ├── borradores/
    ├── entrevistas/
    ├── notas/
    └── referencias/
```

---

# Principios

Toda la documentación almacenada en `knowledge` deberá cumplir los siguientes principios.

## Fuente única

Cada concepto deberá definirse una única vez.

Los demás documentos harán referencia a dicha definición.

---

## Coherencia

Toda la terminología utilizada deberá mantenerse consistente en todos los documentos y en el software.

---

## Trazabilidad

Toda modificación relevante deberá reflejarse tanto en la documentación como en las funcionalidades afectadas del proyecto.

---

## Evolución

La documentación constituye un documento vivo.

Podrá ampliarse, corregirse y reorganizarse conforme evolucione el conocimiento del deporte.

---

## Respeto por la tradición

El proyecto pretende preservar la riqueza terminológica y cultural de las Galotxas.

Cuando existan varias denominaciones tradicionales para un mismo elemento, todas ellas podrán documentarse.

No obstante, con el objetivo de mantener una documentación consistente, el proyecto podrá adoptar una denominación principal, conservando las restantes como sinónimos o variantes.

---

# Relación con el software

El contenido de `knowledge` forma parte del dominio funcional del proyecto.

Toda funcionalidad desarrollada en el backend o frontend que represente reglas, conceptos o elementos propios de las Galotxas deberá tomar como referencia esta documentación.

Del mismo modo, cualquier modificación relevante de esta documentación deberá revisarse para determinar si afecta al software existente.

---

# Estado

La documentación almacenada en `knowledge` se encuentra en desarrollo continuo.

Todos los documentos deberán indicar su versión, estado y fecha de revisión.