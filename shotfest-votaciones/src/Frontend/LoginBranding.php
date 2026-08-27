<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Frontend;

use ShotfestVotaciones\Roles\JuradoRole;

/**
 * Viste `wp-login.php` con la identidad del festival y devuelve al jurado a la votación.
 *
 * El enlace del email de bienvenida lleva a la pantalla de establecer contraseña de
 * WordPress: fondo gris, logo de WordPress y ninguna referencia a ShotFest. A alguien
 * que acaba de recibir un correo para «entrar como jurado» eso le parece un phishing, y
 * con razón. Se marca con el logo y los colores del sitio, y al terminar se le lleva a
 * la hoja de votación en vez de al perfil de WordPress.
 */
class LoginBranding {

    public function register(): void {
        add_action( 'login_enqueue_scripts', [ $this, 'estilos' ] );
        add_filter( 'login_headerurl', [ $this, 'url_del_logo' ] );
        add_filter( 'login_headertext', [ $this, 'texto_del_logo' ] );
        add_filter( 'login_redirect', [ $this, 'destino_tras_entrar' ], 10, 3 );
        add_filter( 'login_message', [ $this, 'mensaje' ] );
    }

    public function estilos(): void {
        wp_enqueue_style(
            'sf-login',
            SF_PLUGIN_URL . 'assets/css/login.css',
            [],
            SF_VERSION
        );

        $icono = get_site_icon_url( 192 );
        if ( $icono ) {
            wp_add_inline_style(
                'sf-login',
                '#login h1 a{background-image:url(' . esc_url_raw( $icono ) . ')}'
            );
        }
    }

    public function url_del_logo(): string {
        return home_url( '/' );
    }

    public function texto_del_logo(): string {
        return get_bloginfo( 'name' );
    }

    /** Un mensaje que explica de qué va esto, para que nadie dude de si el correo era legítimo. */
    public function mensaje( string $mensaje ): string {
        $accion = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

        if ( 'rp' === $accion || 'resetpass' === $accion ) {
            return $mensaje . '<p class="message sf-login-intro">'
                . esc_html__( 'Elige una contraseña para acceder a la zona de jurado de ShotFest.', 'shotfest-votaciones' )
                . '</p>';
        }

        return $mensaje;
    }

    /**
     * Tras entrar, al jurado se le lleva a la votación, no al escritorio ni al perfil.
     * A cualquier otro rol se le respeta el destino que WordPress hubiera elegido.
     *
     * @param string           $destino  Destino calculado por WordPress.
     * @param string           $pedido   Destino solicitado en la petición.
     * @param \WP_User|\WP_Error $usuario Usuario autenticado, o error.
     */
    public function destino_tras_entrar( $destino, $pedido, $usuario ) {
        if ( ! $usuario instanceof \WP_User ) {
            return $destino;
        }

        if ( ! in_array( JuradoRole::ROLE_SLUG, (array) $usuario->roles, true ) ) {
            return $destino;
        }

        return PaginaVotacion::url();
    }
}
