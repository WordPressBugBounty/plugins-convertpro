<?php

namespace ConvertPro\Classes;

class Init
{
    public function init()
    {
        // write a code here
        if (!is_admin()) {
            return;
        }

        // phpcs:ignore
        if (!isset($_GET['page'])) {
            return;
        }
        // phpcs:ignore
        if (($_GET['page'] != "convertpro-settings")) {
            return;
        }
        // phpcs:ignore
        if (!isset($_GET['scope'])) {
            return;
        }
        // phpcs:ignore
        if ($_GET['scope'] == "test") {

            $controller = new Store();
            // phpcs:ignore
            if ($_GET['action'] == "store") {
                $controller->RepoStore();
                // phpcs:ignore
            } else if ($_GET['action'] == "delete") {
                $controller->RepoDelete();
                // phpcs:ignore
            } else if ($_GET['action'] == "update") {
                $controller->Repoupdate();
            }
        }
    }
}
