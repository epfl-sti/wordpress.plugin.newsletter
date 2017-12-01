<?php
/*
 * Plugin Name: EPFL Newsletter
 * Plugin URI:  https://github.com/epfl-sti/wordpress.plugin.newsletter
 * Description: EPFL-specific enhancements to <a href="https://www.thenewsletterplugin.com/">the newsletter plugin</a>
 * Version:     0.1
 * Author:      STI-IT Web
 * Author URI:  mailto:stiitweb@groupes.epfl.ch
 * License:     MIT License / Copyright (c) 2017 EPFL ⋅ STI ⋅ IT
 *
 */

namespace EPFL\Newsletter;

require_once(dirname(__FILE__) . "/inc/i18n.php");

class NewsletterConfig
{
    static function hook ()
    {
        add_action( 'admin_init', array(get_called_class(), 'require_the_newsletter_plugin' ));
    }

    /**
     * Check that The Newsletter Plugin is available and activated.
     *
     * If not, deactivate ourselves and set up suitable red or green
     * boxes at the top of the Extensions page to explain why.
     */
    static function require_the_newsletter_plugin ()
    {
        $the_newsletter_plugin = self::get_newsletter_plugin();
        if (! $the_newsletter_plugin) {
            self::add_admin_notice('error_need_newsletter_plugin');
        } elseif (! is_plugin_active($the_newsletter_plugin)) {
            // Assumption #1: EPFL-newsletter is active (otherwise
            // this code wouldn't run)
            // Assumption #2: we just entered this illegal state
            // (otherwise we would have deactivated ourselves
            // earlier). Therefore, either The Newsletter Plugin was
            // just activated, or this plugin was just deactivated.
            // Since we land here after a redirect, all PHP-side state
            // is gone and the only clue that survives is
            // $_GET["deactivate"]:
            self::add_admin_notice(
                $_GET["deactivate"] ?
                'notice_newsletter_plugin_deactivated_must_deactivate' :
                'error_newsletter_plugin_deactivated_cannot_activate');
        } else {
            return;  // All good
        }
        // In all other cases:
        deactivate_plugins( plugin_basename( __FILE__ ) ); 
        unset($_GET['activate']);  // No green success message
    }

    static function get_newsletter_plugin ()
    {
        foreach (get_plugins() as $path => $info) {
            if ($info["PluginURI"] ===
                "https://www.thenewsletterplugin.com/plugins/newsletter") {
                return $path;
            }
        }
        return null;
    }

    static function add_admin_notice($methodname) {
        add_action('admin_notices', array(get_called_class(), $methodname));
    }

    static function error_need_newsletter_plugin ()
    {
        self::error_box(
            ___( 'EPFL-newsletter activation failed' ),
            ___( 'EPFL-newsletter requires <a href="https://www.thenewsletterplugin.com/">the newsletter plugin</a> to be installed and activated.' ));
    }

    static function error_newsletter_plugin_deactivated_cannot_activate ()
    {
        self::error_box(
            ___( 'EPFL-newsletter activation failed' ),
            ___('Please activate The Newsletter Plugin first.'));
    }

    static function notice_newsletter_plugin_deactivated_must_deactivate ()
    {
        echo "<div class=\"updated notice is-dismissible\"><p>\n";
        echo ___("Automatically deactivated EPFL-newsletter");
        echo "\n</p></div>\n";
    }

    static function error_box($title, $text) {
        echo "<div class=\"error\">\n";
        echo "<h1>$title</h1>\n";
        echo "<p>\n";
        echo $text;
        echo "\n</p></div>\n";

    }
}

NewsletterConfig::hook();

?>
