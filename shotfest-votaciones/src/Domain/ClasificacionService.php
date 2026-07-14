<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Domain;

use ShotfestVotaciones\Data\VotoRepository;
use ShotfestVotaciones\Taxonomies\CategoriaTaxonomy;

class ClasificacionService {

    public function __construct(
        private readonly VotoRepository $repo
    ) {}

    /**
     * Devuelve la clasificación completa de un periodo, agrupada por categoría.
     *
     * Estructura devuelta:
     * [
     *   categoria_id => [
     *     'categoria' => WP_Term,
     *     'spots'     => [
     *       [
     *         'post'      => WP_Post,
     *         'si'        => int,
     *         'no'        => int,
     *         'posicion'  => int,
     *         'shortlist' => bool,
     *       ],
     *       ...
     *     ]
     *   ]
     * ]
     */
    public function clasificacion_por_periodo( int $periodo_id, array $spots ): array {
        $conteos = $this->repo->conteos_por_spot( $periodo_id );

        // Agrupar spots por categoría
        $por_categoria = [];
        foreach ( $spots as $spot ) {
            $categorias = wp_get_post_terms( $spot->ID, CategoriaTaxonomy::TAXONOMY );
            if ( is_wp_error( $categorias ) || empty( $categorias ) ) {
                $categorias = [];
            }
            // Un spot puede pertenecer a varias categorías
            foreach ( $categorias as $cat ) {
                $por_categoria[ $cat->term_id ]['categoria'] = $cat;
                $por_categoria[ $cat->term_id ]['spots'][]   = $spot;
            }
        }

        // Calcular posición y shortlist dentro de cada categoría
        $resultado = [];
        foreach ( $por_categoria as $cat_id => $data ) {
            $spots_con_votos = [];
            foreach ( $data['spots'] as $spot ) {
                $si = $conteos[ $spot->ID ]['si'] ?? 0;
                $no = $conteos[ $spot->ID ]['no'] ?? 0;
                $spots_con_votos[] = [
                    'post' => $spot,
                    'si'   => $si,
                    'no'   => $no,
                ];
            }

            // Ordenar por votos Sí desc, luego por votos No asc
            usort( $spots_con_votos, static fn( $a, $b ) => $b['si'] <=> $a['si'] ?: $a['no'] <=> $b['no'] );

            // Asignar posición y shortlist (empates: todos con el máximo de Sí pasan)
            $max_si   = $spots_con_votos[0]['si'] ?? 0;
            $posicion = 0;
            $prev_si  = null;

            foreach ( $spots_con_votos as &$entry ) {
                if ( $entry['si'] !== $prev_si ) {
                    $posicion++;
                    $prev_si = $entry['si'];
                }
                $entry['posicion']  = $posicion;
                $entry['shortlist'] = $max_si > 0 && $entry['si'] === $max_si;
            }
            unset( $entry );

            $resultado[ $cat_id ] = [
                'categoria' => $data['categoria'],
                'spots'     => $spots_con_votos,
            ];
        }

        return $resultado;
    }
}
