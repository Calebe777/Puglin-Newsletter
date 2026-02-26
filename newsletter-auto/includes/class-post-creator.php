<?php
/**
 * Registra o CPT e cria posts a partir de dados de email.
 *
 * @package NewsletterAuto
 */

namespace NewsletterAuto;

defined( 'ABSPATH' ) || exit;

/**
 * Gerencia o Custom Post Type "newsletter" e a criação de posts.
 */
class Post_Creator {

    /** Slug do CPT. */
    const POST_TYPE = 'newsletter';

    /**
     * Construtor — registra hooks.
     */
    public function __construct() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_filter( 'single_template', [ $this, 'load_template' ] );
    }

    /**
     * Registra o Custom Post Type.
     *
     * @return void
     */
    public static function register_post_type(): void {
        if ( post_type_exists( self::POST_TYPE ) ) {
            return;
        }

        $labels = [
            'name'               => __( 'Newsletters', 'newsletter-auto' ),
            'singular_name'      => __( 'Newsletter', 'newsletter-auto' ),
            'add_new'            => __( 'Adicionar nova', 'newsletter-auto' ),
            'add_new_item'       => __( 'Adicionar nova newsletter', 'newsletter-auto' ),
            'edit_item'          => __( 'Editar newsletter', 'newsletter-auto' ),
            'new_item'           => __( 'Nova newsletter', 'newsletter-auto' ),
            'view_item'          => __( 'Ver newsletter', 'newsletter-auto' ),
            'search_items'       => __( 'Buscar newsletters', 'newsletter-auto' ),
            'not_found'          => __( 'Nenhuma newsletter encontrada', 'newsletter-auto' ),
            'not_found_in_trash' => __( 'Nenhuma newsletter na lixeira', 'newsletter-auto' ),
            'all_items'          => __( 'Todas as newsletters', 'newsletter-auto' ),
            'menu_name'          => __( 'Newsletters', 'newsletter-auto' ),
        ];

        $args = [
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-email',
            'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
            'rewrite'      => [ 'slug' => 'newsletter' ],
        ];

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Carrega template customizado para posts do tipo newsletter.
     *
     * @param string $template Caminho do template atual.
     * @return string
     */
    public function load_template( string $template ): string {
        global $post;

        if ( $post && self::POST_TYPE === $post->post_type ) {
            $plugin_template = NEWSLETTER_AUTO_PATH . 'templates/single-newsletter.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Cria um post de newsletter a partir de dados de email.
     *
     * @param array{subject: string, body: string, date: string} $email_data Dados do email.
     * @return int|false ID do post criado ou false em caso de erro.
     */
    public function create_from_email( array $email_data ) {
        /* Evita duplicatas pelo título. */
        $existing = get_posts( [
            'post_type'   => self::POST_TYPE,
            'title'       => $email_data['subject'],
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
        ] );

        if ( ! empty( $existing ) ) {
            return false;
        }

        $post_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE,
            'post_title'   => $email_data['subject'],
            'post_content' => $this->build_post_content( $email_data ),
            'post_status'  => 'publish',
            'post_date'    => $this->parse_date( $email_data['date'] ),
            'meta_input'   => [
                '_newsletter_auto_source' => 'email',
                '_newsletter_auto_date'   => sanitize_text_field( $email_data['date'] ),
            ],
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return false;
        }

        /* Gera e atribui imagem destacada. */
        $image_gen    = new Image_Generator();
        $plain_text   = wp_strip_all_tags( $email_data['body'] );
        $subtitle     = mb_substr( $plain_text, 0, 150 );
        $attachment_id = $image_gen->generate_and_attach( $email_data['subject'], $subtitle, $post_id );

        if ( $attachment_id ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }

        return $post_id;
    }

    /**
     * Monta conteúdo final do post usando template configurável.
     *
     * @param array{subject: string, body: string, date: string} $email_data Dados do email.
     * @return string
     */
    private function build_post_content( array $email_data ): string {
        $settings = Admin_Settings::get_settings();
        $template = (string) ( $settings['post_template'] ?? '' );

        if ( '' === trim( $template ) ) {
            return $email_data['body'];
        }

        $plain_text = trim( wp_strip_all_tags( $email_data['body'] ) );
        $subtitle   = mb_substr( $plain_text, 0, 150 );

        $replace_map = [
            '{title}'    => $email_data['subject'],
            '{subtitle}' => $subtitle,
            '{date}'     => $this->parse_date( $email_data['date'] ),
            '{content}'  => $email_data['body'],
        ];

        return strtr( $template, $replace_map );
    }

    /**
     * Converte data de email para formato MySQL.
     *
     * @param string $date_string Data bruta do email.
     * @return string Data no formato Y-m-d H:i:s.
     */
    private function parse_date( string $date_string ): string {
        $timestamp = strtotime( $date_string );
        if ( false === $timestamp ) {
            return current_time( 'mysql' );
        }
        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }
}
