# Recordatorio de Tareas

Aplicación web **local** (un solo usuario) que gestiona tus tareas pendientes y
se conecta a tu correo (**Gmail** y **Outlook**) para:

- Crear tareas automáticamente a partir de correos **destacados / prioritarios**.
- Escribir cada tarea como **evento en tu calendario** (Google / Outlook).
- Avisarte de **vencimientos** (vencidas, hoy, pronto).

Stack: **PHP 8.1+** (PDO + MySQL/MariaDB, consultas preparadas), **JavaScript
vanilla** y **SCSS**. Sin frameworks de PHP.

---

## Pasos de instalación

### 1. Instalar dependencias

```bash
composer install
```

### 2. Crear la configuración

```bash
cp config.example.php config.php
```

Edita `config.php` con los datos de tu base de datos y, más adelante, las
credenciales OAuth.

### 3. Generar la clave de cifrado (`app_key`)

```bash
php -r "echo base64_encode(random_bytes(32));"
```

Copia el resultado en `config.php` → `'app_key'`.

### 4. Crear la base de datos

```bash
mysql -u root -p < sql/esquema.sql
```

Esto crea la base de datos `recordatorio_tareas` y sus tablas.

### 5. Registrar las apps OAuth

**Google Cloud Console**

1. Crea un proyecto y **activa Gmail API** y **Google Calendar API**.
2. Configura la pantalla de consentimiento OAuth.
3. Crea una credencial **ID de cliente OAuth → Aplicación web**.
4. Añade como *URI de redirección autorizado*:
   `http://localhost:8000/oauth_callback.php?p=google`
5. Copia `client_id` y `client_secret` en `config.php` → `'google'`.

**Microsoft Entra (Azure)**

1. **Registros de aplicaciones → Nuevo registro** (cuentas según `tenant`).
2. En *Autenticación*, añade una plataforma **Web** con la URI:
   `http://localhost:8000/oauth_callback.php?p=microsoft`
3. En *Permisos de API* añade permisos **delegados** de Microsoft Graph:
   `User.Read`, `Mail.Read`, `offline_access`, `Calendars.ReadWrite`.
4. En *Certificados y secretos*, crea un **secreto de cliente**.
5. Copia `client_id` (ID de aplicación) y `client_secret` en
   `config.php` → `'microsoft'`.

### 6. (Opcional) Compilar el SCSS

El CSS ya viene compilado en `public/assets/style.css`. Si modificas el SCSS:

```bash
sass public/assets/style.scss public/assets/style.css
```

### 7. Arrancar la aplicación

```bash
php -S localhost:8000 -t public
```

Abre <http://localhost:8000> y conecta tus cuentas desde la cabecera.

---

## Arranque rápido con Docker (recomendado para probar)

Si tienes Docker, no necesitas instalar PHP ni MySQL a mano. Levanta todo el
entorno (base de datos con datos de ejemplo precargados, app y phpMyAdmin):

```bash
docker compose up --build
```

- App: <http://localhost:8000>
- phpMyAdmin: <http://localhost:8080> (usuario `root`, contraseña `root`)

La base de datos se inicializa automáticamente con `sql/esquema.sql` y
`sql/datos_ejemplo.sql`, así que verás tareas de ejemplo nada más entrar.
Para conectar el correo real, rellena las variables `GOOGLE_*` / `MS_*` en
`docker-compose.yml`.

Para detener y borrar los datos:

```bash
docker compose down -v
```

---

## Datos de ejemplo (seeds)

Para poblar la base de datos con tareas, filtros y reglas de prueba (sin Docker):

```bash
mysql -u root -p recordatorio_tareas < sql/datos_ejemplo.sql
```

Incluye tareas con distintos estados de vencimiento (vencida, hoy, pronto),
una tarea importada de correo de ejemplo, y filtros y reglas de muestra.

> ⚠️ El seed **vacía** las tablas `tareas`, `filtros` y `reglas` antes de
> insertar, para que sea reproducible. No lo ejecutes sobre datos reales.

---

## Tests

El proyecto incluye una batería de tests unitarios (PHPUnit) sobre la lógica
pura: cifrado de tokens, filtros de correo, reglas de prioridad y estados de
vencimiento. No necesitan base de datos.

```bash
composer install        # instala también phpunit (require-dev)
composer test           # o: vendor/bin/phpunit
```

---

## Sincronización automática por cron

Además del auto-sync del navegador (`auto_sync_min`), puedes programar:

```cron
*/5 * * * * php /ruta/a/recordatorio-tareas/public/sincronizar.php
```

En modo CLI no se pide PIN.

---

## PIN opcional

Por defecto no hay PIN (uso local). Para activarlo, genera un hash:

```bash
php -r "echo password_hash('TU_PIN', PASSWORD_DEFAULT);"
```

y guárdalo en `config.php` → `'pin_hash'`.

---

## Seguridad

- Tokens OAuth **cifrados** en la base de datos con libsodium.
- Consultas **siempre preparadas** (PDO).
- **CSRF** en API (cabecera `X-CSRF`) y formularios.
- Salida HTML **escapada**.
- `config.php` está fuera de `public/` y en `.gitignore`.
