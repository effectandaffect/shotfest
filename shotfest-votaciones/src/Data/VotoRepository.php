<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Data;

class VotoRepository {

    private function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'shotfest_votos';
    }

    /**
     * Registra un voto. Devuelve true si se insertó, false si ya existía (UNIQUE KEY).
     * Lanza \RuntimeException en caso de error SQL distinto de duplicado.
     */
    public function insertar( int $usuario_id, int $spot_id, int $periodo_id, int $valor, string $ip_hash = '' ): bool {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        $resultado = $wpdb->insert(
            $this->table(),
            [
                'usuario_id' => $usuario_id,
                'spot_id'    => $spot_id,
                'periodo_id' => $periodo_id,
                'valor'      => $valor,
                'ip_hash'    => $ip_hash,
            ],
            [ '%d', '%d', '%d', '%d', '%s' ]
        );

        if ( false === $resultado ) {
            $wpdb->query( 'ROLLBACK' );
            // Error 1062 = Duplicate entry (UNIQUE constraint)
            if ( str_contains( $wpdb->last_error, '1062' ) || str_contains( $wpdb->last_error, 'Duplicate' ) ) {
                return false;
            }
            throw new \RuntimeException( 'Error al registrar voto: ' . $wpdb->last_error );
        }

        $wpdb->query( 'COMMIT' );
        return true;
    }

    public function ya_voto( int $usuario_id, int $spot_id, int $periodo_id = 0 ): bool {
        global $wpdb;

        if ( $periodo_id > 0 ) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table()} WHERE usuario_id = %d AND spot_id = %d AND periodo_id = %d",
                    $usuario_id,
                    $spot_id,
                    $periodo_id
                )
            );
        } else {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table()} WHERE usuario_id = %d AND spot_id = %d",
                    $usuario_id,
                    $spot_id
                )
            );
        }

        return (int) $count > 0;
    }

    /** Devuelve [ spot_id => ['si' => int, 'no' => int], ... ] para un periodo */
    public function conteos_por_spot( int $periodo_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT spot_id, valor, COUNT(*) AS total
                 FROM {$this->table()}
                 WHERE periodo_id = %d
                 GROUP BY spot_id, valor",
                $periodo_id
            ),
            ARRAY_A
        );

        $resultado = [];
        foreach ( $rows as $row ) {
            $sid = (int) $row['spot_id'];
            if ( ! isset( $resultado[ $sid ] ) ) {
                $resultado[ $sid ] = [ 'si' => 0, 'no' => 0 ];
            }
            if ( '1' === $row['valor'] ) {
                $resultado[ $sid ]['si'] = (int) $row['total'];
            } else {
                $resultado[ $sid ]['no'] = (int) $row['total'];
            }
        }

        return $resultado;
    }

    /** Total de miembros del jurado que han votado al menos un spot en el periodo */
    public function total_jurado_que_voto( int $periodo_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT usuario_id) FROM {$this->table()} WHERE periodo_id = %d",
                $periodo_id
            )
        );
    }

    /** Votos de un usuario en un periodo */
    public function votos_usuario( int $usuario_id, int $periodo_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT spot_id, valor FROM {$this->table()} WHERE usuario_id = %d AND periodo_id = %d",
                $usuario_id,
                $periodo_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /** Exportación: todos los votos de un periodo con datos de usuario */
    public function exportar_periodo( int $periodo_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.id, v.usuario_id, u.user_login, u.user_email,
                        v.spot_id, v.valor, v.fecha_voto
                 FROM {$this->table()} v
                 INNER JOIN {$wpdb->users} u ON u.ID = v.usuario_id
                 WHERE v.periodo_id = %d
                 ORDER BY u.user_login, v.spot_id",
                $periodo_id
            ),
            ARRAY_A
        ) ?: [];
    }
}
