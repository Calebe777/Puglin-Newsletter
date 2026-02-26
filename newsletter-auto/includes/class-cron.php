<?php
/**
 * Gerenciamento de WP-Cron para verificação periódica de emails.
 *
 * @package NewsletterAuto
 */

namespace NewsletterAuto;

defined( 'ABSPATH' ) || exit;

/**
 * Registra e gerencia o agendamento do WP-Cron.
 */
class Cron {

    /** Nome do evento cron. */
    const EVENT_HOOK = 'newsletter_auto_check_emails';

    /** Identificador do intervalo de 5 minutos. */
    const INTERVAL_NAME = 'every_five_minutes';

    /** Lock transient para evitar execuções simultâneas. */
    const LOCK_TRANSIENT = 'newsletter_auto_processing_lock';

    /**
     * Construtor — registra hooks.
     */
    public function __construct() {
        add_filter( 'cron_schedules', [ $this, 'add_interval' ] );
        add_action( self::EVENT_HOOK, [ __CLASS__, 'process_emails' ] );
    }

    /**
     * Adiciona intervalo personalizado de 5 minutos.
     *
     * @param array $schedules Agendamentos existentes.
     * @return array
     */
    public function add_interval( array $schedules ): array {
        if ( ! isset( $schedules[ self::INTERVAL_NAME ] ) ) {
            $schedules[ self::INTERVAL_NAME ] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'A cada 5 minutos', 'newsletter-auto' ),
            ];
        }
        return $schedules;
    }

    /**
     * Agenda o evento cron se não estiver agendado.
     *
     * @return void
     */
    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::EVENT_HOOK ) ) {
            wp_schedule_event( time(), self::INTERVAL_NAME, self::EVENT_HOOK );
        }
    }

    /**
     * Remove o agendamento do evento cron.
     *
     * @return void
     */
    public static function unschedule(): void {
        $timestamp = wp_next_scheduled( self::EVENT_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::EVENT_HOOK );
        }
    }

    /**
     * Callback do cron — conecta ao IMAP e cria posts.
     *
     * @return int Número de posts criados.
     */
    public static function process_emails(): int {
        /* Lock para evitar execuções concorrentes. */
        if ( get_transient( self::LOCK_TRANSIENT ) ) {
            return 0;
        }
        set_transient( self::LOCK_TRANSIENT, true, 4 * MINUTE_IN_SECONDS );

        $settings = Admin_Settings::get_settings();

        /* Pula se automação desativada e chamada via cron (manual sempre executa). */
        if ( empty( $settings['imap_email'] ) || empty( $settings['imap_password'] ) ) {
            delete_transient( self::LOCK_TRANSIENT );
            return 0;
        }

        $reader = new Email_Reader( $settings );
        $emails = $reader->fetch_unread();

        $creator = new Post_Creator();
        $count   = 0;

        foreach ( $emails as $email_data ) {
            $post_id = $creator->create_from_email( $email_data );
            if ( $post_id ) {
                ++$count;
            }
        }

        /* Registra data/hora da última execução. */
        update_option( 'newsletter_auto_last_run', current_time( 'Y-m-d H:i:s' ) );

        delete_transient( self::LOCK_TRANSIENT );

        return $count;
    }
}