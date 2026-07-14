<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Frontend;

use ShotfestVotaciones\Domain\VotoService;
use ShotfestVotaciones\Security\Guard;

class VotoAjaxController {

    public function __construct(
        private readonly VotoService $voto_service
    ) {}

    public function register(): void {
        add_action( 'wp_ajax_sf_emitir_voto', [ $this, 'emitir_voto' ] );
        // No se permite votar sin sesión
        add_action( 'wp_ajax_nopriv_sf_emitir_voto', [ $this, 'no_autenticado' ] );
    }

    public function emitir_voto(): void {
        // Nonce y capability
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sf_emitir_voto' ) ) {
            Guard::json_error( __( 'Solicitud no válida.', 'shotfest-votaciones' ), 403 );
        }

        if ( ! current_user_can( 'sf_votar_spot' ) ) {
            Guard::json_error( __( 'No tienes permiso para votar.', 'shotfest-votaciones' ), 403 );
        }

        $spot_id = isset( $_POST['spot_id'] ) ? absint( $_POST['spot_id'] ) : 0;
        $valor   = isset( $_POST['valor'] ) ? (int) $_POST['valor'] : -1;

        if ( ! $spot_id ) {
            Guard::json_error( __( 'Spot no válido.', 'shotfest-votaciones' ) );
        }

        $resultado = $this->voto_service->registrarVoto( get_current_user_id(), $spot_id, $valor );

        if ( $resultado['ok'] ) {
            Guard::json_ok( [ 'mensaje' => $resultado['mensaje'] ] );
        } else {
            Guard::json_error( $resultado['mensaje'] );
        }
    }

    public function no_autenticado(): void {
        Guard::json_error( __( 'Debes iniciar sesión para votar.', 'shotfest-votaciones' ), 401 );
    }
}
