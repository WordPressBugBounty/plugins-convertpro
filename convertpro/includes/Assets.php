<?php

namespace ConvertPro;

/**
 * Scripts and Styles Class
 */
class Assets
{

    function __construct()
    {

        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'register'], 5);
        } else {
            add_action('wp_enqueue_scripts', [$this, 'register'], 5);
        }
    }

    /**
     * Register our app scripts and styles
     *
     * @return void
     */
    public function register()
    {
        $this->register_scripts($this->get_scripts());
        $this->register_styles($this->get_styles());
    }

    /**
     * Register scripts
     *
     * @param  array $scripts
     *
     * @return void
     */
    private function register_scripts($scripts)
    {
        foreach ($scripts as $handle => $script) {
            $deps      = isset($script['deps']) ? $script['deps'] : false;
            $in_footer = isset($script['in_footer']) ? $script['in_footer'] : false;
            $version   = isset($script['version']) ? $script['version'] : CONVERTPRO_VERSION;

            wp_register_script($handle, $script['src'], $deps, $version, $in_footer);
        }
    }

    /**
     * Register styles
     *
     * @param  array $styles
     *
     * @return void
     */
    public function register_styles($styles)
    {
        foreach ($styles as $handle => $style) {
            $deps = isset($style['deps']) ? $style['deps'] : false;

            wp_register_style($handle, $style['src'], $deps, CONVERTPRO_VERSION);
        }
    }

    /**
     * Get all registered scripts
     *
     * @return array
     */
    public function get_scripts()
    {
        $prefix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.min' : '';

        $scripts = [

            'test-variations-admin' => [
                'src'       => CONVERTPRO_ASSETS . '/js/test-variation.js',
                'deps'      => ['jquery'],
                'version'   => CONVERTPRO_VERSION,
                'in_footer' => true
            ],
            'chart' => [
                'src'       => 'https://cdn.jsdelivr.net/npm/chart.js',
                'deps'      => ['jquery'],
                'version'   => CONVERTPRO_VERSION,
                'in_footer' => true
            ],
            'ab-tester-select2' => [
                'src'       => CONVERTPRO_ASSETS . '/js/select2.min.js',
                'deps'      => ['jquery'],
                'version'   => '4.1.0',
                'in_footer' => true
            ],


        ];

        return $scripts;
    }

    /**
     * Get registered styles
     *
     * @return array
     */
    public function get_styles()
    {

        $styles = [
            'convertpro-style' => [
                'src' =>  CONVERTPRO_ASSETS . '/css/style.css'
            ],
            'convertpro-frontend' => [
                'src' =>  CONVERTPRO_ASSETS . '/css/frontend.css'
            ],
            'convertpro-admin' => [
                'src' =>  CONVERTPRO_ASSETS . '/css/admin.css'
            ],
            'select2-style' => [
                'src' =>  CONVERTPRO_ASSETS . '/css/select2.min.css'
            ],
        ];

        return $styles;
    }
}
