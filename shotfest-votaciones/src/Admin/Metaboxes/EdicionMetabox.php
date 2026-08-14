<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Metaboxes;

use ShotfestVotaciones\PostTypes\EdicionPostType;

class EdicionMetabox {

    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . EdicionPostType::POST_TYPE, [ $this, 'save' ] );
    }

    public function add_meta_boxes(): void {
        add_meta_box(
            'sf_edicion_datos',
            __( 'Datos de la Edición', 'shotfest-votaciones' ),
            [ $this, 'render' ],
            EdicionPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render( \WP_Post $post ): void {
        wp_nonce_field( 'sf_edicion_save', 'sf_edicion_nonce' );

        $anio = get_post_meta( $post->ID, '_sf_edicion_anio', true ) ?: (string) date( 'Y' );
        ?>
        <table class="form-table sf-metabox-table">
            <tr>
                <th><label for="sf_edicion_anio"><?php esc_html_e( 'Año', 'shotfest-votaciones' ); ?></label></th>
                <td><input type="number" id="sf_edicion_anio" name="sf_edicion_anio" value="<?php echo esc_attr( $anio ); ?>" min="2020" max="2099" style="width:100px;"></td>
            </tr>
        </table>
        <?php
    }

    public function save( int $post_id ): void {
        if ( ! isset( $_POST['sf_edicion_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sf_edicion_nonce'] ) ), 'sf_edicion_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'sf_gestionar_ediciones' ) ) {
            return;
        }

        if ( isset( $_POST['sf_edicion_anio'] ) ) {
            update_post_meta( $post_id, '_sf_edicion_anio', absint( $_POST['sf_edicion_anio'] ) );
        }
    }
}
