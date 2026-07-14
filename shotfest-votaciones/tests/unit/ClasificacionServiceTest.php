<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Tests;

use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Domain\ClasificacionService;
use ShotfestVotaciones\Domain\VotoService;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Activation\Activator;

class ClasificacionServiceTest extends \WP_UnitTestCase {

    private ClasificacionService $service;
    private VotoRepository $repo;
    private int $periodo_id;
    private int $cat_id;

    protected function setUp(): void {
        parent::setUp();
        Activator::activate();

        $this->repo    = new VotoRepository();
        $this->service = new ClasificacionService( $this->repo );

        $this->periodo_id = self::factory()->post->create( [ 'post_type' => 'sf_periodo' ] );

        // Crear categoría de prueba
        $term = wp_insert_term( 'Test Categoría', 'spot_categoria' );
        $this->cat_id = $term['term_id'];
    }

    private function crear_spot_con_categoria( string $titulo ): int {
        $spot_id = self::factory()->post->create( [
            'post_type'  => 'sf_spot',
            'post_title' => $titulo,
        ] );
        update_post_meta( $spot_id, '_sf_spot_periodo_id', $this->periodo_id );
        wp_set_post_terms( $spot_id, [ $this->cat_id ], 'spot_categoria' );
        return $spot_id;
    }

    public function test_clasificacion_vacia_con_cero_votos(): void {
        $spot_id = $this->crear_spot_con_categoria( 'Spot A' );
        $spots   = [ get_post( $spot_id ) ];

        $clasificacion = $this->service->clasificacion_por_periodo( $this->periodo_id, $spots );
        $this->assertNotEmpty( $clasificacion );

        $entry = $clasificacion[ $this->cat_id ]['spots'][0];
        $this->assertSame( 0, $entry['si'] );
        $this->assertSame( 0, $entry['no'] );
        $this->assertFalse( $entry['shortlist'] );
    }

    public function test_shortlist_spot_con_mas_votos_si(): void {
        $spot_a = $this->crear_spot_con_categoria( 'Spot A' );
        $spot_b = $this->crear_spot_con_categoria( 'Spot B' );

        $u1 = self::factory()->user->create();
        $u2 = self::factory()->user->create();
        $u3 = self::factory()->user->create();

        $this->repo->insertar( $u1, $spot_a, $this->periodo_id, 1 );
        $this->repo->insertar( $u2, $spot_a, $this->periodo_id, 1 );
        $this->repo->insertar( $u3, $spot_a, $this->periodo_id, 1 );
        $this->repo->insertar( $u1, $spot_b, $this->periodo_id, 1 );
        $this->repo->insertar( $u2, $spot_b, $this->periodo_id, 0 );

        $spots         = array_filter( [ get_post( $spot_a ), get_post( $spot_b ) ] );
        $clasificacion = $this->service->clasificacion_por_periodo( $this->periodo_id, $spots );
        $spots_result  = $clasificacion[ $this->cat_id ]['spots'];

        $entry_a = current( array_filter( $spots_result, fn( $e ) => $e['post']->ID === $spot_a ) );
        $entry_b = current( array_filter( $spots_result, fn( $e ) => $e['post']->ID === $spot_b ) );

        $this->assertTrue( $entry_a['shortlist'] );
        $this->assertFalse( $entry_b['shortlist'] );
        $this->assertSame( 1, $entry_a['posicion'] );
        $this->assertSame( 2, $entry_b['posicion'] );
    }

    public function test_empate_ambos_en_shortlist(): void {
        $spot_a = $this->crear_spot_con_categoria( 'Spot A' );
        $spot_b = $this->crear_spot_con_categoria( 'Spot B' );

        $u1 = self::factory()->user->create();
        $u2 = self::factory()->user->create();

        $this->repo->insertar( $u1, $spot_a, $this->periodo_id, 1 );
        $this->repo->insertar( $u2, $spot_b, $this->periodo_id, 1 );

        $spots         = array_filter( [ get_post( $spot_a ), get_post( $spot_b ) ] );
        $clasificacion = $this->service->clasificacion_por_periodo( $this->periodo_id, $spots );
        $spots_result  = $clasificacion[ $this->cat_id ]['spots'];

        foreach ( $spots_result as $entry ) {
            $this->assertTrue( $entry['shortlist'], 'En empate, todos deben estar en shortlist' );
            $this->assertSame( 1, $entry['posicion'] );
        }
    }

    public function test_posiciones_correctas_sin_empate(): void {
        $spot_a = $this->crear_spot_con_categoria( 'Spot A' );
        $spot_b = $this->crear_spot_con_categoria( 'Spot B' );
        $spot_c = $this->crear_spot_con_categoria( 'Spot C' );

        $u1 = self::factory()->user->create();
        $u2 = self::factory()->user->create();
        $u3 = self::factory()->user->create();

        $this->repo->insertar( $u1, $spot_a, $this->periodo_id, 1 );
        $this->repo->insertar( $u2, $spot_a, $this->periodo_id, 1 );
        $this->repo->insertar( $u3, $spot_a, $this->periodo_id, 1 );

        $this->repo->insertar( $u1, $spot_b, $this->periodo_id, 1 );
        $this->repo->insertar( $u2, $spot_b, $this->periodo_id, 1 );

        $this->repo->insertar( $u1, $spot_c, $this->periodo_id, 1 );

        $spots         = array_filter( [ get_post( $spot_a ), get_post( $spot_b ), get_post( $spot_c ) ] );
        $clasificacion = $this->service->clasificacion_por_periodo( $this->periodo_id, $spots );
        $spots_result  = $clasificacion[ $this->cat_id ]['spots'];

        $by_id = [];
        foreach ( $spots_result as $e ) {
            $by_id[ $e['post']->ID ] = $e;
        }

        $this->assertSame( 1, $by_id[ $spot_a ]['posicion'] );
        $this->assertSame( 2, $by_id[ $spot_b ]['posicion'] );
        $this->assertSame( 3, $by_id[ $spot_c ]['posicion'] );
        $this->assertTrue( $by_id[ $spot_a ]['shortlist'] );
        $this->assertFalse( $by_id[ $spot_b ]['shortlist'] );
    }
}
