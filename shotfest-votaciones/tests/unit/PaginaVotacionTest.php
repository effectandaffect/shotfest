<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Tests;

use ShotfestVotaciones\Frontend\PaginaVotacion;

/**
 * La URL de votación tiene que salir de la página que contiene el shortcode, no de la
 * portada. Antes iba fija a home_url('/'), así que en cuanto el shortcode dejaba de
 * vivir en la home los tres emails mandaban al jurado a una página sin votación.
 */
class PaginaVotacionTest extends \WP_UnitTestCase {

    private function crear_pagina_con_shortcode( string $estado = 'publish' ): int {
        return self::factory()->post->create( [
            'post_type'    => 'page',
            'post_status'  => $estado,
            'post_title'   => 'Votación',
            'post_content' => '[shotfest_votaciones]',
        ] );
    }

    public function tearDown(): void {
        delete_option( PaginaVotacion::OPTION_KEY );
        parent::tearDown();
    }

    public function test_sin_pagina_con_shortcode_cae_a_la_portada(): void {
        $this->assertSame( home_url( '/' ), PaginaVotacion::url() );
    }

    public function test_encuentra_la_pagina_que_contiene_el_shortcode(): void {
        self::factory()->post->create( [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => 'Una página cualquiera sin shortcode',
        ] );
        $votacion = $this->crear_pagina_con_shortcode();

        $this->assertSame( get_permalink( $votacion ), PaginaVotacion::url() );
        $this->assertSame( $votacion, PaginaVotacion::page_id() );
    }

    public function test_ignora_paginas_en_borrador(): void {
        $this->crear_pagina_con_shortcode( 'draft' );

        $this->assertSame( home_url( '/' ), PaginaVotacion::url() );
    }

    public function test_cachea_el_id_encontrado(): void {
        $votacion = $this->crear_pagina_con_shortcode();

        PaginaVotacion::url();

        $this->assertSame( $votacion, (int) get_option( PaginaVotacion::OPTION_KEY ) );
    }

    /** Si la página cacheada deja de valer, hay que volver a buscar en vez de devolver una URL muerta. */
    public function test_revalida_la_cache_si_la_pagina_deja_de_servir(): void {
        $vieja = $this->crear_pagina_con_shortcode();
        PaginaVotacion::url();
        $this->assertSame( $vieja, (int) get_option( PaginaVotacion::OPTION_KEY ) );

        // Se le quita el shortcode y aparece otra página que sí lo tiene
        wp_update_post( [ 'ID' => $vieja, 'post_content' => 'Ya no hay shortcode aquí' ] );
        $nueva = $this->crear_pagina_con_shortcode();

        $this->assertSame( get_permalink( $nueva ), PaginaVotacion::url() );
        $this->assertSame( $nueva, (int) get_option( PaginaVotacion::OPTION_KEY ) );
    }

    public function test_vuelve_a_la_portada_si_se_despublica_la_pagina_cacheada(): void {
        $votacion = $this->crear_pagina_con_shortcode();
        PaginaVotacion::url();

        wp_update_post( [ 'ID' => $votacion, 'post_status' => 'draft' ] );

        $this->assertSame( home_url( '/' ), PaginaVotacion::url() );
    }
}
