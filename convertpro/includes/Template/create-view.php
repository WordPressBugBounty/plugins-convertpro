<?php

if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}
// This template is required from inside a Controller method, so the variables
// it sets are local to that method, not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$scope = "create";
$text = esc_html__("Create New Test", "convertpro");
$test = (object) [
    'name' => '',
    'variations' =>  [
        (object) [
            'id' => 'null',
            'name' => 'v1',
            'percentage' => '50',
            'class_name' => uniqid('celm-')
        ],
        (object) [
            'id' => null,
            'name' => 'v2',
            'percentage' => '50',
            'class_name' => uniqid('celm-')
        ]
    ]
];


include(__DIR__ . "/form-view.php");
