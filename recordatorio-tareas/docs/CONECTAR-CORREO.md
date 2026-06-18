# Conectar tu correo (Gmail y Outlook)

Esta guía te lleva paso a paso para que la app pueda **leer tus correos
destacados** y **crear eventos en tu calendario**. Es la única parte "tediosa":
registrar la app en Google y/o Microsoft y pegar dos datos en `config.php`.

> Dato clave que comparten ambos: la **URI de redirección** que debes registrar
> es exactamente:
>
> ```
> http://localhost:8000/oauth_callback.php
> ```
>
> (Sin `?` ni nada detrás. Es la misma para Google y para Microsoft.)

Después de registrar cada proveedor, editarás `config.php` (en la carpeta
`recordatorio-tareas\`) y rellenarás `client_id` y `client_secret`. Guarda el
archivo y **reinicia el servidor** (cierra la ventana y vuelve a abrir
`iniciar.bat`, o `Ctrl+C` y de nuevo `php -S ...`).

---

## A) Gmail (Google Cloud Console)

1. Entra en <https://console.cloud.google.com/> con tu cuenta de Google.
2. Arriba, crea un **proyecto** nuevo (por ejemplo "Recordatorio de Tareas") y
   selecciónalo.
3. **Habilita las APIs** (menú "APIs y servicios" → "Biblioteca"); busca y
   pulsa **Habilitar** en:
   - **Gmail API**
   - **Google Calendar API**
4. Ve a **APIs y servicios → Pantalla de consentimiento de OAuth**:
   - Tipo de usuario: **Externo** → Crear.
   - Rellena nombre de la app, tu correo de asistencia y de contacto.
   - En **Usuarios de prueba** añade **tu propia dirección de Gmail**
     (⚠️ imprescindible: si no te añades como usuario de prueba, al conectar te
     dará "acceso denegado").
5. Ve a **APIs y servicios → Credenciales → Crear credenciales → ID de cliente
   de OAuth**:
   - Tipo de aplicación: **Aplicación web**.
   - En **URI de redireccionamiento autorizados** añade:
     `http://localhost:8000/oauth_callback.php`
   - Crear. Te mostrará el **ID de cliente** y el **secreto de cliente**.
6. En `config.php`, sección `'google'`:
   ```php
   'google' => [
       'client_id'     => 'PEGA_AQUI_EL_ID_DE_CLIENTE',
       'client_secret' => 'PEGA_AQUI_EL_SECRETO',
   ],
   ```
7. Guarda, reinicia el servidor y pulsa **＋ Gmail** en la app.

> Nota: con la pantalla de consentimiento en modo "Prueba", Google muestra un
> aviso de "app no verificada"; pulsa **Configuración avanzada → Ir a (app)**.
> Es normal para uso personal.

---

## B) Outlook (Microsoft Entra / Azure)

1. Entra en <https://entra.microsoft.com/> (o el "Portal de Azure") con tu
   cuenta Microsoft.
2. Busca **App registrations** (Registros de aplicaciones) → **New
   registration** (Nuevo registro):
   - **Name**: "Recordatorio de Tareas".
   - **Supported account types**: elige
     **"Accounts in any organizational directory and personal Microsoft
     accounts"** (cuentas de cualquier directorio y **cuentas personales**).
     Esto equivale a `tenant = common`, necesario para Outlook.com.
   - **Redirect URI**: plataforma **Web** y la URL:
     `http://localhost:8000/oauth_callback.php`
   - Register.
3. Copia el **Application (client) ID** (es un GUID, p. ej.
   `4b2c9a1e-...`). Ese es tu `client_id`.
4. **Certificates & secrets** → **New client secret**:
   - Descripción y caducidad → Add.
   - ⚠️ Copia el **Value** (Valor) del secreto **ahora** (no el "Secret ID"):
     solo se muestra una vez. Ese es tu `client_secret`.
5. **API permissions** → **Add a permission** → **Microsoft Graph** →
   **Delegated permissions**, y añade:
   - `User.Read`
   - `Mail.Read`
   - `offline_access`
   - `Calendars.ReadWrite`
   (No hace falta "consentimiento de administrador" para una cuenta personal.)
6. En `config.php`, sección `'microsoft'` (deja `tenant` en `'common'`):
   ```php
   'microsoft' => [
       'client_id'     => 'EL_APPLICATION_CLIENT_ID',
       'client_secret' => 'EL_VALUE_DEL_SECRETO',
       'tenant'        => 'common',
   ],
   ```
7. Guarda, reinicia el servidor y pulsa **＋ Outlook** en la app.

---

## Qué importa la app

- **Gmail**: correos **destacados** (con estrella), excluyendo Promociones.
- **Outlook**: correos de la **Bandeja de entrada** marcados y "Prioritarios".

Cada correo nuevo se convierte en tarea (al pulsar **🔄 Sincronizar** o
automáticamente) y, si la agenda está activa, crea su evento de calendario.

## Problemas frecuentes

- **"Application identifier is expected to be a GUID"** (Microsoft): el
  `client_id` sigue siendo el de ejemplo o está mal pegado. Pega el GUID real.
- **"redirect_uri_mismatch"**: la URI registrada no coincide. Debe ser
  exactamente `http://localhost:8000/oauth_callback.php` (sin barra final extra,
  sin `?`).
- **Google "acceso denegado / app no verificada"**: añade tu correo en
  **Usuarios de prueba** y usa "Configuración avanzada → Ir a la app".
- **No aparece refresh_token / se desconecta**: en Google la app ya pide
  `access_type=offline` y `prompt=consent`; reconecta la cuenta si te lo pide.
- Si `base_url` no es `http://localhost:8000`, ajusta también la URI de
  redirección registrada para que coincida.
