/**
 * Editor visual das descrições do produto.
 *
 * Usa o editor do WordPress só nesta tela. No formulário ele cresce com o
 * texto até um teto; Ampliar abre a mesma edição numa área larga.
 */
(function () {
    'use strict';

    var I18N = (window.paEditor && window.paEditor.i18n) || {};
    var aberto = null;
    var velo = null;

    function campoDa(caixa) {
        return caixa.querySelector('textarea.vh-editor-campo');
    }

    function editorDe(id) {
        return window.tinymce ? window.tinymce.get(id) : null;
    }

    function ler(id) {
        var editor = editorDe(id);
        if (editor && !editor.isHidden()) {
            return editor.getContent();
        }
        var campo = document.getElementById(id);
        return campo ? campo.value : '';
    }

    function gravar(id, html) {
        var campo = document.getElementById(id);
        if (campo) {
            campo.value = html;
        }
        var editor = editorDe(id);
        if (!editor) {
            return;
        }
        if (editor.undoManager) {
            editor.undoManager.transact(function () {
                editor.setContent(html);
            });
            return;
        }
        editor.setContent(html);
    }

    function limparHtml(html) {
        var doc = new DOMParser().parseFromString('<div id="vh-editor-raiz">' + html + '</div>', 'text/html');
        var raiz = doc.getElementById('vh-editor-raiz');
        if (!raiz) {
            return html;
        }

        raiz.querySelectorAll('*').forEach(function (el) {
            el.removeAttribute('style');
            el.removeAttribute('class');
            el.removeAttribute('id');
            ['align', 'face', 'color', 'size', 'bgcolor'].forEach(function (attr) {
                el.removeAttribute(attr);
            });
            Array.from(el.attributes).forEach(function (attr) {
                if (attr.name.indexOf('data-') === 0) {
                    el.removeAttribute(attr.name);
                }
            });
        });

        var pendente = true;
        while (pendente) {
            pendente = false;
            raiz.querySelectorAll('font, span').forEach(function (el) {
                if (el.attributes.length) {
                    return;
                }
                var pai = el.parentNode;
                while (el.firstChild) {
                    pai.insertBefore(el.firstChild, el);
                }
                pai.removeChild(el);
                pendente = true;
            });
        }

        return raiz.innerHTML;
    }

    function alturaCaixa(caixa, editor) {
        if (!caixa.classList.contains('is-ampliado')) {
            return parseInt(caixa.getAttribute('data-vh-editor-altura'), 10) || 200;
        }
        var topo = caixa.querySelector('.vh-editor-topo');
        var ferramentas = caixa.querySelector('.wp-editor-tools');
        var usado = 28;
        if (topo) {
            usado += topo.offsetHeight;
        }
        if (ferramentas) {
            usado += ferramentas.offsetHeight;
        }
        return Math.max(320, caixa.clientHeight - usado);
    }

    function ajustar(caixa) {
        var campo = campoDa(caixa);
        var editor = campo ? editorDe(campo.id) : null;
        if (!editor || !editor.iframeElement) {
            return;
        }
        var altura = alturaCaixa(caixa, editor);
        var teto = caixa.classList.contains('is-ampliado')
            ? altura
            : (parseInt(caixa.getAttribute('data-vh-editor-teto'), 10) || 420);
        editor.settings.autoresize_min_height = caixa.classList.contains('is-ampliado') ? altura : (parseInt(caixa.getAttribute('data-vh-editor-altura'), 10) || 160);
        editor.settings.autoresize_max_height = teto;
        editor.iframeElement.style.height = (caixa.classList.contains('is-ampliado') ? altura : Math.min(altura, teto)) + 'px';
        if (!caixa.classList.contains('is-ampliado')) {
            editor.fire('keyup');
        }
    }

    function garantirVelo() {
        if (velo) {
            return velo;
        }
        velo = document.createElement('div');
        velo.className = 'vh-editor-velo';
        velo.hidden = true;
        velo.addEventListener('click', recolher);
        document.body.appendChild(velo);
        return velo;
    }

    function recolher() {
        if (!aberto) {
            return;
        }
        var caixa = aberto;
        var botao = caixa.querySelector('.vh-editor-ampliar');
        aberto = null;
        caixa.classList.remove('is-ampliado');
        document.body.classList.remove('vh-editor-aberto');
        if (velo) {
            velo.hidden = true;
        }
        if (botao) {
            marcarAmpliar(botao, false);
        }
        ajustar(caixa);
        if (botao) {
            botao.focus();
        }
    }

    function ampliar(caixa) {
        if (aberto && aberto !== caixa) {
            recolher();
        }
        aberto = caixa;
        caixa.classList.add('is-ampliado');
        document.body.classList.add('vh-editor-aberto');
        garantirVelo().hidden = false;
        var botao = caixa.querySelector('.vh-editor-ampliar');
        if (botao) {
            marcarAmpliar(botao, true);
        }
        window.requestAnimationFrame(function () {
            ajustar(caixa);
        });
    }

    function marcarAmpliar(botao, aberto) {
        var icone = botao.querySelector('.dashicons');
        botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        botao.setAttribute('aria-label', aberto ? (I18N.recolher || 'Recolher') : (I18N.ampliar || 'Ampliar'));
        if (!icone) {
            return;
        }
        icone.classList.toggle('dashicons-fullscreen-alt', !aberto);
        icone.classList.toggle('dashicons-fullscreen-exit-alt', aberto);
    }

    function semTexto(html) {
        var doc = new DOMParser().parseFromString(html || '', 'text/html');
        var texto = (doc.body.textContent || '').replace(/\u00a0/g, ' ').trim();
        return texto === '' && !doc.body.querySelector('img, iframe, video');
    }

    function iniciar(caixa) {
        var campo = campoDa(caixa);
        if (!campo || !campo.id || !window.wp || !wp.editor || typeof wp.editor.initialize !== 'function') {
            return;
        }

        var altura = parseInt(caixa.getAttribute('data-vh-editor-altura'), 10) || 200;
        var teto = parseInt(caixa.getAttribute('data-vh-editor-teto'), 10) || 260;
        var opcional = caixa.getAttribute('data-vh-editor-opcional') === '1';
        if (opcional && semTexto(campo.value)) {
            caixa.classList.add('is-fechado');
        }

        wp.editor.initialize(campo.id, {
            tinymce: {
                wpautop: false,
                wp_autoresize_on: true,
                autoresize_min_height: altura,
                autoresize_max_height: teto,
                toolbar1: 'bold,italic,bullist,numlist,link,undo,redo',
                toolbar2: 'formatselect,blockquote,alignleft,aligncenter,alignright,unlink,removeformat',
                menubar: false,
                branding: false,
                statusbar: false,
                resize: false,
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.55; color: #212529; margin: 12px 14px; }',
                setup: function (editor) {
                    editor.on('init', function () {
                        var abas = caixa.querySelector('.wp-editor-tabs');
                        var ampliarBtn = caixa.querySelector('.vh-editor-ampliar');
                        if (abas && ampliarBtn) {
                            abas.appendChild(ampliarBtn);
                        }
                        caixa.classList.add('is-pronto');
                    });
                    editor.on('blur', function () {
                        window.setTimeout(function () {
                            if (!opcional || caixa.classList.contains('is-ampliado')) {
                                return;
                            }
                            if (caixa.contains(document.activeElement)) {
                                return;
                            }
                            if (semTexto(ler(campo.id))) {
                                caixa.classList.add('is-fechado');
                            }
                        }, 180);
                    });
                }
            },
            quicktags: {
                buttons: 'strong,em,link,block,ul,ol,li,close'
            },
            mediaButtons: false
        });

        var ampliarBtn = caixa.querySelector('.vh-editor-ampliar');
        if (ampliarBtn) {
            ampliarBtn.addEventListener('click', function () {
                if (caixa.classList.contains('is-ampliado')) {
                    recolher();
                    return;
                }
                ampliar(caixa);
            });
        }

        var limparBtn = caixa.querySelector('.vh-editor-limpar');
        if (limparBtn) {
            limparBtn.addEventListener('click', function () {
                gravar(campo.id, limparHtml(ler(campo.id)));
                caixa.classList.remove('is-fechado');
            });
        }

        var abrirBtn = caixa.querySelector('.vh-editor-abrir');
        if (abrirBtn) {
            abrirBtn.addEventListener('click', function () {
                caixa.classList.remove('is-fechado');
                window.setTimeout(function () {
                    var editor = editorDe(campo.id);
                    if (editor) {
                        editor.focus();
                    }
                    ajustar(caixa);
                }, 30);
            });
        }
    }

    function boot() {
        document.querySelectorAll('[data-vh-editor]').forEach(iniciar);
    }

    document.addEventListener('submit', function () {
        if (window.tinyMCE) {
            window.tinyMCE.triggerSave();
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !aberto) {
            return;
        }
        if (document.querySelector('.mce-window, #wp-link-backdrop, #wp-link-wrap')) {
            return;
        }
        recolher();
    });

    window.addEventListener('resize', function () {
        if (aberto) {
            ajustar(aberto);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
