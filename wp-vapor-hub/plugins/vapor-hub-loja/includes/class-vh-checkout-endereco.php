<?php
/**
 * Número e bairro como campos próprios do endereço.
 *
 * Endereço brasileiro separa logradouro, número e bairro; o WooCommerce junta
 * tudo em “Endereço linha 1”. Sem esses dois campos o pedido chega ao ERP com o
 * número no meio da rua e sem bairro — e é exatamente isso que a transportadora
 * e a nota fiscal precisam. Os valores ficam em `_billing_number`,
 * `_billing_neighborhood` e nos equivalentes de entrega, as mesmas chaves que o
 * mercado brasileiro já usa.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Checkout_Endereco {

	public static function init(): void {
		add_filter( 'woocommerce_default_address_fields', [ self::class, 'campos_endereco' ], 20 );
		add_filter( 'woocommerce_admin_billing_fields', [ self::class, 'campos_admin_cobranca' ], 20 );
		add_filter( 'woocommerce_admin_shipping_fields', [ self::class, 'campos_admin_entrega' ], 20 );
		add_filter( 'woocommerce_order_formatted_billing_address', [ self::class, 'formatar_cobranca' ], 20, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', [ self::class, 'formatar_entrega' ], 20, 2 );
		add_filter( 'woocommerce_localisation_address_formats', [ self::class, 'formato_br' ], 20 );
		add_filter( 'woocommerce_formatted_address_replacements', [ self::class, 'substituicoes' ], 20, 2 );
	}

	/**
	 * @param array<string, array<string, mixed>> $campos
	 * @return array<string, array<string, mixed>>
	 */
	public static function campos_endereco( array $campos ): array {
		$numero = [
			'label'        => __( 'Número', 'vapor-hub-loja' ),
			'placeholder'  => __( '123', 'vapor-hub-loja' ),
			'required'     => true,
			'class'        => [ 'form-row-last' ],
			'clear'        => true,
			'priority'     => 51,
			'autocomplete' => 'address-line2',
		];

		$bairro = [
			'label'        => __( 'Bairro', 'vapor-hub-loja' ),
			'placeholder'  => __( 'Centro', 'vapor-hub-loja' ),
			'required'     => true,
			'class'        => [ 'form-row-wide' ],
			'priority'     => 55,
			'autocomplete' => 'address-level3',
		];

		if ( isset( $campos['address_1'] ) ) {
			$campos['address_1']['label']       = __( 'Endereço', 'vapor-hub-loja' );
			$campos['address_1']['placeholder'] = __( 'Rua, avenida, estrada', 'vapor-hub-loja' );
			$campos['address_1']['class']       = [ 'form-row-first' ];
			$campos['address_1']['priority']    = 50;
		}

		$campos['number']       = $numero;
		$campos['neighborhood'] = $bairro;

		if ( isset( $campos['address_2'] ) ) {
			$campos['address_2']['label']    = __( 'Complemento (opcional)', 'vapor-hub-loja' );
			$campos['address_2']['priority'] = 60;
		}

		return $campos;
	}

	/**
	 * @param array<string, array<string, mixed>> $campos
	 * @return array<string, array<string, mixed>>
	 */
	public static function campos_admin_cobranca( array $campos ): array {
		return self::campos_admin( $campos, 'billing' );
	}

	/**
	 * @param array<string, array<string, mixed>> $campos
	 * @return array<string, array<string, mixed>>
	 */
	public static function campos_admin_entrega( array $campos ): array {
		return self::campos_admin( $campos, 'shipping' );
	}

	/**
	 * @param array<string, array<string, mixed>> $campos
	 * @return array<string, array<string, mixed>>
	 */
	private static function campos_admin( array $campos, string $contexto ): array {
		$campos['number'] = [
			'label' => __( 'Número', 'vapor-hub-loja' ),
			'show'  => false,
			'value' => '',
		];
		$campos['neighborhood'] = [
			'label' => __( 'Bairro', 'vapor-hub-loja' ),
			'show'  => false,
			'value' => '',
		];

		unset( $contexto );
		return $campos;
	}

	/**
	 * @param array<string, mixed> $endereco
	 * @return array<string, mixed>
	 */
	public static function formatar_cobranca( array $endereco, WC_Order $pedido ): array {
		return self::acrescentar( $endereco, $pedido, 'billing' );
	}

	/**
	 * @param array<string, mixed> $endereco
	 * @return array<string, mixed>
	 */
	public static function formatar_entrega( array $endereco, WC_Order $pedido ): array {
		return self::acrescentar( $endereco, $pedido, 'shipping' );
	}

	/**
	 * @param array<string, mixed> $endereco
	 * @return array<string, mixed>
	 */
	private static function acrescentar( array $endereco, WC_Order $pedido, string $contexto ): array {
		$endereco['number']       = (string) $pedido->get_meta( '_' . $contexto . '_number' );
		$endereco['neighborhood'] = (string) $pedido->get_meta( '_' . $contexto . '_neighborhood' );

		return $endereco;
	}

	/**
	 * @param array<string, string> $formatos
	 * @return array<string, string>
	 */
	public static function formato_br( array $formatos ): array {
		$formatos['BR'] = "{name}\n{company}\n{address_1}, {number}\n{address_2}\n{neighborhood}\n{city}-{state}\n{postcode}\n{country}";
		return $formatos;
	}

	/**
	 * @param array<string, string> $substituicoes
	 * @param array<string, mixed>  $argumentos
	 * @return array<string, string>
	 */
	public static function substituicoes( array $substituicoes, array $argumentos ): array {
		$substituicoes['{number}']       = (string) ( $argumentos['number'] ?? '' );
		$substituicoes['{neighborhood}'] = (string) ( $argumentos['neighborhood'] ?? '' );

		return $substituicoes;
	}
}
