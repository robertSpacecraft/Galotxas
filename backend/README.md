<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Entorno Galotxas

El backend se ejecuta con Compose en tres proyectos separados: `galotxas` para desarrollo, `galotxas-test` para PHPUnit y `galotxas-e2e` para Playwright. Los tests backend no forman parte del archivo Compose de desarrollo y nunca deben ejecutarse contra su base.

Desde la raíz del monorepo:

```bash
backend/scripts/run-tests.sh
backend/scripts/run-tests.sh --filter=School
```

El runner valida con `docker compose config` el proyecto, archivo, entorno, base, red, volúmenes y nombres de contenedor antes de limpiar. Su MariaDB usa `tmpfs` y el cleanup sólo puede actuar sobre `galotxas-test`. El diseño, los comandos de desarrollo y la recuperación tras el incidente de 6C se mantienen en [`docs/13-docker-environment-isolation.md`](../docs/13-docker-environment-isolation.md).

## Contacto institucional

Laravel incluye la operación de `ContactRequest`: consentimiento versionado,
persistencia local como recepción, POST protegido, configuración fail-closed,
notificación auxiliar con estado y reintento, bandeja Blade, cierre, retención,
hold y anonimización. Se mantiene desactivada por defecto mediante
`CONTACT_FORM_ENABLED=false`; destinatario, remitente, mailer y estados nunca
se exponen en la API. Consulta el contrato histórico en
[`docs/17-club-technical-preparation-and-contact.md`](../docs/17-club-technical-preparation-and-contact.md)
y su consumo público en
[`docs/18-club-public-facades.md`](../docs/18-club-public-facades.md). El entorno
E2E habilita el formulario sólo dentro de su base temporal protegida; el default
de cualquier otro entorno continúa siendo `false`.
La operación vigente y los gates de 7F están en
[`docs/24-contact-operation-and-privacy-layer.md`](../docs/24-contact-operation-and-privacy-layer.md).

## Escuela de Galotxas

La recepción pública de Escuela es fail-closed. Su default es
`SCHOOL_ENROLLMENT_ENABLED=false` y `SchoolEnrollmentAvailabilityService`
combina la flag con programa público, contenido, ubicación, nivel/horario y
aviso vigente. Los contactos de inscripción y del programa son privados y no
se serializan en `GET /api/v1/school`. El comando
`school:purge-expired --dry-run` permite auditar vencimientos sin modificar
datos; no está conectado al scheduler. La preparación y los gates productivos
se documentan en
[`docs/26-school-operational-readiness.md`](../docs/26-school-operational-readiness.md).

## Privacidad técnica

Las respuestas anónimas de competición usan Resources con listas cerradas y
`PublicPlayerIdentityService`: un adulto aparece mediante alias o nombre e
inicial del primer apellido. Un menor sólo usa el modo de una
`PublicIdentityAuthorization` efectiva; sin vínculo, confirmación, revisión,
conformidad 14–17, versión o vigencia produce `Participante`. La fecha de
nacimiento y la evidencia privada no forman parte de esa proyección. Los
contratos autenticados y la administración Blade conservan su información.

Los flags `PUBLIC_IDENTITY_AUTHORIZATION_ENABLED` y
`PUBLIC_IDENTITY_NOTIFICATION_ENABLED` son `false` por defecto. La solicitud
opcional nace desde Escuela, se vincula manualmente con un jugador, usa tokens
hash de un solo uso y se revisa en
`/admin/public-identity-authorizations`. No existe proveedor productivo de
correo configurado. El contrato completo está en
[`docs/23-verifiable-minor-public-identity.md`](../docs/23-verifiable-minor-public-identity.md).

El layout administrativo carga únicamente `public/css/admin.css` y
`public/js/admin.js`. No depende de Google Fonts, Bunny Fonts, jsDelivr ni otro
CDN al renderizarse. El alcance y los riesgos residuales se documentan en
[`docs/21-privacy-hardening-and-public-identity.md`](../docs/21-privacy-hardening-and-public-identity.md).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
