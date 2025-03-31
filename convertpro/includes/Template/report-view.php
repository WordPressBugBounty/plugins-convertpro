<?php
// Add a function to handle the AJAX request

$test_id = isset($_GET['id']) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : false;
// Step 1: Retrieve Data from the Database
global $wpdb;
$table_name = $wpdb->prefix . 'convertpro_interactions';


$results = convertpro_interactions_chart_query($test_id);
$labels = array();
$datasets = array();
// var_dump($results);
if ($results):
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

        // // Create a dataset for conversions
        // $dataset_name = $variation_name . ' Conversions';
        // if (!isset($datasets[$dataset_name])) {
        //     $datasets[$dataset_name] = array(
        //         'label' => $dataset_name,
        //         'data' => array_fill_keys($labels, 0),
        //     );
        // }
        // $datasets[$dataset_name]['data'][$date] = $conversions;
    }

    $datasets = array_values($datasets);


endif;
// print_r($datasets);
wp_enqueue_script('chart');
?>

<style>
    .convertpro-interactionChart-wrap {
        width: 100%;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 15px;
    }

    td {
        border: 1px solid;
        padding: .5em;
        text-align: center;
    }

    .convertpro-interactionChart-wrap canvas#convertpro-interactionChart {
        background-color: #fff;
        padding: 30px;
        border-radius: 10px;
        border: 1px solid #D2D2D2;
    }

    .convertpro-report-page {
        width: 819px;
        max-width: 100%;
        margin: 0 auto;
        padding: 75px 0;
    }

    .convertpro-performance-report h4 {
        font-size: 18px;
        font-family: Inter;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .convertpro-full-report .report-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }



    select#convertpro-report-range {
        height: 36px;
        border-radius: 8px;
        border: 1px solid #CBD2D9;
        padding: 0 10px;
        font-size: 12px;
        min-width: 115px;
        max-width: 100%;
        color: #080E13;
    }
</style>
<div class="convertpro-report-page">
    <div class="container">

        <div class="convertpro-full-report">
            <div class="report-title">
                <h1><?php esc_html_e('Full Report', 'convertpro'); ?></h1>

                <select name="report-range" id="convertpro-report-range">
                    <option value="7"><?php echo esc_html('Last 7 Days'); ?></option>
                    <option value="30"><?php echo esc_html('Last 30 Days'); ?></option>
                    <option value="90"><?php echo esc_html('Last 90 Days'); ?></option>
                    <option value="all"><?php echo esc_html('All Data'); ?></option>

                </select>
            </div>
            <div class="convertpro-performance-report">
                <h4><?php echo esc_html('Performance Report') ?></h4>
                <div class="convertpro-interactionChart-wrap">
                    <canvas id="convertpro-interactionChart"></canvas>
                </div>
            </div>

            <div class="variation-details-stats"></div>

            <div class="convertpro-data-table">

                <h4><?php echo esc_html__('Report by Variation', 'convertpro') ?></h4>
                <div class="convertpro-fullreport-wrap">
                    <?php convertpro_interactions_report_html() ?>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        var ctx = document.getElementById('convertpro-interactionChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode($labels); ?>,
                datasets: <?php echo wp_json_encode($datasets); ?>
            },
            options: {
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: 'rgb(255, 99, 132)'
                        }
                    }
                }

            }
        });

        <?php if (empty($labels)): ?>
            myChart.data = {};
            myChart.update();

        <?php endif; ?>
        // Event handler for when the date range select box changes
        $('#convertpro-report-range').on('change', function() {
            // Get the selected range value
            var range = $(this).val();

            // Make AJAX request to fetch data based on the selected range
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                type: 'GET',
                data: {
                    action: 'convertpro_get_chart_data',
                    range: range,
                    id: <?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['id']))) ?>
                },
                success: function(response) {
                    // Parse the JSON response
                    var data = response;

                    // Update the chart with the new data
                    updateChart(data);
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                type: 'GET',
                data: {
                    action: 'convertpro_interactions_report_ajax',
                    range: range,
                    id: <?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['id']))) ?>
                },
                success: function(response) {
                    // Parse the JSON response
                    var data = response;

                    $('.convertpro-fullreport-wrap').html(data);
                    // Update the chart with the new data

                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
        });

        // Function to update the chart with new data
        function updateChart(data) {

            // Update the chart's labels and datasets
            if (data.labels == 0 || data == 0) {
                myChart.data = {};


            } else {
                myChart.data.labels = data.labels;
                myChart.data.datasets = data.datasets;
            }

            // Update the chart
            myChart.update();
        }
    });
</script>