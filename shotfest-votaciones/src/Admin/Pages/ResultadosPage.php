<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

use ShotfestVotaciones\PostTypes\EdicionPostType;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Domain\PeriodoService;

class ResultadosPage {

    const TODOS = 'todos';

    public function __construct(
        private readonly PeriodoService       $periodo_service,
        private readonly ClasificacionService $clasificacion_service
    ) {}

    public function render(): void {
        if ( ! current_user_can( 'sf_ver_resultados' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }

        $edicion_id    = absint( $_GET['edicion_id'] ?? 0 );
        $periodo_param = isset( $_GET['periodo_id'] ) ? sanitize_text_field( $_GET['periodo_id'] ) : self::TODOS;

        $ediciones = get_posts( [
            'post_type'      => EdicionPostType::POST_TYPE,
            'post_status'    => PeriodoService::POST_STATUSES,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'DESC',
        ] );

        $periodos_edicion = $edicion_id ? $this->periodo_service->get_periodos_de_edicion( $edicion_id ) : [];
        $periodo_ids_validos = wp_list_pluck( $periodos_edicion, 'ID' );

        if ( self::TODOS !== $periodo_param && ! in_array( (int) $periodo_param, $periodo_ids_validos, true ) ) {
            $periodo_param = self::TODOS;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Resultados de votación', 'shotfest-votaciones' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="sf-resultados">
                <select id="sf_resultados_edicion" name="edicion_id" required>
                    <option value="" <?php selected( $edicion_id, 0 ); ?>><?php esc_html_e( '— Selecciona una Edición —', 'shotfest-votaciones' ); ?></option>
                    <?php foreach ( $ediciones as $e ) : ?>
                        <option value="<?php echo esc_attr( $e->ID ); ?>" <?php selected( $edicion_id, $e->ID ); ?>>
                            <?php echo esc_html( $e->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ( $edicion_id ) : ?>
                    <select id="sf_resultados_periodo" name="periodo_id">
                        <option value="<?php echo esc_attr( self::TODOS ); ?>" <?php selected( $periodo_param, self::TODOS ); ?>>
                            <?php esc_html_e( 'Todos los periodos', 'shotfest-votaciones' ); ?>
                        </option>
                        <?php foreach ( $periodos_edicion as $p ) : ?>
                            <option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $periodo_param, (string) $p->ID ); ?>>
                                <?php echo esc_html( $p->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php submit_button( __( 'Ver resultados', 'shotfest-votaciones' ), 'secondary', 'submit', false ); ?>
            </form>
            <script>
            (function(){
                var edicion = document.getElementById('sf_resultados_edicion');
                if ( ! edicion ) { return; }
                edicion.addEventListener('change', function(){ edicion.form.submit(); });
            })();
            </script>

            <?php if ( ! $edicion_id ) : ?>
                <p class="description" style="margin-top:16px;"><?php esc_html_e( 'Selecciona una Edición para ver sus resultados.', 'shotfest-votaciones' ); ?></p>
                </div>
                <?php
                return;
            endif; ?>

            <?php if ( empty( $periodos_edicion ) ) : ?>
                <p class="description" style="margin-top:16px;"><?php esc_html_e( 'Esta Edición todavía no tiene ningún periodo.', 'shotfest-votaciones' ); ?></p>
            <?php else : ?>

                <?php if ( current_user_can( 'sf_exportar_resultados' ) ) : ?>
                    <p style="margin-top:16px;">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
                            <?php wp_nonce_field( 'sf_export_clasificacion', 'sf_export_nonce' ); ?>
                            <input type="hidden" name="action" value="sf_export_clasificacion">
                            <?php if ( self::TODOS === $periodo_param ) : ?>
                                <input type="hidden" name="edicion_id" value="<?php echo esc_attr( (string) $edicion_id ); ?>">
                            <?php else : ?>
                                <input type="hidden" name="periodo_id" value="<?php echo esc_attr( $periodo_param ); ?>">
                            <?php endif; ?>
                            <?php submit_button( __( 'Descargar CSV clasificación', 'shotfest-votaciones' ), 'secondary', 'submit', false ); ?>
                        </form>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                            <?php wp_nonce_field( 'sf_export_votos', 'sf_export_nonce' ); ?>
                            <input type="hidden" name="action" value="sf_export_votos">
                            <?php if ( self::TODOS === $periodo_param ) : ?>
                                <input type="hidden" name="edicion_id" value="<?php echo esc_attr( (string) $edicion_id ); ?>">
                            <?php else : ?>
                                <input type="hidden" name="periodo_id" value="<?php echo esc_attr( $periodo_param ); ?>">
                            <?php endif; ?>
                            <?php submit_button( __( 'Descargar CSV votos detallados', 'shotfest-votaciones' ), 'secondary', 'submit', false ); ?>
                        </form>
                    </p>
                <?php endif; ?>

                <?php
                $periodos_a_mostrar = self::TODOS === $periodo_param
                    ? $periodos_edicion
                    : array_filter( [ get_post( (int) $periodo_param ) ] );

                foreach ( $periodos_a_mostrar as $periodo ) :
                    $spots         = $this->periodo_service->get_todos_spots_del_periodo( $periodo->ID );
                    $clasificacion = $this->clasificacion_service->clasificacion_por_periodo( $periodo->ID, $spots );
                    ?>
                    <hr>
                    <h2><?php echo esc_html( $periodo->post_title ); ?></h2>

                    <?php if ( empty( $clasificacion ) ) : ?>
                        <p><?php esc_html_e( 'No hay votos registrados para este periodo.', 'shotfest-votaciones' ); ?></p>
                    <?php else : ?>
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
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
        <?php
    }
}
