/**
 * Lógica del frontend (JavaScript vanilla).
 *
 * Gestiona: crear/editar tarea (modal), completar, borrar, vincular correo
 * (modal) y auto-sincronización periódica contra api.php.
 *
 * El token CSRF se lee de <body data-csrf> y se envía en la cabecera X-CSRF.
 */

(() => {
    'use strict';

    const cuerpo = document.body;
    const CSRF = cuerpo.dataset.csrf || '';
    const AUTO_SYNC_MIN = parseInt(cuerpo.dataset.autoSync || '0', 10);

    // -------------------------------------------------------------------
    // Notificaciones (toasts) — reemplazan a alert()
    // -------------------------------------------------------------------
    function toast(mensaje, tipo = 'ok') {
        let cont = document.querySelector('.toast-contenedor');
        if (!cont) {
            cont = document.createElement('div');
            cont.className = 'toast-contenedor';
            document.body.appendChild(cont);
        }
        const t = document.createElement('div');
        t.className = 'toast' + (tipo === 'error' ? ' toast-error' : '');
        t.textContent = mensaje;
        cont.appendChild(t);

        // Auto-cierre con animación de salida.
        setTimeout(() => {
            t.classList.add('saliendo');
            t.addEventListener('animationend', () => t.remove(), { once: true });
        }, 3200);
    }

    /** Llamada a la API JSON. Devuelve el objeto de respuesta. */
    async function api(accion, datos = {}) {
        const resp = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF': CSRF,
            },
            body: JSON.stringify({ accion, ...datos }),
        });
        if (resp.status === 401) {
            // Sesión caducada: volver al login.
            window.location.href = 'login.php';
            return { ok: false };
        }
        return resp.json();
    }

    // -------------------------------------------------------------------
    // Utilidades de modales
    // -------------------------------------------------------------------
    const modalTarea = document.getElementById('modal-tarea');
    const modalVincular = document.getElementById('modal-vincular');
    const formTarea = document.getElementById('form-tarea');

    function abrir(modal) { modal.classList.remove('oculto'); }
    function cerrar(modal) { modal.classList.add('oculto'); }

    document.querySelectorAll('.cerrar-modal').forEach((btn) => {
        btn.addEventListener('click', () => {
            cerrar(modalTarea);
            cerrar(modalVincular);
        });
    });

    // Cerrar al hacer clic en el fondo oscuro.
    [modalTarea, modalVincular].forEach((m) => {
        m.addEventListener('click', (ev) => {
            if (ev.target === m) cerrar(m);
        });
    });

    // -------------------------------------------------------------------
    // Crear / editar tarea
    // -------------------------------------------------------------------
    const tituloModal = document.getElementById('modal-tarea-titulo');

    document.getElementById('btn-nueva').addEventListener('click', () => {
        formTarea.reset();
        formTarea.id.value = '';
        tituloModal.textContent = 'Nueva tarea';
        abrir(modalTarea);
        formTarea.titulo.focus();
    });

    // Botones "editar" de cada tarea.
    document.querySelectorAll('.btn-editar').forEach((btn) => {
        btn.addEventListener('click', () => {
            const li = btn.closest('.tarea');
            formTarea.id.value = li.dataset.id;
            formTarea.titulo.value = btn.dataset.titulo || '';
            formTarea.notas.value = btn.dataset.notas || '';
            formTarea.prioridad.value = btn.dataset.prioridad || 'media';
            formTarea.fecha_limite.value = btn.dataset.fecha || '';
            tituloModal.textContent = 'Editar tarea';
            abrir(modalTarea);
            formTarea.titulo.focus();
        });
    });

    formTarea.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const datos = {
            titulo: formTarea.titulo.value.trim(),
            notas: formTarea.notas.value.trim(),
            prioridad: formTarea.prioridad.value,
            fecha_limite: formTarea.fecha_limite.value,
        };
        const id = formTarea.id.value;
        const accion = id ? 'editar' : 'crear';
        if (id) datos.id = parseInt(id, 10);

        const r = await api(accion, datos);
        if (r.ok) {
            window.location.reload();
        } else {
            toast('Error: ' + (r.error || 'desconocido'), 'error');
        }
    });

    // -------------------------------------------------------------------
    // Completar / borrar
    // -------------------------------------------------------------------
    document.querySelectorAll('.check-completar').forEach((chk) => {
        chk.addEventListener('change', async () => {
            const li = chk.closest('.tarea');
            const r = await api('completar', { id: parseInt(li.dataset.id, 10) });
            if (r.ok) {
                li.classList.toggle('completada', chk.checked);
                toast(chk.checked ? 'Tarea completada ✓' : 'Tarea reabierta');
            }
        });
    });

    document.querySelectorAll('.btn-borrar').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Borrar esta tarea?')) return;
            const li = btn.closest('.tarea');
            const r = await api('borrar', { id: parseInt(li.dataset.id, 10) });
            if (r.ok) {
                li.remove();
                toast('Tarea borrada');
            }
        });
    });

    // -------------------------------------------------------------------
    // Vincular correo
    // -------------------------------------------------------------------
    let tareaVincularId = null;
    const listaCorreos = document.getElementById('lista-correos');

    document.querySelectorAll('.btn-vincular').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const li = btn.closest('.tarea');
            tareaVincularId = parseInt(li.dataset.id, 10);
            listaCorreos.innerHTML = '<p class="vacio">Cargando correos…</p>';
            abrir(modalVincular);

            const r = await api('correos');
            if (!r.ok) {
                listaCorreos.innerHTML = '<p class="vacio">No se pudieron cargar los correos.</p>';
                return;
            }
            pintarCorreos(r.correos || []);
        });
    });

    function pintarCorreos(correos) {
        if (!correos.length) {
            listaCorreos.innerHTML = '<p class="vacio">No hay correos destacados disponibles.</p>';
            return;
        }
        listaCorreos.innerHTML = '';
        correos.forEach((c) => {
            const div = document.createElement('div');
            div.className = 'correo-item';
            div.innerHTML = `
                <div class="correo-info">
                    <strong></strong>
                    <small></small>
                </div>
                <button class="btn btn-primario btn-elegir">Vincular</button>`;
            // Asignar texto de forma segura (evita inyección HTML).
            div.querySelector('strong').textContent = c.asunto || '(sin asunto)';
            div.querySelector('small').textContent = `${c.remitente} · ${c.cuenta_email}`;
            div.querySelector('.btn-elegir').addEventListener('click', () => vincular(c));
            listaCorreos.appendChild(div);
        });
    }

    async function vincular(c) {
        const r = await api('vincular', {
            id: tareaVincularId,
            cuenta_id: c.cuenta_id,
            email_message_id: c.id,
            email_asunto: c.asunto,
            email_remitente: c.remitente,
            email_enlace: c.enlace,
        });
        if (r.ok) {
            window.location.reload();
        } else {
            toast('Error al vincular: ' + (r.error || 'desconocido'), 'error');
        }
    }

    // -------------------------------------------------------------------
    // Sincronización (manual y automática)
    // -------------------------------------------------------------------
    const btnSync = document.getElementById('btn-sincronizar');

    async function sincronizar(manual = false) {
        if (manual) btnSync.textContent = '⏳ Sincronizando…';
        const r = await api('sincronizar');
        if (manual) btnSync.textContent = '🔄 Sincronizar';

        if (r.ok && r.nuevas > 0) {
            // Hay tareas nuevas: recargar para mostrarlas.
            window.location.reload();
        } else if (manual) {
            btnSync.textContent = r.ok ? '✓ Sin novedades' : '✗ Error';
            setTimeout(() => { btnSync.textContent = '🔄 Sincronizar'; }, 2000);
        }
    }

    btnSync.addEventListener('click', () => sincronizar(true));

    // Auto-sincronización periódica si está configurada.
    if (AUTO_SYNC_MIN > 0) {
        setInterval(() => sincronizar(false), AUTO_SYNC_MIN * 60 * 1000);
    }
})();
