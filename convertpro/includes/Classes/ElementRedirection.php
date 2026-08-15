<?php

namespace ConvertPro\Classes;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


use KubAT\PhpSimple\HtmlDomParser;
use simplehtmldom;
use simplehtmldom\HtmlNode;
use simplehtmldom\HtmlWeb;

class ElementRedirection
{

    private $html_parser;

    function __construct()
    {
        if (is_admin()) {
            return false;
        }
        // add_action('wp', [$this, 'autoSelectElement'], 1);
        // add_action('wp_enqueue_scripts', [$this, 'addElementStyles']);

        add_action('init', array($this, 'start_buffer'), PHP_INT_MAX);
        add_action('template_redirect', [$this, 'update_conversion']);




        // $this->html_parser = new HtmlWeb();

    }

    public function start_buffer()
    {
        ob_start(array($this, 'manipulate_html'));

        // var_dump(ob_get_clean());
    }

    public function manipulate_html($buffer)
    {
        // Load the buffer into the object

        global $wpdb;
        $dom = HtmlDomParser::str_get_html($buffer);

        // The parser returns false for output it cannot handle — an empty buffer
        // on shutdown, for one — and calling find() on that is a fatal error.
        if (!$dom) {
            return $buffer;
        }

        $styles = '';
        $active_class = '';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type='elements' AND active = 1");
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $allVariations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro_variations where class_name != '' ");
        $output = '';
        foreach ($results as $value) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            $variations = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}convertpro_variations WHERE splittest_id = %d",
                    $value->id
                )
            );

            // Check if any variation matches the cookie value
            $cookieName = convertpro_test_cookie_name('convert_pro_elm_test_', $value->id);
            $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash(($_COOKIE[$cookieName]))) : '';




            // $this->autoSelectElement();
            foreach ($variations as $variation) {
                if (empty($active_class)) {

                    if ($dom->find('.' . $variation->class_name)) {
                        $this->autoSelectElement($value->id);
                    }
                }

                $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';
                // $output .= $dom->find('body', 0)->innertext = 'hellow' . $variation->class_name;


                if (!empty($active_class) && $variation->class_name != $active_class) {
                    // var_dump($variation->class_name);
                    foreach ($dom->find('.' . $variation->class_name) as $element) {

                        $element->remove();
                    }
                }
            }
        }



        // Manipulate the HTML here
        // For example, let's change all <h1> tags to <h2>




        return $dom->save();
        // return $output;

    }

    public function update_conversion()
    {
        // The page WordPress resolved for this request. Comparing IDs keeps query
        // parameters and endpoints from mattering, and stops a 404 counting as a
        // conversion — get_the_permalink() with nothing in the loop falls back to
        // whichever post comes first.
        $current_page_id = get_queried_object_id();

        if (!$current_page_id) {
            return;
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'elements' AND active = 1");



        foreach ($results as $value) {

            // Click goals are handled by the front-end tracker; skipping them
            // also avoids get_the_permalink(0) matching the current page and
            // counting every visit as a conversion.
            if ('click' === $value->conversion_type || empty($value->conversion_page_id)) {
                continue;
            }

            // Nothing to do unless this is the goal page. Checked before the
            // variation query so an ordinary page view costs no queries at all.
            if ((int) $value->conversion_page_id !== $current_page_id) {
                continue;
            }

            $run = convertpro_get_test_run($value->id);
            $cookieName = convertpro_test_cookie_name('convert_pro_elm_test_', $value->id);
            $variationCookie = convertpro_test_cookie_name('convert_pro_elm_variation_id_', $value->id);
            $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

            $variations = $this->convertpro_ele_query($value->id); // Fetch all variations for the current test
            foreach ($variations as $variation) {
                $user_variation_id = isset($_COOKIE[$variationCookie]) ? sanitize_text_field(wp_unslash($_COOKIE[$variationCookie])) : '';
                $client_id = isset($_COOKIE['convert_pro_uid']) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid'])) : '';

                if ($user_variation_id == $variation->id && !empty($active_class)) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update(
                        $wpdb->prefix . 'convertpro_interactions',
                        array('type' => 'conversion'),
                        array('variation_id' => $variation->id, 'splittest_id' => $value->id, 'client_id' => $client_id, 'run' => $run)
                    );
                }
            }
        }
    }
    public function autoSelectElement($testId)
    {
        global $wpdb;

        /*         $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'elements' AND active = 1");


                foreach ($results as $value) {
                */
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $variations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}convertpro_variations WHERE splittest_id = %d AND class_name != ''",
                $testId
            )
        );

        if (empty($variations)) {
            return;
        }
        $cookieName = convertpro_test_cookie_name('convert_pro_elm_test_', $testId);
        $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';



        if (empty($active_class)) {

            // checking if all remaining are 0 or not
            $allRemainingZero = array_filter($variations, function ($obj) {
                return $obj->remaining != 0;
            });


            if (empty($allRemainingZero)) {

                $this->refillRemaining($variations);
                $variations = $this->convertpro_ele_query($testId);
                $variations = array_filter($variations, function ($variation) {
                    return $variation->class_name !== '';
                });
            }

            // Pick at random, weighted by the quota left, so the variation a
            // visitor gets is not decided by when they happened to arrive.
            $variation = $this->convertpro_ele_selectVariation($variations);

            if ($variation) {
                $this->updateVariation($wpdb, $variation, $testId, $cookieName, $variation->remaining - 1);
            }
        }
        // }
    }



    public function convertpro_ele_query($id)
    {
        global $wpdb;
        // Query the database for variations associated with the specified split test ID

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . $wpdb->prefix . "convertpro_variations
            WHERE splittest_id = %d
            ORDER BY id ASC",
                $id
            )
        );
        return $results;
    }

    public function updateVariation($wpdb, $variation, $testid, $cookieName, $remaining)
    {
        // Assignment happens while the page is being rendered, so if the headers
        // have already gone out the cookies would be dropped and this visitor
        // would be re-assigned (and re-counted) on every request. Skip instead.
        if (headers_sent()) {
            return;
        }

        // The page being built now contains one visitor's variation, so it must
        // not be stored and handed to the next visitor.
        convertpro_prevent_page_cache();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // $remaining = $variation->remaining - 1;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $wpdb->prefix . 'convertpro_variations',
            array('remaining' => $remaining),
            array('id' => $variation->id, 'splittest_id' => $testid)
        );

        $expires = time() + CONVERTPRO_COOKIE_LIFETIME;

        setcookie($cookieName, $variation->class_name, $expires, '/');
        $_COOKIE[$cookieName] = $variation->class_name; // Update $_COOKIE superglobal
        setcookie(convertpro_test_cookie_name('convert_pro_elm_variation_id_', $testid), $variation->id, $expires, '/');

        // Reuse the visitor's existing uid when they already have one: storing the
        // interaction under a freshly generated uid would leave the row orphaned
        // from the cookie, and the conversion update would never match it.
        $cookie_value = isset($_COOKIE['convert_pro_uid'])
            ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid']))
            : $this->convertpro_ele_generateuid();

        if (!isset($_COOKIE['convert_pro_uid'])) {
            setcookie('convert_pro_uid', $cookie_value, $expires, '/');
            setcookie('convert_pro_ele_uid', $testid, $expires, '/');
            $_COOKIE['convert_pro_uid'] = $cookie_value;
        }

        $this->convertpro_store_visit_data($cookie_value, $variation->id, $testid);
    }

    /**
     * Pick one of the variations that still has quota left, at random and
     * weighted by the remaining quota.
     *
     * @param array $variations Variation rows for a single test.
     * @return object|null
     */
    public function convertpro_ele_selectVariation($variations)
    {
        // Filter variations to only include those with remaining count greater than 0
        $available_variations = array_values(array_filter((array) $variations, function ($variation) {
            return $variation->remaining > 0;
        }));

        if (empty($available_variations)) {
            return null;
        }

        $total = 0;
        foreach ($available_variations as $variation) {
            $total += (int) $variation->remaining;
        }

        if ($total <= 0) {
            return $available_variations[array_rand($available_variations)];
        }

        $ticket = wp_rand(1, $total);
        $cursor = 0;

        foreach ($available_variations as $variation) {
            $cursor += (int) $variation->remaining;
            if ($ticket <= $cursor) {
                return $variation;
            }
        }

        return $available_variations[0];
    }

    public function refillRemaining($variations)
    {
        global $wpdb;
        $results = [];
        // var_dump($variations);
        foreach ($variations as $variation) {

            // Use the full configured percentage; taking the first digit only
            // (50 -> 5) distorted every split that was not a multiple of ten.
            $percentage = (int) $variation->percentage;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->update(
                $wpdb->prefix . 'convertpro_variations',
                array('remaining' => (int) $percentage),
                array('id' => (int) $variation->id)
            );
        }

        return $results;
    }
    public function updateeleVariationRemaining($variationid, $remainingCount)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $wpdb->prefix . 'convertpro_variations',
            array('remaining' => (int) $remainingCount),
            array('id' => (int) $variationid)
        );
    }



    public function convertpro_ele_generateuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),

            wp_rand(0, 0xffff),
            wp_rand(0, 0x0fff) | 0x4000,
            wp_rand(0, 0x3fff) | 0x8000,
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff)
        );
    }

    public function convertpro_store_visit_data($cookie_value, $variation_id, $testid)
    {
        global $wpdb;

        // Check if there's already an interaction record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $run = convertpro_get_test_run($testid);

        $query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}convertpro_interactions
        WHERE splittest_id = %d
        AND client_id = %s
        AND run = %d",
                $testid,
                $cookie_value,
                $run
            ),
            OBJECT
        );



        if (empty($query)) {
            $table_name = $wpdb->prefix . 'convertpro_interactions';
            $wpdb->insert(
                $table_name,
                [
                    'client_id' => $cookie_value,
                    'splittest_id' => $testid,
                    'variation_id' => $variation_id, // Ensure correct variation ID is stored
                    'run' => $run,
                ]
            );
        }
    }
}
