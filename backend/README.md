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

Laravel incluye la base técnica de `ContactRequest`: persistencia local, POST
público protegido, configuración pública allowlisted, bandeja Blade y
notificación opcional posterior al guardado. Se mantiene desactivada por
defecto mediante `CONTACT_FORM_ENABLED=false`; el destinatario y el flag de
notificación nunca se exponen en la API. Consulta el contrato y los gates en
[`docs/17-club-technical-preparation-and-contact.md`](../docs/17-club-technical-preparation-and-contact.md)
y su consumo público en
[`docs/18-club-public-facades.md`](../docs/18-club-public-facades.md). El entorno
E2E habilita el formulario sólo dentro de su base temporal protegida; el default
de cualquier otro entorno continúa siendo `false`.

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
