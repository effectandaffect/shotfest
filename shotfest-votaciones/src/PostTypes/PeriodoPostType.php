<?php
declare(strict_types=1);

namespace ShotfestVotaciones\PostTypes;

class PeriodoPostType {

    const POST_TYPE = 'sf_periodo';

    public function register(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
    }

    public function register_post_type(): void {
        $labels = [
            'name'               => __( 'Periodos de votación', 'shotfest-votaciones' ),
            'singular_name'      => __( 'Periodo', 'shotfest-votaciones' ),
            'add_new'            => __( 'Añadir nuevo', 'shotfest-votaciones' ),
            'add_new_item'       => __( 'Añadir nuevo Periodo', 'shotfest-votaciones' ),
            'edit_item'          => __( 'Editar Periodo', 'shotfest-votaciones' ),
            'new_item'           => __( 'Nuevo Periodo', 'shotfest-votaciones' ),
            'not_found'          => __( 'No se encontraron periodos', 'shotfest-votaciones' ),
            'menu_name'          => __( 'Periodos', 'shotfest-votaciones' ),
        ];

        register_post_type( self::POST_TYPE, [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'shotfest-votaciones',
            'show_in_rest'    => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'capabilities'    => [
                'edit_post'          => 'sf_gestionar_periodos',
                'read_post'          => 'sf_gestionar_periodos',
                'delete_post'        => 'sf_gestionar_periodos',
                'edit_posts'         => 'sf_gestionar_periodos',
                'edit_others_posts'  => 'sf_gestionar_periodos',
                'publish_posts'      => 'sf_gestionar_periodos',
                'read_private_posts' => 'sf_gestionar_periodos',
                'create_posts'       => 'sf_gestionar_periodos',
            ],
            'has_archive'     => false,
            'rewrite'         => false,
            'supports'        => [ 'title' ],
            'menu_icon'       => 'dashicons-calendar-alt',
        ] );
    }

    public function add_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['sf_edicion']    = __( 'Edición', 'shotfest-votaciones' );
                $new['sf_estado_p']   = __( 'Estado', 'shotfest-votaciones' );
                $new['sf_fecha_ini']  = __( 'Inicio', 'shotfest-votaciones' );
                $new['sf_fecha_fin']  = __( 'Fin', 'shotfest-votaciones' );
            }
        }
        return $new;
    }

    public function render_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'sf_edicion':
                echo esc_html( get_post_meta( $post_id, '_sf_periodo_edicion_year', true ) ?: '—' );
                break;
            case 'sf_estado_p':
                echo esc_html( get_post_meta( $post_id, '_sf_periodo_estado', true ) ?: '—' );
                break;
            case 'sf_fecha_ini':
                $d = get_post_meta( $post_id, '_sf_periodo_fecha_inicio', true );
                echo esc_html( $d ? date_i18n( get_option( 'date_format' ), strtotime( $d ) ) : '—' );
                break;
            case 'sf_fecha_fin':
                $d = get_post_meta( $post_id, '_sf_periodo_fecha_fin', true );
                echo esc_html( $d ? date_i18n( get_option( 'date_format' ), strtotime( $d ) ) : '—' );
                break;
        }
    }
}
