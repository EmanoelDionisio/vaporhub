/**
 * Editor de personalização por produto — dimensões e opções dinâmicas.
 */
(function () {
    'use strict';

    var CFG = window.paProdutoPers || {};
    var I18N = CFG.i18n || {};
    var catalogo = Array.isArray(CFG.catalogo) ? CFG.catalogo : [];
    var catalogoPorTax = {};
    catalogo.forEach(function (cat) {
        if (cat && cat.taxonomy) {
            catalogoPorTax[cat.taxonomy] = cat;
        }
    });
    var state = normalizar(CFG.config || { versao: 1, preco_base: '', dimensoes: [] });
    var raiz;
    var elCombinacoes;
    var openDimIds = new Set();
    var draggedDim = null;
    var dropIndicadorEl = null;

    function uid(prefix) {
        return prefix + '_' + Math.random().toString(36).slice(2, 10);
    }

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }

    function normalizar(config) {
        var c = config && typeof config === 'object' ? config : {};
        c.versao = 1;
        c.preco_base = c.preco_base != null ? String(c.preco_base) : '';
        c.dimensoes = Array.isArray(c.dimensoes) ? c.dimensoes.map(function (dim, i) {
            dim = dim && typeof dim === 'object' ? dim : {};
            dim.id = dim.id || uid('dim');
            dim.nome = dim.nome || '';
            dim.tipo = dim.tipo || 'texto';
            dim.ordem = typeof dim.ordem === 'number' ? dim.ordem : i;
            dim.taxonomy = dim.taxonomy || '';
            dim.opcoes = Array.isArray(dim.opcoes) ? dim.opcoes.map(function (op) {
                op = op && typeof op === 'object' ? op : {};
                op.id = op.id || uid('op');
                op.nome = op.nome || '';
                op.slug = op.slug || '';
                op.preco_acrescimo = op.preco_acrescimo != null ? String(op.preco_acrescimo) : '';
                op.cor = op.cor || '#c0c0c0';
                op.imagem_id = parseInt(op.imagem_id, 10) || 0;
                op.imagem_url = op.imagem_url || '';
                return op;
            }) : [];
            return dim;
        }) : [];
        return c;
    }

    function dimCompartilhada(dim) {
        var tax = (dim && dim.taxonomy) ? String(dim.taxonomy) : '';
        return tax !== '' && !!catalogoPorTax[tax];
    }

    function taxonomiasEmUso() {
        var map = {};
        state.dimensoes.forEach(function (dim) {
            if (dim.taxonomy) {
                map[String(dim.taxonomy)] = true;
            }
        });
        return map;
    }

    function filtrarCatalogo(query) {
        var q = (query || '').trim().toLowerCase();
        if (!q) {
            return [];
        }
        var usadas = taxonomiasEmUso();
        return catalogo.filter(function (cat) {
            if (!cat || !cat.taxonomy || usadas[cat.taxonomy]) {
                return false;
            }
            return String(cat.nome || '').toLowerCase().indexOf(q) !== -1;
        }).slice(0, 12);
    }

    function dimFromCatalog(cat) {
        var opcoes = (cat.termos || []).map(function (termo) {
            return {
                id: uid('op'),
                nome: termo.nome || '',
                slug: termo.slug || '',
                preco_acrescimo: termo.preco_acrescimo != null ? String(termo.preco_acrescimo) : '',
                cor: termo.cor || '#c0c0c0',
                imagem_id: parseInt(termo.imagem_id, 10) || 0,
                imagem_url: termo.imagem_url || ''
            };
        });
        if (!opcoes.length) {
            opcoes.push({
                id: uid('op'),
                nome: '',
                slug: '',
                preco_acrescimo: '',
                cor: '#c0c0c0',
                imagem_id: 0,
                imagem_url: ''
            });
        }
        return {
            id: uid('dim'),
            nome: cat.nome || '',
            tipo: cat.tipo || 'texto',
            ordem: state.dimensoes.length,
            taxonomy: cat.taxonomy || '',
            opcoes: opcoes
        };
    }

    function renderReusoPanel() {
        if (!catalogo.length) {
            return '';
        }
        var html = '<div class="vh-pers-reuso" data-vh-pers-reuso>';
        html += '<label class="vh-pers-reuso__label">' + esc(I18N.reusoBusca || 'Buscar personalização existente') + '</label>';
        html += '<div class="vh-pers-reuso__busca">';
        html += '<span class="dashicons dashicons-search vh-pers-reuso__icone" aria-hidden="true"></span>';
        html += '<input type="search" class="vh-input vh-pers-reuso__input" data-acao="reuso-busca" autocomplete="off" placeholder="' + esc(I18N.reusoPlaceholder || 'Ex.: Tamanho do EVA…') + '" />';
        html += '</div>';
        html += '<ul class="vh-pers-reuso__lista vh-ui-oculto" data-vh-pers-reuso-lista role="listbox" aria-label="' + esc(I18N.reusoBusca || 'Buscar personalização existente') + '"></ul>';
        html += '</div>';
        return html;
    }

    function atualizarListaReuso(inputEl) {
        var lista = raiz && raiz.querySelector('[data-vh-pers-reuso-lista]');
        if (!lista) {
            return;
        }
        var q = inputEl ? String(inputEl.value || '').trim() : '';
        var itens = filtrarCatalogo(q);
        if (!q) {
            lista.classList.add('vh-ui-oculto');
            lista.innerHTML = '';
            return;
        }
        if (!itens.length) {
            lista.classList.remove('vh-ui-oculto');
            lista.innerHTML = '<li class="vh-pers-reuso__vazio" role="presentation">' + esc(I18N.reusoVazio || 'Nenhuma personalização encontrada.') + '</li>';
            return;
        }
        lista.classList.remove('vh-ui-oculto');
        lista.innerHTML = itens.map(function (cat) {
            var qtd = (cat.termos || []).length;
            var meta = tipoLabel(cat.tipo) + ' · ' + qtd + ' ' + (qtd === 1 ? (I18N.opcaoSingular || '1 opção') : (I18N.opcoesPlural || '%d opções').replace('%d', String(qtd)));
            return '<li role="presentation"><button type="button" class="vh-pers-reuso__item" data-acao="reuso-pick" data-taxonomy="' + esc(cat.taxonomy) + '">' +
                '<span class="vh-pers-reuso__item-nome">' + esc(cat.nome) + '</span>' +
                '<span class="vh-pers-reuso__item-meta">' + esc(meta) + '</span>' +
                '</button></li>';
        }).join('');
    }

    function adicionarDoCatalogo(taxonomy) {
        if (state.dimensoes.length >= (CFG.maxDimensoes || 8)) {
            window.alert(I18N.maxDim || 'Limite de opções de personalização atingido.');
            return;
        }
        var cat = catalogoPorTax[taxonomy];
        if (!cat) {
            return;
        }
        if (taxonomiasEmUso()[taxonomy]) {
            window.alert(I18N.reusoJaAdicionada || 'Esta personalização já está neste produto.');
            return;
        }
        var dim = dimFromCatalog(cat);
        openDimIds.clear();
        openDimIds.add(dim.id);
        state.dimensoes.push(dim);
        render();
    }

    function resumoTitulo(dim) {
        var nome = (dim.nome || '').trim();
        return nome || (I18N.dimSemNome || 'Personalização sem nome');
    }

    function resumoQtdLabel(dim) {
        var qtd = (dim.opcoes || []).length;
        if (qtd === 1) {
            return I18N.opcaoSingular || '1 opção';
        }
        return (I18N.opcoesPlural || '%d opções').replace('%d', String(qtd));
    }

    function contagemDimensoesLabel(total) {
        if (total === 1) {
            return I18N.dimSingular || '1 opção de personalização';
        }
        return (I18N.dimensoesPlural || '%d opções de personalização').replace('%d', String(total));
    }

    function atualizarContagemDimensoes() {
        var el = raiz && raiz.querySelector('[data-vh-pers-contagem]');
        if (!el) {
            return;
        }
        el.textContent = contagemDimensoesLabel(state.dimensoes.length);
    }

    function reorderFromDom() {
        syncDomParaState();
        var ordem = [];
        raiz.querySelectorAll('.vh-pers-dim--accordion[data-dim-id]').forEach(function (el) {
            ordem.push(el.getAttribute('data-dim-id'));
        });
        state.dimensoes.sort(function (a, b) {
            return ordem.indexOf(a.id) - ordem.indexOf(b.id);
        });
    }

    function limparIndicadoresDropDim() {
        if (!raiz) {
            return;
        }
        raiz.querySelectorAll('.vh-pers-dim--accordion').forEach(function (el) {
            el.classList.remove(
                'vh-pers-dim--drop-before',
                'vh-pers-dim--drop-after',
                'vh-pers-dim--drop-alvo',
                'vh-pers-dim--dragging'
            );
        });
        if (dropIndicadorEl && dropIndicadorEl.parentNode) {
            dropIndicadorEl.parentNode.removeChild(dropIndicadorEl);
        }
    }

    function obterDropIndicador() {
        if (!dropIndicadorEl) {
            dropIndicadorEl = document.createElement('div');
            dropIndicadorEl.className = 'vh-pers-drop-indicador';
            dropIndicadorEl.setAttribute('aria-hidden', 'true');
        }
        return dropIndicadorEl;
    }

    function posicionarIndicadorDrop(container, alvo, antes) {
        var ind = obterDropIndicador();
        if (antes) {
            container.insertBefore(ind, alvo);
        } else if (alvo.nextElementSibling) {
            container.insertBefore(ind, alvo.nextElementSibling);
        } else {
            container.appendChild(ind);
        }
    }

    function contarCombinacoes() {
        if (!state.dimensoes.length) {
            return 0;
        }
        return state.dimensoes.reduce(function (acc, dim) {
            var n = (dim.opcoes || []).length;
            return n > 0 ? acc * n : 0;
        }, 1);
    }

    function atualizarCombinacoes() {
        if (!elCombinacoes) {
            return;
        }
        var total = contarCombinacoes();
        if (total <= 0) {
            elCombinacoes.hidden = true;
            elCombinacoes.textContent = '';
            return;
        }
        elCombinacoes.hidden = false;
        var aviso = total > (CFG.maxCombinacoes || 200);
        elCombinacoes.textContent = (I18N.combinacoes || '%d combinações').replace('%d', String(total));
        elCombinacoes.classList.toggle('vh-pers-combinacoes--aviso', aviso);
        if (aviso && I18N.combinacoesAviso) {
            elCombinacoes.textContent += ' — ' + I18N.combinacoesAviso;
        }
    }

    function tipoLabel(tipo) {
        if (tipo === 'cor') {
            return I18N.tipoCor || 'Cores';
        }
        if (tipo === 'imagem') {
            return I18N.tipoImagem || 'Cards com imagem';
        }
        return I18N.tipoTexto || 'Botões de texto';
    }

    function classesTabela(dim) {
        var cls = 'vh-opcoes-tabela vh-opcoes-tabela--editor';
        if (dim.tipo === 'cor') {
            return cls;
        }
        if (dim.tipo === 'imagem') {
            return cls + ' vh-opcoes-tabela--sem-cor vh-opcoes-tabela--com-imagem';
        }
        return cls + ' vh-opcoes-tabela--sem-cor';
    }

    function renderOpcaoHead(dim) {
        var html = '<div class="vh-opcoes-tabela__head">';
        html += '<span class="vh-opcoes-tabela__col-ativo" aria-hidden="true"></span>';
        html += '<span class="vh-opcoes-tabela__col-nome">' + esc(I18N.colOpcao || 'Opção') + '</span>';
        if (dim.tipo === 'cor') {
            html += '<span class="vh-opcoes-tabela__col-cor">' + esc(I18N.colCor || 'Cor') + '</span>';
        }
        if (dim.tipo === 'imagem') {
            html += '<span class="vh-opcoes-tabela__col-imagem">' + esc(I18N.colImagem || 'Imagem') + '</span>';
        }
        html += '<span class="vh-opcoes-tabela__col-preco">' + esc(I18N.colPreco || 'Acréscimo') + '</span>';
        html += '</div>';
        return html;
    }

    function renderOpcao(dim, op, dimIndex, opIndex) {
        var html = '<div class="vh-opcoes-tabela__linha" data-dim="' + dimIndex + '" data-op="' + opIndex + '">';
        html += '<span class="vh-opcoes-tabela__col-ativo">';
        html += '<button type="button" class="vh-pers-remover-opcao" data-acao="remover-opcao" title="' + esc(I18N.removerOpcao || 'Remover opção') + '" aria-label="' + esc(I18N.removerOpcao || 'Remover opção') + '">';
        html += '<span class="dashicons dashicons-no-alt"></span></button></span>';

        html += '<span class="vh-opcoes-tabela__col-nome">';
        html += '<input type="text" class="vh-pers-opcao-nome" data-field="nome" value="' + esc(op.nome) + '" placeholder="' + esc(I18N.opcaoNome || 'Nome da opção') + '" />';
        html += '</span>';

        if (dim.tipo === 'cor') {
            html += '<span class="vh-opcoes-tabela__col-cor">';
            html += '<input type="color" class="vh-attr-cor" data-field="cor" value="' + esc(op.cor || '#c0c0c0') + '" title="' + esc(I18N.corSwatch || 'Cor na loja') + '" />';
            html += '</span>';
        }

        if (dim.tipo === 'imagem') {
            html += '<span class="vh-opcoes-tabela__col-imagem">';
            html += '<div class="vh-pers-thumb' + (op.imagem_url ? ' vh-pers-thumb--ok' : '') + '" data-thumb>';
            if (op.imagem_url) {
                html += '<img src="' + esc(op.imagem_url) + '" alt="" />';
            } else {
                html += '<span class="dashicons dashicons-format-image"></span>';
            }
            html += '</div>';
            html += '<button type="button" class="vh-btn vh-btn--ghost" data-acao="imagem">' + esc(I18N.selecionarImagem || 'Imagem') + '</button>';
            html += '<input type="hidden" data-field="imagem_id" value="' + (op.imagem_id || 0) + '" />';
            html += '</span>';
        }

        html += '<span class="vh-opcoes-tabela__col-preco">';
        html += '<span class="vh-opcoes-preco-field"><span class="vh-opcoes-preco-field__prefix">+ R$</span>';
        html += '<input type="number" step="0.01" min="0" class="vh-attr-preco" data-field="preco_acrescimo" value="' + esc(op.preco_acrescimo) + '" placeholder="0,00" />';
        html += '</span></span></div>';
        return html;
    }

    function renderDimensao(dim, index) {
        var aberto = openDimIds.has(dim.id);
        var html = '<details class="vh-pers-dim vh-pers-dim--accordion vh-form-grupo vh-form-grupo--opcoes"';
        html += ' data-dim-index="' + index + '" data-dim-id="' + esc(dim.id) + '"' + (aberto ? ' open' : '') + '>';

        html += '<summary class="vh-pers-dim__resumo">';
        html += '<span class="vh-pers-dim__drag-handle" draggable="true" role="button" tabindex="0" title="' + esc(I18N.arrastar || 'Arrastar para reordenar') + '" aria-label="' + esc(I18N.arrastar || 'Arrastar para reordenar') + '">';
        html += '<span class="dashicons dashicons-menu"></span></span>';
        html += '<span class="vh-pers-dim__ordem" aria-hidden="true">' + (index + 1) + '</span>';
        html += '<span class="vh-pers-dim__titulo"><strong class="vh-pers-dim__titulo-text">' + esc(resumoTitulo(dim)) + '</strong></span>';
        html += '<button type="button" class="vh-btn vh-btn--ghost vh-pers-dim__excluir-resumo" data-acao="remover-dim" aria-label="' + esc(I18N.removerDimAria || I18N.removerDim || 'Remover personalização') + '">' + esc(I18N.removerDim || 'Remover') + '</button>';
        html += '<span class="vh-pers-dim__meta">';
        if (dimCompartilhada(dim)) {
            html += '<span class="vh-pers-dim__badge vh-pers-dim__badge--compartilhada">' + esc(I18N.reusoCompartilhada || 'Compartilhada') + '</span>';
        }
        html += '<span class="vh-pers-dim__badge">' + esc(tipoLabel(dim.tipo)) + '</span>';
        html += '<span class="vh-pers-dim__qtd">' + esc(resumoQtdLabel(dim)) + '</span>';
        html += '</span>';
        html += '</summary>';

        html += '<div class="vh-pers-dim__corpo">';
        html += '<div class="vh-pers-dim__topo">';
        html += '<div class="vh-form-grupo vh-pers-dim__nome">';
        html += '<label class="vh-form-grupo__label">' + esc(I18N.dimLabel || 'Personalização') + '</label>';
        html += '<input type="text" class="vh-input" data-field="dim-nome" value="' + esc(dim.nome) + '" placeholder="' + esc(I18N.dimNome || 'Nome (ex.: Cor do EVA, Lado, Tamanho)') + '" />';
        html += '</div>';
        html += '<div class="vh-form-grupo vh-pers-dim__tipo">';
        html += '<label class="vh-form-grupo__label">' + esc(I18N.tipoLabel || 'Exibição na loja') + '</label>';
        html += '<select class="vh-input" data-field="dim-tipo"' + (dimCompartilhada(dim) ? ' disabled' : '') + '>';
        ['cor', 'imagem', 'texto'].forEach(function (t) {
            html += '<option value="' + t + '"' + (dim.tipo === t ? ' selected' : '') + '>' + esc(tipoLabel(t)) + '</option>';
        });
        html += '</select></div></div>';

        if (dimCompartilhada(dim)) {
            html += '<p class="description vh-pers-reuso-nota">' + esc(I18N.reusoNota || 'Usada em outros produtos — alterações nas opções valem para todos que a utilizam.') + '</p>';
        }

        html += '<div class="' + classesTabela(dim) + '" data-opcoes>';
        html += renderOpcaoHead(dim);
        (dim.opcoes || []).forEach(function (op, oi) {
            html += renderOpcao(dim, op, index, oi);
        });
        html += '</div>';

        html += '<button type="button" class="vh-btn vh-btn--ghost vh-pers-add-opcao" data-acao="add-opcao">';
        html += '<span class="dashicons dashicons-plus-alt2"></span> ' + esc(I18N.addOpcao || 'Adicionar opção') + '</button>';
        html += '</div></details>';
        return html;
    }

    function render() {
        var html = '';

        if (state.dimensoes.length) {
            html += '<div class="vh-pers-lista-cabecalho">';
            html += '<span class="vh-pers-lista-contagem" data-vh-pers-contagem>' + esc(contagemDimensoesLabel(state.dimensoes.length)) + '</span>';
            html += '<span class="vh-pers-lista-dica">' + esc(I18N.arrastarDica || 'Arraste pelo ícone ☰ para reordenar') + '</span>';
            html += '</div>';
            html += '<div class="vh-pers-lista">';
            state.dimensoes.forEach(function (dim, i) {
                html += renderDimensao(dim, i);
            });
            html += '</div>';
        }

        html += renderReusoPanel();

        html += '<div class="vh-pers-acoes">';
        html += '<button type="button" class="vh-btn vh-btn--secundario vh-pers-add-dim" data-acao="add-dim">';
        html += '<span class="dashicons dashicons-plus-alt2"></span> ' + esc(I18N.addDimNova || I18N.addDim || 'Criar personalização nova') + '</button>';
        html += '</div>';

        if (!state.dimensoes.length) {
            html = '<p class="vh-pers-vazio">' + esc(I18N.vazio || 'Nenhuma opção de personalização ainda. Adicione cor, formato, tamanho ou outra.') + '</p>' + html;
        }

        raiz.innerHTML = html;
        atualizarCombinacoes();
        atualizarContagemDimensoes();
    }

    function lerCampo(el, field) {
        if (!el) {
            return '';
        }
        if (field === 'imagem_id') {
            return parseInt(el.value, 10) || 0;
        }
        return el.value;
    }

    function syncDomParaState() {
        var precoInput = document.getElementById('vh-pers-preco-base');
        if (precoInput) {
            state.preco_base = precoInput.value;
        }

        raiz.querySelectorAll('.vh-pers-dim--accordion[data-dim-index]').forEach(function (dimEl) {
            var di = parseInt(dimEl.getAttribute('data-dim-index'), 10);
            if (!state.dimensoes[di]) {
                return;
            }
            var nomeEl = dimEl.querySelector('[data-field="dim-nome"]');
            var tipoEl = dimEl.querySelector('[data-field="dim-tipo"]');
            if (nomeEl) {
                state.dimensoes[di].nome = nomeEl.value;
            }
            if (tipoEl) {
                state.dimensoes[di].tipo = tipoEl.value;
            }

            dimEl.querySelectorAll('.vh-opcoes-tabela__linha[data-op]').forEach(function (opEl) {
                var oi = parseInt(opEl.getAttribute('data-op'), 10);
                if (!state.dimensoes[di].opcoes[oi]) {
                    return;
                }
                var op = state.dimensoes[di].opcoes[oi];
                op.nome = lerCampo(opEl.querySelector('[data-field="nome"]'), 'nome');
                op.preco_acrescimo = lerCampo(opEl.querySelector('[data-field="preco_acrescimo"]'), 'preco_acrescimo');
                if (state.dimensoes[di].tipo === 'cor') {
                    op.cor = lerCampo(opEl.querySelector('[data-field="cor"]'), 'cor');
                }
                if (state.dimensoes[di].tipo === 'imagem') {
                    op.imagem_id = lerCampo(opEl.querySelector('[data-field="imagem_id"]'), 'imagem_id');
                }
            });
        });
    }

    function confirmarRemoverDim() {
        var opcoes = {
            titulo: I18N.confirmRemoverDimTitulo || 'Remover opção de personalização?',
            mensagem: I18N.confirmRemoverDim || 'Remover esta opção de personalização e todas as escolhas?',
            confirmar: I18N.confirmRemoverDimBtn || 'Remover',
            cancelar: I18N.cancelar || 'Cancelar',
            tipo: 'perigo'
        };

        if (window.paLojaApp && typeof window.paLojaApp.confirmar === 'function') {
            return window.paLojaApp.confirmar(opcoes);
        }

        return Promise.resolve(window.confirm(opcoes.mensagem));
    }

    function abrirMidia(callback) {
        if (typeof wp === 'undefined' || !wp.media) {
            window.alert(I18N.erroMidia || 'Biblioteca de mídia indisponível.');
            return;
        }
        var frame = wp.media({
            title: I18N.selecionarImagem || 'Selecionar imagem',
            button: { text: I18N.usarImagem || 'Usar imagem' },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            callback(attachment);
        });
        frame.open();
    }

    function bindEvents() {
        if (!raiz || raiz.dataset.paPersBound) {
            return;
        }
        raiz.dataset.paPersBound = '1';

        raiz.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-acao]');
        if (!btn || !raiz.contains(btn)) {
            return;
        }
        if (btn.closest('.vh-pers-dim__resumo')) {
            ev.stopPropagation();
        }
        var acao = btn.getAttribute('data-acao');
        var dimEl = btn.closest('.vh-pers-dim--accordion');
        var opEl = btn.closest('.vh-opcoes-tabela__linha[data-op]');

        syncDomParaState();

        if (acao === 'add-dim') {
            if (state.dimensoes.length >= (CFG.maxDimensoes || 8)) {
                window.alert(I18N.maxDim || 'Limite de opções de personalização atingido.');
                return;
            }
            var novoId = uid('dim');
            openDimIds.clear();
            openDimIds.add(novoId);
            state.dimensoes.push({
                id: novoId,
                nome: '',
                tipo: 'texto',
                ordem: state.dimensoes.length,
                taxonomy: '',
                opcoes: [{ id: uid('op'), nome: '', slug: '', preco_acrescimo: '', cor: '#c0c0c0', imagem_id: 0, imagem_url: '' }]
            });
            render();
            return;
        }

        if (acao === 'reuso-pick') {
            var tax = btn.getAttribute('data-taxonomy');
            if (tax) {
                adicionarDoCatalogo(tax);
            }
            return;
        }

        if (!dimEl) {
            return;
        }

        var di = parseInt(dimEl.getAttribute('data-dim-index'), 10);
        var dimId = dimEl.getAttribute('data-dim-id');

        if (acao === 'add-opcao') {
            if ((state.dimensoes[di].opcoes || []).length >= (CFG.maxOpcoes || 30)) {
                window.alert(I18N.maxOpc || 'Limite de opções atingido.');
                return;
            }
            state.dimensoes[di].opcoes.push({
                id: uid('op'), nome: '', slug: '', preco_acrescimo: '', cor: '#c0c0c0', imagem_id: 0, imagem_url: ''
            });
            render();
            return;
        }

        if (acao === 'remover-dim') {
            confirmarRemoverDim().then(function (ok) {
                if (!ok) {
                    return;
                }
                if (dimId) {
                    openDimIds.delete(dimId);
                }
                state.dimensoes.splice(di, 1);
                render();
            });
            return;
        }

        if (acao === 'remover-opcao' && opEl) {
            var oi = parseInt(opEl.getAttribute('data-op'), 10);
            state.dimensoes[di].opcoes.splice(oi, 1);
            render();
            return;
        }

        if (acao === 'imagem' && opEl) {
            var oiImg = parseInt(opEl.getAttribute('data-op'), 10);
            abrirMidia(function (attachment) {
                syncDomParaState();
                state.dimensoes[di].opcoes[oiImg].imagem_id = attachment.id;
                state.dimensoes[di].opcoes[oiImg].imagem_url = attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;
                render();
            });
        }
        });

        raiz.addEventListener('change', function (ev) {
            if (ev.target.matches('[data-field="dim-tipo"]')) {
                syncDomParaState();
                render();
            }
        });

        raiz.addEventListener('input', function (ev) {
            if (ev.target.matches('[data-acao="reuso-busca"]')) {
                atualizarListaReuso(ev.target);
                return;
            }
            if (!ev.target.matches('[data-field="dim-nome"]')) {
                return;
            }
            var dimEl = ev.target.closest('.vh-pers-dim--accordion');
            if (!dimEl) {
                return;
            }
            var titulo = dimEl.querySelector('.vh-pers-dim__titulo-text');
            if (titulo) {
                titulo.textContent = ev.target.value.trim() || (I18N.dimSemNome || 'Personalização sem nome');
            }
        });

        raiz.addEventListener('toggle', function (ev) {
            var details = ev.target;
            if (!details.matches('.vh-pers-dim--accordion') || !raiz.contains(details)) {
                return;
            }
            var id = details.getAttribute('data-dim-id');
            if (!id) {
                return;
            }
            if (details.open) {
                openDimIds.add(id);
            } else {
                openDimIds.delete(id);
            }
        }, true);

        raiz.addEventListener('mousedown', function (ev) {
            if (ev.target.closest('.vh-pers-dim__drag-handle')) {
                ev.stopPropagation();
                return;
            }
            if (ev.target.closest('[data-acao="remover-dim"]')) {
                ev.preventDefault();
                ev.stopPropagation();
            }
        }, true);

        raiz.addEventListener('click', function (ev) {
            if (ev.target.closest('.vh-pers-dim__drag-handle')) {
                ev.stopPropagation();
            }
        }, true);

        raiz.addEventListener('dragstart', function (ev) {
            var handle = ev.target.closest('.vh-pers-dim__drag-handle');
            if (!handle) {
                ev.preventDefault();
                return;
            }

            draggedDim = handle.closest('.vh-pers-dim--accordion');
            if (!draggedDim) {
                ev.preventDefault();
                return;
            }

            syncDomParaState();
            draggedDim.classList.add('vh-pers-dim--dragging');
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', draggedDim.getAttribute('data-dim-id') || '');
            var preview = draggedDim.querySelector('.vh-pers-dim__resumo');
            if (preview && ev.dataTransfer.setDragImage) {
                ev.dataTransfer.setDragImage(preview, 48, 24);
            }
        });

        raiz.addEventListener('dragenter', function (ev) {
            if (draggedDim) {
                ev.preventDefault();
            }
        });

        raiz.addEventListener('dragover', function (ev) {
            if (!draggedDim) {
                return;
            }

            var container = raiz.querySelector('.vh-pers-lista');
            var alvo = ev.target.closest('.vh-pers-dim--accordion');
            if (!container || !alvo || alvo === draggedDim || !container.contains(alvo)) {
                if (dropIndicadorEl && dropIndicadorEl.parentNode) {
                    dropIndicadorEl.parentNode.removeChild(dropIndicadorEl);
                }
                return;
            }

            ev.preventDefault();
            ev.dataTransfer.dropEffect = 'move';

            raiz.querySelectorAll('.vh-pers-dim--accordion').forEach(function (item) {
                item.classList.remove('vh-pers-dim--drop-before', 'vh-pers-dim--drop-after', 'vh-pers-dim--drop-alvo');
            });
            if (dropIndicadorEl && dropIndicadorEl.parentNode) {
                dropIndicadorEl.parentNode.removeChild(dropIndicadorEl);
            }

            draggedDim.classList.add('vh-pers-dim--dragging');

            var rect = alvo.getBoundingClientRect();
            var antes = ev.clientY < rect.top + rect.height / 2;
            alvo.classList.add('vh-pers-dim--drop-alvo');
            alvo.classList.add(antes ? 'vh-pers-dim--drop-before' : 'vh-pers-dim--drop-after');
            posicionarIndicadorDrop(container, alvo, antes);
        });

        raiz.addEventListener('dragend', function () {
            draggedDim = null;
            limparIndicadoresDropDim();
        });

        raiz.addEventListener('drop', function (ev) {
            if (!draggedDim) {
                return;
            }

            ev.preventDefault();
            var container = raiz.querySelector('.vh-pers-lista');
            var alvo = ev.target.closest('.vh-pers-dim--accordion');
            if (!container || !alvo || alvo === draggedDim) {
                limparIndicadoresDropDim();
                return;
            }

            var rect = alvo.getBoundingClientRect();
            var antes = ev.clientY < rect.top + rect.height / 2;

            if (antes) {
                container.insertBefore(draggedDim, alvo);
            } else {
                container.insertBefore(draggedDim, alvo.nextElementSibling);
            }

            limparIndicadoresDropDim();
            reorderFromDom();
            render();
        });
    }

    window.PAProdutoPersonalizacao = {
        serializar: function () {
            syncDomParaState();
            var out = JSON.parse(JSON.stringify(state));
            out.dimensoes.forEach(function (dim, i) {
                dim.ordem = i;
                delete dim.imagem_url;
                (dim.opcoes || []).forEach(function (op) {
                    delete op.imagem_url;
                });
            });
            return out;
        },
        getState: function () {
            syncDomParaState();
            return state;
        }
    };

    function boot() {
        raiz = document.querySelector('[data-vh-pers-editor]');
        elCombinacoes = document.querySelector('[data-vh-pers-combinacoes]');
        if (!raiz) {
            return;
        }
        bindEvents();
        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
