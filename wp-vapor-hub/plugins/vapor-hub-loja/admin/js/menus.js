/**
 * Editor de menus da vitrine — Minha Loja.
 *
 * A árvore vive no DOM até o salvamento. O servidor grava o menu nativo.
 *
 * @package VaporHubLoja
 */
(function () {
    'use strict';

    var CFG = window.vhMenus || {};
    var I18N = CFG.i18n || {};
    var origens = CFG.origens || {};
    var MAX_ITENS = parseInt(CFG.limites && CFG.limites.itens, 10) || 120;
    var MAX_NIVEL = parseInt(CFG.limites && CFG.limites.profundidade, 10) || 5;
    var itens = Array.isArray(CFG.itens) ? CFG.itens : [];
    var sujo = false;

    var arvore = document.getElementById('vh-menu-arvore');
    var form = document.getElementById('vh-menu-novo');
    var ajuda = document.getElementById('vh-menu-ajuda');
    var contagem = document.getElementById('vh-menu-contagem');
    var botaoSalvar = document.getElementById('vh-menu-salvar');

    if (!arvore || !form || !botaoSalvar) {
        return;
    }

    var rotuloSalvar = botaoSalvar.textContent;
    var seqChave = 1;
    var arraste = null;

    function feedback(mensagem, tipo) {
        if (window.paLojaApp && window.paLojaApp.feedback) {
            window.paLojaApp.feedback(mensagem, tipo);
            return;
        }
        window.alert(mensagem);
    }

    function ehAutomatico(flag) {
        return flag === true || flag === 1 || flag === '1';
    }

    function mostrarAviso(automatico) {
        if (!ajuda) {
            return;
        }
        var partes = [];
        if (CFG.descricao) {
            partes.push(CFG.descricao);
        }
        if (I18N.dica) {
            partes.push(I18N.dica);
        }
        if (ehAutomatico(automatico)) {
            partes.push(I18N.automatico || '');
        }
        ajuda.textContent = partes.filter(Boolean).join(' ');
    }

    function contar(lista) {
        return lista.reduce(function (total, item) {
            return total + 1 + contar(item.filhos || []);
        }, 0);
    }

    function altura(item) {
        var maior = 1;
        (item.filhos || []).forEach(function (filho) {
            maior = Math.max(maior, 1 + altura(filho));
        });
        return maior;
    }

    function atualizarContagem() {
        if (!contagem) {
            return;
        }
        var total = contar(itens);
        contagem.textContent = total === 1
            ? (I18N.umItem || '1 item')
            : String(total) + ' ' + (I18N.itens || 'itens');
    }

    function urlOk(url) {
        if (!url || /[\s\\]/.test(url)) {
            return false;
        }
        if (url.charAt(0) === '/' && url.charAt(1) !== '/') {
            return true;
        }
        return /^https?:\/\//i.test(url);
    }

    function validarNivel(lista, nivel) {
        var i;
        var erro;
        if (lista.length && nivel > MAX_NIVEL) {
            return I18N.profundo;
        }
        for (i = 0; i < lista.length; i++) {
            if (!lista[i].titulo) {
                return I18N.titulo;
            }
            if (lista[i].tipo === 'link' && !urlOk(lista[i].destino)) {
                return I18N.url;
            }
            if (lista[i].tipo !== 'link' && !lista[i].destino) {
                return I18N.destino;
            }
            erro = validarNivel(lista[i].filhos || [], nivel + 1);
            if (erro) {
                return erro;
            }
        }
        return '';
    }

    function validar(lista) {
        if (contar(lista) > MAX_ITENS) {
            return I18N.limite;
        }
        return validarNivel(lista, 1);
    }

    function el(tag, classe) {
        var node = document.createElement(tag);
        if (classe) {
            node.className = classe;
        }
        return node;
    }

    function rotuloCategoria(cat) {
        var prefixo = '';
        var i;
        for (i = 0; i < (cat.profundidade || 0); i++) {
            prefixo += '– ';
        }
        return prefixo + (cat.nome || '');
    }

    function preencherOpcoes(select, lista, valor, rotulo, vazio) {
        if (!lista.length) {
            var oco = document.createElement('option');
            oco.value = '';
            oco.textContent = vazio;
            select.appendChild(oco);
            select.disabled = true;
            return;
        }
        lista.forEach(function (entrada) {
            var opt = document.createElement('option');
            opt.value = String(entrada.id);
            opt.textContent = rotulo(entrada);
            if (String(entrada.id) === String(valor)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
        if (valor && select.value !== String(valor)) {
            var extra = document.createElement('option');
            extra.value = String(valor);
            extra.textContent = I18N.destinoAtual || String(valor);
            extra.selected = true;
            select.insertBefore(extra, select.firstChild);
        }
    }

    function criarDestino(tipo, valor) {
        var wrap = el('div', 'vh-menu-destino-wrap');
        if (tipo === 'link') {
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'vh-menu-destino vh-menu-campo';
            input.value = valor || '';
            input.placeholder = I18N.placeholderLink || '/loja/';
            input.maxLength = 500;
            input.autocomplete = 'off';
            input.setAttribute('aria-label', I18N.link || 'Link');
            wrap.appendChild(input);
            return wrap;
        }

        var select = document.createElement('select');
        select.className = 'vh-menu-destino';
        select.setAttribute('data-pa-select-search', '');
        select.setAttribute('aria-label', tipo === 'pagina' ? (I18N.pagina || 'Página') : (I18N.categoria || 'Categoria'));
        if (tipo === 'pagina') {
            preencherOpcoes(select, origens.paginas || [], valor, function (p) {
                return p.titulo || '';
            }, I18N.semPaginas || '');
        } else {
            preencherOpcoes(select, origens.categorias || [], valor, rotuloCategoria, I18N.semCategorias || '');
        }
        wrap.appendChild(select);
        return wrap;
    }

    function criarTipo(tipo) {
        var select = document.createElement('select');
        select.className = 'vh-menu-tipo';
        select.setAttribute('aria-label', I18N.aponta || 'Aponta para');
        [
            ['categoria', I18N.categoria || 'Categoria'],
            ['pagina', I18N.pagina || 'Página'],
            ['link', I18N.link || 'Link']
        ].forEach(function (par) {
            var opt = document.createElement('option');
            opt.value = par[0];
            opt.textContent = par[1];
            opt.selected = par[0] === tipo;
            select.appendChild(opt);
        });
        return select;
    }

    function garantirChave(item) {
        if (!item._k) {
            item._k = 'm' + (seqChave++);
        }
        return item._k;
    }

    function htmlIcone(slug) {
        var lista = CFG.icones || [];
        var i;
        for (i = 0; i < lista.length; i++) {
            if (lista[i].slug === slug) {
                return lista[i].html || '';
            }
        }
        return '';
    }

    function desenharSvg(caixa, slug) {
        if (!caixa) {
            return;
        }
        caixa.textContent = '';
        var html = htmlIcone(slug);
        if (!html) {
            caixa.appendChild(el('span', 'vh-menu-ico-vazio'));
            return;
        }
        var molde = document.createElement('template');
        molde.innerHTML = html.trim();
        if (molde.content.firstChild) {
            caixa.appendChild(molde.content.firstChild);
        }
    }

    var biblioteca = null;
    var iconeAberto = null;

    function garantirBiblioteca() {
        if (biblioteca) {
            return biblioteca;
        }
        biblioteca = el('div', 'vh-menu-biblioteca');
        biblioteca.hidden = true;
        biblioteca.setAttribute('role', 'listbox');
        biblioteca.setAttribute('aria-label', I18N.icone || 'Ícone');

        function opcao(slug, rotulo) {
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'vh-menu-biblioteca-item';
            botao.setAttribute('role', 'option');
            botao.setAttribute('data-icone', slug);
            botao.setAttribute('aria-label', rotulo);
            var desenho = el('span', 'vh-menu-biblioteca-desenho');
            desenharSvg(desenho, slug);
            botao.appendChild(desenho);
            return botao;
        }

        biblioteca.appendChild(opcao('', I18N.semIcone || 'Sem ícone'));
        (CFG.icones || []).forEach(function (icone) {
            biblioteca.appendChild(opcao(icone.slug, icone.rotulo || icone.slug));
        });

        biblioteca.addEventListener('click', function (evento) {
            var item = evento.target.closest('.vh-menu-biblioteca-item');
            if (!item || !iconeAberto) {
                return;
            }
            var slug = item.getAttribute('data-icone') || '';
            iconeAberto.input.value = slug;
            desenharSvg(iconeAberto.botao.querySelector('.vh-menu-icone-atual'), slug);
            iconeAberto.botao.classList.toggle('tem-icone', slug !== '');
            sujo = true;
            fecharBiblioteca();
        });

        document.body.appendChild(biblioteca);
        return biblioteca;
    }

    function fecharBiblioteca() {
        if (!biblioteca || biblioteca.hidden) {
            return;
        }
        biblioteca.hidden = true;
        if (iconeAberto) {
            iconeAberto.botao.setAttribute('aria-expanded', 'false');
        }
        iconeAberto = null;
    }

    function abrirBiblioteca(botao, input) {
        var painel = garantirBiblioteca();
        iconeAberto = { botao: botao, input: input };
        botao.setAttribute('aria-expanded', 'true');
        painel.querySelectorAll('.vh-menu-biblioteca-item').forEach(function (item) {
            var ativo = (item.getAttribute('data-icone') || '') === (input.value || '');
            item.classList.toggle('is-ativa', ativo);
            item.setAttribute('aria-selected', ativo ? 'true' : 'false');
        });
        painel.hidden = false;
        if (window.innerWidth < 720) {
            painel.style.left = '12px';
            painel.style.right = '12px';
            painel.style.top = 'auto';
            painel.style.bottom = '12px';
            return;
        }
        painel.style.right = 'auto';
        painel.style.bottom = 'auto';
        var rect = botao.getBoundingClientRect();
        var largura = painel.offsetWidth || 280;
        var esquerda = Math.max(12, Math.min(rect.left, window.innerWidth - largura - 12));
        var topo = rect.bottom + 8;
        if (topo + painel.offsetHeight > window.innerHeight - 12) {
            topo = Math.max(12, rect.top - painel.offsetHeight - 8);
        }
        painel.style.left = esquerda + 'px';
        painel.style.top = topo + 'px';
    }

    function criarIcone(slug) {
        var linha = el('div', 'vh-menu-icone-linha');
        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'vh-menu-icone-abrir' + (slug ? ' tem-icone' : '');
        botao.setAttribute('aria-haspopup', 'listbox');
        botao.setAttribute('aria-expanded', 'false');
        botao.setAttribute('aria-label', I18N.icone || 'Ícone');
        var atual = el('span', 'vh-menu-icone-atual');
        desenharSvg(atual, slug || '');
        botao.appendChild(atual);

        var input = document.createElement('input');
        input.type = 'hidden';
        input.className = 'vh-menu-icone';
        input.value = slug || '';

        botao.addEventListener('click', function (evento) {
            evento.preventDefault();
            if (iconeAberto && iconeAberto.botao === botao) {
                fecharBiblioteca();
                return;
            }
            abrirBiblioteca(botao, input);
        });

        linha.appendChild(botao);
        linha.appendChild(input);
        return linha;
    }

    function renderItem(item, naRaiz) {
        var li = document.createElement('li');
        var temFilhos = item.filhos && item.filhos.length;
        li.className = 'vh-menu-item' + (temFilhos ? ' vh-menu-item--pai' : '');
        li.setAttribute('data-id', String(item.id || 0));
        li.setAttribute('data-k', garantirChave(item));

        var linha = el('div', 'vh-menu-linha');
        var handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'vh-menu-arraste';
        handle.setAttribute('aria-label', I18N.arrastar || 'Arrastar');
        handle.title = I18N.arrastar || 'Arrastar';
        var iconeArraste = el('span', 'dashicons dashicons-move');
        iconeArraste.setAttribute('aria-hidden', 'true');
        handle.appendChild(iconeArraste);

        var campos = el('div', 'vh-menu-campos');
        var titulo = document.createElement('input');
        titulo.type = 'text';
        titulo.className = 'vh-menu-titulo vh-menu-campo';
        titulo.value = item.titulo || '';
        titulo.maxLength = parseInt(CFG.limites && CFG.limites.titulo, 10) || 80;
        titulo.autocomplete = 'off';
        titulo.setAttribute('aria-label', I18N.tituloCampo || 'Título');
        campos.appendChild(titulo);
        campos.appendChild(criarTipo(item.tipo || 'link'));
        campos.appendChild(criarDestino(item.tipo || 'link', item.destino || ''));
        if (naRaiz) {
            campos.appendChild(criarIcone(item.icone || ''));
        }

        var remover = document.createElement('button');
        remover.type = 'button';
        remover.className = 'vh-menu-ico vh-menu-ico--perigo';
        remover.setAttribute('data-acao', 'remover');
        remover.setAttribute('aria-label', I18N.remover || 'Remover');
        remover.title = I18N.remover || 'Remover';
        var iconeRemover = el('span', 'dashicons dashicons-trash');
        iconeRemover.setAttribute('aria-hidden', 'true');
        remover.appendChild(iconeRemover);

        linha.appendChild(handle);
        linha.appendChild(campos);
        linha.appendChild(remover);
        li.appendChild(linha);

        if (temFilhos) {
            li.appendChild(renderLista(item.filhos, false));
        }
        return li;
    }

    function renderLista(lista, naRaiz) {
        var ul = document.createElement('ul');
        ul.className = 'vh-menu-filhos';
        lista.forEach(function (item) {
            ul.appendChild(renderItem(item, naRaiz));
        });
        return ul;
    }

    function render() {
        arvore.textContent = '';
        if (!itens.length) {
            var vazio = el('p', 'vh-menu-vazio');
            vazio.textContent = I18N.vazio || '';
            arvore.appendChild(vazio);
        } else {
            var raiz = renderLista(itens, true);
            raiz.className = 'vh-menu-raiz';
            arvore.appendChild(raiz);
            if (window.paInitCustomSelects) {
                window.paInitCustomSelects(arvore);
            }
        }
        atualizarContagem();
    }

    function lerLista(ul) {
        var saida = [];
        Array.prototype.forEach.call(ul.children, function (li) {
            if (!li || li.tagName !== 'LI') {
                return;
            }
            var titulo = li.querySelector('.vh-menu-titulo');
            var tipo = li.querySelector('.vh-menu-tipo');
            var destino = li.querySelector('.vh-menu-destino');
            var icone = li.querySelector(':scope > .vh-menu-linha .vh-menu-icone');
            var filhos = li.querySelector(':scope > ul');
            saida.push({
                id: parseInt(li.getAttribute('data-id'), 10) || 0,
                titulo: titulo ? titulo.value.trim() : '',
                tipo: tipo ? tipo.value : 'link',
                destino: destino ? String(destino.value).trim() : '',
                icone: icone ? icone.value : '',
                filhos: filhos ? lerLista(filhos) : [],
                _k: li.getAttribute('data-k') || garantirChave({})
            });
        });
        return saida;
    }

    function caminhar(lista, fn) {
        var i;
        var resultado;
        for (i = 0; i < lista.length; i++) {
            resultado = fn(lista, i, lista[i]);
            if (resultado) {
                return resultado;
            }
            resultado = caminhar(lista[i].filhos || [], fn);
            if (resultado) {
                return resultado;
            }
        }
        return null;
    }

    function nivelDe(lista, chave, nivel) {
        var i;
        var abaixo;
        for (i = 0; i < lista.length; i++) {
            if (lista[i]._k === chave) {
                return nivel;
            }
            abaixo = nivelDe(lista[i].filhos || [], chave, nivel + 1);
            if (abaixo) {
                return abaixo;
            }
        }
        return 0;
    }

    function contemChave(node, chave) {
        if (!node || node._k === chave) {
            return !!node;
        }
        return (node.filhos || []).some(function (filho) {
            return contemChave(filho, chave);
        });
    }

    function podeMover(lista, chaveOrigem, chaveAlvo, posicao) {
        var origem = caminhar(lista, function (grupo, indice, node) {
            return node._k === chaveOrigem ? node : null;
        });
        var nivelAlvo = nivelDe(lista, chaveAlvo, 1);
        if (!origem || !nivelAlvo || contemChave(origem, chaveAlvo)) {
            return false;
        }
        var nivelNovo = posicao === 'inside' ? nivelAlvo + 1 : nivelAlvo;
        return nivelNovo + altura(origem) - 1 <= MAX_NIVEL;
    }

    function soltarItem(chaveOrigem, chaveAlvo, posicao) {
        itens = lerAtual();
        var origem = caminhar(itens, function (grupo, indice, node) {
            return node._k === chaveOrigem ? node : null;
        });
        var nivelAlvo = nivelDe(itens, chaveAlvo, 1);
        if (!origem || !nivelAlvo || chaveOrigem === chaveAlvo || contemChave(origem, chaveAlvo)) {
            return;
        }
        var nivelNovo = posicao === 'inside' ? nivelAlvo + 1 : nivelAlvo;
        if (nivelNovo + altura(origem) - 1 > MAX_NIVEL) {
            feedback(I18N.profundo, 'erro');
            return;
        }
        var origem = caminhar(itens, function (grupo, indice, node) {
            if (node._k !== chaveOrigem) {
                return null;
            }
            grupo.splice(indice, 1);
            return node;
        });
        var lugar = caminhar(itens, function (grupo, indice, node) {
            return node._k === chaveAlvo ? { lista: grupo, index: indice, node: node } : null;
        });
        if (!origem || !lugar) {
            return;
        }
        if (posicao === 'inside') {
            lugar.node.filhos = lugar.node.filhos || [];
            lugar.node.filhos.push(origem);
        } else {
            lugar.lista.splice(lugar.index + (posicao === 'after' ? 1 : 0), 0, origem);
        }
        sujo = true;
        render();
    }

    function limparDrop() {
        arvore.querySelectorAll('.vh-menu-item').forEach(function (item) {
            item.classList.remove(
                'vh-menu-item--drop-before',
                'vh-menu-item--drop-after',
                'vh-menu-item--drop-inside',
                'vh-menu-item--drop-invalid'
            );
        });
    }

    function encerrarArraste() {
        limparDrop();
        if (arraste && arraste.item) {
            arraste.item.classList.remove('vh-menu-item--dragging');
        }
        if (arraste && arraste.fantasma) {
            arraste.fantasma.remove();
        }
        document.body.classList.remove('vh-menu-arrastando');
        arraste = null;
    }

    function marcarAlvo(x, y) {
        limparDrop();
        arraste.item.classList.add('vh-menu-item--dragging');
        var sob = document.elementFromPoint(x, y);
        var alvo = sob && sob.closest('.vh-menu-item');
        if (!alvo || alvo === arraste.item || arraste.item.contains(alvo)) {
            if (alvo && alvo !== arraste.item) {
                alvo.classList.add('vh-menu-item--drop-invalid');
            }
            arraste.alvo = null;
            arraste.posicao = '';
            return;
        }

        var linha = alvo.querySelector('.vh-menu-linha') || alvo;
        var rect = linha.getBoundingClientRect();
        var rel = y - rect.top;
        var posicao = rel < rect.height * 0.28 ? 'before' : (rel > rect.height * 0.72 ? 'after' : 'inside');
        var lista = lerAtual();
        var chave = arraste.item.getAttribute('data-k');
        var chaveAlvo = alvo.getAttribute('data-k');
        if (!podeMover(lista, chave, chaveAlvo, posicao)) {
            var alternativa = rel < rect.height * 0.5 ? 'before' : 'after';
            if (alternativa !== posicao && podeMover(lista, chave, chaveAlvo, alternativa)) {
                posicao = alternativa;
            } else {
                alvo.classList.add('vh-menu-item--drop-invalid');
                arraste.alvo = null;
                arraste.posicao = '';
                return;
            }
        }
        alvo.classList.add('vh-menu-item--drop-' + posicao);
        arraste.alvo = alvo;
        arraste.posicao = posicao;
    }

    function iniciarArraste() {
        arvore.addEventListener('pointerdown', function (evento) {
            var handle = evento.target.closest('.vh-menu-arraste');
            if (!handle || !arvore.contains(handle) || evento.button > 0) {
                return;
            }
            var item = handle.closest('.vh-menu-item');
            if (!item) {
                return;
            }
            evento.preventDefault();
            try {
                handle.setPointerCapture(evento.pointerId);
            } catch (erroCaptura) {
                /* O gesto segue mesmo se o navegador não entregar o ponteiro. */
            }
            arraste = {
                pointerId: evento.pointerId,
                item: item,
                x: evento.clientX,
                y: evento.clientY,
                movendo: false,
                alvo: null,
                posicao: '',
                fantasma: null
            };
        });

        arvore.addEventListener('pointermove', function (evento) {
            if (!arraste || evento.pointerId !== arraste.pointerId) {
                return;
            }
            if (!arraste.movendo) {
                if (Math.abs(evento.clientX - arraste.x) + Math.abs(evento.clientY - arraste.y) < 8) {
                    return;
                }
                arraste.movendo = true;
                arraste.item.classList.add('vh-menu-item--dragging');
                document.body.classList.add('vh-menu-arrastando');
                arraste.fantasma = el('div', 'vh-menu-fantasma');
                var campo = arraste.item.querySelector('.vh-menu-titulo');
                arraste.fantasma.textContent = campo ? campo.value : '';
                document.body.appendChild(arraste.fantasma);
            }
            evento.preventDefault();
            arraste.fantasma.style.left = (evento.clientX + 14) + 'px';
            arraste.fantasma.style.top = (evento.clientY - 18) + 'px';
            marcarAlvo(evento.clientX, evento.clientY);
        });

        function concluir(evento) {
            if (!arraste || evento.pointerId !== arraste.pointerId) {
                return;
            }
            var estado = arraste;
            var chave = estado.item.getAttribute('data-k');
            var chaveAlvo = estado.alvo ? estado.alvo.getAttribute('data-k') : '';
            var posicao = estado.posicao;
            var movendo = estado.movendo;
            encerrarArraste();
            if (movendo && chave && chaveAlvo && posicao) {
                soltarItem(chave, chaveAlvo, posicao);
            }
        }

        arvore.addEventListener('pointerup', concluir);
        arvore.addEventListener('pointercancel', function (evento) {
            if (arraste && evento.pointerId === arraste.pointerId) {
                encerrarArraste();
            }
        });
    }

    function lerAtual() {
        var ul = arvore.querySelector(':scope > ul');
        return ul ? lerLista(ul) : [];
    }

    function pintarDestinoNovo(tipo) {
        var caixa = document.getElementById('vh-menu-destino-novo');
        if (!caixa) {
            return;
        }
        caixa.textContent = '';
        var campo = criarDestino(tipo, '');
        caixa.appendChild(campo);
        if (window.paInitCustomSelects) {
            window.paInitCustomSelects(campo);
        }
    }

    function montarFormulario() {
        var grupoTitulo = el('div', 'vh-form-grupo');
        var labelTitulo = document.createElement('label');
        labelTitulo.htmlFor = 'vh-menu-titulo';
        labelTitulo.textContent = I18N.tituloCampo || 'Título';
        var inputTitulo = document.createElement('input');
        inputTitulo.id = 'vh-menu-titulo';
        inputTitulo.type = 'text';
        inputTitulo.maxLength = parseInt(CFG.limites && CFG.limites.titulo, 10) || 80;
        inputTitulo.autocomplete = 'off';
        grupoTitulo.appendChild(labelTitulo);
        grupoTitulo.appendChild(inputTitulo);

        var grupoTipo = el('div', 'vh-form-grupo');
        var labelTipo = document.createElement('label');
        labelTipo.htmlFor = 'vh-menu-tipo';
        labelTipo.textContent = I18N.aponta || 'Aponta para';
        var selectTipo = criarTipo('categoria');
        selectTipo.id = 'vh-menu-tipo';
        grupoTipo.appendChild(labelTipo);
        grupoTipo.appendChild(selectTipo);

        var destino = el('div', 'vh-form-grupo');
        destino.id = 'vh-menu-destino-novo';

        var grupoIcone = el('div', 'vh-form-grupo');
        var labelIcone = document.createElement('label');
        labelIcone.htmlFor = 'vh-menu-icone-abrir';
        labelIcone.textContent = I18N.icone || 'Ícone';
        var campoIcone = criarIcone('');
        campoIcone.querySelector('.vh-menu-icone').id = 'vh-menu-icone';
        campoIcone.querySelector('.vh-menu-icone-abrir').id = 'vh-menu-icone-abrir';
        grupoIcone.appendChild(labelIcone);
        grupoIcone.appendChild(campoIcone);

        var adicionar = document.createElement('button');
        adicionar.type = 'submit';
        adicionar.className = 'vh-btn vh-btn--secundario';
        adicionar.textContent = I18N.adicionar || 'Adicionar';

        form.appendChild(grupoTitulo);
        form.appendChild(grupoTipo);
        form.appendChild(destino);
        form.appendChild(grupoIcone);
        form.appendChild(adicionar);
        pintarDestinoNovo('categoria');
        if (window.paInitCustomSelects) {
            window.paInitCustomSelects(grupoTipo);
        }

        selectTipo.addEventListener('change', function () {
            pintarDestinoNovo(selectTipo.value);
        });
    }

    function adicionar(evento) {
        evento.preventDefault();
        itens = lerAtual();
        if (contar(itens) >= MAX_ITENS) {
            feedback(I18N.limite, 'erro');
            return;
        }
        var titulo = document.getElementById('vh-menu-titulo');
        var tipo = document.getElementById('vh-menu-tipo');
        var destino = form.querySelector('#vh-menu-destino-novo .vh-menu-destino');
        var icone = document.getElementById('vh-menu-icone');
        var texto = titulo ? titulo.value.trim() : '';
        var tipoValor = tipo ? tipo.value : 'link';
        var destinoValor = destino ? String(destino.value).trim() : '';
        if (!texto) {
            feedback(I18N.titulo, 'erro');
            return;
        }
        if (tipoValor === 'link' && !urlOk(destinoValor)) {
            feedback(I18N.url, 'erro');
            return;
        }
        if (tipoValor !== 'link' && !destinoValor) {
            feedback(I18N.destino, 'erro');
            return;
        }
        itens.push({
            id: 0,
            titulo: texto,
            tipo: tipoValor,
            destino: destinoValor,
            icone: icone ? icone.value : '',
            filhos: []
        });
        sujo = true;
        render();
        titulo.value = '';
        if (icone) {
            icone.value = '';
            var botaoIcone = icone.parentElement.querySelector('.vh-menu-icone-abrir');
            if (botaoIcone) {
                desenharSvg(botaoIcone.querySelector('.vh-menu-icone-atual'), '');
                botaoIcone.classList.remove('tem-icone');
            }
        }
        titulo.focus();
    }

    function salvar() {
        var atual = lerAtual();
        var erro = validar(atual);
        if (erro) {
            feedback(erro, 'erro');
            return;
        }
        if (!CFG.restUrl || !CFG.nonce) {
            feedback(I18N.erro || 'Erro', 'erro');
            return;
        }
        botaoSalvar.disabled = true;
        botaoSalvar.textContent = I18N.salvando || rotuloSalvar;

        fetch(CFG.restUrl + 'menus', {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': CFG.nonce
            },
            body: JSON.stringify({
                posicao: CFG.posicao,
                itens: atual
            })
        }).then(function (resp) {
            return resp.json().then(function (json) {
                if (!resp.ok) {
                    var mensagem = (json && json.message) ? json.message : (I18N.erro || 'Erro');
                    throw new Error(mensagem);
                }
                return json;
            });
        }).then(function (json) {
            var dados = (json && json.dados) || {};
            itens = Array.isArray(dados.itens) ? dados.itens : [];
            sujo = false;
            mostrarAviso(dados.automatico);
            render();
            feedback(I18N.salvo || rotuloSalvar, 'sucesso');
        }).catch(function (err) {
            feedback((err && err.message) || I18N.erro || 'Erro', 'erro');
        }).then(function () {
            botaoSalvar.disabled = false;
            botaoSalvar.textContent = rotuloSalvar;
        });
    }

    arvore.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-acao="remover"]');
        if (!botao || !arvore.contains(botao)) {
            return;
        }
        var li = botao.closest('.vh-menu-item');
        if (!li) {
            return;
        }
        itens = lerAtual();
        var removido = caminhar(itens, function (grupo, indice, node) {
            if (node._k !== li.getAttribute('data-k')) {
                return null;
            }
            grupo.splice(indice, 1);
            return node;
        });
        if (!removido) {
            return;
        }
        sujo = true;
        render();
    });

    arvore.addEventListener('change', function (evento) {
        if (!evento.target.classList.contains('vh-menu-tipo')) {
            return;
        }
        var linha = evento.target.closest('.vh-menu-linha');
        if (!linha) {
            return;
        }
        var antigo = linha.querySelector('.vh-menu-destino-wrap');
        var novo = criarDestino(evento.target.value, '');
        if (antigo) {
            antigo.replaceWith(novo);
        }
        if (window.paInitCustomSelects) {
            window.paInitCustomSelects(novo);
        }
        sujo = true;
    });

    arvore.addEventListener('input', function () {
        sujo = true;
    });

    form.addEventListener('submit', adicionar);
    botaoSalvar.addEventListener('click', salvar);

    window.addEventListener('beforeunload', function (evento) {
        if (!sujo) {
            return;
        }
        evento.preventDefault();
        evento.returnValue = '';
    });

    iniciarArraste();
    montarFormulario();
    mostrarAviso(CFG.automatico);
    render();

    document.addEventListener('click', function (evento) {
        if (!biblioteca || biblioteca.hidden) {
            return;
        }
        if (biblioteca.contains(evento.target)) {
            return;
        }
        if (iconeAberto && iconeAberto.botao.contains(evento.target)) {
            return;
        }
        fecharBiblioteca();
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            fecharBiblioteca();
        }
    });

    window.addEventListener('scroll', function () {
        fecharBiblioteca();
    }, true);
}());
