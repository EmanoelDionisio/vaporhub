<?php
/**
 * Geração de SKU para produtos variáveis (Tiny ERP).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny_SKU {

	public const PREFIXO_PADRAO = 'VH-';

	/**
	 * Assinatura do SKU da loja, sempre terminando em hífen.
	 *
	 * A conta do Tiny é compartilhada com outras operações. O prefixo é o que
	 * permite dizer, olhando só o código, se um produto do ERP é da loja. Vazio
	 * desliga a trava e volta ao casamento por SKU puro.
	 */
	public static function prefixo(): string {
		$dados = VH_Tiny::obter();

		return self::normalizar_prefixo( (string) ( $dados['sku_prefixo'] ?? self::PREFIXO_PADRAO ) );
	}

	public static function normalizar_prefixo( string $prefixo ): string {
		$limpo = strtoupper( preg_replace( '/[^A-Za-z0-9_\-]/', '', $prefixo ) ?? '' );
		$limpo = trim( $limpo, '-' );

		return '' === $limpo ? '' : $limpo . '-';
	}

	/**
	 * SKU nasce (e continua) com a assinatura da loja.
	 */
	public static function aplicar_prefixo( string $sku ): string {
		$sku = trim( $sku );
		if ( '' === $sku || self::da_loja( $sku ) ) {
			return $sku;
		}

		return self::prefixo() . $sku;
	}

	/**
	 * O código segue o padrão da loja?
	 *
	 * Com prefixo vazio a resposta é sim para qualquer SKU preenchido: a trava
	 * fica desligada e o comportamento antigo volta inteiro.
	 */
	public static function da_loja( string $sku ): bool {
		$sku = trim( $sku );
		if ( '' === $sku ) {
			return false;
		}

		$prefixo = self::prefixo();
		if ( '' === $prefixo ) {
			return true;
		}

		return 0 === strncasecmp( $sku, $prefixo, strlen( $prefixo ) );
	}

	/**
	 * Garante SKU no produto pai (obrigatório para variações).
	 */
	public static function garantir_sku_pai( WC_Product_Variable $pai ): string {
		$sku = $pai->get_sku();
		if ( '' !== $sku ) {
			return $sku;
		}

		$base = sanitize_title( $pai->get_slug() ?: $pai->get_name() );
		if ( '' === $base ) {
			$base = 'produto-' . $pai->get_id();
		}
		$sku = self::unico( self::aplicar_prefixo( strtoupper( $base ) ) );

		try {
			$pai->set_sku( $sku );
			$pai->save();
		} catch ( WC_Data_Exception $e ) {
			unset( $e );
			$sku = self::unico( self::aplicar_prefixo( strtoupper( $base . '-' . $pai->get_id() ) ) );
			$pai->set_sku( $sku );
			$pai->save();
		}

		return $sku;
	}

	/**
	 * Garante SKU no pai e em todas as variações (passo de escrita, idempotente).
	 *
	 * Centraliza a geração de SKU para que a montagem do canônico permaneça
	 * somente leitura.
	 */
	public static function garantir_skus_produto( WC_Product_Variable $pai ): void {
		self::garantir_sku_pai( $pai );

		foreach ( $pai->get_children() as $variacao_id ) {
			$variacao = wc_get_product( (int) $variacao_id );
			if ( ! $variacao instanceof WC_Product_Variation ) {
				continue;
			}
			$sku = $variacao->get_sku();
			if ( '' === $sku ) {
				$novo = self::gerar_variacao( $pai, $variacao->get_attributes(), (int) $variacao_id );
				if ( '' !== $novo ) {
					try {
						$variacao->set_sku( $novo );
						$variacao->save();
					} catch ( WC_Data_Exception $e ) {
						unset( $e );
					}
				}
			}
		}
	}

	/**
	 * Gera SKU determinístico para uma variação.
	 *
	 * @param array<string, string> $atributos taxonomy => slug
	 */
	public static function gerar_variacao( WC_Product_Variable $pai, array $atributos, int $variacao_id = 0 ): string {
		$sku_pai = self::garantir_sku_pai( $pai );
		$partes  = [];

		foreach ( $atributos as $slug ) {
			$partes[] = strtoupper( sanitize_title( (string) $slug ) );
		}

		$sufixo = $partes ? implode( '-', $partes ) : (string) max( 1, $variacao_id );
		$base   = $sku_pai . '-' . $sufixo;

		return self::unico( $base, $variacao_id );
	}

	private static function unico( string $sku, int $ignorar_id = 0 ): string {
		$sku = wc_clean( $sku );
		if ( '' === $sku ) {
			return $sku;
		}

		$existente = wc_get_product_id_by_sku( $sku );
		if ( $existente <= 0 || $existente === $ignorar_id ) {
			return $sku;
		}

		$tentativa = 2;
		do {
			$candidato = $sku . '-' . $tentativa;
			$existente = wc_get_product_id_by_sku( $candidato );
			++$tentativa;
		} while ( $existente > 0 && $existente !== $ignorar_id && $tentativa < 100 );

		return $candidato ?? $sku;
	}
}
