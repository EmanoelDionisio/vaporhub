<?php
/**
 * Perfis de dimensionamento para crop/upload otimizado de imagens.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Media_Profiles {

    /**
     * @return array<string,array{width:int,height:int,label:string,ratio:float}>
     */
    public static function todos(): array {
        return [
            'produto'       => [
                'width'  => 800,
                'height' => 800,
                'label'  => __( 'Produto (800×800)', 'vapor-hub-loja' ),
                'ratio'  => 1.0,
            ],
            'comunidade'    => [
                'width'  => 640,
                'height' => 800,
                'label'  => __( 'Comunidade (640×800)', 'vapor-hub-loja' ),
                'ratio'  => 0.8,
            ],
            'categoria'     => [
                'width'  => 640,
                'height' => 800,
                'label'  => __( 'Categoria (640×800)', 'vapor-hub-loja' ),
                'ratio'  => 0.8,
            ],
            'hero-desktop'  => [
                'width'  => 1920,
                'height' => 800,
                'label'  => __( 'Banner desktop (1920×800)', 'vapor-hub-loja' ),
                'ratio'  => 2.4,
            ],
            'hero-mobile'   => [
                'width'  => 1080,
                'height' => 1350,
                'label'  => __( 'Banner celular (1080×1350)', 'vapor-hub-loja' ),
                'ratio'  => 0.8,
            ],
            'logo'          => [
                'width'  => 400,
                'height' => 160,
                'label'  => __( 'Logo (até 400×160)', 'vapor-hub-loja' ),
                'ratio'  => 2.5,
                /*
                 * O recorte acompanha a arte. A saída cabe em 400×160 sem esticar.
                 * As três marcas do painel usam este mesmo perfil.
                 */
                'proporcional'            => true,
                /* Mantém canal alpha (PNG → WebP com transparência). */
                'preservar_transparencia' => true,
            ],
            'favicon'       => [
                'width'  => 512,
                'height' => 512,
                'label'  => __( 'Favicon (512×512)', 'vapor-hub-loja' ),
                'ratio'  => 1.0,
                'preservar_transparencia' => true,
            ],
        ];
    }

    /**
     * @return array{width:int,height:int,label:string,ratio:float}|null
     */
    public static function obter( string $slug ): ?array {
        $slug = sanitize_key( $slug );
        $todos = self::todos();
        return $todos[ $slug ] ?? null;
    }

    /**
     * Redimensiona e recorta um arquivo de imagem para o perfil informado.
     */
    public static function aplicar_perfil( string $caminho, string $slug ): bool|WP_Error {
        $perfil = self::obter( $slug );
        if ( ! $perfil ) {
            return new WP_Error( 'perfil_invalido', __( 'Perfil de imagem inválido.', 'vapor-hub-loja' ) );
        }

        if ( ! file_exists( $caminho ) ) {
            return new WP_Error( 'arquivo_ausente', __( 'Arquivo de imagem não encontrado.', 'vapor-hub-loja' ) );
        }

        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            return true;
        }

        $editor = wp_get_image_editor( $caminho );
        if ( is_wp_error( $editor ) ) {
            return $editor;
        }

        $tamanho = $editor->get_size();
        if ( is_wp_error( $tamanho ) || empty( $tamanho['width'] ) || empty( $tamanho['height'] ) ) {
            return new WP_Error( 'leitura_falhou', __( 'Não foi possível ler a imagem enviada.', 'vapor-hub-loja' ) );
        }

        $dest_w = (int) $perfil['width'];
        $dest_h = (int) $perfil['height'];
        $src_w  = (int) $tamanho['width'];
        $src_h  = (int) $tamanho['height'];

        /*
         * Logo: o navegador já entrega a arte proporcional, no máximo 400×160.
         * Reprocessar no GD achata a transparência e forçaria de novo o retângulo fixo.
         */
        if ( ! empty( $perfil['proporcional'] ) ) {
            if ( $src_w <= $dest_w && $src_h <= $dest_h ) {
                return true;
            }

            $redim = $editor->resize( $dest_w, $dest_h, false );
            if ( is_wp_error( $redim ) ) {
                return $redim;
            }

            $salvo = $editor->save( $caminho );
            if ( is_wp_error( $salvo ) ) {
                return $salvo;
            }

            return true;
        }

        /*
         * Logo no tamanho exato: o cropper já exportou PNG 400×160 com transparência.
         * Reprocessar no editor GD costuma achatar o alpha (fundo preto).
         */
        if ( ! empty( $perfil['preservar_transparencia'] ) && $src_w === $dest_w && $src_h === $dest_h ) {
            return true;
        }

        $ratio_dest = $dest_w / $dest_h;
        $ratio_src  = $src_w / $src_h;

        if ( abs( $ratio_src - $ratio_dest ) > 0.01 ) {
            if ( $ratio_src > $ratio_dest ) {
                $crop_w = (int) round( $src_h * $ratio_dest );
                $crop_h = $src_h;
                $crop_x = (int) round( ( $src_w - $crop_w ) / 2 );
                $crop_y = 0;
            } else {
                $crop_w = $src_w;
                $crop_h = (int) round( $src_w / $ratio_dest );
                $crop_x = 0;
                $crop_y = (int) round( ( $src_h - $crop_h ) / 2 );
            }
            $cortado = $editor->crop( $crop_x, $crop_y, $crop_w, $crop_h );
            if ( is_wp_error( $cortado ) ) {
                return $cortado;
            }
        }

        $redim = $editor->resize( $dest_w, $dest_h, true );
        if ( is_wp_error( $redim ) ) {
            return $redim;
        }

        $salvo = $editor->save( $caminho );
        if ( is_wp_error( $salvo ) ) {
            return $salvo;
        }

        return true;
    }

    /**
     * Dados públicos para localização JS (sem lógica sensível).
     *
     * @return array<string,array{width:int,height:int,label:string,ratio:float}>
     */
    public static function para_js(): array {
        $saida = [];
        foreach ( self::todos() as $slug => $perfil ) {
            $saida[ $slug ] = [
                'width'  => (int) $perfil['width'],
                'height' => (int) $perfil['height'],
                'label'  => (string) $perfil['label'],
                'ratio'  => (float) $perfil['ratio'],
                'transparente' => ! empty( $perfil['preservar_transparencia'] ),
                'proporcional' => ! empty( $perfil['proporcional'] ),
            ];
        }
        return $saida;
    }

    /**
     * Enfileira Cropper.js e o script do modal (painel Minha Loja).
     */
    public static function enqueue_assets(): void {
        wp_enqueue_style(
            'cropperjs',
            'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css',
            [],
            '1.6.2'
        );
        wp_enqueue_script(
            'cropperjs',
            'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js',
            [],
            '1.6.2',
            true
        );
        wp_enqueue_script(
            'vh-media-crop',
            VH_LOJA_ASSETS . 'js/media-crop.js',
            [ 'cropperjs' ],
            VH_LOJA_VERSION,
            true
        );
        wp_localize_script(
            'vh-media-crop',
            'paMediaCropConfig',
            [
                'restUrl'  => esc_url_raw( rest_url( VH_REST_Controller::NS . '/media/upload' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'profiles' => self::para_js(),
                'i18n'     => [
                    'titulo'           => __( 'Ajustar imagem', 'vapor-hub-loja' ),
                    'origemTitulo'     => __( 'Adicionar imagem', 'vapor-hub-loja' ),
                    'origemDescricao'  => __( 'Envie uma nova imagem (será otimizada automaticamente) ou escolha uma já existente na biblioteca.', 'vapor-hub-loja' ),
                    'enviarNova'       => __( 'Enviar nova imagem', 'vapor-hub-loja' ),
                    'biblioteca'       => __( 'Escolher da biblioteca', 'vapor-hub-loja' ),
                    'aplicar'          => __( 'Usar imagem', 'vapor-hub-loja' ),
                    'cancelar'         => __( 'Cancelar', 'vapor-hub-loja' ),
                    'enviando'         => __( 'Enviando…', 'vapor-hub-loja' ),
                    'erroUpload'       => __( 'Não foi possível enviar a imagem. Tente novamente.', 'vapor-hub-loja' ),
                    'erroArquivo'      => __( 'Selecione um arquivo de imagem válido (JPEG, PNG ou WebP).', 'vapor-hub-loja' ),
                    'arraste'          => __( 'Arraste para enquadrar. A imagem será salva no tamanho ideal para o site.', 'vapor-hub-loja' ),
                    'arrasteLogo'      => __( 'Arraste para enquadrar. A marca cabe em até 400×160, proporcional, sem esticar.', 'vapor-hub-loja' ),
                ],
            ]
        );
    }
}
