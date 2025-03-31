<?php

namespace ConvertPro\Classes;

class Store
{

    /**
     * insert all value into
     * database
     * @return void
     */
    public function RepoStore()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce') || !is_user_logged_in()) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=security_error'));
            exit;
        }

        if (!isset($_POST['test-id'])) {
            // LOW@kberlau Log Error
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=error_update_data_missing'));
            return;
        }
        if (isset($_POST['convertpro-test-type']) && $_POST['convertpro-test-type'] == "pages") {
            if (isset($_POST['test-uri']) && strpos(sanitize_text_field(wp_unslash($_POST['test-uri'])), '/') !== false) {
                wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=test&action=create&message=error_test_page_invalid_chars'));
                return;
            }
        }

        // Proceed with data storage
        $db = new Storedatabase();
        $id = $db->CreateTest();
        if (isset($_POST['test-variation']) && is_array($_POST['test-variation'])) {
            $test_variations = isset($_POST['test-variation']) && is_array($_POST['test-variation']) ? $_POST['test-variation'] : [];
            foreach ($test_variations as $variation) {
                $variation['pageId'] = isset($variation['page-id']) ? (int)($variation['page-id']) : '';
                $db->CreateTestVariation($id, $variation);
            }
        }
        // Check if the data was stored successfully
        wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . $id . '&message=store_success'));
    }

    /**
     * delete value from database
     * by the id
     * @return void
     */
    public function RepoDelete()
    {
        // write a code here
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce') || !is_user_logged_in()) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=security_error'));
            exit;
        }
        if (!isset($_GET['id'])) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=error_delete'));
        }

        $id = sanitize_text_field(wp_unslash($_GET['id']));

        $db = new Repo();
        $db->TestDelete($id);

        wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=test&action=index&message=delete_success'));
    }

    /**
     * update test repo
     * from database
     * @return void
     */
    public function Repoupdate()
    {
        // write a code here
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce') || !is_user_logged_in()) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=security_error'));
            return;
        }
        if (!isset($_POST['test-id'])) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=error_update_data_missing'));
            return;
        }

        if (isset($_POST['test-conversion-page']) && $_POST['test-conversion-page'] == "" || $_POST['test-conversion-page'] == null || $_POST['test-conversion-page'] == "null") {
            // LOW@kberlau Log Error
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=conversion_page_missing'));
            return;
        }

        $db = new Storedatabase();

        $test_id = sanitize_text_field(wp_unslash($_POST['test-id']));
        $db->updateTest($test_id);
        if (isset($_POST['test-variation']) && is_array($_POST['test-variation'])) {
            $test_variations = isset($_POST['test-variation']) && is_array($_POST['test-variation']) ? $_POST['test-variation'] : [];
            foreach ($test_variations as $variation) {

                $variation['postId'] = (int) $variation['page-id'];

                if ((int) $variation['id'] !== null) {
                    $db->updateTestVariation((int) $variation['id'], $variation);
                }
            }
        }

        wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . $test_id . '&message=save_success'));
    }
}
