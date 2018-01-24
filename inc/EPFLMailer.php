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
        if (is_array($message)) {
            $html_message = $message["html"];
            $text_message = $message["text"];
        } else {
            $html_message = $message;
            $text_message = $message;
        }
        $phpmailer->msgHTML(
            $this->massage_html($html_message),
            ABSPATH,
            function () use ($text_message) { return $text_message; }
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
     * Download all images, so that PHPMailer::msgHTML
     * can embed them.
     */
    function massage_html ($html) {
        @file_put_contents(WP_CONTENT_DIR . "/newsletter.html", $html);  // XXX
         $doc = new \DOMDocument();

         /* Unless told otherwise, DOMDocument::loadHTML() assumes
          * iso-8859-1 (https://stackoverflow.com/a/8218649/435004).
          * Rather than trusting WordPress' HTML editors (of which
          * there are no less than three) to preserve a <meta
          * charset="UTF-8"> at the beginning of the string, inject or
          * update an XML prolog to clue DOMDocument in. */
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
         // '; # ";  #emacswhatever

         foreach ($doc->getElementsByTagName("img") as $img) {
             if ($img->getAttribute("height") == 1 &&
                 $img->getAttribute("width") == 1) {
                 // Shoot the tracker pixel - For Gmail's sake we want
                 // zero external images, so as to sidestep
                 // https://github.com/epfl-sti/wordpress.plugin.newsletter/issues/2
                 $img->parentNode->removeChild($img);
                 continue;
             }
             $src = $img->getAttribute("src");
             if (preg_match("#^https?:#", $src)) {
                 try {
                     $img->setAttribute("src", ImageCache::get($src)->as_relative_url());
                 } catch (ImageCacheException $e) {
                     static $has_complained_already = array();
                     if (! $has_complained_already[$src]) {
                         error_log("ImageCacheException for $src: $e");
                         $has_complained_already[$src] = true;
                     }
                 }
             }
         }
         return $doc->saveHTML();
    }
}

class ImageCacheException extends \Exception { }

/**
 * A cache of downloaded images that go into a newsletter
 */
class ImageCache {
    static function get ($url) {
        $thisclass = get_called_class();
        return new $thisclass($url);
    }

    private function __construct ($url)
    {
        $this->url = $url;

        if (! $this->_find_on_disk()) {
            $this->_save_to_disk($this->_fetch());
        }
    }

    private function _stem ()
    {
        return hash("sha256", $this->url);
    }

    private function _cache_dir ()
    {
        if (! $this->_cache_dir) {
            $cache_dir = WP_CONTENT_DIR . "/cache/epfl-newsletter-images";
            if (file_exists($cache_dir)) {
                if (! is_dir($cache_dir)) {
                    throw new ImageCacheException("$cache_dir is not a directory");
                }
            } else {
                if (! @mkdir($cache_dir, 0777, /* $recursive = */ true)) {
                    throw new ImageCacheException("Cannot mkdir(\"$cache_dir\"): " . error_get_last());
                }
            }
            $this->_cache_dir = $cache_dir;
        }
        return $this->_cache_dir;
    }

    const IMAGE_EXTS = array(
        IMG_PNG  => "png",
        IMG_GIF  => "gif",
        IMG_JPG  => "jpg",
        IMG_PNG  => "png",
        IMG_WBMP => "bmp",
        IMG_XPM  => "xpm",
        IMG_WEBP => "webp"
    );

    private function _get_known_extensions () {
        $image_types_bitmap = \imagetypes();
        $extensions = array();
        
        foreach (static::IMAGE_EXTS as $bit => $ext) {
            if ($image_types_bitmap & $bit) {
                array_push($extensions, $ext);
            }
        }
        return $extensions;
    }

    private function _find_on_disk ()
    {
        if (! $this->_path_on_disk) {
            foreach ($this::_get_known_extensions() as $ext) {
                $candidate_path = $this->_make_path_on_disk($ext);
                if (! file_exists($candidate_path)) { continue; }

                $this->_path_on_disk = $candidate_path; break;
            }
        }
        return $this->_path_on_disk;
    }

    private function _make_path_on_disk ($ext = null) {
        $stem = sprintf("%s/%s",$this->_cache_dir(), $this->_stem());
        if ($ext) {
            return "$stem.$ext";
        } else {
            return $stem;
        }
    }

    private function _save_to_disk ($blob) {
        // https://bugs.php.net/bug.php?id=65187, #lesigh
        $filename_sans_ext = $this->_make_path_on_disk();
        if (! @file_put_contents($filename_sans_ext, $blob)) {
            throw new ImageCacheException("Cannot create $filename_sans_ext: " . error_get_last());
        }

        $exif_image_type = exif_imagetype($filename_sans_ext);
        if (false === $exif_image_type) {
            // I'm not even sure whether error_get_last() is the proper
            // channel here, #relesigh
            throw new ImageCacheException("Unable to decode: " . error_get_last());
        }
        $ext = static::IMAGE_EXTS[$exif_image_type];
        if (! $ext) {
            throw new ImageCacheException("Unknown EXIF type: $exif_image_type");
        }
        $full_filename = $this->_make_path_on_disk($ext);
        if (! @rename($filename_sans_ext, $full_filename)) {
            throw new ImageCacheException("Cannot rename $filename_sans_ext to $full_filename: " . error_get_last());
        }

        $this->_path_on_disk = $full_filename;
        return $full_filename;
    }

    private function _fetch () {
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($data === false) {
            throw new ImageCacheException("Unable to fetch $url: " . $error);
        }

        return $data;
    }

    function as_relative_url() {
        return sprintf("wp-content/cache/epfl-newsletter-images/%s",
                       basename($this->_find_on_disk()));
    }
}

require_once(dirname(__FILE__) . "/TheNewsletterPlugin.php");

TheNewsletterPlugin::require_once("plugin.php");
\Newsletter::instance()->register_mailer(new EPFLMailer());
