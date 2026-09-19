<?php
/**
 * View — Logs Tiny ERP (somente administrador).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

if ( ! VH_Router::pode_ver_logs_tiny() ) {
	wp_die(
		esc_html__( 'Somente administradores podem acessar os logs do Tiny ERP.', 'vapor-hub-loja' ),
		esc_html__( 'Acesso negado', 'vapor-hub-loja' ),
		[ 'response' => 403 ]
	);
}

$status = VH_Tiny::status_publico();
?>

<div class="vh-admin-wrap vh-tiny-wrap vh-tiny-logs-wrap">

	<?php require VH_LOJA_DIR . 'admin/views/integracoes/partials/subnav.php'; ?>

	<div class="vh-admin-section">
		<div class="vh-admin-section__header">
			<h2><?php esc_html_e( 'Logs da integração', 'vapor-hub-loja' ); ?></h2>
			<p class="vh-admin-section__desc">
				<?php esc_html_e( 'Histórico de eventos, erros de API, jobs da fila e webhooks. Visível apenas para administradores.', 'vapor-hub-loja' ); ?>
			</p>
		</div>

		<div id="vh-tiny-logs-resumo" class="vh-tiny-logs-resumo" aria-live="polite">
			<?php VH_UI_Skeleton::render( 'vh-tiny-logs-resumo-loading', VH_UI_Skeleton::PRESET_CARDS, [ 'rows' => 4 ] ); ?>
		</div>
	</div>

	<div class="vh-admin-section vh-tiny-logs-painel">
		<div class="vh-tiny-logs-toolbar">
			<div class="vh-tiny-logs-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Tipo de log', 'vapor-hub-loja' ); ?>">
				<button type="button" class="vh-tiny-logs-tab is-ativo" data-vh-logs-aba="eventos" role="tab" aria-selected="true">
					<?php esc_html_e( 'Eventos', 'vapor-hub-loja' ); ?>
				</button>
				<button type="button" class="vh-tiny-logs-tab" data-vh-logs-aba="fila" role="tab" aria-selected="false">
					<?php esc_html_e( 'Fila de sync', 'vapor-hub-loja' ); ?>
				</button>
			</div>

			<div class="vh-tiny-logs-filtros">
				<label class="vh-sr-only" for="vh-tiny-logs-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
				<input type="search" id="vh-tiny-logs-busca" class="vh-input vh-tiny-logs-busca" placeholder="<?php esc_attr_e( 'Buscar…', 'vapor-hub-loja' ); ?>" autocomplete="off" />

				<select id="vh-tiny-logs-filtro-nivel" class="vh-select vh-tiny-logs-filtro vh-tiny-logs-filtro--eventos" aria-label="<?php esc_attr_e( 'Nível', 'vapor-hub-loja' ); ?>">
					<option value=""><?php esc_html_e( 'Todos os níveis', 'vapor-hub-loja' ); ?></option>
					<option value="error"><?php esc_html_e( 'Erro', 'vapor-hub-loja' ); ?></option>
					<option value="warning"><?php esc_html_e( 'Aviso', 'vapor-hub-loja' ); ?></option>
					<option value="info"><?php esc_html_e( 'Info', 'vapor-hub-loja' ); ?></option>
					<option value="success"><?php esc_html_e( 'Sucesso', 'vapor-hub-loja' ); ?></option>
				</select>

				<select id="vh-tiny-logs-filtro-origem" class="vh-select vh-tiny-logs-filtro vh-tiny-logs-filtro--eventos" aria-label="<?php esc_attr_e( 'Origem', 'vapor-hub-loja' ); ?>">
					<option value=""><?php esc_html_e( 'Todas as origens', 'vapor-hub-loja' ); ?></option>
					<?php foreach ( VH_Tiny_Log::rotulos_origem() as $slug => $rotulo ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $rotulo ); ?></option>
					<?php endforeach; ?>
				</select>

				<select id="vh-tiny-logs-filtro-status" class="vh-select vh-tiny-logs-filtro vh-tiny-logs-filtro--fila vh-ui-oculto" hidden aria-label="<?php esc_attr_e( 'Status da fila', 'vapor-hub-loja' ); ?>">
					<option value=""><?php esc_html_e( 'Todos os status', 'vapor-hub-loja' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pendente', 'vapor-hub-loja' ); ?></option>
					<option value="processing"><?php esc_html_e( 'Processando', 'vapor-hub-loja' ); ?></option>
					<option value="done"><?php esc_html_e( 'Concluído', 'vapor-hub-loja' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Falhou', 'vapor-hub-loja' ); ?></option>
				</select>

				<select id="vh-tiny-logs-filtro-tipo" class="vh-select vh-tiny-logs-filtro vh-tiny-logs-filtro--fila vh-ui-oculto" hidden aria-label="<?php esc_attr_e( 'Tipo de job', 'vapor-hub-loja' ); ?>">
					<option value=""><?php esc_html_e( 'Todos os tipos', 'vapor-hub-loja' ); ?></option>
					<option value="produto_push">produto_push</option>
					<option value="produto_pull">produto_pull</option>
					<option value="estoque_push">estoque_push</option>
					<option value="estoque_pull">estoque_pull</option>
					<option value="categoria_push">categoria_push</option>
					<option value="pedido_push">pedido_push</option>
					<option value="reconciliar">reconciliar</option>
				</select>

				<button type="button" id="vh-tiny-logs-atualizar" class="vh-btn vh-btn--secundario vh-btn--icone">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Atualizar', 'vapor-hub-loja' ); ?>
				</button>
			</div>
		</div>

		<?php VH_UI_Skeleton::render( 'vh-tiny-logs-tabela-loading', VH_UI_Skeleton::PRESET_TABLE_4, [ 'rows' => 8 ] ); ?>

		<div id="vh-tiny-logs-tabela-wrap" class="vh-tiny-logs-tabela-wrap vh-painel-async vh-ui-oculto" hidden>
			<div class="vh-admin-tabela-wrap">
				<table class="vh-admin-tabela vh-tiny-logs-tabela">
					<thead id="vh-tiny-logs-thead"></thead>
					<tbody id="vh-tiny-logs-tbody"></tbody>
				</table>
			</div>
			<p id="vh-tiny-logs-vazio" class="vh-form-descricao vh-ui-oculto" hidden><?php esc_html_e( 'Nenhum registro encontrado.', 'vapor-hub-loja' ); ?></p>
		</div>

		<nav id="vh-tiny-logs-paginacao" class="vh-tiny-logs-paginacao vh-ui-oculto" hidden aria-label="<?php esc_attr_e( 'Paginação', 'vapor-hub-loja' ); ?>"></nav>
	</div>

	<?php if ( ! empty( $status['ultimo_erro'] ) ) : ?>
		<div class="vh-admin-section vh-tiny-logs-ultimo-erro">
			<h3><?php esc_html_e( 'Último erro global', 'vapor-hub-loja' ); ?></h3>
			<p class="vh-tiny-erro"><?php echo esc_html( (string) $status['ultimo_erro'] ); ?></p>
		</div>
	<?php endif; ?>

</div>
