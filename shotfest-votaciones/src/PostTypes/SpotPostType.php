<?php
declare(strict_types=1);

namespace ShotfestVotaciones\PostTypes;

class SpotPostType {

    const POST_TYPE = 'sf_spot';

    public function register(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
    }

    public function register_post_type(): void {
        $labels = [
            'name'               => __( 'Spots', 'shotfest-votaciones' ),
            'singular_name'      => __( 'Spot', 'shotfest-votaciones' ),
            'add_new'            => __( 'Añadir nuevo', 'shotfest-votaciones' ),
            'add_new_item'       => __( 'Añadir nuevo Spot', 'shotfest-votaciones' ),
            'edit_item'          => __( 'Editar Spot', 'shotfest-votaciones' ),
            'new_item'           => __( 'Nuevo Spot', 'shotfest-votaciones' ),
            'view_item'          => __( 'Ver Spot', 'shotfest-votaciones' ),
            'search_items'       => __( 'Buscar Spots', 'shotfest-votaciones' ),
            'not_found'          => __( 'No se encontraron spots', 'shotfest-votaciones' ),
            'not_found_in_trash' => __( 'No hay spots en la papelera', 'shotfest-votaciones' ),
            'menu_name'          => __( 'Spots', 'shotfest-votaciones' ),
        ];

        register_post_type( self::POST_TYPE, [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'shotfest-votaciones',
            'show_in_rest'        => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'capabilities'        => [
                'edit_post'          => 'sf_gestionar_spots',
                'read_post'          => 'sf_gestionar_spots',
                'delete_post'        => 'sf_gestionar_spots',
                'edit_posts'         => 'sf_gestionar_spots',
                'edit_others_posts'  => 'sf_gestionar_spots',
                'publish_posts'      => 'sf_gestionar_spots',
                'read_private_posts' => 'sf_gestionar_spots',
                'create_posts'       => 'sf_gestionar_spots',
            ],
            'has_archive'         => false,
            'rewrite'             => false,
            'supports'            => [ 'title', 'thumbnail' ],
            'menu_icon'           => 'dashicons-video-alt3',
        ] );
    }

    public function add_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['sf_marca']   = __( 'Marca', 'shotfest-votaciones' );
                $new['sf_estado']  = __( 'Estado', 'shotfest-votaciones' );
                $new['sf_periodo'] = __( 'Periodo', 'shotfest-votaciones' );
            }
        }
        return $new;
    }

    public function render_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'sf_marca':
                echo esc_html( get_post_meta( $post_id, '_sf_spot_marca', true ) );
                break;
            case 'sf_estado':
                $estado = get_post_meta( $post_id, '_sf_spot_estado', true );
                echo esc_html( $estado ?: '—' );
                break;
            case 'sf_periodo':
                $periodo_id = get_post_meta( $post_id, '_sf_spot_periodo_id', true );
                if ( $periodo_id ) {
                    echo esc_html( get_the_title( (int) $periodo_id ) );
                } else {
                    echo '—';
                }
                break;
        }
    }
}
