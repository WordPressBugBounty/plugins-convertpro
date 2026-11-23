<?php

if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}
// This template is required from inside a Controller method, so the variables
// it sets are local to that method, not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$scope = "edit";
$text = "Update Test";
include(__DIR__ . "/form-view.php");
