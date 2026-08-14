<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Metaboxes;

use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\PostTypes\EdicionPostType;
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
        echo '<input type="hidden" name="sf_enviar_email_apertura" id="sf_enviar_email_apertura" value="1">';

        // Las fechas se guardan como 'Y-m-d H:i:s' pero el input las necesita como 'Y-m-dTH:i'
        $fecha_ini  = PeriodoService::fecha_para_input( (string) get_post_meta( $post->ID, '_sf_periodo_fecha_inicio', true ) );
        $fecha_fin  = PeriodoService::fecha_para_input( (string) get_post_meta( $post->ID, '_sf_periodo_fecha_fin', true ) );
        $estado     = get_post_meta( $post->ID, '_sf_periodo_estado', true ) ?: 'pendiente';
        $edicion_id = get_post_meta( $post->ID, '_sf_periodo_edicion_id', true );
        $publicados = get_post_meta( $post->ID, '_sf_periodo_resultados_publicados', true );

        $ediciones = get_posts( [
            'post_type'      => EdicionPostType::POST_TYPE,
            'post_status'    => PeriodoService::POST_STATUSES,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'DESC',
        ] );
        ?>
        <table class="form-table sf-metabox-table">
            <tr>
                <th><label for="sf_periodo_edicion_id"><?php esc_html_e( 'Edición', 'shotfest-votaciones' ); ?></label></th>
                <td>
                    <select id="sf_periodo_edicion_id" name="sf_periodo_edicion_id">
                        <option value=""><?php esc_html_e( '— Sin edición —', 'shotfest-votaciones' ); ?></option>
                        <?php foreach ( $ediciones as $e ) : ?>
                            <option value="<?php echo esc_attr( $e->ID ); ?>" <?php selected( (string) $edicion_id, (string) $e->ID ); ?>>
                                <?php echo esc_html( $e->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
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
                    <p class="description"><?php esc_html_e( 'Al cambiar a "Abierto" se envían emails de apertura al jurado de esta edición.', 'shotfest-votaciones' ); ?></p>
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
        <script>
        (function(){
            var estadoSelect   = document.getElementById('sf_periodo_estado');
            var estadoOriginal = <?php echo wp_json_encode( $estado ); ?>;
            var postForm       = document.getElementById('post');
            var enviarBit      = document.getElementById('sf_enviar_email_apertura');
            if ( ! estadoSelect || ! postForm || ! enviarBit ) { return; }
            postForm.addEventListener('submit', function(){
                if ( 'abierto' === estadoSelect.value && estadoOriginal !== estadoSelect.value ) {
                    var msg = <?php echo wp_json_encode( __( 'Se enviará un email de apertura de votación a todo el jurado de esta edición. ¿Confirmas el envío? El periodo se guardará como abierto igualmente si cancelas.', 'shotfest-votaciones' ) ); ?>;
                    enviarBit.value = confirm( msg ) ? '1' : '0';
                }
            });
        })();
        </script>
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

        // Se normaliza a 'Y-m-d H:i:s' antes de persistir: el input envía «T» como
        // separador y sin segundos, y las comparaciones de vigencia necesitan un
        // formato único y parseable (ver PeriodoService::normalizar_fecha()).
        if ( isset( $_POST['sf_periodo_fecha_inicio'] ) ) {
            update_post_meta(
                $post_id,
                '_sf_periodo_fecha_inicio',
                PeriodoService::normalizar_fecha( sanitize_text_field( wp_unslash( $_POST['sf_periodo_fecha_inicio'] ) ) )
            );
        }
        if ( isset( $_POST['sf_periodo_fecha_fin'] ) ) {
            update_post_meta(
                $post_id,
                '_sf_periodo_fecha_fin',
                PeriodoService::normalizar_fecha( sanitize_text_field( wp_unslash( $_POST['sf_periodo_fecha_fin'] ) ) )
            );
        }
        if ( isset( $_POST['sf_periodo_edicion_id'] ) ) {
            update_post_meta( $post_id, '_sf_periodo_edicion_id', absint( $_POST['sf_periodo_edicion_id'] ) );
        }

        $nuevo_estado = '';
        if ( isset( $_POST['sf_periodo_estado'] ) && array_key_exists( $_POST['sf_periodo_estado'], self::ESTADOS ) ) {
            $nuevo_estado = $_POST['sf_periodo_estado'];
            update_post_meta( $post_id, '_sf_periodo_estado', $nuevo_estado );
        }

        $publicados = isset( $_POST['sf_periodo_resultados_publicados'] ) ? '1' : '0';
        update_post_meta( $post_id, '_sf_periodo_resultados_publicados', $publicados );

        // Disparar evento de apertura si el estado cambia a "abierto" (salvo que se haya cancelado el envío de email)
        $enviar_email = ( $_POST['sf_enviar_email_apertura'] ?? '1' ) !== '0';
        if ( 'abierto' === $nuevo_estado && 'abierto' !== $estado_anterior && $enviar_email ) {
            do_action( 'shotfest_periodo_abierto', $post_id );
        }
    }
}
