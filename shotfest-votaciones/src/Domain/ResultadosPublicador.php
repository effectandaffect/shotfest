<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Domain;

class ResultadosPublicador {

    public function publicar( int $periodo_id ): void {
        update_post_meta( $periodo_id, '_sf_periodo_resultados_publicados', '1' );
    }

    public function despublicar( int $periodo_id ): void {
        update_post_meta( $periodo_id, '_sf_periodo_resultados_publicados', '0' );
    }

    public function esta_publicado( int $periodo_id ): bool {
        return '1' === get_post_meta( $periodo_id, '_sf_periodo_resultados_publicados', true );
    }
}
