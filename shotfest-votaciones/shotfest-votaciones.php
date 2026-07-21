<?php
/**
 * Plugin Name:       ShotFest Votaciones
 * Plugin URI:        https://shotfest.es
 * Description:       Sistema de votaciones para el jurado de los premios ShotFest de Grupo 014.
 * Version:           1.0.0
 * Author:            014Media
 * License:           Proprietary
 * Text Domain:       shotfest-votaciones
 * Domain Path:       /languages
 * Requires PHP:      8.1
 * Requires at least: 6.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SF_VERSION', '1.1.0' );
define( 'SF_PLUGIN_FILE', __FILE__ );
define( 'SF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SF_TEXT_DOMAIN', 'shotfest-votaciones' );

// Autoload vía Composer
if ( file_exists( SF_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once SF_PLUGIN_DIR . 'vendor/autoload.php';
}

use ShotfestVotaciones\Activation\Activator;
use ShotfestVotaciones\Activation\Deactivator;
use ShotfestVotaciones\Plugin;

register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Deactivator::class, 'deactivate' ] );

/** Extrae el ID de vídeo de YouTube de una URL, en cualquier formato habitual. Devuelve '' si no reconoce la URL. */
function sf_extract_video_id( string $url ): string {
    if ( ! $url ) {
        return '';
    }

    // youtube.com/watch?...&v=ID&... (v= en cualquier posición de la query)
    $query = wp_parse_url( $url, PHP_URL_QUERY );
    if ( $query ) {
        parse_str( $query, $params );
        if ( ! empty( $params['v'] ) && preg_match( '/^[a-zA-Z0-9_\-]{11}$/', $params['v'] ) ) {
            return $params['v'];
        }
    }

    $patterns = [
        '/youtu\.be\/([a-zA-Z0-9_\-]{11})/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_\-]{11})/',
        '/youtube\.com\/shorts\/([a-zA-Z0-9_\-]{11})/',
    ];
    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $url, $m ) ) {
            return $m[1];
        }
    }

    return '';
}

function shotfest_votaciones_run(): void {
    $plugin = new Plugin();
    $plugin->run();
}

shotfest_votaciones_run();
