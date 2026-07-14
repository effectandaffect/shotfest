<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin;

use ShotfestVotaciones\Admin\Pages\UsuariosJuradoPage;
use ShotfestVotaciones\Admin\Pages\ResultadosPage;
use ShotfestVotaciones\Admin\Pages\ExportacionPage;
use ShotfestVotaciones\Admin\Pages\EmailTextosPage;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Domain\ClasificacionService;

class AdminMenu {

    public function __construct(
        private readonly PeriodoService       $periodo_service,
        private readonly ClasificacionService $clasificacion_service
    ) {}

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menus' ] );
    }

    public function add_menus(): void {
        add_menu_page(
            __( 'ShotFest Votaciones', 'shotfest-votaciones' ),
            __( 'ShotFest', 'shotfest-votaciones' ),
            'sf_ver_resultados',
            'shotfest-votaciones',
            [ $this, 'render_dashboard' ],
            'dashicons-awards',
            30
        );

        add_submenu_page(
            'shotfest-votaciones',
            __( 'Jurado', 'shotfest-votaciones' ),
            __( 'Jurado', 'shotfest-votaciones' ),
            'sf_gestionar_jurado',
            'sf-jurado',
            [ new UsuariosJuradoPage(), 'render' ]
        );

        add_submenu_page(
            'shotfest-votaciones',
            __( 'Resultados', 'shotfest-votaciones' ),
            __( 'Resultados', 'shotfest-votaciones' ),
            'sf_ver_resultados',
            'sf-resultados',
            [ new ResultadosPage( $this->periodo_service, $this->clasificacion_service ), 'render' ]
        );

        add_submenu_page(
            'shotfest-votaciones',
            __( 'Exportar CSV', 'shotfest-votaciones' ),
            __( 'Exportar CSV', 'shotfest-votaciones' ),
            'sf_exportar_resultados',
            'sf-exportar',
            [ new ExportacionPage(), 'render' ]
        );

        add_submenu_page(
            'shotfest-votaciones',
            __( 'Textos de email', 'shotfest-votaciones' ),
            __( 'Textos de email', 'shotfest-votaciones' ),
            'sf_gestionar_emails',
            'sf-emails',
            [ new EmailTextosPage(), 'render' ]
        );
    }

    public function render_dashboard(): void {
        echo '<div class="wrap"><h1>' . esc_html__( 'ShotFest Votaciones', 'shotfest-votaciones' ) . '</h1>';
        echo '<p>' . esc_html__( 'Panel de control del módulo de votaciones.', 'shotfest-votaciones' ) . '</p>';
        echo '</div>';
    }
}
