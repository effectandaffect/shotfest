<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Tests;

use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Activation\Activator;

class VotoRepositoryTest extends \WP_UnitTestCase {

    private VotoRepository $repo;
    private int $usuario_id;
    private int $spot_id;
    private int $periodo_id;

    protected function setUp(): void {
        parent::setUp();
        Activator::activate();
        $this->repo       = new VotoRepository();
        $this->usuario_id = self::factory()->user->create( [ 'role' => 'jurado_shotfest' ] );
        $this->spot_id    = self::factory()->post->create( [ 'post_type' => 'sf_spot' ] );
        $this->periodo_id = self::factory()->post->create( [ 'post_type' => 'sf_periodo' ] );
    }

    public function test_insertar_voto_si(): void {
        $resultado = $this->repo->insertar( $this->usuario_id, $this->spot_id, $this->periodo_id, 1 );
        $this->assertTrue( $resultado );
    }

    public function test_insertar_voto_no(): void {
        $resultado = $this->repo->insertar( $this->usuario_id, $this->spot_id, $this->periodo_id, 0 );
        $this->assertTrue( $resultado );
    }

    public function test_doble_voto_retorna_false(): void {
        $this->repo->insertar( $this->usuario_id, $this->spot_id, $this->periodo_id, 1 );
        $resultado = $this->repo->insertar( $this->usuario_id, $this->spot_id, $this->periodo_id, 0 );
        $this->assertFalse( $resultado );
    }

    public function test_ya_voto_true(): void {
        $this->repo->insertar( $this->usuario_id, $this->spot_id, $this->periodo_id, 1 );
        $this->assertTrue( $this->repo->ya_voto( $this->usuario_id, $this->spot_id, $this->periodo_id ) );
    }

    public function test_ya_voto_false_sin_voto(): void {
        $this->assertFalse( $this->repo->ya_voto( $this->usuario_id, $this->spot_id, $this->periodo_id ) );
    }

    public function test_mismo_spot_distinto_periodo_permite_votar(): void {
        $periodo2 = self::factory()->post->create( [ 'post_type' => 'sf_periodo' ] );
        $this->repo->insertar( $this->usuario_id, $this->spot_id, $this->periodo_id, 1 );
        $resultado = $this->repo->insertar( $this->usuario_id, $this->spot_id, $periodo2, 0 );
        $this->assertTrue( $resultado );
        $this->assertTrue( $this->repo->ya_voto( $this->usuario_id, $this->spot_id, $this->periodo_id ) );
        $this->assertTrue( $this->repo->ya_voto( $this->usuario_id, $this->spot_id, $periodo2 ) );
    }

    public function test_conteos_por_spot(): void {
        $u2 = self::factory()->user->create( [ 'role' => 'jurado_shotfest' ] );
        $u3 = self::factory()->user->create( [ 'role' => 'jurado_shotfest' ] );

        $this->repo->insertar( $this->usuario_id, $this->spot_id, $this->periodo_id, 1 );
        $this->repo->insertar( $u2, $this->spot_id, $this->periodo_id, 1 );
        $this->repo->insertar( $u3, $this->spot_id, $this->periodo_id, 0 );

        $conteos = $this->repo->conteos_por_spot( $this->periodo_id );
        $this->assertArrayHasKey( $this->spot_id, $conteos );
        $this->assertSame( 2, $conteos[ $this->spot_id ]['si'] );
        $this->assertSame( 1, $conteos[ $this->spot_id ]['no'] );
    }

    public function test_conteos_periodo_distinto_aislado(): void {
        $periodo2 = self::factory()->post->create( [ 'post_type' => 'sf_periodo' ] );
        $this->repo->insertar( $this->usuario_id, $this->spot_id, $periodo2, 1 );

        $conteos = $this->repo->conteos_por_spot( $this->periodo_id );
        $this->assertEmpty( $conteos );
    }
}
