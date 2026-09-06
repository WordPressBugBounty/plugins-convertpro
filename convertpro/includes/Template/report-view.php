<?php

if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}
// Add a function to handle the AJAX request

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which test to show, capability checked by the menu.
$test_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$test = $test_id > 0 ? (new \ConvertPro\Classes\Repo())->gettestvalue($test_id) : null;

// An empty report with a live Reset button is worse than saying the test is not
// there, so stop before any of it is drawn.
if (!$test) {
    printf(
        '<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
        esc_html__('That test is no longer here.', 'convertpro'),
        esc_url(admin_url('admin.php?page=convertpro-settings')),
        esc_html__('Back to all tests', 'convertpro')
    );
    return;
}

// Step 1: Retrieve Data from the Database
global $wpdb;
$table_name = $wpdb->prefix . 'convertpro_interactions';

$results = convertpro_interactions_chart_query($test_id);
$chart = convertpro_build_chart_datasets($results);
$labels = $chart['labels'];
$datasets = $chart['datasets'];

$test_name = $test->name;
$variation_total = ($test && !empty($test->variations)) ? count($test->variations) : 0;

if ($test && $test->test_type === 'elements') {
    /* translators: %d: number of variations. */
    $test_summary = sprintf(_n('Showing visitors %d version of an element', 'Showing visitors one of %d versions of an element', $variation_total, 'convertpro'), $variation_total);
} else {
    /* translators: %d: number of variations. */
    $test_summary = sprintf(_n('Sending visitors to %d version of a page', 'Splitting visitors between %d versions of a page', $variation_total, 'convertpro'), $variation_total);
}

$current_run = convertpro_get_test_run($test_id);
$reset_url = wp_nonce_url(
    admin_url('admin.php?page=convertpro-settings&scope=test&action=reset&id=' . $test_id),
    'convertpro-reset-test_' . $test_id
);

// Data collected before the assignment fix shipped is mixed in with data from
// the corrected engine, so tell the user rather than letting them read it as one
// clean result.
$fix_max_id = get_option('convertpro_engine_fix_max_id', false);
$prefix_count = 0;
if (false !== $fix_max_id && $fix_max_id > 0 && $test_id) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $prefix_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE splittest_id = %d AND run = %d AND id <= %d",
            $test_id,
            $current_run,
            (int) $fix_max_id
        )
    );
}

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
        font-family: var(--convertpro-font);
        font-weight: 600;
        margin-bottom: 15px;
    }

    .convertpro-full-report .report-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
    }

    /* Heading on the left, controls grouped together on the right. */
    .convertpro-full-report .report-title .convertpro-report-heading {
        margin-right: auto;
    }

    .convertpro-full-report .report-title h1 {
        margin-bottom: 0;
    }

    .convertpro-report-subtitle {
        margin: 2px 0 0;
        color: #7C838A;
        font-size: 13px;
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

    .convertpro-run-badge {
        display: inline-block;
        margin-left: 8px;
        padding: 2px 10px;
        border-radius: 12px;
        background: #eef1f6;
        color: #50575e;
        font-size: 13px;
        vertical-align: middle;
    }

    .convertpro-chart-empty {
        text-align: center;
        color: #646970;
        margin: 24px 0;
    }

    /* Notes sit under full-width cards, so let them run the same width rather
       than stopping short and leaving a gap down the right-hand side. */
    .convertpro-full-report .description {
        margin: 10px 0 0;
        color: #646970;
        font-size: 13px;
        line-height: 1.6;
    }

    .convertpro-performance-report .description {
        margin-bottom: 16px;
    }

    .convertpro-fullreport .convertpro-vs-control {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .convertpro-fullreport .convertpro-vs-control.is-up {
        color: #1E8E1B;
    }

    .convertpro-fullreport .convertpro-vs-control.is-down {
        color: #C42B2B;
    }

    .convertpro-message {
        border: 1px solid #E8E8E8;
        border-left: 4px solid #7C838A;
        border-radius: 10px;
        background: #fff;
        padding: 4px 20px;
        margin: 0 0 24px;
    }

    .convertpro-message p {
        margin: 12px 0;
        font-size: 13px;
        line-height: 1.6;
        color: #4B535A;
    }

    .convertpro-message-warning {
        border-left-color: #E0A100;
        background: #FFFBF0;
    }

    /* The review ask is a notice like any other, so it looks like one. */
    .convertpro-message-review {
        border-left-color: #3767FB;
        background: #F7F9FF;
    }

    .convertpro-message-review .convertpro-review-question {
        margin-bottom: 4px;
        font-size: 14px;
        font-weight: 600;
        color: #080E13;
    }

    .convertpro-review-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    /*
     * Match the buttons the rest of the plugin uses — dark for the action we
     * want, outlined for the alternative — instead of inheriting WordPress
     * core's blue, which belongs to a different design.
     */
    .convertpro-review-actions .button,
    .convertpro-review-actions .button:hover,
    .convertpro-review-actions .button:focus {
        height: 34px;
        display: inline-flex;
        align-items: center;
        padding: 0 14px;
        border-radius: 8px;
        border: 1px solid rgba(8, 14, 19, 0.12);
        background: #fff;
        color: #080E13;
        font-size: 13px;
        font-weight: 500;
        box-shadow: none;
        text-shadow: none;
    }

    .convertpro-review-actions .button-primary,
    .convertpro-review-actions .button-primary:hover,
    .convertpro-review-actions .button-primary:focus {
        border-color: #080E13;
        background: #080E13;
        color: #fff;
    }

    .convertpro-review-actions .convertpro-review-later {
        font-size: 12px;
        color: #7C838A;
        text-decoration: none;
        box-shadow: none;
    }

    .convertpro-review-actions .convertpro-review-later:hover {
        color: #080E13;
    }

    .convertpro-message-success {
        border-left-color: #3BCB38;
        background: #F4FCF4;
    }
</style>
<div class="convertpro-report-page">
    <div class="container">

        <div class="convertpro-full-report">
            <div class="report-title">
                <div class="convertpro-report-heading">
                    <h1>
                        <?php echo $test_name ? esc_html($test_name) : esc_html__('Your test results', 'convertpro'); ?>
                        <?php if ($current_run > 1) : ?>
                            <span class="convertpro-run-badge">
                                <?php
                                /* translators: %d: run number. */
                                printf(esc_html__('Run %d', 'convertpro'), (int) $current_run);
                                ?>
                            </span>
                        <?php endif; ?>
                    </h1>
                    <?php if ($test_name && $variation_total) : ?>
                        <p class="convertpro-report-subtitle"><?php echo esc_html($test_summary); ?></p>
                    <?php endif; ?>
                </div>

                <a class="button convertpro-reset-test" href="<?php echo esc_url($reset_url); ?>"
                    onclick="return confirm('<?php echo esc_js(__('Start this test over? Everyone gets sorted into a variation again from scratch. What you have collected so far is kept, but it drops out of this report.', 'convertpro')); ?>');">
                    <?php esc_html_e('Reset test', 'convertpro'); ?>
                </a>

                <select name="report-range" id="convertpro-report-range">
                    <option value="7"><?php echo esc_html('Last 7 Days'); ?></option>
                    <option value="30"><?php echo esc_html('Last 30 Days'); ?></option>
                    <option value="90"><?php echo esc_html('Last 90 Days'); ?></option>
                    <option value="all"><?php echo esc_html('All Data'); ?></option>

                </select>
            </div>
            <?php
            // Up here with the other notices, not trailing off the bottom of the
            // page where it reads as an afterthought. Still only on this screen,
            // still declinable twice over — see docs/review-prompt-plan.md.
            $convertpro_review = convertpro_review_state();
            ?>

            <?php if (convertpro_should_ask_for_review($test_id)) : ?>
                <div class="convertpro-message convertpro-message-review">
                    <p class="convertpro-review-question"><?php esc_html_e('Is EasyTest working out for you?', 'convertpro'); ?></p>
                    <p class="convertpro-review-actions">
                        <a class="button button-primary" href="<?php echo esc_url(convertpro_review_action_url('happy', $test_id)); ?>"><?php esc_html_e('Yes, it is', 'convertpro'); ?></a>
                        <a class="button" href="<?php echo esc_url(convertpro_review_action_url('unhappy', $test_id)); ?>"><?php esc_html_e('Not really', 'convertpro'); ?></a>
                        <a class="convertpro-review-later" href="<?php echo esc_url(convertpro_review_action_url('later', $test_id)); ?>"><?php esc_html_e('Ask me later', 'convertpro'); ?></a>
                        <a class="convertpro-review-later" href="<?php echo esc_url(convertpro_review_action_url('dismissed', $test_id)); ?>"><?php esc_html_e('No thanks', 'convertpro'); ?></a>
                    </p>
                </div>
            <?php elseif (convertpro_should_show_review_link()) : ?>
                <div class="convertpro-message convertpro-message-review">
                    <?php if ('happy' === $convertpro_review['answer']) : ?>
                        <p class="convertpro-review-question"><?php esc_html_e('Good to hear.', 'convertpro'); ?></p>
                        <p><?php esc_html_e('A review on WordPress.org is the main way other people find EasyTest. It takes a minute.', 'convertpro'); ?></p>
                    <?php else : ?>
                        <p class="convertpro-review-question"><?php esc_html_e('Sorry to hear it.', 'convertpro'); ?></p>
                        <p>
                            <?php
                            printf(
                                /* translators: 1: opening link tag to the support forum, 2: closing link tag. */
                                esc_html__('Tell us what is wrong on %1$sthe support forum%2$s and we will look at it. Most of what people reported has since been fixed.', 'convertpro'),
                                '<a href="https://wordpress.org/support/plugin/convertpro/" target="_blank" rel="noopener">',
                                '</a>'
                            );
                            ?>
                        </p>
                        <?php // The link is here either way. We are not deciding who gets to rate the plugin. ?>
                        <p class="description"><?php esc_html_e('If you would rather say it publicly, a review works too:', 'convertpro'); ?></p>
                    <?php endif; ?>
                    <p class="convertpro-review-actions">
                        <a class="button<?php echo 'happy' === $convertpro_review['answer'] ? ' button-primary' : ''; ?>" href="<?php echo esc_url(convertpro_review_action_url('clicked', $test_id)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Leave a review', 'convertpro'); ?></a>
                        <a class="convertpro-review-later" href="<?php echo esc_url(convertpro_review_action_url('dismissed', $test_id)); ?>"><?php esc_html_e('No thanks', 'convertpro'); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['message']) && $_GET['message'] === 'reset_success') : ?>
                <div class="convertpro-message convertpro-message-success">
                    <p>
                        <?php
                        /* translators: %d: run number. */
                        printf(esc_html__('Done. This is now run %d. Visitors will be sorted into versions again, and the previous run is still saved.', 'convertpro'), (int) $current_run);
                        ?>
                    </p>
                </div>
            <?php elseif ($prefix_count > 0) : ?>
                <div class="convertpro-message convertpro-message-warning">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: number of visitors, already formatted. 2: opening link tag. 3: closing link tag. */
                            esc_html(
                                _n(
                                    'Heads up: %1$s of the visitors counted below was sorted by the old method, which did not split traffic evenly. That makes this comparison unreliable. %2$sStart a fresh run%3$s to collect clean numbers. Nothing you have already recorded gets deleted.',
                                    'Heads up: %1$s of the visitors counted below were sorted by the old method, which did not split traffic evenly. That makes this comparison unreliable. %2$sStart a fresh run%3$s to collect clean numbers. Nothing you have already recorded gets deleted.',
                                    $prefix_count,
                                    'convertpro'
                                )
                            ),
                            esc_html(number_format_i18n($prefix_count)),
                            '<a href="' . esc_url($reset_url) . '">',
                            '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="convertpro-performance-report">
                <h4><?php esc_html_e('Day by day', 'convertpro'); ?></h4>
                <p class="description"><?php esc_html_e('Views and conversions for each version, counted on the day the visitor first landed in the test.', 'convertpro'); ?></p>
                <div class="convertpro-interactionChart-wrap">
                    <canvas id="convertpro-interactionChart"></canvas>
                </div>
                <p class="convertpro-chart-empty" <?php echo empty($labels) ? '' : 'style="display:none"'; ?>>
                    <?php esc_html_e('Nothing to show yet. Results will appear here as soon as visitors start landing in the test.', 'convertpro'); ?>
                </p>
            </div>

            <div class="variation-details-stats"></div>

            <div class="convertpro-data-table">

                <h4><?php esc_html_e('How each version did', 'convertpro'); ?></h4>
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
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    }
                }

            }
        });

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
                    nonce: '<?php echo esc_js(wp_create_nonce('convertpro-report-nonce')); ?>',
                    id: <?php echo (int) ( isset($_GET['id']) ? wp_unslash($_GET['id']) : 0 ); ?>
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
                    nonce: '<?php echo esc_js(wp_create_nonce('convertpro-report-nonce')); ?>',
                    id: <?php echo (int) ( isset($_GET['id']) ? wp_unslash($_GET['id']) : 0 ); ?>
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
            var hasData = data && data.labels && data.labels.length;

            myChart.data.labels = hasData ? data.labels : [];
            myChart.data.datasets = hasData ? data.datasets : [];

            $('.convertpro-chart-empty').toggle(!hasData);

            // Update the chart
            myChart.update();
        }
    });
</script>