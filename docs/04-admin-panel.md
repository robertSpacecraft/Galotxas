# Panel administrativo — Galotxas

## Propósito

Este documento describe el funcionamiento del panel administrativo Blade de Galotxas.

El panel administrativo es la herramienta principal para gestionar la competición desde el backend Laravel.

Describe procesos funcionales, no detalles visuales. Las reglas de estilo se encuentran en `backend/BACKEND_STYLE.md`.

## Vistas Principales

### Dashboard

El panel de inicio actúa como el centro de mando (hub) para la gestión del sistema.

- **Métricas Operativas**: Muestra de forma inmediata una tabla de **Solicitudes de inscripción pendientes** de revisión (limitadas a las últimas 20 por razones de rendimiento).
- **Acciones Rápidas**: Permite **Aprobar** o **Rechazar** solicitudes directamente desde la tabla del dashboard, sin necesidad de entrar al detalle de cada campeonato, agilizando enormemente el flujo de revisión.
- **Aprobados pendientes de categoría**: Muestra una segunda tabla con las solicitudes ya aprobadas cuyo jugador todavía no ha sido asignado a ninguna categoría del campeonato correspondiente (limitadas a las últimas 20). Incluye un enlace directo a la gestión de categorías del campeonato para facilitar la asignación.
- Desde el dashboard, el administrador puede revisar qué requiere atención inmediata sin necesidad de navegar a través del árbol de competiciones.
- Contiene accesos rápidos a la creación y listado de Temporadas, Campeonatos, Usuarios y Jugadores.

---

# 1. Naturaleza del panel

El panel administrativo es una interfaz web Blade independiente del frontend React.

Forma parte oficial de la arquitectura del proyecto y permite gestionar el dominio de competición sin depender de la API pública.

---

# 2. Acceso y seguridad

El panel requiere:

- usuario autenticado;
- rol de administrador;
- usuario activo.

Si un administrador es desactivado mientras mantiene una sesión abierta, el sistema debe invalidar la sesión y expulsarlo del panel.

---

# 3. Responsabilidades principales

El administrador puede gestionar:

- usuarios;
- jugadores;
- temporadas;
- campeonatos;
- categorías;
- solicitudes de inscripción;
- asignaciones a categorías;
- participantes competitivos;
- equipos de dobles;
- calendarios;
- partidos;
- resultados;
- rankings.

---

# 4. Ciclo de vida de la competición

1. Crear temporada.
2. Crear campeonatos.
3. Crear categorías.
4. Revisar solicitudes.
5. Asignar jugadores mediante `CategoryRegistration`.
6. Crear equipos cuando proceda.
7. Crear participantes competitivos (`CategoryEntry`).
8. Generar liga.
9. Generar copa.
10. Gestionar partidos.
11. Validar resultados.
12. Revisar clasificaciones y rankings.

---

# 5. Ciclo de vida del jugador

Desde el punto de vista administrativo un jugador pasa por:

1. Usuario registrado.
2. Perfil de jugador creado.
3. Solicitud presentada.
4. Solicitud pendiente.
5. Solicitud aprobada o rechazada.
6. Asignación administrativa mediante `CategoryRegistration`.
7. Creación del participante competitivo (`CategoryEntry`).
8. Participación en partidos.

Una solicitud aprobada no implica competir automáticamente.

La asignación administrativa y la creación del participante competitivo son pasos independientes.

En individuales el `CategoryEntry` referencia a un jugador.

En dobles referencia a un equipo.

---

# 6. Solicitudes de inscripción

Las solicitudes representan la intención de un jugador de participar en un campeonato.

El panel debe diferenciar claramente:

- solicitudes pendientes;
- solicitudes aprobadas;
- solicitudes rechazadas;
- estado del pago.

La aprobación y el pago son procesos independientes.

Actualmente el pago puede gestionarse manualmente.

---

# 7. Asignación a categorías

Una vez aprobada una solicitud el administrador asigna al jugador mediante `CategoryRegistration`.

Reglas funcionales:

- solo jugadores con solicitud aprobada;
- un jugador no puede quedar asignado a dos categorías del mismo campeonato;
- `CategoryRegistration` representa la asignación administrativa;
- `CategoryEntry` representa la unidad competitiva final;
- en individuales el `CategoryEntry` referencia al jugador;
- en dobles referencia al equipo;
- debe respetarse la estructura del campeonato.

---

# 8. Equipos de dobles

En campeonatos de dobles el administrador crea equipos con jugadores aprobados.

Posteriormente se crean los correspondientes `CategoryEntry` que competirán en la categoría.

---

# 9. Calendario y fases

El administrador puede generar:

## Liga

Fase base de la competición.

## Copa

Fase eliminatoria generada a partir de la clasificación de liga.

La lógica pertenece a Services, no a Blade.

---

# 10. Ciclo de vida del partido

1. Generado.
2. Programado.
3. Disputado.
4. Resultado enviado.
5. Resultado confirmado o en conflicto.
6. Resultado validado.
7. Cierre para efectos de rankings.

Estas etapas representan el ciclo funcional del partido y no implican necesariamente una correspondencia directa con un único campo o enum del modelo.

---

# 11. Resultados

Solo los resultados validados producen efectos oficiales.

El panel permite:

- revisar envíos;
- detectar conflictos;
- resolver discrepancias;
- validar resultados;
- mantener trazabilidad.

---

# 12. Rankings

Blade muestra información calculada por el backend.

No implementa algoritmos de clasificación.

---

# 13. UX administrativa

Debe priorizar:

- claridad;
- tareas pendientes;
- acciones seguras;
- separación entre estados administrativos y deportivos;
- coherencia visual;
- prevención de errores.

---

# 14. Funcionalidades futuras

- pagos online;
- notificaciones;
- asignación automática de categoría;
- sugerencias de categoría;
- filtros avanzados;
- auditoría ampliada;
- panel de métricas avanzado.

---

## Mantenimiento

Cuando cambie el flujo administrativo este documento deberá actualizarse.