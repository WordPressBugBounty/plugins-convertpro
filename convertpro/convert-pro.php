<?php
use Finestics\Client;

/*
 * Plugin Name: EasyTest - Simplify A/B Testing (Former ConvertPro)
 * Plugin URI: https://wpgrids.com/
 * Description: EasyTest allows you to ab testing.
 * Version: 1.0.1
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
    public $version = '1.0.1';

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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }
    public function enqueue_frontend_scripts()
    {
        // Enqueue your JavaScript file
        wp_enqueue_script(
            'frontent-script',
            plugin_dir_url(__FILE__) . 'assets/js/frontent-script.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script(
            'frontent-script',
            'convertpro_object',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('convertpro_nonce')
            )
        );
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
        new Assets();
        $init = new Init();
        $init->init();
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}convertpro");

        foreach ($results as $result) {
            if ($result->test_type == 'elements') {
                new ElementRedirection();
            } else {
                new Redirection();
            }
        }

        $this->includes();
        $this->init_hooks();

        if (!class_exists('Finestics\Client')) {
            require_once __DIR__. '/Finestics/Client.php';
        }

        $init_finestics = new Finestics\Client('convertpro', 'ConvertPro', __FILE__);
        $init_finestics->insights()->init();


    }

    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public function activate()
    {

        $installed = get_option('convertpro_installed');
        new Database();
        if (!$installed) {
            update_option('convertpro_installed', time());
        }

        update_option('convertpro_version', CONVERTPRO_VERSION);
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
