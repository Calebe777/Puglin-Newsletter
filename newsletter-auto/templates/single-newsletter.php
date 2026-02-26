<?php
/**
 * Template para posts do tipo "newsletter".
 *
 * @package NewsletterAuto
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<style>
    /* ========== Newsletter Auto — Template Fixo ========== */
    .na-newsletter-wrapper {
        max-width: 780px;
        margin: 40px auto;
        padding: 0 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #333;
        line-height: 1.8;
    }

    .na-newsletter-header {
        text-align: center;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 3px solid #6c5ce7;
    }

    .na-newsletter-header .na-label {
        display: inline-block;
        background: #6c5ce7;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        padding: 4px 14px;
        border-radius: 3px;
        margin-bottom: 16px;
    }

    .na-newsletter-header h1 {
        font-size: 2em;
        margin: 0 0 12px;
        color: #1a1a2e;
        line-height: 1.3;
    }

    .na-newsletter-header .na-meta {
        font-size: 0.9em;
        color: #888;
    }

    .na-newsletter-featured {
        margin-bottom: 32px;
        text-align: center;
    }

    .na-newsletter-featured img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .na-newsletter-content {
        font-size: 1.05em;
    }

    .na-newsletter-content img {
        max-width: 100%;
        height: auto;
    }

    .na-newsletter-content a {
        color: #6c5ce7;
        text-decoration: underline;
    }

    .na-newsletter-content p {
        margin-bottom: 1.4em;
    }

    .na-newsletter-footer {
        margin-top: 48px;
        padding-top: 24px;
        border-top: 1px solid #eee;
        text-align: center;
        font-size: 0.85em;
        color: #aaa;
    }

    .na-newsletter-footer a {
        color: #6c5ce7;
        text-decoration: none;
    }

    @media (max-width: 600px) {
        .na-newsletter-wrapper {
            margin: 20px auto;
        }
        .na-newsletter-header h1 {
            font-size: 1.5em;
        }
    }
</style>

<?php while ( have_posts() ) : the_post(); ?>

<article class="na-newsletter-wrapper">

    <!-- Header -->
    <header class="na-newsletter-header">
        <span class="na-label"><?php esc_html_e( 'Newsletter', 'newsletter-auto' ); ?></span>
        <h1><?php the_title(); ?></h1>
        <div class="na-meta">
            <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                <?php echo esc_html( get_the_date() ); ?>
            </time>
        </div>
    </header>

    <!-- Imagem Destacada -->
    <?php if ( has_post_thumbnail() ) : ?>
        <div class="na-newsletter-featured">
            <?php the_post_thumbnail( 'large' ); ?>
        </div>
    <?php endif; ?>

    <!-- Conteúdo -->
    <div class="na-newsletter-content">
        <?php the_content(); ?>
    </div>

    <!-- Rodapé -->
    <footer class="na-newsletter-footer">
        <p>
            <?php
            printf(
                /* translators: %s: nome do site */
                esc_html__( 'Publicado automaticamente por %s', 'newsletter-auto' ),
                '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>'
            );
            ?>
        </p>
        <nav>
            <?php
            $prev = get_adjacent_post( false, '', true );
            $next = get_adjacent_post( false, '', false );

            if ( $prev ) {
                printf(
                    '<a href="%s">&laquo; %s</a> ',
                    esc_url( get_permalink( $prev ) ),
                    esc_html__( 'Anterior', 'newsletter-auto' )
                );
            }
            if ( $next ) {
                printf(
                    '<a href="%s">%s &raquo;</a>',
                    esc_url( get_permalink( $next ) ),
                    esc_html__( 'Próxima', 'newsletter-auto' )
                );
            }
            ?>
        </nav>
    </footer>

</article>

<?php endwhile; ?>

<?php
get_footer();