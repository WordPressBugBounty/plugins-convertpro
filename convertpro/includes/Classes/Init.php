<?php

namespace ConvertPro\Classes;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


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
            if (!isset($_GET['action'])) {
                return;
                // phpcs:ignore
            } else if ($_GET['action'] == "reset") {
                $controller->RepoReset();
                // phpcs:ignore
            } else if ($_GET['action'] == "toggle") {
                $controller->RepoToggleActive();
                // phpcs:ignore
            } else if ($_GET['action'] == "review") {
                $controller->RepoReview();
                // phpcs:ignore
            } else if ($_GET['action'] == "store") {
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
