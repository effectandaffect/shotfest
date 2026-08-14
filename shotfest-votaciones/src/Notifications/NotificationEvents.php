<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Notifications;

use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Domain\PeriodoService;

class NotificationEvents {

    public function __construct(
        private readonly EmailNotifier $notifier
    ) {}

    public function register(): void {
        add_action( 'shotfest_jurado_alta',      [ $this, 'on_alta_jurado' ] );
        add_action( 'shotfest_periodo_abierto',  [ $this, 'on_periodo_abierto' ] );
        add_action( 'shotfest_voto_emitido',     [ $this, 'on_voto_emitido' ], 10, 4 );
        add_action( 'shotfest_send_recordatorio', [ $this, 'on_recordatorio' ] );
    }

    public function on_alta_jurado( int $user_id ): void {
        $this->notifier->enviar_bienvenida( $user_id );
    }

    public function on_periodo_abierto( int $periodo_id ): void {
        $this->notifier->enviar_apertura_periodo( $periodo_id );
    }

    /** Marca en el periodo que ya se avisó al admin, para no repetir el email con cada voto */
    const META_AVISO_ENVIADO = '_sf_periodo_aviso_completo_enviado';

    /**
     * Aviso al admin cuando todos los miembros del jurado han votado al menos un spot.
     *
     * Antes se comparaban dos poblaciones distintas: el total de jurado de *todas* las
     * ediciones contra los votantes de *este* periodo. En cuanto hubiera jurado en más
     * de una edición la condición no se cumplía nunca y el email no salía. Y cuando sí
     * se cumplía no había ninguna guarda, así que el aviso se reenviaba con cada voto
     * posterior: con 15 jurados y 30 spots, cientos de emails al administrador.
     */
    public function on_voto_emitido( int $usuario_id, int $spot_id, int $periodo_id, int $valor ): void {
        if ( get_post_meta( $periodo_id, self::META_AVISO_ENVIADO, true ) ) {
            return;
        }

        $jurado = $this->notifier->jurado_del_periodo( $periodo_id );
        if ( empty( $jurado ) ) {
            return;
        }

        // Se cruza con los votantes reales en vez de comparar totales: el recuento de
        // usuario_id distintos puede incluir miembros ya dados de baja, cuyos votos se
        // conservan en la tabla.
        $votantes = ( new VotoRepository() )->usuarios_que_votaron( $periodo_id );

        foreach ( $jurado as $miembro ) {
            if ( ! in_array( (int) $miembro->ID, $votantes, true ) ) {
                return;
            }
        }

        update_post_meta( $periodo_id, self::META_AVISO_ENVIADO, '1' );
        $this->notifier->enviar_aviso_jurado_completo( $periodo_id );
    }

    public function on_recordatorio( int $periodo_id ): void {
        $this->notifier->enviar_recordatorio( $periodo_id );
    }
}
