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

define( 'SF_VERSION', '1.0.1' );
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

function shotfest_votaciones_run(): void {
    $plugin = new Plugin();
    $plugin->run();
}

shotfest_votaciones_run();
