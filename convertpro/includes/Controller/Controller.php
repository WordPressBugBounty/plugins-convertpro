<?php

namespace ConvertPro\Controller;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


use ConvertPro\Classes\Repo;

class Controller
{
    public function Run()
    {
        // write a code here
        // phpcs:ignore
        $action = isset($_GET['action']) ? $_GET['action'] : "index";

        switch ($action) {
            case "index":
                $this->index();
                break;
            case "create":
                $this->create();
                break;
            case "edit":
                $this->edit();
                break;
            case "report":
                $this->report();
                break;
            default:
                $this->index();
                break;
        }
    }

    /**
     * index view showing
     *
     * @return void
     */
    public function index()
    {
        // write a code here
        $repo = new Repo();
        $tests = $repo->getAlltests();
        require_once CONVERTPRO_INCLUDES . '/Template/index-view.php';
    }

    /**
     * create new test
     *
     * @return void
     */
    public function create()
    {
        // write a code here
        $pages = get_pages();
        require_once CONVERTPRO_INCLUDES . '/Template/create-view.php';
    }

    /**
     * edit function
     *
     * @return void
     */
    public function edit()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which test to show, capability checked by the menu.
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $repo = new Repo();
        $test = $id > 0 ? $repo->gettestvalue($id) : null;

        // Rather than an empty screen when the link is wrong or the test has since
        // been deleted, say so and point back at the list.
        if (!$test) {
            printf(
                '<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('That test is no longer here.', 'convertpro'),
                esc_url(admin_url('admin.php?page=convertpro-settings')),
                esc_html__('Back to all tests', 'convertpro')
            );
            return;
        }

        $pages = get_pages();

        require_once CONVERTPRO_INCLUDES . '/Template/edit-view.php';
    }

    /**
     * report
     */
    public function report()
    {
        // write a code here
        require_once CONVERTPRO_INCLUDES . '/Template/report-view.php';
    }
}
