<?php

namespace ConvertPro\Classes;

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
        $current_slug = isset($_SERVER['REQUEST_URI']) ?  trim(wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH), '/') : '';

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'pages'");
        foreach ($results as $value) {

            $cookieName = 'convert_pro_test_' . $value->id;
            $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

            $variations = $this->convertpro_query($value->id); // Fetch all variations for the current test
            foreach ($variations as $variation) {
                $user_variation_id = isset($_COOKIE['convert_pro_variation_id_' . $value->id]) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_variation_id_' . $value->id])) : '';
                $client_id = isset($_COOKIE['convert_pro_uid']) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid'])) : '';

                if ((get_the_permalink($value->conversion_page_id) == get_the_permalink()) && ($user_variation_id == $variation->id && !empty($active_class))) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update(
                        $wpdb->prefix . 'convertpro_interactions',
                        array('type' => 'conversion'),
                        array('variation_id' => $variation->id, 'splittest_id' => $value->id, 'client_id' => $client_id),
                    );
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                }
            }
        }
    }
    public function random_redirect()
    {
        // Check if the identifier exists in the URL query string
        $current_slug = isset($_SERVER['REQUEST_URI']) ?  trim(wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH), '/') : '';

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'pages'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching

        foreach ($results as $value) {
            // var_dump($current_slug, $value->test_uri);
            // die;
            if ($current_slug === trim(wp_parse_url(home_url() . '/' . $value->test_uri, PHP_URL_PATH), '/')) {

                $variations = $this->convertpro_query($value->id); // Fetch all variations for the current test

                // Build an array of page slugs for quick lookup
                $page_slugs = array_map(function ($result) {
                    return $result->page_slug;
                }, $variations);

                // Check if any variation matches the cookie value
                foreach ($variations as $variation) {
                    $cookieName = 'convert_pro_test_' . $value->id;

                    if (isset($_COOKIE[$cookieName]) && in_array($_COOKIE[$cookieName], $page_slugs)) {
                        // Redirect if the cookie is set and its value matches a page slug
                        wp_redirect(home_url('/') . sanitize_text_field(wp_unslash($_COOKIE[$cookieName])));
                        exit;
                    }
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


                foreach ($variations as $variation) {
                    $client_test_id = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

                    if (!empty($client_test_id)) {
                        continue;
                    }


                    if ($variation->remaining <= 0) {

                        continue;
                    } else {
                        $remaining = $variation->remaining - 1;
                    }

                    if ($variation) {
                        $cookieName = 'convert_pro_test_' . $value->id;
                        $this->updateVariationAndRedirect($wpdb, $variation, $cookieName, $value->id, $remaining);
                    }
                }
            }
        }
    }

    public function selectVariation($wpdb, $test_id)
    {
        $variations = $this->convertpro_query($test_id);
        $available_variations = array_filter($variations, function ($variation) {
            return $variation->remaining > 0;
        });

        if (!empty($available_variations)) {
            // Choose a random variation from the available ones
            $variation = $available_variations[array_rand($available_variations)];
            return $variation;
        }

        return null;
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
            WHERE v.splittest_id = %d",
                $id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $results;
    }

    public function updateVariationAndRedirect($wpdb, $variation, $cookieName, $testid, $remaining)
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // $remaining = $variation->remaining - 1;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'convertpro_variations',
            array('remaining' => $remaining),
            array('id' => $variation->id, 'splittest_id' => $testid),

        );
        $cookie_value = $this->convertpro_generateuid();

        setcookie($cookieName, $variation->page_slug, time() + (86400 * 30), '/');
        setcookie('convert_pro_test_id', $testid, time() + (86400 * 30), '/');
        setcookie('convert_pro_variation_id_' . $testid, $variation->id, time() + (86400 * 30), '/');
        if (!isset($_COOKIE['convert_pro_uid'])) {
            setcookie('convert_pro_uid', $cookie_value, time() + 3600, "/");
            $_COOKIE['convert_pro_uid'] = $cookie_value;
        }
        // store cookie value
        $this->store_visit_data(sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid'])), $variation->id, $testid);

        wp_redirect(get_permalink($variation->page_id));
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
        $query = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}convertpro_interactions
     WHERE splittest_id = %d
     AND client_id = %s",
            $testid,
            $cookie_value
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

            $percentage = str_split($variation->percentage)[0];

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->update(
                $wpdb->prefix . 'convertpro_variations',
                array('remaining' => (int) $percentage),
                array('id' => (int) $variation->id),
            );
        }

        return $results;
    }
}
