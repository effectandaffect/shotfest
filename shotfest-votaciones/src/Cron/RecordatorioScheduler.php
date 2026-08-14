<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Cron;

use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\Domain\PeriodoService;

class RecordatorioScheduler {

    const HOOK = 'shotfest_cron_recordatorio';

    public function register(): void {
        add_action( self::HOOK, [ $this, 'run' ] );

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 08:00:00' ), 'daily', self::HOOK );
        }
    }

    public function run(): void {
        // Buscar periodos abiertos con fecha de cierre en los próximos 7 días
        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'post_status'    => PeriodoService::POST_STATUSES,
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_sf_periodo_estado',
                    'value' => 'abierto',
                ],
            ],
        ] );

        // time() y PeriodoService::fecha_a_timestamp() están los dos en epoch real. Antes se
        // mezclaba current_time('timestamp') (hora local) con strtotime() (UTC, porque WP fija
        // la zona de PHP a UTC), un desfase igual al offset del sitio: 2 h en verano.
        $ahora       = time();
        $siete_dias  = 7 * DAY_IN_SECONDS;

        foreach ( $periodos as $periodo ) {
            $ts_fin = PeriodoService::fecha_a_timestamp(
                (string) get_post_meta( $periodo->ID, '_sf_periodo_fecha_fin', true )
            );
            if ( null === $ts_fin ) {
                continue;
            }

            $tiempo_restante = $ts_fin - $ahora;

            if ( $tiempo_restante > 0 && $tiempo_restante <= $siete_dias ) {
                $transient_key = 'sf_recordatorio_enviado_' . $periodo->ID;
                if ( get_transient( $transient_key ) ) {
                    continue;
                }
                do_action( 'shotfest_send_recordatorio', $periodo->ID );
                set_transient( $transient_key, 1, $siete_dias );
            }
        }
    }

    public static function clear_scheduled_events(): void {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }
}
