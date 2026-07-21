<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $spot           WP_Post
 *   $ya_votado      bool
 *   $valor_voto     int|null    0 o 1 si ya votó
 *   $cat_slugs      string[]    slugs de categorías del spot
 */

$spot_url  = get_permalink() . '?sf_spot=' . $spot->ID;
$marca     = get_post_meta( $spot->ID, '_sf_spot_marca', true );
$thumb_url = get_the_post_thumbnail_url( $spot->ID, 'medium' );
$video_url = get_post_meta( $spot->ID, '_sf_spot_video_url', true );

if ( ! $thumb_url ) {
    $video_id = sf_extract_video_id( $video_url );
    if ( $video_id ) {
        $thumb_url = 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg';
    }
}
?>
<article class="sf-spot-card" data-sf-cats="<?php echo esc_attr( implode( ',', $cat_slugs ?? [] ) ); ?>">
    <div class="sf-spot-thumb">
        <?php if ( $thumb_url ) : ?>
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $spot->post_title ); ?>" loading="lazy">
        <?php else : ?>
            <div class="sf-spot-thumb-placeholder">
                <div class="sf-spot-thumb-play"></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="sf-spot-info">
        <div>
            <h3 class="sf-spot-nombre"><?php echo esc_html( $spot->post_title ); ?></h3>
            <?php if ( $marca ) : ?>
                <p class="sf-spot-marca"><?php echo esc_html( $marca ); ?></p>
            <?php endif; ?>
        </div>

        <div class="sf-spot-actions">
            <?php if ( $ya_votado ) : ?>
                <span class="sf-voto-emitido sf-voto-<?php echo 1 === (int) $valor_voto ? 'si' : 'no'; ?>">
                    <?php echo 1 === (int) $valor_voto
                        ? esc_html__( '✓ Aprobado', 'shotfest-votaciones' )
                        : esc_html__( '✕ Rechazado', 'shotfest-votaciones' );
                    ?>
                </span>
            <?php else : ?>
                <span class="sf-pendiente"><?php esc_html_e( 'Pendiente de votar', 'shotfest-votaciones' ); ?></span>
            <?php endif; ?>

            <a href="<?php echo esc_url( $spot_url ); ?>" class="sf-btn sf-btn-detalle">
                <?php esc_html_e( 'Ver spot', 'shotfest-votaciones' ); ?>
            </a>
        </div>
    </div>
</article>
