<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Activation;

use ShotfestVotaciones\Cron\RecordatorioScheduler;

class Deactivator {

    public static function deactivate(): void {
        RecordatorioScheduler::clear_scheduled_events();
        flush_rewrite_rules();
    }
}
