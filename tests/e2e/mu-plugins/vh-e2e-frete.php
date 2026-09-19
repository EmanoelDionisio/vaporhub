<?php
/**
 * Plugin Name: PA Frete por peso (E2E)
 * Description: Método de frete determinístico que cobra por peso e volume do pacote. Fica no lugar dos Correios nos testes: lê os mesmos campos nativos do WooCommerce, sem depender de rede.
 *
 * @package VaporHubLoja\Tests\E2E
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'VH_E2E' ) || ! VH_E2E ) {
	return;
}

add_action(
	'woocommerce_shipping_init',
	static function (): void {
		if ( class_exists( 'VH_E2E_Frete_Peso' ) || ! class_exists( 'WC_Shipping_Method' ) ) {
			return;
		}

		class VH_E2E_Frete_Peso extends WC_Shipping_Method {

			public function __construct( $instance_id = 0 ) {
				$this->id                 = 'vh_e2e_peso';
				$this->instance_id        = absint( $instance_id );
				$this->method_title       = 'Frete por peso (E2E)';
				$this->method_description = 'Cobra por quilo e por volume do pacote. Só existe no ambiente de teste.';
				$this->supports           = [ 'shipping-zones', 'instance-settings' ];
				$this->enabled            = 'yes';
				$this->title              = 'Frete por peso (E2E)';

				$this->init_form_fields();
				$this->init_settings();
			}

			public function init_form_fields(): void {
				$this->instance_form_fields = [
					'title' => [
						'title'   => 'Título',
						'type'    => 'text',
						'default' => 'Frete por peso (E2E)',
					],
				];
			}

			/**
			 * @param array<string, mixed> $package
			 */
			public function calculate_shipping( $package = [] ): void {
				$peso   = 0.0;
				$cubico = 0.0;

				foreach ( (array) ( $package['contents'] ?? [] ) as $item ) {
					$produto = $item['data'] ?? null;
					if ( ! $produto instanceof WC_Product ) {
						continue;
					}
					$qtd     = (int) ( $item['quantity'] ?? 1 );
					$peso   += (float) $produto->get_weight() * $qtd;
					$cubico += (float) $produto->get_width() * (float) $produto->get_height() * (float) $produto->get_length() * $qtd;
				}

				$custo = 10 + ( $peso * 5 ) + ( $cubico * 0.01 );

				$this->add_rate(
					[
						'id'    => $this->id,
						'label' => $this->title,
						'cost'  => round( $custo, 2 ),
						'meta_data' => [
							'peso_pacote'   => $peso,
							'cubico_pacote' => $cubico,
						],
					]
				);
			}
		}
	}
);

add_filter(
	'woocommerce_shipping_methods',
	static function ( array $metodos ): array {
		$metodos['vh_e2e_peso'] = 'VH_E2E_Frete_Peso';
		return $metodos;
	}
);
