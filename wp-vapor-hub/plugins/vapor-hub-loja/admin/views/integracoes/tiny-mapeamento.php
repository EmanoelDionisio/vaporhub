<?php
/**
 * View — Mapeamento Tiny ERP (categorias, atributos, preview de campos).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$status    = VH_Tiny::status_publico();
$conectado = ! empty( $status['conectado'] );
?>

<div class="vh-admin-wrap vh-tiny-wrap vh-tiny-mapeamento-wrap">

	<?php require VH_LOJA_DIR . 'admin/views/integracoes/partials/subnav.php'; ?>

	<?php if ( ! $conectado ) : ?>
		<div class="vh-feedback vh-feedback--aviso">
			<?php esc_html_e( 'Conecte o Tiny ERP na aba Conexão antes de configurar o mapeamento.', 'vapor-hub-loja' ); ?>
			<a href="<?php echo esc_url( VH_Router::url( 'tiny', [ 'acao' => 'conexao' ] ) ); ?>" class="vh-link">
				<?php esc_html_e( 'Ir para Conexão', 'vapor-hub-loja' ); ?>
			</a>
		</div>
	<?php else : ?>

		<div class="vh-admin-section">
			<div class="vh-admin-section__header">
				<h2><?php esc_html_e( 'Categorias', 'vapor-hub-loja' ); ?></h2>
				<p class="vh-admin-section__desc">
					<?php esc_html_e( 'Defina como cada categoria da loja aparece no Tiny ERP.', 'vapor-hub-loja' ); ?>
				</p>
				<div class="vh-tiny-map-ajuda vh-form-descricao" role="note">
					<p class="vh-tiny-map-ajuda__titulo">
						<?php esc_html_e( 'Como funciona cada ação', 'vapor-hub-loja' ); ?>
					</p>
					<ul class="vh-tiny-map-ajuda__lista">
						<li>
							<strong><?php esc_html_e( 'Vincular', 'vapor-hub-loja' ); ?></strong>
							<?php esc_html_e( ' — a categoria da loja e a do Tiny são a mesma (ex.: loja “EVA” ↔ Tiny “EVA”). Não coloca uma categoria dentro da outra no ERP. Cada categoria do Tiny só pode ser vinculada a uma da loja.', 'vapor-hub-loja' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Criar no Tiny', 'vapor-hub-loja' ); ?></strong>
							<?php esc_html_e( ' — cria uma categoria nova no ERP. A hierarquia segue a da loja: se for subcategoria (filha) de outra aqui, entrará como subcategoria dela no Tiny (desde que a categoria pai já esteja mapeada).', 'vapor-hub-loja' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Ignorar', 'vapor-hub-loja' ); ?></strong>
							<?php esc_html_e( ' — produtos desta categoria não serão enviados ao Tiny.', 'vapor-hub-loja' ); ?>
						</li>
					</ul>
				</div>
			</div>

			<?php VH_UI_Skeleton::render( 'vh-tiny-map-cats-loading', VH_UI_Skeleton::PRESET_TOOLBAR_TABLE_5 ); ?>

			<div id="vh-tiny-map-categorias" class="vh-tiny-map-panel vh-painel-async vh-ui-oculto" hidden>
				<div class="vh-tiny-map-toolbar">
					<span id="vh-tiny-map-pendentes" class="vh-tiny-map-pendentes"></span>
					<button type="button" class="vh-btn vh-btn--primario" id="vh-tiny-map-salvar-cats">
						<?php esc_html_e( 'Salvar categorias', 'vapor-hub-loja' ); ?>
					</button>
				</div>
				<div id="vh-tiny-map-erros-banner" class="vh-tiny-map-erros-banner vh-ui-oculto" hidden role="alert" aria-live="polite"></div>
				<div class="vh-admin-tabela-wrap">
					<table class="vh-admin-tabela vh-tiny-map-tabela">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Categoria da loja', 'vapor-hub-loja' ); ?></th>
								<th><?php esc_html_e( 'Caminho', 'vapor-hub-loja' ); ?></th>
								<th><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></th>
								<th><?php esc_html_e( 'Ação', 'vapor-hub-loja' ); ?></th>
								<th><?php esc_html_e( 'Categoria Tiny', 'vapor-hub-loja' ); ?></th>
							</tr>
						</thead>
						<tbody id="vh-tiny-map-cats-body"></tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="vh-admin-section">
			<div class="vh-admin-section__header">
				<h2><?php esc_html_e( 'Atributos de grade', 'vapor-hub-loja' ); ?></h2>
				<p class="vh-admin-section__desc">
					<?php esc_html_e( 'Defina o rótulo de cada dimensão de variação enviado ao Tiny (campo descricao da grade).', 'vapor-hub-loja' ); ?>
				</p>
			</div>

			<?php VH_UI_Skeleton::render( 'vh-tiny-map-attrs-loading', VH_UI_Skeleton::PRESET_FORM ); ?>

			<div id="vh-tiny-map-atributos" class="vh-tiny-map-panel vh-painel-async vh-ui-oculto" hidden>
				<form id="vh-form-tiny-map-atributos" class="vh-tiny-map-atributos-form"></form>
				<div class="vh-form-acoes">
					<button type="submit" form="vh-form-tiny-map-atributos" class="vh-btn vh-btn--primario" id="vh-tiny-map-salvar-attrs">
						<?php esc_html_e( 'Salvar atributos', 'vapor-hub-loja' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="vh-admin-section">
			<div class="vh-admin-section__header">
				<h2><?php esc_html_e( 'Campos do produto', 'vapor-hub-loja' ); ?></h2>
				<p class="vh-admin-section__desc">
					<?php esc_html_e( 'Mapeamento fixo entre a loja e o Tiny (somente leitura).', 'vapor-hub-loja' ); ?>
				</p>
			</div>

			<?php VH_UI_Skeleton::render( 'vh-tiny-map-campos-loading', VH_UI_Skeleton::PRESET_TABLE_3 ); ?>

			<div id="vh-tiny-map-campos" class="vh-tiny-map-panel vh-painel-async vh-ui-oculto" hidden>
				<div class="vh-admin-tabela-wrap">
					<table class="vh-admin-tabela">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Campo na loja', 'vapor-hub-loja' ); ?></th>
								<th><?php esc_html_e( 'Campo no Tiny', 'vapor-hub-loja' ); ?></th>
								<th><?php esc_html_e( 'Observação', 'vapor-hub-loja' ); ?></th>
							</tr>
						</thead>
						<tbody id="vh-tiny-map-campos-body"></tbody>
					</table>
				</div>
			</div>
		</div>

	<?php endif; ?>
</div>
