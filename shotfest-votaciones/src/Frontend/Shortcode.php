<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Frontend;

use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Data\VotoRepository;

class Shortcode {

    public function __construct(
        private readonly PeriodoService       $periodo_service,
        private readonly ClasificacionService $clasificacion_service,
        private readonly VotoRepository       $voto_repo
    ) {}

    public function register(): void {
        add_shortcode( 'shotfest_votaciones', [ $this, 'render' ] );
    }

    public function render( array $atts ): string {
        // Pantalla de login si no está autenticado
        if ( ! is_user_logged_in() ) {
            return $this->render_login();
        }

        // Solo el jurado accede
        if ( ! current_user_can( 'sf_ver_spots' ) ) {
            return '<p class="sf-aviso">' . esc_html__( 'No tienes acceso a esta sección.', 'shotfest-votaciones' ) . '</p>';
        }

        // Detalle de un spot concreto
        $spot_id = isset( $_GET['sf_spot'] ) ? absint( $_GET['sf_spot'] ) : 0;
        if ( $spot_id ) {
            return $this->render_detalle( $spot_id );
        }

        // Resultados publicados
        $periodo_resultados = $this->periodo_service->get_periodo_con_resultados();
        $periodo_abierto    = $this->periodo_service->get_periodo_abierto();

        if ( $periodo_abierto ) {
            return $this->render_home_jurado( $periodo_abierto );
        }

        if ( $periodo_resultados ) {
            return $this->render_resultados( $periodo_resultados );
        }

        return '<p class="sf-aviso">' . esc_html__( 'No hay ningún periodo de votación activo en este momento.', 'shotfest-votaciones' ) . '</p>';
    }

    private function render_login(): string {
        return TemplateLoader::load( 'login.php' );
    }

    private function render_home_jurado( \WP_Post $periodo ): string {
        $spots          = $this->periodo_service->get_spots_del_periodo( $periodo->ID );
        $votos_emitidos = [];
        foreach ( $this->voto_repo->votos_usuario( get_current_user_id(), $periodo->ID ) as $v ) {
            $votos_emitidos[ (int) $v['spot_id'] ] = (int) $v['valor'];
        }

        return TemplateLoader::load( 'home-jurado.php', [
            'periodo'        => $periodo,
            'spots'          => $spots,
            'votos_emitidos' => $votos_emitidos,
        ] );
    }

    private function render_detalle( int $spot_id ): string {
        $spot = get_post( $spot_id );
        if ( ! $spot || 'sf_spot' !== $spot->post_type ) {
            return '<p class="sf-aviso">' . esc_html__( 'Spot no encontrado.', 'shotfest-votaciones' ) . '</p>';
        }

        $periodo_abierto = $this->periodo_service->get_periodo_abierto_para_spot( $spot_id );
        $periodo_id      = $periodo_abierto ? $periodo_abierto->ID : 0;
        $ya_voto         = $this->voto_repo->ya_voto( get_current_user_id(), $spot_id, $periodo_id );

        return TemplateLoader::load( 'spot-detalle.php', [
            'spot'           => $spot,
            'periodo_abierto' => $periodo_abierto,
            'ya_voto'        => $ya_voto,
        ] );
    }

    private function render_resultados( \WP_Post $periodo ): string {
        if ( ! current_user_can( 'sf_ver_resultados_publicados' ) ) {
            return '<p class="sf-aviso">' . esc_html__( 'No tienes permiso para ver los resultados.', 'shotfest-votaciones' ) . '</p>';
        }

        $spots         = $this->periodo_service->get_todos_spots_del_periodo( $periodo->ID );
        $clasificacion = $this->clasificacion_service->clasificacion_por_periodo( $periodo->ID, $spots );

        return TemplateLoader::load( 'resultados-publicados.php', [
            'periodo'       => $periodo,
            'clasificacion' => $clasificacion,
        ] );
    }
}
