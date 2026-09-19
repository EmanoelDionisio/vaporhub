/**
 * Select customizado — Minha Loja (marca azul no dropdown).
 *
 * Mantém o <select> nativo (sr-only) para serialização REST e acessibilidade;
 * a UI visível usa lista com hover azul, contornando o vermelho do SO.
 *
 * @package VaporHubLoja
 */
(function () {
    'use strict';

    var I18N = (window.paLojaApp && window.paLojaApp.i18n) || {};

    function isSearchable(select) {
        return select && select.dataset.paSelectSearch !== undefined;
    }

    function painelDropdown(wrap) {
        return wrap.querySelector('.vh-select-custom__painel') || wrap.querySelector('.vh-select-custom__lista');
    }

    function ocultarDropdown(el) {
        if (!el) {
            return;
        }
        el.hidden = true;
        el.classList.add('vh-ui-oculto');
        el.setAttribute('aria-hidden', 'true');
    }

    function mostrarDropdown(el) {
        if (!el) {
            return;
        }
        el.hidden = false;
        el.classList.remove('vh-ui-oculto');
        el.removeAttribute('aria-hidden');
    }

    function dropdownFechado(painel) {
        return !painel || painel.hidden || painel.classList.contains('vh-ui-oculto');
    }

    function normalizarBusca(str) {
        return String(str || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function correspondeBusca(texto, query) {
        var normTexto = normalizarBusca(texto);
        var tokens = normalizarBusca(query).split(/\s+/).filter(Boolean);
        if (!tokens.length) {
            return true;
        }
        return tokens.every(function (tok) {
            return normTexto.indexOf(tok) !== -1;
        });
    }

    function posicionarLista(wrap) {
        var painel = painelDropdown(wrap);
        var lista = wrap.querySelector('.vh-select-custom__lista');
        var trigger = wrap.querySelector('.vh-select-custom__trigger');
        if (!painel || !trigger) {
            return;
        }

        painel.classList.remove('vh-select-custom__lista--acima');

        if (dropdownFechado(painel)) {
            return;
        }

        var rect = trigger.getBoundingClientRect();
        var alturaMax = isSearchable(wrap.querySelector('select')) ? 320 : 260;
        var espacoAbaixo = window.innerHeight - rect.bottom - 12;
        var espacoAcima = rect.top - 12;
        var alturaPainel = Math.min(alturaMax, Math.max(120, espacoAbaixo));

        if (espacoAbaixo < alturaMax && espacoAcima > espacoAbaixo) {
            painel.classList.add('vh-select-custom__lista--acima');
            alturaPainel = Math.min(alturaMax, Math.max(120, espacoAcima));
        }

        painel.style.maxHeight = alturaPainel + 'px';

        if (lista && wrap.classList.contains('vh-select-custom--searchable')) {
            var buscaWrap = wrap.querySelector('.vh-select-custom__busca-wrap');
            var vazio = wrap.querySelector('.vh-select-custom__vazio');
            var reservado = (buscaWrap ? buscaWrap.offsetHeight : 0) + (vazio && !vazio.hidden ? vazio.offsetHeight : 0) + 12;
            lista.style.maxHeight = Math.max(80, alturaPainel - reservado) + 'px';
        } else if (lista && !wrap.classList.contains('vh-select-custom--searchable')) {
            lista.style.maxHeight = alturaPainel + 'px';
        }
    }

    function filtrarOpcoes(wrap, query) {
        var lista = wrap.querySelector('.vh-select-custom__lista');
        var vazio = wrap.querySelector('.vh-select-custom__vazio');
        if (!lista) {
            return;
        }

        var visiveis = 0;
        lista.querySelectorAll('.vh-select-custom__opcao').forEach(function (li) {
            var texto = li.textContent || '';
            var ok = correspondeBusca(texto, query);
            li.hidden = !ok;
            li.classList.toggle('vh-ui-oculto', !ok);
            if (ok) {
                visiveis += 1;
            }
        });

        if (vazio) {
            vazio.hidden = visiveis > 0;
            vazio.classList.toggle('vh-ui-oculto', visiveis > 0);
        }
    }

    function resetBusca(wrap) {
        var input = wrap.querySelector('.vh-select-custom__busca');
        if (!input) {
            return;
        }
        input.value = '';
        filtrarOpcoes(wrap, '');
    }

    function abrirSelect(wrap) {
        var select = wrap.querySelector('select');
        var painel = painelDropdown(wrap);
        var trigger = wrap.querySelector('.vh-select-custom__trigger');
        if (!painel || !trigger) {
            return;
        }

        wrap.classList.add('is-aberto');
        mostrarDropdown(painel);
        trigger.setAttribute('aria-expanded', 'true');

        if (wrap.classList.contains('vh-select-custom--searchable')) {
            resetBusca(wrap);
            var input = wrap.querySelector('.vh-select-custom__busca');
            if (input) {
                window.requestAnimationFrame(function () {
                    input.focus();
                });
            }
        }

        posicionarLista(wrap);
    }

    function fecharTodos(exceto) {
        document.querySelectorAll('.vh-select-custom.is-aberto').forEach(function (wrap) {
            if (exceto && wrap === exceto) {
                return;
            }
            wrap.classList.remove('is-aberto');
            var painel = painelDropdown(wrap);
            var lista = wrap.querySelector('.vh-select-custom__lista');
            var trigger = wrap.querySelector('.vh-select-custom__trigger');
            var input = wrap.querySelector('.vh-select-custom__busca');
            if (painel) {
                ocultarDropdown(painel);
                painel.classList.remove('vh-select-custom__lista--acima');
                painel.style.maxHeight = '';
            }
            if (lista) {
                lista.style.maxHeight = '';
            }
            if (input) {
                input.value = '';
            }
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function sincronizarOpcoes(wrap, select) {
        var lista = wrap.querySelector('.vh-select-custom__lista');
        if (!lista) {
            return;
        }
        lista.innerHTML = '';
        Array.prototype.forEach.call(select.options, function (opt, idx) {
            var li = document.createElement('li');
            li.className = 'vh-select-custom__opcao';
            li.setAttribute('role', 'option');
            li.dataset.optionIndex = String(idx);
            li.textContent = opt.textContent;
            if (opt.selected) {
                li.classList.add('is-selecionada');
                li.setAttribute('aria-selected', 'true');
            } else {
                li.setAttribute('aria-selected', 'false');
            }
            li.addEventListener('click', function (e) {
                e.stopPropagation();
                select.selectedIndex = parseInt(li.dataset.optionIndex, 10);
                select.dispatchEvent(new Event('change', { bubbles: true }));
                atualizarTrigger(wrap, select);
                fecharTodos();
            });
            lista.appendChild(li);
        });

        if (wrap.classList.contains('vh-select-custom--searchable')) {
            filtrarOpcoes(wrap, '');
        }
    }

    function atualizarTrigger(wrap, select) {
        var trigger = wrap.querySelector('.vh-select-custom__trigger');
        var opt = select.options[select.selectedIndex];
        if (trigger) {
            trigger.textContent = opt ? opt.textContent : '';
        }
        wrap.querySelectorAll('.vh-select-custom__opcao').forEach(function (li) {
            var ativo = parseInt(li.dataset.optionIndex, 10) === select.selectedIndex;
            li.classList.toggle('is-selecionada', ativo);
            li.setAttribute('aria-selected', ativo ? 'true' : 'false');
        });
    }

    function montarSelect(select) {
        if (select.multiple || select.dataset.paSelectNative !== undefined) {
            return;
        }
        if (select.closest('.vh-select-custom')) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'vh-select-custom';

        select.classList.add('vh-select-custom__native');
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'vh-select-custom__trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        var lista = document.createElement('ul');
        lista.className = 'vh-select-custom__lista';
        lista.setAttribute('role', 'listbox');

        var searchable = isSearchable(select);
        var painel;

        if (searchable) {
            wrap.classList.add('vh-select-custom--searchable');

            painel = document.createElement('div');
            painel.className = 'vh-select-custom__painel vh-ui-oculto';
            painel.hidden = true;
            painel.setAttribute('aria-hidden', 'true');

            var buscaWrap = document.createElement('div');
            buscaWrap.className = 'vh-select-custom__busca-wrap';

            var busca = document.createElement('input');
            busca.type = 'search';
            busca.className = 'vh-select-custom__busca';
            busca.setAttribute('autocomplete', 'off');
            busca.setAttribute('spellcheck', 'false');
            busca.placeholder = I18N.buscarSelect || 'Buscar…';
            busca.setAttribute('aria-label', I18N.buscarSelect || 'Buscar');

            busca.addEventListener('input', function () {
                filtrarOpcoes(wrap, busca.value);
                posicionarLista(wrap);
            });
            busca.addEventListener('click', function (e) {
                e.stopPropagation();
            });
            busca.addEventListener('keydown', function (e) {
                e.stopPropagation();
                if (e.key === 'Escape') {
                    fecharTodos();
                }
            });

            var vazio = document.createElement('p');
            vazio.className = 'vh-select-custom__vazio vh-ui-oculto';
            vazio.hidden = true;
            vazio.textContent = I18N.nenhumResultadoSelect || 'Nenhum resultado encontrado.';

            buscaWrap.appendChild(busca);
            painel.appendChild(buscaWrap);
            painel.appendChild(lista);
            painel.appendChild(vazio);

            painel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        } else {
            lista.classList.add('vh-ui-oculto');
            lista.hidden = true;
            lista.setAttribute('aria-hidden', 'true');
            painel = lista;
        }

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var aberto = wrap.classList.contains('is-aberto');
            fecharTodos();
            if (!aberto) {
                abrirSelect(wrap);
            }
        });

        select.addEventListener('change', function () {
            atualizarTrigger(wrap, select);
            syncSelectDisabled(select);
        });

        wrap.appendChild(trigger);
        wrap.appendChild(painel);
        sincronizarOpcoes(wrap, select);
        atualizarTrigger(wrap, select);
        syncSelectDisabled(select);
    }

    function syncSelectDisabled(select) {
        var wrap = select.closest('.vh-select-custom');
        if (!wrap) {
            return;
        }
        var trigger = wrap.querySelector('.vh-select-custom__trigger');
        if (trigger) {
            trigger.disabled = select.disabled;
        }
        wrap.classList.toggle('is-desabilitado', select.disabled);
    }

    function initCustomSelects(root) {
        var selects;
        if (root && root !== document && root.nodeType === 1) {
            selects = root.querySelectorAll('select');
        } else {
            selects = document.querySelectorAll('.vh-loja-app select');
        }
        selects.forEach(montarSelect);
    }

    document.addEventListener('click', function () {
        fecharTodos();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            fecharTodos();
        }
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('.vh-select-custom.is-aberto').forEach(posicionarLista);
    });

    window.addEventListener(
        'scroll',
        function () {
            document.querySelectorAll('.vh-select-custom.is-aberto').forEach(posicionarLista);
        },
        true
    );

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initCustomSelects();
        });
    } else {
        initCustomSelects();
    }

    window.paInitCustomSelects = initCustomSelects;
    window.paSyncCustomSelect = syncSelectDisabled;
}());
