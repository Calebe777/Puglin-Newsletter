<?php
/**
 * Gerador dinâmico de imagem destacada usando PHP GD.
 *
 * @package NewsletterAuto
 */

namespace NewsletterAuto;

defined( 'ABSPATH' ) || exit;

/**
 * Gera imagens com título e subtítulo sobre fundo escuro.
 */
class Image_Generator {

    /** Largura da imagem. */
    const WIDTH = 1200;

    /** Altura da imagem. */
    const HEIGHT = 630;

    /**
     * Gera a imagem, faz upload e anexa ao post.
     *
     * @param string $title    Título principal.
     * @param string $subtitle Subtítulo (primeiros 150 chars).
     * @param int    $post_id  ID do post pai.
     * @return int|false ID do attachment ou false.
     */
    public function generate_and_attach( string $title, string $subtitle, int $post_id ) {
        $ai_file = $this->generate_with_ai( $title, $subtitle, $post_id );

        if ( $ai_file ) {
            $filename = basename( $ai_file );
            return $this->insert_attachment( $ai_file, $filename, $post_id );
        }

        if ( ! extension_loaded( 'gd' ) ) {
            return false;
        }

        $image = $this->create_image( $title, $subtitle );
        if ( false === $image ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $filename   = 'newsletter-' . $post_id . '-' . time() . '.png';
        $filepath   = trailingslashit( $upload_dir['path'] ) . $filename;

        imagepng( $image, $filepath, 8 );
        imagedestroy( $image );

        if ( ! file_exists( $filepath ) ) {
            return false;
        }

        return $this->insert_attachment( $filepath, $filename, $post_id );
    }

    /**
     * Cria o recurso GD da imagem.
     *
     * @param string $title    Título.
     * @param string $subtitle Subtítulo.
     * @return \GdImage|resource|false
     */
    private function create_image( string $title, string $subtitle ) {
        $img = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
        if ( false === $img ) {
            return false;
        }

        imagesavealpha( $img, true );

        /* ---------- Cores ---------- */
        $bg_dark   = imagecolorallocate( $img, 15, 15, 35 );      /* #0f0f23 */
        $bg_mid    = imagecolorallocate( $img, 25, 25, 55 );       /* #191937 */
        $accent    = imagecolorallocate( $img, 108, 92, 231 );     /* #6c5ce7 */
        $white     = imagecolorallocate( $img, 255, 255, 255 );
        $gray      = imagecolorallocate( $img, 180, 180, 200 );
        $separator = imagecolorallocate( $img, 60, 60, 90 );

        /* ---------- Fundo gradiente simulado ---------- */
        imagefilledrectangle( $img, 0, 0, self::WIDTH, self::HEIGHT, $bg_dark );
        for ( $y = 0; $y < self::HEIGHT; $y++ ) {
            $ratio = $y / self::HEIGHT;
            $r     = (int) ( 15 + ( 25 - 15 ) * $ratio );
            $g     = (int) ( 15 + ( 25 - 15 ) * $ratio );
            $b     = (int) ( 35 + ( 55 - 35 ) * $ratio );
            $color = imagecolorallocate( $img, $r, $g, $b );
            imageline( $img, 0, $y, self::WIDTH, $y, $color );
        }

        /* ---------- Barra de acento (topo) ---------- */
        imagefilledrectangle( $img, 0, 0, self::WIDTH, 6, $accent );

        /* ---------- Separador inferior ---------- */
        imagefilledrectangle( $img, 80, self::HEIGHT - 100, self::WIDTH - 80, self::HEIGHT - 99, $separator );

        /* ---------- Marca d'água inferior ---------- */
        $font_file = $this->find_font();

        if ( $font_file ) {
            $this->draw_with_ttf( $img, $title, $subtitle, $font_file, $white, $gray, $accent );
        } else {
            $this->draw_with_builtin( $img, $title, $subtitle, $white, $gray );
        }

        return $img;
    }

    /**
     * Desenha textos usando fonte TTF.
     *
     * @param \GdImage|resource $img       Recurso GD.
     * @param string            $title     Título.
     * @param string            $subtitle  Subtítulo.
     * @param string            $font_file Caminho da fonte TTF.
     * @param int               $white     Cor branca alocada.
     * @param int               $gray      Cor cinza alocada.
     * @param int               $accent    Cor de acento alocada.
     * @return void
     */
    private function draw_with_ttf( $img, string $title, string $subtitle, string $font_file, int $white, int $gray, int $accent ): void {
        $padding     = 80;
        $max_width   = self::WIDTH - ( $padding * 2 );
        $title_size  = 38;
        $sub_size    = 20;
        $label_size  = 14;

        /* ---------- Label "NEWSLETTER" ---------- */
        imagettftext( $img, $label_size, 0, $padding, 80, $accent, $font_file, 'NEWSLETTER' );

        /* ---------- Título (com quebra de linha) ---------- */
        $title_lines = $this->wrap_text( $title, $font_file, $title_size, $max_width );
        $title_lines = array_slice( $title_lines, 0, 4 ); /* Máx. 4 linhas */
        $line_height = (int) ( $title_size * 1.5 );
        $title_y     = 150;

        foreach ( $title_lines as $line ) {
            $bbox = imagettfbbox( $title_size, 0, $font_file, $line );
            $lw   = abs( $bbox[2] - $bbox[0] );
            $x    = (int) ( ( self::WIDTH - $lw ) / 2 );
            imagettftext( $img, $title_size, 0, $x, $title_y, $white, $font_file, $line );
            $title_y += $line_height;
        }

        /* ---------- Subtítulo ---------- */
        $sub_lines = $this->wrap_text( $subtitle . '…', $font_file, $sub_size, $max_width );
        $sub_lines = array_slice( $sub_lines, 0, 3 );
        $sub_y     = $title_y + 30;

        foreach ( $sub_lines as $line ) {
            $bbox = imagettfbbox( $sub_size, 0, $font_file, $line );
            $lw   = abs( $bbox[2] - $bbox[0] );
            $x    = (int) ( ( self::WIDTH - $lw ) / 2 );
            imagettftext( $img, $sub_size, 0, $x, $sub_y, $gray, $font_file, $line );
            $sub_y += (int) ( $sub_size * 1.6 );
        }

        /* ---------- Rodapé ---------- */
        $footer_text = get_bloginfo( 'name' );
        imagettftext( $img, $label_size, 0, $padding, self::HEIGHT - 60, $gray, $font_file, $footer_text );
    }

    /**
     * Fallback — desenha textos usando fontes embutidas do GD.
     *
     * @param \GdImage|resource $img      Recurso GD.
     * @param string            $title    Título.
     * @param string            $subtitle Subtítulo.
     * @param int               $white    Cor branca.
     * @param int               $gray     Cor cinza.
     * @return void
     */
    private function draw_with_builtin( $img, string $title, string $subtitle, int $white, int $gray ): void {
        $title    = mb_substr( $title, 0, 60 );
        $subtitle = mb_substr( $subtitle, 0, 100 );

        /* GD built-in font 5 é a maior (≈ 9 px). */
        $font   = 5;
        $char_w = imagefontwidth( $font );
        $char_h = imagefontheight( $font );

        /* Título centralizado. */
        $title_x = (int) ( ( self::WIDTH - ( strlen( $title ) * $char_w ) ) / 2 );
        $title_x = max( 10, $title_x );
        imagestring( $img, $font, $title_x, (int) ( self::HEIGHT / 2 - $char_h - 20 ), $title, $white );

        /* Subtítulo centralizado. */
        $sub_x = (int) ( ( self::WIDTH - ( strlen( $subtitle ) * $char_w ) ) / 2 );
        $sub_x = max( 10, $sub_x );
        imagestring( $img, 3, $sub_x, (int) ( self::HEIGHT / 2 + 10 ), $subtitle, $gray );
    }

    /**
     * Quebra texto em linhas respeitando largura máxima.
     *
     * @param string $text      Texto a quebrar.
     * @param string $font_file Caminho da fonte TTF.
     * @param int    $font_size Tamanho da fonte.
     * @param int    $max_width Largura máxima em pixels.
     * @return string[]
     */
    private function wrap_text( string $text, string $font_file, int $font_size, int $max_width ): array {
        $words        = explode( ' ', $text );
        $lines        = [];
        $current_line = '';

        foreach ( $words as $word ) {
            $test_line = '' !== $current_line ? $current_line . ' ' . $word : $word;
            $bbox      = imagettfbbox( $font_size, 0, $font_file, $test_line );
            $line_w    = abs( $bbox[2] - $bbox[0] );

            if ( $line_w > $max_width && '' !== $current_line ) {
                $lines[]      = $current_line;
                $current_line = $word;
            } else {
                $current_line = $test_line;
            }
        }

        if ( '' !== $current_line ) {
            $lines[] = $current_line;
        }

        return $lines;
    }

    /**
     * Procura uma fonte TTF disponível no sistema.
     *
     * @return string|false Caminho da fonte ou false.
     */
    private function find_font() {
        $candidates = [
            NEWSLETTER_AUTO_PATH . 'assets/fonts/OpenSans-Bold.ttf',
            NEWSLETTER_AUTO_PATH . 'assets/fonts/Roboto-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/ubuntu/Ubuntu-Bold.ttf',
        ];

        /**
         * Permite adicionar caminhos de fontes customizados.
         *
         * @param string[] $candidates Lista de caminhos para fontes TTF.
         */
        $candidates = apply_filters( 'newsletter_auto_font_candidates', $candidates );

        foreach ( $candidates as $path ) {
            if ( is_readable( $path ) ) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Gera imagem com IA, se configurado, e retorna caminho do arquivo baixado.
     *
     * @param string $title    Título.
     * @param string $subtitle Subtítulo.
     * @param int    $post_id  ID do post.
     * @return string|false Caminho local do arquivo ou false.
     */
    private function generate_with_ai( string $title, string $subtitle, int $post_id ) {
        $settings = Admin_Settings::get_settings();

        if ( empty( $settings['ai_enabled'] ) ) {
            return false;
        }

        $provider = $settings['ai_provider'] ?? 'openai';
        if ( 'openai' !== $provider ) {
            return false;
        }

        $cipher_key = $settings['ai_api_key'] ?? '';
        $api_key    = Admin_Settings::decrypt( $cipher_key );
        if ( '' === $api_key ) {
            return false;
        }

        $model = $settings['ai_image_model'] ?? 'gpt-image-1';
        $size  = $settings['ai_image_size'] ?? '1536x1024';

        $prompt_template = $settings['ai_prompt_template'] ?? '';
        if ( '' === $prompt_template ) {
            $prompt_template = Admin_Settings::defaults()['ai_prompt_template'];
        }

        $prompt = str_replace(
            [ '{title}', '{subtitle}' ],
            [ $title, $subtitle ],
            $prompt_template
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(
                    [
                        'model'  => $model,
                        'prompt' => $prompt,
                        'size'   => $size,
                    ]
                ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $status_code ) {
            return false;
        }

        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) || empty( $body['data'][0]['b64_json'] ) ) {
            return false;
        }

        $binary = base64_decode( (string) $body['data'][0]['b64_json'] );
        if ( false === $binary ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $filename   = 'newsletter-ai-' . $post_id . '-' . time() . '.png';
        $filepath   = trailingslashit( $upload_dir['path'] ) . $filename;

        $saved = file_put_contents( $filepath, $binary );
        if ( false === $saved || ! file_exists( $filepath ) ) {
            return false;
        }

        return $filepath;
    }

    /**
     * Insere a imagem na biblioteca de mídia do WordPress.
     *
     * @param string $filepath Caminho absoluto do arquivo.
     * @param string $filename Nome do arquivo.
     * @param int    $post_id  ID do post pai.
     * @return int|false ID do attachment ou false.
     */
    private function insert_attachment( string $filepath, string $filename, int $post_id ) {
        $filetype = wp_check_filetype( $filename, null );

        $attachment_data = [
            'guid'           => trailingslashit( wp_upload_dir()['url'] ) . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment( $attachment_data, $filepath, $post_id );

        if ( is_wp_error( $attach_id ) ) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_generate_attachment_metadata( $attach_id, $filepath );
        wp_update_attachment_metadata( $attach_id, $metadata );

        return $attach_id;
    }
}