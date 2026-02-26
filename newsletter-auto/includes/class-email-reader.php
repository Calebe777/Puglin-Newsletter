<?php
/**
 * Leitor de emails via IMAP.
 *
 * @package NewsletterAuto
 */

namespace NewsletterAuto;

defined( 'ABSPATH' ) || exit;

/**
 * Conecta a uma caixa IMAP e extrai emails não lidos.
 */
class Email_Reader {

    /** @var string */
    private string $host;

    /** @var int */
    private int $port;

    /** @var bool */
    private bool $ssl;

    /** @var string */
    private string $folder;

    /** @var string */
    private string $email;

    /** @var string */
    private string $password;

    /**
     * Construtor — recebe configurações IMAP.
     *
     * @param array<string, mixed> $settings Configurações do plugin.
     */
    public function __construct( array $settings ) {
        $this->host     = $settings['imap_host'];
        $this->port     = (int) $settings['imap_port'];
        $this->ssl      = ! empty( $settings['imap_ssl'] );
        $this->folder   = $settings['imap_folder'];
        $this->email    = $settings['imap_email'];
        $this->password = Admin_Settings::decrypt( $settings['imap_password'] );
    }

    /**
     * Monta a string de conexão IMAP.
     *
     * @return string Ex.: {imap.gmail.com:993/imap/ssl}INBOX
     */
    private function connection_string(): string {
        $str = '{' . $this->host . ':' . $this->port . '/imap';
        if ( $this->ssl ) {
            $str .= '/ssl';
        }
        $str .= '/novalidate-cert}';
        $str .= $this->folder;
        return $str;
    }

    /**
     * Lê emails não lidos e retorna array de dados.
     *
     * Cada elemento contém:
     *   - subject  (string) Assunto decodificado
     *   - body     (string) Corpo HTML (ou texto)
     *   - date     (string) Data do email
     *
     * @return array<int, array{subject: string, body: string, date: string}>
     */
    public function fetch_unread(): array {
        if ( ! extension_loaded( 'imap' ) ) {
            return [];
        }

        $mailbox = @imap_open(
            $this->connection_string(),
            $this->email,
            $this->password,
            0,
            1
        );

        if ( false === $mailbox ) {
            $this->log_error( 'Falha ao conectar IMAP: ' . imap_last_error() );
            return [];
        }

        $uids = imap_search( $mailbox, 'UNSEEN', SE_UID );
        if ( false === $uids ) {
            imap_close( $mailbox );
            return [];
        }

        $emails = [];

        foreach ( $uids as $uid ) {
            $header = imap_fetchheader( $mailbox, $uid, FT_UID );
            $header_info = imap_rfc822_parse_headers( $header );

            $subject = isset( $header_info->subject ) ? $this->decode_mime( $header_info->subject ) : 'Newsletter';
            $date    = isset( $header_info->date ) ? $header_info->date : current_time( 'mysql' );

            $body = $this->extract_body( $mailbox, $uid );

            $emails[] = [
                'subject' => sanitize_text_field( $subject ),
                'body'    => wp_kses_post( $body ),
                'date'    => sanitize_text_field( $date ),
            ];

            /* Marca como lido. */
            imap_setflag_full( $mailbox, (string) $uid, '\\Seen', ST_UID );
        }

        imap_close( $mailbox );

        return $emails;
    }

    /* ===========================================================
     *  Extração de corpo MIME
     * =========================================================== */

    /**
     * Extrai o corpo do email priorizando HTML.
     *
     * @param resource $mailbox Recurso IMAP.
     * @param int      $uid     UID da mensagem.
     * @return string
     */
    private function extract_body( $mailbox, int $uid ): string {
        $structure = imap_fetchstructure( $mailbox, $uid, FT_UID );

        $parts = $this->collect_parts( $mailbox, $uid, $structure );

        /* Prioriza HTML sobre texto plano. */
        foreach ( $parts as $part ) {
            if ( 0 === $part['type'] && 'HTML' === strtoupper( $part['subtype'] ) ) {
                return $part['body'];
            }
        }

        foreach ( $parts as $part ) {
            if ( 0 === $part['type'] && 'PLAIN' === strtoupper( $part['subtype'] ) ) {
                return nl2br( esc_html( $part['body'] ) );
            }
        }

        return '';
    }

    /**
     * Coleta todas as partes da estrutura MIME recursivamente.
     *
     * @param resource  $mailbox   Recurso IMAP.
     * @param int       $uid       UID da mensagem.
     * @param object    $structure Estrutura MIME.
     * @param string    $prefix    Prefixo da parte (ex.: "1.2").
     * @return array<int, array{type: int, subtype: string, body: string}>
     */
    private function collect_parts( $mailbox, int $uid, object $structure, string $prefix = '' ): array {
        $parts = [];

        if ( empty( $structure->parts ) ) {
            $section = $prefix ?: '1';
            $raw     = imap_fetchbody( $mailbox, $uid, $section, FT_UID );
            $decoded = $this->decode_body( $raw, $structure->encoding ?? 0 );
            $decoded = $this->convert_charset( $decoded, $structure );

            $parts[] = [
                'type'    => $structure->type ?? 0,
                'subtype' => $structure->subtype ?? 'PLAIN',
                'body'    => $decoded,
            ];
            return $parts;
        }

        foreach ( $structure->parts as $index => $sub ) {
            $part_number = $prefix ? ( $prefix . '.' . ( $index + 1 ) ) : (string) ( $index + 1 );

            if ( ! empty( $sub->parts ) ) {
                $parts = array_merge( $parts, $this->collect_parts( $mailbox, $uid, $sub, $part_number ) );
            } else {
                $raw     = imap_fetchbody( $mailbox, $uid, $part_number, FT_UID );
                $decoded = $this->decode_body( $raw, $sub->encoding ?? 0 );
                $decoded = $this->convert_charset( $decoded, $sub );

                $parts[] = [
                    'type'    => $sub->type ?? 0,
                    'subtype' => $sub->subtype ?? 'PLAIN',
                    'body'    => $decoded,
                ];
            }
        }

        return $parts;
    }

    /**
     * Decodifica corpo conforme codificação MIME.
     *
     * @param string $body     Corpo bruto.
     * @param int    $encoding Constante de codificação.
     * @return string
     */
    private function decode_body( string $body, int $encoding ): string {
        switch ( $encoding ) {
            case 3: /* BASE64 */
                return base64_decode( $body );
            case 4: /* QUOTED-PRINTABLE */
                return quoted_printable_decode( $body );
            default:
                return $body;
        }
    }

    /**
     * Converte charset para UTF-8 quando necessário.
     *
     * @param string $text      Texto decodificado.
     * @param object $structure Estrutura MIME da parte.
     * @return string
     */
    private function convert_charset( string $text, object $structure ): string {
        $charset = $this->get_charset( $structure );
        if ( $charset && 'UTF-8' !== strtoupper( $charset ) ) {
            $converted = @mb_convert_encoding( $text, 'UTF-8', $charset );
            if ( false !== $converted ) {
                return $converted;
            }
        }
        return $text;
    }

    /**
     * Obtém o charset de uma parte MIME.
     *
     * @param object $part Estrutura da parte.
     * @return string|null
     */
    private function get_charset( object $part ): ?string {
        if ( ! empty( $part->parameters ) ) {
            foreach ( $part->parameters as $param ) {
                if ( 'CHARSET' === strtoupper( $param->attribute ) ) {
                    return $param->value;
                }
            }
        }
        return null;
    }

    /**
     * Decodifica cabeçalho MIME (assunto, remetente etc.).
     *
     * @param string $text Texto codificado.
     * @return string
     */
    private function decode_mime( string $text ): string {
        $decoded = imap_utf8( $text );
        if ( $decoded ) {
            return $decoded;
        }
        return mb_decode_mimeheader( $text );
    }

    /**
     * Registra erro no log do WordPress.
     *
     * @param string $message Mensagem de erro.
     * @return void
     */
    private function log_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Newsletter Auto] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }
    }
}