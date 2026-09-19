<?php
/**
 * Modal de crop/upload otimizado — painel Minha Loja.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="vh-modal-crop-origem" class="vh-modal vh-modal-crop" hidden aria-hidden="true">
    <div class="vh-modal__backdrop" data-vh-crop-fechar tabindex="-1"></div>
    <div class="vh-modal__caixa vh-modal-crop__caixa" role="dialog" aria-modal="true" aria-labelledby="vh-crop-origem-titulo">
        <h2 class="vh-modal__titulo" id="vh-crop-origem-titulo"></h2>
        <p class="vh-modal__mensagem" id="vh-crop-origem-descricao"></p>
        <div class="vh-modal-crop__origem-acoes">
            <button type="button" class="vh-btn vh-btn--primario" id="vh-crop-origem-enviar">
                <span class="dashicons dashicons-upload"></span>
                <span id="vh-crop-origem-enviar-texto"></span>
            </button>
            <button type="button" class="vh-btn vh-btn--secundario" id="vh-crop-origem-biblioteca">
                <span class="dashicons dashicons-admin-media"></span>
                <span id="vh-crop-origem-biblioteca-texto"></span>
            </button>
            <button type="button" class="vh-btn vh-btn--ghost" id="vh-crop-origem-cancelar"></button>
        </div>
        <input type="file" id="vh-crop-file-input" accept="image/jpeg,image/png,image/webp" hidden />
    </div>
</div>

<div id="vh-modal-crop-editor" class="vh-modal vh-modal-crop" hidden aria-hidden="true">
    <div class="vh-modal__backdrop" data-vh-crop-fechar tabindex="-1"></div>
    <div class="vh-modal__caixa vh-modal-crop__caixa vh-modal-crop__caixa--editor" role="dialog" aria-modal="true" aria-labelledby="vh-crop-editor-titulo">
        <h2 class="vh-modal__titulo" id="vh-crop-editor-titulo"></h2>
        <p class="vh-modal-crop__dica" id="vh-crop-editor-dica"></p>
        <p class="vh-modal-crop__perfil" id="vh-crop-editor-perfil"></p>
        <div class="vh-modal-crop__area">
            <img id="vh-crop-imagem" src="" alt="" />
        </div>
        <div class="vh-modal__acoes">
            <button type="button" class="vh-btn vh-btn--ghost" id="vh-crop-editor-cancelar"></button>
            <button type="button" class="vh-btn vh-btn--primario" id="vh-crop-editor-aplicar"></button>
        </div>
    </div>
</div>
