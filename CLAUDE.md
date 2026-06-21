# CLAUDE.md — creando-web

Guía para trabajar en este repositorio con Claude Code.

## Qué hay en el repo

Este repositorio contiene **dos proyectos independientes**:

| Directorio / archivo | Descripción |
|---|---|
| `index.html` + `css/styles.css` | Web estática del estudio fotográfico **Fotocol2000** (legado, mínima, sin build). |
| `recordatorio-tareas/` | **App PHP 8.1+** de gestión de tareas con OAuth Gmail/Outlook, calendario y dashboard. Aquí vive casi todo el trabajo activo. |
| `.github/workflows/ci.yml` | CI (lint + tests + estilo) que solo se dispara con cambios en `recordatorio-tareas/**`. |

## Dónde trabajar

Casi siempre querrás estar en **`recordatorio-tareas/`**. Lee su guía antes de tocar nada:

```
recordatorio-tareas/CLAUDE.md
```

Comandos esenciales (todos se ejecutan **dentro de `recordatorio-tareas/`**):

```bash
composer install                  # instala dependencias (incluye phpunit, cs-fixer)
php -S localhost:8000 -t public   # servidor de desarrollo
make test                         # suite completa (unit + integración)
make lint                         # php -l de todos los .php
make up / make down               # entorno Docker (db + app + phpMyAdmin)
```

La web estática de la raíz (`index.html`) **no tiene build ni dependencias**.

## Rama de desarrollo

```
claude/amazing-wright-fuc1c1
```

Todo el trabajo nuevo va a esta rama. Para subir cambios:

```bash
git push -u origin claude/amazing-wright-fuc1c1
```

## Convenciones clave

- Código y comentarios en **español**.
- El CI y los comandos del subproyecto se ejecutan siempre desde **dentro de `recordatorio-tareas/`** (hay un `defaults.run.working-directory` en el workflow).
- `config.php` está en `.gitignore`; nunca se versiona. Copia `config.example.php` para empezar.
- Ver `recordatorio-tareas/CLAUDE.md` para convenciones de código, tests, OAuth y estilo.
