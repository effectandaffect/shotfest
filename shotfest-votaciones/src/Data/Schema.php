<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Data;

class Schema {

    const DB_VERSION = '1.1';
    const OPTION_KEY = 'sf_db_version';

    public static function create_tables(): void {
        global $wpdb;

        $table   = $wpdb->prefix . 'shotfest_votos';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id  BIGINT(20) UNSIGNED NOT NULL,
            spot_id     BIGINT(20) UNSIGNED NOT NULL,
            periodo_id  BIGINT(20) UNSIGNED NOT NULL,
            valor       TINYINT(1)          NOT NULL COMMENT '1=Si, 0=No',
            fecha_voto  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_hash     VARCHAR(64)                  DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_usuario_spot_periodo (usuario_id, spot_id, periodo_id),
            KEY idx_periodo (periodo_id),
            KEY idx_spot    (spot_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::OPTION_KEY, self::DB_VERSION );
    }

    public static function needs_upgrade(): bool {
        return get_option( self::OPTION_KEY ) !== self::DB_VERSION;
    }
}
