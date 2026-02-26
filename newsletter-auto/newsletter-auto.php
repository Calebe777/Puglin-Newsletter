<?php
/**
 * Plugin Name: Newsletter Auto
 * Plugin URI:  https://example.com/newsletter-auto
 * Description: Converte automaticamente emails de newsletter em posts WordPress com imagem destacada gerada dinamicamente.
 * Version:     1.0.0
 * Author:      Developer
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * Text Domain: newsletter-auto
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

defined( 'ABSPATH' ) || exit;

define( 'NEWSLETTER_AUTO_VERSION', '1.0.0' );
define( 'NEWSLETTER_AUTO_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEWSLETTER_AUTO_URL', plugin_dir_url( __FILE__ ) );
define( 'NEWSLETTER_AUTO_BASENAME', plugin_basename( __FILE__ ) );

require_once NEWSLETTER_AUTO_PATH . 'includes/class-admin-settings.php';
require_once NEWSLETTER_AUTO_PATH . 'includes/class-email-reader.php';
require_once NEWSLETTER_AUTO_PATH . 'includes/class-post-creator.php';
require_once NEWSLETTER_AUTO_PATH . 'includes/class-image-generator.php';
require_once NEWSLETTER_AUTO_PATH . 'includes/class-cron.php';

/**
 * Classe principal do plugin.
 */
final class Newsletter_Auto_Plugin {

    /**
     * Instância singleton.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Retorna a instância singleton.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor — inicializa componentes.
     */
    private function __construct() {
        $this->check_dependencies();
        new NewsletterAuto\Admin_Settings();
        new NewsletterAuto\Post_Creator();
        new NewsletterAuto\Cron();
    }

    /**
     * Verifica se extensões obrigatórias estão disponíveis.
     *
     * @return void
     */
    private function check_dependencies(): void {
        add_action( 'admin_notices', function () {
            if ( ! extension_loaded( 'imap' ) ) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__( 'Newsletter Auto: A extensão PHP IMAP é obrigatória. Por favor, ative-a no servidor.', 'newsletter-auto' )
                );
            }
            if ( ! extension_loaded( 'gd' ) && ! extension_loaded( 'imagick' ) ) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__( 'Newsletter Auto: A extensão PHP GD ou Imagick é obrigatória para gerar imagens.', 'newsletter-auto' )
                );
            }
        } );
    }

    /**
     * Rotina de ativação do plugin.
     *
     * @return void
     */
    public static function activate(): void {
        NewsletterAuto\Post_Creator::register_post_type();
        NewsletterAuto\Cron::schedule();
        flush_rewrite_rules();
    }

    /**
     * Rotina de desativação do plugin.
     *
     * @return void
     */
    public static function deactivate(): void {
        NewsletterAuto\Cron::unschedule();
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__, [ 'Newsletter_Auto_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Newsletter_Auto_Plugin', 'deactivate' ] );

Newsletter_Auto_Plugin::get_instance();