<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="sf-login-wrap">
    <div class="sf-login-box">
        <p class="sf-eyebrow"><?php esc_html_e( 'Zona de jurado', 'shotfest-votaciones' ); ?></p>
        <h2 class="sf-titulo"><?php esc_html_e( 'Accede para votar', 'shotfest-votaciones' ); ?></h2>
        <?php
        wp_login_form( [
            'echo'           => true,
            'redirect'       => get_permalink() ?: home_url( '/' ),
            'label_username' => __( 'Usuario', 'shotfest-votaciones' ),
            'label_password' => __( 'Contraseña', 'shotfest-votaciones' ),
            'label_log_in'   => __( 'Entrar', 'shotfest-votaciones' ),
            'label_remember' => __( 'Mantener la sesión iniciada', 'shotfest-votaciones' ),
        ] );
        ?>
        <p class="sf-login-ayuda">
            <?php esc_html_e( '¿Has perdido el acceso? Escribe a la organización de SHOT y te enviamos un enlace nuevo.', 'shotfest-votaciones' ); ?>
        </p>
    </div>
</div>
