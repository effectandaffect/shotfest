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

        // El jurado solo vota en los periodos de las ediciones que tiene asignadas
        if ( ! $this->pertenece_a_la_edicion( $usuario_id, (int) $periodo->ID ) ) {
            return [ 'ok' => false, 'mensaje' => __( 'Este periodo de votación no corresponde a tu edición del jurado.', 'shotfest-votaciones' ) ];
        }

        // wp_hash() incorpora los salts del sitio. Un sha256 pelado de una IPv4 no es
        // anonimización: el espacio entero son 2^32 valores y revertirlo por fuerza bruta
        // es cuestión de segundos, así que seguía siendo un dato personal en claro.
        $ip_hash = $this->hash_ip();

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

    /**
     * ¿El usuario pertenece a la edición de este periodo?
     *
     * El meta `_sf_jurado_edicion_id` solo se usaba para decidir a quién se le manda el
     * email, así que a efectos de voto la asignación por edición era decorativa: un
     * miembro del jurado de una edición anterior que siguiera dado de alta podía votar
     * en el periodo en curso.
     *
     * Se mantienen las dos vías de compatibilidad que ya aplicaba EmailNotifier: un
     * periodo sin edición asignada (datos previos a la jerarquía Edición→Periodo) y un
     * miembro sin ediciones asignadas no quedan bloqueados.
     */
    private function pertenece_a_la_edicion( int $usuario_id, int $periodo_id ): bool {
        $edicion_periodo = (int) get_post_meta( $periodo_id, '_sf_periodo_edicion_id', true );
        if ( ! $edicion_periodo ) {
            return true;
        }

        $ediciones_usuario = array_map( 'intval', get_user_meta( $usuario_id, '_sf_jurado_edicion_id', false ) );
        if ( empty( $ediciones_usuario ) ) {
            return true;
        }

        return in_array( $edicion_periodo, $ediciones_usuario, true );
    }

    /** Hash con salt de la IP del votante, para trazabilidad mínima sin guardar la IP */
    private function hash_ip(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        return '' === $ip ? '' : wp_hash( $ip );
    }
}
