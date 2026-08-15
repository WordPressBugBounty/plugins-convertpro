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
                array('%s')
            );
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            return $wpdb->insert_id;
        }
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

        $result = $wpdb->insert($this->getVariationTable(), array(
            'name' => sanitize_text_field($variation['name']),
            'percentage' => sanitize_text_field($variation['percentage']),
            'page_id' => isset($variation['pageId']) ? sanitize_text_field($variation['pageId']) : null,
            'splittest_id' => $id,
            'class_name' => sanitize_text_field($variation['customclass']),
            'active' => true,
            'created_at' => current_time('mysql')
        ), array('%s', '%d', '%d', '%s', '%s', '%d'));

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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $this->getVariationTable(),
            [
                'name' => $data['name'],
                'percentage' => $data['percentage'],
                'page_id' => $data['postId'],
                'class_name' => $data['customclass'],
            ],
            ['id' => $id],
            ['%s', '%d', '%s'],
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
