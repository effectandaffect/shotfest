<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Notifications;

use ShotfestVotaciones\Admin\Pages\EmailTextosPage;

class EmailNotifier {

    private function get_template( string $option_key ): string {
        return get_option( $option_key, EmailTextosPage::OPTIONS[ $option_key ]['default'] ?? '' );
    }

    private function render( string $template, array $vars ): string {
        $keys   = array_map( fn( $k ) => '{{' . $k . '}}', array_keys( $vars ) );
        return str_replace( $keys, array_values( $vars ), $template );
    }

    public function enviar_bienvenida( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $reset_key  = get_password_reset_key( $user );
        if ( is_wp_error( $reset_key ) ) {
            return;
        }
        $reset_url = network_site_url( "wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode( $user->user_login ) );

        $template = $this->get_template( 'sf_email_alta_jurado' );
        $cuerpo   = $this->render( $template, [
            'edicion'        => (string) date( 'Y' ),
            'link_password'  => $reset_url,
            'url_votaciones' => home_url( '/' ),
        ] );

        wp_mail(
            $user->user_email,
            __( 'Bienvenido/a al jurado ShotFest', 'shotfest-votaciones' ),
            $cuerpo
        );
    }

    public function enviar_apertura_periodo( int $periodo_id ): void {
        $edicion   = get_post_meta( $periodo_id, '_sf_periodo_edicion_year', true );
        $fecha_fin = get_post_meta( $periodo_id, '_sf_periodo_fecha_fin', true );
        $template  = $this->get_template( 'sf_email_periodo_abierto' );
        $jurado    = get_users( [ 'role' => 'jurado_shotfest' ] );

        foreach ( $jurado as $user ) {
            $cuerpo = $this->render( $template, [
                'nombre'         => $user->display_name,
                'edicion'        => (string) $edicion,
                'url_votaciones' => home_url( '/' ),
                'fecha_fin'      => $fecha_fin ? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $fecha_fin ) ) : '',
            ] );

            wp_mail(
                $user->user_email,
                sprintf( __( 'ShotFest %s — Votación abierta', 'shotfest-votaciones' ), $edicion ),
                $cuerpo
            );
        }
    }

    public function enviar_recordatorio( int $periodo_id ): void {
        $edicion   = get_post_meta( $periodo_id, '_sf_periodo_edicion_year', true );
        $fecha_fin = get_post_meta( $periodo_id, '_sf_periodo_fecha_fin', true );
        $template  = $this->get_template( 'sf_email_recordatorio' );
        $jurado    = get_users( [ 'role' => 'jurado_shotfest' ] );

        foreach ( $jurado as $user ) {
            $cuerpo = $this->render( $template, [
                'nombre'         => $user->display_name,
                'edicion'        => (string) $edicion,
                'url_votaciones' => home_url( '/' ),
                'fecha_fin'      => $fecha_fin ? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $fecha_fin ) ) : '',
            ] );

            wp_mail(
                $user->user_email,
                sprintf( __( 'ShotFest %s — Recuerda votar', 'shotfest-votaciones' ), $edicion ),
                $cuerpo
            );
        }
    }

    public function enviar_aviso_jurado_completo( int $periodo_id ): void {
        $edicion     = get_post_meta( $periodo_id, '_sf_periodo_edicion_year', true );
        $admin_email = get_option( 'admin_email' );

        wp_mail(
            $admin_email,
            sprintf( __( 'ShotFest %s — Todo el jurado ha votado', 'shotfest-votaciones' ), $edicion ),
            sprintf(
                __( 'Todo el jurado ha emitido sus votos en el periodo de votación de ShotFest %s. Puedes revisar los resultados en: %s', 'shotfest-votaciones' ),
                $edicion,
                admin_url( 'admin.php?page=sf-resultados&periodo_id=' . $periodo_id )
            )
        );
    }
}
