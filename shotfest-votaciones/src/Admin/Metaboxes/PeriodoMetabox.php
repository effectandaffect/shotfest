<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Metaboxes;

use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Notifications\NotificationEvents;

class PeriodoMetabox {

    const ESTADOS = [
        'pendiente' => 'Pendiente',
        'abierto'   => 'Abierto',
        'cerrado'   => 'Cerrado',
    ];

    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . PeriodoPostType::POST_TYPE, [ $this, 'save' ] );
    }

    public function add_meta_boxes(): void {
        add_meta_box(
            'sf_periodo_datos',
            __( 'Datos del Periodo', 'shotfest-votaciones' ),
            [ $this, 'render' ],
            PeriodoPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render( \WP_Post $post ): void {
        wp_nonce_field( 'sf_periodo_save', 'sf_periodo_nonce' );

        $fecha_ini  = get_post_meta( $post->ID, '_sf_periodo_fecha_inicio', true );
        $fecha_fin  = get_post_meta( $post->ID, '_sf_periodo_fecha_fin', true );
        $estado     = get_post_meta( $post->ID, '_sf_periodo_estado', true ) ?: 'pendiente';
        $edicion    = get_post_meta( $post->ID, '_sf_periodo_edicion_year', true ) ?: (string) date( 'Y' );
        $publicados = get_post_meta( $post->ID, '_sf_periodo_resultados_publicados', true );
        ?>
        <table class="form-table sf-metabox-table">
            <tr>
                <th><label for="sf_periodo_edicion_year"><?php esc_html_e( 'Edición (año)', 'shotfest-votaciones' ); ?></label></th>
                <td><input type="number" id="sf_periodo_edicion_year" name="sf_periodo_edicion_year" value="<?php echo esc_attr( $edicion ); ?>" min="2020" max="2099" style="width:100px;"></td>
            </tr>
            <tr>
                <th><label for="sf_periodo_fecha_inicio"><?php esc_html_e( 'Fecha de inicio', 'shotfest-votaciones' ); ?></label></th>
                <td><input type="datetime-local" id="sf_periodo_fecha_inicio" name="sf_periodo_fecha_inicio" value="<?php echo esc_attr( $fecha_ini ); ?>"></td>
            </tr>
            <tr>
                <th><label for="sf_periodo_fecha_fin"><?php esc_html_e( 'Fecha de cierre', 'shotfest-votaciones' ); ?></label></th>
                <td><input type="datetime-local" id="sf_periodo_fecha_fin" name="sf_periodo_fecha_fin" value="<?php echo esc_attr( $fecha_fin ); ?>"></td>
            </tr>
            <tr>
                <th><label for="sf_periodo_estado"><?php esc_html_e( 'Estado', 'shotfest-votaciones' ); ?></label></th>
                <td>
                    <select id="sf_periodo_estado" name="sf_periodo_estado">
                        <?php foreach ( self::ESTADOS as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $estado, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Al cambiar a "Abierto" se envían emails de apertura al jurado.', 'shotfest-votaciones' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Resultados publicados', 'shotfest-votaciones' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sf_periodo_resultados_publicados" value="1" <?php checked( $publicados, '1' ); ?>>
                        <?php esc_html_e( 'Publicar resultados visibles para el jurado', 'shotfest-votaciones' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save( int $post_id ): void {
        if ( ! isset( $_POST['sf_periodo_nonce'] ) || ! wp_verify_nonce( $_POST['sf_periodo_nonce'], 'sf_periodo_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'sf_gestionar_periodos' ) ) {
            return;
        }

        $estado_anterior = get_post_meta( $post_id, '_sf_periodo_estado', true );

        if ( isset( $_POST['sf_periodo_fecha_inicio'] ) ) {
            update_post_meta( $post_id, '_sf_periodo_fecha_inicio', sanitize_text_field( $_POST['sf_periodo_fecha_inicio'] ) );
        }
        if ( isset( $_POST['sf_periodo_fecha_fin'] ) ) {
            update_post_meta( $post_id, '_sf_periodo_fecha_fin', sanitize_text_field( $_POST['sf_periodo_fecha_fin'] ) );
        }
        if ( isset( $_POST['sf_periodo_edicion_year'] ) ) {
            update_post_meta( $post_id, '_sf_periodo_edicion_year', absint( $_POST['sf_periodo_edicion_year'] ) );
        }

        $nuevo_estado = '';
        if ( isset( $_POST['sf_periodo_estado'] ) && array_key_exists( $_POST['sf_periodo_estado'], self::ESTADOS ) ) {
            $nuevo_estado = $_POST['sf_periodo_estado'];
            update_post_meta( $post_id, '_sf_periodo_estado', $nuevo_estado );
        }

        $publicados = isset( $_POST['sf_periodo_resultados_publicados'] ) ? '1' : '0';
        update_post_meta( $post_id, '_sf_periodo_resultados_publicados', $publicados );

        // Disparar evento de apertura si el estado cambia a "abierto"
        if ( 'abierto' === $nuevo_estado && 'abierto' !== $estado_anterior ) {
            do_action( 'shotfest_periodo_abierto', $post_id );
        }
    }
}
