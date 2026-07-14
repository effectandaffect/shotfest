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

    public static function add(): void {
        if ( get_role( self::ROLE_SLUG ) ) {
            return;
        }

        $caps = array_fill_keys( self::CAPS, true );
        add_role( self::ROLE_SLUG, __( 'Jurado ShotFest', 'shotfest-votaciones' ), $caps );
    }

    public static function remove(): void {
        remove_role( self::ROLE_SLUG );
    }
}
