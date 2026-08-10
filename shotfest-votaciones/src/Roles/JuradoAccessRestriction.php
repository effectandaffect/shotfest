<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Roles;

/** El jurado nunca entra en wp-admin (ver CLAUDE.md): oculta la barra de admin y bloquea el acceso directo. */
class JuradoAccessRestriction {

    public function register(): void {
        add_filter( 'show_admin_bar', [ $this, 'ocultar_admin_bar' ] );
        add_action( 'admin_init', [ $this, 'bloquear_wp_admin' ] );
    }

    public function ocultar_admin_bar( bool $mostrar ): bool {
        if ( $this->es_jurado() ) {
            return false;
        }
        return $mostrar;
    }

    public function bloquear_wp_admin(): void {
        if ( wp_doing_ajax() || ! $this->es_jurado() ) {
            return;
        }

        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    private function es_jurado(): bool {
        $user = wp_get_current_user();
        return $user->exists() && in_array( JuradoRole::ROLE_SLUG, (array) $user->roles, true );
    }
}
