<?php

namespace ConvertPro\Classes;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


class Storedatabase
{

    /**
     * test insert into
     * database
     * @param [type] $data
     * @return void
     */
    public function CreateTest()
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        if (current_user_can('manage_options') && isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce')) {
            global $wpdb;
            $wpdb->insert(
                $this->getTestTable(),
                [
                    'name' => isset($_POST['test-name']) ? sanitize_text_field(wp_unslash($_POST['test-name'])) : '',
                    'active' => true,
                    'test_type' => isset($_POST['convertpro-test-type']) ? sanitize_text_field(wp_unslash($_POST['convertpro-test-type'])) : 'pages',
                    'test_uri' => isset($_POST['test-uri']) ?  sanitize_text_field(wp_unslash($_POST['test-uri'])) : '',
                    'conversion_type' => $this->conversion_type(),
                    'conversion_page_id' => $this->conversion_page_id(),
                    'conversion_url' => $this->conversion_selector(),
                ],
                array('%s', '%d', '%s', '%s', '%s', '%d', '%s')
            );
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            return $wpdb->insert_id;
        }

        // The caller checks the nonce and the capability before getting here, so
        // this is unreachable in practice. Return 0 rather than nothing so the
        // caller has something to test — Store::RepoStore() stops on a falsy id
        // instead of filing the versions against test 0.
        return 0;
    }

    /**
     * create test variation
     * from test variation table
     * @return void
     */
    public function CreateTestVariation($id, $variation)
    {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery

        $percentage = (int) $variation['percentage'];

        $result = $wpdb->insert($this->getVariationTable(), array(
            'name' => sanitize_text_field($variation['name']),
            'percentage' => $percentage,
            'page_id' => isset($variation['pageId']) ? (int) $variation['pageId'] : null,
            'splittest_id' => $id,
            'class_name' => convertpro_safe_class_name(isset($variation['customclass']) ? $variation['customclass'] : ''),
            'active' => true,
            // The share this version still has left in the current cycle. It has
            // to start at the share itself: the front end only picks from
            // versions with something left, and the automatic refill waits until
            // every version is empty. Left at the column default of 0, a version
            // added to a running test would sit out the rest of the cycle.
            'remaining' => $percentage,
            'created_at' => current_time('mysql')
        ), array('%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->insert_id;
    }

    public function updateTest($id)
    {
        if (current_user_can('manage_options') && isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce')) {
            global $wpdb;

            //  phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $this->getTestTable(),
                [
                    'name' => isset($_POST['test-name']) ? sanitize_text_field(wp_unslash($_POST['test-name'])) : '',
                    'test_type' => isset($_POST['convertpro-test-type']) ?  sanitize_text_field(wp_unslash($_POST['convertpro-test-type'])) : 'pages',
                    'test_uri' => isset($_POST['test-uri']) ? sanitize_text_field(wp_unslash($_POST['test-uri'])) : '',
                    'conversion_type' => $this->conversion_type(),
                    'conversion_page_id' => $this->conversion_page_id(),
                    'conversion_url' => $this->conversion_selector(),
                ],
                ['id' => $id],
                ['%s', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );
            //phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    // write a code here
    public function updateTestVariation($id, $data)
    {
        global $wpdb;

        $percentage = (int) $data['percentage'];

        // `remaining` is deliberately not written here. Whether the quota cycle
        // restarts is a decision about the whole test, not about one row —
        // Store::Repoupdate() compares the saved split with the submitted one and
        // restarts every version together, so a save cannot leave some versions
        // part-way through the old split and others at the start of the new one.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $this->getVariationTable(),
            [
                'name' => sanitize_text_field($data['name']),
                'percentage' => $percentage,
                'page_id' => (int) $data['postId'],
                'class_name' => convertpro_safe_class_name(isset($data['customclass']) ? $data['customclass'] : ''),
            ],
            ['id' => $id],
            ['%s', '%d', '%d', '%s'],
            ['%d']
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * How this test decides someone converted: reaching a page, or clicking
     * something on the page they landed on.
     *
     * @return string 'page' or 'click'
     */
    private function conversion_type()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
        $type = isset($_POST['test-conversion-type']) ? sanitize_text_field(wp_unslash($_POST['test-conversion-type'])) : 'page';

        return 'click' === $type ? 'click' : 'page';
    }

    /**
     * Conversion page, only meaningful for page goals.
     *
     * @return int
     */
    private function conversion_page_id()
    {
        if ('click' === $this->conversion_type()) {
            return 0;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
        return isset($_POST['test-conversion-page']) ? (int) $_POST['test-conversion-page'] : 0;
    }

    /**
     * CSS selector counted as a conversion, only meaningful for click goals.
     *
     * @return string
     */
    private function conversion_selector()
    {
        if ('click' !== $this->conversion_type()) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
        $selector = isset($_POST['test-conversion-selector']) ? wp_unslash($_POST['test-conversion-selector']) : '';

        // A selector is markup-adjacent, so strip tags and keep it to one line.
        return trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $selector)));
    }

    private function getTestTable()
    {
        global $wpdb;
        return $wpdb->prefix . 'convertpro';
    }

    public function getVariationTable()
    {
        // write a code here
        global $wpdb;
        return $wpdb->prefix . 'convertpro_variations';
    }

    private function removewhitespace($conversionUrl)
    {
        if ($conversionUrl == null) {
            return null;
        }
        return rtrim($conversionUrl, "/") . "/";
    }
}
