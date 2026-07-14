<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Taxonomies;

use ShotfestVotaciones\PostTypes\SpotPostType;

class CategoriaTaxonomy {

    const TAXONOMY = 'spot_categoria';

    public function register(): void {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( self::TAXONOMY . '_add_form_fields', [ $this, 'add_term_fields' ] );
        add_action( self::TAXONOMY . '_edit_form_fields', [ $this, 'edit_term_fields' ] );
        add_action( 'created_' . self::TAXONOMY, [ $this, 'save_term_meta' ] );
        add_action( 'edited_' . self::TAXONOMY, [ $this, 'save_term_meta' ] );
    }

    public function register_taxonomy(): void {
        $labels = [
            'name'              => __( 'Categorías', 'shotfest-votaciones' ),
            'singular_name'     => __( 'Categoría', 'shotfest-votaciones' ),
            'search_items'      => __( 'Buscar categorías', 'shotfest-votaciones' ),
            'all_items'         => __( 'Todas las categorías', 'shotfest-votaciones' ),
            'edit_item'         => __( 'Editar categoría', 'shotfest-votaciones' ),
            'update_item'       => __( 'Actualizar categoría', 'shotfest-votaciones' ),
            'add_new_item'      => __( 'Añadir nueva categoría', 'shotfest-votaciones' ),
            'new_item_name'     => __( 'Nombre de la nueva categoría', 'shotfest-votaciones' ),
            'menu_name'         => __( 'Categorías', 'shotfest-votaciones' ),
        ];

        register_taxonomy( self::TAXONOMY, [ SpotPostType::POST_TYPE ], [
            'labels'            => $labels,
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => false,
            'hierarchical'      => true,
            'rewrite'           => false,
            'capabilities'      => [
                'manage_terms' => 'sf_gestionar_spots',
                'edit_terms'   => 'sf_gestionar_spots',
                'delete_terms' => 'sf_gestionar_spots',
                'assign_terms' => 'sf_gestionar_spots',
            ],
        ] );
    }

    public function add_term_fields(): void {
        ?>
        <div class="form-field">
            <label for="sf_categoria_activa"><?php esc_html_e( 'Estado', 'shotfest-votaciones' ); ?></label>
            <select name="sf_categoria_activa" id="sf_categoria_activa">
                <option value="1"><?php esc_html_e( 'Activa', 'shotfest-votaciones' ); ?></option>
                <option value="0"><?php esc_html_e( 'Inactiva', 'shotfest-votaciones' ); ?></option>
            </select>
        </div>
        <?php
    }

    public function edit_term_fields( \WP_Term $term ): void {
        $activa = get_term_meta( $term->term_id, 'sf_categoria_activa', true );
        $activa = '' === $activa ? '1' : $activa;
        ?>
        <tr class="form-field">
            <th><label for="sf_categoria_activa"><?php esc_html_e( 'Estado', 'shotfest-votaciones' ); ?></label></th>
            <td>
                <select name="sf_categoria_activa" id="sf_categoria_activa">
                    <option value="1" <?php selected( $activa, '1' ); ?>><?php esc_html_e( 'Activa', 'shotfest-votaciones' ); ?></option>
                    <option value="0" <?php selected( $activa, '0' ); ?>><?php esc_html_e( 'Inactiva', 'shotfest-votaciones' ); ?></option>
                </select>
            </td>
        </tr>
        <?php
    }

    public function save_term_meta( int $term_id ): void {
        if ( isset( $_POST['sf_categoria_activa'] ) ) {
            update_term_meta(
                $term_id,
                'sf_categoria_activa',
                sanitize_text_field( $_POST['sf_categoria_activa'] )
            );
        }
    }
}
