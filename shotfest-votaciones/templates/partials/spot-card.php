<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $spot           WP_Post
 *   $ya_votado      bool
 *   $valor_voto     int|null    0 o 1 si ya votó
 *   $cat_slugs      string[]    slugs de categorías del spot
 *   $indice         int         posición en el listado, para numerar y escalonar la entrada
 */

$spot_url  = add_query_arg( 'sf_spot', $spot->ID, get_permalink() );
$marca     = get_post_meta( $spot->ID, '_sf_spot_marca', true );
$thumb_url = get_the_post_thumbnail_url( $spot->ID, 'medium' );

if ( ! $thumb_url ) {
    $video_id = sf_extract_video_id( (string) get_post_meta( $spot->ID, '_sf_spot_video_url', true ) );
    if ( $video_id ) {
        $thumb_url = 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg';
    }
}

$numero = str_pad( (string) ( ( $indice ?? 0 ) + 1 ), 2, '0', STR_PAD_LEFT );
?>
<article class="sf-spot-card"
         data-sf-cats="<?php echo esc_attr( implode( ',', $cat_slugs ?? [] ) ); ?>"
         style="--i: <?php echo esc_attr( (string) ( $indice ?? 0 ) ); ?>">

    <span class="sf-spot-num" aria-hidden="true"><?php echo esc_html( $numero ); ?></span>

    <a href="<?php echo esc_url( $spot_url ); ?>" class="sf-spot-thumb" tabindex="-1" aria-hidden="true">
        <?php if ( $thumb_url ) : ?>
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
        <?php else : ?>
            <span class="sf-spot-thumb-placeholder"></span>
        <?php endif; ?>
    </a>

    <div class="sf-spot-info">
        <h3 class="sf-spot-nombre">
            <a href="<?php echo esc_url( $spot_url ); ?>"><?php echo esc_html( $spot->post_title ); ?></a>
        </h3>
        <?php if ( $marca ) : ?>
            <p class="sf-spot-marca"><?php echo esc_html( $marca ); ?></p>
        <?php endif; ?>
    </div>

    <div class="sf-spot-actions">
        <?php if ( $ya_votado ) : ?>
            <span class="sf-pill sf-voto-<?php echo 1 === (int) $valor_voto ? 'si' : 'no'; ?>">
                <?php echo 1 === (int) $valor_voto
                    ? esc_html__( '✓ Aprobado', 'shotfest-votaciones' )
                    : esc_html__( '✕ Rechazado', 'shotfest-votaciones' );
                ?>
            </span>
        <?php else : ?>
            <span class="sf-pill sf-pendiente"><?php esc_html_e( 'Sin votar', 'shotfest-votaciones' ); ?></span>
        <?php endif; ?>

        <a href="<?php echo esc_url( $spot_url ); ?>" class="sf-btn <?php echo $ya_votado ? 'sf-btn-fantasma' : 'sf-btn-primario'; ?>">
            <?php echo $ya_votado
                ? esc_html__( 'Ver spot', 'shotfest-votaciones' )
                : esc_html__( 'Votar', 'shotfest-votaciones' ); ?>
        </a>
    </div>
</article>
