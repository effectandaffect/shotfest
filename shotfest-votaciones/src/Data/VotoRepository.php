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
     *
     * Se usa INSERT IGNORE en lugar de $wpdb->insert(): la detección de duplicado
     * dependía de buscar la palabra «Duplicate» en el mensaje de error de MySQL
     * (last_error contiene el texto, no el código 1062), así que con un servidor de
     * mensajes localizados el segundo voto acababa como «Error interno» en vez de
     * «Ya has votado». La transacción envolvía un único INSERT, no aportaba nada, y
     * en una tabla MyISAM no habría hecho nada en absoluto.
     */
    public function insertar( int $usuario_id, int $spot_id, int $periodo_id, int $valor, string $ip_hash = '' ): bool {
        global $wpdb;

        $insertadas = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$this->table()}
                    (usuario_id, spot_id, periodo_id, valor, ip_hash)
                 VALUES (%d, %d, %d, %d, %s)",
                $usuario_id,
                $spot_id,
                $periodo_id,
                $valor,
                $ip_hash
            )
        );

        if ( false === $insertadas ) {
            throw new \RuntimeException( 'Error al registrar voto: ' . $wpdb->last_error );
        }

        if ( $insertadas > 0 ) {
            return true;
        }

        // 0 filas: lo esperable es que la UNIQUE KEY haya frenado un doble voto. Se
        // confirma, porque INSERT IGNORE también degrada otros errores a warning y no
        // queremos contar un fallo real como «ya habías votado».
        if ( $this->ya_voto( $usuario_id, $spot_id, $periodo_id ) ) {
            return false;
        }

        throw new \RuntimeException( 'El voto no se registró y no consta como duplicado: ' . $wpdb->last_error );
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
            // Cast explícito: get_results() devuelve las columnas numéricas como cadena,
            // pero eso depende de la configuración del driver y no conviene asumirlo.
            if ( 1 === (int) $row['valor'] ) {
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

    /**
     * IDs de los usuarios que han votado al menos un spot en el periodo.
     *
     * Se necesita la lista y no solo el total porque el recuento puede incluir
     * usuarios ya eliminados: para saber si «ha votado todo el jurado» hay que cruzar
     * con los miembros actuales, no comparar dos números de poblaciones distintas.
     *
     * @return int[]
     */
    public function usuarios_que_votaron( int $periodo_id ): array {
        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT usuario_id FROM {$this->table()} WHERE periodo_id = %d",
                $periodo_id
            )
        );

        return array_map( 'intval', $ids ?: [] );
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

    /**
     * Exportación: todos los votos de un periodo con datos de usuario.
     *
     * LEFT JOIN, no INNER JOIN: wp_delete_user() no borra las filas de esta tabla, así
     * que los votos de un miembro dado de baja se quedan huérfanos. Con INNER JOIN
     * desaparecían de este export mientras seguían contando en la clasificación (que no
     * cruza con usuarios), de modo que el ranking que reparte los premios y el CSV que
     * sirve de traza no cuadraban. `user_login`/`user_email` llegan a null en ese caso
     * y quien consume el método decide cómo etiquetarlos.
     */
    public function exportar_periodo( int $periodo_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.id, v.usuario_id, u.user_login, u.user_email,
                        v.spot_id, v.valor, v.fecha_voto
                 FROM {$this->table()} v
                 LEFT JOIN {$wpdb->users} u ON u.ID = v.usuario_id
                 WHERE v.periodo_id = %d
                 ORDER BY COALESCE(u.user_login, ''), v.usuario_id, v.spot_id",
                $periodo_id
            ),
            ARRAY_A
        ) ?: [];
    }
}
