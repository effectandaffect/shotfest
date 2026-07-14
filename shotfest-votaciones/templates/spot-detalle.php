<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $spot             WP_Post
 *   $periodo_abierto  WP_Post|null
 *   $ya_voto          bool
 */

$video_url = get_post_meta( $spot->ID, '_sf_spot_video_url', true );
$marca     = get_post_meta( $spot->ID, '_sf_spot_marca', true );
$titulo    = $spot->post_title;
$home_url  = get_permalink();
?>
<div class="sf-detalle-wrap">
    <nav class="sf-breadcrumb">
        <a href="<?php echo esc_url( $home_url ); ?>">&larr; <?php esc_html_e( 'Volver al listado', 'shotfest-votaciones' ); ?></a>
    </nav>

    <article class="sf-detalle-spot">
        <h1 class="sf-spot-titulo"><?php echo esc_html( $titulo ); ?></h1>
        <?php if ( $marca ) : ?>
            <p class="sf-spot-marca"><?php echo esc_html( $marca ); ?></p>
        <?php endif; ?>

        <?php if ( $video_url ) :
            include SF_PLUGIN_DIR . 'templates/partials/youtube-embed.php';
        endif; ?>

        <div class="sf-voto-seccion">
            <?php if ( $ya_voto ) : ?>
                <p class="sf-aviso sf-ya-votado"><?php esc_html_e( 'Ya has emitido tu voto para este spot. El voto es definitivo y no puede modificarse.', 'shotfest-votaciones' ); ?></p>

            <?php elseif ( $periodo_abierto ) : ?>
                <p class="sf-instrucciones"><?php esc_html_e( '¿Este spot merece pasar a la siguiente fase?', 'shotfest-votaciones' ); ?></p>
                <div class="sf-botones-voto" data-spot-id="<?php echo esc_attr( $spot->ID ); ?>">
                    <button type="button" class="sf-btn sf-btn-si" data-valor="1">
                        <?php esc_html_e( '👍 Sí', 'shotfest-votaciones' ); ?>
                    </button>
                    <button type="button" class="sf-btn sf-btn-no" data-valor="0">
                        <?php esc_html_e( '👎 No', 'shotfest-votaciones' ); ?>
                    </button>
                </div>
                <div class="sf-voto-feedback" role="alert" aria-live="polite"></div>

            <?php else : ?>
                <p class="sf-aviso"><?php esc_html_e( 'El periodo de votación ha finalizado. Ya no es posible votar.', 'shotfest-votaciones' ); ?></p>
            <?php endif; ?>
        </div>
    </article>
</div>
