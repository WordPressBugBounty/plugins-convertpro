<?php

namespace ConvertPro\Classes;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


class Redirection
{
    function __construct()
    {
        add_action('template_redirect', [$this, 'random_redirect']);
        add_action('template_redirect', [$this, 'update_conversion']);
    }

    // Function to randomly redirect users between two pages based on identifier

    public function update_conversion()
    {
        // The page WordPress actually resolved for this request. Matching on this
        // rather than on a permalink string is what makes query parameters and
        // endpoints harmless, and it stops a 404 counting as a conversion —
        // get_the_permalink() with nothing in the loop falls back to whichever
        // post happens to come first.
        $current_page_id = get_queried_object_id();

        if (!$current_page_id) {
            return;
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'pages' AND active = 1");
        foreach ($results as $value) {

            // Click goals are recorded by the front-end tracker instead. Skipping
            // them here also avoids get_the_permalink(0) falling back to the
            // current page, which would count every page as a conversion.
            if ('click' === $value->conversion_type || empty($value->conversion_page_id)) {
                continue;
            }

            // Nothing to do unless this is the goal page. Checked before the
            // variation query so an ordinary page view costs no queries at all.
            if ((int) $value->conversion_page_id !== $current_page_id) {
                continue;
            }

            $run = convertpro_get_test_run($value->id);
            $cookieName = convertpro_test_cookie_name('convert_pro_test_', $value->id);
            $variationCookie = convertpro_test_cookie_name('convert_pro_variation_id_', $value->id);
            $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

            $variations = $this->convertpro_query($value->id); // Fetch all variations for the current test
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
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                }
            }
        }
    }
    public function random_redirect()
    {
        // Check if the identifier exists in the URL query string
        $current_slug = convertpro_current_path();

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'pages' AND active = 1");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching

        foreach ($results as $value) {
            // A row with no test URL matches the empty path, which is the home
            // page — so a single blank row sent every visitor to a variation and
            // the site looked like it had been taken over. Saving one has been
            // refused since 1.0.3; this is for the rows already out there.
            if ('' === trim((string) $value->test_uri, '/ ')) {
                continue;
            }

            if ($current_slug === trim(wp_parse_url(home_url() . '/' . $value->test_uri, PHP_URL_PATH), '/')) {

                // A test URL is an address of its own with nothing behind it, so
                // WordPress should have nothing to show here. When it does have
                // something, the row is one of the broken pre-1.0.3 ones and
                // redirecting would hijack a page people are meant to read — or
                // send them back to this same page forever, if the page is also
                // one of the variations. Leave the request alone.
                if (!is_404()) {
                    continue;
                }

                // This URL decides which variation the visitor gets, so it must
                // not be cached — a stored copy would send everyone the same way.
                // Only this one URL is affected; the variation pages themselves
                // stay cacheable, because they are ordinary pages.
                convertpro_prevent_page_cache();

                $variations = $this->convertpro_query($value->id); // Fetch all variations for the current test

                // Someone who has already been assigned goes back to the same
                // variation. Their target comes from get_permalink() like a first
                // visit does — the old home_url() . $slug only agreed with it for
                // a top-level page on a plain permalink structure, so a child page
                // sent returning visitors to a 404 and nobody else.
                $assigned = $this->variationFromCookie($variations, $value->id);

                if ($assigned) {
                    $this->redirectToVariation($assigned);
                }

                // Check remaining counts and update if needed
                $needsUpdate = false;

                $allRemainingZero = array_filter($variations, function ($obj) {
                    return $obj->remaining != 0;
                });


                if (empty($allRemainingZero)) {

                    $this->refillRemaining($variations);
                    $variations = $this->convertpro_query($value->id); // Fetch all variations for the current test

                }


                $cookieName = convertpro_test_cookie_name('convert_pro_test_', $value->id);
                $client_test_id = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

                if (!empty($client_test_id)) {
                    continue;
                }

                // Pick at random (weighted by the quota left) instead of taking the
                // first variation with quota, so the variation a visitor gets is not
                // correlated with when they arrived.
                $variation = $this->selectVariation($variations);

                if ($variation) {
                    $remaining = $variation->remaining - 1;
                    $this->updateVariationAndRedirect($wpdb, $variation, $cookieName, $value->id, $remaining);
                }
            }
        }
    }

    /**
     * The variation this visitor was already given, if any.
     *
     * @param array $variations Variation rows for a single test.
     * @param int   $test_id
     * @return object|null
     */
    public function variationFromCookie($variations, $test_id)
    {
        $cookieName = convertpro_test_cookie_name('convert_pro_test_', $test_id);
        $assigned_slug = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

        if ('' === $assigned_slug) {
            return null;
        }

        foreach ($variations as $variation) {
            if ($variation->page_slug === $assigned_slug) {
                return $variation;
            }
        }

        return null;
    }

    /**
     * Send the visitor to a variation, unless doing so would break the site.
     *
     * Returns instead of redirecting when the variation page has gone or when the
     * target is the page being requested — the second is what "too many
     * redirects" looked like on tests saved before 1.0.3. Letting the request
     * render is always better than sending the browser round again.
     *
     * @param object $variation
     * @return bool True when the visitor was redirected (execution ends there).
     */
    public function redirectToVariation($variation)
    {
        $target = get_permalink($variation->page_id);

        if (!$target || convertpro_redirect_loops($target)) {
            return false;
        }

        wp_redirect(convertpro_forward_query_string($target));
        exit;
    }

    /**
     * Pick one of the variations that still has quota left, at random and
     * weighted by the remaining quota, so a 70/30 split really does hand out
     * roughly 70/30 rather than serving all of A before any of B.
     *
     * @param array $variations Variation rows for a single test.
     * @return object|null
     */
    public function selectVariation($variations)
    {
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

    public function convertpro_query($id)
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.*, p.post_name AS page_slug
            FROM " . $wpdb->prefix . "convertpro_variations v
            LEFT JOIN " . $wpdb->prefix . "posts p ON v.page_id = p.ID
            WHERE v.splittest_id = %d
            ORDER BY v.id ASC",
                $id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $results;
    }

    public function updateVariationAndRedirect($wpdb, $variation, $cookieName, $testid, $remaining)
    {
        $target = get_permalink($variation->page_id);

        // Checked before anything is written: a test whose URL is also one of its
        // own variation pages loops forever, and quota and cookies should not be
        // spent on a visitor who is about to be left where they are.
        if (!$target || convertpro_redirect_loops($target)) {
            return false;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // $remaining = $variation->remaining - 1;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'convertpro_variations',
            array('remaining' => $remaining),
            array('id' => $variation->id, 'splittest_id' => $testid)

        );
        $cookie_value = $this->convertpro_generateuid();
        $expires = time() + CONVERTPRO_COOKIE_LIFETIME;

        setcookie($cookieName, $variation->page_slug, $expires, '/');
        setcookie('convert_pro_test_id', $testid, $expires, '/');
        setcookie(convertpro_test_cookie_name('convert_pro_variation_id_', $testid), $variation->id, $expires, '/');

        // The uid links this visitor to their interaction row, so it has to live
        // as long as the assignment cookies — a shorter life silently drops every
        // conversion that happens after it expires.
        if (!isset($_COOKIE['convert_pro_uid'])) {
            setcookie('convert_pro_uid', $cookie_value, $expires, '/');
            $_COOKIE['convert_pro_uid'] = $cookie_value;
        }
        // store cookie value
        $this->store_visit_data(sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid'])), $variation->id, $testid);

        wp_redirect(convertpro_forward_query_string($target));
        exit();
    }

    public function updateVariationRemaining($wpdb, $variationId, $remainingPercentage)
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}convertpro_variations
            SET remaining = %d
            WHERE id = %d",
                $remainingPercentage,
                $variationId
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    // Function to generate UUID
    public function convertpro_generateuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),

            // 16 bits for "time_mid"
            wp_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            wp_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            wp_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff),
            wp_rand(0, 0xffff)
        );
    }

    public function store_visit_data($cookie_value, $variation, $testid)
    {
        //  phpcs:ignore WordPress.DB.DirectDatabaseQuery
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $run = convertpro_get_test_run($testid);

        $query = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}convertpro_interactions
     WHERE splittest_id = %d
     AND client_id = %s
     AND run = %d",
            $testid,
            $cookie_value,
            $run
        ), OBJECT);

        if (sizeof($query) == 0) {
            $table_name = $wpdb->prefix . 'convertpro_interactions';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $table_name,
                array(
                    'client_id' => $cookie_value,
                    'splittest_id' => $testid,
                    'variation_id' => $variation,
                    'run' => $run,
                )
            );
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        }
    }


    public function refillRemaining($variations)
    {
        global $wpdb;
        $results = [];
        // var_dump($variations);
        foreach ($variations as $variation) {

            // Refill with the full configured percentage. Taking only the first
            // digit (50 -> 5) both shrank the cycle and distorted every split
            // that was not a round multiple of ten (25/75 became 2:7).
            $percentage = (int) $variation->percentage;

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->update(
                $wpdb->prefix . 'convertpro_variations',
                array('remaining' => (int) $percentage),
                array('id' => (int) $variation->id)
            );
        }

        return $results;
    }
}
