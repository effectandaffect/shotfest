<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Security;

class Guard {

    public static function check_nonce( string $nonce, string $action ): void {
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die( esc_html__( 'Acción no autorizada.', 'shotfest-votaciones' ), 403 );
        }
    }

    public static function require_cap( string $capability ): void {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'No tienes permiso para realizar esta acción.', 'shotfest-votaciones' ), 403 );
        }
    }

    public static function require_login(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Debes iniciar sesión para acceder a esta página.', 'shotfest-votaciones' ), 401 );
        }
    }

    public static function es_jurado(): bool {
        return is_user_logged_in() && current_user_can( 'sf_ver_spots' );
    }

    public static function json_error( string $mensaje, int $status = 400 ): never {
        wp_send_json_error( [ 'mensaje' => $mensaje ], $status );
    }

    public static function json_ok( array $data = [] ): never {
        wp_send_json_success( $data );
    }
}
