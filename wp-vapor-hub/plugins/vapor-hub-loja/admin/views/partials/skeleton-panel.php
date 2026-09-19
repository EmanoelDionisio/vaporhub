<?php
/**
 * Partial — painel skeleton (Design System).
 *
 * @package VaporHubLoja
 *
 * @var string $vh_skeleton_id
 * @var string $vh_skeleton_preset
 * @var int    $vh_skeleton_rows
 * @var int    $vh_skeleton_fields
 * @var bool   $vh_skeleton_show_btn
 * @var int    $vh_skeleton_columns
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $vh_skeleton_id ) || empty( $vh_skeleton_preset ) ) {
	return;
}

$row_class = 'vh-skeleton-table__row vh-skeleton-table__row--' . (int) $vh_skeleton_columns;
?>
<div
	id="<?php echo esc_attr( $vh_skeleton_id ); ?>"
	class="vh-skeleton-panel"
	role="status"
	aria-busy="true"
	aria-live="polite"
	data-vh-skeleton-preset="<?php echo esc_attr( $vh_skeleton_preset ); ?>"
>
	<span class="vh-sr-only"><?php esc_html_e( 'Carregando…', 'vapor-hub-loja' ); ?></span>

	<?php if ( VH_UI_Skeleton::PRESET_TOOLBAR_TABLE_5 === $vh_skeleton_preset ) : ?>
		<div class="vh-skeleton-toolbar">
			<span class="vh-skeleton vh-skeleton--line vh-skeleton--w-28"></span>
			<span class="vh-skeleton vh-skeleton--btn"></span>
		</div>
		<div class="vh-skeleton-table">
			<div class="vh-skeleton-table__head">
				<span class="vh-skeleton vh-skeleton--line vh-skeleton--w-full"></span>
			</div>
			<?php for ( $i = 0; $i < $vh_skeleton_rows; $i++ ) : ?>
				<div class="<?php echo esc_attr( $row_class ); ?>">
					<span class="vh-skeleton vh-skeleton--line"></span>
					<span class="vh-skeleton vh-skeleton--line vh-skeleton--w-75"></span>
					<span class="vh-skeleton vh-skeleton--badge"></span>
					<span class="vh-skeleton vh-skeleton--input"></span>
					<span class="vh-skeleton vh-skeleton--input"></span>
				</div>
			<?php endfor; ?>
		</div>

	<?php elseif ( VH_UI_Skeleton::PRESET_FORM === $vh_skeleton_preset ) : ?>
		<div class="vh-skeleton-form">
			<?php for ( $i = 0; $i < $vh_skeleton_fields; $i++ ) : ?>
				<div class="vh-skeleton-form__grupo">
					<span class="vh-skeleton vh-skeleton--line vh-skeleton--w-40"></span>
					<span class="vh-skeleton vh-skeleton--input vh-skeleton--input-lg"></span>
				</div>
			<?php endfor; ?>
			<?php if ( $vh_skeleton_show_btn ) : ?>
				<span class="vh-skeleton vh-skeleton--btn"></span>
			<?php endif; ?>
		</div>

	<?php elseif ( VH_UI_Skeleton::PRESET_CARDS === $vh_skeleton_preset ) : ?>
		<div class="vh-skeleton-cards">
			<?php for ( $i = 0; $i < $vh_skeleton_rows; $i++ ) : ?>
				<span class="vh-skeleton vh-skeleton--card"></span>
			<?php endfor; ?>
		</div>

	<?php else : ?>
		<div class="vh-skeleton-table">
			<div class="vh-skeleton-table__head">
				<span class="vh-skeleton vh-skeleton--line vh-skeleton--w-full"></span>
			</div>
			<?php for ( $i = 0; $i < $vh_skeleton_rows; $i++ ) : ?>
				<div class="<?php echo esc_attr( $row_class ); ?>">
					<?php for ( $c = 0; $c < $vh_skeleton_columns; $c++ ) : ?>
						<span class="vh-skeleton vh-skeleton--line<?php echo 1 === $c % 2 ? ' vh-skeleton--w-75' : ''; ?>"></span>
					<?php endfor; ?>
				</div>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</div>
