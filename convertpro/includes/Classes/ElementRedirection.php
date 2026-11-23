<?php

namespace ConvertPro\Classes;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


class ElementRedirection
{

    function __construct()
    {
        if (is_admin()) {
            return false;
        }
        // add_action('wp', [$this, 'autoSelectElement'], 1);
        // add_action('wp_enqueue_scripts', [$this, 'addElementStyles']);

        // On template_redirect rather than init, so the buffer only wraps
        // requests that are about to render a template. A REST call, admin-ajax,
        // wp-cron and anything else that never gets that far is left alone.
        add_action('template_redirect', array($this, 'start_buffer'), 0);
        add_action('template_redirect', [$this, 'update_conversion']);
    }

    public function start_buffer()
    {
        ob_start(array($this, 'manipulate_html'));
    }

    /**
     * Hide the versions this visitor was not given.
     *
     * The page is edited in place: the elements carrying another version's class
     * are cut out and **every other byte comes back exactly as the theme wrote
     * it**. Nothing is parsed into a document and written out again.
     *
     * That used to be how this worked, and it is what CVE-2026-110528 was about.
     * The whole page went through a bundled HTML parser, which lifts comments,
     * scripts and styles out before parsing and pastes them back afterwards
     * without regard for where the placeholder ended up. A visitor's own comment
     * could therefore be pasted into an attribute of some other element, close
     * that attribute, and have the rest of it read as markup. Nothing the plugin
     * escaped on the way in could prevent it, because the damage happened on the
     * way out.
     *
     * @param string $buffer The whole response, as the theme produced it.
     * @return string
     */
    public function manipulate_html($buffer)
    {
        global $wpdb;

        // The buffer catches whatever the request sends, so only touch something
        // that is actually an HTML page. The XML check is separate because the
        // sitemap stylesheet is XML that happens to contain an <html> element.
        if (false === stripos($buffer, '<html') || 0 === strpos(ltrim($buffer), '<?xml')) {
            return $buffer;
        }

        $active_class = '';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type='elements' AND active = 1");
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
                // A class that is really selector syntax matches things it was
                // never meant to. Rows saved before this was checked can still
                // hold one, so skip them here rather than trusting the column.
                if ('' === convertpro_safe_class_name($variation->class_name)) {
                    continue;
                }

                // Only sort a visitor into the test when the element it is about
                // is actually on this page.
                if (empty($active_class)) {

                    if (convertpro_html_has_class($buffer, $variation->class_name)) {
                        $this->autoSelectElement($value->id);
                    }
                }

                $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

                if (!empty($active_class) && $variation->class_name != $active_class) {
                    $buffer = convertpro_html_remove_class($buffer, $variation->class_name);
                }
            }
        }

        return $buffer;
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

                // Without a visitor id there is no row to flip, and the update
                // would match on client_id = '' instead.
                if ($user_variation_id == $variation->id && !empty($active_class) && '' !== $client_id) {
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

        // Take one off the quota in the database rather than writing back the
        // number this request read, so two visitors arriving together cannot
        // cost the quota only one. $remaining is left in the signature but no
        // longer used.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}convertpro_variations
                SET remaining = remaining - 1
                WHERE id = %d AND splittest_id = %d AND remaining > 0",
                $variation->id,
                $testid
            )
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

        $run = convertpro_get_test_run($testid);

        // Check if there's already an interaction record. Has to read the
        // current row; a cached answer would count a returning visitor twice.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
            // created_at is left to the column default on purpose — see the
            // matching insert in Redirection::store_visit_data().
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
