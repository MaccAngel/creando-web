<?php
/**
 * Lectura de correos "para acción", refresco de tokens y filtros.
 *
 * Funciones principales:
 * - tokenValido($cuenta)         Devuelve un access_token vigente (renovándolo si hace falta).
 * - leerCorreos($cuenta, $omitir) Lista correos destacados/prioritarios de una cuenta.
 * - filtrosActivos()             Filtros de config + tabla `filtros`.
 * - esNoDeseado($correo, $filtros) ¿Debe descartarse este correo?
 *
 * Las llamadas a las APIs (Gmail / Microsoft Graph) se hacen con cURL para
 * mantener las dependencias al mínimo.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cripto.php';
require_once __DIR__ . '/proveedores.php';

use League\OAuth2\Client\Token\AccessToken;

/**
 * Pequeño ayudante para peticiones HTTP con cURL.
 *
 * @param array $cabeceras Lista de cabeceras "Clave: valor".
 * @return array{codigo:int, cuerpo:string}
 */
function httpPeticion(string $metodo, string $url, array $cabeceras = [], ?string $cuerpo = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
    }

    $respuesta = curl_exec($ch);
    if ($respuesta === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Error de red: ' . $err);
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['codigo' => $codigo, 'cuerpo' => (string) $respuesta];
}

/** Atajo: GET autenticado con Bearer que devuelve JSON decodificado. */
function apiGet(string $token, string $url): array
{
    $r = httpPeticion('GET', $url, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ]);
    if ($r['codigo'] < 200 || $r['codigo'] >= 300) {
        throw new RuntimeException("La API respondió {$r['codigo']}: {$r['cuerpo']}");
    }
    return json_decode($r['cuerpo'], true) ?? [];
}

/**
 * Garantiza un access_token vigente para la cuenta.
 *
 * Descifra el access_token. Si caduca en menos de 60s y hay refresh_token,
 * lo renueva mediante el proveedor OAuth, cifra y guarda los nuevos tokens.
 * Si el refresco falla (token revocado), marca requiere_reconexion=1 y lanza.
 *
 * @param array $cuenta Fila de la tabla `cuentas`.
 * @return string access_token en claro y vigente.
 */
function tokenValido(array $cuenta): string
{
    $access = descifrar($cuenta['access_token']);
    $expira = $cuenta['expira_en'] ? strtotime($cuenta['expira_en']) : 0;

    // Si todavía es válido (más de 60s de margen), úsalo tal cual.
    if ($expira === 0 || $expira - time() > 60) {
        return $access;
    }

    // Necesita renovarse: ¿tenemos refresh_token?
    if (empty($cuenta['refresh_token'])) {
        marcarReconexion((int) $cuenta['id']);
        throw new RuntimeException('El token ha caducado y no hay refresh_token. Reconecta la cuenta.');
    }

    $refresh = descifrar($cuenta['refresh_token']);

    try {
        $proveedor = proveedorPara($cuenta['proveedor']);
        $nuevo = $proveedor->getAccessToken('refresh_token', [
            'refresh_token' => $refresh,
            'scope'         => implode(' ', scopesPara($cuenta['proveedor'])),
        ]);
    } catch (\Throwable $e) {
        // Token revocado o credenciales inválidas: requiere reconexión.
        marcarReconexion((int) $cuenta['id']);
        throw new RuntimeException('No se pudo renovar el token. Reconecta la cuenta. (' . $e->getMessage() . ')');
    }

    // Guardar el access_token (y refresh_token, si el proveedor envía uno nuevo).
    guardarTokensRenovados((int) $cuenta['id'], $nuevo, $refresh);

    return $nuevo->getToken();
}

/** Marca una cuenta como "requiere reconexión". */
function marcarReconexion(int $cuentaId): void
{
    $st = db()->prepare('UPDATE cuentas SET requiere_reconexion = 1 WHERE id = ?');
    $st->execute([$cuentaId]);
}

/**
 * Persiste los tokens renovados (cifrados) y limpia requiere_reconexion.
 */
function guardarTokensRenovados(int $cuentaId, AccessToken $nuevo, string $refreshAnterior): void
{
    $accessCifrado  = cifrar($nuevo->getToken());
    $refreshNuevo   = $nuevo->getRefreshToken() ?: $refreshAnterior;
    $refreshCifrado = cifrar($refreshNuevo);
    $expira         = $nuevo->getExpires()
        ? date('Y-m-d H:i:s', $nuevo->getExpires())
        : null;

    $st = db()->prepare(
        'UPDATE cuentas
            SET access_token = ?, refresh_token = ?, expira_en = ?, requiere_reconexion = 0
          WHERE id = ?'
    );
    $st->execute([$accessCifrado, $refreshCifrado, $expira, $cuentaId]);
}

/**
 * Lee los correos "para acción" de una cuenta.
 *
 * @param array     $cuenta Fila de `cuentas`.
 * @param string[]  $omitir IDs de mensaje ya importados (sincronización incremental).
 * @return array<int, array{id:string, asunto:string, remitente:string, enlace:string}>
 */
function leerCorreos(array $cuenta, array $omitir = []): array
{
    $token = tokenValido($cuenta);
    $omitir = array_flip($omitir); // búsqueda O(1)

    return $cuenta['proveedor'] === 'google'
        ? leerCorreosGoogle($token, $omitir)
        : leerCorreosMicrosoft($token, $omitir);
}

/**
 * Gmail: mensajes destacados, excluyendo promociones (spam excluido por defecto).
 */
function leerCorreosGoogle(string $token, array $omitir): array
{
    $base = 'https://gmail.googleapis.com/gmail/v1/users/me/messages';
    $q = urlencode('is:starred -category:promotions');
    $lista = apiGet($token, "$base?q=$q&maxResults=25");

    $correos = [];
    foreach ($lista['messages'] ?? [] as $msg) {
        $id = $msg['id'];

        // Incremental: si ya está importado, ni siquiera pedimos sus metadatos.
        if (isset($omitir[$id])) {
            continue;
        }

        $detalle = apiGet(
            $token,
            "$base/$id?format=metadata&metadataHeaders=Subject&metadataHeaders=From"
        );

        $asunto = $remitente = '';
        foreach ($detalle['payload']['headers'] ?? [] as $h) {
            if (strcasecmp($h['name'], 'Subject') === 0) {
                $asunto = $h['value'];
            } elseif (strcasecmp($h['name'], 'From') === 0) {
                $remitente = $h['value'];
            }
        }

        $correos[] = [
            'id'        => $id,
            'asunto'    => $asunto,
            'remitente' => $remitente,
            'enlace'    => "https://mail.google.com/mail/u/0/#all/$id",
        ];
    }

    return $correos;
}

/**
 * Outlook (Graph): solo Bandeja de entrada, marcados y "Prioritario".
 */
function leerCorreosMicrosoft(string $token, array $omitir): array
{
    $filtro = urlencode("flag/flagStatus eq 'flagged' and inferenceClassification eq 'focused'");
    $select = 'subject,from,webLink';
    $url = "https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages"
         . "?\$filter=$filtro&\$select=$select&\$top=25";

    $datos = apiGet($token, $url);

    $correos = [];
    foreach ($datos['value'] ?? [] as $msg) {
        $id = $msg['id'];
        if (isset($omitir[$id])) {
            continue;
        }

        $correos[] = [
            'id'        => $id,
            'asunto'    => $msg['subject'] ?? '',
            'remitente' => $msg['from']['emailAddress']['address'] ?? '',
            'enlace'    => $msg['webLink'] ?? '',
        ];
    }

    return $correos;
}

/**
 * Devuelve los filtros activos combinando config.php y la tabla `filtros`.
 * Si la tabla no existe todavía, usa solo los de config (sin romper).
 *
 * @return array{remitentes: string[], palabras: string[]}
 */
function filtrosActivos(): array
{
    $cfg = config()['filtros'] ?? [];
    $remitentes = $cfg['remitentes_bloqueados'] ?? [];
    $palabras   = $cfg['palabras_bloqueadas'] ?? [];

    try {
        $filas = db()->query('SELECT tipo, valor FROM filtros')->fetchAll();
        foreach ($filas as $f) {
            if ($f['tipo'] === 'remitente') {
                $remitentes[] = $f['valor'];
            } else {
                $palabras[] = $f['valor'];
            }
        }
    } catch (\Throwable $e) {
        // La tabla puede no existir aún: ignoramos y usamos solo config.
    }

    return [
        'remitentes' => array_values(array_unique($remitentes)),
        'palabras'   => array_values(array_unique($palabras)),
    ];
}

/**
 * ¿El correo es no deseado según los filtros?
 *
 * Coincidencia parcial e insensible a mayúsculas: descarta si el remitente
 * contiene un remitente bloqueado, o si remitente/asunto contienen una
 * palabra bloqueada.
 *
 * @param array $correo  ['asunto'=>..., 'remitente'=>...]
 * @param array $filtros Resultado de filtrosActivos().
 */
function esNoDeseado(array $correo, array $filtros): bool
{
    $remitente = mb_strtolower($correo['remitente'] ?? '');
    $asunto    = mb_strtolower($correo['asunto'] ?? '');

    foreach ($filtros['remitentes'] as $r) {
        if ($r !== '' && str_contains($remitente, mb_strtolower($r))) {
            return true;
        }
    }
    foreach ($filtros['palabras'] as $p) {
        $p = mb_strtolower($p);
        if ($p !== '' && (str_contains($asunto, $p) || str_contains($remitente, $p))) {
            return true;
        }
    }

    return false;
}
