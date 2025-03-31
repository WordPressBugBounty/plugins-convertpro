<?php

namespace ConvertPro\Classes;

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

        $styles = '';
        $active_class = '';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type='elements'");
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
            $cookieName = 'convert_pro_elm_test_' . $value->id;
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

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'elements'");



        foreach ($results as $value) {


            $cookieName = 'convert_pro_elm_test_' . $value->id;
            $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';

            $variations = $this->convertpro_ele_query($value->id); // Fetch all variations for the current test
            foreach ($variations as $variation) {
                $user_variation_id = isset($_COOKIE['convert_pro_elm_variation_id_' . $value->id]) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_elm_variation_id_' . $value->id])) : '';
                $client_id = isset($_COOKIE['convert_pro_uid']) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid'])) : '';

                // var_dump(get_the_permalink($value->conversion_page_id));
                if ((get_the_permalink($value->conversion_page_id) == get_the_permalink()) && ($user_variation_id == $variation->id && !empty($active_class))) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update(
                        $wpdb->prefix . 'convertpro_interactions',
                        array('type' => 'conversion'),
                        array('variation_id' => $variation->id, 'splittest_id' => $value->id, 'client_id' => $client_id),
                    );
                }
            }
        }
    }
    public function autoSelectElement($testId)
    {
        global $wpdb;

        /*         $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro WHERE test_type = 'elements'");


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
        $cookieName = 'convert_pro_elm_test_' . $testId;
        $active_class = isset($_COOKIE[$cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookieName])) : '';



        if (empty($active_class)) {

            // checking if all remaining are 0 or not
            $allRemainingZero = array_filter($variations, function ($obj) {
                return $obj->remaining != 0;
            });


            if (empty($allRemainingZero)) {

                $this->refillRemaining($variations);
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


                $cookieName = 'convert_pro_elm_test_' . $testId;

                $this->updateVariation($wpdb, $variation, $testId, $cookieName, $remaining);
                // continue; // Redirect after setting the cookie


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
            WHERE splittest_id = %d",
                $id
            )
        );
        return $results;
    }

    public function updateVariation($wpdb, $variation, $testid, $cookieName, $remaining)
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // $remaining = $variation->remaining - 1;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $wpdb->prefix . 'convertpro_variations',
            array('remaining' => $remaining),
            array('id' => $variation->id, 'splittest_id' => $testid),
        );


        setcookie($cookieName, $variation->class_name, time() + (86400 * 30), "/"); // 86400 = 1 day
        $_COOKIE[$cookieName] = $variation->class_name; // Update $_COOKIE superglobal
        setcookie('convert_pro_elm_variation_id_' . $testid, $variation->id, time() + (86400 * 30), '/');
        $cookie_value = $this->convertpro_ele_generateuid();
        $this->convertpro_store_visit_data($cookie_value, $variation->id, $testid);

        if (!isset($_COOKIE['convert_pro_uid'])) {

            setcookie('convert_pro_uid', $cookie_value, time() + 3600, "/");
            setcookie('convert_pro_ele_uid', $testid, time() + 3600, "/");
        }
        $_COOKIE['convert_pro_uid'] = $cookie_value;
    }

    public function convertpro_ele_selectVariation($test_id)
    {
        // Query the variations again after potential updates
        $variations = $this->convertpro_ele_query($test_id);

        // Filter variations to only include those with remaining count greater than 0
        $available_variations = array_filter($variations, function ($variation) {
            return $variation->remaining > 0;
        });

        // Return a random variation from the available ones, or null if none are available
        if (!empty($available_variations)) {
            return $available_variations[array_rand($available_variations)];
        }

        return null;
    }

    public function refillRemaining($variations)
    {
        global $wpdb;
        $results = [];
        // var_dump($variations);
        foreach ($variations as $variation) {

            $percentage = str_split($variation->percentage)[0];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->update(
                $wpdb->prefix . 'convertpro_variations',
                array('remaining' => (int) $percentage),
                array('id' => (int) $variation->id),
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
            array('id' => (int) $variationid),
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
        $query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}convertpro_interactions
        WHERE splittest_id = %d
        AND client_id = %s",
                $testid,
                $cookie_value
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
                ]
            );
        }
    }
}
