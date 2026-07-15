<?php
declare(strict_types=1);

namespace ShotfestVotaciones;

use ShotfestVotaciones\PostTypes\SpotPostType;
use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\Taxonomies\CategoriaTaxonomy;
use ShotfestVotaciones\Admin\AdminMenu;
use ShotfestVotaciones\Admin\Metaboxes\SpotMetabox;
use ShotfestVotaciones\Admin\Metaboxes\PeriodoMetabox;
use ShotfestVotaciones\Frontend\Shortcode;
use ShotfestVotaciones\Frontend\VotoAjaxController;
use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Domain\VotoService;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Notifications\NotificationEvents;
use ShotfestVotaciones\Cron\RecordatorioScheduler;
use ShotfestVotaciones\Admin\Pages\ExportacionPage;

class Plugin {

    public function run(): void {
        // Tipos de contenido y taxonomías
        ( new SpotPostType() )->register();
        ( new PeriodoPostType() )->register();
        ( new CategoriaTaxonomy() )->register();

        // Metaboxes de admin
        ( new SpotMetabox() )->register();
        ( new PeriodoMetabox() )->register();

        // Servicios y repositorios
        $voto_repo          = new VotoRepository();
        $periodo_service    = new PeriodoService();
        $voto_service       = new VotoService( $voto_repo, $periodo_service );
        $clasificacion      = new ClasificacionService( $voto_repo );

        // Menú de administración
        ( new AdminMenu( $periodo_service, $clasificacion ) )->register();

        // Front-end: shortcode + AJAX
        ( new Shortcode( $periodo_service, $clasificacion, $voto_repo ) )->register();
        ( new VotoAjaxController( $voto_service ) )->register();

        // Notificaciones y cron
        ( new NotificationEvents() )->register();
        ( new RecordatorioScheduler() )->register();

        // Exportación CSV (admin-post.php)
        ExportacionPage::register_actions();

        // Cargar estilos/scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'sf-frontend',
            SF_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            SF_VERSION
        );

        if ( is_user_logged_in() ) {
            wp_enqueue_script(
                'sf-votacion',
                SF_PLUGIN_URL . 'assets/js/votacion.js',
                [],
                SF_VERSION,
                true
            );
            wp_localize_script( 'sf-votacion', 'sfAjax', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sf_emitir_voto' ),
            ] );
        }
    }

    public function enqueue_admin_assets( string $hook ): void {
        wp_enqueue_style(
            'sf-admin',
            SF_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SF_VERSION
        );
    }
}
