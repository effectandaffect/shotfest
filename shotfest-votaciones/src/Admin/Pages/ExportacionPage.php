<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Domain\PeriodoService;

class ExportacionPage {

    /**
     * Neutraliza la inyección de fórmulas en CSV: Excel y LibreOffice interpretan como
     * fórmula cualquier celda que empiece por =, +, - o @, así que un título de spot
     * como «=HYPERLINK(...)» se ejecutaría al abrir el fichero. Se prefija un apóstrofo,
     * que los dos programas tratan como «esto es texto».
     */
    private static function csv_safe( $valor ): string {
        $valor = (string) $valor;

        if ( '' !== $valor && str_contains( "=+-@\t\r", $valor[0] ) ) {
            return "'" . $valor;
        }

        return $valor;
    }

    /** Escribe una fila del CSV con todas las celdas neutralizadas */
    private static function fputcsv_safe( $handle, array $fila ): void {
        fputcsv( $handle, array_map( [ self::class, 'csv_safe' ], $fila ), ';' );
    }

    /** Resuelve el año de la edición a la que pertenece un periodo, o '' si no tiene edición asignada */
    private static function resolve_edicion_anio( int $periodo_id ): string {
        $edicion_id = get_post_meta( $periodo_id, '_sf_periodo_edicion_id', true );
        return $edicion_id ? (string) get_post_meta( $edicion_id, '_sf_edicion_anio', true ) : '';
    }

    public static function register_actions(): void {
        add_action( 'admin_post_sf_export_clasificacion', [ self::class, 'export_clasificacion' ] );
        add_action( 'admin_post_sf_export_votos', [ self::class, 'export_votos' ] );
    }

    public static function export_clasificacion(): void {
        if ( ! current_user_can( 'sf_exportar_resultados' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }
        if ( ! isset( $_POST['sf_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sf_export_nonce'] ) ), 'sf_export_clasificacion' ) ) {
            wp_die( esc_html__( 'Solicitud no válida.', 'shotfest-votaciones' ) );
        }

        $periodo_id = absint( $_POST['periodo_id'] ?? 0 );
        $edicion_id = absint( $_POST['edicion_id'] ?? 0 );
        if ( ! $periodo_id && ! $edicion_id ) {
            wp_die( esc_html__( 'Periodo o edición no válidos.', 'shotfest-votaciones' ) );
        }

        $periodo_service = new PeriodoService();
        $repo            = new VotoRepository();
        $clasificacion_service = new ClasificacionService( $repo );

        $periodos = $periodo_id
            ? array_filter( [ get_post( $periodo_id ) ] )
            : $periodo_service->get_periodos_de_edicion( $edicion_id );

        $anio     = $periodo_id
            ? ( self::resolve_edicion_anio( $periodo_id ) ?: 'sin-edicion' )
            : ( get_post_meta( $edicion_id, '_sf_edicion_anio', true ) ?: 'sin-edicion' );
        $filename = 'shotfest-clasificacion-' . $anio . ( $periodo_id ? '' : '-todos-periodos' ) . '.csv';

        self::send_csv_headers( $filename );

        $output = fopen( 'php://output', 'w' );
        fputs( $output, "\xEF\xBB\xBF" ); // BOM UTF-8 para Excel
        fputcsv( $output, [ 'Periodo', 'Categoría', 'Posición', 'Spot', 'Marca', 'Votos Sí', 'Votos No', 'Shortlist' ], ';' );

        foreach ( $periodos as $periodo ) {
            $spots         = $periodo_service->get_todos_spots_del_periodo( $periodo->ID );
            $clasificacion = $clasificacion_service->clasificacion_por_periodo( $periodo->ID, $spots );

            foreach ( $clasificacion as $cat_data ) {
                foreach ( $cat_data['spots'] as $entry ) {
                    self::fputcsv_safe( $output, [
                        $periodo->post_title,
                        $cat_data['categoria']->name,
                        $entry['posicion'],
                        $entry['post']->post_title,
                        get_post_meta( $entry['post']->ID, '_sf_spot_marca', true ),
                        $entry['si'],
                        $entry['no'],
                        $entry['shortlist'] ? 'Sí' : 'No',
                    ] );
                }
            }
        }

        fclose( $output );
        exit;
    }

    public static function export_votos(): void {
        if ( ! current_user_can( 'sf_exportar_resultados' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }
        if ( ! isset( $_POST['sf_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sf_export_nonce'] ) ), 'sf_export_votos' ) ) {
            wp_die( esc_html__( 'Solicitud no válida.', 'shotfest-votaciones' ) );
        }

        $periodo_id = absint( $_POST['periodo_id'] ?? 0 );
        $edicion_id = absint( $_POST['edicion_id'] ?? 0 );
        if ( ! $periodo_id && ! $edicion_id ) {
            wp_die( esc_html__( 'Periodo o edición no válidos.', 'shotfest-votaciones' ) );
        }

        $repo = new VotoRepository();

        $periodos = $periodo_id
            ? array_filter( [ get_post( $periodo_id ) ] )
            : ( new PeriodoService() )->get_periodos_de_edicion( $edicion_id );

        $anio     = $periodo_id
            ? ( self::resolve_edicion_anio( $periodo_id ) ?: 'sin-edicion' )
            : ( get_post_meta( $edicion_id, '_sf_edicion_anio', true ) ?: 'sin-edicion' );
        $filename = 'shotfest-votos-detallados-' . $anio . ( $periodo_id ? '' : '-todos-periodos' ) . '.csv';

        self::send_csv_headers( $filename );

        $output = fopen( 'php://output', 'w' );
        fputs( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, [ 'Periodo', 'Usuario', 'Email', 'Spot ID', 'Spot', 'Voto', 'Fecha' ], ';' );

        foreach ( $periodos as $periodo ) {
            $votos = $repo->exportar_periodo( $periodo->ID );
            foreach ( $votos as $voto ) {
                $spot_title = get_the_title( (int) $voto['spot_id'] );

                // El voto se conserva aunque el miembro del jurado se haya dado de baja,
                // para que el CSV cuadre con los recuentos de la clasificación.
                $eliminado = null === $voto['user_login'];
                $usuario   = $eliminado
                    ? sprintf(
                        /* translators: %d es el ID del usuario ya eliminado */
                        __( '(usuario eliminado #%d)', 'shotfest-votaciones' ),
                        (int) $voto['usuario_id']
                    )
                    : $voto['user_login'];

                self::fputcsv_safe( $output, [
                    $periodo->post_title,
                    $usuario,
                    $eliminado ? '' : $voto['user_email'],
                    $voto['spot_id'],
                    $spot_title,
                    1 === (int) $voto['valor'] ? 'Sí' : 'No',
                    $voto['fecha_voto'],
                ] );
            }
        }

        fclose( $output );
        exit;
    }

    private static function send_csv_headers( string $filename ): void {
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
    }
}
