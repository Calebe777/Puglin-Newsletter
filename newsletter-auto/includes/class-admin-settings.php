<?php
/**
 * Painel administrativo e configurações do plugin.
 *
 * @package NewsletterAuto
 */

namespace NewsletterAuto;

defined( 'ABSPATH' ) || exit;

/**
 * Gerencia a página de configurações no admin.
 */
class Admin_Settings {

    /** Slug da página de opções. */
    const PAGE_SLUG = 'newsletter-auto-settings';

    /** Nome da opção no banco de dados. */
    const OPTION_NAME = 'newsletter_auto_settings';

    /**
     * Construtor — registra hooks do admin.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'handle_run_now' ] );
    }

    /**
     * Adiciona página ao menu do WordPress.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_menu_page(
            __( 'Newsletter Auto', 'newsletter-auto' ),
            __( 'Newsletter Auto', 'newsletter-auto' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ],
            'dashicons-email-alt',
            80
        );
    }

    /**
     * Registra seções e campos de configuração.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            self::PAGE_SLUG,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => self::defaults(),
            ]
        );

        /* ---------- Seção IMAP ---------- */
        add_settings_section(
            'na_imap_section',
            __( 'Configuração IMAP', 'newsletter-auto' ),
            function () {
                echo '<p>' . esc_html__( 'Configure a conexão com a caixa de email.', 'newsletter-auto' ) . '</p>';
            },
            self::PAGE_SLUG
        );

        $imap_fields = [
            'imap_host'   => [ 'label' => __( 'Host IMAP', 'newsletter-auto' ), 'type' => 'text' ],
            'imap_port'   => [ 'label' => __( 'Porta', 'newsletter-auto' ), 'type' => 'number' ],
            'imap_ssl'    => [ 'label' => __( 'Usar SSL', 'newsletter-auto' ), 'type' => 'checkbox' ],
            'imap_folder' => [ 'label' => __( 'Pasta', 'newsletter-auto' ), 'type' => 'text' ],
            'imap_email'  => [ 'label' => __( 'Email', 'newsletter-auto' ), 'type' => 'email' ],
            'imap_password' => [ 'label' => __( 'Senha / App Password', 'newsletter-auto' ), 'type' => 'password' ],
        ];

        foreach ( $imap_fields as $key => $field ) {
            add_settings_field(
                $key,
                $field['label'],
                [ $this, 'render_field' ],
                self::PAGE_SLUG,
                'na_imap_section',
                [ 'key' => $key, 'type' => $field['type'] ]
            );
        }

        /* ---------- Seção Automação ---------- */
        add_settings_section(
            'na_auto_section',
            __( 'Automação', 'newsletter-auto' ),
            function () {
                echo '<p>' . esc_html__( 'Ative para verificar emails automaticamente a cada 5 minutos.', 'newsletter-auto' ) . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            'automation_enabled',
            __( 'Ativar automação', 'newsletter-auto' ),
            [ $this, 'render_field' ],
            self::PAGE_SLUG,
            'na_auto_section',
            [ 'key' => 'automation_enabled', 'type' => 'checkbox' ]
        );

        /* ---------- Seção Integrações (ENV) ---------- */
        add_settings_section(
            'na_env_section',
            __( 'Integrações e ENV', 'newsletter-auto' ),
            function () {
                echo '<p>' . esc_html__( 'Preencha suas chaves diretamente no wp-admin. Se existir variável de ambiente no servidor, ela continua funcionando como fallback.', 'newsletter-auto' ) . '</p>';
            },
            self::PAGE_SLUG
        );

        $env_fields = [
            'image_api_key'         => [ 'label' => __( 'API Key para gerar imagem', 'newsletter-auto' ), 'type' => 'password' ],
            'image_provider'        => [ 'label' => __( 'Provedor de imagem', 'newsletter-auto' ), 'type' => 'text' ],
            'image_prompt_template' => [ 'label' => __( 'Template do prompt da imagem', 'newsletter-auto' ), 'type' => 'textarea' ],
            'post_template'         => [ 'label' => __( 'Template do conteúdo do post', 'newsletter-auto' ), 'type' => 'textarea' ],
        ];

        foreach ( $env_fields as $key => $field ) {
            add_settings_field(
                $key,
                $field['label'],
                [ $this, 'render_field' ],
                self::PAGE_SLUG,
                'na_env_section',
                [ 'key' => $key, 'type' => $field['type'] ]
            );
        }
    }

    /**
     * Valores padrão das configurações.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'imap_host'          => 'imap.gmail.com',
            'imap_port'          => 993,
            'imap_ssl'           => 1,
            'imap_folder'        => 'INBOX',
            'imap_email'         => '',
            'imap_password'      => '',
            'automation_enabled' => 0,
            'image_api_key'      => '',
            'image_provider'     => 'openai',
            'image_prompt_template' => 'Crie uma imagem editorial moderna para o título "{title}" com o contexto: "{subtitle}".',
            'post_template'      => "<!-- wp:paragraph -->\n<p>{content}</p>\n<!-- /wp:paragraph -->",
        ];
    }

    /**
     * Obtém as configurações atuais mescladas com defaults.
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array {
        $stored = get_option( self::OPTION_NAME, [] );
        return wp_parse_args( $stored, self::defaults() );
    }

    /**
     * Sanitiza as configurações antes de salvar.
     *
     * @param array $input Dados brutos vindos do formulário.
     * @return array Dados sanitizados.
     */
    public function sanitize_settings( array $input ): array {
        $clean = [];

        $clean['imap_host']   = sanitize_text_field( $input['imap_host'] ?? '' );
        $clean['imap_port']   = absint( $input['imap_port'] ?? 993 );
        $clean['imap_ssl']    = ! empty( $input['imap_ssl'] ) ? 1 : 0;
        $clean['imap_folder'] = sanitize_text_field( $input['imap_folder'] ?? 'INBOX' );
        $clean['imap_email']  = sanitize_email( $input['imap_email'] ?? '' );
        $clean['image_provider'] = sanitize_text_field( $input['image_provider'] ?? 'openai' );
        $clean['image_prompt_template'] = wp_kses_post( $input['image_prompt_template'] ?? '' );
        $clean['post_template'] = wp_kses_post( $input['post_template'] ?? '' );

        $clean['automation_enabled'] = ! empty( $input['automation_enabled'] ) ? 1 : 0;

        /* Senha: cifrar se foi alterada, manter a anterior se campo vazio. */
        $raw_password = $input['imap_password'] ?? '';
        if ( '' !== $raw_password ) {
            $clean['imap_password'] = self::encrypt( $raw_password );
        } else {
            $current = self::get_settings();
            $clean['imap_password'] = $current['imap_password'];
        }

        $raw_api_key = $input['image_api_key'] ?? '';
        if ( '' !== $raw_api_key ) {
            $clean['image_api_key'] = self::encrypt( $raw_api_key );
        } else {
            $current = self::get_settings();
            $clean['image_api_key'] = $current['image_api_key'];
        }

        /* Agendar ou desagendar cron conforme automação. */
        if ( $clean['automation_enabled'] ) {
            Cron::schedule();
        } else {
            Cron::unschedule();
        }

        return $clean;
    }

    /**
     * Renderiza um campo do formulário.
     *
     * @param array $args Argumentos contendo 'key' e 'type'.
     * @return void
     */
    public function render_field( array $args ): void {
        $settings = self::get_settings();
        $key      = $args['key'];
        $type     = $args['type'];
        $value    = $settings[ $key ] ?? '';
        $name     = self::OPTION_NAME . '[' . esc_attr( $key ) . ']';

        if ( 'checkbox' === $type ) {
            printf(
                '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
                esc_attr( $key ),
                esc_attr( $name ),
                checked( 1, (int) $value, false )
            );
            return;
        }

        if ( 'password' === $type ) {
            printf(
                '<input type="password" id="%1$s" name="%2$s" value="" class="regular-text" autocomplete="new-password" placeholder="%3$s" />',
                esc_attr( $key ),
                esc_attr( $name ),
                $value ? esc_attr__( '••••••••  (salva)', 'newsletter-auto' ) : ''
            );
            return;
        }

        if ( 'textarea' === $type ) {
            printf(
                '<textarea id="%1$s" name="%2$s" rows="6" class="large-text code">%3$s</textarea>',
                esc_attr( $key ),
                esc_attr( $name ),
                esc_textarea( (string) $value )
            );

            if ( in_array( $key, [ 'image_prompt_template', 'post_template' ], true ) ) {
                echo '<p class="description">' . esc_html__( 'Variáveis disponíveis: {title}, {subtitle}, {date}, {content}.', 'newsletter-auto' ) . '</p>';
            }

            return;
        }

        printf(
            '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" />',
            esc_attr( $type ),
            esc_attr( $key ),
            esc_attr( $name ),
            esc_attr( $value )
        );
    }

    /**
     * Renderiza a página de configurações.
     *
     * @return void
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $last_run = get_option( 'newsletter_auto_last_run', '' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( self::PAGE_SLUG );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( 'Salvar configurações', 'newsletter-auto' ) );
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Execução Manual', 'newsletter-auto' ); ?></h2>

            <?php if ( $last_run ) : ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: data/hora da última execução */
                        esc_html__( 'Última execução: %s', 'newsletter-auto' ),
                        esc_html( $last_run )
                    );
                    ?>
                </p>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'newsletter_auto_run_now', '_nonce_run_now' ); ?>
                <input type="hidden" name="newsletter_auto_run_now" value="1" />
                <?php submit_button( __( 'Executar agora', 'newsletter-auto' ), 'secondary', 'run-now-btn', false ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Tutorial rápido: template do post', 'newsletter-auto' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Abra Newsletter Auto > Integrações e ENV.', 'newsletter-auto' ); ?></li>
                <li><?php esc_html_e( 'No campo "Template do conteúdo do post", cole seu HTML/Gutenberg base.', 'newsletter-auto' ); ?></li>
                <li><?php esc_html_e( 'Use variáveis como {title}, {subtitle}, {date} e {content} para manter o layout igual em todos os posts.', 'newsletter-auto' ); ?></li>
                <li><?php esc_html_e( 'Salve e clique em "Executar agora" para validar o resultado.', 'newsletter-auto' ); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * Obtém valor de integração priorizando ENV e fallback no wp-admin.
     *
     * @param string $env_key  Nome da variável de ambiente.
     * @param string $option_key Chave salva nas opções do plugin.
     * @param bool   $encrypted Se o valor do banco está criptografado.
     * @return string
     */
    public static function get_env_or_option( string $env_key, string $option_key, bool $encrypted = false ): string {
        $env_value = getenv( $env_key );
        if ( false !== $env_value && '' !== trim( (string) $env_value ) ) {
            return (string) $env_value;
        }

        $settings = self::get_settings();
        $value    = (string) ( $settings[ $option_key ] ?? '' );

        if ( $encrypted ) {
            return self::decrypt( $value );
        }

        return $value;
    }

    /**
     * Processa o clique em "Executar agora".
     *
     * @return void
     */
    public function handle_run_now(): void {
        if (
            empty( $_POST['newsletter_auto_run_now'] )
            || ! isset( $_POST['_nonce_run_now'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce_run_now'] ) ), 'newsletter_auto_run_now' )
            || ! current_user_can( 'manage_options' )
        ) {
            return;
        }

        $count = Cron::process_emails();

        add_settings_error(
            self::OPTION_NAME,
            'newsletter_auto_run',
            sprintf(
                /* translators: %d: número de posts criados */
                __( 'Execução concluída. %d post(s) criado(s).', 'newsletter-auto' ),
                (int) $count
            ),
            $count > 0 ? 'success' : 'info'
        );

        set_transient( 'settings_errors', get_settings_errors(), 30 );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&settings-updated=true' ) );
        exit;
    }

    /* ===========================================================
     *  Criptografia de senha
     * =========================================================== */

    /**
     * Cifra um texto usando AES-256-CBC.
     *
     * @param string $plain Texto plano.
     * @return string Texto cifrado em base64.
     */
    public static function encrypt( string $plain ): string {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $plain ); // @codeCoverageIgnore
        }
        $key = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
        $iv  = substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 16 );
        return base64_encode( openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv ) );
    }

    /**
     * Decifra um texto cifrado com self::encrypt().
     *
     * @param string $cipher Texto cifrado em base64.
     * @return string Texto plano.
     */
    public static function decrypt( string $cipher ): string {
        if ( '' === $cipher ) {
            return '';
        }
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return base64_decode( $cipher ); // @codeCoverageIgnore
        }
        $key = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
        $iv  = substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 16 );

        $decrypted = openssl_decrypt( base64_decode( $cipher ), 'AES-256-CBC', $key, 0, $iv );
        return false !== $decrypted ? $decrypted : '';
    }
}
