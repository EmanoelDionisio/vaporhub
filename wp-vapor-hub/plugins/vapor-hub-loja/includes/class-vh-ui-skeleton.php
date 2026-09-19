<?php
/**
 * Design System — skeleton loading (Minha Loja).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_UI_Skeleton {

	public const PRESET_TOOLBAR_TABLE_5 = 'toolbar-table-5';
	public const PRESET_TABLE_3         = 'table-3';
	public const PRESET_TABLE_4         = 'table-4';
	public const PRESET_FORM            = 'form';
	public const PRESET_CARDS           = 'cards';

	/**
	 * Renderiza um painel skeleton padronizado.
	 *
	 * @param string               $element_id ID único do elemento (obrigatório).
	 * @param string               $preset     Preset registrado (ver constantes).
	 * @param array<string, mixed> $args       rows, fields, show_btn, columns.
	 */
	public static function render( string $element_id, string $preset, array $args = [] ): void {
		$element_id = sanitize_html_class( $element_id );
		if ( '' === $element_id ) {
			return;
		}

		$defaults = [
			'rows'     => self::rows_padrao( $preset ),
			'fields'   => 3,
			'show_btn' => self::PRESET_FORM === $preset,
			'columns'  => self::colunas_padrao( $preset ),
		];

		$args = wp_parse_args( $args, $defaults );

		$vh_skeleton_id       = $element_id;
		$vh_skeleton_preset   = sanitize_key( $preset );
		$vh_skeleton_rows     = max( 1, min( 20, (int) $args['rows'] ) );
		$vh_skeleton_fields   = max( 1, min( 12, (int) $args['fields'] ) );
		$vh_skeleton_show_btn = ! empty( $args['show_btn'] );
		$vh_skeleton_columns  = max( 2, min( 5, (int) $args['columns'] ) );

		require VH_LOJA_DIR . 'admin/views/partials/skeleton-panel.php';
	}

	/**
	 * @return array<string, string>
	 */
	public static function presets(): array {
		return [
			self::PRESET_TOOLBAR_TABLE_5 => __( 'Toolbar + tabela (5 colunas)', 'vapor-hub-loja' ),
			self::PRESET_TABLE_3         => __( 'Tabela (3 colunas)', 'vapor-hub-loja' ),
			self::PRESET_TABLE_4         => __( 'Tabela (4 colunas)', 'vapor-hub-loja' ),
			self::PRESET_FORM            => __( 'Formulário (labels + inputs)', 'vapor-hub-loja' ),
			self::PRESET_CARDS           => __( 'Grade de cards', 'vapor-hub-loja' ),
		];
	}

	private static function rows_padrao( string $preset ): int {
		switch ( $preset ) {
			case self::PRESET_TOOLBAR_TABLE_5:
				return 5;
			case self::PRESET_TABLE_3:
				return 6;
			case self::PRESET_TABLE_4:
				return 5;
			case self::PRESET_FORM:
				return 3;
			case self::PRESET_CARDS:
				return 4;
			default:
				return 4;
		}
	}

	private static function colunas_padrao( string $preset ): int {
		switch ( $preset ) {
			case self::PRESET_TOOLBAR_TABLE_5:
				return 5;
			case self::PRESET_TABLE_4:
				return 4;
			case self::PRESET_TABLE_3:
				return 3;
			default:
				return 3;
		}
	}
}
