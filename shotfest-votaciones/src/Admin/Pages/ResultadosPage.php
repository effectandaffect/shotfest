<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Domain\PeriodoService;

class ResultadosPage {

    public function __construct(
        private readonly PeriodoService       $periodo_service,
        private readonly ClasificacionService $clasificacion_service
    ) {}

    public function render(): void {
        if ( ! current_user_can( 'sf_ver_resultados' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }

        $periodo_id = isset( $_GET['periodo_id'] ) ? absint( $_GET['periodo_id'] ) : 0;

        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $clasificacion = [];
        $periodo       = null;

        if ( $periodo_id ) {
            $periodo = get_post( $periodo_id );
            if ( $periodo ) {
                $spots         = $this->periodo_service->get_todos_spots_del_periodo( $periodo_id );
                $clasificacion = $this->clasificacion_service->clasificacion_por_periodo( $periodo_id, $spots );
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Resultados de votación', 'shotfest-votaciones' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="sf-resultados">
                <select name="periodo_id">
                    <option value=""><?php esc_html_e( '— Selecciona un periodo —', 'shotfest-votaciones' ); ?></option>
                    <?php foreach ( $periodos as $p ) : ?>
                        <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $periodo_id, $p->ID ); ?>>
                            <?php echo esc_html( $p->post_title ); ?> (<?php echo esc_html( get_post_meta( $p->ID, '_sf_periodo_edicion_year', true ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Ver resultados', 'shotfest-votaciones' ), 'secondary', 'submit', false ); ?>
            </form>

            <?php if ( $periodo && ! empty( $clasificacion ) ) : ?>
                <hr>
                <h2><?php echo esc_html( $periodo->post_title ); ?></h2>

                <?php foreach ( $clasificacion as $cat_data ) : ?>
                    <h3><?php echo esc_html( $cat_data['categoria']->name ); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Pos.', 'shotfest-votaciones' ); ?></th>
                                <th><?php esc_html_e( 'Spot', 'shotfest-votaciones' ); ?></th>
                                <th><?php esc_html_e( 'Marca', 'shotfest-votaciones' ); ?></th>
                                <th><?php esc_html_e( 'Votos Sí', 'shotfest-votaciones' ); ?></th>
                                <th><?php esc_html_e( 'Votos No', 'shotfest-votaciones' ); ?></th>
                                <th><?php esc_html_e( 'Shortlist', 'shotfest-votaciones' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $cat_data['spots'] as $entry ) : ?>
                                <tr <?php echo $entry['shortlist'] ? 'class="sf-shortlist"' : ''; ?>>
                                    <td><?php echo esc_html( $entry['posicion'] ); ?></td>
                                    <td><?php echo esc_html( $entry['post']->post_title ); ?></td>
                                    <td><?php echo esc_html( get_post_meta( $entry['post']->ID, '_sf_spot_marca', true ) ); ?></td>
                                    <td><strong><?php echo esc_html( $entry['si'] ); ?></strong></td>
                                    <td><?php echo esc_html( $entry['no'] ); ?></td>
                                    <td><?php echo $entry['shortlist'] ? '⭐ ' . esc_html__( 'Shortlist', 'shotfest-votaciones' ) : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>

            <?php elseif ( $periodo_id ) : ?>
                <p><?php esc_html_e( 'No hay votos registrados para este periodo.', 'shotfest-votaciones' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
