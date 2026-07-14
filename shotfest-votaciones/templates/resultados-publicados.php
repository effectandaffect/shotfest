<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $periodo        WP_Post
 *   $clasificacion  array
 */

$edicion = get_post_meta( $periodo->ID, '_sf_periodo_edicion_year', true );
?>
<div class="sf-resultados-wrap">
    <header class="sf-header">
        <h1 class="sf-titulo"><?php echo esc_html( sprintf( __( 'Resultados ShotFest %s', 'shotfest-votaciones' ), $edicion ) ); ?></h1>
    </header>

    <?php if ( empty( $clasificacion ) ) : ?>
        <p class="sf-aviso"><?php esc_html_e( 'No hay resultados disponibles.', 'shotfest-votaciones' ); ?></p>
    <?php else : ?>
        <?php foreach ( $clasificacion as $cat_data ) : ?>
            <section class="sf-categoria-resultados">
                <h2 class="sf-categoria-nombre"><?php echo esc_html( $cat_data['categoria']->name ); ?></h2>
                <ol class="sf-resultados-lista">
                    <?php foreach ( $cat_data['spots'] as $entry ) :
                        if ( ! $entry['shortlist'] ) continue;
                    ?>
                        <li class="sf-resultado-item sf-shortlist">
                            <span class="sf-resultado-titulo"><?php echo esc_html( $entry['post']->post_title ); ?></span>
                            <?php $marca = get_post_meta( $entry['post']->ID, '_sf_spot_marca', true ); ?>
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
</div>
