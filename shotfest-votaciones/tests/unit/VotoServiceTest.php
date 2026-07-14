<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Tests;

use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Domain\VotoService;
use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\Activation\Activator;

class VotoServiceTest extends \WP_UnitTestCase {

    private VotoService $service;
    private VotoRepository $repo;
    private int $usuario_id;
    private int $spot_id;
    private int $periodo_id;

    protected function setUp(): void {
        parent::setUp();
        Activator::activate();

        $this->repo      = new VotoRepository();
        $this->service   = new VotoService( $this->repo, new PeriodoService() );
        $this->usuario_id = self::factory()->user->create( [ 'role' => 'jurado_shotfest' ] );

        // Crear periodo abierto con fechas válidas
        $this->periodo_id = self::factory()->post->create( [ 'post_type' => 'sf_periodo' ] );
        update_post_meta( $this->periodo_id, '_sf_periodo_estado', 'abierto' );
        update_post_meta( $this->periodo_id, '_sf_periodo_fecha_inicio', date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) );
        update_post_meta( $this->periodo_id, '_sf_periodo_fecha_fin', date( 'Y-m-d H:i:s', strtotime( '+7 days' ) ) );

        // Crear spot disponible para el periodo
        $this->spot_id = self::factory()->post->create( [ 'post_type' => 'sf_spot' ] );
        update_post_meta( $this->spot_id, '_sf_spot_estado', 'disponible_votacion' );
        update_post_meta( $this->spot_id, '_sf_spot_periodo_id', $this->periodo_id );

        wp_set_current_user( $this->usuario_id );
    }

    public function test_voto_si_registrado(): void {
        $result = $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 1 );
        $this->assertTrue( $result['ok'] );
    }

    public function test_voto_no_registrado(): void {
        $result = $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 0 );
        $this->assertTrue( $result['ok'] );
    }

    public function test_doble_voto_rechazado(): void {
        $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 1 );
        $result = $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 0 );
        $this->assertFalse( $result['ok'] );
    }

    public function test_voto_en_periodo_cerrado_rechazado(): void {
        update_post_meta( $this->periodo_id, '_sf_periodo_estado', 'cerrado' );
        $result = $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 1 );
        $this->assertFalse( $result['ok'] );
    }

    public function test_voto_en_spot_no_disponible_rechazado(): void {
        update_post_meta( $this->spot_id, '_sf_spot_estado', 'finalizado' );
        $result = $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 1 );
        $this->assertFalse( $result['ok'] );
    }

    public function test_valor_invalido_rechazado(): void {
        $result = $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 99 );
        $this->assertFalse( $result['ok'] );
    }

    public function test_voto_fuera_de_fechas_rechazado(): void {
        update_post_meta( $this->periodo_id, '_sf_periodo_fecha_fin', date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) );
        $result = $this->service->registrarVoto( $this->usuario_id, $this->spot_id, 1 );
        $this->assertFalse( $result['ok'] );
    }
}
