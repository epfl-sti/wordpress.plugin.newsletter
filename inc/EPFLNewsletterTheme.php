<?php

/**
 * Like the newsletter plugin's NewsletterThemes, except that there is
 * only our themes.
 *
 * Unlike vanilla The Newsletter Plugin, a EPFL-newsletter compatible
 * newsletter theme can freely choose in which directory they live
 * (for instance, the epfl-sti theme has a subdirectory called
 * newsletter-theme/). In order to register with EPFL-newsletter, the
 * theme should add itself to the "epfl_newsletter_init" action, e.g.
 *
 *  add_action("epfl_newsletter_init", function() {
 *     \EPFL\Newsletter\EPFLNewsletterThemes::register(
 *         "myuniquename",
 *         dirname(__FILE__) . '/theme.php',
 *         array(
 *             "name" => "My sweet newsletter theme"
 *         ));
 *  });
 *
 * EPFLNewsletterTheme.php should only be require_once()'d after
 * ensuring that The Newsletter Plugin is loaded.
 */

namespace EPFL\Newsletter;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

require_once(dirname(__FILE__) . "/TheNewsletterPlugin.php");
use \EPFL\Newsletter\TheNewsletterPlugin;

TheNewsletterPlugin::require_once("includes/themes.php");
use \NewsletterThemes;

class EPFLNewsletterThemes extends NewsletterThemes {

    function __construct()
    {
        parent::__construct('emails');
    }

    private function _themes () {
    }

    function get_all()
    {
        // This is dead code as of version 5.1.1 of The Newsletter Plugin afaict
        $retval = array();
        foreach (self::get_all_with_data() as $id => $data) {
            $retval[$id] = $id;
        }
        return $retval;
    }

    function get_file_path($theme, $file)
    {
        return self::get_all_with_data()[$theme]["basedir"] . DIRECTORY_SEPARATOR . $file;
    }

    function get_theme_url($theme)
    {
        return self::get_all_with_data()[$theme]["baseuri"];
    }

    static private $_themes = null;
    function get_all_with_data()
    {
        if (self::$_themes === null) {
            /**
             * Request registration from EPFL-newsletter compatible themes.
             *
             * The callback should invoke @link
             * \EPFL\Newsletter\EPFLNewsletterThemes::register , which is
             * guaranteed to exist at that time.
             */
            do_action("epfl_newsletter_init");
        }
        return self::$_themes;
    }

    /**
     * Register a newsletter theme.
     *
     * The newsletter theme should be organized in the same way as an
     * "ordinary" theme of The Newsletter Plugin (see @link
     * https://www.thenewsletterplugin.com/documentation/newsletter-themes ),
     * with files such as theme.php, theme-text.php, theme-options.php and
     * (optional) screenshot.png
     *
     * @param $id A unique name
     * @param $theme_file The path to the theme.pp file. If a comment sigil 
     *        is found in $theme_file, the metadata found inside will be
     *        taken into account as if it were part of $theme_options.
     * @param $theme_options An associative array of options for this
     *        newsletter theme.
     * @param $theme_options["name"] The theme's display name
     * @param $theme_options["type"] Same as the same attribute in a theme
     *        of The Newsletter Plugin; default is "standard"
     * @param $theme_options["basedir"] The base directory of the theme;
     *        dirname($theme_file) by default
     * @param $theme_options["baseuri"] The (absolute) base URI of the theme;
     *        figured out automagically by default
     * @param $theme_options["screenshot"] The URI to a thumbnail asset,
     *        suitable for an <img src=""> tag. By default, if a file named
     *        screenshot.png exists in $theme_options["basedir"], it will
     *        be used.
     */
    function register ($id, $theme_file, $theme_options = array())
    {
        $data = array_merge(get_file_data($theme_file, array(
            "name" => "Theme Name",
            "description" => "Description",
            "type" => "Type",
            "screenshot" => "Screenshot",
        )), $theme_options);
        $data["id"] = $id;

        if (empty($data['type'])) {
            $data['type'] = 'standard';
        }
        if (empty($data['basedir'])) {
            $data['basedir'] = dirname($theme_file);
        }
        if (empty($data['baseuri'])) {
            $data['baseuri'] = self::file2uri($data['basedir']);
        }
        $matched = array();
        if (preg_match('#^(.*)/$#', $data['baseuri'])) {
                $data['baseuri'] = $matched[0];
        }

        if (empty($data['screenshot'])) {
            $default_screenshot = dirname($theme_file) . DIRECTORY_SEPARATOR . "screenshot.png";
            if (file_exists($default_screenshot)) {
                $default_screenshot_uri = self::file2uri($default_screenshot);
                if (null !== $default_screenshot_uri) {
                    $data["screenshot"] = $default_screenshot_uri;
                }
            }
        } elseif (! preg_match('#^(/|[a-z]:)#')) {
            $data['screenshot'] = sprintf("%s/%s", 
                                          $data['baseuri'],
                                          $data['screenshot']);
        }
        self::$_themes[$id] = $data;
    }

    /**
     * @return The URI of $file_path, or null if we can't guess.
     */
    private function file2uri ($file_path)
    {
        $matched = array();
        if (preg_match('#[\\/]themes[\\/](.*)$#', $file_path, $matched)) {
            return \get_theme_root_uri() . "/" . str_replace("\\", "/", $matched[1]);
        } elseif (preg_match("#[/\\]plugins[/\\](.*)$#", $file_path, $matched)) {
            return \plugins_url() . "/" . str_replace("\\", "/", $matched[1]);
        } else {
            return null;
        }
    }
}

TheNewsletterPlugin::require_once("emails/emails.php");
\NewsletterEmails::instance()->themes = new EPFLNewsletterThemes();
