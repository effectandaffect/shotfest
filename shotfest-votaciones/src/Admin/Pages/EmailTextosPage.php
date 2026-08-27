<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

class EmailTextosPage {

    /**
     * Identidad del remitente.
     *
     * Por defecto WordPress firma como «WordPress» desde `wordpress@<dominio>`, una
     * dirección que nadie ha configurado nunca. A un jurado que espera un correo de la
     * organización eso le parece spam — y a los filtros de los dominios corporativos,
     * también.
     *
     * Ojo: esto arregla cómo se ve el remitente, no la entregabilidad. Si el dominio
     * publica DMARC y el correo sale del servidor web sin SPF/DKIM alineados, seguirá
     * yendo a no deseado. Para eso hace falta enviar por SMTP autenticado.
     */
    const REMITENTE = [
        'sf_email_from_name' => [
            'label'       => 'Nombre del remitente',
            'description' => 'Lo que ve el jurado como remitente. Si se deja vacío, WordPress firma como «WordPress».',
        ],
        'sf_email_from'      => [
            'label'       => 'Dirección del remitente',
            'description' => 'Debe ser una dirección del propio dominio (por ejemplo premios@shotfest.es). Una dirección de otro dominio hace fallar la verificación DMARC y acaba en spam. Vacío = la que ponga WordPress por defecto.',
        ],
    ];

    /** Nombre configurado para el remitente, o '' si no se ha puesto ninguno. */
    public static function from_name(): string {
        return (string) get_option( 'sf_email_from_name', '' );
    }

    /** Dirección configurada para el remitente, o '' si no se ha puesto ninguna. */
    public static function from_address(): string {
        $email = (string) get_option( 'sf_email_from', '' );

        return is_email( $email ) ? $email : '';
    }

    const OPTIONS = [
        'sf_email_alta_jurado'     => [
            'label'   => 'Email de bienvenida al jurado',
            'default' => "Bienvenido/a al jurado de ShotFest {{edicion}}.\n\nPuedes establecer tu contraseña y acceder aquí: {{link_password}}\n\nUna vez dentro, podrás votar los spots en: {{url_votaciones}}\n\nSaludos,\nEquipo ShotFest",
            'vars'    => '{{edicion}}, {{link_password}}, {{url_votaciones}}',
        ],
        'sf_email_periodo_abierto' => [
            'label'   => 'Email de apertura de periodo de votación',
            'default' => "Hola {{nombre}},\n\nEl periodo de votación de ShotFest {{edicion}} ya está abierto.\n\nVota los spots aquí: {{url_votaciones}}\n\nFecha límite: {{fecha_fin}}\n\nSaludos,\nEquipo ShotFest",
            'vars'    => '{{nombre}}, {{edicion}}, {{url_votaciones}}, {{fecha_fin}}',
        ],
        'sf_email_recordatorio'    => [
            'label'   => 'Email de recordatorio (envío automático)',
            'default' => "Hola {{nombre}},\n\nRecuerda que el periodo de votación de ShotFest {{edicion}} cierra el {{fecha_fin}}.\n\nAún tienes spots pendientes de votar: {{url_votaciones}}\n\nSaludos,\nEquipo ShotFest",
            'vars'    => '{{nombre}}, {{edicion}}, {{url_votaciones}}, {{fecha_fin}}',
        ],
    ];

    public function render(): void {
        if ( ! current_user_can( 'sf_gestionar_emails' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'shotfest-votaciones' ) );
        }

        if ( isset( $_POST['sf_emails_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sf_emails_nonce'] ) ), 'sf_guardar_emails' ) ) {
            $error = $this->guardar();

            if ( '' === $error ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Textos guardados.', 'shotfest-votaciones' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Textos de email', 'shotfest-votaciones' ); ?></h1>
            <p><?php esc_html_e( 'Edita el texto de los emails automáticos. Las variables entre {{}} serán sustituidas automáticamente.', 'shotfest-votaciones' ); ?></p>

            <form method="post">
                <?php wp_nonce_field( 'sf_guardar_emails', 'sf_emails_nonce' ); ?>

                <h2><?php esc_html_e( 'Remitente', 'shotfest-votaciones' ); ?></h2>
                <table class="form-table">
                    <?php foreach ( self::REMITENTE as $option_key => $config ) : ?>
                        <tr>
                            <th>
                                <label for="<?php echo esc_attr( $option_key ); ?>">
                                    <?php echo esc_html( $config['label'] ); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="<?php echo 'sf_email_from' === $option_key ? 'email' : 'text'; ?>"
                                    id="<?php echo esc_attr( $option_key ); ?>"
                                    name="<?php echo esc_attr( $option_key ); ?>"
                                    value="<?php echo esc_attr( (string) get_option( $option_key, '' ) ); ?>"
                                    class="regular-text"
                                    placeholder="<?php echo esc_attr( 'sf_email_from' === $option_key ? 'premios@shotfest.es' : get_bloginfo( 'name' ) ); ?>">
                                <p class="description"><?php echo esc_html( $config['description'] ); ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php foreach ( self::OPTIONS as $option_key => $config ) : ?>
                    <?php $value = get_option( $option_key, $config['default'] ); ?>
                    <h2><?php echo esc_html( $config['label'] ); ?></h2>
                    <p class="description"><?php echo esc_html__( 'Variables disponibles: ', 'shotfest-votaciones' ) . esc_html( $config['vars'] ); ?></p>
                    <textarea name="<?php echo esc_attr( $option_key ); ?>" rows="8" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
                <?php endforeach; ?>

                <?php submit_button( __( 'Guardar textos', 'shotfest-votaciones' ) ); ?>
            </form>
        </div>
        <?php
    }

    /** @return string Mensaje de error, o '' si todo se guardó bien. */
    private function guardar(): string {
        foreach ( self::OPTIONS as $option_key => $config ) {
            if ( isset( $_POST[ $option_key ] ) ) {
                update_option( $option_key, sanitize_textarea_field( wp_unslash( $_POST[ $option_key ] ) ) );
            }
        }

        if ( isset( $_POST['sf_email_from_name'] ) ) {
            update_option( 'sf_email_from_name', sanitize_text_field( wp_unslash( $_POST['sf_email_from_name'] ) ) );
        }

        if ( ! isset( $_POST['sf_email_from'] ) ) {
            return '';
        }

        $direccion = sanitize_email( wp_unslash( $_POST['sf_email_from'] ) );

        // Vaciar es válido: significa «usa el remitente por defecto de WordPress»
        if ( '' === trim( (string) $_POST['sf_email_from'] ) ) {
            update_option( 'sf_email_from', '' );

            return '';
        }

        if ( ! is_email( $direccion ) ) {
            return __( 'La dirección del remitente no es válida, así que no se ha guardado. El resto de cambios sí.', 'shotfest-votaciones' );
        }

        update_option( 'sf_email_from', $direccion );

        return '';
    }
}
