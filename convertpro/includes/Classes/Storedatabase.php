<?php

namespace ConvertPro\Classes;

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
        if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce')) {
            global $wpdb;
            $wpdb->insert(
                $this->getTestTable(),
                [
                    'name' => isset($_POST['test-name']) ? sanitize_text_field(wp_unslash($_POST['test-name'])) : '',
                    'active' => true,
                    'test_type' => isset($_POST['convertpro-test-type']) ? sanitize_text_field(wp_unslash($_POST['convertpro-test-type'])) : 'pages',
                    'test_uri' => isset($_POST['test-uri']) ?  sanitize_text_field(wp_unslash($_POST['test-uri'])) : '',
                    'conversion_page_id' => isset($_POST['test-conversion-page']) ? sanitize_text_field(wp_unslash($_POST['test-conversion-page'])) : 0,
                    // 'conversion_url' => $_POST['test-conversion-url'] == "null" ? "null" : sanitize_text_field(wp_unslash($_POST['test-conversion-url']));
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
        if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce')) {
            global $wpdb;

            //  phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $this->getTestTable(),
                [
                    'name' => isset($_POST['test-name']) ? sanitize_text_field(wp_unslash($_POST['test-name'])) : '',
                    'test_type' => isset($_POST['convertpro-test-type']) ?  sanitize_text_field(wp_unslash($_POST['convertpro-test-type'])) : 'pages',
                    'test_uri' => isset($_POST['test-uri']) ? sanitize_text_field(wp_unslash($_POST['test-uri'])) : '',
                    'conversion_page_id' => isset($_POST['test-conversion-page']) ? sanitize_text_field(wp_unslash($_POST['test-conversion-page'])) : 0,
                    // 'conversion_url' => $this->removewhitespace($_POST['test-conversion-url']),
                ],
                ['id' => $id],
                ['%s', '%s', '%s'],
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
