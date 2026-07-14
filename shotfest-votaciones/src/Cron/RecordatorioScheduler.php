<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Cron;

use ShotfestVotaciones\PostTypes\PeriodoPostType;

class RecordatorioScheduler {

    const HOOK = 'shotfest_cron_recordatorio';

    public function register(): void {
        add_action( self::HOOK, [ $this, 'run' ] );

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 08:00:00' ), 'daily', self::HOOK );
        }
    }

    public function run(): void {
        // Buscar periodos abiertos con fecha de cierre en los próximos 3 días
        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_sf_periodo_estado',
                    'value' => 'abierto',
                ],
            ],
        ] );

        $ahora        = current_time( 'timestamp' );
        $tres_dias    = 3 * DAY_IN_SECONDS;

        foreach ( $periodos as $periodo ) {
            $fecha_fin = get_post_meta( $periodo->ID, '_sf_periodo_fecha_fin', true );
            if ( ! $fecha_fin ) {
                continue;
            }

            $ts_fin          = strtotime( $fecha_fin );
            $tiempo_restante = $ts_fin - $ahora;

            if ( $tiempo_restante > 0 && $tiempo_restante <= $tres_dias ) {
                $transient_key = 'sf_recordatorio_enviado_' . $periodo->ID;
                if ( get_transient( $transient_key ) ) {
                    continue;
                }
                do_action( 'shotfest_send_recordatorio', $periodo->ID );
                set_transient( $transient_key, 1, $tres_dias );
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
