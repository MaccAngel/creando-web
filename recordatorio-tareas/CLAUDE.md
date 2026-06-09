# CLAUDE.md — Recordatorio de Tareas

Guía para trabajar en este proyecto con Claude Code. Léela antes de hacer
cambios para mantener el estilo y las decisiones de diseño existentes.

## Qué es

App web **local** (un solo usuario) en **PHP 8.1+**, sin framework. Gestiona
tareas pendientes, importa correos destacados de **Gmail/Outlook** como tareas
(OAuth 2.0) y crea su **evento de calendario**. Frontend en **JavaScript
vanilla** y **SCSS** compilado a CSS. Sin framework de PHP: estructura propia y
mínima.

> Nota: este proyecto vive en el subdirectorio `recordatorio-tareas/` del
> repositorio. La raíz del repo contiene además una web estática aparte
> (`index.html`, `css/`) que **no** forma parte de esta app.

## Comandos habituales

```bash
composer install                  # dependencias (incluye dev: phpunit, cs-fixer, phpcs)
php -S localhost:8000 -t public   # servidor de desarrollo  (o: make serve)

make test                         # toda la suite (necesita BD para integración)
make test-unit                    # solo unitarios (sin BD)
make test-integration DB_PASS=... # solo integración

make estilo                       # PHP CS Fixer (dry-run) + PHP_CodeSniffer
make cs-fix                       # aplica el formato automático
make lint                         # php -l de todos los .php

make up / make down               # entorno Docker (db + app + phpMyAdmin)
make seed DB_PASS=...             # carga sql/datos_ejemplo.sql
make key                          # genera una app_key (base64 de 32 bytes)
```

Ejecuta `make help` para la lista completa.

## Estructura

```
config.php            Config real (NO versionada; copia de config.example.php)
docker/               Dockerfile config + entrypoint (config por variables de entorno)
sql/                  esquema.sql y datos_ejemplo.sql (seeds)
lib/                  Lógica (sin estado de vista):
  db.php              Conexión PDO única (singleton).
  proveedores.php     config() + fábricas OAuth Google/Microsoft + scopes.
  cripto.php          cifrar()/descifrar() de tokens con libsodium.
  auth.php            PIN opcional, sesión y CSRF.
  correo.php          tokenValido() (refresco/reconexión) + lectura de correos + filtros.
  agenda.php          Crear/actualizar eventos de calendario.
  sync.php            sincronizarCuentas() incremental + reglas de prioridad.
  fechas.php          estadoVencimiento() (puro).
  estadisticas.php    resumirTareas() (puro) para el panel.
public/               Punto de entrada web (DocumentRoot):
  index.php           UI de tareas.        dashboard.php  Panel de estadísticas.
  login.php           PIN.                 filtros.php    Filtros y reglas.
  oauth_iniciar.php / oauth_callback.php   Flujo OAuth.
  sincronizar.php     Sync web/CLI (cron). api.php        API JSON del frontend.
  assets/             style.scss -> style.css, app.js
tests/                PHPUnit. Unitarios (sin BD) + tests/Integration (con BD).
```

## Convenciones (respétalas)

- **Idioma español** en nombres de funciones, variables, comentarios y UI.
- **SQL siempre con sentencias preparadas** (PDO). Nunca concatenar entrada de
  usuario en las consultas.
- **Escapar toda salida HTML** con la función `e()` (htmlspecialchars).
- **Tokens cifrados** en la BD (`cripto.php`); nunca guardarlos en claro.
- `config.php` va **fuera de `public/`** y está en `.gitignore`. La config se
  lee siempre con `config()`; en tests/Docker se inyecta con la variable de
  entorno `RECORDATORIO_CONFIG`.
- **Código simple y mantenible**: sin abstracciones innecesarias.
- Al añadir lógica con cálculo, **extrae una función pura** (como
  `estadoVencimiento`, `prioridadSegunReglas`, `resumirTareas`) y cúbrela con un
  test unitario sin BD. Las funciones que tocan la BD delegan en esas puras.
- Si tocas el SCSS, **recompila** el CSS: `make css` (o `sass`). No edites
  `style.css` a mano.
- Tras los cambios, deja verde: `make lint`, `make estilo` y `make test`.

## Tests

- **Unitarios** (`tests/*.php`): lógica pura, sin BD. Siempre deben pasar.
- **Integración** (`tests/Integration/`): contra MySQL/MariaDB real. Se **omiten
  con elegancia** (`markTestSkipped`) si no hay BD; no las conviertas en fallos.
- Conexión configurable por entorno: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
  (ver `tests/fixtures/config.test.php`).

## Estilo de código

- **PHP CS Fixer** (`.php-cs-fixer.dist.php`) es el formateador (PSR-12 + extras);
  aplica con `make cs-fix`. Cubre `lib/`, `tests/` y `public/`.
- **PHP_CodeSniffer** (`phpcs.xml.dist`) es el guardarraíl PSR-12 sobre `lib/` y
  `tests/`. Los avisos de longitud de línea no rompen el build.
- Las vistas de `public/` mezclan HTML/PHP por diseño (no se les aplica la regla
  PSR1 de "sin efectos secundarios").

## CI

`.github/workflows/ci.yml` (en la raíz del repo) ejecuta en cada push/PR que
toque `recordatorio-tareas/`: **lint + unitarios**, **estilo** (cs-fixer + phpcs)
y **integración** (servicio MariaDB).

## OAuth (recordatorios)

- Redirect URI: `{base_url}/oauth_callback.php?p=google|microsoft`.
- Google: `access_type=offline` + `prompt=consent` para recibir refresh_token.
- Microsoft: endpoint **v2.0** (provider thenetworg/oauth2-azure).
- Validar siempre el parámetro `state` en el callback.
