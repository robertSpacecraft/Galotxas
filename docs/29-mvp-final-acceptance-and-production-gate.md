# Aceptación final del MVP y gate de producción

## Estado

Este documento registra `MVP-FINAL-GATE-READINESS-1`, la auditoría 7G.0 y el
contrato ejecutable de Fase 7G.

**7G está preparada y auditada; no se ha iniciado su gate irreversible y no
está cerrada.** No se ha desplegado producción, cambiado DNS, activado flags,
enviado correo real, ejecutado migraciones productivas ni creado un tag o una
release.

**Resend está integrado y la validación en staging se cerró operativamente con éxito;
el P0 de correo/password-reset queda pendiente sólo de la infraestructura de
producción (dominio/secret/smoke test).**

**El P0 de capacidad de recuperación de MariaDB está cerrado para staging tras el PASS operativo de 7G.1D (restore lógico aislado verificado en 5 min 27 s). Producción y media siguen pendientes de su propio gate predeploy.**

Son prerrequisitos inmediatos todavía pendientes:

1. (CERRADO) aceptar humanamente el flujo completo de Copa en staging;
2. ejecutar después la regresión global final de 7F.2 sobre el baseline
   integrado.

7F.2A–7F.2F conservan sus aceptaciones específicas de staging. Esa evidencia
no acredita el refinamiento posterior de Copa ni sustituye la regresión
integrada. Producción y el cierre del MVP siguen además sujetos a los gates
operativos y humanos descritos aquí.

## 1. Contrato de 7G encontrado

`14-mvp-parity-audit.md` y
`15-mvp-editorial-and-navigation-contract.md` ya definen el propósito de 7G:
demostrar los criterios observables del MVP, no implementar otra fase de
producto. Exigen suites completas, recorridos críticos, QA responsive y
accesible, una prioridad multibrowser acordada, aceptación de contenido,
privacidad y operación, smoke productivo, cero P0, decisiones firmadas y
rollback disponible. El tag o la release sólo pueden aparecer después de la
aceptación.

`27-production-readiness-and-deployment-runbook.md` aporta la secuencia de
despliegue, backup, restore, rollback, DNS, correo, observabilidad y smoke. El
addendum `28-preproduction-product-refinement.md` obliga a cerrar Copa y volver
a aceptar el baseline integrado antes de producción.

Faltaba una única especificación que reconciliara esas piezas con el estado
posterior a 7F.2, distinguiera staging de producción y ordenara el Go/No-Go.
Las subfases 7G.1–7G.7 de este documento cubren ese gap sin sustituir los
runbooks operativos ni reescribir la historia del candidato antiguo.

## 2. Prerrequisitos y clases de evidencia

### 2.1 Prerrequisitos obligatorios para producción y cierre

- [x] Copa aceptada en staging sobre el código actualmente candidato.
- Regresión global final de 7F.2 y aceptación humana del baseline integrado.
- Commit candidato único identificado, árbol limpio y `develop` reconciliada
  con `origin/develop`.
- Cero P0 técnicos, operativos, editoriales, legales o de privacidad.
- Configuración y recursos productivos revisados sin copiar datos o secretos de
  staging.
- Backup utilizable, restore aislado acreditado y rollback compatible con las
  migraciones del candidato.
- Responsable humano de producto, operación, contenido y privacidad con
  decisión registrada.

### 2.2 Evidencia automática

- Laravel completo mediante `backend/scripts/run-tests.sh`, sobre MariaDB
  aislada.
- `composer validate --strict`, auditoría de dependencias y los análisis PHP
  aplicables al diff candidato.
- `npm run test:run`, `npm run lint`, `npm run legal:check`,
  `npm run knowledge:check`, `npm run seo:check` y build reproducible.
- `npm run e2e` completo mediante el runner aislado, nunca contra staging o
  producción.
- Preflights frontend/backend, estado de migraciones y probe de media en el
  entorno al que corresponda.
- `git diff --check`, árbol limpio y hash exacto del candidato.

Las cifras de una ejecución se registrarán como evidencia, pero no se fijan
como contrato. El contrato es que se descubran y ejecuten todos los tests del
commit candidato sin omisiones, fallos, skips nuevos o residuos.

### 2.3 Evidencia manual

- Regresión final de staging por recorridos, incluida Copa.
- Revisión visual y funcional en viewports prioritarios, teclado, foco, zoom y
  navegadores acordados.
- Aprobación de contenido real, datos School, Legal, privacidad, identidad e
  imágenes efectivamente publicadas.
- Restore test, rollback rehearsal y revisión de logs/observabilidad.
- Smoke productivo no destructivo, decisión de indexación y Go final.

### 2.4 Evidencia vigente y evidencia que debe repetirse

| Evidencia | Estado para 7G | Tratamiento |
|---|---|---|
| Aceptaciones específicas 7F.2A–7F.2F en staging | Vigente para cada feature | No repetir escrituras destructivas salvo fallo, cambio en esa superficie o duda sobre cleanup/persistencia. |
| Persistencia S3 de 7F.2B y gates remotos de Sponsor, avatar y Noticias | Vigente en staging | Reutilizar; hacer sólo probes y lectura integrada no destructiva en el smoke final. No proyectarla a producción. |
| Legal, Knowledge, CMS base, CORS, auth, administración y `noindex` ya aceptados en staging | Vigente como evidencia histórica de staging | Revalidar por smoke integrado; no rehacer toda la carga editorial temporal. |
| Suites completas anteriores a los commits de Copa | Caducada como aceptación del candidato final | Repetir sobre el commit reconciliado. |
| Smoke global anterior a 7F.2 | Caducado para el baseline ampliado | Repetir después de aceptar Copa. |
| Validación local de Copa: 557 backend, 659 frontend y 68 E2E | Vigente como evidencia local del bloque | No sustituye staging; repetir sólo como parte de la regresión automática final del candidato. |
| DNS, TLS, DB, media, CMS y correo de staging | No acredita producción | Obtener evidencia propia del entorno productivo. |
| Backup, restore y rollback de staging | Restore lógico aislado verificado en 7G.1D (PASS, RTO: 5 min 27 s) | Rollback rehearsal pendiente antes del Go productivo. |
| Cualquier resultado basado en fixtures o `E2ESmokeSeeder` | Válido sólo para test/E2E | No promover datos, cuentas ni credenciales a producción. |

## 3. Baseline reconciliado

| Área | Estado real | Gate restante |
|---|---|---|
| Navegación, rankings, Liga, resultados, conflictos, standings y visibilidad | Cerrado; requiere regresión integrada | Smoke global final de 7F.2. |
| Copa y vista dedicada | Aceptación humana completada en staging (flujo completo verificado) | Regresión global final 7G.2 = PASS. |
| CMS, navegación CMS, Noticias, Knowledge, Club y Legal | Cerrado en sus bloques | Regresión integrada, contenido real y aprobación humana de lo publicado. |
| Bucket y núcleo multimedia, Sponsors, avatar y portadas de Noticias | Cerrado en staging | Configuración y probe propios de producción; derechos de imágenes reales. |
| Escuela de lectura | Implementada | Programa, niveles, horarios, ubicación y contacto reales revisados en producción. |
| Inscripción School | Implementada y fail-closed | Puede permanecer cerrada en la primera producción; abrirla exige gate operativo propio. |
| Contacto | Persistencia/formulario/notificación implementados y fail-closed | El formulario y su notificación pueden permanecer cerrados; el canal institucional publicado debe ser válido. |
| Auth y recuperación de contraseña | Implementados | Entrega de correo real extremo a extremo bajo el contrato MVP vigente. |
| Railway, Vercel, MariaDB, dominios, CORS, headers y health | Acreditados en staging | Recursos, secretos, migraciones y smoke propios de producción. |
| Backup, restore y rollback | Restore lógico aislado verificado (7G.1D); media no validado | Rollback rehearsal y prueba final en producción antes del Go. |
| Admin bootstrap, logs y observabilidad mínima | Capacidad preparada | Ejecutar bootstrap seguro, asignar responsable y acreditar revisión/alerta mínima. |

## 4. Matriz staging frente a producción

| Gate | Staging | Producción | ¿Bloquea 7G? | Evidencia exigida |
|---|---|---|---|---|
| Copa | Escritura y aceptación humana completadas en staging | Sólo lectura/smoke no destructivo | Sí (Cerrado en 7G.2) | Acta del recorrido y ausencia de regresión. |
| Regresión global 7F.2 | Obligatoria tras Copa | No se ejecuta la suite E2E contra producción | Sí | Suites del candidato y checklist de recorridos firmado. |
| Configuración | `deploy:check`, aislamiento y flags cerradas | Preflight con URLs, CORS, secretos y recursos propios | Sí | Salidas saneadas de ambos preflights. |
| Migraciones | Estado y migraciones ensayados | `migrate:status`, backup previo y `migrate --force` manual | Sí | Lista prevista, salida antes/después y decisor. |
| DNS/TLS | Dominios de staging ya separados | Apex, `www`, API, certificados y MX preservados | Sí | Resolución, HTTPS, redirect y ausencia de mixed content. |
| Contenido | Datos ficticios/temporales o copia manual controlada | CMS, School, Legal, Noticias y Sponsors reales, sin seeders demo | Sí | Revisión editorial y funcional por rutas. |
| Media | Bucket `media-staging` y persistencia acreditados | Bucket/credenciales/política productivos y probe con cleanup | Sí | Probe, serving, persistencia y ausencia de key pública. |
| Backup/restore | Restore lógico obligatorio sobre destino temporal. El plan Hobby no dispone de backup nativo. | Dump lógico consistente del estado previo a migrar; estado/copia de media según aplique | Sí | Checksum, cifrado (si aplica), copia externa verificada, restore aislado validado, RTO medido. |
| Rollback | Rehearsal sin afectar producción | Artefactos anteriores y compatibilidad de esquema verificadas | Sí | Parte de ensayo y plan específico del release. |
| Correo | Prueba real cuando el proveedor lo permita | Reset extremo a extremo; otras notificaciones según flags | Sí para reset; no para capacidades cerradas | Entrega, TLS, From/Reply-To y logs saneados. |
| Smoke | Integrado y con escrituras controladas | Mínimo y no destructivo | Sí | Checklist, hora, hash y responsable. |
| Indexación | Siempre `noindex, nofollow` | Primera publicación `initial` también noindex; cambio `live` separado y aprobado | La decisión sí; activarla de inmediato no | Preflight, `robots`, sitemap y decisión registrada. |
| Observabilidad | Revisión manual de plataforma | `/up`, errores, DB, backup y responsable/alerta mínima | Sí si no hay propietario o detección mínima | Checklist y canal de escalado. |

Las suites, seeders y cuentas E2E sólo se usan en el entorno aislado. En
producción no se repiten flujos destructivos para “demostrar” lo ya cubierto;
se comprueban las diferencias propias de infraestructura y datos reales.

## 5. Flags y correo

| Control | Valor inicial esperado | Requisito para activarlo | ¿Puede seguir cerrado en la primera producción? | Impacto en el Go |
|---|---|---|---|---|
| `CONTACT_FORM_ENABLED` | `false` | Aviso vigente, destinatario/canal atendido, persistencia, antispam, privacidad y prueba controlada. | Sí | No bloquea si Contacto publica un canal institucional operativo y la decisión queda registrada. |
| `CONTACT_NOTIFICATION_ENABLED` | `false` | Formulario operativo, mailer real, From/Reply-To, entrega, reintento y logs saneados. | Sí | No bloquea mientras el formulario esté cerrado o la recepción persistida tenga operación aprobada sin notificación. |
| `SCHOOL_ENROLLMENT_ENABLED` | `false` | Programa público real, estado abierto efectivo, niveles/horarios/ubicación/contacto, responsable y procedimiento de solicitudes. | Sí | La experiencia informativa debe ser real; la recepción de solicitudes puede seguir cerrada. |
| `PUBLIC_IDENTITY_AUTHORIZATION_ENABLED` | `false` | Aviso vigente, vinculación, tokens, revisión, revocación, privacidad y operación completas. | Sí | Los menores permanecen anónimos de forma fail-closed; la decisión debe aprobarse antes de publicar identidades reales. |
| `PUBLIC_IDENTITY_NOTIFICATION_ENABLED` | `false` | Autorización activa y correo real probado. | Sí | No se activa de forma independiente ni es necesaria si autorización sigue cerrada. |
| `DEPLOYMENT_SCHEDULER_ENABLED` | `false` | Dry-runs, backup, holds, ejecución manual, ensayo staging y proceso Railway separado supervisado. | Sí | Las purgas quedan manuales con responsable y calendario; no se finge automatización. |
| `MAIL_MAILER` | `resend` integrado para producción; `array` en staging fuera del gate | Key sending-only por entorno, dominio/remitente verificados y entrega extremo a extremo. | No para el contrato actual de recuperación de contraseña | SDK, preflight y fallo no enumerable validados; entrega real aceptada operativamente en staging. Queda pendiente el smoke productivo y llaves propias de producción. `log` sigue sin ser seguro. |

La activación de una capacidad no se deduce de que el código exista. Cada flag
se decide y verifica por separado con `deploy:check --allow-live-features` antes
y después del cambio. La primera producción usa el perfil fail-closed.

## 6. Restricciones de proveedor

| Restricción | Clasificación | Consecuencia y tratamiento admitido |
|---|---|---|
| Railway Hobby bloquea SMTP saliente | Bloqueante del correo requerido por auth; las features con flag pueden permanecer apagadas | 7G.1B validó Resend HTTPS extremo a extremo en staging con éxito. Antes del Go deben verificarse dominios productivos, cargar keys y probar el reset final. Apagar Contacto/School/identidad no resuelve el reset. |
| Railway limita los backups nativos de volúmenes y PITR exclusivamente al plan Pro (`maxBackupsCount = 0` comprobado en este workspace) | Corrige la premisa documental errónea; evita un upgrade innecesario a Pro y derivó en el PASS 7G.1D | El MVP se apoya 100% en mecanismos externos. Staging cerró su P0 de DB con RTO 5 min 27 s. Producción y media continúan pendientes de su propio gate predeploy. |
| Monitor externo persistente ausente | Gate operativo manual; la ausencia de un SaaS concreto no es por sí sola P0 | Sí bloquea el Go carecer de responsable, revisión de `/up`/plataforma/DB/backups y canal de escalado mínimo. Automatización adicional puede quedar post-MVP. |
| Scheduler no desplegado | Capacidad que puede permanecer desactivada | Las purgas se operan manualmente con dry-run y evidencia hasta un bloque posterior. |

No se improvisan proveedores, backups o alertas durante el despliegue. Cualquier
cambio de plataforma, excepción de alcance o riesgo aceptado requiere decisión
humana previa y documentación separada.

### 6.1 Estado de la decisión de correo 7G.1A

Esta fotografía histórica corresponde a la auditoría anterior a la
implementación 7G.1B. La auditoría del flujo y del lock concluyó:

- el Password Broker, los dos endpoints, el token hash de 60 minutos, la URL
  React y la respuesta genérica ya existen;
- la notificación usada es la estándar de Laravel, por el canal Mail y de
  forma síncrona;
- SMTP, `log` y `array` son los únicos transports configurados y ejecutables
  para su finalidad actual; `sendmail` carece de binario en la imagen;
- SES tiene SDK sólo por la dependencia multimedia, sin contrato operativo;
  Resend y Postmark tienen stubs de configuración pero no sus paquetes;
- `deploy:check` está acoplado al SMTP DonDominio y sólo revisa correo al abrir
  notificaciones opcionales, por lo que hoy puede pasar con reset inoperante;
- el mailer `log` de staging registra el mensaje completo, incluida la URL con
  token, y debe sustituirse por `array` fuera de la ventana de entrega real;
- los tests no cubren todavía URL exacta, expiración, reutilización, fallo del
  proveedor ni el nuevo preflight obligatorio.

Se selecciona **Resend mediante API HTTPS** porque Railway lo recomienda para
Hobby, Laravel 12 aporta transport oficial y el repositorio ya tiene las
entradas `resend`. Requiere instalar `resend/resend-php`, introducir
`RESEND_API_KEY` como secret sending-only restringido al dominio, adaptar los
ejemplos/preflight y completar pruebas automáticas, staging y smoke productivo.
**Postmark** queda como alternativa si Resend no supera el gate operativo.

La selección no activa correo ni cierra el P0. No se han instalado
dependencias, creado cuenta, cargado credenciales, cambiado DNS, enviado
mensajes ni tocado staging/producción. El detalle, comparación, contrato de
variables y plan de prueba están en
`27-production-readiness-and-deployment-runbook.md`.

### 6.2 Estado de la implementación local 7G.1B

El SDK oficial `resend/resend-php` 1.10.0 está fijado y el transport Laravel se
carga sin realizar envíos. `deploy:check` acepta `array` sólo como baseline
seguro de staging, rechaza `log`, valida Resend completo durante el gate y lo
exige en producción aun con flags opcionales cerradas. El fallo síncrono de
entrega conserva el mismo `200`, invalida el token emitido y deja únicamente
un código de fallo saneado.

La regresión local completa sobre MariaDB aislada pasa y Composer no presenta
advisories. Contacto, Escuela, identidad y scheduler siguen cerrados. No se ha
creado cuenta Resend productiva, configurado secret, cambiado DNS, verificado dominio
ni modificado Railway de producción. Por ello el P0 no se
cierra: la validación real en staging fue exitosa, falta repetir el proceso en el entorno productivo con secret y dominio finales, y, después,
el smoke productivo de su gate correspondiente.

### 6.3 Estado de la recuperación (7G.1C / 7G.1D)

**CORRECCIÓN OPERATIVA 2026-08-24:** Aunque la documentación pública de Railway era ambigua, la verificación efectiva en este workspace Hobby demuestra que `maxBackupsCount = 0`. La interfaz indica: *“Backups and point-in-time recovery (PITR) are only available for customers on the Pro plan.”* Por lo tanto, en el entorno actual NO hay backup nativo ni manual, NO hay PITR y NO se puede exigir un snapshot nativo predeploy.

La estrategia se corrige para no depender del proveedor:

La recuperación MVP queda separada en tres capas:

1. dump lógico consistente de MariaDB (con SHA-256, compresión, cifrado y copia externa verificada);
2. inventario y copia independiente de los objetos privados (avatares, portadas, logos), ya que MariaDB sólo guarda sus referencias y Railway Buckets no ofrece object versioning, lifecycle ni backup;
3. runbook de restore aislado y rollback forward-fix definido y ensayado.

La imagen Laravel no contiene `mariadb-dump`; el drill debe usar un cliente
MariaDB 11.4 controlado y verificar su versión. El restore lógico hacia una DB
temporal aislada de staging es obligatorio antes del Go. Se proponen
como objetivos (no SLA, pendientes de medición): RPO 24 h, RTO 4 h para núcleo
controlado y 8 h para reabrir escrituras, retención lógica de 30 diarios y 3 mensuales,
y retención de media mínimo 30 días, con responsable y suplente.

7G.1D completó el "restore lógico aislado validado en staging" con resultado PASS. El ensayo generó un dump lógico consistente (SHA-256: 84243b3be0efdc557fb93ecf1bc4565331492c3470f57269767ca33e1a314f5a), restauró en una DB Docker efímera y aislada, superó las verificaciones estructurales (conteos, migraciones, foreign keys) y validó un RTO de 5 min 27 s (cumpliendo el objetivo read-only de 4h). El P0 de capacidad de recuperación MariaDB se declara CERRADO para staging. Producción sigue pendiente de su propio gate predeploy (dump cifrado, copia separada) y el recovery de media sigue siendo un requisito pendiente e independiente. El RTO para reapertura de escrituras (8 h) no se acreditó en este drill.

## 7. Regresión global final de 7F.2 preparada

Este checklist se ejecutará una sola vez después de aceptar Copa. No se ejecuta
como parte de 7G.0.

### 7.1 Automático sobre el commit candidato

- [ ] Backend completo sobre MariaDB aislada y sin tocar desarrollo.
- [ ] Composer estricto/audit, Pint y `php -l` aplicables.
- [ ] Frontend completo, lint, Legal, Knowledge, SEO y build sin warnings.
- [ ] E2E completo del runner aislado, incluidos Copa, CMS Navigation,
      Noticias, Sponsor, avatar, Escuela, Contacto, Legal y SEO.
- [ ] `git diff --check`, árbol limpio, ningún residuo Docker/Playwright y hashes
      generados sincronizados.

### 7.2 Público en staging

- [ ] Home, Navbar desktop/móvil, Footer y Cuenta; disclosures, Escape, foco y
      ruta activa.
- [ ] Competición → campeonato → categoría → Liga/standings/schedule/partido y
      Rankings histórico/temporada/campeonato/categoría.
- [ ] Copa completa: semifinales, conflicto/resolución, Final, tercer puesto,
      campeón, retorno contextual y exclusión de Copa en categoría/Mi Panel.
- [ ] Noticias: listado, detalle, portada y metadata; Sponsor visible y media
      persistente sin exponer keys.
- [ ] Club estructural y placement CMS, cuatro fachadas, Knowledge/Manual,
      Escuela informativa, Legal y 404/SEO básico.
- [ ] Estados cerrados de Contacto, inscripción School, identidad y scheduler;
      `noindex, nofollow` y ausencia de sitemap en staging.

### 7.3 Usuario en staging

- [ ] Registro, login, `/me`, logout y semánticas 401/403/419.
- [ ] Mi Panel, fallback/avatar privado, upload/replace/delete sólo si se usa un
      objeto temporal expresamente autorizado y con cleanup.
- [ ] Partidos propios, reporte coincidente, discrepancia, resultado y rankings
      propios sin datos privados.
- [ ] Recuperación de contraseña se marca bloqueada, no superada, hasta disponer
      de entrega real; `log` no acredita el recorrido.

### 7.4 Administración en staging

- [ ] Login y permisos de administrador activo.
- [ ] CMS, navegación CMS, Noticias y Sponsor accesibles con estados vigentes.
- [ ] Conflicto deportivo visible y resolución ligada al recorrido de Copa.
- [ ] School/Contacto/identidad permanecen privados y sus flags cerradas.
- [ ] No repetir altas/borrados multimedia ya aceptados salvo necesidad del
      recorrido; todo dato temporal creado se identifica y limpia.

### 7.5 Infraestructura y revisión humana

- [ ] `/up`, API, admin, CORS, TLS, migraciones sin pendientes, media probe y
      persistencia tras redeploy.
- [ ] Logs sin PII, tokens, secretos, object keys o errores 5xx pendientes.
- [ ] 320 px, desktop, 200 % zoom, teclado y navegador/es prioritarios acordados.
- [ ] Producto, privacidad y operación firman la aceptación del baseline 7F.2.

## 8. Gate ordenado de 7G

La subdivisión siguiente formaliza el orden operativo sin cambiar el alcance de
7G ya aprobado.

### 7G.1 — Reconciliación del candidato

- **Entrada:** Copa aceptada en staging y los cierres específicos
  7F.2A–7F.2F vigentes.
- **Acciones:** fijar hash, reconciliar `develop/origin`, revisar diff e
  inventario de migraciones, ejecutar las suites automáticas y consolidar P0.
- **Evidencia:** salidas completas, árbol limpio, hash y lista de migraciones.
- **Salida:** candidato único reproducible, sin P0 de código conocidos y listo
  para la regresión global 7F.2.
- **Rollback:** no promover el hash; corregir en un bloque acotado y repetir.
- **Responsable:** técnico y producto aceptan el alcance del candidato.

### 7G.2 — Regresión global final de staging

*ESTADO ACTUAL: PASS / CERRADO. La regresión global sobre el candidato actual en staging (Vercel y Railway) superó todos los criterios (backend, frontend, SEO, privacy, QA visual). No se identificaron P0 ni bloqueos vigentes (todos los hallazgos previos, como sitemap, han sido resueltos).*

- **Entrada:** candidato de 7G.1 desplegado en staging con flags cerradas.
- **Acciones:** ejecutar el checklist de la sección 7 y la QA priorizada.
- **Evidencia:** acta por recorrido, logs saneados, incidencias y decisión.
- **Salida:** baseline integrado y 7F.2 aceptados sin P0; se satisface el último
  prerrequisito funcional antes de preparar producción.
- **Rollback:** volver al deployment staging anterior y dejar flags cerradas;
  limpiar únicamente datos temporales identificados.
- **Responsable:** producto, QA, privacidad y operación.

### 7G.3 — Auditoría de configuración productiva

*ESTADO ACTUAL: ABIERTA / SIGUIENTE GATE. La regresión global 7G.2 ha sido completada (PASS), permitiendo la transición a este gate para la auditoría y despliegue real de la configuración productiva.*

- **Entrada:** 7G.2 verde; recursos productivos creados pero sin tráfico real.
- **Acciones:** revisar secretos, URLs, CORS, DB, migraciones, media, correo,
  DNS/MX, flags, admin bootstrap, backup/restore y rollback.
- **Evidencia:** preflights saneados, matriz de variables, restore/rehearsal,
  plan de migración y decisión de flags.
- **Salida:** configuración lista para desplegar, todavía sin mutación
  irreversible.
- **Rollback:** no conectar dominio ni migrar; revocar credenciales de prueba
  cuando corresponda.
- **Responsable:** operación con revisión técnica y de privacidad.

### 7G.4 — Go/No-Go humano

- **Entrada:** 7G.1–7G.3 completos y checklist de la sección 9 sin bloqueos.
- **Acciones:** aceptar o rechazar expresamente riesgos, contenido, ventana,
  responsables y plan de vuelta atrás. Un P0 produce siempre `NO-GO`.
- **Evidencia:** decisión fechada, decisores, hash, ventana y condiciones.
- **Salida:** autorización explícita o parada segura.
- **Rollback:** `NO-GO`; no existe mutación que revertir.
- **Responsable:** propietario del producto y responsable operativo.

### 7G.5 — Despliegue productivo controlado

- **Entrada:** `GO` vigente para el mismo hash y ventana.
- **Acciones:** seguir el orden exacto del runbook: flags cerradas, backup,
  migración manual, admin, URLs de proveedor, DNS/TLS y frontend `initial`
  noindex. No ejecutar seeders demo ni E2E.
- **Evidencia:** deployment/hash, migraciones antes/después, health, DNS/TLS,
  backup y eventos de la ventana.
- **Salida:** producción accesible de forma controlada y preparada para smoke.
- **Rollback:** deployment anterior compatible, maintenance si procede y
  restore sólo conforme al runbook; nunca `migrate:rollback` automático.
- **Responsable:** operación ejecuta; técnico y producto permanecen disponibles.

### 7G.6 — Smoke productivo mínimo

- **Entrada:** 7G.5 estable y logs disponibles.
- **Acciones:** smoke no destructivo de web/API/admin/auth, contenido real,
  media, rutas críticas, privacidad, flags, SEO `initial` y observabilidad.
- **Evidencia:** checklist, hora, hash, respuestas esperadas y logs saneados.
- **Salida:** aceptación productiva o rollback inmediato.
- **Rollback:** aplicar el plan de 7G.5 si aparece cualquier P0; no abrir flags
  ni indexación para compensar un fallo.
- **Responsable:** producto, QA y operación.

### 7G.7 — Cierre documental, candidato, tag y release

- **Entrada:** smoke productivo aceptado, cero P0 y commit desplegado inequívoco.
- **Acciones:** registrar resultados y limitaciones, decidir por separado la
  etapa `live`/indexación, reconciliar `main` según el flujo aprobado, crear un
  tag inmutable y publicar las notas sólo con autorización expresa.
- **Evidencia:** acta final, hash de producción/main/tag, release y estado de
  indexación/flags.
- **Salida:** 7G, Fase 7 y MVP cerrados; nunca antes.
- **Rollback:** no reutilizar un tag; retirar una prerelease o revertir el
  deployment sólo con aprobación y trazabilidad.
- **Responsable:** propietario del producto autoriza; responsable técnico
  ejecuta Git/release.

## 9. Checklist Go/No-Go

- [ ] 7F.2 completa: Copa y regresión global final aceptadas.
- [ ] Suites completas verdes sobre el hash exacto.
- [ ] Árbol limpio y `develop`, `origin/develop` y candidato reconciliados.
- [ ] Migraciones identificadas, ensayadas y orden de aplicación aprobado.
- [ ] Producción: dump lógico cifrado pre-migración, media verificado, rollback forward-fix definido.
- [ ] Rollback de frontend/backend/esquema disponible y ensayado.
- [ ] Bootstrap del administrador seguro, idempotente y sin credenciales demo.
- [ ] Recursos, dominios, TLS, CORS, sesiones, headers y health productivos.
- [ ] MariaDB y media persistente productivas preparadas y probadas.
- [ ] Cada flag tiene valor y responsable explícitos; todas parten cerradas.
- [ ] Correo real permite reset; las capacidades dependientes no aprobadas
      permanecen cerradas.
- [ ] Legal, privacidad, identidad, copyright e imágenes publicadas aprobados.
- [ ] CMS, Noticias, Sponsor y School contienen datos reales revisados; no hay
      fixtures, seeders demo, placeholders o secretos.
- [ ] Logs, backup, DB, health, alertas/canal mínimo y guardia humana definidos.
- [ ] Navegadores/viewports prioritarios y limitaciones aceptados.
- [ ] Responsable de producto y operación registran `GO` para el mismo hash.

Una excepción no convierte una comprobación fallida en verde. Si altera el
alcance observable del MVP, debe aprobarse y versionarse antes de un nuevo
Go/No-Go.

## 10. Deuda y prioridades

### P0 — bloquea el cierre bajo el contrato vigente

- Aceptación humana de Copa y regresión global final de 7F.2.
- Regresión automática del hash candidato y cero defectos críticos.
- Correo real extremo a extremo para recuperación de contraseña.
- Recursos/configuración productivos, migraciones, contenido real y media
  persistente verificados.
- Backup con restore aislado, rollback rehearsal y responsable operativo.
- Aprobación de Legal, privacidad, identidad y derechos de cada imagen real
  publicada.
- Smoke productivo, observabilidad mínima, decisión humana y trazabilidad del
  release.

Las flags de Contacto, inscripción School, identidad de menores y scheduler no
son P0 por el mero hecho de permanecer en `false`. Sí sería P0 abrir cualquiera
sin su gate o publicar una promesa funcional que dependa de ella.

### P1 — post-MVP cercano

- Borrado/retirada administrativa de `CmsPage` con política de integridad.
- Edición completa de perfil y resumen directo de equipo/participación.
- Reprogramación React y hardening de su API.
- Sitemap dinámico de Noticias, metadata por respuesta y eventual
  SSR/prerender.
- Matriz automatizada con navegadores adicionales y auditoría de accesibilidad
  más amplia; 7G conserva una revisión humana priorizada mínima.
- Automatización y alertas del dump lógico y la copia de media, además del
  scheduler, después de validar el restore y la operación controlada; los
  schedules nativos de volumen (inexistentes en este workspace) no sustituyen esas capas.

### P2 — evolución

- Patrocinios contextuales y perfil deportivo público opcional.
- OpenAPI y normalización adicional de contratos/envelopes.
- Migración de Bearer en `localStorage` a cookies seguras con CSRF.
- Pagos, métricas avanzadas, roles granulares y ampliación multimedia del CMS.
- `academy`, aliases/redirects y limpieza heredada no necesaria para el gate.

La prioridad sólo cambia si una deuda provoca un incumplimiento observable o un
riesgo crítico en el candidato concreto.

## 11. Acciones de alto riesgo reservadas

7G.0 no autoriza producción, DNS, migraciones, flags, correo real, secretos,
backups con datos reales, tag, release, merge o push. En 7G esas acciones se
ejecutan únicamente en su subgate, con entrada satisfecha, responsable humano y
rollback disponible.

## 12. Criterio de cierre

Preparar este documento no inicia 7G.5, no acepta Copa, no ejecuta la regresión
global y no reduce los P0 operativos. 7G sólo podrá cerrarse cuando 7G.1–7G.7
dispongan de evidencia para el mismo candidato y no quede ningún P0.

Hasta entonces, el estado oficial es:

**7G preparada y auditada; NO iniciada en su gate irreversible y NO cerrada.**
