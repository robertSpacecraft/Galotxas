---
id: LEG-003
title: Política de cookies y almacenamiento local
slug: cookies
version: 1.0.0
status: vigente
published_at: 2026-08-06
reviewed_at: 2026-08-06
owner: Club Galotxes de Monover
source_draft: docs/legal-drafts/cookies.borrador.md
summary: Estado actual de cookies, almacenamiento local y recursos externos utilizados por Galotxas.
---
# Política de cookies y almacenamiento local

## Alcance

Esta política describe el estado técnico auditado de Galotxas. Distingue las cookies del almacenamiento local del navegador y de los recursos que podrían solicitarse a terceros.

## Web pública

La web pública no utiliza actualmente cookies no esenciales según la configuración auditada. No incorpora analítica, publicidad, píxeles, mapas o vídeos embebidos, widgets sociales ni service workers.

Tampoco carga automáticamente Google Fonts, Bunny Fonts o recursos desde jsDelivr. Los enlaces a Facebook e Instagram son enlaces normales y sólo trasladan a la persona usuaria al servicio externo cuando decide activarlos.

## Cuenta React y almacenamiento local

Cuando una persona inicia sesión, React conserva el token Bearer de acceso en `localStorage.token` para autenticar las peticiones a la API. Es almacenamiento local de primera parte, no una cookie. Se elimina al cerrar sesión y también ante las respuestas de sesión inválida previstas por la aplicación.

El perfil no se conserva en `localStorage.user`. Cualquier valor legado con esa clave se elimina y el perfil actual se obtiene del servidor para mantenerlo sólo en memoria durante la sesión de la interfaz.

## Administración Laravel

El panel administrativo Blade puede usar una cookie de sesión Laravel y un token CSRF asociado para autenticar a administradores y proteger formularios. Son mecanismos técnicos de primera parte necesarios para el acceso administrativo. Su nombre y atributos efectivos dependen de la configuración segura del entorno desplegado.

## Contacto

El formulario de Contacto está desactivado. La ruta pública no presenta el formulario ni persiste campos de una consulta. Su futura activación exigirá revisar de nuevo esta política y la configuración de correo y seguridad.

## Consentimiento y revisión

No se muestra un banner porque, en el estado técnico descrito, la web pública no instala cookies o recursos automáticos no esenciales que requieran esa elección. Los mecanismos de sesión y autenticación se explican por transparencia.

Esta conclusión debe revisarse antes de incorporar analítica, publicidad, contenido embebido, fuentes remotas, widgets, preferencias persistentes u otro almacenamiento no esencial. Si cambia el inventario, se actualizarán la información, la versión y, cuando corresponda, el mecanismo de consentimiento antes de activar el nuevo recurso.

## Vigencia

La versión y la fecha de publicación mostradas en la cabecera identifican el estado auditado al que se refiere esta política.
