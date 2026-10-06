<?php
/**
 * View — Integração Tiny ERP.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$status = VH_Tiny::status_publico();
$modo   = (string) ( $status['modo'] ?? 'v3' );
$conectado = ! empty( $status['conectado'] );
$v2_token_configurado = ! empty( $status['v2_token_configurado'] ) || ( ! empty( $status['credenciais_ok'] ) && 'v2' === $modo );
$v3_secret_configurado = ! empty( $status['client_secret_configurado'] ) || ( ! empty( $status['credenciais_ok'] ) && 'v3' === $modo );

$conta_cnpj      = (string) ( $status['conta_cnpj_fmt'] ?? '' );
$conta_nome      = (string) ( $status['conta_nome'] ?? '' );
$conta_bloqueada = ! empty( $status['conta_bloqueada'] );

$tiny_feedback = isset( $_GET['tiny'] ) ? sanitize_key( wp_unslash( $_GET['tiny'] ) ) : '';
$tiny_msg      = isset( $_GET['tiny_msg'] ) ? sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['tiny_msg'] ) ) ) : '';
?>

<div class="vh-admin-wrap vh-tiny-wrap">

	<?php require VH_LOJA_DIR . 'admin/views/integracoes/partials/subnav.php'; ?>

	<?php if ( 'conectado' === $tiny_feedback ) : ?>
		<div class="vh-feedback vh-feedback--sucesso"><?php esc_html_e( 'Tiny ERP conectado com sucesso.', 'vapor-hub-loja' ); ?></div>
	<?php elseif ( 'erro' === $tiny_feedback ) : ?>
		<div class="vh-feedback vh-feedback--erro"><?php echo esc_html( $tiny_msg ?: __( 'Falha ao conectar com o Tiny.', 'vapor-hub-loja' ) ); ?></div>
	<?php endif; ?>

	<?php if ( $conta_bloqueada ) : ?>
		<div class="vh-feedback vh-feedback--erro">
			<?php
			printf(
				/* translators: %s: locked company CNPJ */
				esc_html__( 'Sincronização bloqueada: a conta conectada no Tiny não é a empresa travada (%s). Reconecte a empresa correta ou libere a trava para usar outro CNPJ.', 'vapor-hub-loja' ),
				esc_html( $conta_cnpj )
			);
			?>
			<button type="button" class="vh-btn vh-btn--secundario" id="vh-tiny-liberar-trava" style="margin-left:8px">
				<?php esc_html_e( 'Liberar trava de empresa', 'vapor-hub-loja' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<?php if ( ! $conectado && ! empty( $status['credenciais_ok'] ) && ! empty( $status['ultimo_erro'] ) && ! $conta_bloqueada ) : ?>
		<div class="vh-feedback vh-feedback--aviso">
			<?php echo esc_html( $status['ultimo_erro'] ); ?>
			<?php if ( 'v3' === $modo ) : ?>
				<a href="#vh-tiny-bloco-v3" class="vh-link"><?php esc_html_e( 'Reconectar ao Tiny', 'vapor-hub-loja' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="vh-admin-section">
		<h2><?php esc_html_e( 'Conexão com o Tiny ERP', 'vapor-hub-loja' ); ?></h2>
		<p class="vh-form-descricao">
			<?php esc_html_e( 'Sincronize produtos, preços, estoque, categorias, imagens e pedidos entre a loja e o Tiny ERP.', 'vapor-hub-loja' ); ?>
		</p>

		<div class="vh-form-grupo vh-tiny-modo-grupo">
			<label for="vh-tiny-modo"><?php esc_html_e( 'Versão da API', 'vapor-hub-loja' ); ?></label>
			<select id="vh-tiny-modo" class="vh-tiny-modo-select">
				<option value="v3" <?php selected( $modo, 'v3' ); ?>><?php esc_html_e( 'v3 (Aplicativos / OAuth)', 'vapor-hub-loja' ); ?></option>
				<option value="v2" <?php selected( $modo, 'v2' ); ?>><?php esc_html_e( 'v2 (Token API)', 'vapor-hub-loja' ); ?></option>
			</select>
			<p class="vh-form-descricao"><?php esc_html_e( 'Escolha o método de autenticação disponível no seu plano Tiny. As credenciais de cada modo são preservadas ao alternar.', 'vapor-hub-loja' ); ?></p>
		</div>

		<div class="vh-tiny-status-grid">
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Modo ativo', 'vapor-hub-loja' ); ?></span>
				<strong><?php echo esc_html( strtoupper( $modo ) ); ?></strong>
			</div>
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Credenciais', 'vapor-hub-loja' ); ?></span>
				<strong class="<?php echo ! empty( $status['credenciais_ok'] ) ? 'vh-tiny-ok' : 'vh-tiny-pendente'; ?>">
					<?php echo ! empty( $status['credenciais_ok'] ) ? esc_html__( 'Configuradas', 'vapor-hub-loja' ) : esc_html__( 'Pendentes', 'vapor-hub-loja' ); ?>
				</strong>
			</div>
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Conexão', 'vapor-hub-loja' ); ?></span>
				<strong class="<?php echo $conectado ? 'vh-tiny-ok' : 'vh-tiny-pendente'; ?>">
					<?php echo $conectado ? esc_html__( 'Conectado', 'vapor-hub-loja' ) : esc_html__( 'Desconectado', 'vapor-hub-loja' ); ?>
				</strong>
			</div>
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Empresa (CNPJ)', 'vapor-hub-loja' ); ?></span>
				<strong class="<?php echo $conta_bloqueada ? 'vh-tiny-pendente' : 'vh-tiny-ok'; ?>">
					<?php echo esc_html( $conta_cnpj ?: __( 'Não travada', 'vapor-hub-loja' ) ); ?>
				</strong>
				<?php if ( $conta_nome ) : ?>
					<span class="vh-tiny-status-label"><?php echo esc_html( $conta_nome ); ?></span>
				<?php endif; ?>
			</div>
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Produtos sem vínculo', 'vapor-hub-loja' ); ?></span>
				<strong><?php echo esc_html( (string) (int) $status['pendencias'] ); ?></strong>
			</div>
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Alterações não enviadas', 'vapor-hub-loja' ); ?></span>
				<strong class="<?php echo (int) ( $status['alteracoes_locais_pendentes'] ?? 0 ) > 0 ? 'vh-tiny-pendente' : 'vh-tiny-ok'; ?>">
					<?php echo esc_html( (string) (int) ( $status['alteracoes_locais_pendentes'] ?? 0 ) ); ?>
				</strong>
			</div>
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Categorias sem vínculo', 'vapor-hub-loja' ); ?></span>
				<strong class="<?php echo (int) ( $status['categorias_sem_vinculo'] ?? 0 ) > 0 ? 'vh-tiny-pendente' : 'vh-tiny-ok'; ?>">
					<?php echo esc_html( (string) (int) ( $status['categorias_sem_vinculo'] ?? 0 ) ); ?>
				</strong>
			</div>
			<div class="vh-tiny-status-card">
				<span class="vh-tiny-status-label"><?php esc_html_e( 'Variações sem SKU', 'vapor-hub-loja' ); ?></span>
				<strong class="<?php echo (int) ( $status['variacoes_sem_sku'] ?? 0 ) > 0 ? 'vh-tiny-pendente' : 'vh-tiny-ok'; ?>">
					<?php echo esc_html( (string) (int) ( $status['variacoes_sem_sku'] ?? 0 ) ); ?>
				</strong>
			</div>
		</div>

		<div id="vh-tiny-bloco-v3" class="vh-tiny-bloco-modo" <?php echo 'v3' !== $modo ? 'hidden' : ''; ?>>
			<form id="vh-form-tiny-credenciais" class="vh-tiny-form">
				<div class="vh-form-grupo">
					<label for="vh-tiny-client-id"><?php esc_html_e( 'Client ID', 'vapor-hub-loja' ); ?></label>
					<input type="text" id="vh-tiny-client-id" name="client_id" value="<?php echo esc_attr( (string) ( $status['client_id'] ?? '' ) ); ?>" autocomplete="off" />
				</div>
				<div class="vh-form-grupo">
					<label for="vh-tiny-client-secret"><?php esc_html_e( 'Client Secret', 'vapor-hub-loja' ); ?></label>
					<input
						type="password"
						id="vh-tiny-client-secret"
						name="client_secret"
						value=""
						autocomplete="new-password"
						spellcheck="false"
						data-secret-configurado="<?php echo $v3_secret_configurado ? '1' : '0'; ?>"
						placeholder="<?php echo esc_attr( $v3_secret_configurado
							? __( '••••••••••••••••  — digite um novo para alterar', 'vapor-hub-loja' )
							: __( 'Cole o Client Secret gerado no aplicativo API v3 do Tiny', 'vapor-hub-loja' )
						); ?>"
					/>
					<?php if ( $v3_secret_configurado ) : ?>
						<p class="vh-form-descricao vh-tiny-token-mascarado"><?php esc_html_e( 'Secret criptografado no servidor. O valor original nunca é exibido.', 'vapor-hub-loja' ); ?></p>
					<?php else : ?>
						<p class="vh-form-descricao"><?php esc_html_e( 'Gerado ao salvar o aplicativo no Tiny. Salve aqui antes de clicar em Conectar.', 'vapor-hub-loja' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="vh-form-grupo">
					<label><?php esc_html_e( 'URL de redirecionamento (OAuth)', 'vapor-hub-loja' ); ?></label>
					<input type="text" readonly value="<?php echo esc_attr( (string) ( $status['redirect_uri'] ?? '' ) ); ?>" class="vh-input-readonly" onclick="this.select()" />
					<p class="vh-form-descricao"><?php esc_html_e( 'Cadastre esta URL no aplicativo API v3 do Tiny.', 'vapor-hub-loja' ); ?></p>
				</div>
				<div class="vh-form-acoes">
					<button type="submit" class="vh-btn vh-btn--secundario"><?php esc_html_e( 'Salvar credenciais', 'vapor-hub-loja' ); ?></button>
					<?php if ( ! empty( $status['credenciais_ok'] ) && ! $conectado ) : ?>
						<button type="button" id="vh-tiny-conectar" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Conectar ao Tiny', 'vapor-hub-loja' ); ?></button>
					<?php endif; ?>
					<?php if ( $conectado && 'v3' === $modo ) : ?>
						<button type="button" id="vh-tiny-desconectar" class="vh-btn vh-btn--ghost"><?php esc_html_e( 'Desconectar', 'vapor-hub-loja' ); ?></button>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<div id="vh-tiny-bloco-v2" class="vh-tiny-bloco-modo" <?php echo 'v2' !== $modo ? 'hidden' : ''; ?>>
			<form id="vh-form-tiny-v2" class="vh-tiny-form">
				<div class="vh-form-grupo">
					<label for="vh-tiny-v2-token"><?php esc_html_e( 'Token API', 'vapor-hub-loja' ); ?></label>
					<input
						type="password"
						id="vh-tiny-v2-token"
						name="token"
						value=""
						autocomplete="new-password"
						spellcheck="false"
						data-token-configurado="<?php echo $v2_token_configurado ? '1' : '0'; ?>"
						placeholder="<?php echo esc_attr( $v2_token_configurado
							? __( '••••••••••••••••  — digite um novo para alterar', 'vapor-hub-loja' )
							: __( 'Cole o token de Configurações → Geral no Tiny', 'vapor-hub-loja' )
						); ?>"
					/>
					<?php if ( $v2_token_configurado ) : ?>
						<p class="vh-form-descricao vh-tiny-token-mascarado"><?php esc_html_e( 'Token criptografado no servidor. O valor original nunca é exibido.', 'vapor-hub-loja' ); ?></p>
					<?php else : ?>
						<p class="vh-form-descricao"><?php esc_html_e( 'Token gerado em Configurações → Geral no painel Tiny (API v2). Teste a conexão e, se estiver OK, salve o token para conectar.', 'vapor-hub-loja' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="vh-form-acoes">
					<button type="submit" class="vh-btn vh-btn--secundario"><?php esc_html_e( 'Salvar token', 'vapor-hub-loja' ); ?></button>
					<button type="button" id="vh-tiny-v2-testar" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Testar conexão', 'vapor-hub-loja' ); ?></button>
					<?php if ( $conectado && 'v2' === $modo ) : ?>
						<button type="button" id="vh-tiny-desconectar-v2" class="vh-btn vh-btn--ghost"><?php esc_html_e( 'Desconectar', 'vapor-hub-loja' ); ?></button>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</div>

	<?php if ( $conectado ) : ?>
	<div class="vh-admin-section">
		<h2><?php esc_html_e( 'Sincronização', 'vapor-hub-loja' ); ?></h2>

		<form id="vh-form-tiny-config" class="vh-tiny-form">
			<div class="vh-toggle-wrapper">
				<input type="checkbox" class="vh-toggle" id="vh-tiny-ativo" <?php checked( ! empty( $status['ativo'] ) ); ?> />
				<label for="vh-tiny-ativo"><?php esc_html_e( 'Integração ativa', 'vapor-hub-loja' ); ?></label>
			</div>
			<div class="vh-toggle-wrapper" style="margin-top:10px">
				<input type="checkbox" class="vh-toggle" id="vh-tiny-sinc-auto" <?php checked( ! empty( $status['sinc_auto'] ) ); ?> />
				<label for="vh-tiny-sinc-auto"><?php esc_html_e( 'Sincronização automática (hooks + cron)', 'vapor-hub-loja' ); ?></label>
			</div>

			<?php $vh_travas = is_array( $status['travas'] ?? null ) ? $status['travas'] : []; ?>
			<fieldset class="vh-tiny-direcao" style="margin-top:16px;border:1px solid var(--vh-cinza-200);border-radius:8px;padding:14px">
				<legend style="font-weight:600;padding:0 6px"><?php esc_html_e( 'O que volta do Tiny depois da importação', 'vapor-hub-loja' ); ?></legend>
				<?php
				$vh_entrada = [
					'entrada_estoque'   => __( 'Estoque', 'vapor-hub-loja' ),
					'entrada_preco'     => __( 'Preço', 'vapor-hub-loja' ),
					'entrada_nome'      => __( 'Nome', 'vapor-hub-loja' ),
					'entrada_descricao' => __( 'Descrição', 'vapor-hub-loja' ),
					'entrada_categoria' => __( 'Categoria', 'vapor-hub-loja' ),
					'entrada_imagem'    => __( 'Imagens', 'vapor-hub-loja' ),
					'entrada_grade'     => __( 'Grade e variações', 'vapor-hub-loja' ),
				];
				foreach ( $vh_entrada as $vh_chave => $vh_rotulo ) :
					?>
					<div class="vh-toggle-wrapper" style="margin-top:8px">
						<input type="checkbox" class="vh-toggle" data-trava="<?php echo esc_attr( $vh_chave ); ?>" id="vh-trava-<?php echo esc_attr( $vh_chave ); ?>" <?php checked( ! empty( $vh_travas[ $vh_chave ] ) ); ?> />
						<label for="vh-trava-<?php echo esc_attr( $vh_chave ); ?>"><?php echo esc_html( $vh_rotulo ); ?></label>
					</div>
				<?php endforeach; ?>
				<p class="vh-form-descricao" style="margin-top:10px"><?php esc_html_e( 'Nome, descrição, categoria, preço, imagens e grade entram na importação inicial. Estas travas valem para a sincronização seguinte.', 'vapor-hub-loja' ); ?></p>
			</fieldset>
			<fieldset class="vh-tiny-direcao" style="margin-top:16px;border:1px solid var(--vh-cinza-200);border-radius:8px;padding:14px">
				<legend style="font-weight:600;padding:0 6px"><?php esc_html_e( 'O que sai da loja para o Tiny', 'vapor-hub-loja' ); ?></legend>
				<?php
				$vh_saida = [
					'saida_estoque'   => __( 'Estoque', 'vapor-hub-loja' ),
					'saida_pedido'    => __( 'Status do pedido', 'vapor-hub-loja' ),
					'saida_nome'      => __( 'Nome', 'vapor-hub-loja' ),
					'saida_descricao' => __( 'Descrição', 'vapor-hub-loja' ),
					'saida_categoria' => __( 'Categoria', 'vapor-hub-loja' ),
					'saida_preco'     => __( 'Preço', 'vapor-hub-loja' ),
					'saida_imagem'    => __( 'Imagens', 'vapor-hub-loja' ),
					'saida_grade'     => __( 'Grade e variações', 'vapor-hub-loja' ),
				];
				foreach ( $vh_saida as $vh_chave => $vh_rotulo ) :
					?>
					<div class="vh-toggle-wrapper" style="margin-top:8px">
						<input type="checkbox" class="vh-toggle" data-trava="<?php echo esc_attr( $vh_chave ); ?>" id="vh-trava-<?php echo esc_attr( $vh_chave ); ?>" <?php checked( ! empty( $vh_travas[ $vh_chave ] ) ); ?> />
						<label for="vh-trava-<?php echo esc_attr( $vh_chave ); ?>"><?php echo esc_html( $vh_rotulo ); ?></label>
					</div>
				<?php endforeach; ?>
				<p class="vh-form-descricao" style="margin-top:10px"><?php esc_html_e( 'Título, descrição e preço editados na loja não voltam ao hub. O identificador abaixo marca só o pedido.', 'vapor-hub-loja' ); ?></p>
			</fieldset>

			<div class="vh-form-grupo" style="margin-top:16px">
				<label for="vh-tiny-loja-id"><?php esc_html_e( 'Identificador da loja', 'vapor-hub-loja' ); ?></label>
				<input
					type="text"
					id="vh-tiny-loja-id"
					name="loja_identificador"
					value="<?php echo esc_attr( (string) ( $status['loja_identificador'] ?? '' ) ); ?>"
					placeholder="<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'piloto.example' ); ?>"
					maxlength="120"
					autocomplete="off"
				/>
				<p class="vh-form-descricao"><?php esc_html_e( 'Vai no pedido enviado ao Tiny. Não altera SKU nem separa o cadastro do produto.', 'vapor-hub-loja' ); ?></p>
			</div>
			<div class="vh-form-grupo" style="margin-top:16px">
				<label for="vh-tiny-import-marcas"><?php esc_html_e( 'Marcas permitidas na importação', 'vapor-hub-loja' ); ?></label>
				<input
					type="text"
					id="vh-tiny-import-marcas"
					value="<?php echo esc_attr( implode( ', ', (array) ( $status['import_marcas'] ?? [] ) ) ); ?>"
					placeholder="<?php esc_attr_e( 'Vazio importa todas', 'vapor-hub-loja' ); ?>"
					autocomplete="off"
				/>
			</div>
			<div class="vh-form-grupo" id="vh-tiny-raizes" style="margin-top:16px" data-selecionadas="<?php echo esc_attr( implode( ',', array_map( 'strval', (array) ( $status['import_raizes'] ?? [] ) ) ) ); ?>">
				<label><?php esc_html_e( 'Raízes de categoria permitidas', 'vapor-hub-loja' ); ?></label>
				<p class="vh-form-descricao"><?php esc_html_e( 'Vazio usa a árvore dos produtos com estoque. Marque uma raiz para limitar esta loja.', 'vapor-hub-loja' ); ?></p>
			</div>
			<p class="vh-form-descricao" style="margin-top:12px">
				<?php esc_html_e( 'A importação inicial traz só produto principal ativo com estoque, com categoria, grade e variações. O cron seguinte não repete esse cadastro.', 'vapor-hub-loja' ); ?>
			</p>
			<div class="vh-form-acoes">
				<button type="submit" class="vh-btn vh-btn--secundario"><?php esc_html_e( 'Salvar configuração', 'vapor-hub-loja' ); ?></button>
				<button type="button" id="vh-tiny-sincronizar" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Sincronizar agora', 'vapor-hub-loja' ); ?></button>
				<button type="button" id="vh-tiny-importar-previa" class="vh-btn vh-btn--secundario"><?php esc_html_e( 'Contar recorte', 'vapor-hub-loja' ); ?></button>
				<button type="button" id="vh-tiny-importar" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Importar lote', 'vapor-hub-loja' ); ?></button>
				<button type="button" id="vh-tiny-importar-zerar" class="vh-btn vh-btn--ghost"><?php esc_html_e( 'Apagar catálogo importado', 'vapor-hub-loja' ); ?></button>
			</div>
			<p class="vh-form-descricao" id="vh-tiny-import-status" aria-live="polite"></p>
		</form>

		<div class="vh-form-grupo" style="margin-top:20px">
			<label><?php esc_html_e( 'URL do Webhook', 'vapor-hub-loja' ); ?></label>
			<input type="text" readonly value="<?php echo esc_attr( (string) ( $status['webhook_url'] ?? '' ) ); ?>" class="vh-input-readonly" onclick="this.select()" />
			<p class="vh-form-descricao"><?php esc_html_e( 'Configure no Tiny para receber atualizações em tempo real (opcional na v2; cron a cada 5 min também reconcilia estoque).', 'vapor-hub-loja' ); ?></p>
		</div>

		<div class="vh-form-grupo">
			<label><?php esc_html_e( 'Token do Webhook (header)', 'vapor-hub-loja' ); ?></label>
			<input type="text" readonly value="<?php echo esc_attr( (string) ( $status['webhook_token'] ?? '' ) ); ?>" class="vh-input-readonly" onclick="this.select()" />
			<p class="vh-form-descricao">
				<?php
				printf(
					/* translators: %s: HTTP header name */
					esc_html__( 'Preferível à URL: envie o token no header %s. O que fica gravado aqui é só o hash.', 'vapor-hub-loja' ),
					'<code>' . esc_html( VH_Tiny::HEADER_WEBHOOK ) . '</code>'
				);
				?>
			</p>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<button type="button" id="vh-tiny-rotacionar-webhook" class="vh-btn vh-btn--secundario">
					<?php esc_html_e( 'Rotacionar token', 'vapor-hub-loja' ); ?>
				</button>
				<p class="vh-form-descricao">
					<?php esc_html_e( 'Rotacionar invalida na hora a URL e o token antigos — atualize o cadastro no painel do Tiny em seguida.', 'vapor-hub-loja' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $status['ultima_reconciliacao'] ) ) : ?>
			<p class="vh-form-descricao">
				<?php
				printf(
					esc_html__( 'Última reconciliação: %s', 'vapor-hub-loja' ),
					esc_html( VH_Tiny::formatar_data( (string) $status['ultima_reconciliacao'] ) )
				);
				?>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $status['ultimo_erro'] ) ) : ?>
			<p class="vh-tiny-erro"><?php echo esc_html( $status['ultimo_erro'] ); ?></p>
		<?php endif; ?>
	</div>

	<div class="vh-admin-section">
		<h2><?php esc_html_e( 'Fila de sincronização', 'vapor-hub-loja' ); ?></h2>
		<p class="vh-form-descricao">
			<?php esc_html_e( 'Resumo da fila assíncrona. Jobs entram ao salvar produtos/categorias, via webhook, cron ou “Sincronizar agora”.', 'vapor-hub-loja' ); ?>
		</p>
		<div class="vh-tiny-fila-resumo">
			<span><?php printf( esc_html__( 'Pendentes: %d', 'vapor-hub-loja' ), (int) ( $status['fila']['pending'] ?? 0 ) ); ?></span>
			<span><?php printf( esc_html__( 'Falhas: %d', 'vapor-hub-loja' ), (int) ( $status['fila']['failed'] ?? 0 ) ); ?></span>
			<span><?php printf( esc_html__( 'Concluídos: %d', 'vapor-hub-loja' ), (int) ( $status['fila']['done'] ?? 0 ) ); ?></span>
		</div>
		<?php if ( VH_Router::pode_ver_logs_tiny() ) : ?>
			<p class="vh-form-descricao" style="margin-top:12px">
				<a href="<?php echo esc_url( VH_Router::url( 'tiny', [ 'acao' => 'logs' ] ) ); ?>" class="vh-link">
					<?php esc_html_e( 'Ver fila completa e histórico de logs →', 'vapor-hub-loja' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
