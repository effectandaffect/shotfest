<?php
declare(strict_types=1);

namespace ShotfestVotaciones\Activation;

use ShotfestVotaciones\Data\Schema;
use ShotfestVotaciones\Roles\JuradoRole;
use ShotfestVotaciones\Roles\CapabilitiesManager;

class Activator {

    public static function activate(): void {
        Schema::create_tables();
        JuradoRole::add();
        CapabilitiesManager::add_to_administrator();
        flush_rewrite_rules();
    }
}
