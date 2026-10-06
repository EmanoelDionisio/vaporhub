<?php
/**
 * Gateway Tiny ERP — seleciona driver v2 ou v3.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny {

	public const OPTION = 'vh_tiny_erp';

	private static ?VH_Tiny_Driver_Interface $driver_instancia = null;
	private static string $driver_modo_cache = '';

	/**
	 * @return array<string, mixed>
	 */
	public static function obter(): array {
		return wp_parse_args( VH_Tiny_Client::obter(), self::padrao() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function padrao(): array {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return array_merge(
			VH_Tiny_Client::padrao(),
			[
				'modo'                  => 'v3',
				'v2_token'              => '',
				'v2_conectado'          => false,
				'v2_conta'              => '',
				'loja_identificador'    => is_string( $host ) ? $host : '',
				'map_atributos'         => [],
				/*
				 * Recebimento em dois interruptores, ambos desligados por padrão.
				 * No Vapor Hub o Tiny é o hub: puxar um item com SKU da loja +
				 * categoria mapeada pode criar produto simples. Webhook de id
				 * desconhecido e reconciliação NÃO paginam o ERP inteiro.
				 */
				'receber_catalogo'      => false,
				'receber_estoque_preco' => false,
				/* Assinatura do SKU da loja dentro da conta compartilhada do ERP. */
				'sku_prefixo'           => VH_Tiny_SKU::PREFIXO_PADRAO,
				/* Raiz que abriga a árvore de categorias da loja no ERP. */
				'categoria_raiz'        => 'Loja Vapor Hub',
				'categoria_raiz_id'     => 0,
			]
		);
	}

	public static function loja_identificador(): string {
		$dados = self::obter();
		$id    = sanitize_text_field( (string) ( $dados['loja_identificador'] ?? '' ) );
		if ( '' !== $id ) {
			return $id;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? $host : 'loja';
	}

	public static function modo(): string {
		$dados = self::obter();
		$modo  = sanitize_key( (string) ( $dados['modo'] ?? 'v3' ) );
		return in_array( $modo, [ 'v2', 'v3' ], true ) ? $modo : 'v3';
	}

	public static function definir_modo( string $modo ): void {
		$modo  = sanitize_key( $modo );
		$dados = self::obter();
		$dados['modo'] = in_array( $modo, [ 'v2', 'v3' ], true ) ? $modo : 'v3';
		update_option( self::OPTION, $dados, false );
		self::$driver_instancia  = null;
		self::$driver_modo_cache = '';
	}

	public static function driver(): VH_Tiny_Driver_Interface {
		$modo = self::modo();
		if ( null === self::$driver_instancia || self::$driver_modo_cache !== $modo ) {
			self::$driver_modo_cache = $modo;
			self::$driver_instancia  = 'v2' === $modo ? new VH_Tiny_Driver_V2() : new VH_Tiny_Driver_V3();
		}
		return self::$driver_instancia;
	}

	public const HEADER_WEBHOOK = 'X-PA-Tiny-Token';

	public static function webhook_url(): string {
		$token = self::webhook_token();
		if ( '' === $token ) {
			return rest_url( VH_REST_Controller::NS . '/public/tiny/webhook' );
		}
		return rest_url( VH_REST_Controller::NS . '/public/tiny/webhook/' . rawurlencode( $token ) );
	}

	/**
	 * Token em claro, para exibir no painel e colar no cadastro do ERP.
	 *
	 * O que fica no banco é o sha256; a versão em claro mora cifrada, com a mesma
	 * chave dos tokens OAuth. Instalação antiga guardava o valor puro no campo
	 * `webhook_token_hash` — esse caso continua sendo lido até a próxima rotação.
	 */
	public static function webhook_token(): string {
		$dados = VH_Tiny_Client::obter();

		$cifrado = (string) ( $dados['webhook_token_cifrado'] ?? '' );
		if ( '' !== $cifrado ) {
			$claro = VH_SEO_Crypto::descriptografar( $cifrado );
			if ( is_string( $claro ) && '' !== $claro ) {
				return $claro;
			}
		}

		$guardado = (string) ( $dados['webhook_token_hash'] ?? '' );
		return self::e_hash_sha256( $guardado ) ? '' : $guardado;
	}

	public static function garantir_webhook_token(): void {
		$dados = VH_Tiny_Client::obter();
		if ( empty( $dados['webhook_token_hash'] ) ) {
			self::rotacionar_webhook_token();
		}
	}

	/**
	 * Gera token novo, invalidando o anterior. Devolve o valor em claro — é a
	 * única hora em que ele existe fora do cofre.
	 */
	public static function rotacionar_webhook_token(): string {
		$token = wp_generate_password( 32, false, false );
		self::gravar_webhook_token( $token );

		return $token;
	}

	/**
	 * Confere o token recebido (header ou URL) contra o hash guardado.
	 *
	 * Instalação que ainda guarda o valor puro é aceita uma vez e migrada na hora
	 * para hash: o Tiny continua chamando a URL antiga sem quebrar.
	 */
	public static function validar_webhook_token( string $recebido ): bool {
		$recebido = trim( $recebido );
		if ( '' === $recebido ) {
			return false;
		}

		$dados    = VH_Tiny_Client::obter();
		$guardado = (string) ( $dados['webhook_token_hash'] ?? '' );
		if ( '' === $guardado ) {
			return false;
		}

		if ( self::e_hash_sha256( $guardado ) ) {
			return hash_equals( $guardado, hash( 'sha256', $recebido ) );
		}

		if ( ! hash_equals( $guardado, $recebido ) ) {
			return false;
		}

		self::gravar_webhook_token( $recebido );
		return true;
	}

	private static function gravar_webhook_token( string $token ): void {
		$dados = VH_Tiny_Client::obter();

		$dados['webhook_token_hash']    = hash( 'sha256', $token );
		$dados['webhook_token_cifrado'] = VH_SEO_Crypto::criptografar( $token );

		update_option( self::OPTION, $dados, false );
	}

	private static function e_hash_sha256( string $valor ): bool {
		return 64 === strlen( $valor ) && (bool) preg_match( '/^[a-f0-9]{64}$/', $valor );
	}

	/**
	 * Gravação parcial: só mexe no que veio em `$config`.
	 *
	 * A tela de configuração manda o formulário inteiro, mas serviços internos
	 * chamam com uma chave só — a raiz de categoria, o mapa de atributos. Ler
	 * `ativo` de um array que nunca o trouxe desligava a integração no meio de
	 * uma sincronização.
	 */
	public static function salvar_config( array $config ): void {
		$dados = wp_parse_args( self::obter(), self::padrao() );

		if ( array_key_exists( 'ativo', $config ) ) {
			$dados['ativo'] = ! empty( $config['ativo'] );
		}
		if ( array_key_exists( 'sinc_auto', $config ) ) {
			$dados['sinc_auto'] = ! empty( $config['sinc_auto'] );
		}

		if ( array_key_exists( 'permitir_envio', $config ) ) {
			$dados['permitir_envio'] = ! empty( $config['permitir_envio'] );
		}
		if ( array_key_exists( 'permitir_recebimento', $config ) ) {
			$dados['permitir_recebimento']    = ! empty( $config['permitir_recebimento'] );
			$dados['receber_catalogo']        = $dados['permitir_recebimento'];
			$dados['receber_estoque_preco']   = $dados['permitir_recebimento'];
		}

		if ( array_key_exists( 'receber_catalogo', $config ) ) {
			$dados['receber_catalogo'] = ! empty( $config['receber_catalogo'] );
		}
		if ( array_key_exists( 'receber_estoque_preco', $config ) ) {
			$dados['receber_estoque_preco'] = ! empty( $config['receber_estoque_preco'] );
		}

		/* Espelho legado, para telas e integrações que ainda leem a chave antiga. */
		$dados['permitir_recebimento'] = ! empty( $dados['receber_catalogo'] ) || ! empty( $dados['receber_estoque_preco'] );

		if ( array_key_exists( 'sku_prefixo', $config ) ) {
			$dados['sku_prefixo'] = VH_Tiny_SKU::normalizar_prefixo( (string) $config['sku_prefixo'] );
		}

		if ( array_key_exists( 'categoria_raiz', $config ) ) {
			$raiz = sanitize_text_field( (string) $config['categoria_raiz'] );
			$raiz = mb_substr( trim( $raiz ), 0, 80 );
			/* Raiz trocada de nome é raiz nova: o id antigo aponta para outra árvore. */
			if ( $raiz !== (string) ( $dados['categoria_raiz'] ?? '' ) ) {
				$dados['categoria_raiz_id'] = 0;
			}
			$dados['categoria_raiz'] = $raiz;
		}

		if ( array_key_exists( 'categoria_raiz_id', $config ) ) {
			$dados['categoria_raiz_id'] = absint( $config['categoria_raiz_id'] );
		}

		if ( array_key_exists( 'loja_identificador', $config ) ) {
			$id = sanitize_text_field( (string) $config['loja_identificador'] );
			$id = preg_replace( '/[^a-zA-Z0-9.\-_]/', '', $id ) ?? '';
			$dados['loja_identificador'] = substr( $id, 0, 120 );
		}

		foreach ( array_keys( self::travas_padrao() ) as $trava ) {
			if ( array_key_exists( $trava, $config ) ) {
				$dados[ $trava ] = ! empty( $config[ $trava ] );
			}
		}

		if ( array_key_exists( 'import_raizes', $config ) ) {
			$ids = [];
			foreach ( (array) $config['import_raizes'] as $id_raiz ) {
				$id_raiz = absint( $id_raiz );
				if ( $id_raiz > 0 ) {
					$ids[] = $id_raiz;
				}
			}
			$dados['import_raizes'] = array_values( array_unique( $ids ) );
		}

		if ( array_key_exists( 'import_marcas', $config ) ) {
			$marcas = [];
			$bruto  = $config['import_marcas'];
			$lista  = is_array( $bruto ) ? $bruto : preg_split( '/\s*,\s*/', (string) $bruto );
			foreach ( (array) $lista as $marca ) {
				$marca = sanitize_text_field( (string) $marca );
				if ( '' !== $marca ) {
					$marcas[] = $marca;
				}
			}
			$dados['import_marcas'] = array_values( array_unique( $marcas ) );
		}

		if ( array_key_exists( 'map_atributos', $config ) && is_array( $config['map_atributos'] ) ) {
			$mapa = [];
			foreach ( $config['map_atributos'] as $taxonomy => $rotulo ) {
				$tax = sanitize_key( (string) $taxonomy );
				if ( '' === $tax ) {
					continue;
				}
				$mapa[ $tax ] = sanitize_text_field( (string) $rotulo );
			}
			$dados['map_atributos'] = $mapa;
		}

		update_option( self::OPTION, $dados, false );
	}

	public static function registrar_erro( string $mensagem ): void {
		VH_Tiny_Client::registrar_erro( $mensagem );
	}

	public static function limpar_erro(): void {
		VH_Tiny_Client::limpar_erro();
	}

	/**
	 * Loja → Tiny está autorizado (envio).
	 */
	/**
	 * Campos que a política deixa trafegar. Conteúdo nasce na importação e não volta.
	 *
	 * @return array<string, bool>
	 */
	public static function travas_padrao(): array {
		return [
			'entrada_nome'      => false,
			'entrada_descricao' => false,
			'entrada_categoria' => false,
			'entrada_preco'     => false,
			'entrada_imagem'    => false,
			'entrada_grade'     => false,
			'entrada_estoque'   => true,
			'saida_nome'        => false,
			'saida_descricao'   => false,
			'saida_categoria'   => false,
			'saida_preco'       => false,
			'saida_imagem'      => false,
			'saida_grade'       => false,
			'saida_estoque'     => true,
			'saida_pedido'      => true,
		];
	}

	/**
	 * A trava é do servidor. Chave explícita na opção vence. Sem ela, estoque e
	 * pedido herdam os interruptores antigos; o restante do cadastro fica fechado.
	 */
	public static function campo_liberado( string $campo, string $sentido ): bool {
		$campo   = sanitize_key( $campo );
		$sentido = 'saida' === $sentido ? 'saida' : 'entrada';
		$chave   = $sentido . '_' . $campo;
		$bruto   = VH_Tiny_Client::obter();
		$padrao  = self::travas_padrao();

		if ( ! array_key_exists( $chave, $padrao ) ) {
			return false;
		}

		if ( array_key_exists( $chave, $bruto ) ) {
			return ! empty( $bruto[ $chave ] );
		}

		if ( 'entrada' === $sentido && in_array( $campo, [ 'estoque', 'preco' ], true ) ) {
			if ( array_key_exists( 'receber_estoque_preco', $bruto ) ) {
				return ! empty( $bruto['receber_estoque_preco'] );
			}
			return ! empty( $bruto['permitir_recebimento'] );
		}

		if ( 'saida' === $sentido && in_array( $campo, [ 'estoque', 'pedido' ], true ) && array_key_exists( 'permitir_envio', $bruto ) ) {
			return ! empty( $bruto['permitir_envio'] );
		}

		return ! empty( $padrao[ $chave ] );
	}

	public static function pode_enviar_cadastro(): bool {
		foreach ( [ 'nome', 'descricao', 'categoria', 'preco', 'imagem', 'grade' ] as $campo ) {
			if ( self::campo_liberado( $campo, 'saida' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function pode_receber_vinculo(): bool {
		foreach ( [ 'estoque', 'preco', 'nome', 'descricao', 'categoria', 'imagem', 'grade' ] as $campo ) {
			if ( self::campo_liberado( $campo, 'entrada' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Envio de cadastro. Estoque e pedido usam campo_liberado().
	 */
	public static function pode_enviar(): bool {
		return self::pode_enviar_cadastro();
	}

	/**
	 * Tiny → Loja está autorizado em alguma direção de entrada.
	 */
	public static function pode_receber(): bool {
		return self::pode_receber_catalogo() || self::pode_receber_estoque_preco();
	}

	/**
	 * Tiny → Loja para cadastro: criar/atualizar produto, imagem, categoria.
	 */
	public static function pode_receber_catalogo(): bool {
		return ! empty( self::obter()['ativo'] ) && self::interruptor_recebimento( 'receber_catalogo' );
	}

	/**
	 * Tiny → Loja para estoque e preço.
	 */
	public static function pode_receber_estoque_preco(): bool {
		return ! empty( self::obter()['ativo'] ) && self::interruptor_recebimento( 'receber_estoque_preco' );
	}

	/**
	 * Lê o interruptor novo no que está gravado de fato.
	 *
	 * Consulta a opção bruta, não a mesclada com os padrões: instalação que ainda
	 * não salvou a configuração nova precisa herdar da chave antiga
	 * `permitir_recebimento`, e o padrão mesclado esconderia essa ausência.
	 */
	public static function interruptor_recebimento( string $chave ): bool {
		$bruto = VH_Tiny_Client::obter();

		if ( array_key_exists( $chave, $bruto ) ) {
			return ! empty( $bruto[ $chave ] );
		}

		return ! empty( $bruto['permitir_recebimento'] );
	}

	public static function marcar_reconciliacao(): void {
		$dados = self::obter();
		$dados['ultima_reconciliacao'] = VH_DateTime::agora_utc();
		update_option( self::OPTION, $dados, false );
	}

	/**
	 * Exibe data/hora no fuso configurado no WordPress.
	 */
	public static function formatar_data( string $valor ): string {
		return VH_DateTime::formatar_curto( $valor );
	}

	/**
	 * Contexto para o botão "Salvar/Criar e enviar ao Tiny" no formulário de produto.
	 *
	 * @return array{disponivel:bool, modo:string, motivo:string, url_config:string, rotulo_modo:string}
	 */
	public static function contexto_envio_produto(): array {
		$dados     = self::obter();
		$driver    = self::driver();
		$modo      = self::modo();
		$ativo     = ! empty( $dados['ativo'] );
		$conectado = $driver->conectado();
		$rotulo    = 'v2' === $modo ? 'API v2' : 'API v3';

		$motivo = '';
		if ( ! $ativo ) {
			$motivo = __( 'Ative a integração Tiny em Integrações → Tiny ERP.', 'vapor-hub-loja' );
		} elseif ( ! $conectado ) {
			$motivo = 'v2' === $modo
				? __( 'Configure e teste o token da API v2 para conectar ao Tiny.', 'vapor-hub-loja' )
				: __( 'Conecte a conta via OAuth (API v3) para enviar produtos ao Tiny.', 'vapor-hub-loja' );
		} elseif ( ! self::pode_enviar_cadastro() ) {
			$motivo = __( 'O envio de cadastro ao Tiny está travado. Estoque e pedido continuam nas travas próprias.', 'vapor-hub-loja' );
		}

		return [
			'disponivel'  => $ativo && $conectado && self::pode_enviar_cadastro(),
			'modo'        => $modo,
			'rotulo_modo' => $rotulo,
			'motivo'      => $motivo,
			'url_config'  => VH_Router::url( 'tiny' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function status_publico(): array {
		$driver = self::driver();
		$fila   = class_exists( 'VH_Tiny_Queue' ) ? VH_Tiny_Queue::contagem_por_status() : [];

		$status = self::filtrar_segredos(
			array_merge(
				$driver->status_driver(),
				[
					'modo'                      => self::modo(),
					'webhook_url'               => self::webhook_url(),
					'fila'                      => $fila,
					'pendencias'                => class_exists( 'VH_Tiny_Sync_Service' ) ? VH_Tiny_Sync_Service::contar_pendencias() : 0,
					'alteracoes_locais_pendentes' => class_exists( 'VH_Tiny_Sync_Service' ) ? VH_Tiny_Sync_Service::contar_alteracoes_locais_pendentes() : 0,
					'categorias_sem_vinculo'    => class_exists( 'VH_Tiny_Sync_Service' ) ? VH_Tiny_Sync_Service::contar_categorias_sem_vinculo() : 0,
					'variacoes_sem_sku'         => class_exists( 'VH_Tiny_Sync_Service' ) ? VH_Tiny_Sync_Service::contar_variacoes_sem_sku() : 0,
					'loja_identificador'        => self::loja_identificador(),
					'sku_prefixo'               => VH_Tiny_SKU::prefixo(),
					'categoria_raiz'            => (string) ( self::obter()['categoria_raiz'] ?? '' ),
					'categoria_raiz_id'         => (int) ( self::obter()['categoria_raiz_id'] ?? 0 ),
				]
			)
		);

		$dados = self::obter();
		$status['ativo']                = ! empty( $dados['ativo'] );
		$status['operacional']          = ! empty( $dados['ativo'] ) && ! empty( $status['conectado'] );
		$status['conta_cnpj']           = VH_Tiny_Client::normalizar_cnpj( (string) ( $dados['conta_cnpj'] ?? '' ) );
		$status['conta_cnpj_fmt']       = VH_Tiny_Client::formatar_cnpj( (string) ( $dados['conta_cnpj'] ?? '' ) );
		$status['conta_bloqueada']      = ! empty( $dados['conta_bloqueada'] );
		$status['sinc_auto']            = ! empty( $dados['sinc_auto'] );
		$status['permitir_envio']        = ! empty( $dados['permitir_envio'] );
		$status['travas']               = [];
		foreach ( array_keys( self::travas_padrao() ) as $trava ) {
			$partes = explode( '_', $trava, 2 );
			$status['travas'][ $trava ] = self::campo_liberado( $partes[1], $partes[0] );
		}
		$status['import_raizes']        = array_map( 'intval', (array) ( $dados['import_raizes'] ?? [] ) );
		$status['import_marcas']        = array_values( (array) ( $dados['import_marcas'] ?? [] ) );
		$status['import_offset']        = (int) get_option( 'vh_tiny_import_offset', 0 );
		$status['receber_catalogo']     = self::interruptor_recebimento( 'receber_catalogo' );
		$status['receber_estoque_preco'] = self::interruptor_recebimento( 'receber_estoque_preco' );
		$status['permitir_recebimento'] = $status['receber_catalogo'] || $status['receber_estoque_preco'];
		$status['ultima_reconciliacao'] = (string) ( $dados['ultima_reconciliacao'] ?? '' );
		$status['ultimo_erro']          = (string) ( $dados['ultimo_erro'] ?? '' );
		$status['v2_token_configurado'] = ! empty( $dados['v2_token'] );
		$status['client_secret_configurado'] = ! empty( $dados['client_secret'] );

		/* Só o painel do lojista mostra o token; a REST já exige capacidade para ler. */
		$status['webhook_token']        = self::webhook_token();
		$status['webhook_token_header'] = self::HEADER_WEBHOOK;

		return $status;
	}

	/**
	 * Remove segredos de respostas públicas (REST / painel).
	 *
	 * @param array<string, mixed> $dados
	 * @return array<string, mixed>
	 */
	private static function filtrar_segredos( array $dados ): array {
		foreach (
			[
				'v2_token',
				'access_token',
				'refresh_token',
				'client_secret',
				'webhook_token_hash',
				'webhook_token_cifrado',
			] as $chave
		) {
			unset( $dados[ $chave ] );
		}
		return $dados;
	}
}
