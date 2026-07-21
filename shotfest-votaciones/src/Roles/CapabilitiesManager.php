<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Roles;

class CapabilitiesManager {

    const ADMIN_CAPS = [
        'sf_gestionar_spots',
        'sf_gestionar_periodos',
        'sf_gestionar_ediciones',
        'sf_gestionar_jurado',
        'sf_ver_resultados',
        'sf_exportar_resultados',
        'sf_gestionar_emails',
    ];

    /** Súbelo cada vez que ADMIN_CAPS cambie, para que maybe_sync() reparta las nuevas capabilities sin depender de reactivar el plugin */
    const VERSION     = '2';
    const OPTION_KEY  = 'sf_caps_version';

    public static function add_to_administrator(): void {
        $role = get_role( 'administrator' );
        if ( ! $role ) {
            return;
        }
        foreach ( self::ADMIN_CAPS as $cap ) {
            $role->add_cap( $cap );
        }
    }

    /** Sincroniza capabilities nuevas en el primer admin_init tras un despliegue, sin requerir desactivar/reactivar el plugin */
    public static function maybe_sync(): void {
        if ( get_option( self::OPTION_KEY ) === self::VERSION ) {
            return;
        }
        self::add_to_administrator();
        update_option( self::OPTION_KEY, self::VERSION );
    }

    public static function remove_from_administrator(): void {
        $role = get_role( 'administrator' );
        if ( ! $role ) {
            return;
        }
        foreach ( self::ADMIN_CAPS as $cap ) {
            $role->remove_cap( $cap );
        }
    }
}
