<?php
declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Eliminar tabla de votos
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}shotfest_votos" );

// Eliminar versión de esquema
delete_option( 'sf_db_version' );

// Eliminar opciones del plugin
delete_option( 'sf_email_alta_jurado' );
delete_option( 'sf_email_periodo_abierto' );
delete_option( 'sf_email_recordatorio' );

// Eliminar capabilities del administrador
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
    $caps = [
        'sf_gestionar_spots',
        'sf_gestionar_periodos',
        'sf_gestionar_jurado',
        'sf_ver_resultados',
        'sf_exportar_resultados',
        'sf_gestionar_emails',
    ];
    foreach ( $caps as $cap ) {
        $admin_role->remove_cap( $cap );
    }
}

// Eliminar rol custom
remove_role( 'jurado_shotfest' );
