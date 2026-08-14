<?php
declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Eliminar tabla de votos
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}shotfest_votos" );

// Opciones del plugin: versión de esquema, versión de capabilities y textos de email
delete_option( 'sf_db_version' );
delete_option( 'sf_caps_version' );
delete_option( 'sf_jurado_caps_version' );
delete_option( 'sf_email_alta_jurado' );
delete_option( 'sf_email_periodo_abierto' );
delete_option( 'sf_email_recordatorio' );

// Transients de control de reenvío del recordatorio (uno por periodo)
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_sf\_recordatorio\_enviado\_%'
        OR option_name LIKE '\_transient\_timeout\_sf\_recordatorio\_enviado\_%'"
);

/*
 * Capabilities del administrador. Se carga la clase para leer ADMIN_CAPS en vez de
 * repetir la lista aquí: la copia manual ya se había quedado desincronizada y dejaba
 * `sf_gestionar_ediciones` pegada al rol para siempre.
 */
require_once __DIR__ . '/src/Roles/CapabilitiesManager.php';

$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
    foreach ( \ShotfestVotaciones\Roles\CapabilitiesManager::ADMIN_CAPS as $cap ) {
        $admin_role->remove_cap( $cap );
    }
}

// Eliminar rol custom
remove_role( 'jurado_shotfest' );
