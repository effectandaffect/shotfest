<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Roles;

class CapabilitiesManager {

    const ADMIN_CAPS = [
        'sf_gestionar_spots',
        'sf_gestionar_periodos',
        'sf_gestionar_jurado',
        'sf_ver_resultados',
        'sf_exportar_resultados',
        'sf_gestionar_emails',
    ];

    public static function add_to_administrator(): void {
        $role = get_role( 'administrator' );
        if ( ! $role ) {
            return;
        }
        foreach ( self::ADMIN_CAPS as $cap ) {
            $role->add_cap( $cap );
        }
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
