<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Notifications;

use ShotfestVotaciones\Admin\Pages\EmailTextosPage;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Data\VotoRepository;

class EmailNotifier {

    public function __construct(
        private readonly PeriodoService $periodo_service,
        private readonly VotoRepository $voto_repo
    ) {}

    private function get_template( string $option_key ): string {
        return get_option( $option_key, EmailTextosPage::OPTIONS[ $option_key ]['default'] ?? '' );
    }

    private function render( string $template, array $vars ): string {
        $keys   = array_map( fn( $k ) => '{{' . $k . '}}', array_keys( $vars ) );
        return str_replace( $keys, array_values( $vars ), $template );
    }

    /** Resuelve el año de la edición a la que pertenece un periodo, o '' si no tiene edición asignada */
    private function resolve_edicion_anio( int $periodo_id ): string {
        $edicion_id = get_post_meta( $periodo_id, '_sf_periodo_edicion_id', true );
        return $edicion_id ? (string) get_post_meta( $edicion_id, '_sf_edicion_anio', true ) : '';
    }

    /**
     * Jurado de la edición a la que pertenece el periodo. Si el periodo no tiene
     * edición asignada (dato antiguo), se envía a todo el jurado como antes.
     */
    private function jurado_del_periodo( int $periodo_id ): array {
        $edicion_id = get_post_meta( $periodo_id, '_sf_periodo_edicion_id', true );
        if ( ! $edicion_id ) {
            return get_users( [ 'role' => 'jurado_shotfest' ] );
        }
        return get_users( [
            'role'       => 'jurado_shotfest',
            'meta_key'   => '_sf_jurado_edicion_id',
            'meta_value' => $edicion_id,
        ] );
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
        $edicion   = $this->resolve_edicion_anio( $periodo_id );
        $fecha_fin = get_post_meta( $periodo_id, '_sf_periodo_fecha_fin', true );
        $template  = $this->get_template( 'sf_email_periodo_abierto' );
        $jurado    = $this->jurado_del_periodo( $periodo_id );

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
        $edicion     = $this->resolve_edicion_anio( $periodo_id );
        $fecha_fin   = get_post_meta( $periodo_id, '_sf_periodo_fecha_fin', true );
        $template    = $this->get_template( 'sf_email_recordatorio' );
        $jurado      = $this->jurado_del_periodo( $periodo_id );
        $total_spots = count( $this->periodo_service->get_spots_del_periodo( $periodo_id ) );

        foreach ( $jurado as $user ) {
            // Solo se recuerda a quien todavía tenga spots pendientes de votar en este periodo
            if ( $total_spots > 0 ) {
                $votados = count( $this->voto_repo->votos_usuario( $user->ID, $periodo_id ) );
                if ( $votados >= $total_spots ) {
                    continue;
                }
            }

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
        $edicion     = $this->resolve_edicion_anio( $periodo_id );
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
