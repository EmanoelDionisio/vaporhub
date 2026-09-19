/**
 * Categorias — árvore hierárquica com drag-and-drop.
 *
 * @package VaporHubLoja
 */
(function () {
    'use strict';

    const CFG = window.paCategorias || {};
    const I18N = CFG.i18n || {};

    function requisitar(endpoint, corpo) {
        if (!CFG.restUrl || !CFG.nonce) {
            return Promise.reject(new Error(I18N.erroGenerico || 'Configuração indisponível.'));
        }

        const url = CFG.restUrl + endpoint.replace(/^\//, '');
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': CFG.nonce,
            },
            credentials: 'same-origin',
            body: JSON.stringify(corpo),
        }).then(function (resp) {
            return resp.json().then(function (json) {
                if (!resp.ok) {
                    const msg = (json && json.message) ? json.message : (I18N.erroGenerico || 'Erro');
                    throw new Error(msg);
                }
                return json;
            });
        });
    }

    function feedback(mensagem, tipo) {
        if (window.paLojaApp && window.paLojaApp.feedback) {
            window.paLojaApp.feedback(mensagem, tipo);
            return;
        }
        window.alert(mensagem);
    }

    function initArvoreCategorias() {
        const container = document.getElementById('vh-cat-arvore');
        if (!container || container.dataset.paCatInit) {
            return;
        }

        let dragged = null;
        let dropAlvo = null;
        let dropPosicao = '';
        let dragPermitido = false;

        function limparEstados() {
            container.querySelectorAll('.vh-cat-linha').forEach(function (linha) {
                linha.classList.remove(
                    'vh-cat-linha--dragging',
                    'vh-cat-linha--drop-before',
                    'vh-cat-linha--drop-after',
                    'vh-cat-linha--drop-inside',
                    'vh-cat-linha--drop-invalid'
                );
            });
        }

        function ehDescendente(possivelFilho, ancestralId) {
            let atual = possivelFilho;
            while (atual) {
                if (parseInt(atual.getAttribute('data-id'), 10) === ancestralId) {
                    return true;
                }
                const paiId = parseInt(atual.getAttribute('data-pai-id'), 10);
                if (!paiId) {
                    return false;
                }
                atual = container.querySelector('.vh-cat-linha[data-id="' + paiId + '"]');
            }
            return false;
        }

        function calcularDrop(linha, clientY) {
            const rect = linha.getBoundingClientRect();
            const relY = clientY - rect.top;
            const h = rect.height;

            if (relY < h * 0.25) {
                return 'before';
            }
            if (relY > h * 0.75) {
                return 'after';
            }
            return 'inside';
        }

        container.addEventListener('mousedown', function (e) {
            dragPermitido = !!e.target.closest('.vh-cat-drag-handle');
        });

        container.addEventListener('mouseup', function () {
            dragPermitido = false;
        });

        container.addEventListener('dragstart', function (e) {
            const linha = e.target.closest('.vh-cat-linha');
            if (!linha || !dragPermitido) {
                e.preventDefault();
                return;
            }

            dragged = linha;
            dragged.classList.add('vh-cat-linha--dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', dragged.getAttribute('data-id') || '');
        });

        container.addEventListener('dragend', function () {
            dragged = null;
            dropAlvo = null;
            dropPosicao = '';
            dragPermitido = false;
            limparEstados();
        });

        container.addEventListener('dragover', function (e) {
            if (!dragged) {
                return;
            }

            e.preventDefault();

            const linha = e.target.closest('.vh-cat-linha');
            if (!linha) {
                dropAlvo = null;
                dropPosicao = '';
                return;
            }

            const draggedId = parseInt(dragged.getAttribute('data-id'), 10);

            if (linha === dragged || ehDescendente(linha, draggedId)) {
                dropAlvo = null;
                dropPosicao = '';
                e.dataTransfer.dropEffect = 'none';

                limparEstados();
                dragged.classList.add('vh-cat-linha--dragging');
                if (linha !== dragged) {
                    linha.classList.add('vh-cat-linha--drop-invalid');
                }
                return;
            }

            const posicao = calcularDrop(linha, e.clientY);
            dropAlvo = linha;
            dropPosicao = posicao;
            e.dataTransfer.dropEffect = 'move';

            limparEstados();
            dragged.classList.add('vh-cat-linha--dragging');

            if ('inside' === posicao) {
                linha.classList.add('vh-cat-linha--drop-inside');
            } else if ('before' === posicao) {
                linha.classList.add('vh-cat-linha--drop-before');
            } else {
                linha.classList.add('vh-cat-linha--drop-after');
            }
        });

        container.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!dragged) {
                return;
            }

            const linha = e.target.closest('.vh-cat-linha');
            const id = parseInt(dragged.getAttribute('data-id'), 10);

            if (!linha || !id) {
                limparEstados();
                return;
            }

            const referenciaId = parseInt(linha.getAttribute('data-id'), 10);

            if (!referenciaId || id === referenciaId) {
                feedback(I18N.moveInvalidoMesma || I18N.erroGenerico, 'erro');
                limparEstados();
                return;
            }

            if (ehDescendente(linha, id)) {
                feedback(I18N.moveInvalidoDescendente || I18N.erroGenerico, 'erro');
                limparEstados();
                return;
            }

            const posicao = (dropAlvo === linha && dropPosicao) ? dropPosicao : calcularDrop(linha, e.clientY);

            container.classList.add('vh-cat-arvore--salvando');

            requisitar('categorias/' + id + '/mover', {
                referencia_id: referenciaId,
                posicao: posicao,
            })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    container.classList.remove('vh-cat-arvore--salvando');
                    feedback(err.message || I18N.erroGenerico, 'erro');
                });
        });

        container.querySelectorAll('.vh-cat-linha').forEach(function (linha) {
            linha.setAttribute('draggable', 'true');
        });

        container.dataset.paCatInit = '1';
    }

    function boot() {
        initArvoreCategorias();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.addEventListener('load', boot);
}());
