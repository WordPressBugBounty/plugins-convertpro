<?php

namespace ConvertPro;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


use ConvertPro\Controller\Controller;

/**
 * Admin Pages Handler
 */
class Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * The admin screen our own assets belong on.
     *
     * @var string
     */
    private $hook = '';

    /**
     * Register our menu page
     *
     * @return void
     */
    public function admin_menu()
    {
        global $submenu;

        $capability = 'manage_options';
        $slug       = 'convertpro-settings';

        $hook = add_menu_page(__('EasyTest', 'convertpro'), __('EasyTest', 'convertpro'), $capability, $slug, [$this, 'ab_tester_settings'], 'dashicons-text');

        $this->hook = $hook;
        // add_submenu_page($slug, __('Settings', 'convertpro'), __('Settings', 'convertpro'), $capability, 'convertpro-settings', [$this, 'ab_tester_settings']);
        // if (current_user_can($capability)) {
        //     $submenu[$slug][] = array(__('App', 'convertpro'), $capability, 'admin.php?page=' . $slug . '#/');
        //     $submenu[$slug][] = array(__('Settings', 'convertpro'), $capability, 'admin.php?page=' . $slug . '#/settings');
        // }

        // add_action('load-' . $hook, [$this, 'init_hooks']);
    }


    /**
     * Load scripts and styles for the app
     *
     * @return void
     */
    public function enqueue_scripts($hook_suffix = '')
    {
        // Only on our own screen. These were loading on every page of wp-admin,
        // which meant a stylesheet with a plain `p` rule restyling other people's
        // screens, select2 downloaded for nothing, and a query for every
        // published slug on the site running on every admin request.
        if ($hook_suffix !== $this->hook) {
            return;
        }

        wp_enqueue_style('convertpro-admin');
        wp_enqueue_style('select2-style');
        wp_enqueue_script('test-variations-admin');
        wp_enqueue_script('ab-tester-select2');

        wp_localize_script('test-variations-admin', 'convertproForm', $this->form_validation_data());
        // wp_enqueue_script('test-variations-admin', CONVERTPRO_ASSETS . '/js/test-variation.js', ['jquery'], CONVERTPRO_VERSION, true);
    }

    /**
     * Everything the test form needs to check itself as the user types.
     *
     * Passing the taken slugs and URLs up front means the form can point out a
     * clash straight away instead of waiting for a save to bounce back.
     *
     * @return array
     */
    private function form_validation_data()
    {
        global $wpdb;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $editing = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $taken_slugs = $wpdb->get_col("SELECT post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post')");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $taken_uris = $wpdb->get_col(
            $wpdb->prepare("SELECT test_uri FROM {$wpdb->prefix}convertpro WHERE id != %d AND test_uri != ''", $editing)
        );

        return array(
            'takenSlugs' => array_values(array_filter((array) $taken_slugs)),
            'takenUris' => array_values(array_filter((array) $taken_uris)),
            'i18n' => array(
                'nameMissing' => __('Give the test a name so you can recognise it later.', 'convertpro'),
                'typeMissing' => __('Pick whether you are testing whole pages or an element.', 'convertpro'),
                'uriMissing' => __('Add a test URL. This is the link you share.', 'convertpro'),
                'uriSlash' => __('No slashes here. Use letters, numbers, dashes and underscores.', 'convertpro'),
                'uriTakenContent' => __('You already have a page at this address. Pick something else, or that page would start redirecting people.', 'convertpro'),
                'uriTakenTest' => __('Another test already uses this URL.', 'convertpro'),
                'pageMissing' => __('Choose the page this version sends visitors to.', 'convertpro'),
                'pageDuplicate' => __('Another version already uses this page, so there would be nothing to compare.', 'convertpro'),
                'pageIsConversion' => __('This is your conversion page, so everyone would convert the moment they arrive.', 'convertpro'),
                'classMissing' => __('Add a CSS class, then paste it onto the matching element in your page builder.', 'convertpro'),
                'classDuplicate' => __('Another version already uses this class. Each one needs its own.', 'convertpro'),
                'percentRange' => __('Use a share between 1 and 100.', 'convertpro'),
                /* translators: %s: current total of the shares. */
                'percentTotal' => __('The shares add up to %s. They need to total 100.', 'convertpro'),
                'conversionMissing' => __('Choose the page that counts as a conversion.', 'convertpro'),
                'selectorMissing' => __('Add what to watch for a click, such as .buy-now or part of the link address.', 'convertpro'),
                'needTwo' => __('A test needs at least two versions to compare.', 'convertpro'),
                'fixBeforeSaving' => __('Fix the highlighted fields before saving.', 'convertpro'),
            ),
        );
    }
    /**
     * ab_tester_settings
     * settings page include
     * @return void
     */
    public function ab_tester_settings()
    {
        $testcon = new Controller();
        $testcon->Run();
    }

    /**
     * Render our admin page
     *
     * @return void
     */
    public function plugin_page()
    {
        echo '<div class="wrap"><div id="vue-admin-app">Heelo</div></div>';
    }

    // admin fronted js
}
