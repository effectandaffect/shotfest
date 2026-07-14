<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Domain;

use ShotfestVotaciones\Data\VotoRepository;

class VotoService {

    public function __construct(
        private readonly VotoRepository $repo,
        private readonly PeriodoService $periodo_service
    ) {}

    /**
     * Registra el voto de un usuario sobre un spot.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function registrarVoto( int $usuario_id, int $spot_id, int $valor ): array {
        // Valor solo puede ser 0 o 1
        if ( ! in_array( $valor, [ 0, 1 ], true ) ) {
            return [ 'ok' => false, 'mensaje' => __( 'Valor de voto no válido.', 'shotfest-votaciones' ) ];
        }

        // El periodo abierto que contiene este spot
        $periodo = $this->periodo_service->get_periodo_abierto_para_spot( $spot_id );
        if ( ! $periodo ) {
            return [ 'ok' => false, 'mensaje' => __( 'No hay ningún periodo de votación abierto para este spot.', 'shotfest-votaciones' ) ];
        }

        // El spot debe estar en estado disponible_votacion
        $estado_spot = get_post_meta( $spot_id, '_sf_spot_estado', true );
        if ( 'disponible_votacion' !== $estado_spot ) {
            return [ 'ok' => false, 'mensaje' => __( 'Este spot no está disponible para votación.', 'shotfest-votaciones' ) ];
        }

        $ip_hash = hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' );

        try {
            $insertado = $this->repo->insertar( $usuario_id, $spot_id, $periodo->ID, $valor, $ip_hash );
        } catch ( \RuntimeException $e ) {
            return [ 'ok' => false, 'mensaje' => __( 'Error interno al registrar el voto.', 'shotfest-votaciones' ) ];
        }

        if ( ! $insertado ) {
            return [ 'ok' => false, 'mensaje' => __( 'Ya has emitido tu voto para este spot.', 'shotfest-votaciones' ) ];
        }

        do_action( 'shotfest_voto_emitido', $usuario_id, $spot_id, $periodo->ID, $valor );

        return [ 'ok' => true, 'mensaje' => __( 'Voto registrado correctamente.', 'shotfest-votaciones' ) ];
    }
}
