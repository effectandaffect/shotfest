<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

use ShotfestVotaciones\Roles\JuradoRole;
use ShotfestVotaciones\PostTypes\EdicionPostType;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Data\VotoRepository;

class UsuariosJuradoPage {

    public function __construct(
        private readonly PeriodoService $periodo_service,
        private readonly VotoRepository $voto_repo
    ) {}

    public function render(): void {
        if ( ! current_user_can( 'sf_gestionar_jurado' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }

        $mensaje = '';
        $error   = '';
        $editado = false;

        if ( isset( $_POST['sf_alta_jurado_nonce'] ) && wp_verify_nonce( $_POST['sf_alta_jurado_nonce'], 'sf_alta_jurado' ) ) {
            [ $mensaje, $error ] = $this->procesar_alta();
        }

        if ( isset( $_POST['sf_editar_jurado_nonce'] ) && wp_verify_nonce( $_POST['sf_editar_jurado_nonce'], 'sf_editar_jurado' ) ) {
            [ $mensaje, $error ] = $this->procesar_edicion();
            if ( $mensaje ) {
                $editado = true;
            }
        }

        if ( isset( $_GET['sf_action'], $_GET['user_id'], $_GET['_wpnonce'] )
             && 'eliminar' === $_GET['sf_action']
             && wp_verify_nonce( $_GET['_wpnonce'], 'sf_eliminar_jurado_' . absint( $_GET['user_id'] ) )
        ) {
            [ $mensaje, $error ] = $this->procesar_eliminacion( absint( $_GET['user_id'] ) );
        }

        $ediciones      = $this->get_ediciones();
        $filtro_edicion = absint( $_GET['edicion_id'] ?? 0 );

        $show_edit = ! $editado
                     && isset( $_GET['sf_action'], $_GET['user_id'] )
                     && 'editar' === $_GET['sf_action'];
        $editing_user = $show_edit ? get_userdata( absint( $_GET['user_id'] ) ) : null;

        if ( $filtro_edicion > 0 ) {
            $jurado = get_users( [
                'role'       => JuradoRole::ROLE_SLUG,
                'meta_key'   => '_sf_jurado_edicion_id',
                'meta_value' => $filtro_edicion,
            ] );
        } else {
            $jurado = get_users( [ 'role' => JuradoRole::ROLE_SLUG ] );
        }

        $periodo_abierto = $this->periodo_service->get_periodo_abierto();
        $total_spots     = $periodo_abierto ? count( $this->periodo_service->get_spots_del_periodo( $periodo_abierto->ID ) ) : 0;

        $page_url = admin_url( 'admin.php?page=sf-jurado' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Gestión del Jurado', 'shotfest-votaciones' ); ?></h1>

            <?php if ( $mensaje ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $mensaje ); ?></p></div>
            <?php endif; ?>
            <?php if ( $error ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <form method="get" style="margin:16px 0;display:flex;align-items:center;gap:8px;">
                <input type="hidden" name="page" value="sf-jurado">
                <label for="edicion_id"><strong><?php esc_html_e( 'Filtrar por Edición:', 'shotfest-votaciones' ); ?></strong></label>
                <select name="edicion_id" id="edicion_id">
                    <option value="0"><?php esc_html_e( '— Todas —', 'shotfest-votaciones' ); ?></option>
                    <?php foreach ( $ediciones as $e ) : ?>
                        <option value="<?php echo esc_attr( (string) $e->ID ); ?>" <?php selected( $filtro_edicion, $e->ID ); ?>>
                            <?php echo esc_html( $e->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Filtrar', 'shotfest-votaciones' ), 'secondary', 'submit', false ); ?>
            </form>

            <?php if ( $editing_user ) : ?>
                <?php $this->render_form_edicion( $editing_user, $ediciones, $page_url ); ?>
            <?php else : ?>
                <?php $this->render_form_alta( $ediciones ); ?>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Miembros actuales del jurado', 'shotfest-votaciones' ); ?></h2>

            <?php if ( ! $periodo_abierto ) : ?>
                <p class="description"><?php esc_html_e( 'No hay ningún periodo abierto ahora mismo — no se muestra el estado de votación.', 'shotfest-votaciones' ); ?></p>
            <?php endif; ?>

            <?php if ( empty( $jurado ) ) : ?>
                <p><?php esc_html_e( 'No hay miembros del jurado para este filtro.', 'shotfest-votaciones' ); ?></p>
            <?php else : ?>
                <?php $this->render_tabla( $jurado, $periodo_abierto, $total_spots, $page_url ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_form_alta( array $ediciones ): void {
        ?>
        <h2><?php esc_html_e( 'Añadir miembro del jurado', 'shotfest-votaciones' ); ?></h2>
        <form method="post" id="sf-form-alta-jurado">
            <?php wp_nonce_field( 'sf_alta_jurado', 'sf_alta_jurado_nonce' ); ?>
            <input type="hidden" name="sf_enviar_email" id="sf_enviar_email_alta" value="1">
            <table class="form-table">
                <tr>
                    <th><label for="sf_nombre"><?php esc_html_e( 'Nombre', 'shotfest-votaciones' ); ?></label></th>
                    <td><input type="text" id="sf_nombre" name="sf_nombre" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="sf_apellidos"><?php esc_html_e( 'Apellidos', 'shotfest-votaciones' ); ?></label></th>
                    <td><input type="text" id="sf_apellidos" name="sf_apellidos" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="sf_email"><?php esc_html_e( 'Email', 'shotfest-votaciones' ); ?></label></th>
                    <td><input type="email" id="sf_email" name="sf_email" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Ediciones', 'shotfest-votaciones' ); ?></th>
                    <td>
                        <?php foreach ( $ediciones as $e ) : ?>
                            <label style="display:block;margin-bottom:4px;">
                                <input type="checkbox" name="sf_ediciones[]" value="<?php echo esc_attr( (string) $e->ID ); ?>">
                                <?php echo esc_html( $e->post_title ); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Añadir miembro del jurado', 'shotfest-votaciones' ) ); ?>
        </form>
        <script>
        (function(){
            var form      = document.getElementById('sf-form-alta-jurado');
            var email     = document.getElementById('sf_email');
            var enviarBit = document.getElementById('sf_enviar_email_alta');
            if ( ! form || ! email || ! enviarBit ) { return; }
            form.addEventListener('submit', function(){
                var msg = <?php echo wp_json_encode( __( 'Se enviará un email de bienvenida con acceso a la cuenta a', 'shotfest-votaciones' ) ); ?> + ' ' + email.value + '. ' + <?php echo wp_json_encode( __( '¿Confirmas el envío? El miembro se creará igualmente si cancelas.', 'shotfest-votaciones' ) ); ?>;
                enviarBit.value = confirm( msg ) ? '1' : '0';
            });
        })();
        </script>
        <?php
    }

    private function render_form_edicion( \WP_User $user, array $ediciones, string $page_url ): void {
        $user_ediciones = array_map( 'intval', get_user_meta( $user->ID, '_sf_jurado_edicion_id', false ) );
        ?>
        <h2><?php echo esc_html( sprintf( __( 'Editar: %s', 'shotfest-votaciones' ), $user->display_name ) ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'sf_editar_jurado', 'sf_editar_jurado_nonce' ); ?>
            <input type="hidden" name="sf_user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="sf_nombre"><?php esc_html_e( 'Nombre', 'shotfest-votaciones' ); ?></label></th>
                    <td><input type="text" id="sf_nombre" name="sf_nombre" class="regular-text" value="<?php echo esc_attr( $user->first_name ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="sf_apellidos"><?php esc_html_e( 'Apellidos', 'shotfest-votaciones' ); ?></label></th>
                    <td><input type="text" id="sf_apellidos" name="sf_apellidos" class="regular-text" value="<?php echo esc_attr( $user->last_name ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="sf_email"><?php esc_html_e( 'Email', 'shotfest-votaciones' ); ?></label></th>
                    <td><input type="email" id="sf_email" name="sf_email" class="regular-text" value="<?php echo esc_attr( $user->user_email ); ?>" required></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Ediciones', 'shotfest-votaciones' ); ?></th>
                    <td>
                        <?php foreach ( $ediciones as $e ) : ?>
                            <label style="display:block;margin-bottom:4px;">
                                <input type="checkbox" name="sf_ediciones[]" value="<?php echo esc_attr( (string) $e->ID ); ?>"
                                    <?php checked( in_array( $e->ID, $user_ediciones, true ) ); ?>>
                                <?php echo esc_html( $e->post_title ); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Guardar cambios', 'shotfest-votaciones' ) ); ?>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button">
                <?php esc_html_e( 'Cancelar', 'shotfest-votaciones' ); ?>
            </a>
        </form>
        <?php
    }

    private function render_tabla( array $jurado, ?\WP_Post $periodo_abierto, int $total_spots, string $page_url ): void {
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Nombre', 'shotfest-votaciones' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'shotfest-votaciones' ); ?></th>
                    <th><?php esc_html_e( 'Usuario', 'shotfest-votaciones' ); ?></th>
                    <th><?php esc_html_e( 'Ediciones', 'shotfest-votaciones' ); ?></th>
                    <th><?php esc_html_e( 'Votación', 'shotfest-votaciones' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'shotfest-votaciones' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $jurado as $user ) :
                    $ediciones_labels = $this->ediciones_de_usuario( $user->ID );

                    if ( $periodo_abierto && $total_spots > 0 ) {
                        $votados      = count( $this->voto_repo->votos_usuario( $user->ID, $periodo_abierto->ID ) );
                        $completo     = $votados >= $total_spots;
                        $voto_display = $votados . ' / ' . $total_spots;
                    } else {
                        $votados      = 0;
                        $completo     = false;
                        $voto_display = '—';
                    }

                    $edit_url = add_query_arg( [
                        'page'      => 'sf-jurado',
                        'sf_action' => 'editar',
                        'user_id'   => $user->ID,
                    ], admin_url( 'admin.php' ) );

                    $delete_url = wp_nonce_url(
                        add_query_arg( [
                            'page'      => 'sf-jurado',
                            'sf_action' => 'eliminar',
                            'user_id'   => $user->ID,
                        ], admin_url( 'admin.php' ) ),
                        'sf_eliminar_jurado_' . $user->ID
                    );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
                        <td><?php echo esc_html( $user->user_email ); ?></td>
                        <td><?php echo esc_html( $user->user_login ); ?></td>
                        <td><?php echo esc_html( $ediciones_labels ? implode( ', ', $ediciones_labels ) : '—' ); ?></td>
                        <td>
                            <?php if ( ! $periodo_abierto || 0 === $total_spots ) : ?>
                                <span style="color:#999;">—</span>
                            <?php elseif ( $completo ) : ?>
                                <span style="color:#198754;font-weight:600;">&#10003; <?php echo esc_html( $voto_display ); ?></span>
                            <?php elseif ( $votados > 0 ) : ?>
                                <span style="color:#e67e00;font-weight:600;"><?php echo esc_html( $voto_display ); ?></span>
                            <?php else : ?>
                                <span style="color:#dc3545;"><?php esc_html_e( 'Sin votar', 'shotfest-votaciones' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Editar', 'shotfest-votaciones' ); ?></a>
                            &nbsp;|&nbsp;
                            <a href="<?php echo esc_url( $delete_url ); ?>"
                               onclick="return confirm('<?php echo esc_js( __( '¿Eliminar este miembro del jurado?', 'shotfest-votaciones' ) ); ?>')"
                               style="color:#dc3545;">
                                <?php esc_html_e( 'Eliminar', 'shotfest-votaciones' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function get_ediciones(): array {
        return get_posts( [
            'post_type'      => EdicionPostType::POST_TYPE,
            'post_status'    => PeriodoService::POST_STATUSES,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'DESC',
        ] );
    }

    private function ediciones_de_usuario( int $user_id ): array {
        $ids = array_map( 'intval', get_user_meta( $user_id, '_sf_jurado_edicion_id', false ) );
        $labels = [];
        foreach ( $ids as $id ) {
            $p = get_post( $id );
            if ( $p ) {
                $labels[] = $p->post_title;
            }
        }
        return $labels;
    }

    /** @return array{0:string,1:string} [mensaje, error] */
    private function procesar_alta(): array {
        $nombre    = sanitize_text_field( $_POST['sf_nombre'] ?? '' );
        $apellidos = sanitize_text_field( $_POST['sf_apellidos'] ?? '' );
        $email     = sanitize_email( $_POST['sf_email'] ?? '' );
        $ediciones = array_map( 'absint', (array) ( $_POST['sf_ediciones'] ?? [] ) );

        if ( ! $nombre || ! is_email( $email ) ) {
            return [ '', __( 'Por favor, completa todos los campos requeridos con datos válidos.', 'shotfest-votaciones' ) ];
        }

        if ( email_exists( $email ) ) {
            return [ '', sprintf( __( 'Ya existe un usuario con el email %s.', 'shotfest-votaciones' ), $email ) ];
        }

        $login    = sanitize_user( strtolower( $nombre . '.' . $apellidos ), true );
        $login    = $login ?: sanitize_user( strtolower( $nombre ), true );
        $password = wp_generate_password( 20, true );

        $user_id = wp_insert_user( [
            'user_login'   => $login,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $nombre,
            'last_name'    => $apellidos,
            'display_name' => trim( $nombre . ' ' . $apellidos ),
            'role'         => JuradoRole::ROLE_SLUG,
        ] );

        if ( is_wp_error( $user_id ) ) {
            return [ '', $user_id->get_error_message() ];
        }

        foreach ( $ediciones as $edicion_id ) {
            if ( $edicion_id > 0 ) {
                add_user_meta( $user_id, '_sf_jurado_edicion_id', $edicion_id );
            }
        }

        $enviar_email = ( $_POST['sf_enviar_email'] ?? '1' ) !== '0';
        if ( $enviar_email ) {
            // Enviar email de bienvenida con enlace de establecer contraseña
            do_action( 'shotfest_jurado_alta', $user_id );

            return [ sprintf(
                __( 'Usuario %s creado correctamente. Se ha enviado un email con instrucciones de acceso.', 'shotfest-votaciones' ),
                esc_html( $email )
            ), '' ];
        }

        return [ sprintf(
            __( 'Usuario %s creado correctamente, sin enviar el email de bienvenida.', 'shotfest-votaciones' ),
            esc_html( $email )
        ), '' ];
    }

    /** @return array{0:string,1:string} [mensaje, error] */
    private function procesar_edicion(): array {
        $user_id   = absint( $_POST['sf_user_id'] ?? 0 );
        $nombre    = sanitize_text_field( $_POST['sf_nombre'] ?? '' );
        $apellidos = sanitize_text_field( $_POST['sf_apellidos'] ?? '' );
        $email     = sanitize_email( $_POST['sf_email'] ?? '' );
        $ediciones = array_map( 'absint', (array) ( $_POST['sf_ediciones'] ?? [] ) );

        if ( ! $user_id || ! $nombre || ! is_email( $email ) ) {
            return [ '', __( 'Datos inválidos.', 'shotfest-votaciones' ) ];
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [ '', __( 'Usuario no encontrado.', 'shotfest-votaciones' ) ];
        }

        $existing = email_exists( $email );
        if ( $existing && $existing !== $user_id ) {
            return [ '', sprintf( __( 'Ya existe otro usuario con el email %s.', 'shotfest-votaciones' ), $email ) ];
        }

        wp_update_user( [
            'ID'           => $user_id,
            'first_name'   => $nombre,
            'last_name'    => $apellidos,
            'display_name' => trim( $nombre . ' ' . $apellidos ),
            'user_email'   => $email,
        ] );

        delete_user_meta( $user_id, '_sf_jurado_edicion_id' );
        foreach ( $ediciones as $edicion_id ) {
            if ( $edicion_id > 0 ) {
                add_user_meta( $user_id, '_sf_jurado_edicion_id', $edicion_id );
            }
        }

        return [ sprintf( __( 'Datos de %s actualizados.', 'shotfest-votaciones' ), esc_html( trim( $nombre . ' ' . $apellidos ) ) ), '' ];
    }

    /** @return array{0:string,1:string} [mensaje, error] */
    private function procesar_eliminacion( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [ '', __( 'Usuario no encontrado.', 'shotfest-votaciones' ) ];
        }
        if ( ! in_array( JuradoRole::ROLE_SLUG, $user->roles, true ) ) {
            return [ '', __( 'Este usuario no es miembro del jurado.', 'shotfest-votaciones' ) ];
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $name = $user->display_name;
        wp_delete_user( $user_id );

        return [ sprintf( __( 'Miembro del jurado %s eliminado.', 'shotfest-votaciones' ), esc_html( $name ) ), '' ];
    }
}
