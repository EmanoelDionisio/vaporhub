<?php
/**
 * Otimização de mídia — conversão automática de uploads para WebP.
 *
 * Sempre que uma imagem JPEG ou PNG é enviada (biblioteca de mídia, slides do
 * hero, produtos etc.), ela é convertida para WebP com alta qualidade, gerando
 * arquivos bem mais leves sem perda perceptível. Imagens já em WebP, GIFs
 * (animação) e SVG são preservados.
 *
 * PNGs com transparência (ex.: logo) usam conversão que preserva o canal alpha.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Media {

    /** Qualidade aplicada ao gerar o WebP (alta, sem perda perceptível). */
    private const QUALIDADE_WEBP = 90;

    public static function init(): void {
        add_filter( 'wp_handle_upload', [ __CLASS__, 'converter_para_webp' ] );
        add_filter( 'wp_editor_set_quality', [ __CLASS__, 'qualidade' ], 10, 2 );
    }

    /**
     * Define a qualidade usada ao salvar arquivos WebP.
     *
     * @param int    $qualidade Qualidade atual.
     * @param string $mime      Mime type do arquivo.
     * @return int
     */
    public static function qualidade( $qualidade, $mime ) {
        return ( 'image/webp' === $mime ) ? self::QUALIDADE_WEBP : $qualidade;
    }

    /**
     * Converte o arquivo recém-enviado para WebP, quando aplicável.
     *
     * @param array<string,string> $upload Dados retornados por wp_handle_upload.
     * @return array<string,string>
     */
    public static function converter_para_webp( $upload ) {
        return self::converter_upload( $upload );
    }

    /**
     * Converte upload para WebP com opções (ex.: preservar alpha em PNG).
     *
     * @param array<string,string> $upload Dados do upload.
     * @param array<string,bool>   $opts   preservar_alpha => true força canal alpha.
     * @return array<string,string>
     */
    public static function converter_upload( $upload, array $opts = [] ) {
        if ( empty( $upload['file'] ) || empty( $upload['type'] ) ) {
            return $upload;
        }

        if ( ! in_array( $upload['type'], [ 'image/jpeg', 'image/png' ], true ) ) {
            return $upload;
        }

        if ( ! function_exists( 'wp_get_image_editor' ) || ! function_exists( 'wp_image_editor_supports' ) ) {
            return $upload;
        }

        if ( ! wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) {
            return $upload;
        }

        $preservar_alpha = ! empty( $opts['preservar_alpha'] ) || 'image/png' === $upload['type'];

        if ( $preservar_alpha && 'image/png' === $upload['type'] ) {
            $com_alpha = self::png_para_webp_com_alpha( $upload );
            if ( ! empty( $com_alpha['file'] ) && 'image/webp' === ( $com_alpha['type'] ?? '' ) ) {
                return $com_alpha;
            }
        }

        $origem  = $upload['file'];
        $destino = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $origem );
        if ( ! $destino || $destino === $origem ) {
            return $upload;
        }

        $editor = wp_get_image_editor( $origem );
        if ( is_wp_error( $editor ) ) {
            return $upload;
        }

        $resultado = $editor->save( $destino, 'image/webp' );
        if ( is_wp_error( $resultado ) || empty( $resultado['path'] ) ) {
            return $upload;
        }

        if ( $resultado['path'] !== $origem && file_exists( $origem ) ) {
            @unlink( $origem );
        }

        $upload['file'] = $resultado['path'];
        $upload['url']  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $upload['url'] );
        $upload['type'] = 'image/webp';

        return $upload;
    }

    /**
     * PNG → WebP preservando transparência (GD com alpha explícito).
     *
     * @param array<string,string> $upload Dados do upload.
     * @return array<string,string>
     */
    private static function png_para_webp_com_alpha( array $upload ): array {
        if ( ! function_exists( 'imagecreatefrompng' ) || ! function_exists( 'imagewebp' ) ) {
            return $upload;
        }

        $origem  = $upload['file'];
        $destino = preg_replace( '/\.png$/i', '.webp', $origem );
        if ( ! $destino || $destino === $origem ) {
            return $upload;
        }

        $img = @imagecreatefrompng( $origem );
        if ( ! $img ) {
            return $upload;
        }

        imagealphablending( $img, false );
        imagesavealpha( $img, true );

        $ok = imagewebp( $img, $destino, self::QUALIDADE_WEBP );
        imagedestroy( $img );

        if ( ! $ok || ! file_exists( $destino ) ) {
            return $upload;
        }

        if ( file_exists( $origem ) ) {
            @unlink( $origem );
        }

        $upload['file'] = $destino;
        $upload['url']  = preg_replace( '/\.png$/i', '.webp', $upload['url'] );
        $upload['type'] = 'image/webp';

        return $upload;
    }
}
