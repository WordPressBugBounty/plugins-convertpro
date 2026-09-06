<?php
use Finestics\Client;

/*
 * Plugin Name: EasyTest - Simplify A/B Testing (Former ConvertPro)
 * Plugin URI: https://wpgrids.com/
 * Description: EasyTest allows you to ab testing.
 * Version: 1.0.4
 * Author: wpgrids
 * Author URI: https://profiles.wordpress.org/wpgrids/
 * Text Domain: convertpro
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */


// don't call the file directly
if (!defined('ABSPATH'))
    exit;


require_once __DIR__ . '/vendor/autoload.php';

use ConvertPro\Assets;
use ConvertPro\DataBase\Database;
use ConvertPro\Classes\Init;
use ConvertPro\Classes\Redirection;
use ConvertPro\Classes\ElementRedirection;

/**
 * ConvertPro class
 *
 * @class ConvertPro The class that holds the entire ConvertPro plugin
 */
final class ConvertPro
{

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '1.0.4';

    /**
     * Holds various class instances
     *
     * @var array
     */
    private $container = array();

    /**
     * Constructor for the ConvertPro class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     */
    public function __construct()
    {

        $this->define_constants();

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }
    /**
     * Initializes the ConvertPro() class
     *
     * Checks for an existing ConvertPro() instance
     * and if it doesn't find one, creates it.
     */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new ConvertPro();
        }

        return $instance;
    }

    /**
     * Magic getter to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __get($prop)
    {
        if (array_key_exists($prop, $this->container)) {
            return $this->container[$prop];
        }

        return $this->{$prop};
    }

    /**
     * Magic isset to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __isset($prop)
    {
        return isset($this->{$prop}) || isset($this->container[$prop]);
    }

    /**
     * Define the constants
     *
     * @return void
     */
    public function define_constants()
    {
        define('CONVERTPRO_VERSION', $this->version);
        define('CONVERTPRO_FILE', __FILE__);
        define('CONVERTPRO_PATH', dirname(CONVERTPRO_FILE));
        define('CONVERTPRO_INCLUDES', CONVERTPRO_PATH . '/includes');
        define('CONVERTPRO_URL', plugins_url('', CONVERTPRO_FILE));
        define('CONVERTPRO_ASSETS', CONVERTPRO_URL . '/assets');
    }

    /**
     * Load the plugin after all plugis are loaded
     *
     * @return void
     */
    public function init_plugin()
    {
        $this->maybe_upgrade();

        new Assets();
        $init = new Init();
        $init->init();
        $this->boot_visitor_handlers();

        $this->includes();
        $this->init_hooks();

        if (!class_exists('Finestics\Client')) {
            require_once __DIR__. '/Finestics/Client.php';
        }

        $init_finestics = new Finestics\Client('convertpro', 'ConvertPro', __FILE__);

        // Rides the existing opt-in: nothing is sent unless the site owner turned
        // usage reporting on. Counts and timestamps only — see
        // convertpro_free_tier_usage().
        $init_finestics->insights()
            ->add_extra('convertpro_free_tier_usage')
            ->init();


    }

    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public function activate()
    {

        $installed = get_option('convertpro_installed');

        // Read before either option is written below, so a first-ever activation
        // is told apart from someone switching an old install back on.
        $this->remember_free_limits(get_option('convertpro_version'), $installed);

        new Database();
        if (!$installed) {
            update_option('convertpro_installed', time());
        }

        update_option('convertpro_version', CONVERTPRO_VERSION);
    }

    /**
     * Decide once, per site, whether the free limits apply here.
     *
     * Sites that were already using EasyTest keep everything they built — the
     * limits are for people installing it from now on. Taking working tests away
     * from someone who already has them is both a bad thing to do and, on
     * WordPress.org, not allowed.
     *
     * The answer is written once and never revisited, so nothing changes under a
     * site later.
     *
     * @param string|false $stored_version Version option as it was before this release.
     * @param int|false    $installed_at   Install timestamp as it was before this release.
     * @return void
     */
    public function remember_free_limits($stored_version, $installed_at)
    {
        // Nothing to be exempt from until the limits are actually in force, and
        // stamping early would do real harm: a site that installs during the hold
        // would be marked "new", build five tests under no limit, and then be
        // capped the day the limits switch on. Deciding at the moment they become
        // real means everything already out there is exempt, which is the promise.
        if (!array_filter(convertpro_free_limit_defaults())) {
            return;
        }

        if (false !== get_option('convertpro_free_limits', false)) {
            return;
        }

        // Every released version wrote both of these on activation, so either one
        // proves the site had EasyTest before.
        $already_here = (bool) $stored_version || (bool) $installed_at;

        // And if neither survived — options wiped, a migration, a restore that
        // brought the tables but not the options — tests in the table say the
        // same thing. Getting this wrong would cap someone who already built
        // more than the limit, so it is worth one query, once, ever.
        if (!$already_here) {
            $already_here = $this->has_existing_tests();
        }

        update_option('convertpro_free_limits', $already_here ? 'off' : 'on');
    }

    /**
     * Does this site already have tests, from before the limits existed?
     *
     * @return bool
     */
    private function has_existing_tests()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'convertpro';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Hook up the visitor-facing handlers, once each.
     *
     * These used to be created once per row in the tests table, so a site with
     * five tests registered five copies of the same hooks — five passes over the
     * test list on every request, and for element tests five nested output
     * buffers each re-parsing the whole page.
     *
     * @return void
     */
    public function boot_visitor_handlers()
    {
        if (is_admin()) {
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $types = $wpdb->get_col("SELECT DISTINCT test_type FROM {$wpdb->prefix}convertpro WHERE active = 1");

        if (in_array('elements', $types, true)) {
            new ElementRedirection();
        }

        if (array_diff($types, array('elements'))) {
            new Redirection();
        }
    }

    /**
     * Run install/upgrade routines when the stored version is behind the code.
     *
     * WordPress does not fire the activation hook when a plugin is updated, so
     * schema changes have to be applied from a version check on load.
     *
     * @return void
     */
    public function maybe_upgrade()
    {
        $stored = get_option('convertpro_version');

        // Before the early return: WordPress does not fire the activation hook on
        // an update, so for every site that upgrades into this release, this is
        // the only place we get to notice they were here first.
        $this->remember_free_limits($stored, get_option('convertpro_installed'));

        if ($stored === CONVERTPRO_VERSION) {
            return;
        }

        new Database();

        // Remember the last interaction recorded by the old, biased engine so the
        // report can warn about runs that still mix it with corrected data. Row
        // ids are used rather than a timestamp because the interaction times come
        // from MySQL's clock, which need not agree with the site's timezone.
        if ($stored && false === get_option('convertpro_engine_fix_max_id', false)) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $max_id = (int) $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}convertpro_interactions");
            update_option('convertpro_engine_fix_max_id', $max_id);
        }

        // The interactions table was first created with created_at defaulting to
        // '0000-00-00 00:00:00', and dbDelta does not change a default on a
        // column that already exists. Every row written on those installs has no
        // entry date, so the report falls back to updated_at — which moves when a
        // view is flipped to a conversion, dragging the visit into the day it
        // converted. Repair the default once; rows already written keep the
        // fallback, since their entry time cannot be recovered.
        if (!get_option('convertpro_created_at_default')) {
            global $wpdb;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query(
                "ALTER TABLE {$wpdb->prefix}convertpro_interactions
                MODIFY created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP"
            );

            update_option('convertpro_created_at_default', 1);
        }

        update_option('convertpro_looping_tests', $this->find_looping_tests());

        update_option('convertpro_version', CONVERTPRO_VERSION);
    }

    /**
     * Tests whose URL is a page that already exists.
     *
     * Saving one of these has been refused since 1.0.3, but rows saved before
     * that are still in the table and still send visitors round in a circle. The
     * redirect itself is skipped at runtime; this is what tells the owner which
     * test to go and fix.
     *
     * @return array
     */
    public function find_looping_tests()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $tests = $wpdb->get_results("SELECT id, name, test_uri FROM {$wpdb->prefix}convertpro WHERE test_type = 'pages' AND active = 1");

        $broken = array();

        foreach ((array) $tests as $test) {
            $uri = trim((string) $test->test_uri, '/ ');

            if ('' === $uri || get_page_by_path($uri, OBJECT, array('page', 'post'))) {
                $broken[] = array(
                    'id'   => (int) $test->id,
                    'name' => (string) $test->name,
                    'uri'  => $uri,
                );
            }
        }

        return $broken;
    }

    /**
     * Warn about tests that were sending visitors back to where they started.
     *
     * @return void
     */
    public function looping_tests_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stored = get_option('convertpro_looping_tests', array());

        if (empty($stored) || !is_array($stored)) {
            return;
        }

        // Re-check before saying anything, so the notice clears itself as soon as
        // the tests are fixed rather than waiting for the next update.
        $broken = $this->find_looping_tests();

        if (empty($broken)) {
            delete_option('convertpro_looping_tests');
            return;
        }

        update_option('convertpro_looping_tests', $broken);

        $links = array();

        foreach ($broken as $test) {
            $label = '' === $test['uri']
                ? sprintf(
                    /* translators: %s: test name. */
                    __('%s — no URL, so it was catching your home page', 'convertpro'),
                    $test['name']
                )
                : sprintf(
                    /* translators: 1: test name, 2: the test URL. */
                    __('%1$s — /%2$s/ is already a page', 'convertpro'),
                    $test['name'],
                    $test['uri']
                );

            $links[] = '<a href="' . esc_url(admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . $test['id'])) . '">' . esc_html($label) . '</a>';
        }

        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('EasyTest has stopped some of your tests', 'convertpro') . '</strong></p>';
        echo '<p>' . wp_kses_post(
            sprintf(
                /* translators: %s: list of links to the affected tests. */
                __('A test URL has to be an address of its own, with no page behind it. These point at pages people already visit, so visitors were being sent somewhere they never asked for. They stay off until each one has its own URL: %s', 'convertpro'),
                implode(', ', $links)
            )
        ) . '</p>';
        echo '<p>' . esc_html__('Pick something no page uses — summer-offer, say — and the test starts again.', 'convertpro') . '</p></div>';
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate()
    {
    }

    /**
     * Include the required files
     *
     * @return void
     */
    public function includes()
    {

        require_once CONVERTPRO_INCLUDES . '/Assets.php';

        if ($this->is_request('admin')) {
            require_once CONVERTPRO_INCLUDES . '/Admin.php';
        }

        if ($this->is_request('ajax')) {
            // require_once CONVERTPRO_INCLUDES . '/class-ajax.php';
        }
    }

    /**
     * Initialize the hooks
     *
     * @return void
     */
    public function init_hooks()
    {

        add_action('init', array($this, 'init_classes'));

        // Localize our plugin
        add_action('init', array($this, 'localization_setup'));

        if ($this->is_request('admin')) {
            add_action('admin_notices', array($this, 'looping_tests_notice'));
        }
        do_action('convertpro_init');
    }

    /**
     * Instantiate the required classes
     *
     * @return void
     */
    public function init_classes()
    {

        if ($this->is_request('admin')) {
            $this->container['admin'] = new ConvertPro\Admin();
        }

        if ($this->is_request('ajax')) {
            // $this->container['ajax'] =  new App\Ajax();
        }

        // $this->container['api'] = new AbTest\Api();
        $this->container['assets'] = new Assets();
    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup()
    {
        load_plugin_textdomain('convertpro', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * What type of request is this?
     *
     * @param  string $type admin, ajax, cron or frontend.
     *
     * @return bool
     */
    private function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();

            case 'ajax':
                return defined('DOING_AJAX');

            case 'rest':
                return defined('REST_REQUEST');

            case 'cron':
                return defined('DOING_CRON');

            case 'frontend':
                return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
        }
    }
} // ConvertPro

$convertpro = ConvertPro::init();
