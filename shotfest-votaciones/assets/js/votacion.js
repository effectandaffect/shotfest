/* global sfAjax */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const contenedor = document.querySelector('.sf-botones-voto');
        if (!contenedor) return;

        const spotId   = contenedor.dataset.spotId;
        const feedback = document.querySelector('.sf-voto-feedback');

        contenedor.querySelectorAll('.sf-btn[data-valor]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const valor = btn.dataset.valor;

                btn.disabled = true;
                contenedor.querySelectorAll('.sf-btn').forEach(b => b.disabled = true);

                const body = new URLSearchParams({
                    action:   'sf_emitir_voto',
                    nonce:    sfAjax.nonce,
                    spot_id:  spotId,
                    valor:    valor,
                });

                fetch(sfAjax.ajaxurl, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    body.toString(),
                })
                    .then(r => r.json())
                    .then(function (data) {
                        if (data.success) {
                            contenedor.classList.add('sf-voto-confirmado');
                            contenedor.innerHTML = '';
                            if (feedback) {
                                feedback.textContent = data.data.mensaje;
                                feedback.className = 'sf-voto-feedback sf-ok';
                            }
                        } else {
                            if (feedback) {
                                feedback.textContent = data.data.mensaje || 'Error al registrar el voto.';
                                feedback.className = 'sf-voto-feedback sf-error';
                            }
                            contenedor.querySelectorAll('.sf-btn').forEach(b => b.disabled = false);
                        }
                    })
                    .catch(function () {
                        if (feedback) {
                            feedback.textContent = 'Error de conexión. Por favor, inténtalo de nuevo.';
                            feedback.className = 'sf-voto-feedback sf-error';
                        }
                        contenedor.querySelectorAll('.sf-btn').forEach(b => b.disabled = false);
                    });
            });
        });
    });
}());
