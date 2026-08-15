# Navegación agrupada, Home y footer

## Seguimiento tras 7D.2C1

El footer global incorpora un tercer grupo de navegación con Aviso legal,
Privacidad y Cookies. Los tres destinos tienen contenido versionado real y no
son enlaces condicionales vacíos. El Navbar, sus cuatro controles editoriales,
Cuenta, Home, redes y rutas Club permanecen intactos. No se añade banner y
Contacto continúa desactivado.

## 1. Propósito

Este documento registra `PUBLIC-NAVIGATION-HOME-FOOTER-1`, la implementación
de Fase 7D.1. Describe el árbol público, la portada y el footer estructural que
React ofrece como entrada al MVP.

## 2. Alcance

7D.1 incluye una configuración única de navegación, disclosures accesibles en
desktop y móvil, Cuenta separada, Home veraz, footer global, estados activos,
responsive, pruebas frontend/E2E y documentación. No cambia dominio, API, CMS
ni fuentes editoriales.

## 3. Fuera de alcance

Quedan fuera las páginas legales, la activación productiva de Contacto,
aliases, redirects, canonical, sitemap, robots, SEO completo, landing `/club`,
landing `/aprende`, migración de `/nosotros`, limpieza de código heredado,
contenido CMS, datos, imágenes y despliegue.

## 4. Navegación final

El primer nivel es:

```text
Inicio
Competición
Aprende
├── Aprende a jugar
├── Manual y reglas
└── Escuela de Galotxas
Club
├── Quiénes somos
├── Contacto
├── Federarse
└── Documentos
Cuenta
```

Inicio y Competición son enlaces. Aprende y Club son botones de revelación
sin `to`; no existen rutas `/aprende` ni `/club`. Cuenta es un grupo hermano,
no una rama editorial.

## 5. Configuración

`frontend/src/navigation/publicNavigation.js` declara de forma explícita
`link` o `disclosure`, hijos, rutas exactas, prefijos, `visible` y `audience`.
También centraliza identidad, enlaces Club del footer y redes confirmadas.
Navbar, Home y Footer consumen esta configuración; no mantienen copias de las
URLs canónicas.

## 6. Aprende

Aprende agrupa `/aprende-a-jugar`, `/aprende-a-jugar/manual` y `/escuela` por
intención de descubrimiento. No fusiona fuentes: Aprende y Manual siguen en la
proyección build-time de Knowledge y Escuela conserva su dominio Laravel y su
ruta independiente.

## 7. Club

Club agrupa las cuatro fachadas CMS diferidas:

- `/club/quienes-somos`;
- `/club/contacto`;
- `/club/federarse`;
- `/club/documentos`.

El padre no tiene landing. React controla descubrimiento y presentación; el
cuerpo y los metadatos siguen procediendo del CMS publicado.

## 8. Cuenta

El visitante conserva Iniciar sesión. La sesión autenticada conserva saludo,
Mi Panel y Salir. El grupo mantiene `aria-label="Cuenta"`, permanece fuera de
la lista editorial y no expone administración.

## 9. Desktop

Los disclosures son botones nativos y no dependen de hover. Click, Enter y
Space alternan su estado. Cada disparador usa un ID de panel estable mediante
`aria-controls` y refleja el estado con `aria-expanded`. Abrir un grupo cierra
el otro; click fuera y navegación cierran ambos.

## 10. Móvil

El botón Menú controla la misma lista y el mismo árbol semántico que desktop;
no hay enlaces duplicados. Cerrar el menú principal elimina también el estado
de sus disclosures. Navegar cierra menú y grupos. Los paneles cerrados usan
`hidden` y quedan fuera de teclado y lector de pantalla.

## 11. Estados activos

`/` activa Inicio sólo de forma exacta. La landing y destinos secundarios de
Competición activan visualmente Competición. `/aprende-a-jugar/*` y
`/escuela/*` activan Aprende; `/club/*` activa Club. `aria-current="page"` se
reserva a un enlace cuyo destino coincide exactamente, incluido cada hijo.
Los descendientes mantienen contexto visual sin anunciar una página distinta
como actual. `/contenidos/*`, login y Mi Panel no activan ramas editoriales.

## 12. Home

Home es una puerta de entrada estática de interfaz: hero, dos CTAs principales
y cuatro bloques para Competición, Aprende, Escuela y Club. Todos los bloques
tienen destinos funcionales. No realiza peticiones nuevas, no importa el
artefacto Knowledge y no duplica contenido CMS.

## 13. Copy de interfaz

El H1 es `Galotxas en Monóvar`. La introducción y las descripciones se limitan
a explicar los recorridos observables: competición, reglas, programa escolar e
información institucional. Se retiraron los claims de plataforma oficial, las
noticias ficticias y las tarjetas de Prensa/Federaciones sin destino.

## 14. Imagen

Home no incorpora imagen en 7D.1. El archivo permitido se indicó como
`/media/club/club-actividad.jpg`, pero el repositorio sólo contiene
`club-actividad.JPG`. Para no renombrar, duplicar o publicar una URL sensible a
mayúsculas distinta del contrato, se opta por una composición sin imagen. No se
modifica ningún asset.

## 15. Footer

`Footer` se monta una sola vez tras el `<main>` global. El layout flex lo lleva
al final del viewport en páginas cortas sin fijarlo. Incluye la identidad Club
Galotxes Monòver, cuatro rutas Club canónicas y la fórmula
`© {año actual} Club Galotxes Monòver`, con el año calculado en runtime. Home
deja de montar su footer local.

## 16. Redes

Facebook e Instagram usan las URLs confirmadas. Ambos enlaces abren en nueva
pestaña con `target="_blank"`, `rel="noopener noreferrer"` y una indicación
accesible de ese comportamiento. No se publican teléfono ni dirección.

## 17. Legal, estado histórico de 7D.1

Privacidad, aviso legal y cookies no aparecen como enlaces vacíos. 7D.2A audita
su base técnica y crea sólo borradores internos no publicables. Validación,
texto final, responsabilidad, aplicabilidad y rutas pertenecen a 7D.2C. El formulario de
Contacto continúa desactivado por defecto en producción hasta cerrar privacidad
y operación.

7D.2C1 implementa posteriormente los tres textos, rutas y enlaces de footer
sin alterar el Navbar ni activar Contacto.

## 18. Accesibilidad

La aplicación expone `header`, `nav`, un único `main#main-content` y `footer`.
Un skip link permite saltar al contenido. Los controles tienen foco visible y
áreas mínimas de 44 px. Escape cierra primero el disclosure y devuelve foco a
su botón; un segundo Escape cierra el menú móvil y devuelve foco a Menú. No se
usa el patrón ARIA `menubar`.

## 19. Responsive

Navbar, paneles, Home, CTAs y footer se adaptan a 320, 375, 768, 1024 y 1280
píxeles. El árbol se colapsa hasta 1024 px, las cards pasan a una columna y los
CTAs ocupan el ancho disponible en móvil estrecho. La validación exige ausencia
de overflow horizontal con Cuenta anónima y autenticada.

## 20. Testing

Vitest y React Testing Library cubren configuración, orden, padres sin ruta,
exactos/prefijos, apertura/cierre, exclusión mutua, teclado, Escape, retorno de
foco, click exterior, navegación, menú móvil, sesiones, activos, Home, CTAs,
footer, redes, año y ausencias deliberadas. La suite completa debe pasar junto
con lint, build temporal y `knowledge:check`. El cierre completa 371 tests en
57 archivos, sin fallos de lint.

## 21. E2E

Playwright valida disclosures desktop, Manual, Club, exclusión mutua, Escape,
foco, recorrido móvil a Escuela, cierre al navegar, Cuenta anónima/autenticada,
CTAs de Home, footer y redes seguras, 320 px sin overflow y regresiones lazy de
Club, School y Knowledge. Usa exclusivamente el stack y los fixtures E2E.
El runner protegido completa 37/37 escenarios Chromium y limpia su red,
contenedores e informes temporales.

## 22. Bundle

Navbar, Home y Footer pertenecen al chunk inicial y sólo importan helpers de
rutas, no las features. El build temporal mantiene chunks separados para Club
(11,39 kB), School (15,33 kB), páginas Knowledge y el módulo Knowledge (282,13
kB). El chunk inicial JS es 420,34 kB (124,38 kB gzip). No aparecen warnings
Vite y `frontend/dist` no se
genera ni modifica.

## 23. Gates de 7D.2

7D.2A consolida fuentes, inventario técnico, terceros, identidad e imágenes y
deja borradores internos. 7D.2B implementa la proyección deportiva, minimiza la
sesión persistida y elimina recursos remotos prescindibles sin cambiar esta
navegación. 7D.2C1 resuelve privacidad, aviso legal, cookies y sus rutas.
7D.2C2B completa la primera capa, operación, destinatario configurable y correo
auxiliar de Contacto, manteniendo bloqueada la recogida productiva por default.
Proveedor, entrega, logs, scheduler, backups y activación pertenecen a 7F.
No se deducen aliases, redirects, canonical, indexación o despliegue de este
cierre estructural.

## 24. Criterios de cierre

7D.1 queda cerrada cuando el árbol aprobado funciona con una configuración
única en desktop y móvil; los padres no crean rutas; Cuenta sigue separada;
Home sólo enlaza funciones reales; el footer es global y seguro; accesibilidad,
responsive, suites, build, Knowledge y E2E pasan; no cambian backend, CMS,
Knowledge, dist, imágenes, logo, datos ni dependencias. 7D.2C2, Fase 7, MVP y
despliegue permanecen abiertos.

## 25. Seguimiento de 7D.2B

Navbar, Home, footer, rutas y destinos no cambian. La cuenta restaura su estado
consultando `/me` y no conserva el perfil en `localStorage`; la tipografía usa
la pila del sistema. Las pruebas E2E mantienen los recorridos de Cuenta y Club,
confirman Contacto oculto y la 404 de las rutas legales, y vigilan los hosts
remotos retirados. 7D.2C1 realiza posteriormente la publicación visible desde
una fuente versionada; Contacto sigue reservado a 7D.2C2.

## 26. Seguimiento de 7D.3

Navbar, Home y footer conservan sus destinos. La nueva capa SEO adopta las
rutas Club como canonical, mantiene los aliases históricos sin redirect y deja
los padres Aprende/Club en 404. El layout conserva un solo main y el skip link;
el cambio real de pathname mueve foco a ese main y se anuncia mediante una
única región live. La indexación permanece desactivada hasta 7F. Los 61
escenarios E2E pasan y cierran 7D.3 y 7D; Fase 7 y el MVP siguen abiertos.


---

**Nota de seguimiento posterior (Fase 7F.2):** Tras la aceptación de staging, ciertas decisiones (como el modelo de navegación en Competición y el aplazamiento de noticias y multimedia persistente) han sido promovidas o refinadas en la Fase 7F.2. Ver `docs/28-preproduction-product-refinement.md` y `ADR-042`.
