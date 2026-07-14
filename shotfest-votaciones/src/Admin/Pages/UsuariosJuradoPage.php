<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

use ShotfestVotaciones\Roles\JuradoRole;

class UsuariosJuradoPage {

    public function render(): void {
        if ( ! current_user_can( 'sf_gestionar_jurado' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }

        $mensaje = '';

        // Alta de nuevo usuario de jurado
        if ( isset( $_POST['sf_alta_jurado_nonce'] ) && wp_verify_nonce( $_POST['sf_alta_jurado_nonce'], 'sf_alta_jurado' ) ) {
            $mensaje = $this->procesar_alta();
        }

        $jurado = get_users( [ 'role' => JuradoRole::ROLE_SLUG ] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Gestión del Jurado', 'shotfest-votaciones' ); ?></h1>

            <?php if ( $mensaje ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $mensaje ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Añadir miembro del jurado', 'shotfest-votaciones' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'sf_alta_jurado', 'sf_alta_jurado_nonce' ); ?>
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
                </table>
                <?php submit_button( __( 'Añadir miembro del jurado', 'shotfest-votaciones' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Miembros actuales del jurado', 'shotfest-votaciones' ); ?></h2>
            <?php if ( empty( $jurado ) ) : ?>
                <p><?php esc_html_e( 'No hay miembros del jurado aún.', 'shotfest-votaciones' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Nombre', 'shotfest-votaciones' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'shotfest-votaciones' ); ?></th>
                            <th><?php esc_html_e( 'Usuario', 'shotfest-votaciones' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $jurado as $user ) : ?>
                            <tr>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo esc_html( $user->user_login ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function procesar_alta(): string {
        $nombre    = sanitize_text_field( $_POST['sf_nombre'] ?? '' );
        $apellidos = sanitize_text_field( $_POST['sf_apellidos'] ?? '' );
        $email     = sanitize_email( $_POST['sf_email'] ?? '' );

        if ( ! $nombre || ! is_email( $email ) ) {
            return __( 'Por favor, completa todos los campos requeridos con datos válidos.', 'shotfest-votaciones' );
        }

        if ( email_exists( $email ) ) {
            return sprintf( __( 'Ya existe un usuario con el email %s.', 'shotfest-votaciones' ), $email );
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
            return $user_id->get_error_message();
        }

        // Enviar email de bienvenida con enlace de establecer contraseña
        do_action( 'shotfest_jurado_alta', $user_id );

        return sprintf(
            __( 'Usuario %s creado correctamente. Se ha enviado un email con instrucciones de acceso.', 'shotfest-votaciones' ),
            esc_html( $email )
        );
    }
}
