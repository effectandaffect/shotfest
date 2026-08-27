<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Tests;

use ShotfestVotaciones\Admin\Pages\EmailTextosPage;

/**
 * El remitente configurable existe porque WordPress firma por defecto como «WordPress»
 * desde wordpress@<dominio>, y a un jurado eso le parece spam.
 *
 * Lo que se cubre aquí son los accesores: que una dirección inválida no llegue nunca a
 * los filtros de wp_mail, y que sin configurar no se toque el comportamiento por defecto.
 */
class RemitenteEmailTest extends \WP_UnitTestCase {

    public function tearDown(): void {
        delete_option( 'sf_email_from_name' );
        delete_option( 'sf_email_from' );
        parent::tearDown();
    }

    public function test_sin_configurar_no_hay_remitente_propio(): void {
        $this->assertSame( '', EmailTextosPage::from_name() );
        $this->assertSame( '', EmailTextosPage::from_address() );
    }

    public function test_devuelve_el_nombre_configurado(): void {
        update_option( 'sf_email_from_name', 'Premios ShotFest' );

        $this->assertSame( 'Premios ShotFest', EmailTextosPage::from_name() );
    }

    public function test_devuelve_la_direccion_configurada(): void {
        update_option( 'sf_email_from', 'premios@shotfest.es' );

        $this->assertSame( 'premios@shotfest.es', EmailTextosPage::from_address() );
    }

    /**
     * Una dirección corrupta en la opción no debe propagarse al filtro wp_mail_from:
     * PHPMailer la rechazaría y el envío fallaría entero, en vez de caer al remitente
     * por defecto de WordPress.
     */
    public function test_una_direccion_invalida_se_ignora(): void {
        update_option( 'sf_email_from', 'esto no es un email' );

        $this->assertSame( '', EmailTextosPage::from_address() );
    }

    public function test_una_direccion_vacia_se_ignora(): void {
        update_option( 'sf_email_from', '' );

        $this->assertSame( '', EmailTextosPage::from_address() );
    }
}
