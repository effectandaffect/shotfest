<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Domain;

use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\PostTypes\SpotPostType;

class PeriodoService {

    /** Devuelve el periodo abierto activo (el primero que encuentre), o null */
    public function get_periodo_abierto(): ?\WP_Post {
        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_sf_periodo_estado',
                    'value' => 'abierto',
                ],
            ],
        ] );

        if ( empty( $periodos ) ) {
            return null;
        }

        $periodo = $periodos[0];
        $ahora   = current_time( 'mysql' );
        $inicio  = get_post_meta( $periodo->ID, '_sf_periodo_fecha_inicio', true );
        $fin     = get_post_meta( $periodo->ID, '_sf_periodo_fecha_fin', true );

        if ( $inicio && $fin && ( $ahora < $inicio || $ahora > $fin ) ) {
            return null;
        }

        return $periodo;
    }

    /** Devuelve el periodo abierto que contiene el spot dado */
    public function get_periodo_abierto_para_spot( int $spot_id ): ?\WP_Post {
        $periodo_id = (int) get_post_meta( $spot_id, '_sf_spot_periodo_id', true );
        if ( ! $periodo_id ) {
            return null;
        }

        $periodo = get_post( $periodo_id );
        if ( ! $periodo || 'sf_periodo' !== $periodo->post_type ) {
            return null;
        }

        $estado = get_post_meta( $periodo_id, '_sf_periodo_estado', true );
        if ( 'abierto' !== $estado ) {
            return null;
        }

        $ahora  = current_time( 'mysql' );
        $inicio = get_post_meta( $periodo_id, '_sf_periodo_fecha_inicio', true );
        $fin    = get_post_meta( $periodo_id, '_sf_periodo_fecha_fin', true );

        if ( $inicio && $fin && ( $ahora < $inicio || $ahora > $fin ) ) {
            return null;
        }

        return $periodo;
    }

    /** Comprueba si hay algún periodo con resultados publicados */
    public function get_periodo_con_resultados(): ?\WP_Post {
        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_sf_periodo_resultados_publicados',
                    'value' => '1',
                ],
            ],
        ] );

        return $periodos[0] ?? null;
    }

    /** Devuelve los spots disponibles para votación en un periodo dado */
    public function get_spots_del_periodo( int $periodo_id ): array {
        return get_posts( [
            'post_type'      => SpotPostType::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_sf_spot_periodo_id',
                    'value' => $periodo_id,
                ],
                [
                    'key'   => '_sf_spot_estado',
                    'value' => 'disponible_votacion',
                ],
            ],
        ] );
    }

    /** Todos los spots de un periodo (independientemente del estado) */
    public function get_todos_spots_del_periodo( int $periodo_id ): array {
        return get_posts( [
            'post_type'      => SpotPostType::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'   => '_sf_spot_periodo_id',
                    'value' => $periodo_id,
                ],
            ],
        ] );
    }
}
