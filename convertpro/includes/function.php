<?php

if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}

use ConvertPro\Classes\Repo;

/**
 * How long a visitor stays assigned to the variation they were given.
 */
if (!defined('CONVERTPRO_COOKIE_LIFETIME')) {
    define('CONVERTPRO_COOKIE_LIFETIME', 86400 * 30);
}

/**
 * SQL expression for the day a visitor entered a test.
 *
 * Rows written before `created_at` was populated hold a zero date, which would
 * otherwise all pile up under 0000-00-00, so those fall back to `updated_at`.
 *
 * @param string $alias Table alias, without the trailing dot.
 * @return string
 */
function convertpro_entry_date_sql($alias = 'i')
{
    return "COALESCE(NULLIF({$alias}.created_at, '0000-00-00 00:00:00'), {$alias}.updated_at)";
}

/**
 * The path of the request being served, with no host, query or edge slashes.
 *
 * Kept deliberately raw — it is only ever compared against another path, never
 * printed, and running it through sanitize_text_field() would strip percent
 * escapes and make an encoded path stop matching itself.
 *
 * @return string
 */
function convertpro_current_path()
{
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = wp_parse_url($uri, PHP_URL_PATH);

    return is_string($path) ? trim($path, '/') : '';
}

/**
 * Would redirecting here send the browser straight back to where it already is?
 *
 * Tests saved before 1.0.3 could use a test URL that was also one of the pages
 * being tested, which loops until the browser gives up. Saving now refuses that,
 * but rows already in the table keep looping after an update, and the person
 * seeing the loop often cannot reach wp-admin to fix it.
 *
 * @param string $url Redirect target.
 * @return bool
 */
function convertpro_redirect_loops($url)
{
    if (empty($url) || !is_string($url)) {
        return true;
    }

    $path = wp_parse_url($url, PHP_URL_PATH);
    $path = is_string($path) ? trim($path, '/') : '';

    return $path === convertpro_current_path();
}

/**
 * Carry the query string the visitor arrived with over to the redirect target.
 *
 * Someone landing on a test URL from an ad arrives with the campaign tags the
 * test exists to measure. Rebuilding the target from the page permalink dropped
 * every one of them, so the variation page saw no utm_source, no gclid, and the
 * attribution was gone before the page rendered.
 *
 * The target keeps its own parameters; anything the visitor arrived with wins on
 * a clash.
 *
 * @param string $url Redirect target.
 * @return string
 */
function convertpro_forward_query_string($url)
{
    if (empty($url) || !is_string($url)) {
        return $url;
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
    $incoming = isset($_SERVER['QUERY_STRING']) ? wp_unslash($_SERVER['QUERY_STRING']) : '';

    if ('' === $incoming) {
        return $url;
    }

    $args = array();
    wp_parse_str($incoming, $args);

    if (empty($args)) {
        return $url;
    }

    // Values are re-encoded by add_query_arg() and the result goes through
    // wp_redirect(), which strips anything that could break the header, so
    // stripping tags is all that is needed here.
    $args = map_deep($args, 'wp_strip_all_tags');

    return add_query_arg($args, $url);
}

/**
 * What the free tier allows, before any per-site exemption.
 *
 * **This is the switch.** Zero means no limit, and zero everywhere means the
 * limits are not in force at all — no counting, no notices, nothing recorded.
 *
 * They are held at zero on purpose. The store-page banner still says "Unlimited
 * Tests & Variations", and shipping a limit while the shop window promises the
 * opposite is how you earn one-star reviews. Turn these on in the same release
 * that the banner changes, not before.
 *
 * When that happens, the numbers to use are 3 and 2 — one better than AB Split
 * Test's free tier, which allows a single test with a single variation, and
 * better than Nelio's one test.
 *
 * @return array
 */
function convertpro_free_limit_defaults()
{
    return array(
        'tests' => 0,
        'variations' => 0,
    );
}

/**
 * How much the free plugin allows, for this site.
 *
 * Zero means no limit. Sites that had EasyTest before the limits came in get
 * zero for everything and never see any of this — see
 * ConvertPro::remember_free_limits().
 *
 * @param string $what Either 'tests' or 'variations'.
 * @return int
 */
function convertpro_free_limit($what)
{
    $limits = convertpro_free_limit_defaults();

    $limit = isset($limits[$what]) ? $limits[$what] : 0;

    if ('off' === get_option('convertpro_free_limits')) {
        $limit = 0;
    }

    /**
     * Filter a free-tier limit. Return 0 for no limit.
     *
     * @param int    $limit
     * @param string $what  'tests' or 'variations'.
     */
    return (int) apply_filters('convertpro_free_limit', $limit, $what);
}

/**
 * How many tests are running on this site right now.
 *
 * @return int
 */
function convertpro_active_test_count()
{
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}convertpro WHERE active = 1");
}

/**
 * Is there room for another test?
 *
 * @return bool
 */
function convertpro_can_add_test()
{
    $limit = convertpro_free_limit('tests');

    return !$limit || convertpro_active_test_count() < $limit;
}

/**
 * Note that this site has filled up its free allowance.
 *
 * Recorded once, the first time it happens, so we can answer one question before
 * building anything paid: how many people actually run out of room, and how long
 * it takes them. Asking that with a prompt would only hear from the enthusiastic;
 * counting hears from everyone.
 *
 * Nothing leaves the site unless the owner has opted into usage tracking — see
 * convertpro_free_tier_usage().
 *
 * @return void
 */
function convertpro_record_cap_reached()
{
    $usage = get_option('convertpro_cap_usage', array());

    if (!empty($usage['reached'])) {
        return;
    }

    $usage['reached'] = time();

    update_option('convertpro_cap_usage', $usage);
}

/**
 * Note a save that was refused for being over the limit.
 *
 * Rarer than reaching the cap, and a stronger signal: the form hides the controls
 * at the limit, so getting here means someone went round the UI to try anyway.
 *
 * @param string $what 'tests' or 'variations'.
 * @return void
 */
function convertpro_record_cap_blocked($what)
{
    $usage = get_option('convertpro_cap_usage', array());
    $key = 'blocked_' . $what;

    $usage[$key] = isset($usage[$key]) ? (int) $usage[$key] + 1 : 1;

    update_option('convertpro_cap_usage', $usage);
}

/**
 * What gets added to the usage report, for sites that opted into one.
 *
 * Attached through Finestics' add_extra(), which only runs when
 * `convertpro_allow_tracking` is 'yes'. No new outbound request, no personal
 * data — counts and timestamps only.
 *
 * @return array
 */
function convertpro_free_tier_usage()
{
    $usage = get_option('convertpro_cap_usage', array());

    return array(
        'free_limits' => (string) get_option('convertpro_free_limits', 'unknown'),
        'installed_at' => (int) get_option('convertpro_installed', 0),
        'tests' => convertpro_active_test_count(),
        'cap_reached' => isset($usage['reached']) ? (int) $usage['reached'] : 0,
        'cap_blocked_tests' => isset($usage['blocked_tests']) ? (int) $usage['blocked_tests'] : 0,
        'cap_blocked_variations' => isset($usage['blocked_variations']) ? (int) $usage['blocked_variations'] : 0,
    );
}

/**
 * What a page is called in the pickers.
 *
 * Duplicating a page is how most people build a second version, so two pages
 * with the same title is the normal case here rather than an oddity. When it
 * happens the title on its own tells you nothing, so the address goes beside it.
 *
 * @param WP_Post $page  The page being listed.
 * @param array   $pages Every page in the same list.
 * @return string
 */
function convertpro_page_option_label($page, $pages)
{
    static $seen = null;

    if (null === $seen) {
        $seen = array();

        foreach ($pages as $other) {
            $title = $other->post_title;
            $seen[$title] = isset($seen[$title]) ? $seen[$title] + 1 : 1;
        }
    }

    $title = $page->post_title;

    if (empty($seen[$title]) || $seen[$title] < 2) {
        return $title;
    }

    return sprintf('%s (/%s/)', $title, $page->post_name);
}

/**
 * Tell page caches not to store this response.
 *
 * A cached response is the same for everyone, so whichever variation the first
 * visitor happened to get would be served to all of them and the test would
 * quietly measure nothing. Called only on requests that actually take part in a
 * test, so the rest of the site keeps its cache.
 *
 * Recognised by WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache and
 * others through the DONOTCACHEPAGE constant. Caches that sit in front of PHP,
 * at the host or a CDN, never see this and need their own exclusion rule.
 *
 * @return void
 */
function convertpro_prevent_page_cache()
{
    /**
     * Filter whether to ask page caches to skip this response.
     *
     * @param bool $prevent
     */
    if (!apply_filters('convertpro_prevent_page_cache', true)) {
        return;
    }

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }

    if (!defined('DONOTCACHEOBJECT')) {
        define('DONOTCACHEOBJECT', true);
    }

    if (!defined('DONOTCACHEDB')) {
        define('DONOTCACHEDB', true);
    }

    if (headers_sent()) {
        return;
    }

    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
}

/**
 * Which edge cache, if any, sits in front of this site.
 *
 * These run before PHP does, so nothing the plugin sends can reach them. All we
 * can do is recognise them and tell the person what rule to add.
 *
 * @return array|null Name and instructions, or null when nothing is detected.
 */
function convertpro_detect_edge_cache()
{
    $test_url = home_url('/your-test-url/');

    if (isset($_SERVER['HTTP_CF_RAY']) || isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return array(
            'name' => 'Cloudflare',
            'how' => sprintf(
                /* translators: %s: example test URL. */
                __('In Cloudflare, add a Cache Rule for %s with caching set to Bypass. Without it Cloudflare answers from its own copy and every visitor lands on the same version.', 'convertpro'),
                $test_url
            ),
        );
    }

    if (isset($_SERVER['HTTP_X_SUCURI_CLIENTIP'])) {
        return array(
            'name' => 'Sucuri',
            'how' => __('In the Sucuri firewall, add your test URL to the cache exceptions list.', 'convertpro'),
        );
    }

    if (defined('KINSTAMU_VERSION')) {
        return array(
            'name' => 'Kinsta',
            'how' => __('Ask Kinsta support to exclude your test URL from the server cache, or add it under Tools → Cache exclusions.', 'convertpro'),
        );
    }

    if (class_exists('WpeCommon')) {
        return array(
            'name' => 'WP Engine',
            'how' => __('In the WP Engine portal, add your test URL as a cache exclusion.', 'convertpro'),
        );
    }

    if (defined('SG_CACHE_PLUGIN_DIR') || isset($_SERVER['HTTP_X_PROXY_CACHE'])) {
        return array(
            'name' => 'SiteGround',
            'how' => __('In SG Optimizer, add your test URL under Dynamic Caching exclusions.', 'convertpro'),
        );
    }

    return null;
}

/**
 * Load the click tracker for visitors who are in a test that counts clicks.
 *
 * Nothing is enqueued for visitors who have not been put into such a test, so
 * the vast majority of page views carry no extra script at all.
 *
 * @return void
 */
function convertpro_enqueue_click_goals()
{
    if (is_admin()) {
        return;
    }

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $tests = $wpdb->get_results("SELECT id, test_type, conversion_url FROM {$wpdb->prefix}convertpro WHERE active = 1 AND conversion_type = 'click' AND conversion_url != ''");

    if (!$tests) {
        return;
    }

    $watching = array();

    foreach ($tests as $test) {
        $prefix = ('elements' === $test->test_type) ? 'convert_pro_elm_variation_id_' : 'convert_pro_variation_id_';
        $cookie = convertpro_test_cookie_name($prefix, $test->id);

        if (empty($_COOKIE[$cookie])) {
            continue;
        }

        $patterns = array_values(array_filter(array_map('trim', explode(',', $test->conversion_url))));

        if (!$patterns) {
            continue;
        }

        $watching[] = array(
            'id' => (int) $test->id,
            'variation' => (int) sanitize_text_field(wp_unslash($_COOKIE[$cookie])),
            'patterns' => $patterns,
        );
    }

    if (!$watching) {
        return;
    }

    wp_enqueue_script('convertpro-click-goal');
    wp_localize_script('convertpro-click-goal', 'convertproClickGoals', array(
        'endpoint' => esc_url_raw(rest_url('convertpro/v1/conversion')),
        'tests' => $watching,
    ));
}
add_action('wp_enqueue_scripts', 'convertpro_enqueue_click_goals');

/**
 * Mark this visitor's interaction with a test as a conversion.
 *
 * Flipping the row they already have keeps one row per visitor per run, so a
 * visitor can only ever be counted once however many times this is called.
 *
 * @param int    $test_id
 * @param int    $variation_id
 * @param string $client_id Visitor uid from the cookie.
 * @return bool Whether a row was updated.
 */
function convertpro_record_conversion($test_id, $variation_id, $client_id)
{
    if (!$test_id || !$variation_id || '' === $client_id) {
        return false;
    }

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return (bool) $wpdb->update(
        $wpdb->prefix . 'convertpro_interactions',
        array('type' => 'conversion'),
        array(
            'splittest_id' => (int) $test_id,
            'variation_id' => (int) $variation_id,
            'client_id' => $client_id,
            'run' => convertpro_get_test_run($test_id),
        )
    );
}

/**
 * Record a conversion reported by the click tracker on the front end.
 *
 * Deliberately unauthenticated and nonce-free: visitors are anonymous, and a
 * nonce baked into a cached page goes stale and silently loses conversions.
 * Nothing here can be abused beyond marking your own visit as converted, which
 * you could do by clicking anyway.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function convertpro_rest_record_click($request)
{
    $test_id = (int) $request->get_param('test');
    $variation_id = (int) $request->get_param('variation');
    $client_id = isset($_COOKIE['convert_pro_uid'])
        ? sanitize_text_field(wp_unslash($_COOKIE['convert_pro_uid']))
        : '';

    $recorded = convertpro_record_conversion($test_id, $variation_id, $client_id);

    return new WP_REST_Response(array('recorded' => $recorded), 200);
}

add_action('rest_api_init', function () {
    register_rest_route(
        'convertpro/v1',
        '/conversion',
        array(
            'methods' => 'POST',
            'callback' => 'convertpro_rest_record_click',
            'permission_callback' => '__return_true',
            'args' => array(
                'test' => array('required' => true, 'sanitize_callback' => 'absint'),
                'variation' => array('required' => true, 'sanitize_callback' => 'absint'),
            ),
        )
    );
});

/**
 * Current run (round) number for a test.
 *
 * Resetting a test increments this, which both changes the cookie names — so
 * previously assigned visitors are re-bucketed — and separates the new data
 * from the previous round's history.
 *
 * @param int $test_id
 * @return int
 */
function convertpro_get_test_run($test_id)
{
    return max(1, (int) get_option('convertpro_test_run_' . (int) $test_id, 1));
}

/**
 * Build a run-scoped cookie name, e.g. convert_pro_test_5_r2.
 *
 * @param string $prefix  Cookie prefix, including the trailing underscore.
 * @param int    $test_id
 * @return string
 */
function convertpro_test_cookie_name($prefix, $test_id)
{
    return $prefix . (int) $test_id . '_r' . convertpro_get_test_run($test_id);
}

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

    // Gather the numbers first so every row can be compared against the control
    // (the first variation) and the totals can be shown.
    $rows = array();
    $control_rate = null;

    if ($results) {
        foreach ($results as $result) {
            $conversion_count = (int) convertpro_get_conversion($id, $result->id, $range);
            $total_views = (int) convertpro_get_views($id, $result->id, $range);
            $conversion_rate = $total_views > 0 ? ($conversion_count / $total_views) * 100 : 0;

            if (null === $control_rate) {
                $control_rate = $conversion_rate;
            }

            $rows[] = array(
                'name' => $result->name,
                'percentage' => $result->percentage,
                'views' => $total_views,
                'conversions' => $conversion_count,
                'rate' => $conversion_rate,
            );
        }
    }

?>
    <div class="convertpro-fullreport">
        <table>
            <tr>
                <th><?php esc_html_e('Version', 'convertpro'); ?></th>
                <th><?php esc_html_e('Share', 'convertpro'); ?></th>
                <th><?php esc_html_e('Views', 'convertpro'); ?></th>
                <th><?php esc_html_e('Conversions', 'convertpro'); ?></th>
                <th><?php esc_html_e('Conversion Rate', 'convertpro'); ?></th>
                <th><?php esc_html_e('vs. Control', 'convertpro'); ?></th>
            </tr>
            <?php if ($rows) {
                foreach ($rows as $index => $row) {
                    $comparison_class = '';

                    if (0 === $index) {
                        $comparison = esc_html__('Control', 'convertpro');
                    } elseif ($control_rate > 0) {
                        $uplift = (($row['rate'] - $control_rate) / $control_rate) * 100;
                        // A true minus sign rather than a hyphen, so the figure lines
                        // up with the positive case instead of looking cramped.
                        $sign = $uplift < 0 ? "\xE2\x88\x92" : '+';
                        $comparison = $sign . number_format_i18n(abs($uplift), 1) . '%';
                        $comparison_class = $uplift < 0 ? 'is-down' : 'is-up';
                    } else {
                        $comparison = '—';
                    }
            ?>
                    <tr>
                        <td><?php echo esc_html($row['name']); ?></td>
                        <td><?php echo esc_html($row['percentage']); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['views'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['conversions'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['rate'], 1)); ?>%</td>
                        <td class="convertpro-vs-control <?php echo esc_attr($comparison_class); ?>"><?php echo esc_html($comparison); ?></td>

                    </tr>
                <?php }
            } else { ?>
                <tr>
                    <td colspan="6"><?php esc_html_e('No data available', 'convertpro'); ?></td>
                </tr>
            <?php } ?>
        </table>
    </div>
    <p class="description">
        <?php esc_html_e('The last column compares each version with your control, which is the first one in the list. With only a handful of visitors this number swings around a lot, so give the test time to run before you pick a winner.', 'convertpro'); ?>
    </p>
<?php


}

function convertpro_interactions_report_ajax()
{
    // Reporting is admin-only: enforce capability and nonce (CVE-2025-63031).
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('You are not allowed to access this resource.', 'convertpro'), 403);
    }
    check_ajax_referer('convertpro-report-nonce', 'nonce');

    if (!isset($_GET['id']))
        return false;
    ob_start();
    convertpro_interactions_report_html();
    wp_send_json(ob_get_clean());
}
add_action('wp_ajax_convertpro_interactions_report_ajax', 'convertpro_interactions_report_ajax');
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

    // Rows are grouped by `created_at`, the day the visitor was put into the
    // test. Grouping by `updated_at` moved a visitor's view into whatever day
    // they later converted on, which quietly corrupted the whole time series.
    // Every row is a participant, so views = all rows in that day's cohort and
    // conversions are the subset of them that converted.
    $entry_date = convertpro_entry_date_sql('i');

    $query = "";
    $placeholders = [];
    $query .= "SELECT
            v.id AS variation_id,
            v.name AS variation_name,
            DATE_FORMAT({$entry_date}, '%%Y-%%m-%%d') AS interaction_date,
            DATE_FORMAT({$entry_date}, '%%W') AS day_name,
            COUNT(i.id) AS daily_views,
            COUNT(CASE WHEN i.type = 'conversion' THEN 1 END) AS daily_conversions,
            COUNT(i.id) AS daily_total_interactions
        FROM
            {$wpdb->prefix}convertpro_variations AS v
            INNER JOIN {$wpdb->prefix}convertpro_interactions AS i ON v.id = i.variation_id
            INNER JOIN {$wpdb->prefix}convertpro AS s ON i.splittest_id = s.id
        WHERE
            i.splittest_id = %d
            AND i.run = %d";

    $placeholders[] = $test_id;
    $placeholders[] = convertpro_get_test_run($test_id);

    if ($range != 'all') {
        $query .= " AND {$entry_date} <= NOW()
        AND {$entry_date} >= DATE_SUB(NOW(), INTERVAL %s DAY)";
        $placeholders[] = intval($range);
        // $placeholders[] = $endDate;
    }

    $query .= " GROUP BY
    variation_id, variation_name, interaction_date
ORDER BY
    interaction_date ASC";

    $query = $wpdb->prepare(// phpcs:ignore
        $query, // phpcs:ignore
        $placeholders
    );

    return $wpdb->get_results($query, ARRAY_A); // phpcs:ignore
}

/**
 * Colour for a variation, picked by its position in the test so a variation
 * keeps the same colour across days and any number of variations is supported.
 *
 * @param int   $index Zero-based position of the variation.
 * @param float $alpha 1 for the solid colour, less for a translucent fill.
 * @return string Hex colour, or rgba() when an alpha is given.
 */
function convertpro_variation_color($index, $alpha = 1)
{
    $palette = array('#3767FB', '#3BCB38', '#EE2626', '#F5A623', '#9B51E0', '#00B8D9', '#FF6B9A', '#7A869A');
    $hex = $palette[$index % count($palette)];

    if ($alpha >= 1) {
        return $hex;
    }

    return sprintf(
        'rgba(%d, %d, %d, %s)',
        hexdec(substr($hex, 1, 2)),
        hexdec(substr($hex, 3, 2)),
        hexdec(substr($hex, 5, 2)),
        $alpha
    );
}

/**
 * Turn chart query rows into Chart.js labels and datasets.
 *
 * Each variation gets a Views bar and a Conversions bar. The old chart plotted
 * views and conversions added together, which is not a number anyone can act on.
 *
 * @param array $results Rows from convertpro_interactions_chart_query().
 * @return array {labels: string[], datasets: array[]}
 */
function convertpro_build_chart_datasets($results)
{
    if (empty($results)) {
        return array('labels' => array(), 'datasets' => array());
    }

    $labels = array_values(array_unique(array_column($results, 'interaction_date')));
    sort($labels);

    // Colour by variation id order, not by the order rows happen to come back,
    // so a variation keeps the same colour between runs and date ranges.
    $variation_ids = array_unique(array_map('intval', array_column($results, 'variation_id')));
    sort($variation_ids);
    $variation_order = array_flip($variation_ids);

    $views = array();
    $conversions = array();

    foreach ($results as $row) {
        $key = (int) $row['variation_id'];
        $name = $row['variation_name'];
        $date = $row['interaction_date'];
        $color = convertpro_variation_color($variation_order[$key]);

        // Conversions keep the version's colour but sit behind a lighter fill, so
        // the two bars in a pair are the same family and still tell apart. A solid
        // fill for both made the legend unreadable.
        $conversion_fill = convertpro_variation_color($variation_order[$key], 0.3);

        if (!isset($views[$key])) {
            $views[$key] = array(
                /* translators: %s: variation name. */
                'label' => sprintf(__('%s — Views', 'convertpro'), $name),
                'data' => array_fill_keys($labels, 0),
                'backgroundColor' => $color,
            );
            $conversions[$key] = array(
                /* translators: %s: variation name. */
                'label' => sprintf(__('%s — Conversions', 'convertpro'), $name),
                'data' => array_fill_keys($labels, 0),
                'backgroundColor' => $conversion_fill,
                'borderColor' => $color,
                'borderWidth' => 2,
            );
        }

        $views[$key]['data'][$date] = (int) $row['daily_views'];
        $conversions[$key]['data'][$date] = (int) $row['daily_conversions'];
    }

    // Views first, then conversions, so each pair sits next to its own colour,
    // and always in variation order so the legend does not shuffle between loads.
    ksort($views);
    $datasets = array();
    foreach ($views as $key => $dataset) {
        $datasets[] = $dataset;
        $datasets[] = $conversions[$key];
    }

    return array('labels' => $labels, 'datasets' => $datasets);
}

function convertpro_get_chart_data()
{
    // Reporting is admin-only: enforce capability and nonce (CVE-2025-63031).
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('You are not allowed to access this resource.', 'convertpro'), 403);
    }
    check_ajax_referer('convertpro-report-nonce', 'nonce');

    if (isset($_GET['range'])) {
        $test_id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : false;
        // Handle AJAX request to fetch data based on selected date range
        $range =  isset($_GET['range']) ? sanitize_text_field(wp_unslash($_GET['range'])) : '';

        $results = convertpro_interactions_chart_query($test_id, $range);

        wp_send_json(convertpro_build_chart_datasets($results));
    }
}

// Hook the AJAX handler function to a WordPress AJAX action
add_action('wp_ajax_convertpro_get_chart_data', 'convertpro_get_chart_data');


function convertpro_get_views($test_id, $variation_id, $range = 7)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'convertpro_interactions';

    // Every participant row counts as a view — a visitor who later converted
    // still saw the variation — so this deliberately does not filter on `type`.
    // Rows are dated by `created_at` (when the visitor was assigned); using
    // `updated_at` would move a view into the day its conversion happened.
    $views_query = "";
    $views_placeholders = [];
    $views_query .= "SELECT COUNT(*) FROM {$table_name} WHERE splittest_id = %d AND variation_id = %d AND run = %d";
    $views_placeholders[] = $test_id;
    $views_placeholders[] = $variation_id;
    $views_placeholders[] = convertpro_get_test_run($test_id);

    if ($range != 'all') {

        $entry_date = convertpro_entry_date_sql($table_name);
        $views_query .= " AND {$entry_date} <= NOW()
        AND {$entry_date} >= DATE_SUB(NOW(), INTERVAL %s DAY)";
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
    $conversion_query .= "SELECT COUNT(*) FROM {$table_name} WHERE type = 'conversion' AND splittest_id = %d AND variation_id = %d AND run = %d";
    $conversion_placeholders[] = $test_id;
    $conversion_placeholders[] = $variation_id;
    $conversion_placeholders[] = convertpro_get_test_run($test_id);

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

// Element conversions are recorded by ElementRedirection::update_conversion()
// on the goal page itself. There used to be an admin-ajax route here that did
// the same write for logged-out visitors, guarded only by a referer check and
// blind to which run the visitor belonged to. Nothing had called it since the
// front-end code was commented out, so it has been removed rather than left
// sitting there as an unauthenticated write.
