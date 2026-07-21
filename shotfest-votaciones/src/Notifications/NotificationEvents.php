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

    public function on_voto_emitido( int $usuario_id, int $spot_id, int $periodo_id, int $valor ): void {
        $total_jurado   = count( get_users( [ 'role' => 'jurado_shotfest' ] ) );
        $repo           = new VotoRepository();
        $jurado_que_voto = $repo->total_jurado_que_voto( $periodo_id );

        // Aviso al admin cuando todos han votado al menos un spot
        if ( $total_jurado > 0 && $jurado_que_voto >= $total_jurado ) {
            $this->notifier->enviar_aviso_jurado_completo( $periodo_id );
        }
    }

    public function on_recordatorio( int $periodo_id ): void {
        $this->notifier->enviar_recordatorio( $periodo_id );
    }
}
