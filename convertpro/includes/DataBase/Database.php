<?php

namespace ConvertPro\DataBase;

/**
 * create all database table
 * in this class
 */
class Database
{

    public function __construct()
    {
        $this->DatabaseTable();
    }
    private function DatabaseTable()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'convertpro';
        $sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			active tinyint(1) NOT NULL,
			name varchar(255) NOT NULL,
            test_type varchar(32) NOT NULL,
			test_uri varchar(255) NULL DEFAULT NULL,
			conversion_type varchar(32) NOT NULL,
			conversion_page_id int(11) NULL,
			conversion_url varchar(511) NULL DEFAULT NULL,
			created_at timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
        dbDelta($sql);

        $table_name = $wpdb->prefix . 'convertpro_variations';
        $sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			active tinyint(1) NOT NULL,
			name varchar(255) NOT NULL,
			percentage int(3) NOT NULL,
			page_id int(11) NULL DEFAULT NULL,
            remaining int NOT NUll DEFAULT '0',
            slot_status VARCHAR(255) DEFAULT 'active',
			splittest_id int(11) NOT NULL,
            class_name varchar(255) NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
        dbDelta($sql);

        $table_name = $wpdb->prefix . 'convertpro_interactions';
        $sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			client_id varchar(46) NOT NULL,
			type enum('view','conversion') NOT NULL,
			splittest_id int(11) NOT NULL,
			variation_id int(11) NOT NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
        dbDelta($sql);
    }
}
