---
id: LEG-003
title: Política de cookies y almacenamiento local
slug: cookies
version: 1.1.0
status: vigente
published_at: 2026-10-03
reviewed_at: 2026-10-03
owner: Club Galotxes de Monover
source_draft: docs/legal-drafts/cookies.borrador.md
summary: Estado actual de cookies, almacenamiento local y recursos externos utilizados por Galotxas.
---
# Política de cookies y almacenamiento local

## Alcance

Esta política describe el estado técnico auditado de Galotxas. Distingue las cookies del almacenamiento del navegador y de los recursos que podrían solicitarse a terceros.

## Web pública

La web pública no utiliza actualmente cookies no esenciales según la configuración auditada. No incorpora analítica, publicidad, píxeles, mapas o vídeos embebidos, widgets sociales ni service workers.

Tampoco carga automáticamente Google Fonts, Bunny Fonts o recursos desde jsDelivr. Los enlaces a Facebook e Instagram son enlaces normales y sólo trasladan a la persona usuaria al servicio externo cuando decide activarlos.

## Cuenta y sesión de la API

La zona privada de la web identifica a la persona usuaria con una cookie de sesión de primera parte emitida por la API:

- **Nombre:** `galotxas-spa-session`.
- **Finalidad:** cookie técnica estrictamente necesaria para mantener la sesión de la zona privada y para apoyar la protección frente a peticiones falsificadas (CSRF). No se usa para analítica ni publicidad.
- **Atributos:** `HttpOnly`, por lo que JavaScript no puede leerla; `Secure` en los entornos de staging y producción; `SameSite=Lax`; `Path=/`; asociada únicamente al dominio de la API (no se comparte con otros dominios).
- **Duración:** la configuración actual la hace caducar tras 120 minutos sin actividad. Un cambio de esa duración se reflejará en esta política.
- **Separación:** es distinta de la cookie de sesión del panel administrativo.

La mera visita a la web pública no crea esta cookie. Puede crearse antes de que la persona esté autenticada, cuando la aplicación prepara la protección CSRF para iniciar sesión o registrarse, y se renueva al autenticarse. Al cerrar sesión el servidor invalida la identidad autenticada, pero puede emitirse una cookie de sesión técnica sin identidad que permanece hasta su caducidad.

La aplicación no guarda ningún token reutilizable de autenticación en `localStorage` ni en `sessionStorage`. El identificador de sesión viaja únicamente en la cookie `HttpOnly`. El token de protección CSRF se mantiene sólo en la memoria de la aplicación mientras la página está abierta y no se guarda en el almacenamiento del navegador. El perfil de la cuenta se obtiene del servidor y se conserva en memoria durante la sesión de la interfaz.

Las versiones anteriores de la aplicación guardaban un token de acceso en `localStorage`. Al cargar la versión actual, ese valor heredado se elimina del navegador.

## Administración Laravel

El panel administrativo Blade puede usar una cookie de sesión Laravel y un token CSRF asociado para autenticar a administradores y proteger formularios. Son mecanismos técnicos de primera parte necesarios para el acceso administrativo. Su nombre y atributos efectivos dependen de la configuración segura del entorno desplegado.

## Contacto

El formulario de Contacto está desactivado. La ruta pública no presenta el formulario ni persiste campos de una consulta. Su futura activación exigirá revisar de nuevo esta política y la configuración de correo y seguridad.

## Consentimiento y revisión

No se muestra un banner porque, en el estado técnico descrito, la web pública no instala cookies o recursos automáticos no esenciales que requieran esa elección. Los mecanismos de sesión y autenticación se explican por transparencia.

Esta conclusión debe revisarse antes de incorporar analítica, publicidad, contenido embebido, fuentes remotas, widgets, preferencias persistentes u otro almacenamiento no esencial. Si cambia el inventario, se actualizarán la información, la versión y, cuando corresponda, el mecanismo de consentimiento antes de activar el nuevo recurso.

## Vigencia

La versión y la fecha de publicación mostradas en la cabecera identifican el estado auditado al que se refiere esta política.
