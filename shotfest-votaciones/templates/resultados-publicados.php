<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $periodo        WP_Post
 *   $clasificacion  array
 */

$edicion_id = get_post_meta( $periodo->ID, '_sf_periodo_edicion_id', true );
$edicion    = $edicion_id ? get_post_meta( $edicion_id, '_sf_edicion_anio', true ) : '';
?>
<header class="sf-head">
    <div class="sf-head-left">
        <p class="sf-eyebrow">
            <?php echo esc_html( $edicion ? sprintf( __( 'Edición %s', 'shotfest-votaciones' ), $edicion ) : __( 'ShotFest', 'shotfest-votaciones' ) ); ?>
        </p>
        <h1 class="sf-titulo"><?php esc_html_e( 'Resultados del jurado', 'shotfest-votaciones' ); ?></h1>
        <p class="sf-head-periodo"><?php echo esc_html( $periodo->post_title ); ?></p>
    </div>

    <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="sf-logout">
        <?php esc_html_e( 'Cerrar sesión', 'shotfest-votaciones' ); ?>
    </a>
</header>

<?php if ( empty( $clasificacion ) ) : ?>
    <p class="sf-aviso"><?php esc_html_e( 'No hay resultados disponibles.', 'shotfest-votaciones' ); ?></p>
<?php else : ?>
    <?php foreach ( $clasificacion as $cat_data ) : ?>
        <section class="sf-categoria-resultados">
            <h2 class="sf-categoria-nombre"><?php echo esc_html( $cat_data['categoria']->name ); ?></h2>
            <ol class="sf-resultados-lista">
                <?php foreach ( $cat_data['spots'] as $entry ) :
                    if ( ! $entry['shortlist'] ) {
                        continue;
                    }
                    $marca = get_post_meta( $entry['post']->ID, '_sf_spot_marca', true );
                ?>
                    <li class="sf-resultado-item">
                        <span class="sf-resultado-titulo"><?php echo esc_html( $entry['post']->post_title ); ?></span>
                        <?php if ( $marca ) : ?>
                            <span class="sf-resultado-marca"><?php echo esc_html( $marca ); ?></span>
                        <?php endif; ?>
                        <span class="sf-shortlist-badge"><?php esc_html_e( 'Shortlist', 'shotfest-votaciones' ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
