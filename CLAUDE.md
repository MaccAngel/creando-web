# CLAUDE.md — Fotocol2000

Web del estudio fotográfico **Fotocol2000**.

## Stack

- **Frontend**: Vite + Tailwind CSS v4 + Vanilla JS
- **Backend API**: PHP 8.1+ (contacto, admin)
- **Base de datos**: SQLite (`data/fotocol.db`, auto-creado)

## Estructura

```
index.html          Entrada Vite (sitio completo SPA-like)
src/
  main.css          Tailwind v4 + componentes personalizados
  main.js           Interacciones: nav, galería, lightbox, formulario
api/
  contacto.php      Guarda mensajes del formulario en SQLite
admin/              Panel de administración PHP (pendiente)
data/               SQLite DB (gitignoreado)
public/uploads/     Fotos subidas (gitignoreado)
sql/                Esquemas de BD
```

## Comandos

```bash
npm install          # instalar dependencias
npm run dev          # Vite dev server → http://localhost:5173
npm run build        # compilar a dist/
php -S localhost:8000 -t .   # PHP server (admin + api)
make help            # lista completa
```

## Convenciones

- Tailwind v4: colores personalizados en `@theme {}` → `bg-gold`, `text-dark`, etc.
- Clases de componentes en `src/main.css` (`.filtro`, `.galeria-item`, `.servicio-card`, etc.).
- Sin comentarios salvo WHY no obvio.
- `data/fotocol.db` y `public/uploads/` están en `.gitignore`.

## Rama de desarrollo

```
claude/amazing-wright-fuc1c1
```
