<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="sf-login-wrap">
    <div class="sf-login-box">
        <h2 class="sf-login-titulo"><?php esc_html_e( 'Acceso al jurado ShotFest', 'shotfest-votaciones' ); ?></h2>
        <?php
        $args = [
            'echo'           => true,
            'redirect'       => get_permalink() ?: home_url( '/' ),
            'label_username' => __( 'Usuario o email', 'shotfest-votaciones' ),
            'label_password' => __( 'Contraseña', 'shotfest-votaciones' ),
            'label_log_in'   => __( 'Acceder', 'shotfest-votaciones' ),
        ];
        wp_login_form( $args );
        ?>
        <p class="sf-login-ayuda">
            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
                <?php esc_html_e( '¿Olvidaste tu contraseña?', 'shotfest-votaciones' ); ?>
            </a>
        </p>
    </div>
</div>
