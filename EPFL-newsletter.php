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
        register_activation_hook(__FILE__,
                                 array(get_called_class(), 'activation_hook' ) );
    }

    function activation_hook ()
    {
        wp_die( ___( 'EPFL-newsletter requires <a href="https://www.thenewsletterplugin.com/">the newsletter plugin</a> to be installed and activated.' ), __x( 'Error', 'die'), array( 'back_link' => true ) );
    }
}

NewsletterConfig::hook();

?>
