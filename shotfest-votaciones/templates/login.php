<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="sf-login-wrap">
    <div class="sf-login-box">
        <div class="sf-login-label"><?php esc_html_e( 'Zona de jurado', 'shotfest-votaciones' ); ?></div>
        <h2 class="sf-login-titulo"><?php esc_html_e( 'Accede para votar', 'shotfest-votaciones' ); ?></h2>
        <?php
        $args = [
            'echo'           => true,
            'redirect'       => get_permalink() ?: home_url( '/' ),
            'label_username' => __( 'Usuario', 'shotfest-votaciones' ),
            'label_password' => __( 'Contraseña', 'shotfest-votaciones' ),
            'label_log_in'   => __( 'Entrar', 'shotfest-votaciones' ),
        ];
        wp_login_form( $args );
        ?>
        <p class="sf-login-ayuda">
            <?php esc_html_e( '¿No tienes credenciales? Contacta con la organización de SHOT.', 'shotfest-votaciones' ); ?>
        </p>
    </div>
</div>
