/**
 * Vapor Hub - Minha Loja — App administrativo (camada REST).
 *
 * Intercepta formulários `.vh-rest-form` e botões `.vh-rest-acao`, enviando as
 * operações para a API interna (vh-loja/v1) com o nonce do WordPress. Mantém o
 * gestor 100% dentro do app, sem recarregar telas nativas.
 *
 * @package VaporHubLoja
 */

(function () {
    'use strict';

    const APP = window.paLojaApp || {};
    const I18N = APP.i18n || {};

    /* ── Toast de feedback (sucesso / erro) ── */
    let toastTimer = null;

    function removerToast(toast) {
        if (!toast || !toast.parentNode) {
            return;
        }
        toast.classList.add('vh-toast--saindo');
        window.setTimeout(function () {
            toast.remove();
        }, 220);
    }

    function feedback(mensagem, tipo) {
        const stack = document.getElementById('vh-toast-stack');
        if (!stack) {
            window.alert(mensagem);
            return;
        }

        if (toastTimer) {
            window.clearTimeout(toastTimer);
            toastTimer = null;
        }

        stack.querySelectorAll('.vh-toast').forEach(removerToast);

        const ehErro = tipo === 'erro';
        const toast = document.createElement('div');
        toast.className = 'vh-toast vh-toast--' + (ehErro ? 'erro' : 'sucesso');
        toast.setAttribute('role', ehErro ? 'alert' : 'status');

        const icone = document.createElement('span');
        icone.className = 'vh-toast__icone';
        icone.setAttribute('aria-hidden', 'true');
        icone.innerHTML = '<span class="dashicons dashicons-' + (ehErro ? 'warning' : 'yes-alt') + '"></span>';

        const texto = document.createElement('p');
        texto.className = 'vh-toast__texto';
        texto.textContent = mensagem;

        const fechar = document.createElement('button');
        fechar.type = 'button';
        fechar.className = 'vh-toast__fechar';
        fechar.setAttribute('aria-label', I18N.fecharAviso || 'Fechar aviso');
        fechar.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
        fechar.addEventListener('click', function () {
            if (toastTimer) {
                window.clearTimeout(toastTimer);
                toastTimer = null;
            }
            removerToast(toast);
        });

        toast.appendChild(icone);
        toast.appendChild(texto);
        toast.appendChild(fechar);
        stack.appendChild(toast);

        const duracao = ehErro ? 7000 : 4500;
        toastTimer = window.setTimeout(function () {
            toastTimer = null;
            removerToast(toast);
        }, duracao);
    }

    /* ── Modal de confirmação reutilizável (Promise<boolean>) ── */
    let modalResolver = null;

    function fecharModalConfirmacao(resultado) {
        const modal = document.getElementById('vh-modal-confirmacao');
        if (!modal) {
            if (modalResolver) {
                modalResolver(resultado);
                modalResolver = null;
            }
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('vh-modal-aberto');
        if (modalResolver) {
            modalResolver(resultado);
            modalResolver = null;
        }
    }

    function confirmar(opcoes) {
        const modal = document.getElementById('vh-modal-confirmacao');
        if (!modal) {
            /* Fail-closed no painel: sem DOM do modal, não prossegue. */
            return Promise.resolve(false);
        }

        /* Impede empilhar modais (evita resolver Promises cruzadas). */
        if (modalResolver) {
            return Promise.resolve(false);
        }

        const tituloEl = document.getElementById('vh-modal-titulo');
        const mensagemEl = document.getElementById('vh-modal-mensagem');
        const iconeEl = document.getElementById('vh-modal-icone');
        const btnCancelar = document.getElementById('vh-modal-cancelar');
        const btnConfirmar = document.getElementById('vh-modal-confirmar');
        const perigo = opcoes && opcoes.tipo === 'perigo';

        modal.classList.toggle('vh-modal--perigo', perigo);
        if (tituloEl) {
            tituloEl.textContent = (opcoes && opcoes.titulo) || I18N.confirmarSalvarTitulo || 'Confirmar';
        }
        if (mensagemEl) {
            /* textContent neutraliza HTML — mensagens vêm do PHP (esc_attr) ou i18n fixo. */
            mensagemEl.textContent = (opcoes && opcoes.mensagem) || '';
        }
        if (iconeEl) {
            const icone = iconeEl.querySelector('.dashicons');
            if (icone) {
                icone.className = 'dashicons dashicons-' + (perigo ? 'warning' : 'yes-alt');
            }
        }
        if (btnCancelar) {
            btnCancelar.textContent = (opcoes && opcoes.cancelar) || I18N.cancelar || 'Cancelar';
        }
        if (btnConfirmar) {
            btnConfirmar.textContent = (opcoes && opcoes.confirmar) || I18N.confirmarSalvarBtn || 'Confirmar';
            btnConfirmar.className = 'vh-btn vh-btn--primario';
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('vh-modal-aberto');

        return new Promise(function (resolve) {
            modalResolver = resolve;
            window.setTimeout(function () {
                if (btnConfirmar) {
                    btnConfirmar.focus();
                }
            }, 50);
        });
    }

    /* Listeners do modal (registrados uma vez). */
    (function initModalConfirmacao() {
        const modal = document.getElementById('vh-modal-confirmacao');
        if (!modal) {
            return;
        }

        document.getElementById('vh-modal-confirmar')?.addEventListener('click', function () {
            fecharModalConfirmacao(true);
        });
        document.getElementById('vh-modal-cancelar')?.addEventListener('click', function () {
            fecharModalConfirmacao(false);
        });
        modal.querySelectorAll('[data-vh-modal-fechar]').forEach(function (el) {
            el.addEventListener('click', function () {
                fecharModalConfirmacao(false);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (!modal.hidden && e.key === 'Escape') {
                fecharModalConfirmacao(false);
            }
        });
    }());

    /* ── Define valor em caminho com notação a[b][c] / a[] ── */
    function definirCaminho(alvo, nome, valor) {
        const tokens = nome.replace(/\]/g, '').split('[');

        /* "a[]" → empilha em um array na chave "a". */
        let empilhar = false;
        if (tokens.length > 1 && tokens[tokens.length - 1] === '') {
            empilhar = true;
            tokens.pop();
        }

        let atual = alvo;
        for (let i = 0; i < tokens.length; i++) {
            const chave = tokens[i];
            const ultimo = i === tokens.length - 1;

            if (ultimo) {
                if (empilhar) {
                    if (!Array.isArray(atual[chave])) {
                        atual[chave] = [];
                    }
                    atual[chave].push(valor);
                } else {
                    atual[chave] = valor;
                }
            } else {
                if (typeof atual[chave] !== 'object' || atual[chave] === null) {
                    atual[chave] = {};
                }
                atual = atual[chave];
            }
        }
    }

    /* ── Serializa um formulário em objeto aninhado ── */
    function serializar(form) {
        const dados = {};
        const campos = form.querySelectorAll('[name]');

        campos.forEach(function (el) {
            if (el.disabled) {
                return;
            }
            const nome = el.getAttribute('name');
            if (!nome) {
                return;
            }
            const tipo = el.type;

            if (tipo === 'checkbox') {
                if (nome.slice(-2) === '[]') {
                    if (el.checked) {
                        definirCaminho(dados, nome, el.value);
                    }
                } else {
                    definirCaminho(dados, nome, el.checked ? '1' : '0');
                }
                return;
            }

            if (tipo === 'radio') {
                if (el.checked) {
                    definirCaminho(dados, nome, el.value);
                }
                return;
            }

            definirCaminho(dados, nome, el.value);
        });

        return dados;
    }

    /* ── Requisição REST ── */
    function requisitar(endpoint, metodo, corpo) {
        const url = APP.restUrl + endpoint.replace(/^\//, '');
        const opcoes = {
            method: metodo,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': APP.nonce,
            },
            credentials: 'same-origin',
        };
        if (corpo !== undefined && corpo !== null && metodo !== 'GET') {
            opcoes.body = JSON.stringify(corpo);
        }

        return fetch(url, opcoes).then(function (resp) {
            return resp.json().then(function (json) {
                if (!resp.ok) {
                    const msg = (json && json.message) ? json.message : I18N.erroGenerico;
                    throw new Error(msg);
                }
                return json;
            });
        });
    }

    /* ── Submissão de formulários REST ── */
    function tratarFormulario(form, opcoes) {
        opcoes = opcoes || {};
        const endpoint = form.getAttribute('data-endpoint');
        const metodo = (form.getAttribute('data-method') || 'POST').toUpperCase();
        const redirecionar = form.getAttribute('data-redirect');
        const botoes = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        const enviarTiny = !!opcoes.enviarTiny;

        botoes.forEach(function (b) {
            if (b.disabled) {
                b.setAttribute('data-vh-was-disabled', '1');
            }
            b.disabled = true;
        });

        const corpo = serializar(form);
        if (window.PAProdutoPersonalizacao && typeof window.PAProdutoPersonalizacao.serializar === 'function') {
            const tipoInput = form.querySelector('#vh-tipo');
            if (tipoInput && tipoInput.value === 'variable') {
                corpo.personalizacao = window.PAProdutoPersonalizacao.serializar();
            }
        }
        if (enviarTiny) {
            corpo.enviar_tiny = true;
        }

        requisitar(endpoint, metodo, corpo)
            .then(function (json) {
                const dados = json && json.dados ? json.dados : {};
                let msgSucesso = I18N.sucessoSalvar || I18N.sucesso;

                if (enviarTiny && dados.tiny) {
                    if (dados.tiny.enviado) {
                        msgSucesso = I18N.sucessoSalvarTiny || msgSucesso;
                    } else if (dados.tiny.enfileirado) {
                        msgSucesso = dados.tiny.mensagem || I18N.sucessoSalvarTinyFila || msgSucesso;
                    } else if (dados.tiny.erro) {
                        feedback(dados.tiny.erro, 'erro');
                        botoes.forEach(function (b) { b.disabled = false; });
                        return;
                    }
                }

                if (redirecionar) {
                    if (enviarTiny && dados.tiny && (dados.tiny.enviado || dados.tiny.enfileirado)) {
                        feedback(msgSucesso, 'sucesso');
                        window.setTimeout(function () {
                            let destino = redirecionar;
                            if (dados.id && destino.indexOf('{id}') !== -1) {
                                destino = destino.replace('{id}', dados.id);
                            }
                            window.location.href = destino;
                        }, 700);
                        return;
                    }
                    let destino = redirecionar;
                    if (dados.id && destino.indexOf('{id}') !== -1) {
                        destino = destino.replace('{id}', dados.id);
                    }
                    window.location.href = destino;
                    return;
                }
                feedback(msgSucesso, 'sucesso');
                if (form.getAttribute('data-reload') === '1') {
                    window.setTimeout(function () { window.location.reload(); }, 600);
                }
            })
            .catch(function (err) {
                feedback((err && err.message) || I18N.erroSalvar || I18N.erroGenerico, 'erro');
            })
            .finally(function () {
                botoes.forEach(function (b) {
                    b.disabled = b.getAttribute('data-vh-was-disabled') === '1';
                });
            });
    }

    /* ── Ação direta (ex.: excluir) ── */
    function tratarAcao(botao) {
        const endpoint = botao.getAttribute('data-endpoint');
        const metodo = (botao.getAttribute('data-method') || 'POST').toUpperCase();
        const redirecionar = botao.getAttribute('data-redirect');
        const confirmarMsg = botao.getAttribute('data-confirmar');

        function executar() {
            botao.disabled = true;

            requisitar(endpoint, metodo, null)
                .then(function () {
                    if (redirecionar) {
                        window.location.href = redirecionar;
                        return;
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    feedback(err.message || I18N.erroGenerico, 'erro');
                    botao.disabled = false;
                });
        }

        if (confirmarMsg) {
            confirmar({
                titulo: I18N.confirmarAcaoTitulo || 'Confirmar ação',
                mensagem: confirmarMsg,
                confirmar: I18N.confirmarAcaoBtn || 'Confirmar',
                cancelar: I18N.cancelar || 'Cancelar',
                tipo: 'perigo',
            }).then(function (ok) {
                if (ok) {
                    executar();
                }
            });
            return;
        }

        executar();
    }

    /* ═══════════════════════════════════════════════
     * Seletor de mídia baseado em ID (imagem destacada e galeria)
     * ═══════════════════════════════════════════════ */
    function thumb(att) {
        if (att.sizes && att.sizes.thumbnail) {
            return att.sizes.thumbnail.url;
        }
        return att.url;
    }

    function selecionarMidiaUnica(botao) {
        const input = document.querySelector(botao.getAttribute('data-target'));
        const preview = document.querySelector(botao.getAttribute('data-preview'));
        const frame = window.wp.media({
            title: APP.i18n.selecionarImagem || 'Selecionar imagem',
            button: { text: APP.i18n.usarImagem || 'Usar imagem' },
            multiple: false,
            library: { type: 'image' },
        });
        frame.on('select', function () {
            const att = frame.state().get('selection').first().toJSON();
            if (input) { input.value = att.id; }
            if (preview) { preview.innerHTML = '<img src="' + thumb(att) + '" alt="" />'; }
        });
        frame.open();
    }

    function selecionarGaleria(botao) {
        const input = document.querySelector(botao.getAttribute('data-target'));
        const preview = document.querySelector(botao.getAttribute('data-preview'));
        const frame = window.wp.media({
            title: APP.i18n.selecionarImagens || 'Selecionar imagens',
            button: { text: APP.i18n.usarImagens || 'Usar imagens' },
            multiple: true,
            library: { type: 'image' },
        });
        frame.on('select', function () {
            const selecao = frame.state().get('selection').toJSON();
            const ids = selecao.map(function (a) { return a.id; });
            if (input) { input.value = ids.join(','); }
            if (preview) {
                preview.innerHTML = selecao.map(function (a) {
                    return '<img src="' + thumb(a) + '" alt="" />';
                }).join('');
            }
        });
        frame.open();
    }

    /* ═══════════════════════════════════════════════
     * Toggle de tipo de produto (simples ⇄ personalizável)
     * ═══════════════════════════════════════════════ */
    function alternarSecao(secao, ativo) {
        if (!secao) {
            return;
        }
        secao.style.display = ativo ? '' : 'none';
        secao.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !ativo;
        });
    }

    function aplicarTipoProduto() {
        const toggle = document.getElementById('vh-tipo-toggle');
        const hidden = document.getElementById('vh-tipo');
        if (!toggle || !hidden) {
            return;
        }
        const ehVariavel = toggle.checked;
        hidden.value = ehVariavel ? 'variable' : 'simple';
        alternarSecao(document.getElementById('vh-secao-simples'), !ehVariavel);
        alternarSecao(document.getElementById('vh-secao-variavel'), ehVariavel);
    }

    /* ── Listeners ── */
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'vh-tipo-toggle') {
            aplicarTipoProduto();
        }
    });

    if (document.readyState !== 'loading') {
        aplicarTipoProduto();
    } else {
        document.addEventListener('DOMContentLoaded', aplicarTipoProduto);
    }

    document.addEventListener('submit', function (e) {
        const form = e.target.closest('.vh-rest-form');
        if (!form) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        if (form.getAttribute('data-sem-confirmacao') === '1') {
            tratarFormulario(form, opcoesSubmit(e));
            return;
        }

        const opts = opcoesSubmit(e);
        confirmar({
            titulo: opts.confirmTitle || I18N.confirmarSalvarTitulo || 'Salvar alterações?',
            mensagem: opts.confirmMsg || form.getAttribute('data-confirmar') || I18N.confirmarSalvar || 'Deseja salvar as alterações?',
            confirmar: opts.confirmBtn || I18N.confirmarSalvarBtn || 'Salvar',
            cancelar: I18N.cancelar || 'Cancelar',
            tipo: opts.confirmTipo || 'primario',
        }).then(function (ok) {
            if (ok) {
                tratarFormulario(form, opts);
            }
        });
    }, true);

    function opcoesSubmit(e) {
        const submitter = e.submitter;
        const form = e.target.closest('.vh-rest-form');
        const enviarTiny = submitter && submitter.getAttribute('data-vh-enviar-tiny') === '1';
        if (!enviarTiny) {
            const pendente = form && form.getAttribute('data-vh-tiny-pendente') === '1';
            if (pendente) {
                return {
                    enviarTiny: false,
                    confirmTitle: I18N.confirmarSemTinyTitulo || 'Salvar sem enviar ao Tiny?',
                    confirmMsg: I18N.confirmarSemTiny || 'Este produto ficará diferente do Tiny ERP. Para atualizar o ERP agora, use “Salvar e enviar ao Tiny”.',
                    confirmBtn: I18N.confirmarSemTinyBtn || 'Salvar só na loja',
                    confirmTipo: 'primario',
                };
            }
            return { enviarTiny: false };
        }
        return {
            enviarTiny: true,
            confirmTitle: I18N.confirmarTinyTitulo || 'Enviar ao Tiny?',
            confirmMsg: I18N.confirmarTiny || 'Salvar o produto e enviar agora para o Tiny ERP?',
            confirmBtn: I18N.confirmarTinyBtn || 'Salvar e enviar',
            confirmTipo: 'primario',
        };
    }

    document.addEventListener('click', function (e) {
        const acao = e.target.closest('.vh-rest-acao');
        if (acao) {
            e.preventDefault();
            tratarAcao(acao);
            return;
        }

        const midiaEnviar = e.target.closest('.vh-media-enviar-btn');
        if (midiaEnviar) {
            e.preventDefault();
            if (window.paMediaCrop) {
                window.paMediaCrop.iniciar(midiaEnviar);
            }
            return;
        }

        const midiaUnica = e.target.closest('.vh-media-btn');
        if (midiaUnica) {
            e.preventDefault();
            if (!window.wp || !window.wp.media) {
                feedback(I18N.erroMidia || 'Biblioteca de mídia indisponível.', 'erro');
                return;
            }
            selecionarMidiaUnica(midiaUnica);
            return;
        }

        const galeria = e.target.closest('.vh-media-galeria-btn');
        if (galeria) {
            e.preventDefault();
            if (!window.wp || !window.wp.media) {
                feedback(I18N.erroMidia || 'Biblioteca de mídia indisponível.', 'erro');
                return;
            }
            selecionarGaleria(galeria);
            return;
        }

        const limpar = e.target.closest('.vh-media-limpar');
        if (limpar) {
            e.preventDefault();
            const input = document.querySelector(limpar.getAttribute('data-target'));
            const preview = document.querySelector(limpar.getAttribute('data-preview'));
            if (input) { input.value = ''; }
            if (preview) { preview.innerHTML = '<span class="dashicons dashicons-format-image"></span>'; }
        }
    });

    /* ── Design System — loading assíncrono (skeleton) ── */
    function ocultarEl(el) {
        if (!el) {
            return;
        }
        el.hidden = true;
        el.classList.add('vh-ui-oculto');
        el.setAttribute('aria-hidden', 'true');
        if (el.classList.contains('vh-skeleton-panel')) {
            el.setAttribute('aria-busy', 'false');
        }
    }

    function mostrarEl(el) {
        if (!el) {
            return;
        }
        el.hidden = false;
        el.classList.remove('vh-ui-oculto');
        el.removeAttribute('aria-hidden');
    }

    function esconderSkeleton(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        ocultarEl(el);
    }

    function mostrarPainelAsync(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        mostrarEl(el);
    }

    function skeletonErro(id, mensagem) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.add('vh-skeleton-panel--erro');
        el.setAttribute('aria-busy', 'false');
        el.innerHTML = '<p class="vh-skeleton-panel__erro">' + String(mensagem || '') + '</p>';
    }

    function concluirCarregamentoAsync(skeletonId, painelId) {
        esconderSkeleton(skeletonId);
        if (painelId) {
            mostrarPainelAsync(painelId);
        }
    }

    /* API interna para admin.js (repeater). Não substitui auth REST no servidor. */
    if (window.paLojaApp && typeof window.paLojaApp === 'object') {
        Object.defineProperty(window.paLojaApp, 'confirmar', {
            value: confirmar,
            writable: false,
            configurable: false,
        });
        Object.defineProperty(window.paLojaApp, 'feedback', {
            value: feedback,
            writable: false,
            configurable: false,
        });
        Object.defineProperty(window.paLojaApp, 'ui', {
            value: {
                ocultarEl: ocultarEl,
                mostrarEl: mostrarEl,
                esconderSkeleton: esconderSkeleton,
                mostrarPainelAsync: mostrarPainelAsync,
                skeletonErro: skeletonErro,
                concluirCarregamentoAsync: concluirCarregamentoAsync,
            },
            writable: false,
            configurable: false,
        });
    }
})();
