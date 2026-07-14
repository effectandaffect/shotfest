<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Domain\PeriodoService;

class ExportacionPage {

    public function render(): void {
        if ( ! current_user_can( 'sf_exportar_resultados' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }

        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Exportar resultados', 'shotfest-votaciones' ); ?></h1>

            <h2><?php esc_html_e( 'Exportar clasificación (resumen)', 'shotfest-votaciones' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sf_export_clasificacion', 'sf_export_nonce' ); ?>
                <input type="hidden" name="action" value="sf_export_clasificacion">
                <select name="periodo_id" required>
                    <option value=""><?php esc_html_e( '— Selecciona un periodo —', 'shotfest-votaciones' ); ?></option>
                    <?php foreach ( $periodos as $p ) : ?>
                        <option value="<?php echo esc_attr( $p->ID ); ?>">
                            <?php echo esc_html( $p->post_title ); ?> (<?php echo esc_html( get_post_meta( $p->ID, '_sf_periodo_edicion_year', true ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Descargar CSV clasificación', 'shotfest-votaciones' ), 'primary', 'submit', false ); ?>
            </form>

            <h2><?php esc_html_e( 'Exportar votos detallados', 'shotfest-votaciones' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sf_export_votos', 'sf_export_nonce' ); ?>
                <input type="hidden" name="action" value="sf_export_votos">
                <select name="periodo_id" required>
                    <option value=""><?php esc_html_e( '— Selecciona un periodo —', 'shotfest-votaciones' ); ?></option>
                    <?php foreach ( $periodos as $p ) : ?>
                        <option value="<?php echo esc_attr( $p->ID ); ?>">
                            <?php echo esc_html( $p->post_title ); ?> (<?php echo esc_html( get_post_meta( $p->ID, '_sf_periodo_edicion_year', true ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Descargar CSV votos detallados', 'shotfest-votaciones' ), 'primary', 'submit', false ); ?>
            </form>
        </div>
        <?php
    }

    public static function register_actions(): void {
        add_action( 'admin_post_sf_export_clasificacion', [ self::class, 'export_clasificacion' ] );
        add_action( 'admin_post_sf_export_votos', [ self::class, 'export_votos' ] );
    }

    public static function export_clasificacion(): void {
        if ( ! current_user_can( 'sf_exportar_resultados' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }
        if ( ! isset( $_POST['sf_export_nonce'] ) || ! wp_verify_nonce( $_POST['sf_export_nonce'], 'sf_export_clasificacion' ) ) {
            wp_die( esc_html__( 'Solicitud no válida.', 'shotfest-votaciones' ) );
        }

        $periodo_id = absint( $_POST['periodo_id'] ?? 0 );
        if ( ! $periodo_id ) {
            wp_die( esc_html__( 'Periodo no válido.', 'shotfest-votaciones' ) );
        }

        $periodo_service = new PeriodoService();
        $spots           = $periodo_service->get_todos_spots_del_periodo( $periodo_id );
        $repo            = new VotoRepository();
        $clasificacion   = ( new ClasificacionService( $repo ) )->clasificacion_por_periodo( $periodo_id, $spots );

        $periodo    = get_post( $periodo_id );
        $edicion    = get_post_meta( $periodo_id, '_sf_periodo_edicion_year', true );
        $filename   = 'shotfest-clasificacion-' . $edicion . '.csv';

        self::send_csv_headers( $filename );

        $output = fopen( 'php://output', 'w' );
        fputs( $output, "\xEF\xBB\xBF" ); // BOM UTF-8 para Excel
        fputcsv( $output, [ 'Categoría', 'Posición', 'Spot', 'Marca', 'Votos Sí', 'Votos No', 'Shortlist' ], ';' );

        foreach ( $clasificacion as $cat_data ) {
            foreach ( $cat_data['spots'] as $entry ) {
                fputcsv( $output, [
                    $cat_data['categoria']->name,
                    $entry['posicion'],
                    $entry['post']->post_title,
                    get_post_meta( $entry['post']->ID, '_sf_spot_marca', true ),
                    $entry['si'],
                    $entry['no'],
                    $entry['shortlist'] ? 'Sí' : 'No',
                ], ';' );
            }
        }

        fclose( $output );
        exit;
    }

    public static function export_votos(): void {
        if ( ! current_user_can( 'sf_exportar_resultados' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }
        if ( ! isset( $_POST['sf_export_nonce'] ) || ! wp_verify_nonce( $_POST['sf_export_nonce'], 'sf_export_votos' ) ) {
            wp_die( esc_html__( 'Solicitud no válida.', 'shotfest-votaciones' ) );
        }

        $periodo_id = absint( $_POST['periodo_id'] ?? 0 );
        if ( ! $periodo_id ) {
            wp_die( esc_html__( 'Periodo no válido.', 'shotfest-votaciones' ) );
        }

        $repo   = new VotoRepository();
        $votos  = $repo->exportar_periodo( $periodo_id );

        $edicion  = get_post_meta( $periodo_id, '_sf_periodo_edicion_year', true );
        $filename = 'shotfest-votos-detallados-' . $edicion . '.csv';

        self::send_csv_headers( $filename );

        $output = fopen( 'php://output', 'w' );
        fputs( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, [ 'Usuario', 'Email', 'Spot ID', 'Spot', 'Voto', 'Fecha' ], ';' );

        foreach ( $votos as $voto ) {
            $spot_title = get_the_title( (int) $voto['spot_id'] );
            fputcsv( $output, [
                $voto['user_login'],
                $voto['user_email'],
                $voto['spot_id'],
                $spot_title,
                '1' === $voto['valor'] ? 'Sí' : 'No',
                $voto['fecha_voto'],
            ], ';' );
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
