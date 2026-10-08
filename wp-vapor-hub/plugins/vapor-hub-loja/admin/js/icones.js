/**
 * Biblioteca visual de ícones, compartilhada pelo painel.
 * O catálogo chega em vhIconesCatalogo. O valor fica num input oculto.
 */
(function () {
    var CFG = window.vhIconesCatalogo || {};
    var itens = CFG.itens || [];
    var biblioteca = null;
    var aberto = null;

    function htmlDo(slug) {
        var i;
        for (i = 0; i < itens.length; i++) {
            if (itens[i].slug === slug) {
                return itens[i].html || '';
            }
        }
        return '';
    }

    function desenhar(caixa, slug) {
        if (!caixa) {
            return;
        }
        caixa.textContent = '';
        var html = htmlDo(slug);
        if (!html) {
            var vazio = document.createElement('span');
            vazio.className = 'vh-menu-ico-vazio';
            caixa.appendChild(vazio);
            return;
        }
        var molde = document.createElement('template');
        molde.innerHTML = html.trim();
        if (molde.content.firstChild) {
            caixa.appendChild(molde.content.firstChild);
        }
    }

    function garantir() {
        if (biblioteca) {
            return biblioteca;
        }
        biblioteca = document.createElement('div');
        biblioteca.className = 'vh-menu-biblioteca';
        biblioteca.hidden = true;
        biblioteca.setAttribute('role', 'listbox');
        biblioteca.setAttribute('aria-label', CFG.rotulo || 'Ícone');

        function opcao(slug, rotulo) {
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'vh-menu-biblioteca-item';
            botao.setAttribute('role', 'option');
            botao.setAttribute('data-icone', slug);
            botao.setAttribute('aria-label', rotulo);
            var desenho = document.createElement('span');
            desenho.className = 'vh-menu-biblioteca-desenho';
            desenhar(desenho, slug);
            botao.appendChild(desenho);
            return botao;
        }

        biblioteca.appendChild(opcao('', CFG.semIcone || 'Sem ícone'));
        itens.forEach(function (icone) {
            biblioteca.appendChild(opcao(icone.slug, icone.rotulo || icone.slug));
        });

        biblioteca.addEventListener('click', function (evento) {
            var item = evento.target.closest('.vh-menu-biblioteca-item');
            if (!item || !aberto) {
                return;
            }
            var slug = item.getAttribute('data-icone') || '';
            aberto.input.value = slug;
            desenhar(aberto.botao.querySelector('.vh-menu-icone-atual'), slug);
            aberto.botao.classList.toggle('tem-icone', slug !== '');
            aberto.input.dispatchEvent(new Event('change', { bubbles: true }));
            fechar();
        });

        document.body.appendChild(biblioteca);
        return biblioteca;
    }

    function fechar() {
        if (!biblioteca || biblioteca.hidden) {
            return;
        }
        biblioteca.hidden = true;
        if (aberto) {
            aberto.botao.setAttribute('aria-expanded', 'false');
        }
        aberto = null;
    }

    function abrir(botao, input) {
        var painel = garantir();
        var permiteVazio = botao.getAttribute('data-vh-icone-vazio') === '1';
        aberto = { botao: botao, input: input };
        botao.setAttribute('aria-expanded', 'true');
        painel.querySelectorAll('.vh-menu-biblioteca-item').forEach(function (item) {
            var slug = item.getAttribute('data-icone') || '';
            var ativo = slug === (input.value || '');
            item.hidden = slug === '' && !permiteVazio;
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
        var largura = painel.offsetWidth || 320;
        var esquerda = Math.max(12, Math.min(rect.left, window.innerWidth - largura - 12));
        var topo = rect.bottom + 8;
        if (topo + painel.offsetHeight > window.innerHeight - 12) {
            topo = Math.max(12, rect.top - painel.offsetHeight - 8);
        }
        painel.style.left = esquerda + 'px';
        painel.style.top = topo + 'px';
    }

    function criar(slug, opcoes) {
        opcoes = opcoes || {};
        var linha = document.createElement('div');
        linha.className = 'vh-menu-icone-linha';
        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'vh-menu-icone-abrir' + (slug ? ' tem-icone' : '');
        botao.setAttribute('aria-haspopup', 'listbox');
        botao.setAttribute('aria-expanded', 'false');
        botao.setAttribute('aria-label', CFG.rotulo || 'Ícone');
        if (opcoes.vazio) {
            botao.setAttribute('data-vh-icone-vazio', '1');
        }
        var atual = document.createElement('span');
        atual.className = 'vh-menu-icone-atual';
        desenhar(atual, slug || '');
        botao.appendChild(atual);
        var input = document.createElement('input');
        input.type = 'hidden';
        input.className = 'vh-menu-icone';
        input.value = slug || '';
        linha.appendChild(botao);
        linha.appendChild(input);
        return linha;
    }

    document.addEventListener('click', function (evento) {
        var botao = evento.target.closest ? evento.target.closest('.vh-menu-icone-abrir') : null;
        if (botao) {
            evento.preventDefault();
            var linha = botao.closest('.vh-menu-icone-linha');
            var input = linha ? linha.querySelector('.vh-menu-icone, .vh-beneficio-icone') : null;
            if (!input) {
                return;
            }
            if (aberto && aberto.botao === botao) {
                fechar();
                return;
            }
            abrir(botao, input);
            return;
        }
        if (!biblioteca || biblioteca.hidden) {
            return;
        }
        if (biblioteca.contains(evento.target)) {
            return;
        }
        fechar();
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            fechar();
        }
    });

    window.addEventListener('scroll', function () {
        fechar();
    }, true);

    window.vhIconesBiblioteca = {
        desenhar: desenhar,
        criar: criar
    };
}());
