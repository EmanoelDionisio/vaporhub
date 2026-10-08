/**
 * Vapor Hub - Minha Loja — Scripts do painel administrativo.
 *
 * @package VaporHubLoja
 */

(function ($) {
    'use strict';

    const PA = window.paLoja || {};

    /* ═══════════════════════════════════════════════
     * Media Uploader
     * ═══════════════════════════════════════════════ */
    function abrirMediaUploader(targetInput, previewContainer, size) {
        if (typeof wp === 'undefined' || !wp.media) {
            window.alert(PA.erro_midia || 'Biblioteca de mídia indisponível. Recarregue a página.');
            return;
        }
        if (!targetInput) {
            return;
        }

        const frame = wp.media({
            title: PA.titulo_midia || 'Selecionar imagem',
            button: { text: PA.botao_midia || 'Usar esta imagem' },
            multiple: false,
            library: { type: 'image' },
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            let url = attachment.url;
            if (size === 'full') {
                url = attachment.url;
            } else if (size && attachment.sizes && attachment.sizes[size]) {
                url = attachment.sizes[size].url;
            } else if (attachment.sizes && attachment.sizes.medium) {
                url = attachment.sizes.medium.url;
            }

            $(targetInput).val(url);

            const $preview = $(previewContainer);
            if ($preview.length) {
                $preview.html('<img src="' + url + '" alt="" />');
            }
        });

        frame.open();
    }

    $(document).on('click', '.vh-upload-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var crop = $(this).attr('data-crop');
        if (crop && window.paMediaCrop) {
            window.paMediaCrop.iniciar(this, { direto: true });
            return;
        }

        const targetInput = $(this).attr('data-target');
        const previewContainer = $(this).attr('data-preview');
        const size = $(this).attr('data-size') || 'medium';
        abrirMediaUploader(targetInput, previewContainer, size);
    });

    $(document).on('click', '.vh-media-enviar-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (window.paMediaCrop) {
            window.paMediaCrop.iniciar(this);
        }
    });

    $(document).on('click', '.vh-upload-limpar', function (e) {
        e.preventDefault();
        const targetInput = $(this).attr('data-target');
        const previewContainer = $(this).attr('data-preview');

        $(targetInput).val('');
        const emptyIcon = $(previewContainer).data('emptyIcon') || 'format-image';
        $(previewContainer).html('<span class="dashicons dashicons-' + emptyIcon + '"></span>');
        $(this).remove();
    });

    /* ═══════════════════════════════════════════════
     * Repeater — Comunidade
     * ═══════════════════════════════════════════════ */
    function obterProximoIndice() {
        let max = -1;
        $('#vh-repeater-container .vh-repeater-item').each(function () {
            const idx = parseInt($(this).data('indice'), 10);
            if (idx > max) max = idx;
        });
        return max + 1;
    }

    function atualizarContagem() {
        const total = $('#vh-repeater-container .vh-repeater-item').length;
        const maxItens = PA.max_comunidade || 6;
        $('#vh-repeater-contagem').text(total + ' de ' + maxItens + ' entradas');

        if (total >= maxItens) {
            $('#vh-repeater-adicionar').prop('disabled', true).css('opacity', '0.5');
        } else {
            $('#vh-repeater-adicionar').prop('disabled', false).css('opacity', '1');
        }
    }

    $(document).on('click', '#vh-repeater-adicionar', function () {
        const maxItens = PA.max_comunidade || 6;
        const total = $('#vh-repeater-container .vh-repeater-item').length;

        if (total >= maxItens) {
            return;
        }

        const novoIndice = obterProximoIndice();
        const template = $('#tmpl-vh-repeater-item').html();

        if (!template) {
            return;
        }

        const html = template.replace(/\{\{INDEX\}\}/g, novoIndice);
        $('#vh-repeater-container').append(html);
        atualizarContagem();
    });

    $(document).on('click', '.vh-repeater-remover', function () {
        const $item = $(this).closest('.vh-repeater-item');
        const total = $('#vh-repeater-container .vh-repeater-item').length;

        if (total <= 1) {
            $item.find('input').val('');
            $item.find('.vh-repeater-thumb span').html(
                '<span class="dashicons dashicons-camera"></span>'
            );
            return;
        }

        const opcoes = {
            titulo: PA.confirmar_remover_titulo || 'Remover entrada?',
            mensagem: PA.confirmar_remover || 'Deseja remover esta entrada?',
            confirmar: PA.confirmar_acao_btn || 'Remover',
            cancelar: PA.cancelar || 'Cancelar',
            tipo: 'perigo',
        };

        function remover() {
            $item.fadeOut(200, function () {
                $(this).remove();
                reindexarRepeater();
                atualizarContagem();
            });
        }

        if (window.paLojaApp && typeof window.paLojaApp.confirmar === 'function') {
            window.paLojaApp.confirmar(opcoes).then(function (ok) {
                if (ok) {
                    remover();
                }
            });
            return;
        }

        if (confirm(opcoes.mensagem)) {
            remover();
        }
    });

    function reindexarRepeater() {
        $('#vh-repeater-container .vh-repeater-item').each(function (i) {
            $(this).attr('data-indice', i);

            $(this).find('input, select, textarea').each(function () {
                const name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
                }
                const id = $(this).attr('id');
                if (id) {
                    $(this).attr('id', id.replace(/\d+$/, i));
                }
            });

            $(this).find('.vh-repeater-thumb').attr('data-target', '#vh-comunidade-img-' + i);
            $(this).find('.vh-repeater-thumb').attr('data-preview', '#vh-comunidade-preview-' + i);
            $(this).find('.vh-repeater-thumb > span').attr('id', 'vh-comunidade-preview-' + i);
        });
    }

    /* ═══════════════════════════════════════════════
     * Repeater — Slides do Hero
     * ═══════════════════════════════════════════════ */
    const MAX_SLIDES = 8;

    function proximoIndiceSlide() {
        let max = -1;
        $('#vh-slides-container .vh-slide-item').each(function () {
            const idx = parseInt($(this).data('indice'), 10);
            if (idx > max) max = idx;
        });
        return max + 1;
    }

    function atualizarContagemSlides() {
        const total = $('#vh-slides-container .vh-slide-item').length;
        $('#vh-slides-contagem').text(total + ' slide(s) — recomendado até 3');

        if (total >= MAX_SLIDES) {
            $('#vh-slides-adicionar').prop('disabled', true).css('opacity', '0.5');
        } else {
            $('#vh-slides-adicionar').prop('disabled', false).css('opacity', '1');
        }
    }

    function marcarSlidesArrastaveis() {
        $('#vh-slides-container .vh-slide-item').each(function () {
            this.setAttribute('draggable', 'true');
        });
    }

    function initSlidesSortable() {
        const container = document.getElementById('vh-slides-container');
        if (!container || container.dataset.paSortableInit) {
            return;
        }

        let draggedItem = null;

        function limparIndicadoresDrop() {
            container.querySelectorAll('.vh-slide-item').forEach(function (item) {
                item.classList.remove('vh-slide-item--drop-before', 'vh-slide-item--drop-after', 'vh-slide-item--dragging');
            });
        }

        container.addEventListener('dragstart', function (e) {
            if (!e.target.closest('.vh-slide-drag-handle')) {
                e.preventDefault();
                return;
            }

            draggedItem = e.target.closest('.vh-slide-item');
            if (!draggedItem) {
                e.preventDefault();
                return;
            }

            draggedItem.classList.add('vh-slide-item--dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', draggedItem.getAttribute('data-indice') || '0');
        });

        container.addEventListener('dragend', function () {
            draggedItem = null;
            limparIndicadoresDrop();
        });

        container.addEventListener('dragover', function (e) {
            if (!draggedItem) {
                return;
            }

            e.preventDefault();

            const alvo = e.target.closest('.vh-slide-item');
            if (!alvo || alvo === draggedItem) {
                return;
            }

            e.dataTransfer.dropEffect = 'move';

            container.querySelectorAll('.vh-slide-item').forEach(function (item) {
                item.classList.remove('vh-slide-item--drop-before', 'vh-slide-item--drop-after');
            });

            const rect = alvo.getBoundingClientRect();
            const antes = e.clientY < rect.top + rect.height / 2;
            alvo.classList.add(antes ? 'vh-slide-item--drop-before' : 'vh-slide-item--drop-after');
        });

        container.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!draggedItem) {
                return;
            }

            const alvo = e.target.closest('.vh-slide-item');
            if (!alvo || alvo === draggedItem) {
                return;
            }

            const rect = alvo.getBoundingClientRect();
            const antes = e.clientY < rect.top + rect.height / 2;

            if (antes) {
                container.insertBefore(draggedItem, alvo);
            } else {
                container.insertBefore(draggedItem, alvo.nextElementSibling);
            }

            limparIndicadoresDrop();
            reindexarSlides();
        });

        container.dataset.paSortableInit = '1';
        marcarSlidesArrastaveis();
    }

    function reindexarSlides() {
        $('#vh-slides-container .vh-slide-item').each(function (i) {
            $(this).attr('data-indice', i);
            $(this).find('.vh-slide-ordem-num').text(i + 1);

            const $desktop = $(this).find('.vh-slide-campo--desktop');
            $desktop.find('input').attr('name', 'vh_hero[slides][' + i + '][url]').attr('id', 'vh-slide-img-' + i);
            $desktop.find('.vh-slide-thumb')
                .attr('data-target', '#vh-slide-img-' + i)
                .attr('data-preview', '#vh-slide-preview-' + i);
            $desktop.find('.vh-slide-thumb > span').attr('id', 'vh-slide-preview-' + i);

            const $mobile = $(this).find('.vh-slide-campo--mobile');
            $mobile.find('input').attr('name', 'vh_hero[slides][' + i + '][url_mobile]').attr('id', 'vh-slide-img-m-' + i);
            $mobile.find('.vh-slide-thumb')
                .attr('data-target', '#vh-slide-img-m-' + i)
                .attr('data-preview', '#vh-slide-preview-m-' + i);
            $mobile.find('.vh-slide-thumb > span').attr('id', 'vh-slide-preview-m-' + i);
        });
    }

    $(document).on('click', '#vh-slides-adicionar', function () {
        if ($('#vh-slides-container .vh-slide-item').length >= MAX_SLIDES) {
            return;
        }
        const template = $('#tmpl-vh-slide-item').html();
        if (!template) {
            return;
        }
        const html = template.replace(/\{\{INDEX\}\}/g, proximoIndiceSlide());
        $('#vh-slides-container').append(html);
        marcarSlidesArrastaveis();
        reindexarSlides();
        atualizarContagemSlides();
    });

    $(document).on('click', '.vh-slide-remover', function () {
        const $item = $(this).closest('.vh-slide-item');
        const total = $('#vh-slides-container .vh-slide-item').length;

        if (total <= 1) {
            $item.find('input').val('');
            $item.find('.vh-slide-thumb span').html('<span class="dashicons dashicons-format-image"></span>');
            return;
        }

        $item.fadeOut(180, function () {
            $(this).remove();
            reindexarSlides();
            atualizarContagemSlides();
        });
    });

    /* ═══════════════════════════════════════════════
     * Repeater — Benefícios
     * ═══════════════════════════════════════════════ */
    const MAX_BENEFICIOS = 8;

    function atualizarPreviewBeneficio($item) {
        const icone = $item.find('.vh-beneficio-icone').val() || 'check-circle';
        const titulo = $.trim($item.find('.vh-beneficio-input-titulo').val());
        const previa = $item.find('.vh-beneficio-icone-preview .vh-menu-icone-atual').get(0);
        if (previa && window.vhIconesBiblioteca) {
            window.vhIconesBiblioteca.desenhar(previa, icone);
        }
        $item.find('.vh-beneficio-item-titulo-preview').text(
            titulo || (PA.beneficio_novo || 'Novo benefício')
        );
    }

    function proximoIndiceBeneficio() {
        let max = -1;
        $('#vh-beneficios-container .vh-beneficio-item').each(function () {
            const idx = parseInt($(this).data('indice'), 10);
            if (idx > max) {
                max = idx;
            }
        });
        return max + 1;
    }

    function atualizarContagemBeneficios() {
        const total = $('#vh-beneficios-container .vh-beneficio-item').length;
        const maxTxt = PA.max_beneficios || MAX_BENEFICIOS;
        $('#vh-beneficios-contagem').text(total + ' de ' + maxTxt + ' itens');

        if (total >= maxTxt) {
            $('#vh-beneficios-adicionar').prop('disabled', true).css('opacity', '0.5');
        } else {
            $('#vh-beneficios-adicionar').prop('disabled', false).css('opacity', '1');
        }

        $('#vh-beneficios-container .vh-beneficio-item').each(function (i) {
            $(this).find('.vh-beneficio-ordem').text(i + 1);
        });
    }

    function reindexarBeneficios() {
        $('#vh-beneficios-container .vh-beneficio-item').each(function (i) {
            $(this).attr('data-indice', i);
            $(this).find('select, input').each(function () {
                const name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
                }
            });
        });
        atualizarContagemBeneficios();
    }

    $(document).on('click', '#vh-beneficios-adicionar', function () {
        const maxTxt = PA.max_beneficios || MAX_BENEFICIOS;
        if ($('#vh-beneficios-container .vh-beneficio-item').length >= maxTxt) {
            return;
        }

        const template = $('#tmpl-vh-beneficio-item').html();
        if (!template) {
            return;
        }

        const idx = proximoIndiceBeneficio();
        const numero = $('#vh-beneficios-container .vh-beneficio-item').length + 1;
        const html = template
            .replace(/\{\{INDEX\}\}/g, idx)
            .replace(/\{\{NUMERO\}\}/g, numero);

        const $novo = $(html);
        $('#vh-beneficios-container').append($novo);
        reindexarBeneficios();
        if (window.paInitCustomSelects) {
            window.paInitCustomSelects($novo[0]);
        }
        $novo.find('.vh-beneficio-input-titulo').trigger('focus');
    });

    $(document).on('click', '.vh-beneficio-remover', function () {
        const $item = $(this).closest('.vh-beneficio-item');
        const total = $('#vh-beneficios-container .vh-beneficio-item').length;

        if (total <= 1) {
            $item.find('input[type="text"]').val('');
            $item.find('.vh-beneficio-icone').val('check-circle');
            if (window.vhIconesBiblioteca) {
                window.vhIconesBiblioteca.desenhar($item.find('.vh-menu-icone-abrir .vh-menu-icone-atual').get(0), 'check-circle');
                $item.find('.vh-menu-icone-abrir').addClass('tem-icone');
            }
            atualizarPreviewBeneficio($item);
            return;
        }

        const opcoes = {
            titulo: PA.confirmar_remover_titulo || 'Remover benefício?',
            mensagem: PA.confirmar_remover || 'Deseja remover este benefício?',
            confirmar: PA.confirmar_acao_btn || 'Remover',
            cancelar: PA.cancelar || 'Cancelar',
            tipo: 'perigo',
        };

        function remover() {
            $item.fadeOut(180, function () {
                $(this).remove();
                reindexarBeneficios();
            });
        }

        if (window.paLojaApp && typeof window.paLojaApp.confirmar === 'function') {
            window.paLojaApp.confirmar(opcoes).then(function (ok) {
                if (ok) {
                    remover();
                }
            });
            return;
        }

        if (confirm(opcoes.mensagem)) {
            remover();
        }
    });

    $(document).on('change', '.vh-beneficio-icone', function () {
        atualizarPreviewBeneficio($(this).closest('.vh-beneficio-item'));
    });

    $(document).on('input', '.vh-beneficio-input-titulo', function () {
        atualizarPreviewBeneficio($(this).closest('.vh-beneficio-item'));
    });

    /* ═══════════════════════════════════════════════
     * Inicialização
     * ═══════════════════════════════════════════════ */
    $(document).ready(function () {
        atualizarContagem();
        initSlidesSortable();
        reindexarSlides();
        atualizarContagemSlides();
        atualizarContagemBeneficios();
        $('#vh-beneficios-container .vh-beneficio-item').each(function () {
            atualizarPreviewBeneficio($(this));
        });

        if ($('.vh-toggle').length) {
            $('.vh-toggle').on('change', function () {
                const checked = $(this).is(':checked');
                $(this).val(checked ? '1' : '0');
            });
        }

        $(document).on('change', '#vh-usar-slides', function () {
            if (!$(this).is(':checked')) {
                return;
            }
            window.setTimeout(function () {
                marcarSlidesArrastaveis();
            }, 50);
        });

        /* Picker de categorias no produto: checkbox não deve fechar o grupo. */
        $(document).on('click', '.vh-cat-produto-grupo-resumo .vh-cat-produto-item', function (e) {
            e.stopPropagation();
        });

        initCatProdutoPicker();
    });

    function atualizarScrollPickerCategorias() {
        document.querySelectorAll('.vh-cat-produto-picker').forEach(function (picker) {
            picker.classList.toggle(
                'vh-cat-produto-picker--scrollable',
                picker.scrollHeight > picker.clientHeight + 2
            );
        });
    }

    function initCatProdutoPicker() {
        var $picker = $('.vh-cat-produto-picker');
        if (!$picker.length) {
            return;
        }

        atualizarScrollPickerCategorias();
        window.addEventListener('resize', atualizarScrollPickerCategorias);
        $picker.on('toggle', 'details', atualizarScrollPickerCategorias);
    }

    $(document).on('input', '.vh-cat-produto-busca', function () {
        var q = $(this).val().toLowerCase().trim();
        var $wrap = $(this).closest('.vh-cat-produto-wrap');
        var $picker = $wrap.find('.vh-cat-produto-picker');

        $picker.find('.vh-cat-produto-item--solta').each(function () {
            var nome = $(this).find('.vh-cat-produto-nome').text().toLowerCase();
            var match = !q || nome.indexOf(q) !== -1;
            this.hidden = !match;
        });

        $picker.find('.vh-cat-produto-grupo').each(function () {
            var $grupo = $(this);
            var rootNome = $grupo.find('> summary .vh-cat-produto-nome').text().toLowerCase();
            var rootMatch = q && rootNome.indexOf(q) !== -1;
            var filhoVisivel = false;

            $grupo.find('.vh-cat-produto-grupo-corpo .vh-cat-produto-item').each(function () {
                var nome = $(this).find('.vh-cat-produto-nome').text().toLowerCase();
                var caminho = $(this).find('.vh-cat-produto-caminho').text().toLowerCase();
                var match = !q || rootMatch || nome.indexOf(q) !== -1 || caminho.indexOf(q) !== -1;
                this.hidden = !match;
                if (match && q) {
                    filhoVisivel = true;
                }
            });

            var mostrar = !q || rootMatch || filhoVisivel;
            this.hidden = !mostrar;
            if (q && (rootMatch || filhoVisivel)) {
                this.open = true;
            }
        });

        atualizarScrollPickerCategorias();
    });

})(jQuery);
