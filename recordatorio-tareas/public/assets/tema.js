/**
 * Tema claro/oscuro.
 *
 * Se carga en el <head> (sin defer) para aplicar el tema guardado ANTES de
 * pintar y evitar el parpadeo. Si no hay preferencia guardada, se respeta la
 * del sistema (prefers-color-scheme) automáticamente vía CSS.
 *
 * Un botón con [data-accion="tema"] alterna entre claro y oscuro y guarda la
 * elección en localStorage. Se usa delegación de eventos para que funcione
 * aunque el botón se cree después de este script.
 */
(() => {
    'use strict';

    const raiz = document.documentElement;

    // 1. Aplicar el tema guardado cuanto antes.
    const guardado = localStorage.getItem('tema');
    if (guardado === 'claro' || guardado === 'oscuro') {
        raiz.dataset.tema = guardado;
    }

    // 2. Alternar al pulsar el botón de tema.
    document.addEventListener('click', (ev) => {
        const boton = ev.target.closest('[data-accion="tema"]');
        if (!boton) {
            return;
        }
        const oscuroAhora = raiz.dataset.tema === 'oscuro'
            || (!raiz.dataset.tema
                && window.matchMedia('(prefers-color-scheme: dark)').matches);
        const nuevo = oscuroAhora ? 'claro' : 'oscuro';
        raiz.dataset.tema = nuevo;
        localStorage.setItem('tema', nuevo);
        actualizarIcono(boton, nuevo);
    });

    /** Cambia el icono del botón según el tema activo. */
    function actualizarIcono(boton, tema) {
        boton.textContent = tema === 'oscuro' ? '☀️' : '🌙';
    }
})();
