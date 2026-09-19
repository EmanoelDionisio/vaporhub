/**
 * SEO — OAuth Google, métricas e PageSpeed (carrega só em /minha-loja/seo).
 *
 * @package VaporHubLoja
 */
(function () {
    'use strict';

    var APP = window.paLojaApp || {};
    var SEO = window.paSeoApp || {};
    var I18N = SEO.i18n || {};

    function rest(path, method) {
        return fetch(APP.restUrl + path.replace(/^\//, ''), {
            method: method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': APP.nonce,
            },
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok) {
                    throw new Error((j && j.message) || APP.i18n.erroGenerico);
                }
                return j;
            });
        });
    }

    function feedback(msg, tipo) {
        if (window.paLojaApp && typeof window.paLojaApp.feedback === 'function') {
            window.paLojaApp.feedback(msg, tipo);
            return;
        }
    }

    function fmtNum(n) {
        if (n === null || n === undefined || n === '') {
            return '—';
        }
        return Number(n).toLocaleString('pt-BR');
    }

    function renderMetricas(dados) {
        var el = document.getElementById('vh-seo-metricas-grid');
        if (!el) {
            return;
        }

        var gsc = dados.search_console || {};
        var ga = dados.analytics || {};

        if (gsc.erro && ga.erro) {
            el.innerHTML = '<p class="vh-form-descricao">' + gsc.erro + '</p>';
            return;
        }

        var cards = [];

        if (!gsc.erro) {
            cards.push(
                card('Search Console', [
                    ['Cliques', fmtNum(gsc.cliques)],
                    ['Impressões', fmtNum(gsc.impressoes)],
                    ['CTR', gsc.ctr != null ? gsc.ctr + '%' : '—'],
                    ['Posição média', gsc.posicao != null ? gsc.posicao : '—'],
                ], gsc.periodo)
            );
        } else {
            cards.push('<div class="vh-seo-metrica-card vh-seo-metrica-card--erro"><strong>Search Console</strong><p>' + gsc.erro + '</p></div>');
        }

        if (!ga.erro) {
            cards.push(
                card('Google Analytics 4', [
                    ['Sessões', fmtNum(ga.sessoes)],
                    ['Usuários', fmtNum(ga.usuarios)],
                    ['Pageviews', fmtNum(ga.pageviews)],
                ], ga.periodo)
            );
        } else {
            cards.push('<div class="vh-seo-metrica-card vh-seo-metrica-card--erro"><strong>Analytics</strong><p>' + ga.erro + '</p></div>');
        }

        el.innerHTML = cards.join('');
    }

    function card(titulo, linhas, periodo) {
        var rows = linhas.map(function (l) {
            return '<div class="vh-seo-metrica-linha"><span>' + l[0] + '</span><strong>' + l[1] + '</strong></div>';
        }).join('');
        return '<div class="vh-seo-metrica-card"><strong>' + titulo + '</strong>' + rows +
            (periodo ? '<small class="vh-seo-metrica-periodo">' + periodo + '</small>' : '') + '</div>';
    }

    function renderPageSpeed(d) {
        var el = document.getElementById('vh-seo-pagespeed-result');
        if (!el || !d) {
            return;
        }
        el.innerHTML =
            '<div class="vh-kpi-grid vh-kpi-grid--4">' +
            kpi('Performance', d.score != null ? d.score + '/100' : '—') +
            kpi('LCP', d.lcp_ms != null ? Math.round(d.lcp_ms) + ' ms' : '—') +
            kpi('CLS', d.cls != null ? d.cls : '—') +
            kpi('INP', d.inp_ms != null ? Math.round(d.inp_ms) + ' ms' : '—') +
            '</div>' +
            '<p class="vh-form-descricao">' + (d.strategy || 'mobile') + ' · ' + (d.url || '') + '</p>';
    }

    function kpi(label, val) {
        return '<div class="vh-kpi-card"><span class="vh-kpi-label">' + label + '</span><span class="vh-kpi-valor">' + val + '</span></div>';
    }

    function atualizarStatusUI(st) {
        var badge = document.getElementById('vh-seo-google-badge');
        var email = document.getElementById('vh-seo-google-email');
        var btnConnect = document.getElementById('vh-seo-google-connect');
        var btnDisconnect = document.getElementById('vh-seo-google-disconnect');
        var metricasWrap = document.getElementById('vh-seo-metricas-wrap');

        if (!badge) {
            return;
        }

        if (st.conectado) {
            badge.textContent = 'Conectado';
            badge.className = 'vh-seo-badge vh-seo-badge--ok';
            if (email) {
                email.textContent = st.email || '';
            }
            if (btnConnect) {
                btnConnect.hidden = true;
            }
            if (btnDisconnect) {
                btnDisconnect.hidden = false;
            }
            if (metricasWrap) {
                metricasWrap.hidden = false;
            }
            carregarMetricas();
        } else {
            badge.textContent = 'Desconectado';
            badge.className = 'vh-seo-badge';
            if (email) {
                email.textContent = '';
            }
            if (btnConnect) {
                btnConnect.hidden = false;
            }
            if (btnDisconnect) {
                btnDisconnect.hidden = true;
            }
            if (metricasWrap) {
                metricasWrap.hidden = true;
            }
        }
    }

    function carregarMetricas() {
        var grid = document.getElementById('vh-seo-metricas-grid');
        if (grid) {
            grid.innerHTML = '<p class="vh-form-descricao">' + (I18N.carregando || 'Carregando…') + '</p>';
        }
        rest('seo/metricas').then(function (j) {
            renderMetricas(j.dados || {});
        }).catch(function (err) {
            if (grid) {
                grid.innerHTML = '<p class="vh-form-descricao">' + err.message + '</p>';
            }
        });
    }

    function initOAuthFeedback() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('google') === 'conectado') {
            feedback(I18N.conectadoOk || 'Conectado.', 'sucesso');
            window.history.replaceState({}, '', window.location.pathname);
        }
        if (params.get('google') === 'erro') {
            feedback(decodeURIComponent(params.get('google_msg') || 'Erro OAuth'), 'erro');
            window.history.replaceState({}, '', window.location.pathname);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initOAuthFeedback();

        rest('seo/google/status').then(function (j) {
            atualizarStatusUI(j.dados || {});
        }).catch(function () { /* silencioso */ });

        var btnConnect = document.getElementById('vh-seo-google-connect');
        if (btnConnect) {
            btnConnect.addEventListener('click', function () {
                btnConnect.disabled = true;
                rest('seo/google/auth-url').then(function (j) {
                    if (j.dados && j.dados.url) {
                        window.location.href = j.dados.url;
                    }
                }).catch(function (err) {
                    feedback(err.message, 'erro');
                    btnConnect.disabled = false;
                });
            });
        }

        var btnDisconnect = document.getElementById('vh-seo-google-disconnect');
        if (btnDisconnect) {
            btnDisconnect.addEventListener('click', function () {
                if (window.paLojaApp && window.paLojaApp.confirmar) {
                    window.paLojaApp.confirmar({
                        titulo: I18N.desconectar || 'Desconectar',
                        mensagem: I18N.desconectarConfirm || 'Desconectar?',
                        confirmar: I18N.desconectar || 'Desconectar',
                        tipo: 'perigo',
                    }).then(function (ok) {
                        if (!ok) {
                            return;
                        }
                        rest('seo/google/disconnect', 'POST').then(function (j) {
                            atualizarStatusUI(j.dados || {});
                            feedback(APP.i18n.sucessoSalvar || APP.i18n.sucesso, 'sucesso');
                        }).catch(function () {
                            feedback(APP.i18n.erroSalvar || APP.i18n.erroGenerico, 'erro');
                        });
                    });
                    return;
                }
                rest('seo/google/disconnect', 'POST').then(function (j) {
                    atualizarStatusUI(j.dados || {});
                });
            });
        }

        var btnPsi = document.getElementById('vh-seo-pagespeed-run');
        if (btnPsi) {
            btnPsi.addEventListener('click', function () {
                btnPsi.disabled = true;
                rest('seo/pagespeed?forcar=1').then(function (j) {
                    renderPageSpeed(j.dados);
                }).catch(function (err) {
                    feedback(err.message, 'erro');
                }).finally(function () {
                    btnPsi.disabled = false;
                });
            });
        }
    });
})();
