<?php
declare(strict_types=1);

namespace ShotfestVotaciones\PostTypes;

use ShotfestVotaciones\Domain\PeriodoService;
use ShotfestVotaciones\PostTypes\PeriodoPostType;

class SpotPostType {

    const POST_TYPE = 'sf_spot';

    public function register(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_filter( 'disable_months_dropdown', [ $this, 'disable_months_dropdown' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ $this, 'render_filtro_periodo' ] );
        add_action( 'pre_get_posts', [ $this, 'aplicar_filtro_periodo' ] );
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
            'map_meta_cap'        => false,
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

    /** Oculta el desplegable nativo de fechas de WP en el listado de Spots */
    public function disable_months_dropdown( bool $disable, string $post_type ): bool {
        return self::POST_TYPE === $post_type ? true : $disable;
    }

    /** Desplegable de filtro por Periodo, sustituyendo al de fechas */
    public function render_filtro_periodo(): void {
        global $typenow;
        if ( self::POST_TYPE !== $typenow ) {
            return;
        }

        $periodos = get_posts( [
            'post_type'      => PeriodoPostType::POST_TYPE,
            'post_status'    => PeriodoService::POST_STATUSES,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $seleccionado = absint( $_GET['sf_filtro_periodo'] ?? 0 );
        ?>
        <select name="sf_filtro_periodo">
            <option value="0"><?php esc_html_e( '— Todos los periodos —', 'shotfest-votaciones' ); ?></option>
            <?php foreach ( $periodos as $p ) : ?>
                <option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $seleccionado, $p->ID ); ?>>
                    <?php echo esc_html( $p->post_title ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /** Aplica el filtro de Periodo seleccionado a la consulta del listado de Spots */
    public function aplicar_filtro_periodo( \WP_Query $query ): void {
        global $pagenow, $typenow;
        if ( ! is_admin() || 'edit.php' !== $pagenow || self::POST_TYPE !== $typenow || ! $query->is_main_query() ) {
            return;
        }

        $periodo_id = absint( $_GET['sf_filtro_periodo'] ?? 0 );
        if ( ! $periodo_id ) {
            return;
        }

        $meta_query   = (array) $query->get( 'meta_query' );
        $meta_query[] = [
            'key'   => '_sf_spot_periodo_id',
            'value' => $periodo_id,
        ];
        $query->set( 'meta_query', $meta_query );
    }
}
