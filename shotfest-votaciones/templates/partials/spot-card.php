<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $spot           WP_Post
 *   $ya_votado      bool|null   true si el usuario ya votó este spot
 *   $valor_voto     int|null    0 o 1 si ya votó
 */

$spot_url  = get_permalink() . '?sf_spot=' . $spot->ID;
$marca     = get_post_meta( $spot->ID, '_sf_spot_marca', true );
$thumb_url = get_the_post_thumbnail_url( $spot->ID, 'medium' );
?>
<article class="sf-spot-card <?php echo $ya_votado ? 'sf-spot-votado' : ''; ?>">
    <?php if ( $thumb_url ) : ?>
        <div class="sf-spot-thumb">
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $spot->post_title ); ?>" loading="lazy">
        </div>
    <?php endif; ?>

    <div class="sf-spot-info">
        <h3 class="sf-spot-nombre"><?php echo esc_html( $spot->post_title ); ?></h3>
        <?php if ( $marca ) : ?>
            <p class="sf-spot-marca"><?php echo esc_html( $marca ); ?></p>
        <?php endif; ?>

        <?php if ( $ya_votado ) : ?>
            <span class="sf-voto-emitido sf-voto-<?php echo 1 === (int) $valor_voto ? 'si' : 'no'; ?>">
                <?php echo 1 === (int) $valor_voto
                    ? esc_html__( '✓ Has votado Sí', 'shotfest-votaciones' )
                    : esc_html__( '✗ Has votado No', 'shotfest-votaciones' );
                ?>
            </span>
        <?php else : ?>
            <span class="sf-pendiente"><?php esc_html_e( 'Pendiente de votar', 'shotfest-votaciones' ); ?></span>
        <?php endif; ?>

        <a href="<?php echo esc_url( $spot_url ); ?>" class="sf-btn sf-btn-detalle">
            <?php esc_html_e( 'Ver spot', 'shotfest-votaciones' ); ?>
        </a>
    </div>
</article>
