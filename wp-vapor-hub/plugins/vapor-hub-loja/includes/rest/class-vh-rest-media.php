<?php
/**
 * REST — Upload de mídia com perfil de crop/dimensionamento.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Media extends VH_REST_Controller {

    protected string $rest_base = 'media';

    public function register_routes(): void {
        register_rest_route(
            self::NS,
            '/media/upload',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'upload' ],
                'permission_callback' => [ $this, 'permissao_upload' ],
            ]
        );
    }

    public function permissao_upload(): bool {
        return current_user_can( 'upload_files' ) && current_user_can( VH_Roles::CAP );
    }

    /**
     * @param WP_REST_Request $request
     */
    public function upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ) ?: '', 'wp_rest' ) ) {
            return $this->erro( 'nonce_invalido', __( 'Sessão expirada. Atualize a página e tente de novo.', 'vapor-hub-loja' ), 403 );
        }

        $perfil_slug = sanitize_key( (string) $request->get_param( 'perfil' ) );
        $perfil      = VH_Media_Profiles::obter( $perfil_slug );
        if ( ! $perfil ) {
            return $this->erro( 'perfil_invalido', __( 'Perfil de imagem inválido.', 'vapor-hub-loja' ), 400 );
        }

        if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
            return $this->erro( 'arquivo_ausente', __( 'Nenhuma imagem foi enviada.', 'vapor-hub-loja' ), 400 );
        }

        $arquivo = $_FILES['file'];
        if ( ! empty( $arquivo['error'] ) && UPLOAD_ERR_OK !== (int) $arquivo['error'] ) {
            return $this->erro( 'upload_erro', __( 'Erro ao receber a imagem. Tente novamente.', 'vapor-hub-loja' ), 400 );
        }

        $tipos = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        ];

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        /*
         * Upload com perfil: mantém PNG/JPEG original até o crop ser aplicado.
         * A conversão global wp_handle_upload → WebP ocorre cedo demais e, em PNGs
         * sem alpha explícito, achata o fundo transparente.
         */
        $filtro_webp_ativo = has_filter( 'wp_handle_upload', [ 'VH_Media', 'converter_para_webp' ] );
        if ( $filtro_webp_ativo ) {
            remove_filter( 'wp_handle_upload', [ 'VH_Media', 'converter_para_webp' ] );
        }

        $upload = wp_handle_upload(
            $arquivo,
            [
                'test_form' => false,
                'mimes'     => $tipos,
            ]
        );

        if ( $filtro_webp_ativo ) {
            add_filter( 'wp_handle_upload', [ 'VH_Media', 'converter_para_webp' ] );
        }

        if ( isset( $upload['error'] ) ) {
            return $this->erro( 'upload_invalido', $upload['error'], 400 );
        }

        if ( empty( $upload['file'] ) || empty( $upload['url'] ) ) {
            return $this->erro( 'upload_invalido', __( 'Formato de imagem não suportado.', 'vapor-hub-loja' ), 400 );
        }

        $ajuste = VH_Media_Profiles::aplicar_perfil( $upload['file'], $perfil_slug );
        if ( is_wp_error( $ajuste ) ) {
            @unlink( $upload['file'] );
            return $this->erro( $ajuste->get_error_code(), $ajuste->get_error_message(), 400 );
        }

        /* WebP após redimensionamento — logo/PNG preservam transparência. */
        if ( class_exists( 'VH_Media' ) ) {
            $upload = VH_Media::converter_upload(
                $upload,
                [
                    'preservar_alpha' => ! empty( $perfil['preservar_transparencia'] ),
                ]
            );
        }

        $mime = ! empty( $upload['type'] ) ? $upload['type'] : wp_check_filetype( $upload['file'] )['type'];

        $attachment = [
            'post_mime_type' => $mime ?: 'image/webp',
            'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment( $attachment, $upload['file'] );
        if ( is_wp_error( $attach_id ) || ! $attach_id ) {
            @unlink( $upload['file'] );
            return $this->erro( 'attachment_falhou', __( 'Não foi possível registrar a imagem na biblioteca.', 'vapor-hub-loja' ), 500 );
        }

        $metadata = wp_generate_attachment_metadata( (int) $attach_id, $upload['file'] );
        if ( ! is_wp_error( $metadata ) && $metadata ) {
            wp_update_attachment_metadata( (int) $attach_id, $metadata );
        }

        $url_final = wp_get_attachment_url( (int) $attach_id ) ?: $upload['url'];

        return $this->ok(
            [
                'id'      => (int) $attach_id,
                'url'     => $url_final,
                'perfil'  => $perfil_slug,
                'largura' => (int) $perfil['width'],
                'altura'  => (int) $perfil['height'],
                'message' => __( 'Imagem otimizada e salva com sucesso.', 'vapor-hub-loja' ),
            ],
            201
        );
    }
}
