<?php
/**
 * Abas internas da seção Tiny ERP.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_tiny_acao = VH_Router::acao_atual( 'tiny' );
$vh_pendentes = class_exists( 'VH_Tiny_Mapping_Service' ) ? VH_Tiny_Mapping_Service::contar_categorias_pendentes() : 0;
?>

<nav class="vh-revenda-subnav" aria-label="<?php esc_attr_e( 'Seções do Tiny ERP', 'vapor-hub-loja' ); ?>">
	<a class="vh-revenda-subnav__link <?php echo 'conexao' === $vh_tiny_acao ? 'is-ativo' : ''; ?>"
	   href="<?php echo esc_url( VH_Router::url( 'tiny', [ 'acao' => 'conexao' ] ) ); ?>">
		<span class="dashicons dashicons-admin-plugins"></span>
		<?php esc_html_e( 'Conexão', 'vapor-hub-loja' ); ?>
	</a>
	<a class="vh-revenda-subnav__link <?php echo 'mapeamento' === $vh_tiny_acao ? 'is-ativo' : ''; ?>"
	   href="<?php echo esc_url( VH_Router::url( 'tiny', [ 'acao' => 'mapeamento' ] ) ); ?>">
		<span class="dashicons dashicons-randomize"></span>
		<?php esc_html_e( 'Mapeamento', 'vapor-hub-loja' ); ?>
		<?php if ( $vh_pendentes > 0 ) : ?>
			<span class="vh-revenda-badge-novos"><?php echo esc_html( (string) (int) $vh_pendentes ); ?></span>
		<?php endif; ?>
	</a>
	<?php if ( VH_Router::pode_ver_logs_tiny() ) : ?>
	<a class="vh-revenda-subnav__link <?php echo 'logs' === $vh_tiny_acao ? 'is-ativo' : ''; ?>"
	   href="<?php echo esc_url( VH_Router::url( 'tiny', [ 'acao' => 'logs' ] ) ); ?>">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Logs', 'vapor-hub-loja' ); ?>
	</a>
	<?php endif; ?>
</nav>
