<?php

use ConvertPro\Classes\Repo;

// AJAX handler to handle requests
add_action('wp_ajax_convertpro_ajax_action', 'convertpro_ajax_request');
add_action('wp_ajax_nopriv_convertpro_ajax_action', 'convertpro_ajax_request');

function convertpro_ajax_request()
{
    check_ajax_referer('convertpro_nonce', 'security');

    global $wpdb;

    $testId = isset($_COOKIE['convert_pro_test_id']) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_test_id'])) : '';
    $variationid = isset($_COOKIE['convert_pro_variation_id_'.$testId]) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_variation_id_'.$testId])) : '';
    $clientId = isset($_COOKIE['convert_pro_uid']) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid'])) : '';
    $pageslug = isset($_COOKIE['convert_pro_test_' . $testId]) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_test_' . $testId])) : '';
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "convertpro" . " WHERE id =%d", $testId)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
    
    $pageId = '';
    foreach ($results as $result) {
        $pageId = isset($result->conversion_page_id) ? $result->conversion_page_id : '';
    }

    $permalink = get_permalink($pageId);

    $purl = isset($_POST['previous_url']) ? sanitize_text_field(wp_unslash($_POST['previous_url'])) : '';

    $parsedUrl = wp_parse_url($purl);
    $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
    $path = trim($path, '/');

    // Get the last segment (page slug)
    $segments = explode('/', $path);
    $pageSlug = end($segments);


    $fpath = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';


    $message = '';
    if ($pageSlug == $pageslug) {

        if ($fpath === $permalink) {
            
            $query = $wpdb->get_results($wpdb->prepare(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT * FROM {$wpdb->prefix}convertpro_interactions
                WHERE splittest_id = %d
                AND client_id = %s",
                $testId,
                $clientId
            ), OBJECT);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            if (sizeof($query) > 0) {
                $query = $wpdb->query(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}convertpro_interactions
                    SET type = 'conversion', variation_id = %d
                    WHERE splittest_id = %d
                    AND client_id = %s",
                        $variationid,
                        $testId,
                        $clientId
                    )
                );
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            }
        }
    }



    // Modify the URL value in the response array
    $response = array(
        'url' => $permalink,
        'id' => $testId,
        'variationdid' => $variationid,
        'fpath' => $fpath,
        'message' => $message
    );

    // Encode the array as JSON and output it


    wp_die();
}

function convertpro_interactions_report_html()
{


    $id = isset($_GET['id']) ? intval(sanitize_text_field(wp_unslash($_GET['id']))) : 0;
    $range = isset($_GET['range']) ? sanitize_text_field(wp_unslash(($_GET['range']))) : 7;

    $repo = new Repo();
    $results = $repo->getVariations($id);

?>
    <div class="convertpro-fullreport">
        <table>
            <tr>
                <th><?php esc_html_e('Variation', 'convertpro'); ?></th>
                <th><?php esc_html_e('Percentage', 'convertpro'); ?></th>
                <th><?php esc_html_e('Views', 'convertpro'); ?></th>
                <th><?php esc_html_e('Conversions', 'convertpro'); ?></th>
                <th><?php esc_html_e('Conversion Rate', 'convertpro'); ?></th>
            </tr>
            <?php if ($results) {
                foreach ($results as $result) {
                    // Output the table row for each variation
                    $conversion_count = convertpro_get_conversion($id, $result->id, $range);
                    $total_views = convertpro_get_views($id, $result->id, $range);

                    if ($total_views > 0) {
                        $conversion_rate = ($conversion_count / $total_views) * 100;
                    } else {
                        $conversion_rate = 0;
                    }

            ?>
                    <tr>
                        <td><?php echo esc_html($result->name); ?></td>
                        <td><?php echo esc_html($result->percentage); ?></td>
                        <td><?php echo intval($total_views); ?></td>
                        <td><?php echo intval($conversion_count); ?></td>
                        <td><?php echo intval($conversion_rate); ?>%</td>

                    </tr>
                <?php }
            } else { ?>
                <tr>
                    <td colspan="5"><?php esc_html_e('No data available', 'convertpro'); ?></td>
                </tr>
            <?php } ?>
        </table>
    </div>
<?php


}

function convertpro_interactions_report_ajax()
{
    if (!isset($_GET['id']))
        return false;
    ob_start();
    convertpro_interactions_report_html();
    wp_send_json(ob_get_clean());
}
add_action('wp_ajax_convertpro_interactions_report_ajax', 'convertpro_interactions_report_ajax');
add_action('wp_ajax_nopriv_convertpro_interactions_report_ajax', 'convertpro_interactions_report_ajax');
function convertpro_interactions_chart_query($id, $range = 7)
{
    if (!$id) {
        return false;
    }



    global $wpdb;
    $table_name = $wpdb->prefix . 'convertpro_interactions';
    $test_id = $id;
    // Handle AJAX request to fetch data based on selected date range


    // Calculate the start date based on the selected range

    $query = "";
    $placeholders = [];
    $query .= "SELECT
            v.name AS variation_name,
            DATE_FORMAT(i.updated_at, '%%Y-%%m-%%d') AS interaction_date,
            DATE_FORMAT(i.updated_at, '%%W') AS day_name,
            COUNT(CASE WHEN i.type = 'view' THEN 1 END) AS daily_views,
            COUNT(CASE WHEN i.type = 'conversion' THEN 1 END) AS daily_conversions,
            COUNT(i.type) AS daily_total_interactions
        FROM
            {$wpdb->prefix}convertpro_variations AS v
            INNER JOIN {$wpdb->prefix}convertpro_interactions AS i ON v.id = i.variation_id
            INNER JOIN {$wpdb->prefix}convertpro AS s ON i.splittest_id = s.id
        WHERE
            i.splittest_id = %d";

    $placeholders[] = $test_id;

    if ($range != 'all') {
        $query .= " AND i.updated_at <= NOW()
        AND i.updated_at >= DATE_SUB(NOW(), INTERVAL %s DAY)";
        $placeholders[] = intval($range);
        // $placeholders[] = $endDate;
    }

    $query .= " GROUP BY
    variation_name, interaction_date
ORDER BY
    interaction_date ASC";

    $query = $wpdb->prepare(// phpcs:ignore
        $query, // phpcs:ignore
        $placeholders
    );

    return $wpdb->get_results($query, ARRAY_A); // phpcs:ignore
}

function convertpro_get_chart_data()
{


    if (isset($_GET['range'])) {
        $test_id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : false;
        // Handle AJAX request to fetch data based on selected date range
        $range =  isset($_GET['range']) ? sanitize_text_field(wp_unslash($_GET['range'])) : '';

        $results = convertpro_interactions_chart_query($test_id, $range);
        // var_dump($results);
        if ($results) :
            // Prepare the data for Chart.js
            $labels = array_unique(array_merge(array_column($results, 'interaction_date'))); // or 'day_name'
            sort($labels);

            $datasets = array();
            $bg_color_set = [
                1 => '#3BCB38',
                2 => '#3767FB',
                3 => '#3767FB',
                4 => '#EE2626'
            ];
            $i = 0;
            foreach ($results as $row) {
                $i++;
                $variation_name = $row['variation_name'];
                $date = $row['interaction_date']; // or $row['day_name']
                $views = $row['daily_total_interactions'];
                $conversions = $row['daily_conversions'];

                // Create a dataset for views
                $dataset_name = $variation_name;
                if (!isset($datasets[$dataset_name])) {
                    $datasets[$dataset_name] = array(
                        'label' => $dataset_name,
                        'data' => array_fill_keys($labels, 0),
                        'backgroundColor' => [
                            $bg_color_set[$i]
                        ],
                    );
                }
                $datasets[$dataset_name]['data'][$date] = $views;
            }

            $datasets = array_values($datasets);
            wp_send_json([
                'labels' => $labels,
                'datasets' => $datasets
            ]);
        else :
            wp_send_json([
                'labels' => 0,
                'datasets' => 0
            ]);

        endif;
        // Return the response as a JSON



    }
}

// Hook the AJAX handler function to a WordPress AJAX action
add_action('wp_ajax_convertpro_get_chart_data', 'convertpro_get_chart_data');
add_action('wp_ajax_nopriv_convertpro_get_chart_data', 'convertpro_get_chart_data');


function convertpro_get_views($test_id, $variation_id, $range = 7)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'convertpro_interactions';

    $views_query = "";
    $views_placeholders = [];
    $views_query .= "SELECT COUNT(*) FROM {$table_name} WHERE splittest_id = %d AND variation_id = %d";
    $views_placeholders[] = $test_id;
    $views_placeholders[] = $variation_id;

    if ($range != 'all') {

        $views_query .= " AND updated_at <= NOW()
        AND updated_at >= DATE_SUB(NOW(), INTERVAL %s DAY)";
        $views_placeholders[] = intval($range);
    }

    $views_query = $wpdb->prepare(
        $views_query,// phpcs:ignore
        $views_placeholders
    );

    return $wpdb->get_var($views_query);// phpcs:ignore
}
function convertpro_get_conversion($test_id, $variation_id, $range = 7)
{

    global $wpdb;
    $table_name = $wpdb->prefix . 'convertpro_interactions';

    $conversion_query = "";
    $conversion_placeholders = [];
    $conversion_query .= "SELECT COUNT(*) FROM {$table_name} WHERE type = 'conversion' AND splittest_id = %d AND variation_id = %d";
    $conversion_placeholders[] = $test_id;
    $conversion_placeholders[] = $variation_id;

    if ($range != 'all') {

        $conversion_query .= " AND updated_at <= NOW()
        AND updated_at >= DATE_SUB(NOW(), INTERVAL %s DAY)";
        $conversion_placeholders[] = intval($range);
    }

    $conversion_query = $wpdb->prepare(
        $conversion_query,// phpcs:ignore
        $conversion_placeholders
    );


    // Get the count of conversions
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
    return $wpdb->get_var($conversion_query);// phpcs:ignore
}

// elements conversion here


function get_conversion_page_permalink_ajax()
{
    global $wpdb;
    $testeleId = isset($_COOKIE['convert_pro_ele_uid']) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_ele_uid'])) : '';
    $variationid = isset($_COOKIE['convert_pro_variation_id_'.$testeleId]) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_variation_id_'.$testeleId])) : '';
    $clientId = isset($_COOKIE['convert_pro_uid']) ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid'])) : '';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "convertpro WHERE id = %d", $testeleId));
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    $pageeleId = '';
    foreach ($results as $result) {
        $pageeleId = isset($result->conversion_page_id) ? $result->conversion_page_id : '';
    }

    if ($pageeleId) {
        $permalink = get_permalink($pageeleId);
        $cpath = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        if ($permalink == $cpath) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
            $query = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}convertpro_interactions
                WHERE splittest_id = %d
                AND client_id = %s",
                $testeleId,
                $clientId
            ), OBJECT);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            if (sizeof($query) > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
                $result = $query = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}convertpro_interactions
                    SET type = 'conversion', variation_id = %d
                    WHERE splittest_id = %d
                    AND client_id = %s",
                        $variationid,
                        $testeleId,
                        $clientId
                    )
                );
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            }
        }
    }

    wp_die(); // Always include this at the end of your AJAX callback function
}

add_action('wp_ajax_get_conversion_page_permalink', 'get_conversion_page_permalink_ajax');
add_action('wp_ajax_nopriv_get_conversion_page_permalink', 'get_conversion_page_permalink_ajax');
