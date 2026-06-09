/**
 * Auto-refresco del panel.
 *
 * Cada N minutos (data-refresh, reutiliza auto_sync_min) consulta
 * api.php?accion=estadisticas y compara la "firma" (total-completadas) con la
 * actual. Si ha cambiado, recarga la página para mostrar los datos al día.
 * Es deliberadamente simple: reaprovecha el render del servidor en vez de
 * redibujar el DOM a mano.
 */

(() => {
    'use strict';

    const cuerpo = document.body;
    const REFRESH_MIN = parseInt(cuerpo.dataset.refresh || '0', 10);
    const FIRMA_INICIAL = cuerpo.dataset.firma || '';

    if (REFRESH_MIN <= 0) {
        return; // auto-refresco desactivado
    }

    async function comprobar() {
        try {
            const resp = await fetch('api.php?accion=estadisticas', {
                headers: { Accept: 'application/json' },
            });
            if (resp.status === 401) {
                window.location.href = 'login.php';
                return;
            }
            const datos = await resp.json();
            if (datos.ok && datos.firma && datos.firma !== FIRMA_INICIAL) {
                window.location.reload();
            }
        } catch (e) {
            // Silencioso: un fallo de red puntual no debe molestar al usuario.
        }
    }

    setInterval(comprobar, REFRESH_MIN * 60 * 1000);
})();
