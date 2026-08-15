<?php

namespace ConvertPro\Classes;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


use WP_Query;

class Repo
{
    public function getAlltests()
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        global $wpdb;

        // Paused tests still belong in the list — they are shown with their
        // status so their results stay reachable while they are not running.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $tests = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}convertpro ORDER BY id DESC",
            OBJECT
        );

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $tests;
    }

    public function gettestvalue($id)
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $test = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "convertpro" . " WHERE id = %d",  intval($id)), OBJECT);
        if ($test) {
            $test->variations = $this->getVariations($test->id);
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $test;
    }

    public function getVariations($pageId)
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        // Ordered so the report always treats the same variation as the control.
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "convertpro_variations" . " WHERE splittest_id =%d ORDER BY id ASC", $pageId));

        foreach ($results as $result) {
            // Access each row as $result, which is an object
            $result->page_id = $result->page_id;
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $results;
    }


    public function TestDelete($id)
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        global $wpdb;

        $this->VariationTestDelete($id);
        $this->deleteTestInteractions($id);
        $table_name = $wpdb->prefix . 'convertpro';
        $where = array('id' => $id);
        $format = array('%d');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($table_name, $where, $format);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    public function deleteTestInteractions($splitTestID)
    {
        //  phpcs:ignore WordPress.DB.DirectDatabaseQuery
        global $wpdb;

        $table_name = $wpdb->prefix . 'convertpro_interactions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($table_name, ['splittest_id' => $splitTestID], ['%d']);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    public function VariationTestDelete($id)
    {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        global $wpdb;

        // Prepare the SQL statement
        $table_name = $wpdb->prefix . 'convertpro_variations';
        $where = array('splittest_id' => $id);
        $format = array('%d');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($table_name, $where, $format);
    }
}
