<?php
if (!defined('ABSPATH')) exit;

class Langa_Tools_Client_Command_Runner {
    public static function run($command) {
        if ($command['command_type'] === 'enable_module') {
            self::enable_module($command['payload']);
        }

        if ($command['command_type'] === 'disable_module') {
            self::disable_module($command['payload']);
        }
    }

    private static function enable_module($module) {
        $enabled = get_option(LANGA_TOOLS_OPTION_ENABLED_MODULES, []);
        if (!in_array($module, $enabled)) {
            $enabled[] = $module;
            update_option(LANGA_TOOLS_OPTION_ENABLED_MODULES, $enabled);
        }
    }

    private static function disable_module($module) {
        $enabled = get_option(LANGA_TOOLS_OPTION_ENABLED_MODULES, []);
        update_option(
            LANGA_TOOLS_OPTION_ENABLED_MODULES,
            array_diff($enabled, [$module])
        );
    }
}
