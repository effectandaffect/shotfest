<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Domain;

use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\PostTypes\SpotPostType;

class PeriodoService {

    /** Estados de post considerados válidos al listar Periodos/Ediciones para selección — un CPT guardado como borrador debe seguir siendo utilizable */
    const POST_STATUSES = [ 'publish', 'draft', 'pending', 'private' ];

    /** Formato canónico en el que se persisten las fechas de periodo en post meta */
    const FORMATO_FECHA = 'Y-m-d H:i:s';

    /**
     * Normaliza una fecha de formulario al formato canónico `Y-m-d H:i:s`.
     *
     * Los `<input type="datetime-local">` envían `2026-08-13T08:00` (con «T» y sin
     * segundos). Guardar eso tal cual hacía que las comparaciones contra
     * `current_time('mysql')` se resolvieran byte a byte: el espacio (0x20) siempre
     * es menor que la «T» (0x54), así que un periodo se consideraba «aún no iniciado»
     * durante todo su primer día. Se aceptan ambas formas para tolerar datos ya
     * guardados con el formato antiguo.
     *
     * @return string Fecha normalizada, o '' si el valor no es una fecha interpretable.
     */
    public static function normalizar_fecha( string $valor ): string {
        $valor = trim( str_replace( 'T', ' ', $valor ) );
        if ( '' === $valor ) {
            return '';
        }

        // `datetime-local` omite los segundos salvo que se le pida step="1"
        if ( 16 === strlen( $valor ) ) {
            $valor .= ':00';
        }

        $fecha = \DateTimeImmutable::createFromFormat( self::FORMATO_FECHA, $valor, wp_timezone() );

        return ( $fecha && $fecha->format( self::FORMATO_FECHA ) === $valor ) ? $valor : '';
    }

    /**
     * Convierte una fecha guardada en timestamp Unix real, interpretándola en la zona
     * horaria configurada en WordPress (las fechas del metabox son hora local, no UTC).
     */
    public static function fecha_a_timestamp( string $valor ): ?int {
        $normalizada = self::normalizar_fecha( $valor );
        if ( '' === $normalizada ) {
            return null;
        }

        $fecha = \DateTimeImmutable::createFromFormat( self::FORMATO_FECHA, $normalizada, wp_timezone() );

        return $fecha ? $fecha->getTimestamp() : null;
    }

    /** Devuelve la fecha en el formato que espera un `<input type="datetime-local">`. */
    public static function fecha_para_input( string $valor ): string {
        $normalizada = self::normalizar_fecha( $valor );

        return '' === $normalizada ? '' : str_replace( ' ', 'T', substr( $normalizada, 0, 16 ) );
    }

    /**
     * ¿Estamos ahora mismo dentro de la ventana de fechas del periodo?
     *
     * Cada límite se evalúa por separado a propósito: antes se exigía que estuvieran
     * las dos fechas para comprobar cualquiera de ellas, así que un periodo con solo
     * fecha de cierre no se cerraba nunca.
     */
    public function esta_vigente( int $periodo_id ): bool {
        $ahora  = time();
        $inicio = self::fecha_a_timestamp( (string) get_post_meta( $periodo_id, '_sf_periodo_fecha_inicio', true ) );
        $fin    = self::fecha_a_timestamp( (string) get_post_meta( $periodo_id, '_sf_periodo_fecha_fin', true ) );

        if ( null !== $inicio && $ahora < $inicio ) {
            return false;
        }

        if ( null !== $fin && $ahora > $fin ) {
            return false;
        }

        return true;
    }

    /** Devuelve el periodo abierto activo (el primero que encuentre), o null */
    public function get_periodo_abierto(): ?\WP_Post {
        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'post_status'    => self::POST_STATUSES,
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

        return $this->esta_vigente( $periodo->ID ) ? $periodo : null;
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

        return $this->esta_vigente( $periodo_id ) ? $periodo : null;
    }

    /** Comprueba si hay algún periodo con resultados publicados */
    public function get_periodo_con_resultados(): ?\WP_Post {
        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'post_status'    => self::POST_STATUSES,
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

    /** Devuelve los periodos pertenecientes a una edición dada */
    public function get_periodos_de_edicion( int $edicion_id ): array {
        return get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'post_status'    => self::POST_STATUSES,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_sf_periodo_edicion_id',
                    'value' => $edicion_id,
                ],
            ],
        ] );
    }
}
