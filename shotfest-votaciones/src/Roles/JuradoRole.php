<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Roles;

class JuradoRole {

    const ROLE_SLUG = 'jurado_shotfest';

    const CAPS = [
        'sf_ver_spots',
        'sf_votar_spot',
        'sf_ver_resultados_publicados',
        'read',
    ];

    /** Súbelo cada vez que CAPS cambie, para que maybe_sync() reparta las nuevas capabilities */
    const VERSION    = '1';
    const OPTION_KEY = 'sf_jurado_caps_version';

    public static function add(): void {
        $role = get_role( self::ROLE_SLUG );

        if ( ! $role ) {
            add_role( self::ROLE_SLUG, __( 'Jurado ShotFest', 'shotfest-votaciones' ), array_fill_keys( self::CAPS, true ) );
        } else {
            // El rol ya existe: se completan las capabilities que falten. Antes se salía
            // sin hacer nada, así que un cambio en CAPS no llegaba nunca a las
            // instalaciones existentes (maybe_sync() solo cubría el rol administrator).
            foreach ( self::CAPS as $cap ) {
                $role->add_cap( $cap );
            }
        }

        update_option( self::OPTION_KEY, self::VERSION );
    }

    /** Sincroniza capabilities nuevas del jurado en el primer admin_init tras un despliegue */
    public static function maybe_sync(): void {
        if ( get_option( self::OPTION_KEY ) === self::VERSION ) {
            return;
        }

        self::add();
    }

    public static function remove(): void {
        remove_role( self::ROLE_SLUG );
    }
}
