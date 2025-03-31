<?php

namespace ConvertPro\Controller;

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
        // write a code here
        // phpcs:ignore
        $id = $_GET['id'];
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return "Wrong Test Id";
        }
        $repo = new Repo();
        $test = $repo->gettestvalue($id);

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
