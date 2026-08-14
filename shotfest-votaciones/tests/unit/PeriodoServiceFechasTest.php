<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Tests;

use ShotfestVotaciones\Domain\PeriodoService;

/**
 * Fechas de periodo: normalización y cálculo de vigencia.
 *
 * Los tests que ya había guardaban las fechas con `date('Y-m-d H:i:s')`, el formato
 * canónico, así que pasaban en verde mientras el bug seguía ahí: el metabox guardaba en
 * realidad lo que envía un `<input type="datetime-local">` («2026-08-13T08:00», con «T» y
 * sin segundos) y la comparación se hacía como cadena, byte a byte, contra
 * `current_time('mysql')`. Como el espacio (0x20) siempre es menor que la «T» (0x54), un
 * periodo se consideraba «aún no iniciado» durante todo su primer día. De ahí que aquí se
 * pruebe explícitamente con el formato del formulario.
 */
class PeriodoServiceFechasTest extends \WP_UnitTestCase {

    private PeriodoService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new PeriodoService();
    }

    /** Fecha local del sitio desplazada, en el formato canónico */
    private function local( string $desplazamiento ): string {
        return current_datetime()->modify( $desplazamiento )->format( PeriodoService::FORMATO_FECHA );
    }

    /** La misma fecha, pero tal y como la envía un input datetime-local */
    private function local_como_formulario( string $desplazamiento ): string {
        return current_datetime()->modify( $desplazamiento )->format( 'Y-m-d\TH:i' );
    }

    private function crear_periodo( string $inicio, string $fin ): int {
        $periodo_id = self::factory()->post->create( [ 'post_type' => 'sf_periodo' ] );
        update_post_meta( $periodo_id, '_sf_periodo_fecha_inicio', $inicio );
        update_post_meta( $periodo_id, '_sf_periodo_fecha_fin', $fin );

        return $periodo_id;
    }

    public function test_normaliza_el_formato_del_formulario(): void {
        $this->assertSame( '2026-08-13 08:00:00', PeriodoService::normalizar_fecha( '2026-08-13T08:00' ) );
    }

    public function test_normaliza_con_segundos(): void {
        $this->assertSame( '2026-08-13 08:00:30', PeriodoService::normalizar_fecha( '2026-08-13T08:00:30' ) );
    }

    public function test_acepta_el_formato_ya_canonico(): void {
        $this->assertSame( '2026-08-13 08:00:00', PeriodoService::normalizar_fecha( '2026-08-13 08:00:00' ) );
    }

    public function test_descarta_valores_no_interpretables(): void {
        $this->assertSame( '', PeriodoService::normalizar_fecha( '' ) );
        $this->assertSame( '', PeriodoService::normalizar_fecha( 'no soy una fecha' ) );
        $this->assertSame( '', PeriodoService::normalizar_fecha( '2026-02-31T08:00' ) );
    }

    public function test_fecha_para_input_va_y_vuelve(): void {
        $this->assertSame( '2026-08-13T08:00', PeriodoService::fecha_para_input( '2026-08-13 08:00:00' ) );
        $this->assertSame( '2026-08-13T08:00', PeriodoService::fecha_para_input( '2026-08-13T08:00' ) );
    }

    public function test_la_fecha_se_interpreta_en_la_zona_del_sitio(): void {
        $esperado = ( new \DateTimeImmutable( '2026-08-13 08:00:00', wp_timezone() ) )->getTimestamp();

        $this->assertSame( $esperado, PeriodoService::fecha_a_timestamp( '2026-08-13T08:00' ) );
    }

    /** El bug original: el periodo arranca hoy a una hora ya pasada */
    public function test_periodo_iniciado_hoy_esta_vigente(): void {
        $periodo_id = $this->crear_periodo( $this->local( '-2 hours' ), $this->local( '+30 days' ) );

        $this->assertTrue( $this->service->esta_vigente( $periodo_id ) );
    }

    /** El mismo caso con los datos ya guardados en el formato antiguo */
    public function test_periodo_iniciado_hoy_esta_vigente_con_formato_legado(): void {
        $periodo_id = $this->crear_periodo(
            $this->local_como_formulario( '-2 hours' ),
            $this->local_como_formulario( '+30 days' )
        );

        $this->assertTrue( $this->service->esta_vigente( $periodo_id ) );
    }

    public function test_periodo_que_empieza_manana_no_esta_vigente(): void {
        $periodo_id = $this->crear_periodo( $this->local( '+1 day' ), $this->local( '+30 days' ) );

        $this->assertFalse( $this->service->esta_vigente( $periodo_id ) );
    }

    public function test_periodo_ya_cerrado_no_esta_vigente(): void {
        $periodo_id = $this->crear_periodo( $this->local( '-30 days' ), $this->local( '-1 hour' ) );

        $this->assertFalse( $this->service->esta_vigente( $periodo_id ) );
    }

    /** Antes hacían falta las dos fechas para comprobar cualquiera de ellas */
    public function test_solo_fecha_de_cierre_ya_pasada_cierra_el_periodo(): void {
        $periodo_id = $this->crear_periodo( '', $this->local( '-1 hour' ) );

        $this->assertFalse( $this->service->esta_vigente( $periodo_id ) );
    }

    public function test_solo_fecha_de_inicio_futura_no_abre_el_periodo(): void {
        $periodo_id = $this->crear_periodo( $this->local( '+1 day' ), '' );

        $this->assertFalse( $this->service->esta_vigente( $periodo_id ) );
    }

    public function test_periodo_sin_fechas_no_tiene_limites(): void {
        $periodo_id = $this->crear_periodo( '', '' );

        $this->assertTrue( $this->service->esta_vigente( $periodo_id ) );
    }

    public function test_get_periodo_abierto_encuentra_el_que_arranca_hoy(): void {
        $periodo_id = $this->crear_periodo( $this->local( '-2 hours' ), $this->local( '+30 days' ) );
        update_post_meta( $periodo_id, '_sf_periodo_estado', 'abierto' );

        $periodo = $this->service->get_periodo_abierto();

        $this->assertNotNull( $periodo );
        $this->assertSame( $periodo_id, $periodo->ID );
    }
}
