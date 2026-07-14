<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Frontend;

class TemplateLoader {

    /**
     * Carga una plantilla buscando primero en el tema activo,
     * luego en el directorio templates/ del plugin.
     */
    public static function load( string $template_name, array $vars = [] ): string {
        $theme_file  = get_stylesheet_directory() . '/shotfest-votaciones/' . $template_name;
        $plugin_file = SF_PLUGIN_DIR . 'templates/' . $template_name;

        $file = file_exists( $theme_file ) ? $theme_file : $plugin_file;

        if ( ! file_exists( $file ) ) {
            return '';
        }

        // Extraer variables al scope local antes de incluir
        if ( $vars ) {
            extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        }

        ob_start();
        include $file;
        return ob_get_clean() ?: '';
    }
}
