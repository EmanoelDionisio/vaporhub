/**
 * Crop + upload otimizado — Minha Loja.
 *
 * @package VaporHubLoja
 */

(function () {
    'use strict';

    var CFG = window.paMediaCropConfig || {};
    var I18N = CFG.i18n || {};
    var PROFILES = CFG.profiles || {};

    var estado = {
        gatilho: null,
        perfil: '',
        valueType: 'url',
        galeria: false,
        cropper: null,
        objectUrl: null,
        arquivoAtual: null,
    };

    var elOrigem, elEditor, elFileInput, elCropImg;

    function qs(id) {
        return document.getElementById(id);
    }

    function perfilAtual() {
        return PROFILES[estado.perfil] || null;
    }

    /** Formato de exportação: logo usa PNG (transparência); demais perfis usam JPEG. */
    function formatoExportacao(perfil) {
        if (perfil && perfil.transparente) {
            return { mime: 'image/png', ext: 'png' };
        }
        return { mime: 'image/jpeg', ext: 'jpg', qualidade: 0.92 };
    }

    function limparObjectUrl() {
        if (estado.objectUrl) {
            URL.revokeObjectURL(estado.objectUrl);
            estado.objectUrl = null;
        }
    }

    function destruirCropper() {
        if (estado.cropper) {
            estado.cropper.destroy();
            estado.cropper = null;
        }
    }

    function fecharModais() {
        if (elOrigem) {
            elOrigem.hidden = true;
            elOrigem.setAttribute('aria-hidden', 'true');
        }
        if (elEditor) {
            elEditor.hidden = true;
            elEditor.setAttribute('aria-hidden', 'true');
        }
        destruirCropper();
        limparObjectUrl();
        estado.arquivoAtual = null;
        estado.gatilho = null;
    }

    function abrirOrigem() {
        if (!elOrigem) return;
        qs('vh-crop-origem-titulo').textContent = I18N.origemTitulo || 'Adicionar imagem';
        qs('vh-crop-origem-descricao').textContent = I18N.origemDescricao || '';
        qs('vh-crop-origem-enviar-texto').textContent = I18N.enviarNova || 'Enviar nova imagem';
        qs('vh-crop-origem-biblioteca-texto').textContent = I18N.biblioteca || 'Escolher da biblioteca';
        qs('vh-crop-origem-cancelar').textContent = I18N.cancelar || 'Cancelar';
        elOrigem.hidden = false;
        elOrigem.setAttribute('aria-hidden', 'false');
    }

    function abrirEditor(arquivo) {
        var perfil = perfilAtual();
        if (!perfil || !elEditor || !elCropImg) return;

        estado.arquivoAtual = arquivo;
        limparObjectUrl();
        destruirCropper();

        estado.objectUrl = URL.createObjectURL(arquivo);
        elCropImg.src = estado.objectUrl;

        qs('vh-crop-editor-titulo').textContent = I18N.titulo || 'Ajustar imagem';
        qs('vh-crop-editor-dica').textContent = I18N.arraste || '';
        qs('vh-crop-editor-perfil').textContent = perfil.label || (perfil.width + '×' + perfil.height);
        qs('vh-crop-editor-cancelar').textContent = I18N.cancelar || 'Cancelar';
        qs('vh-crop-editor-aplicar').textContent = I18N.aplicar || 'Usar imagem';

        if (elOrigem) {
            elOrigem.hidden = true;
            elOrigem.setAttribute('aria-hidden', 'true');
        }

        elEditor.hidden = false;
        elEditor.setAttribute('aria-hidden', 'false');

        elCropImg.onload = function () {
            destruirCropper();
            if (!window.Cropper) return;
            estado.cropper = new window.Cropper(elCropImg, {
                aspectRatio: perfil.width / perfil.height,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                responsive: true,
                background: false,
            });
        };
    }

    function lerGatilho(gatilho) {
        estado.gatilho = gatilho;
        estado.perfil = gatilho.getAttribute('data-crop') || '';
        estado.valueType = gatilho.getAttribute('data-value-type') || 'url';
        estado.galeria = gatilho.getAttribute('data-galeria') === '1';
    }

    function aplicarResultado(dados) {
        var gatilho = estado.gatilho;
        if (!gatilho || !dados) return;

        var targetSel = gatilho.getAttribute('data-target');
        var previewSel = gatilho.getAttribute('data-preview');
        var input = targetSel ? document.querySelector(targetSel) : null;
        var preview = previewSel ? document.querySelector(previewSel) : null;
        var url = dados.url || '';
        var id = dados.id || 0;

        if (input) {
            if (estado.valueType === 'id') {
                if (estado.galeria) {
                    var atual = input.value ? input.value.split(',').filter(Boolean) : [];
                    atual.push(String(id));
                    input.value = atual.join(',');
                } else {
                    input.value = String(id);
                }
            } else {
                input.value = url;
            }
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (preview) {
            if (estado.galeria) {
                var img = document.createElement('img');
                img.src = url;
                img.alt = '';
                img.width = 56;
                img.height = 56;
                img.style.borderRadius = '6px';
                img.style.objectFit = 'cover';
                preview.appendChild(img);
            } else {
                preview.innerHTML = '<img src="' + url + '" alt="" />';
            }
        }
    }

    function uploadBlob(blob, nomeArquivo) {
        var perfil = perfilAtual();
        if (!perfil) {
            return Promise.reject(new Error('perfil'));
        }

        var formData = new FormData();
        formData.append('file', blob, nomeArquivo || 'imagem.jpg');
        formData.append('perfil', estado.perfil);

        return fetch(CFG.restUrl || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': CFG.nonce || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok || !json || !json.sucesso) {
                    var msg = (json && json.message) ? json.message : (I18N.erroUpload || 'Erro');
                    throw new Error(msg);
                }
                return json.dados || {};
            });
        });
    }

    function aplicarCrop() {
        var perfil = perfilAtual();
        if (!estado.cropper || !perfil) return;

        var btn = qs('vh-crop-editor-aplicar');
        var textoOriginal = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = I18N.enviando || 'Enviando…';
        }

        var canvas = estado.cropper.getCroppedCanvas({
            width: perfil.width,
            height: perfil.height,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!canvas) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = textoOriginal;
            }
            return;
        }

        var fmt = formatoExportacao(perfil);
        var qualidade = fmt.qualidade !== undefined ? fmt.qualidade : undefined;

        canvas.toBlob(function (blob) {
            if (!blob) {
                alert(I18N.erroUpload || 'Erro');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = textoOriginal;
                }
                return;
            }

            var base = (estado.arquivoAtual && estado.arquivoAtual.name)
                ? estado.arquivoAtual.name.replace(/\.[^.]+$/, '')
                : 'imagem';
            var nome = base + '.' + fmt.ext;

            uploadBlob(blob, nome)
                .then(function (dados) {
                    aplicarResultado(dados);
                    fecharModais();
                })
                .catch(function (err) {
                    alert(err.message || I18N.erroUpload || 'Erro');
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = textoOriginal;
                    }
                });
        }, fmt.mime, qualidade);
    }

    function abrirBiblioteca() {
        if (!window.wp || !window.wp.media) {
            alert(I18N.erroMidia || 'Biblioteca indisponível.');
            return;
        }

        var gatilho = estado.gatilho;
        if (!gatilho) return;

        var targetSel = gatilho.getAttribute('data-target');
        var previewSel = gatilho.getAttribute('data-preview');
        var input = targetSel ? document.querySelector(targetSel) : null;
        var preview = previewSel ? document.querySelector(previewSel) : null;
        var multiplo = estado.galeria;

        var frame = window.wp.media({
            title: I18N.biblioteca || 'Biblioteca',
            button: { text: I18N.aplicar || 'Usar imagem' },
            multiple: multiplo,
            library: { type: 'image' },
        });

        frame.on('select', function () {
            var selecao = frame.state().get('selection');
            if (multiplo) {
                var ids = [];
                var html = '';
                selecao.each(function (att) {
                    var json = att.toJSON();
                    ids.push(String(json.id));
                    var thumb = (json.sizes && json.sizes.thumbnail) ? json.sizes.thumbnail.url : json.url;
                    html += '<img src="' + thumb + '" alt="" width="56" height="56" style="border-radius:6px;object-fit:cover" />';
                });
                if (input) input.value = ids.join(',');
                if (preview) preview.innerHTML = html;
            } else {
                var att = selecao.first().toJSON();
                var thumb = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
                if (input) {
                    input.value = estado.valueType === 'id' ? String(att.id) : att.url;
                }
                if (preview) {
                    preview.innerHTML = '<img src="' + thumb + '" alt="" />';
                }
            }
            fecharModais();
        });

        fecharModais();
        frame.open();
    }

    function processarArquivo(arquivo) {
        if (!arquivo) return;
        var tipos = ['image/jpeg', 'image/png', 'image/webp'];
        if (tipos.indexOf(arquivo.type) === -1) {
            alert(I18N.erroArquivo || 'Arquivo inválido');
            return;
        }
        abrirEditor(arquivo);
    }

    function iniciar(gatilho, opcoes) {
        opcoes = opcoes || {};
        lerGatilho(gatilho);

        if (!PROFILES[estado.perfil]) {
            return false;
        }

        if (opcoes.direto === true || gatilho.classList.contains('vh-upload-btn')) {
            if (elFileInput) {
                elFileInput.value = '';
                elFileInput.click();
            }
            return true;
        }

        abrirOrigem();
        return true;
    }

    function bindEventos() {
        elOrigem = qs('vh-modal-crop-origem');
        elEditor = qs('vh-modal-crop-editor');
        elFileInput = qs('vh-crop-file-input');
        elCropImg = qs('vh-crop-imagem');

        if (!elOrigem || !elEditor || !elFileInput) return;

        qs('vh-crop-origem-enviar').addEventListener('click', function () {
            elFileInput.value = '';
            elFileInput.click();
        });

        qs('vh-crop-origem-biblioteca').addEventListener('click', abrirBiblioteca);
        qs('vh-crop-origem-cancelar').addEventListener('click', fecharModais);
        qs('vh-crop-editor-cancelar').addEventListener('click', fecharModais);
        qs('vh-crop-editor-aplicar').addEventListener('click', aplicarCrop);

        elFileInput.addEventListener('change', function () {
            var arquivo = elFileInput.files && elFileInput.files[0];
            if (arquivo) {
                processarArquivo(arquivo);
            }
        });

        document.querySelectorAll('[data-vh-crop-fechar]').forEach(function (el) {
            el.addEventListener('click', fecharModais);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && (!elOrigem.hidden || !elEditor.hidden)) {
                fecharModais();
            }
        });
    }

    window.paMediaCrop = {
        iniciar: iniciar,
        uploadBlob: uploadBlob,
        profiles: PROFILES,
        getCroppedBlob: function (cropper, perfilSlug, qualidade) {
            var perfil = PROFILES[perfilSlug];
            if (!cropper || !perfil) return null;
            var canvas = cropper.getCroppedCanvas({
                width: perfil.width,
                height: perfil.height,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            if (!canvas) return null;
            var fmt = formatoExportacao(perfil);
            var qualidade = fmt.qualidade !== undefined ? fmt.qualidade : undefined;
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    resolve(blob);
                }, fmt.mime, qualidade);
            });
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEventos);
    } else {
        bindEventos();
    }
})();
