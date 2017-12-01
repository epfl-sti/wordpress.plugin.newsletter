<?php
/**
 * Find The Newsletter Plugin, that EPFL-newsletter depends on.
 */

namespace EPFL\Newsletter;

require_once ABSPATH . 'wp-admin/includes/plugin.php';  // For get_plugins()

class TheNewsletterPlugin {
    static private $_path = null;
    static private function _find ()
    {
        if (null === self::$_path) {
            foreach (get_plugins() as $path => $info) {
                if ($info["PluginURI"] ===
                    "https://www.thenewsletterplugin.com/plugins/newsletter") {
                    self::$_path = $path;
                    break;
                }
            }
        }
        return self::$_path;
    }

    static function get_state () {
        $path = self::_find();
        if (! $path) {
            return "UNAVAILABLE";
        }
        if (\is_plugin_active($path)) {
            return "OK";
        }
        return "INACTIVE";
    }

    static function require_once ($path_under_newsletter_plugin) {
        $state = self::get_state();
        if ($state != "OK") {
            wp_die("\\EPFL\\Newsletter\\TheNewsletterPlugin::require_once(\"$path_under_newsletter_plugin\"): cannot do, The Newsletter Plugin is $state");
        }

        $dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname(self::_find());
        // Be careful not to violate The Newsletter Plugin's assumptions about
        // its own loading order:
        require_once("$dir/plugin.php");
        require_once("$dir/" . $path_under_newsletter_plugin);
    }
}
