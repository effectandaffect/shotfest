<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Frontend;

use ShotfestVotaciones\Domain\PeriodoService;

/**
 * Añade «Votación» al menú principal mientras haya un periodo abierto.
 *
 * El enlace se muestra a todo el mundo, no solo al jurado: quien no tenga sesión
 * aterriza en el formulario de acceso, que es justo lo que se quiere. Y desaparece solo
 * cuando el periodo se cierra, sin que nadie tenga que acordarse de editar el menú.
 */
class MenuVotacion {

    /** Localizaciones de menú donde se inyecta el enlace. bootscore-navbar es el menú principal del tema. */
    private const LOCALIZACIONES = [ 'main-menu', 'primary', 'menu-1' ];
    private const MENU_IDS       = [ 'bootscore-navbar' ];

    public function __construct(
        private readonly PeriodoService $periodo_service
    ) {}

    public function register(): void {
        add_filter( 'wp_nav_menu_items', [ $this, 'anadir_enlace' ], 10, 2 );
    }

    public function anadir_enlace( string $items, \stdClass $args ): string {
        if ( ! $this->es_menu_principal( $args ) ) {
            return $items;
        }

        if ( ! $this->periodo_service->get_periodo_abierto() ) {
            return $items;
        }

        $url = PaginaVotacion::url();

        // Sin página de votación, PaginaVotacion cae a la portada: mejor no poner nada
        // que mandar al jurado a un sitio donde no hay votación.
        if ( ! PaginaVotacion::page_id() ) {
            return $items;
        }

        $actual = untrailingslashit( $url ) === untrailingslashit( home_url( add_query_arg( [] ) ) );

        return $items . sprintf(
            '<li class="menu-item sf-menu-votacion%s"><a href="%s" class="nav-link">%s</a></li>',
            $actual ? ' current-menu-item' : '',
            esc_url( $url ),
            esc_html__( 'Votación', 'shotfest-votaciones' )
        );
    }

    private function es_menu_principal( \stdClass $args ): bool {
        $localizacion = (string) ( $args->theme_location ?? '' );
        $menu_id      = (string) ( $args->menu_id ?? '' );

        /** Permite ajustar dónde aparece el enlace si el tema cambia de localizaciones. */
        $localizaciones = (array) apply_filters( 'shotfest_menu_votacion_localizaciones', self::LOCALIZACIONES );

        return in_array( $localizacion, $localizaciones, true )
            || in_array( $menu_id, self::MENU_IDS, true );
    }
}
