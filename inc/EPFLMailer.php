<?php

/**
 * A no-frills, yes-it-does-multipart implementation of the
 * ->register_mailer() entry point of the Newsletter plugin.
 *
 * This file should only be require_once()'d after ensuring that The
 * Newsletter Plugin is loaded.
 */

namespace EPFL\Newsletter;
require_once ABSPATH . WPINC . '/class-phpmailer.php';
require_once ABSPATH . WPINC . '/class-smtp.php';

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

class EPFLMailer
{
    /**
     * @return A PHPMailer instance all configured from the relevant
     * settings in the newsletter plugin
     */
    function phpmailer ()
    {
        if (! $this->_phpmailer) {
            $that = \Newsletter::instance();
            $that->mailer_init();
            $this->_phpmailer = $that->mailer;
        }
        return $this->_phpmailer;
    }

    function mail($to, $subject, $message, $headers = null, $enqueue = false)
    {
        $phpmailer = $this->phpmailer();
        $phpmailer->ClearAddresses();      // $phpmailer might be re-used
        $phpmailer->AddAddress($to);
        $phpmailer->Subject = $subject;
        $phpmailer->ClearCustomHeaders();  // Same remark
        foreach ($this->massage_headers($headers) as $key => $value) {
            $phpmailer->AddCustomHeader($key . ': ' . $value);
        }

        // After the dust settles, doing the
        // multipart/{alternate,mixed} dance as best explained in
        // https://stackoverflow.com/a/3984262/435004 is as simple as
        // this. Yet there is not a single grep hit for "msgHTML" in
        // the sources of that much-heralded, supposedly best-of-breed
        // newsletter plugin.
        $phpmailer->msgHTML(
            $this->massage_html($message["html"]),
            ABSPATH,
            function () use ($message) { return $message["text"]; }
        );

        $phpmailer->Send();
    }

    function flush() {
        // We aren't queueing (for now), so there is no need to flush
    }

    function massage_headers($headers) {
        if (!$headers) {
            $headers = array();
        }
        // No autoresponders: MS edition
        // (https://en.wikipedia.org/wiki/Email_loop#Prevention)
        if (! $headers['X-Auto-Response-Suppress']) {
            $headers['X-Auto-Response-Suppress'] = 'OOF, AutoReply';
        }
        // No autoresponders: RFC3834 edition
        if (! $headers['Auto-Submitted']) {
            $headers['Auto-Submitted'] = 'auto-generated';
        }
        return $headers;
    }

    /**
     * Make image links relative again, so that PHPMailer::msgHTML
     * can embed the images.
     */
    /* It is just simpler and more robust to post-process the img
     * links in this way, rather than to engage in a crusade to teach
     * HTML editors in use with the newsletter plugin (of which there
     * is no less than three) how the Web actually works.
     */
    function massage_html ($html) {
         $doc = new \DOMDocument();

         // Unless told otherwise, DOMDocument::loadHTML() assumes
         // iso-8859-1 (https://stackoverflow.com/a/8218649/435004).
         // Again, rather than trusting the HTML editors to preserve a
         // <meta charset="UTF-8"> at the beginning of the string,
         // inject or update an XML prolog to clue DOMDocument in.
         $xml_prolog = array();
         preg_match("/^(<\?xml.*?\?>)/s", trim($html), $xml_prolog);
         $html = preg_replace("/^(<\?xml.*?\?>)/s", "", trim($html));
         if (! $xml_prolog[0]) {
             $html = '<?xml encoding="utf-8" ?>' . $html;
         } elseif (preg_match("/encoding=/i", $xml_prolog[0])) {
             // Straight out disbelieve it.
             $html = preg_replace("/encoding=['\"].*?['\"]/i",
                                  "encoding=\"utf-8\"", $xml_prolog) . $html;
         } else {
             $html = preg_replace('/\?>$', $xml_prolog,
                                  "encoding=\"utf-8\"?>") . $html;
         }

         @$doc->loadHTML($html);
         $base = get_home_url();
         if (! preg_match('/\/$/', $base)) {
             $base = $base . "/";
         }
         foreach ($doc->getElementsByTagName("img") as $img) {
             $src = $img->getAttribute("src");
             if (0 === strpos($src, $base)) {
                 $src = substr($src, strlen($base));
                 $img->setAttribute("src", $src);
             }
         }
         return $doc->saveHTML();
    }
}

require_once(dirname(__FILE__) . "/TheNewsletterPlugin.php");

TheNewsletterPlugin::require_once("plugin.php");
\Newsletter::instance()->register_mailer(new EPFLMailer());
