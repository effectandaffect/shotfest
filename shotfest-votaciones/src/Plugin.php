<?php
declare(strict_types=1);

namespace ShotfestVotaciones;

use ShotfestVotaciones\PostTypes\SpotPostType;
use ShotfestVotaciones\PostTypes\PeriodoPostType;
use ShotfestVotaciones\PostTypes\EdicionPostType;
use ShotfestVotaciones\Taxonomies\CategoriaTaxonomy;
use ShotfestVotaciones\Admin\AdminMenu;
use ShotfestVotaciones\Admin\Metaboxes\SpotMetabox;
use ShotfestVotaciones\Admin\Metaboxes\PeriodoMetabox;
use ShotfestVotaciones\Admin\Metaboxes\EdicionMetabox;
use ShotfestVotaciones\Roles\CapabilitiesManager;
use ShotfestVotaciones\Roles\JuradoAccessRestriction;
use ShotfestVotaciones\Roles\JuradoRole;
use ShotfestVotaciones\Frontend\Shortcode;
use ShotfestVotaciones\Frontend\VotoAjaxController;
use ShotfestVotaciones\Frontend\MenuVotacion;
use ShotfestVotaciones\Frontend\LoginBranding;
use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Domain\VotoService;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Notifications\NotificationEvents;
use ShotfestVotaciones\Notifications\EmailNotifier;
use ShotfestVotaciones\Cron\RecordatorioScheduler;
use ShotfestVotaciones\Admin\Pages\ExportacionPage;

class Plugin {

    public function run(): void {
        // Tipos de contenido y taxonomías
        ( new SpotPostType() )->register();
        ( new PeriodoPostType() )->register();
        ( new EdicionPostType() )->register();
        ( new CategoriaTaxonomy() )->register();

        // Metaboxes de admin
        ( new SpotMetabox() )->register();
        ( new PeriodoMetabox() )->register();
        ( new EdicionMetabox() )->register();

        // Sincroniza capabilities nuevas sin depender de reactivar el plugin
        add_action( 'admin_init', [ CapabilitiesManager::class, 'maybe_sync' ] );
        add_action( 'admin_init', [ JuradoRole::class, 'maybe_sync' ] );

        // El jurado nunca entra en wp-admin
        ( new JuradoAccessRestriction() )->register();

        // Servicios y repositorios
        $voto_repo          = new VotoRepository();
        $periodo_service    = new PeriodoService();
        $voto_service       = new VotoService( $voto_repo, $periodo_service );
        $clasificacion      = new ClasificacionService( $voto_repo );

        // Menú de administración
        ( new AdminMenu( $periodo_service, $clasificacion, $voto_repo ) )->register();

        // Front-end: shortcode + AJAX
        ( new Shortcode( $periodo_service, $clasificacion, $voto_repo ) )->register();
        ( new VotoAjaxController( $voto_service ) )->register();

        // «Votación» en el menú mientras haya periodo abierto, y wp-login con la
        // identidad del festival en vez de la de WordPress
        ( new MenuVotacion( $periodo_service ) )->register();
        ( new LoginBranding() )->register();

        // Notificaciones y cron
        $email_notifier = new EmailNotifier( $periodo_service, $voto_repo );
        ( new NotificationEvents( $email_notifier ) )->register();
        ( new RecordatorioScheduler() )->register();

        // Exportación CSV (admin-post.php)
        ExportacionPage::register_actions();

        // Cargar estilos/scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Registra los assets del front sin encolarlos. Los encola el propio shortcode al
     * renderizarse (ver Plugin::enqueue_frontend()), en vez de cargarse en todas las
     * páginas del sitio. Se registran aquí y no dentro del shortcode porque
     * `wp_enqueue_scripts` es el hook donde WordPress espera las declaraciones.
     */
    public function register_frontend_assets(): void {
        wp_register_style(
            'sf-frontend',
            SF_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            SF_VERSION
        );

        wp_register_script(
            'sf-votacion',
            SF_PLUGIN_URL . 'assets/js/votacion.js',
            [],
            SF_VERSION,
            true
        );

        if ( is_user_logged_in() ) {
            wp_localize_script( 'sf-votacion', 'sfAjax', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sf_emitir_voto' ),
            ] );
        }

        // Si la página ya contiene el shortcode, se encola aquí para que la hoja salga en
        // <head>. Encolarla solo desde dentro del shortcode la dejaba en el pie —después
        // de que el navegador ya hubiera pintado— y la zona de votación aparecía un
        // instante sin estilos. La llamada desde el shortcode se mantiene como red para
        // los casos que este chequeo no ve (widgets, bloques reutilizables).
        if ( $this->contenido_actual_tiene_shortcode() ) {
            self::enqueue_frontend();
        }
    }

    private function contenido_actual_tiene_shortcode(): bool {
        if ( ! is_singular() ) {
            return false;
        }

        $post = get_queried_object();

        return $post instanceof \WP_Post
            && has_shortcode( (string) $post->post_content, 'shotfest_votaciones' );
    }

    /**
     * Encola los assets ya registrados. Se llama desde el shortcode, que es el único
     * punto que sabe con certeza que hacen falta: mirar `has_shortcode()` sobre el
     * contenido del post daría falsos negativos si se inserta desde un bloque
     * reutilizable o una plantilla del theme. El script va al footer, así que encolarlo
     * durante el renderizado del contenido sigue llegando a tiempo.
     */
    public static function enqueue_frontend(): void {
        wp_enqueue_style( 'sf-frontend' );

        if ( is_user_logged_in() ) {
            wp_enqueue_script( 'sf-votacion' );
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
