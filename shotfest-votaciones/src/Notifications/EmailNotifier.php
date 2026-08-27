<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Notifications;

use ShotfestVotaciones\Admin\Pages\EmailTextosPage;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Frontend\PaginaVotacion;

class EmailNotifier {

    public function __construct(
        private readonly PeriodoService $periodo_service,
        private readonly VotoRepository $voto_repo
    ) {}

    /**
     * Envía un correo del plugin firmándolo con el remitente configurado.
     *
     * Los filtros se añaden justo antes de `wp_mail()` y se retiran justo después, a
     * propósito: si se registraran de forma global, el plugin estaría reescribiendo
     * también el remitente de los correos de WPForms, de Complianz y de la recuperación
     * de contraseña del núcleo, que no son suyos.
     *
     * Con el remitente sin configurar no se toca nada y WordPress usa su valor por
     * defecto, que es el comportamiento anterior.
     */
    private function enviar( string $destinatario, string $asunto, string $cuerpo ): bool {
        $nombre    = EmailTextosPage::from_name();
        $direccion = EmailTextosPage::from_address();

        $filtro_nombre    = static fn(): string => $nombre;
        $filtro_direccion = static fn(): string => $direccion;

        if ( '' !== $nombre ) {
            add_filter( 'wp_mail_from_name', $filtro_nombre );
        }
        if ( '' !== $direccion ) {
            add_filter( 'wp_mail_from', $filtro_direccion );
        }

        try {
            return wp_mail( $destinatario, $asunto, $cuerpo );
        } finally {
            // finally: si wp_mail() lanza, los filtros no pueden quedarse puestos
            remove_filter( 'wp_mail_from_name', $filtro_nombre );
            remove_filter( 'wp_mail_from', $filtro_direccion );
        }
    }

    private function get_template( string $option_key ): string {
        return get_option( $option_key, EmailTextosPage::OPTIONS[ $option_key ]['default'] ?? '' );
    }

    private function render( string $template, array $vars ): string {
        $keys   = array_map( fn( $k ) => '{{' . $k . '}}', array_keys( $vars ) );
        return str_replace( $keys, array_values( $vars ), $template );
    }

    /**
     * Formatea una fecha de periodo para mostrarla en un email, en la zona horaria del sitio.
     * `wp_date()` sobre un timestamp real, en vez de `date_i18n(strtotime(...))`, que aplicaba
     * el offset dos veces.
     */
    private function formatear_fecha( string $fecha ): string {
        $ts = PeriodoService::fecha_a_timestamp( $fecha );

        return null === $ts ? '' : (string) wp_date( get_option( 'date_format' ) . ' H:i', $ts );
    }

    /**
     * Año de la edición más reciente asignada al miembro del jurado.
     *
     * El email de bienvenida usaba `date('Y')`, que además de ignorar la zona horaria de
     * WordPress da el año en curso: alguien dado de alta en diciembre para la edición
     * siguiente recibía el año anterior. Se cae al año actual solo si no tiene ninguna
     * edición asignada.
     */
    private function anio_del_jurado( int $user_id ): string {
        $anios = [];
        foreach ( array_map( 'intval', get_user_meta( $user_id, '_sf_jurado_edicion_id', false ) ) as $edicion_id ) {
            $anio = (int) get_post_meta( $edicion_id, '_sf_edicion_anio', true );
            if ( $anio > 0 ) {
                $anios[] = $anio;
            }
        }

        return (string) ( $anios ? max( $anios ) : wp_date( 'Y' ) );
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
    public function jurado_del_periodo( int $periodo_id ): array {
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

        // El TTL de WP por defecto es de 24h (DAY_IN_SECONDS); con 12-15 altas de golpe es
        // probable que alguien abra el enlace tarde, así que se amplía a 96h solo para este
        // envío (el filtro se retira justo después para no afectar a otros resets de contraseña).
        $ampliar_ttl = static fn (): int => 4 * DAY_IN_SECONDS;
        add_filter( 'password_reset_expiration', $ampliar_ttl );
        $reset_key = get_password_reset_key( $user );
        remove_filter( 'password_reset_expiration', $ampliar_ttl );
        if ( is_wp_error( $reset_key ) ) {
            return;
        }
        $reset_url = network_site_url( "wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode( $user->user_login ) );

        $template = $this->get_template( 'sf_email_alta_jurado' );
        $cuerpo   = $this->render( $template, [
            'edicion'        => $this->anio_del_jurado( $user_id ),
            'link_password'  => $reset_url,
            'url_votaciones' => PaginaVotacion::url(),
        ] );

        $this->enviar(
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
                'url_votaciones' => PaginaVotacion::url(),
                'fecha_fin'      => $this->formatear_fecha( (string) $fecha_fin ),
            ] );

            $this->enviar(
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
                'url_votaciones' => PaginaVotacion::url(),
                'fecha_fin'      => $this->formatear_fecha( (string) $fecha_fin ),
            ] );

            $this->enviar(
                $user->user_email,
                sprintf( __( 'ShotFest %s — Recuerda votar', 'shotfest-votaciones' ), $edicion ),
                $cuerpo
            );
        }
    }

    public function enviar_aviso_jurado_completo( int $periodo_id ): void {
        $edicion     = $this->resolve_edicion_anio( $periodo_id );
        $admin_email = get_option( 'admin_email' );

        $this->enviar(
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
