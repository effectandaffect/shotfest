<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Admin\Pages;

class EmailTextosPage {

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
            $this->guardar();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Textos guardados.', 'shotfest-votaciones' ) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Textos de email', 'shotfest-votaciones' ); ?></h1>
            <p><?php esc_html_e( 'Edita el texto de los emails automáticos. Las variables entre {{}} serán sustituidas automáticamente.', 'shotfest-votaciones' ); ?></p>

            <form method="post">
                <?php wp_nonce_field( 'sf_guardar_emails', 'sf_emails_nonce' ); ?>

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

    private function guardar(): void {
        foreach ( self::OPTIONS as $option_key => $config ) {
            if ( isset( $_POST[ $option_key ] ) ) {
                update_option( $option_key, sanitize_textarea_field( $_POST[ $option_key ] ) );
            }
        }
    }
}
