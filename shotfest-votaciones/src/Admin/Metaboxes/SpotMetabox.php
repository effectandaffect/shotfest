<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Metaboxes;

use ShotfestVotaciones\PostTypes\SpotPostType;
use ShotfestVotaciones\PostTypes\PeriodoPostType;

class SpotMetabox {

    const ESTADOS = [
        'borrador'               => 'Borrador',
        'pendiente_publicacion'  => 'Pendiente de publicación',
        'disponible_votacion'    => 'Disponible para votación',
        'finalizado'             => 'Finalizado',
        'archivado'              => 'Archivado',
    ];

    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . SpotPostType::POST_TYPE, [ $this, 'save' ] );
    }

    public function add_meta_boxes(): void {
        add_meta_box(
            'sf_spot_datos',
            __( 'Datos del Spot', 'shotfest-votaciones' ),
            [ $this, 'render' ],
            SpotPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render( \WP_Post $post ): void {
        wp_nonce_field( 'sf_spot_save', 'sf_spot_nonce' );

        $marca      = get_post_meta( $post->ID, '_sf_spot_marca', true );
        $video_url  = get_post_meta( $post->ID, '_sf_spot_video_url', true );
        $estado     = get_post_meta( $post->ID, '_sf_spot_estado', true ) ?: 'borrador';
        $periodo_id = get_post_meta( $post->ID, '_sf_spot_periodo_id', true );
        $orden      = get_post_meta( $post->ID, '_sf_spot_orden', true );
        $obs        = get_post_meta( $post->ID, '_sf_spot_observaciones', true );

        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ?>
        <table class="form-table sf-metabox-table">
            <tr>
                <th><label for="sf_spot_marca"><?php esc_html_e( 'Marca/Anunciante', 'shotfest-votaciones' ); ?></label></th>
                <td><input type="text" id="sf_spot_marca" name="sf_spot_marca" value="<?php echo esc_attr( $marca ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="sf_spot_video_url"><?php esc_html_e( 'URL del vídeo (YouTube)', 'shotfest-votaciones' ); ?></label></th>
                <td>
                    <input type="url" id="sf_spot_video_url" name="sf_spot_video_url" value="<?php echo esc_attr( $video_url ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Enlace de YouTube no listado. Ej: https://www.youtube.com/watch?v=XXXXXXXXXXX', 'shotfest-votaciones' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="sf_spot_estado"><?php esc_html_e( 'Estado del spot', 'shotfest-votaciones' ); ?></label></th>
                <td>
                    <select id="sf_spot_estado" name="sf_spot_estado">
                        <?php foreach ( self::ESTADOS as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $estado, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sf_spot_periodo_id"><?php esc_html_e( 'Periodo de votación', 'shotfest-votaciones' ); ?></label></th>
                <td>
                    <select id="sf_spot_periodo_id" name="sf_spot_periodo_id">
                        <option value=""><?php esc_html_e( '— Sin periodo —', 'shotfest-votaciones' ); ?></option>
                        <?php foreach ( $periodos as $periodo ) : ?>
                            <option value="<?php echo esc_attr( $periodo->ID ); ?>" <?php selected( (string) $periodo_id, (string) $periodo->ID ); ?>>
                                <?php echo esc_html( $periodo->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sf_spot_orden"><?php esc_html_e( 'Orden de visualización', 'shotfest-votaciones' ); ?></label></th>
                <td><input type="number" id="sf_spot_orden" name="sf_spot_orden" value="<?php echo esc_attr( $orden ); ?>" min="0" step="1" style="width:80px;"></td>
            </tr>
            <tr>
                <th><label for="sf_spot_observaciones"><?php esc_html_e( 'Observaciones internas', 'shotfest-votaciones' ); ?></label></th>
                <td><textarea id="sf_spot_observaciones" name="sf_spot_observaciones" rows="3" class="large-text"><?php echo esc_textarea( $obs ); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    public function save( int $post_id ): void {
        if ( ! isset( $_POST['sf_spot_nonce'] ) || ! wp_verify_nonce( $_POST['sf_spot_nonce'], 'sf_spot_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'sf_gestionar_spots' ) ) {
            return;
        }

        $fields = [
            '_sf_spot_marca'          => 'sanitize_text_field',
            '_sf_spot_video_url'      => 'esc_url_raw',
            '_sf_spot_observaciones'  => 'sanitize_textarea_field',
        ];
        foreach ( $fields as $meta_key => $sanitizer ) {
            $input_key = str_replace( '_sf_spot_', 'sf_spot_', $meta_key );
            if ( isset( $_POST[ $input_key ] ) ) {
                update_post_meta( $post_id, $meta_key, $sanitizer( $_POST[ $input_key ] ) );
            }
        }

        if ( isset( $_POST['sf_spot_estado'] ) && array_key_exists( $_POST['sf_spot_estado'], self::ESTADOS ) ) {
            update_post_meta( $post_id, '_sf_spot_estado', $_POST['sf_spot_estado'] );
        }

        if ( isset( $_POST['sf_spot_periodo_id'] ) ) {
            $periodo_id = absint( $_POST['sf_spot_periodo_id'] );
            update_post_meta( $post_id, '_sf_spot_periodo_id', $periodo_id );
            if ( $periodo_id ) {
                $year = get_post_meta( $periodo_id, '_sf_periodo_edicion_year', true );
                update_post_meta( $post_id, '_sf_spot_edicion_year', $year );
            }
        }

        if ( isset( $_POST['sf_spot_orden'] ) ) {
            update_post_meta( $post_id, '_sf_spot_orden', absint( $_POST['sf_spot_orden'] ) );
        }
    }
}
