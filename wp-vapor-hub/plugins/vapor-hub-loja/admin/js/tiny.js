/**
 * Tiny ERP — painel de integração.
 *
 * @package VaporHubLoja
 */
(function () {
    'use strict';

    var APP = window.paLojaApp || {};
    var TINY = window.paTinyApp || {};
    var I18N = TINY.i18n || {};

    function formatI18n(template, map) {
        var msg = template;
        Object.keys(map || {}).forEach(function (key) {
            var val = map[key];
            while (msg.indexOf(key) !== -1) {
                msg = msg.split(key).join(val);
            }
        });
        return msg;
    }

    function rest(path, method, body) {
        var opcoes = {
            method: method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': APP.nonce,
            },
            credentials: 'same-origin',
        };
        if (body) {
            opcoes.body = JSON.stringify(body);
        }
        return fetch(APP.restUrl + path.replace(/^\//, ''), opcoes).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok) {
                    throw new Error((j && j.message) || APP.i18n.erroGenerico);
                }
                return j;
            });
        });
    }

    function feedback(msg, tipo) {
        if (typeof APP.feedback === 'function') {
            APP.feedback(msg, tipo);
        }
    }

    function confirmarDesconectar(callback) {
        if (typeof APP.confirmar === 'function') {
            APP.confirmar({
                titulo: I18N.desconectarTitulo || 'Desconectar Tiny ERP',
                mensagem: I18N.desconectarConfirm || 'Desconectar o Tiny ERP desta loja?',
                confirmar: I18N.desconectarBtn || 'Desconectar',
                cancelar: I18N.cancelar || 'Cancelar',
                tipo: 'perigo',
            }).then(function (ok) {
                if (ok && typeof callback === 'function') {
                    callback();
                }
            });
            return;
        }
        if (typeof callback === 'function') {
            callback();
        }
    }

    function initModo() {
        var select = document.getElementById('vh-tiny-modo');
        if (!select) {
            return;
        }
        select.addEventListener('change', function () {
            var modo = select.value;
            rest('tiny/modo', 'POST', { modo: modo }).then(function () {
                window.location.reload();
            }).catch(function (err) {
                feedback(err.message, 'erro');
            });
        });
    }

    function initSecretFieldV3() {
        var input = document.getElementById('vh-tiny-client-secret');
        if (!input) {
            return;
        }
        input.value = '';
    }

    function initCredenciais() {
        var form = document.getElementById('vh-form-tiny-credenciais');
        if (!form) {
            return;
        }

        initSecretFieldV3();

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var secretInput = document.getElementById('vh-tiny-client-secret');
            var body = {
                client_id: document.getElementById('vh-tiny-client-id').value,
            };
            if (secretInput && secretInput.value.trim()) {
                body.client_secret = secretInput.value.trim();
            }
            rest('tiny/credenciais', 'POST', body).then(function () {
                feedback(I18N.credenciaisOk || 'Credenciais salvas.', 'sucesso');
                window.location.reload();
            }).catch(function (err) {
                feedback(err.message, 'erro');
            });
        });
    }

    function initTokenFieldV2() {
        var input = document.getElementById('vh-tiny-v2-token');
        if (!input) {
            return;
        }
        input.value = '';
    }

    function initTokenV2() {
        var form = document.getElementById('vh-form-tiny-v2');
        if (!form) {
            return;
        }

        initTokenFieldV2();

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var token = document.getElementById('vh-tiny-v2-token').value.trim();
            if (!token) {
                feedback(I18N.tokenObrigatorio || 'Informe o token para salvar.', 'erro');
                return;
            }
            rest('tiny/v2/token', 'POST', { token: token }).then(function () {
                feedback(I18N.tokenOk || 'Token salvo e conexão estabelecida.', 'sucesso');
                window.location.reload();
            }).catch(function (err) {
                feedback(err.message, 'erro');
            });
        });

        var btnTestar = document.getElementById('vh-tiny-v2-testar');
        if (btnTestar) {
            btnTestar.addEventListener('click', function () {
                btnTestar.disabled = true;
                var tokenInput = document.getElementById('vh-tiny-v2-token');
                var body = {};
                if (tokenInput && tokenInput.value.trim()) {
                    body.token = tokenInput.value.trim();
                }
                rest('tiny/v2/testar', 'POST', body).then(function (resp) {
                    var msg = (resp.dados && resp.dados.mensagem) || I18N.testeOk || 'Teste OK — salve o token para conectar.';
                    feedback(msg, 'sucesso');
                }).catch(function (err) {
                    feedback(err.message, 'erro');
                }).finally(function () {
                    btnTestar.disabled = false;
                });
            });
        }
    }

    function initConectar() {
        var btn = document.getElementById('vh-tiny-conectar');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            rest('tiny/auth-url').then(function (resp) {
                if (resp.dados && resp.dados.url) {
                    window.location.href = resp.dados.url;
                }
            }).catch(function (err) {
                feedback(err.message, 'erro');
            });
        });
    }

    function executarDesconectar() {
        rest('tiny/disconnect', 'POST').then(function () {
            feedback(I18N.desconectado || 'Desconectado.', 'sucesso');
            window.location.reload();
        }).catch(function (err) {
            feedback(err.message, 'erro');
        });
    }

    function initDesconectar() {
        ['vh-tiny-desconectar', 'vh-tiny-desconectar-v2'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (!btn) {
                return;
            }
            btn.addEventListener('click', function () {
                confirmarDesconectar(executarDesconectar);
            });
        });
    }

    function initLiberarTrava() {
        var btn = document.getElementById('vh-tiny-liberar-trava');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            if (!window.confirm(I18N.liberarTrava || 'Liberar a trava de empresa? A próxima conexão passa a valer como a empresa oficial da loja.')) {
                return;
            }
            rest('tiny/liberar-trava-conta', 'POST').then(function () {
                window.location.reload();
            }).catch(function (err) {
                feedback(err.message, 'erro');
            });
        });
    }

    function initRotacionarWebhook() {
        var btn = document.getElementById('vh-tiny-rotacionar-webhook');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            if (!window.confirm(I18N.rotacionarWebhook || 'Gerar um token novo? A URL e o token atuais param de funcionar até você atualizar o cadastro no Tiny.')) {
                return;
            }
            rest('tiny/webhook-token', 'POST').then(function () {
                window.location.reload();
            }).catch(function (err) {
                feedback(err.message, 'erro');
            });
        });
    }

    function initConfig() {
        var form = document.getElementById('vh-form-tiny-config');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var lojaInput = document.getElementById('vh-tiny-loja-id');
            var prefixoInput = document.getElementById('vh-tiny-sku-prefixo');
            var raizInput = document.getElementById('vh-tiny-categoria-raiz');
            var envioEl = document.getElementById('vh-tiny-permitir-envio');
            var catalogoEl = document.getElementById('vh-tiny-receber-catalogo');
            var estoquePrecoEl = document.getElementById('vh-tiny-receber-estoque-preco');
            rest('tiny/config', 'POST', {
                ativo: document.getElementById('vh-tiny-ativo').checked,
                sinc_auto: document.getElementById('vh-tiny-sinc-auto').checked,
                permitir_envio: envioEl ? envioEl.checked : true,
                receber_catalogo: catalogoEl ? catalogoEl.checked : false,
                receber_estoque_preco: estoquePrecoEl ? estoquePrecoEl.checked : false,
                loja_identificador: lojaInput ? lojaInput.value.trim() : '',
                sku_prefixo: prefixoInput ? prefixoInput.value.trim() : undefined,
                categoria_raiz: raizInput ? raizInput.value.trim() : undefined,
            }).then(function () {
                feedback(I18N.configOk || 'Configuração salva.', 'sucesso');
            }).catch(function (err) {
                feedback(err.message, 'erro');
            });
        });
    }

    function initSincronizar() {
        var btn = document.getElementById('vh-tiny-sincronizar');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            btn.disabled = true;
            rest('tiny/sincronizar', 'POST').then(function (resp) {
                var n = (resp.dados && resp.dados.processados) || 0;
                var pendentes = (resp.dados && resp.dados.pendentes_fila) || 0;
                var msg = I18N.sincOk || 'Sincronização enfileirada.';
                if (n > 0) {
                    msg = (I18N.sincProcessados || '%d tarefas processadas nesta execução. A fila continua no cron.').replace('%d', String(n));
                }
                if (pendentes > 0) {
                    msg += ' ' + (I18N.sincPendentesFila || 'Ainda restam %d tarefa(s) na fila.').replace('%d', String(pendentes));
                }
                feedback(msg, pendentes > 0 ? 'aviso' : 'sucesso');
                window.location.reload();
            }).catch(function (err) {
                feedback(err.message, 'erro');
            }).finally(function () {
                btn.disabled = false;
            });
        });
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    var UI = (APP.ui && typeof APP.ui === 'object') ? APP.ui : null;

    function ocultarEl(el) {
        if (UI && UI.ocultarEl) {
            UI.ocultarEl(el);
            return;
        }
        if (!el) {
            return;
        }
        el.hidden = true;
        el.classList.add('vh-ui-oculto');
        el.setAttribute('aria-hidden', 'true');
    }

    function mostrarEl(el) {
        if (UI && UI.mostrarEl) {
            UI.mostrarEl(el);
            return;
        }
        if (!el) {
            return;
        }
        el.hidden = false;
        el.classList.remove('vh-ui-oculto');
        el.removeAttribute('aria-hidden');
    }

    function esconderCarregamento(loadingId) {
        if (UI && UI.esconderSkeleton) {
            UI.esconderSkeleton(loadingId);
            return;
        }
        ocultarEl(document.getElementById(loadingId));
    }

    function mostrarErroCarregando(loadingId, mensagem) {
        if (UI && UI.skeletonErro) {
            UI.skeletonErro(loadingId, mensagem);
            return;
        }
        var loading = document.getElementById(loadingId);
        if (!loading) {
            return;
        }
        loading.classList.add('vh-skeleton-panel--erro');
        loading.setAttribute('aria-busy', 'false');
        loading.innerHTML =
            '<p class="vh-skeleton-panel__erro">' + escHtml(mensagem) + '</p>';
    }

    function btnCarregando(btn, ativo, textoSalvar) {
        if (!btn) {
            return;
        }
        if (ativo) {
            btn.dataset.paLabelOriginal = btn.textContent;
            btn.disabled = true;
            btn.classList.add('is-carregando');
            btn.textContent = I18N.salvando || 'Salvando…';
        } else {
            btn.disabled = false;
            btn.classList.remove('is-carregando');
            if (btn.dataset.paLabelOriginal) {
                btn.textContent = btn.dataset.paLabelOriginal;
            } else if (textoSalvar) {
                btn.textContent = textoSalvar;
            }
        }
    }

    function inicializarControles(container) {
        if (typeof window.paInitCustomSelects === 'function') {
            window.paInitCustomSelects(container || document);
        }
    }

    function syncSelect(select) {
        if (typeof window.paSyncCustomSelect === 'function') {
            window.paSyncCustomSelect(select);
        }
    }

    function statusBadge(status) {
        if (status === 'vinculado') {
            return '<span class="vh-status vh-status--completed">' + escHtml(I18N.statusVinculado || 'Vinculado') + '</span>';
        }
        if (status === 'ignorado') {
            return '<span class="vh-status vh-status--muted">' + escHtml(I18N.statusIgnorado || 'Ignorado') + '</span>';
        }
        return '<span class="vh-status vh-status--pendente">' + escHtml(I18N.statusPendente || 'Pendente') + '</span>';
    }

    function celulaSelect(classe, attrs, optionsHtml) {
        return '<td class="vh-tiny-map-celula-select">' +
            '<div class="vh-form-grupo vh-form-grupo--inline">' +
                '<select class="' + classe + '"' + attrs + '>' + optionsHtml + '</select>' +
            '</div>' +
        '</td>';
    }

    function limparErrosCategorias() {
        var banner = document.getElementById('vh-tiny-map-erros-banner');
        if (banner) {
            banner.hidden = true;
            banner.classList.add('vh-ui-oculto');
            banner.innerHTML = '';
        }
        document.querySelectorAll('.vh-tiny-map-erro-linha').forEach(function (el) {
            el.remove();
        });
        document.querySelectorAll('.vh-tiny-map-row--erro').forEach(function (el) {
            el.classList.remove('vh-tiny-map-row--erro');
        });
    }

    function aplicarErrosCategorias(erros) {
        limparErrosCategorias();
        if (!erros || !erros.length) {
            return;
        }

        var banner = document.getElementById('vh-tiny-map-erros-banner');
        if (banner) {
            banner.hidden = false;
            banner.classList.remove('vh-ui-oculto');
            banner.innerHTML =
                '<p class="vh-tiny-map-erros-banner__titulo">' +
                    escHtml(I18N.mapCatsErrosTitulo || 'Não foi possível salvar o mapeamento') +
                '</p>' +
                (I18N.mapCatsErrosSubtitulo
                    ? '<p class="vh-tiny-map-erros-banner__subtitulo">' + escHtml(I18N.mapCatsErrosSubtitulo) + '</p>'
                    : '') +
                '<ul class="vh-tiny-map-erros-banner__lista">' +
                erros.map(function (e) {
                    return '<li>' + escHtml(e.msg || '') + '</li>';
                }).join('') +
                '</ul>';
        }

        var body = document.getElementById('vh-tiny-map-cats-body');
        if (!body) {
            return;
        }

        erros.forEach(function (e) {
            if (!e.term_id) {
                return;
            }
            var row = body.querySelector('tr[data-term-id="' + String(e.term_id) + '"]');
            if (!row) {
                return;
            }
            row.classList.add('vh-tiny-map-row--erro');
            var errRow = document.createElement('tr');
            errRow.className = 'vh-tiny-map-erro-linha';
            errRow.setAttribute('data-erro-term-id', String(e.term_id));
            errRow.innerHTML =
                '<td colspan="5">' +
                    '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' +
                    escHtml(e.msg || '') +
                '</td>';
            row.insertAdjacentElement('afterend', errRow);
        });
    }

    function sugerirAcaoCategoria(cat, lojaById) {
        if (cat.status === 'vinculado') {
            return 'vincular';
        }
        var parent = lojaById[cat.parent_id];
        if (parent && parent.tiny_id) {
            return 'criar';
        }
        if (cat.status === 'ignorado') {
            return 'ignorar';
        }
        return 'ignorar';
    }

    function mensagemConflitoVinculo(nome, dono, tinyCat, cat) {
        var caminhoTiny = tinyCat.caminho || tinyCat.nome || '';
        if (cat.parent_id && Number(cat.parent_id) === Number(dono.term_id)) {
            return formatI18n(
                I18N.mapCatConflitoHierarquiaOk || '“%1$s”: Vincular só serve quando esta categoria é a mesma no Tiny — não para subcategorias. A hierarquia na loja já está correta (“%1$s” é filha de “%2$s”, que já está no Tiny). Troque a ação para “Criar no Tiny” e salve. Não use Vincular nem selecione “%3$s” do Tiny.',
                {
                    '%1$s': nome,
                    '%2$s': dono.nome,
                    '%3$s': caminhoTiny,
                }
            );
        }
        return formatI18n(
            I18N.mapCatConflito || '“%1$s”: Vincular não cria subcategoria no Tiny — só indica que esta categoria da loja é a mesma que já existe no ERP (1 categoria da loja = 1 categoria do Tiny). A categoria “%3$s” do Tiny já está ligada a “%2$s” na loja. Se você quer “%1$s” dentro de “%2$s” no Tiny: (1) em Minha Loja → Categorias, faça “%1$s” ser subcategoria (filha) de “%2$s”; (2) volte aqui e escolha a ação “Criar no Tiny” para “%1$s” — não use Vincular.',
            {
                '%1$s': nome,
                '%2$s': dono.nome,
                '%3$s': caminhoTiny,
            }
        );
    }

    function validarVinculosCliente(vinculos, loja, tinyList) {
        var lojaMap = {};
        var tinyDonos = {};
        var tinyMap = {};
        (loja || []).forEach(function (c) {
            lojaMap[c.term_id] = c;
            if (c.tiny_id) {
                tinyDonos[String(c.tiny_id)] = c;
            }
        });
        (tinyList || []).forEach(function (t) {
            tinyMap[String(t.id_tiny)] = t;
        });

        var erros = [];
        vinculos.forEach(function (v) {
            var cat = lojaMap[v.term_id] || {};
            var nome = cat.nome || ('#' + v.term_id);

            if (v.acao !== 'vincular') {
                return;
            }

            if (!v.tiny_id) {
                erros.push({
                    term_id: v.term_id,
                    msg: (I18N.mapCatSemTiny || '“%s”: você marcou Vincular, mas não escolheu a categoria do Tiny. Selecione a categoria do Tiny que é exatamente a mesma desta da loja (mesmo nome e mesmo nível — não escolha a categoria “mãe” se esta for subcategoria).').replace('%s', nome),
                });
                return;
            }

            var dono = tinyDonos[String(v.tiny_id)];
            var tinyCat = tinyMap[String(v.tiny_id)] || {};
            if (dono && dono.term_id !== v.term_id) {
                erros.push({
                    term_id: v.term_id,
                    msg: mensagemConflitoVinculo(nome, dono, tinyCat, cat),
                });
            }
        });

        return erros;
    }

    function renderCategorias(dados) {
        var body = document.getElementById('vh-tiny-map-cats-body');
        var panel = document.getElementById('vh-tiny-map-categorias');
        var pendentesEl = document.getElementById('vh-tiny-map-pendentes');
        if (!body || !panel) {
            return;
        }

        var loja = (dados && dados.categorias_loja) || [];
        var tiny = (dados && dados.categorias_tiny) || [];
        var pendentes = (dados && dados.pendentes) || 0;

        if (pendentesEl) {
            pendentesEl.textContent = pendentes > 0
                ? (I18N.catsPendentes || '%d categorias pendentes').replace('%d', String(pendentes))
                : (I18N.catsOk || 'Todas as categorias vinculadas');
        }

        /*
         * Mapa de categorias do Tiny já usadas por alguma categoria da loja.
         * Uma categoria do Tiny só pode estar vinculada a uma da loja, então
         * desabilitamos no dropdown as que já pertencem a outra linha — evita
         * o erro de unicidade antes de o usuário tentar salvar.
         */
        var lojaById = {};
        loja.forEach(function (c) {
            lojaById[c.term_id] = c;
        });

        var tinyUsados = {};
        loja.forEach(function (c) {
            if (c.tiny_id) {
                tinyUsados[String(c.tiny_id)] = { term_id: c.term_id, nome: c.nome, caminho: c.caminho };
            }
        });

        body.innerHTML = loja.map(function (cat) {
            var acaoInicial = sugerirAcaoCategoria(cat, lojaById);
            var parent = lojaById[cat.parent_id];
            var mostrarDicaCriar = parent && parent.tiny_id && cat.status !== 'vinculado';
            var selectDisabled = cat.status === 'vinculado' || acaoInicial === 'criar' || acaoInicial === 'ignorar' ? ' disabled' : '';
            var acaoDisabled = cat.status === 'vinculado' ? ' disabled' : '';
            var options = '<option value="">' + escHtml(I18N.selecionarTiny || '— Selecionar —') + '</option>';
            options += '<option value="criar"' + (acaoInicial === 'criar' ? ' selected' : '') + '>' + escHtml(I18N.criarNoTiny || 'Criar no Tiny') + '</option>';
            tiny.forEach(function (t) {
                var idStr = String(t.id_tiny);
                var ehDesteTermo = cat.tiny_id && Number(cat.tiny_id) === Number(t.id_tiny);
                var dono = tinyUsados[idStr];
                var usadoPorOutro = dono && dono.term_id !== cat.term_id;
                var selected = ehDesteTermo ? ' selected' : '';
                var disabled = usadoPorOutro ? ' disabled' : '';
                var sufixo = '';
                if (usadoPorOutro) {
                    sufixo = ' (' + (I18N.tinyVinculadaA || 'já ligada a “%s” na loja (indisponível para outra categoria)').replace('%s', dono.nome) + ')';
                }
                options += '<option value="' + escHtml(idStr) + '"' + selected + disabled + '>' +
                    escHtml((t.caminho || t.nome) + sufixo) + '</option>';
            });

            return '<tr data-term-id="' + escHtml(String(cat.term_id)) + '" data-cat-status="' + escHtml(cat.status || 'pendente') + '" data-cat-tiny-id="' + escHtml(cat.tiny_id ? String(cat.tiny_id) : '') + '">' +
                '<td><strong>' + escHtml(cat.nome) + '</strong>' +
                    (mostrarDicaCriar
                        ? '<div class="vh-tiny-map-dica-linha">' + escHtml(formatI18n(I18N.mapCatSugestaoCriar || 'Subcategoria de “%s” (já no Tiny) — use “Criar no Tiny”.', { '%s': parent.nome })) + '</div>'
                        : '') +
                '</td>' +
                '<td class="vh-tiny-map-caminho">' + escHtml(cat.caminho) + '</td>' +
                '<td class="vh-tiny-map-status">' + statusBadge(cat.status) + '</td>' +
                celulaSelect(
                    'vh-tiny-map-acao',
                    ' data-term-id="' + escHtml(String(cat.term_id)) + '"' + acaoDisabled,
                    '<option value="ignorar"' + (acaoInicial === 'ignorar' ? ' selected' : '') + '>' + escHtml(I18N.ignorar || 'Ignorar') + '</option>' +
                    '<option value="vincular"' + (acaoInicial === 'vincular' && cat.tiny_id ? ' selected' : '') + '>' + escHtml(I18N.vincular || 'Vincular') + '</option>' +
                    '<option value="criar"' + (acaoInicial === 'criar' && !cat.tiny_id ? ' selected' : '') + '>' + escHtml(I18N.criarNoTiny || 'Criar no Tiny') + '</option>'
                ) +
                celulaSelect(
                    'vh-tiny-map-tiny-id',
                    ' data-term-id="' + escHtml(String(cat.term_id)) + '"' +
                    ' data-vh-select-search' +
                    selectDisabled,
                    options
                ) +
            '</tr>';
        }).join('');

        body.querySelectorAll('.vh-tiny-map-acao').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var row = sel.closest('tr');
                if (!row) {
                    return;
                }
                limparErrosCategorias();
                var tinySel = row.querySelector('.vh-tiny-map-tiny-id');
                if (!tinySel) {
                    return;
                }
                if (sel.value === 'vincular') {
                    tinySel.disabled = false;
                } else if (sel.value === 'criar') {
                    tinySel.value = 'criar';
                    tinySel.disabled = true;
                } else {
                    tinySel.value = '';
                    tinySel.disabled = true;
                }
                tinySel.dispatchEvent(new Event('change', { bubbles: true }));
                syncSelect(tinySel);
            });
        });

        inicializarControles(panel);
        mostrarEl(panel);
    }

    function renderAtributos(dados) {
        var form = document.getElementById('vh-form-tiny-map-atributos');
        var panel = document.getElementById('vh-tiny-map-atributos');
        if (!form || !panel) {
            return;
        }

        var attrs = (dados && dados.atributos) || [];
        form.innerHTML = attrs.map(function (attr) {
            return '<div class="vh-form-grupo vh-tiny-map-attr-row">' +
                '<label for="vh-tiny-attr-' + escHtml(attr.taxonomy) + '">' +
                    escHtml(attr.nome_loja) +
                    ' <span class="vh-tiny-map-attr-meta">(' + escHtml(attr.taxonomy) + ' · ' + escHtml(String(attr.termos)) + ' ' + escHtml(I18N.termos || 'termos') + ')</span>' +
                '</label>' +
                '<input type="text" id="vh-tiny-attr-' + escHtml(attr.taxonomy) + '" name="' + escHtml(attr.taxonomy) + '" value="' + escHtml(attr.rotulo_tiny || attr.rotulo_padrao) + '" placeholder="' + escHtml(attr.rotulo_padrao) + '">' +
            '</div>';
        }).join('');

        mostrarEl(panel);
    }

    function renderCampos(dados) {
        var body = document.getElementById('vh-tiny-map-campos-body');
        var panel = document.getElementById('vh-tiny-map-campos');
        if (!body || !panel) {
            return;
        }

        var campos = (dados && dados.campos_produto) || [];
        body.innerHTML = campos.map(function (campo) {
            return '<tr>' +
                '<td>' + escHtml(campo.campo_loja) + '</td>' +
                '<td><code>' + escHtml(campo.campo_tiny) + '</code></td>' +
                '<td class="vh-tiny-map-nota">' + escHtml(campo.nota || '—') + '</td>' +
            '</tr>';
        }).join('');

        mostrarEl(panel);
    }

    function coletarVinculosCategorias() {
        var vinculos = [];
        document.querySelectorAll('#vh-tiny-map-cats-body tr[data-term-id]').forEach(function (row) {
            var termId = parseInt(row.getAttribute('data-term-id'), 10);
            var statusAtual = row.getAttribute('data-cat-status') || '';
            var tinyAtual = row.getAttribute('data-cat-tiny-id') || '';
            var acaoSel = row.querySelector('.vh-tiny-map-acao');
            var tinySel = row.querySelector('.vh-tiny-map-tiny-id');
            if (!termId || !acaoSel) {
                return;
            }
            var acao = acaoSel.value;

            if (statusAtual === 'vinculado' && acao === 'vincular') {
                return;
            }

            if (statusAtual === 'ignorado' && acao === 'ignorar') {
                return;
            }

            var item = { term_id: termId, acao: acao };
            if (acao === 'vincular' && tinySel && tinySel.value && tinySel.value !== 'criar') {
                if (statusAtual === 'vinculado' && String(tinySel.value) === String(tinyAtual)) {
                    return;
                }
                item.tiny_id = parseInt(tinySel.value, 10);
            }
            vinculos.push(item);
        });
        return vinculos;
    }

    function confirmarSalvarCategorias(vinculos) {
        var criar = vinculos.filter(function (v) { return v.acao === 'criar'; });
        if (!criar.length) {
            return Promise.resolve(true);
        }
        var msg = (I18N.confirmarCriarCats || 'Serão criadas %d categoria(s) novas no Tiny ERP. A hierarquia segue a da loja: uma subcategoria aqui vira subcategoria de quem já estiver mapeada no Tiny. Use isto quando a categoria ainda não existe no ERP. Se ela já existir lá com o mesmo nome, prefira Vincular. Continuar?')
            .replace('%d', String(criar.length));
        if (typeof APP.confirmar === 'function') {
            return APP.confirmar({
                titulo: I18N.confirmarCriarCatsTitulo || 'Criar categorias no Tiny?',
                mensagem: msg,
                confirmar: I18N.confirmarCriarCatsBtn || 'Criar no Tiny',
                cancelar: I18N.cancelar || 'Cancelar',
                tipo: 'aviso',
            });
        }
        return Promise.resolve(window.confirm(msg));
    }

    function initMapeamento() {
        if (!document.querySelector('.vh-tiny-mapeamento-wrap')) {
            return;
        }

        var ultimoMapeamento = null;

        rest('tiny/mapeamento').then(function (resp) {
            var dados = resp.dados || resp;
            ultimoMapeamento = dados;
            renderCategorias(dados);
            renderAtributos(dados);
            renderCampos(dados);
            esconderCarregamento('vh-tiny-map-cats-loading');
            esconderCarregamento('vh-tiny-map-attrs-loading');
            esconderCarregamento('vh-tiny-map-campos-loading');
        }).catch(function (err) {
            ['vh-tiny-map-cats-loading', 'vh-tiny-map-attrs-loading', 'vh-tiny-map-campos-loading'].forEach(function (id) {
                mostrarErroCarregando(id, err.message);
            });
            feedback(err.message, 'erro');
        });

        var btnCats = document.getElementById('vh-tiny-map-salvar-cats');
        if (btnCats) {
            btnCats.addEventListener('click', function () {
                var vinculos = coletarVinculosCategorias();
                if (!vinculos.length) {
                    feedback(I18N.mapCatsSemAlteracao || 'Nenhuma alteração para salvar.', 'aviso');
                    return;
                }

                var loja = (ultimoMapeamento && ultimoMapeamento.categorias_loja) || [];
                var errosCliente = validarVinculosCliente(
                    vinculos,
                    loja,
                    (ultimoMapeamento && ultimoMapeamento.categorias_tiny) || []
                );
                if (errosCliente.length) {
                    feedback(errosCliente[0].msg, 'erro');
                    if (ultimoMapeamento) {
                        renderCategorias(ultimoMapeamento);
                    }
                    aplicarErrosCategorias(errosCliente);
                    return;
                }

                confirmarSalvarCategorias(vinculos).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    btnCarregando(btnCats, true);
                    limparErrosCategorias();
                    rest('tiny/mapeamento/categorias', 'POST', { vinculos: vinculos }).then(function (resp) {
                        var dados = resp.dados || resp;
                        ultimoMapeamento = dados;
                        var res = dados.resultados || {};
                        var erros = res.erros || [];
                        var totalOk = (res.vinculados || 0) + (res.criados || 0) + (res.ignorados || 0);
                        var msg = I18N.mapCatsOk || 'Categorias salvas.';
                        if (totalOk > 0) {
                            msg = formatI18n(I18N.mapCatsResumo || '%1$d vinculadas, %2$d criadas, %3$d ignoradas.', {
                                '%1$d': String(res.vinculados || 0),
                                '%2$d': String(res.criados || 0),
                                '%3$d': String(res.ignorados || 0),
                            });
                        }
                        renderCategorias(dados);
                        if (erros.length) {
                            aplicarErrosCategorias(erros);
                            var msgErros = erros.map(function (e) { return e.msg; }).join(' ');
                            if (totalOk > 0) {
                                feedback(msg + ' ' + msgErros, 'aviso');
                            } else {
                                feedback(msgErros, 'erro');
                            }
                        } else {
                            feedback(msg, 'sucesso');
                        }
                    }).catch(function (err) {
                        feedback(err.message, 'erro');
                    }).finally(function () {
                        btnCarregando(btnCats, false, I18N.salvarCats || 'Salvar categorias');
                    });
                });
            });
        }

        var formAttr = document.getElementById('vh-form-tiny-map-atributos');
        var btnAttrs = document.getElementById('vh-tiny-map-salvar-attrs');
        if (formAttr) {
            formAttr.addEventListener('submit', function (e) {
                e.preventDefault();
                btnCarregando(btnAttrs, true);
                var mapa = {};
                formAttr.querySelectorAll('input[name]').forEach(function (input) {
                    mapa[input.name] = input.value.trim();
                });
                rest('tiny/mapeamento/atributos', 'POST', { map_atributos: mapa }).then(function (resp) {
                    feedback(I18N.mapAttrOk || 'Atributos salvos.', 'sucesso');
                    var dados = resp.dados || resp;
                    renderAtributos({ atributos: dados.atributos });
                }).catch(function (err) {
                    feedback(err.message, 'erro');
                }).finally(function () {
                    btnCarregando(btnAttrs, false, I18N.salvarAttrs || 'Salvar atributos');
                });
            });
        }
    }

    function initLogs() {
        var wrap = document.querySelector('.vh-tiny-logs-wrap');
        if (!wrap) {
            return;
        }

        var UI = (APP.ui && typeof APP.ui === 'object') ? APP.ui : null;
        var aba = 'eventos';
        var pagina = 1;
        var buscaTimer = null;

        var elResumo = document.getElementById('vh-tiny-logs-resumo');
        var elTabelaWrap = document.getElementById('vh-tiny-logs-tabela-wrap');
        var elThead = document.getElementById('vh-tiny-logs-thead');
        var elTbody = document.getElementById('vh-tiny-logs-tbody');
        var elVazio = document.getElementById('vh-tiny-logs-vazio');
        var elPag = document.getElementById('vh-tiny-logs-paginacao');
        var inputBusca = document.getElementById('vh-tiny-logs-busca');
        var selNivel = document.getElementById('vh-tiny-logs-filtro-nivel');
        var selOrigem = document.getElementById('vh-tiny-logs-filtro-origem');
        var selStatus = document.getElementById('vh-tiny-logs-filtro-status');
        var selTipo = document.getElementById('vh-tiny-logs-filtro-tipo');
        var btnAtualizar = document.getElementById('vh-tiny-logs-atualizar');
        var tabs = wrap.querySelectorAll('[data-vh-logs-aba]');
        var filtrosEventos = wrap.querySelectorAll('.vh-tiny-logs-filtro--eventos');
        var filtrosFila = wrap.querySelectorAll('.vh-tiny-logs-filtro--fila');

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = String(s || '');
            return d.innerHTML;
        }

        function toggleFiltros() {
            filtrosEventos.forEach(function (el) {
                if ('fila' === aba) {
                    el.hidden = true;
                    el.classList.add('vh-ui-oculto');
                } else {
                    el.hidden = false;
                    el.classList.remove('vh-ui-oculto');
                }
            });
            filtrosFila.forEach(function (el) {
                if ('fila' === aba) {
                    el.hidden = false;
                    el.classList.remove('vh-ui-oculto');
                } else {
                    el.hidden = true;
                    el.classList.add('vh-ui-oculto');
                }
            });
        }

        function renderResumo(resumo) {
            if (!elResumo || !resumo) {
                return;
            }
            var lc = resumo.log_contagem || {};
            var fc = resumo.fila_contagem || {};
            elResumo.innerHTML =
                '<div class="vh-tiny-logs-card vh-tiny-logs-card--erro">' +
                    '<span class="vh-tiny-logs-card__valor">' + escHtml(String(lc.error || 0)) + '</span>' +
                    '<span class="vh-tiny-logs-card__label">' + escHtml(I18N.logsErros || 'Erros') + '</span>' +
                '</div>' +
                '<div class="vh-tiny-logs-card vh-tiny-logs-card--aviso">' +
                    '<span class="vh-tiny-logs-card__valor">' + escHtml(String(lc.warning || 0)) + '</span>' +
                    '<span class="vh-tiny-logs-card__label">' + escHtml(I18N.logsAvisos || 'Avisos') + '</span>' +
                '</div>' +
                '<div class="vh-tiny-logs-card">' +
                    '<span class="vh-tiny-logs-card__valor">' + escHtml(String((fc.pending || 0) + (fc.processing || 0))) + '</span>' +
                    '<span class="vh-tiny-logs-card__label">' + escHtml(I18N.logsFilaPend || 'Fila pendente') + '</span>' +
                '</div>' +
                '<div class="vh-tiny-logs-card vh-tiny-logs-card--ok">' +
                    '<span class="vh-tiny-logs-card__valor">' + escHtml(String(fc.done || 0)) + '</span>' +
                    '<span class="vh-tiny-logs-card__label">' + escHtml(I18N.logsFilaOk || 'Jobs concluídos') + '</span>' +
                '</div>';
        }

        function renderContexto(ctx) {
            if (!ctx || !Object.keys(ctx).length) {
                return '';
            }
            return '<details class="vh-tiny-log-contexto"><summary>' +
                escHtml(I18N.logsDetalhes || 'Detalhes') +
                '</summary><pre>' + escHtml(JSON.stringify(ctx, null, 2)) + '</pre></details>';
        }

        function renderEventos(itens) {
            elThead.innerHTML = '<tr>' +
                '<th>' + escHtml(I18N.logsColData || 'Data') + '</th>' +
                '<th>' + escHtml(I18N.logsColNivel || 'Nível') + '</th>' +
                '<th>' + escHtml(I18N.logsColOrigem || 'Origem') + '</th>' +
                '<th>' + escHtml(I18N.logsColMsg || 'Mensagem') + '</th>' +
                '</tr>';

            if (!itens.length) {
                elTbody.innerHTML = '';
                elVazio.hidden = false;
                elVazio.classList.remove('vh-ui-oculto');
                return;
            }
            elVazio.hidden = true;
            elVazio.classList.add('vh-ui-oculto');

            elTbody.innerHTML = itens.map(function (item) {
                return '<tr>' +
                    '<td>' + escHtml(item.criado_em) + '</td>' +
                    '<td><span class="vh-tiny-log-nivel vh-tiny-log-nivel--' + escHtml(item.nivel) + '">' + escHtml(item.nivel) + '</span></td>' +
                    '<td><span class="vh-tiny-log-origem">' + escHtml(item.origem) + '</span>' +
                        (item.referencia ? '<br><code>' + escHtml(item.referencia) + '</code>' : '') +
                    '</td>' +
                    '<td class="vh-tiny-log-msg">' + escHtml(item.mensagem) + renderContexto(item.contexto) + '</td>' +
                    '</tr>';
            }).join('');
        }

        function renderFila(itens) {
            elThead.innerHTML = '<tr>' +
                '<th>' + escHtml(I18N.logsColTipo || 'Tipo') + '</th>' +
                '<th>' + escHtml(I18N.logsColRef || 'Referência') + '</th>' +
                '<th>' + escHtml(I18N.logsColStatus || 'Status') + '</th>' +
                '<th>' + escHtml(I18N.logsColData || 'Data') + '</th>' +
                '</tr>';

            if (!itens.length) {
                elTbody.innerHTML = '';
                elVazio.hidden = false;
                elVazio.classList.remove('vh-ui-oculto');
                return;
            }
            elVazio.hidden = true;
            elVazio.classList.add('vh-ui-oculto');

            elTbody.innerHTML = itens.map(function (job) {
                var erro = job.erro
                    ? '<br><small class="vh-tiny-erro">' + escHtml(job.erro) + '</small>'
                    : '';
                var tent = job.tentativas > 0
                    ? '<br><small>' + escHtml((I18N.logsTentativas || 'Tentativas') + ': ' + job.tentativas) + '</small>'
                    : '';
                return '<tr>' +
                    '<td><code>' + escHtml(job.tipo) + '</code></td>' +
                    '<td>' + escHtml(job.referencia) + '</td>' +
                    '<td><span class="vh-tiny-badge vh-tiny-badge--' + escHtml(job.status) + '">' + escHtml(job.status) + '</span>' +
                        erro + tent +
                    '</td>' +
                    '<td>' + escHtml(job.criado_em) +
                        (job.processado_em ? '<br><small>' + escHtml(job.processado_em) + '</small>' : '') +
                    '</td>' +
                    '</tr>';
            }).join('');
        }

        function renderPaginacao(total, paginas, pagAtual) {
            if (!elPag || paginas <= 1) {
                if (elPag) {
                    elPag.hidden = true;
                    elPag.classList.add('vh-ui-oculto');
                }
                return;
            }
            elPag.hidden = false;
            elPag.classList.remove('vh-ui-oculto');

            var html = '';
            if (pagAtual > 1) {
                html += '<button type="button" class="vh-btn vh-btn--secundario vh-btn--sm" data-vh-logs-pag="' + (pagAtual - 1) + '">←</button>';
            }
            html += '<span class="vh-tiny-logs-paginacao__info">' +
                escHtml((I18N.logsPagina || 'Página %1 de %2').replace('%1', String(pagAtual)).replace('%2', String(paginas))) +
                ' · ' + escHtml(String(total)) + ' ' + escHtml(I18N.logsRegistros || 'registros') +
                '</span>';
            if (pagAtual < paginas) {
                html += '<button type="button" class="vh-btn vh-btn--secundario vh-btn--sm" data-vh-logs-pag="' + (pagAtual + 1) + '">→</button>';
            }
            elPag.innerHTML = html;

            elPag.querySelectorAll('[data-vh-logs-pag]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    pagina = parseInt(btn.getAttribute('data-vh-logs-pag'), 10) || 1;
                    carregar();
                });
            });
        }

        function montarQuery() {
            var q = '?aba=' + encodeURIComponent(aba) + '&pagina=' + pagina;
            var busca = inputBusca ? inputBusca.value.trim() : '';
            if (busca) {
                q += '&busca=' + encodeURIComponent(busca);
            }
            if ('fila' === aba) {
                if (selStatus && selStatus.value) {
                    q += '&status=' + encodeURIComponent(selStatus.value);
                }
                if (selTipo && selTipo.value) {
                    q += '&tipo=' + encodeURIComponent(selTipo.value);
                }
            } else {
                if (selNivel && selNivel.value) {
                    q += '&nivel=' + encodeURIComponent(selNivel.value);
                }
                if (selOrigem && selOrigem.value) {
                    q += '&origem=' + encodeURIComponent(selOrigem.value);
                }
            }
            return q;
        }

        function carregar() {
            rest('tiny/logs' + montarQuery()).then(function (resp) {
                var dados = resp.dados || resp;
                renderResumo(dados.resumo);
                if ('fila' === aba) {
                    renderFila(dados.fila || []);
                } else {
                    renderEventos(dados.eventos || []);
                }
                renderPaginacao(dados.total || 0, dados.paginas || 1, dados.pagina || 1);
                if (UI) {
                    UI.concluirCarregamentoAsync('vh-tiny-logs-tabela-loading', 'vh-tiny-logs-tabela-wrap');
                } else if (elTabelaWrap) {
                    elTabelaWrap.hidden = false;
                    elTabelaWrap.classList.remove('vh-ui-oculto');
                }
            }).catch(function (err) {
                if (UI) {
                    UI.skeletonErro('vh-tiny-logs-tabela-loading', err.message);
                }
                feedback(err.message, 'erro');
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                aba = tab.getAttribute('data-vh-logs-aba') || 'eventos';
                pagina = 1;
                tabs.forEach(function (t) {
                    var ativo = t === tab;
                    t.classList.toggle('is-ativo', ativo);
                    t.setAttribute('aria-selected', ativo ? 'true' : 'false');
                });
                toggleFiltros();
                carregar();
            });
        });

        [selNivel, selOrigem, selStatus, selTipo].forEach(function (sel) {
            if (sel) {
                sel.addEventListener('change', function () {
                    pagina = 1;
                    carregar();
                });
            }
        });

        if (inputBusca) {
            inputBusca.addEventListener('input', function () {
                clearTimeout(buscaTimer);
                buscaTimer = setTimeout(function () {
                    pagina = 1;
                    carregar();
                }, 350);
            });
        }

        if (btnAtualizar) {
            btnAtualizar.addEventListener('click', function () {
                carregar();
            });
        }

        toggleFiltros();
        carregar();
    }

    function boot() {
        initModo();
        initCredenciais();
        initTokenV2();
        initConectar();
        initDesconectar();
        initLiberarTrava();
        initRotacionarWebhook();
        initConfig();
        initSincronizar();
        initMapeamento();
        initLogs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
