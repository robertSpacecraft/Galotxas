# Preparación operativa de la Escuela de Galotxas

## Propósito y alcance

Este documento registra `SCHOOL-OPERATIONAL-READINESS-1`, la Fase 7E. El
objetivo es que la Escuela pueda abrir inscripciones de forma controlada cuando
un entorno productivo disponga de configuración y datos reales revisados. El
bloque prepara la capacidad técnica; no despliega, no activa producción y no
introduce horarios, niveles, cupos, fechas, responsables o canales inventados.

La Fase 7 continúa abierta. Despliegue, correo productivo, backups,
restauración, scheduler y activación pertenecen a 7F; la aceptación final del
MVP pertenece a 7G. Las imágenes, sus autorizaciones y CMS export/import siguen
siendo frentes independientes.

## Arquitectura y fuentes de verdad

La vertical conserva la arquitectura híbrida aprobada:

- Laravel y MariaDB son la fuente del programa, niveles, horarios, ubicaciones,
  inscripciones, alumnado, centros y actividades;
- Blade es la interfaz administrativa;
- `GET /api/v1/school` y `POST /api/v1/school/enrollments` son los únicos
  contratos públicos de Escuela;
- React consume el agregado y presenta el formulario, pero no decide apertura,
  visibilidad efectiva, minoría de edad o transiciones;
- `knowledge/` no contiene todavía una colección pedagógica de Escuela y no se
  ha modificado;
- el CMS genérico y el slug legado `academy` no almacenan configuración
  operativa ni datos personales.

Clasificación de los datos:

| Clase | Fuente |
|---|---|
| Inscripción, participante, representante, estados y aprobaciones | dominio Laravel |
| Centros y actividades educativas | dominio Laravel |
| Apertura declarada, niveles, horarios y ubicaciones | configuración Blade/MariaDB |
| Presentación y explicación pública del proceso | campos administrables de `SchoolProgram` |
| Feature flag, proveedor, credenciales y remitentes | entorno; los secretos no se incorporan al repositorio |

## Inventario real

La Línea 1 usa `SchoolProgram`, `SchoolLevel`, `SchoolLocation`,
`SchoolSchedule` y `SchoolEnrollment`. La Línea 2 usa `EducationalCenter` y
`EducationalActivity`. Las relaciones, validaciones y ciclos de 6B permanecen:

- programa único público, niveles normalizados y horarios semanales;
- día ISO 1–7, inicio anterior a fin, duplicado exacto bloqueado y orden estable;
- ubicación escolar independiente de `Venue`;
- solicitud pública sin cuenta, cuenta Sanctum opcional y estado inicial
  `pending`;
- transiciones `pending → active`, `pending → rejected` y
  `active → withdrawn`;
- centro único por registro con múltiples actividades, fecha y número previsto
  de alumnos, sin identidad individual de asistentes.

No existe dominio de cupos, pagos, curso o temporada escolar. 7E no los simula
ni convierte edades orientativas en reglas rígidas. `SchoolLevel` sigue sin
slug porque el contrato vigente no lo necesita.

## Apertura fail-closed

`SchoolEnrollmentAvailabilityService` centraliza la disponibilidad. Los
estados públicos son `open`, `closed` y `unavailable`:

- `unavailable`: falta cualquier requisito estructural;
- `closed`: la estructura es válida, pero la flag de entorno o la apertura
  declarada está cerrada;
- `open`: configuración completa, apertura declarada y flag de entorno activas.

Los requisitos estructurales son:

1. programa público;
2. presentación pública;
3. explicación pública del proceso;
4. ubicación habitual existente y activa;
5. correo operativo privado válido;
6. al menos un nivel activo y público con horario y ubicación activos;
7. aviso vigente `NOTICE-SCHOOL-ENROLLMENT`.

La flag es `SCHOOL_ENROLLMENT_ENABLED` y su default es `false`. Blade puede
dejar preparada la apertura declarada, pero ningún entorno recibe solicitudes
si la flag no se activa expresamente. El administrador no puede guardar
`enrollments_open = true` con configuración incompleta. React sólo usa el
estado entregado por Laravel.

Cerrar la inscripción no oculta el programa, niveles, horarios o ubicación. Un
fallo de lectura se presenta como error recuperable y no habilita el formulario.

## Datos y contenido pendientes

La ubicación habitual confirmada para una carga humana es `Centro
Polideportivo de Monóvar`. No se ha creado un seeder productivo ni se han
inventado pista, puerta o punto de encuentro. Niveles, días, horas y canales
operativos reales deben cargarse y revisarse en Blade antes de 7F.

`SchoolProgram.public_description` y
`SchoolProgram.enrollment_information` son contenido público administrable.
React no contiene una copia editorial. Los textos ficticios sólo aparecen en
factories/tests y en `E2ESmokeSeeder`, protegido por `APP_ENV=e2e` y la base
desechable `galotxas_e2e`.

## Inscripción, menores y adultos

El formulario sigue siendo anónimo y mínimo. Solicita nombre, nacimiento,
teléfono, correo, privacidad y, opcionalmente, nivel. No solicita DNI,
dirección, fotografía, datos sanitarios, pagos o redes sociales.

- el backend exige teléfono y correo válidos para todas las solicitudes;
- un menor exige nombre y relación del representante;
- un adulto no recibe ni conserva representante;
- la cuenta autenticada es opcional y nunca sustituye los datos declarados;
- la aceptación de privacidad y la autorización opcional de identidad pública
  son decisiones separadas;
- elegir `anonymous` o no solicitar identidad no bloquea la inscripción;
- una solicitud no publica al participante ni crea un `Player`.

El formulario incorpora honeypot, limitador de cinco solicitudes por minuto y
protección de doble envío. La clave del limitador es un HMAC de IP y correo
normalizado; no contiene PII en claro. React no guarda los campos en
`localStorage`, `sessionStorage`, URL, metadata o telemetría añadida. Un `201`
limpia los campos y desplaza el foco al acuse neutral.

## Privacidad y aviso versionado

La primera capa procede de `legal/notices/school-enrollment.md`:

- ID `NOTICE-SCHOOL-ENROLLMENT`;
- scope `school_enrollment`;
- versión `1.0.0`;
- enlace `/legal/privacidad`.

Laravel valida ID y versión contra el artefacto generado y persiste ambos con
el instante de aceptación. La política `LEGAL-002` 1.1.0 ya contenía los plazos
aprobados, por lo que no se ha cambiado su versión:

- pendientes no formalizadas y rechazadas: seis meses;
- alumnos activos: durante la inscripción;
- bajas de alumnos: dos años desde la baja.

La identidad pública de menores continúa bajo su aviso y ciclo independiente.
Los registros de autorización vinculados siguen su propia conservación; 7E no
reescribe el dominio cerrado en 7D.2C2A.

## Retención y anonimización

La migración incremental añade vencimiento, hold, anonimización y actores de
trazabilidad. El backfill calcula plazos sólo desde fechas ya existentes; no
inventa hechos ni cambia estados.

`school:purge-expired`:

- admite `--dry-run`;
- sólo procesa `pending`, `rejected` y `withdrawn` vencidas;
- excluye activas, holds y registros ya anonimizados;
- es idempotente;
- informa únicamente de contadores;
- no está programado en el scheduler.

La anonimización elimina nombre, nacimiento, teléfono, correo, representante,
notas, enlace opcional de cuenta y motivo libre del hold. Conserva programa,
nivel, estado, fechas, aviso y actores administrativos como histórico no
nominal. Blade permite colocar y retirar un hold motivado y anonimizar sólo
cuando el vencimiento se ha cumplido. No existe borrado arbitrario de
inscripciones.

## Administración y trazabilidad

Programa muestra apertura efectiva y causas de configuración incompleta.
Presentación y proceso son editables; correo y teléfono del programa se tratan
como canales operativos privados y no se serializan públicamente.

Inscripciones mantiene filtros, detalle, corrección administrativa explícita,
aprobación, rechazo, baja y reasignación. Registra actor y fecha de corrección,
activación, rechazo, baja y holds. La evidencia de privacidad no es editable
por el formulario de corrección. Una inscripción anonimizada no vuelve a
editarse o activarse.

Niveles, horarios, ubicaciones, centros y actividades conservan la
administración y el borrado conservador de 6B. Centros y actividades no tienen
API; una actividad sólo registra un número previsto, nunca una lista de menores.

## API pública y privacidad

`GET /api/v1/school` expone exclusivamente:

- nombre, presentación y explicación pública;
- `enrollment_status`, `enrollments_open` y el aviso mínimo;
- ubicación habitual activa;
- niveles activos/públicos y horarios efectivamente públicos;
- configuración mínima de identidad opcional cuando su flag está activa.

No expone teléfono, correo, inscripciones, nacimiento, representante, notas,
actores, consentimientos, centros, actividades, flags internos o secretos. La
consulta mantiene eager loading y un número constante de consultas.

`POST /api/v1/school/enrollments` conserva payload allowlisted, respuesta `201`
genérica, `409` para indisponibilidad y ausencia de endpoints de seguimiento.
Un honeypot relleno recibe el mismo acuse sin persistencia. No existe API
administrativa de Escuela.

## Correo

7E no configura proveedor, credenciales, destinatario o remitente productivos.
La inscripción se persiste antes de cualquier notificación auxiliar de
identidad. Esa notificación captura fallos y registra sólo tipo de excepción e
identificador interno; la solicitud permanece recibida. La activación y prueba
de entrega pertenecen a 7F.

## Seguridad, accesibilidad y SEO

Las rutas Blade permanecen bajo sesión, CSRF y administrador activo. Form
Requests, allowlists, asignación controlada, transacciones y bloqueos preservan
estados y relaciones válidos. No se han añadido recursos remotos ni secretos.

La ruta `/escuela` conserva un `main`, un H1, skip link, labels visibles,
fieldset/legend, errores asociados, foco tras validación y resultado, estados
anunciados y navegación por teclado. La composición refluye de 320 px a
escritorio y con zoom 200 %. 7D.3 mantiene metadata, canonical e indexación; no
se crean URLs de solicitud, confirmación o individuo.

## Testing y E2E

La cobertura dirigida incluye:

- flag cerrada por defecto, estados abierto/cerrado/no disponible y bloqueo
  administrativo de configuración incompleta;
- menor, adulto, representante, nivel opcional, aviso exacto, teléfono, payload
  cerrado, rate limit y honeypot;
- privacidad del agregado, allowlists, orden y ausencia de N+1;
- actores, plazos, holds, dry-run, purga y repetición idempotente;
- administración de programa, niveles, horarios, solicitudes, centros y dos
  actividades para un mismo centro;
- contenido administrado, ausencia de contacto privado, doble envío, storage,
  foco, cierre concurrente, responsive, zoom y regresiones SEO/Legal/Knowledge.

Los runners oficiales usan proyectos Docker aislados. No se ejecutan
`migrate:fresh`, `db:wipe` ni seeders E2E contra desarrollo.

Resultado final:

- backend dirigido: 78 tests y 549 aserciones;
- backend completo: 421 tests y 3.309 aserciones;
- frontend dirigido: 24 tests; frontend completo: 484 tests;
- E2E Escuela: 7/7; E2E completo: 63/63 en 2,6 minutos;
- lint, Pint, `php -l`, Legal, Knowledge, SEO y build temporal: correctos;
- Knowledge canónico, proyección pública y estatutos conservan los hashes
  aprobados;
- `frontend/dist`, la base de desarrollo y datos locales permanecen intactos.

## Matriz de revisión humana pendiente

Esta matriz se documenta, pero no se afirma ejecutada:

| Área | Revisión humana |
|---|---|
| Escuela cerrada | contenido visible y formulario ausente |
| Escuela abierta | copy, niveles, horarios y formulario correctos |
| Menor | lenguaje, representante y ausencia de publicación implícita |
| Adulto | ausencia de representante y expectativas de tramitación |
| Privacidad | primera capa, enlace, aceptación y plazos comprensibles |
| Identidad | decisión opcional claramente separada de la inscripción |
| Niveles | nombres, orden y orientación de edades confirmados |
| Horarios | días, horas, ubicación y orden confirmados |
| Móvil | recorrido completo a 320 y 375 px |
| Teclado | orden de foco, envío, errores y disclosures |
| Zoom | reflow al 200 % sin pérdida de contenido |
| Administración | gate, filtros, trazabilidad, holds y anonimización |
| Colegios | centro con dos actividades, fechas y cantidades sin identidades |

## Gates de 7F y riesgos residuales

Antes de abrir producción se requieren dominio/HTTPS, backend y MariaDB
persistentes, datos y contenido reales revisados, niveles y horarios reales,
secrets, CORS/sesiones, correo y remitentes, logs, backups/restauración,
scheduler, staging, migraciones, monitorización, rollback y una prueba real
controlada. `SCHOOL_ENROLLMENT_ENABLED` debe permanecer `false` hasta ese
momento.

Riesgos residuales: datos humanos todavía no cargados, aceptación humana no
realizada, proveedor de correo ausente, scheduler no configurado y posibles
decisiones futuras de curso/cupo que hoy no forman parte del dominio. Ninguno
se oculta mediante fixtures o copy React.

## Criterio de cierre técnico

7E puede cerrarse técnicamente cuando backend, frontend, Legal, Knowledge, SEO,
build temporal y E2E completos pasan, los hashes canónicos permanecen intactos
y Git no contiene artefactos temporales. Ese cierre no habilita producción, no
cierra Fase 7 y no sustituye 7F o 7G.
