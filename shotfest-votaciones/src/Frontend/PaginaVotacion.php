<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Frontend;

/**
 * Resuelve la URL pública de la votación: la página que contiene el shortcode
 * `[shotfest_votaciones]`.
 *
 * Antes, tanto `{{url_votaciones}}` en los tres emails como el redirect del jurado
 * fuera de wp-admin apuntaban fijo a `home_url('/')`. Eso solo era correcto bajo la
 * suposición del plan original —que el shortcode viviría en la portada—, así que al
 * colocarlo en una página propia los emails mandaban al jurado a la home, donde no
 * hay nada que votar.
 *
 * La página se localiza sola y el resultado se cachea en una opción, revalidándola en
 * cada lectura: si alguien la despublica, la borra o mueve el shortcode a otro sitio,
 * la siguiente llamada vuelve a buscar en vez de devolver una URL muerta. Si no hay
 * ninguna página con el shortcode se cae a `home_url('/')`, el comportamiento anterior.
 */
class PaginaVotacion {

    const OPTION_KEY = 'sf_pagina_votacion_id';
    const SHORTCODE  = 'shotfest_votaciones';

    /** URL de la página de votación, o la portada si no se encuentra ninguna. */
    public static function url(): string {
        $page_id = self::page_id();

        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }

        return home_url( '/' );
    }

    /** ID de la página que contiene el shortcode, o 0 si no hay ninguna. */
    public static function page_id(): int {
        $cacheada = (int) get_option( self::OPTION_KEY, 0 );
        if ( $cacheada && self::contiene_shortcode( $cacheada ) ) {
            return $cacheada;
        }

        $encontrada = self::buscar();
        update_option( self::OPTION_KEY, $encontrada );

        return $encontrada;
    }

    /** ¿Sigue siendo válida la página cacheada? */
    private static function contiene_shortcode( int $page_id ): bool {
        $post = get_post( $page_id );

        if ( ! $post || 'publish' !== $post->post_status ) {
            return false;
        }

        return has_shortcode( (string) $post->post_content, self::SHORTCODE );
    }

    /**
     * Busca la primera entrada o página publicada que contenga el shortcode.
     *
     * Se consulta directamente por `post_content`: `WP_Query` no sabe buscar shortcodes
     * y su parámetro `s` haría una búsqueda de texto con otras reglas.
     */
    private static function buscar(): int {
        global $wpdb;

        $like = '%' . $wpdb->esc_like( '[' . self::SHORTCODE ) . '%';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ('page', 'post')
                   AND post_content LIKE %s
                 ORDER BY ID ASC
                 LIMIT 1",
                $like
            )
        );
    }
}
