<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $periodo         WP_Post
 *   $spots           WP_Post[]
 *   $votos_emitidos  array<int,int>  spot_id => valor
 */

$edicion   = get_post_meta( $periodo->ID, '_sf_periodo_edicion_year', true );
$fecha_fin = get_post_meta( $periodo->ID, '_sf_periodo_fecha_fin', true );
$total     = count( $spots );
$votados   = count( array_filter( $spots, fn( $s ) => isset( $votos_emitidos[ $s->ID ] ) ) );
?>
<div class="sf-home-jurado">
    <header class="sf-header">
        <h1 class="sf-titulo"><?php echo esc_html( sprintf( __( 'Votaciones ShotFest %s', 'shotfest-votaciones' ), $edicion ) ); ?></h1>
        <div class="sf-progreso-wrap">
            <p class="sf-progreso-texto">
                <?php echo esc_html( sprintf(
                    __( 'Has votado %d de %d spots', 'shotfest-votaciones' ),
                    $votados,
                    $total
                ) ); ?>
            </p>
            <div class="sf-progreso-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $votados ); ?>" aria-valuemax="<?php echo esc_attr( $total ); ?>">
                <div class="sf-progreso-fill" style="width: <?php echo $total > 0 ? esc_attr( round( $votados / $total * 100 ) ) : 0; ?>%"></div>
            </div>
        </div>
        <?php if ( $fecha_fin ) : ?>
            <p class="sf-fecha-cierre">
                <?php echo esc_html( sprintf(
                    __( 'La votación cierra el %s', 'shotfest-votaciones' ),
                    date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $fecha_fin ) )
                ) ); ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if ( empty( $spots ) ) : ?>
        <p class="sf-aviso"><?php esc_html_e( 'No hay spots disponibles para votar en este momento.', 'shotfest-votaciones' ); ?></p>
    <?php else : ?>
        <div class="sf-spots-grid">
            <?php foreach ( $spots as $spot ) :
                $ya_votado  = isset( $votos_emitidos[ $spot->ID ] );
                $valor_voto = $votos_emitidos[ $spot->ID ] ?? null;
                include SF_PLUGIN_DIR . 'templates/partials/spot-card.php';
            endforeach; ?>
        </div>
    <?php endif; ?>
</div>
