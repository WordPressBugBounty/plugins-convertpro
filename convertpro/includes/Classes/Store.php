<?php

namespace ConvertPro\Classes;
if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}


class Store
{

    /**
     * Check a submitted test before it reaches the database.
     *
     * The form checks some of this in the browser, but that check disappears the
     * moment JavaScript fails or someone posts directly, and a test saved with a
     * broken split or a missing page fails silently on the live site.
     *
     * @param int $test_id Test being updated, or 0 when creating.
     * @return string Empty string when the test is fine, otherwise a message key.
     */
    private function validate_test($test_id = 0)
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.
        $type = isset($_POST['convertpro-test-type']) ? sanitize_text_field(wp_unslash($_POST['convertpro-test-type'])) : '';

        if (!in_array($type, array('pages', 'elements'), true)) {
            return 'error_type_missing';
        }

        // Only when creating. An existing test can always be saved again, even on
        // a site that is at its limit — otherwise someone could be locked out of
        // editing what they already have.
        if (!$test_id && !convertpro_can_add_test()) {
            convertpro_record_cap_blocked('tests');

            return 'error_test_limit';
        }

        if (empty($_POST['test-name']) || '' === trim(sanitize_text_field(wp_unslash($_POST['test-name'])))) {
            return 'error_name_missing';
        }

        $goal = isset($_POST['test-conversion-type']) ? sanitize_text_field(wp_unslash($_POST['test-conversion-type'])) : 'page';
        $conversion_page = isset($_POST['test-conversion-page']) ? sanitize_text_field(wp_unslash($_POST['test-conversion-page'])) : '';

        if ('click' === $goal) {
            $selector = isset($_POST['test-conversion-selector']) ? trim(wp_strip_all_tags(wp_unslash($_POST['test-conversion-selector']))) : '';

            if ('' === $selector) {
                return 'error_selector_missing';
            }

            // Nothing else can be compared against the conversion page, because
            // a click goal does not have one.
            $conversion_page = '';
        } elseif ('' === $conversion_page || 'null' === $conversion_page || !get_post((int) $conversion_page)) {
            return 'conversion_page_missing';
        }

        if ('pages' === $type) {
            $uri = isset($_POST['test-uri']) ? trim(sanitize_text_field(wp_unslash($_POST['test-uri'])), '/ ') : '';

            if ('' === $uri) {
                return 'error_uri_missing';
            }

            if (strpos($uri, '/') !== false) {
                return 'error_test_page_invalid_chars';
            }

            // An identifier that matches real content would turn that page into a
            // redirector, so refuse it rather than quietly breaking the site.
            if (get_page_by_path($uri, OBJECT, array('page', 'post'))) {
                return 'error_uri_taken_by_content';
            }

            if ($this->uri_used_by_another_test($uri, $test_id)) {
                return 'error_uri_taken_by_test';
            }
        }

        $variations = (isset($_POST['test-variation']) && is_array($_POST['test-variation']))
            ? wp_unslash($_POST['test-variation'])
            : array();

        if (count($variations) < 2) {
            return 'error_need_two_variations';
        }

        $variation_limit = convertpro_free_limit('variations');

        // Compared against what the test already has, so a test built before the
        // limit applied can still be saved with the versions it already had.
        if ($variation_limit && count($variations) > max($variation_limit, $this->saved_variation_count($test_id))) {
            convertpro_record_cap_blocked('variations');

            return 'error_variation_limit';
        }

        $total = 0;
        $pages = array();
        $classes = array();

        foreach ($variations as $variation) {
            $percentage = isset($variation['percentage']) ? (int) $variation['percentage'] : 0;

            if ($percentage < 1 || $percentage > 100) {
                return 'error_percentage_range';
            }

            $total += $percentage;

            if ('pages' === $type) {
                $page_id = isset($variation['page-id']) ? (int) $variation['page-id'] : 0;

                if (!$page_id || !get_post($page_id)) {
                    return 'error_variation_page_missing';
                }

                if (in_array($page_id, $pages, true)) {
                    return 'error_variation_page_duplicate';
                }

                if ($page_id === (int) $conversion_page) {
                    return 'error_conversion_is_variation';
                }

                $pages[] = $page_id;
            } else {
                $class = isset($variation['customclass']) ? trim(sanitize_text_field($variation['customclass'])) : '';

                if ('' === $class) {
                    return 'error_variation_class_missing';
                }

                // The engine uses this as a CSS selector. A class of `*` or
                // `a, body` selects far more than the element it was meant for,
                // and removing what it selects can empty the page.
                if ('' === convertpro_safe_class_name($class)) {
                    return 'error_variation_class_invalid';
                }

                if (in_array($class, $classes, true)) {
                    return 'error_variation_class_duplicate';
                }

                $classes[] = $class;
            }
        }

        if (100 !== $total) {
            return 'error_percentage_total';
        }

        return '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Variation ids currently saved against a test.
     *
     * @param int $test_id
     * @return int[]
     */
    private function existing_variation_ids($test_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}convertpro_variations WHERE splittest_id = %d",
                (int) $test_id
            )
        );

        return array_map('intval', (array) $ids);
    }

    /**
     * The split a test is saved with: version id => share.
     *
     * Compared against what was submitted to tell a real change to the split from
     * a save that only renamed something.
     *
     * @param int $test_id
     * @return array
     */
    private function saved_shares($test_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, percentage FROM {$wpdb->prefix}convertpro_variations WHERE splittest_id = %d",
                (int) $test_id
            )
        );

        $shares = array();

        foreach ((array) $rows as $row) {
            $shares[(int) $row->id] = (int) $row->percentage;
        }

        return $shares;
    }

    /**
     * Start every version's quota again from its share.
     *
     * Used when the split has changed, so the traffic people get from here on is
     * the split that is now on screen rather than a half-spent version of the old
     * one.
     *
     * @param int $test_id
     * @return void
     */
    private function restart_cycle($test_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}convertpro_variations
                SET remaining = percentage
                WHERE splittest_id = %d",
                (int) $test_id
            )
        );
    }

    /**
     * Remove a version, and the interactions recorded against it.
     *
     * Leaving the interactions behind would keep them in the totals for a version
     * nobody can see any more.
     *
     * @param int $variation_id
     * @param int $test_id
     * @return void
     */
    private function delete_variation($variation_id, $test_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'convertpro_variations',
            array('id' => (int) $variation_id, 'splittest_id' => (int) $test_id),
            array('%d', '%d')
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'convertpro_interactions',
            array('variation_id' => (int) $variation_id, 'splittest_id' => (int) $test_id),
            array('%d', '%d')
        );
    }

    /**
     * How many versions this test already has saved.
     *
     * @param int $test_id 0 when creating.
     * @return int
     */
    private function saved_variation_count($test_id)
    {
        if (!$test_id) {
            return 0;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}convertpro_variations WHERE splittest_id = %d",
                (int) $test_id
            )
        );
    }

    /**
     * Is this test URL already claimed by a different test?
     *
     * @param string $uri
     * @param int    $test_id Test being saved, excluded from the check.
     * @return bool
     */
    private function uri_used_by_another_test($uri, $test_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}convertpro WHERE test_uri = %s AND id != %d",
                $uri,
                (int) $test_id
            )
        );
    }

    /**
     * Send the user back to the form they came from, with the problem explained.
     *
     * @param string $message Message key.
     * @param int    $test_id Test being edited, or 0 when creating.
     * @return void
     */
    private function bail($message, $test_id = 0)
    {
        $url = $test_id
            ? admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . (int) $test_id)
            : admin_url('admin.php?page=convertpro-settings&scope=test&action=create');

        wp_redirect($url . '&message=' . rawurlencode($message));
        exit;
    }

    /**
     * insert all value into
     * database
     * @return void
     */
    public function RepoStore()
    {
        // Nothing is kept from an unverified request: a forged post that failed
        // the nonce must not come back pre-filled on the next form this person
        // opens. They get the form and a fresh nonce, not their attacker's text.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce') || !current_user_can('manage_options')) {
            $this->bail('security_error');
        }

        if (!isset($_POST['test-id'])) {
            // LOW@kberlau Log Error
            $this->bail('error_update_data_missing');
        }
        $problem = $this->validate_test();

        if ($problem) {
            convertpro_stash_form();
            $this->bail($problem);
        }

        // Proceed with data storage
        $db = new Storedatabase();
        $id = (int) $db->CreateTest();

        // No id means the insert did not happen. Writing the versions anyway
        // would file them against test 0, and the success message would point at
        // a test that is not there.
        if (!$id) {
            convertpro_stash_form();
            $this->bail('error_test_not_saved');
        }

        if (isset($_POST['test-variation']) && is_array($_POST['test-variation'])) {
            $test_variations = wp_unslash($_POST['test-variation']);
            foreach ($test_variations as $variation) {
                $variation['pageId'] = isset($variation['page-id']) ? (int)($variation['page-id']) : '';
                $db->CreateTestVariation($id, $variation);
            }
        }
        // Check if the data was stored successfully
        wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . $id . '&message=store_success'));
        exit;
    }

    /**
     * delete value from database
     * by the id
     * @return void
     */
    public function RepoDelete()
    {
        // write a code here
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce') || !current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=security_error'));
            exit;
        }
        if (!isset($_GET['id'])) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=error_delete'));
            exit;
        }

        $id = sanitize_text_field(wp_unslash($_GET['id']));

        $db = new Repo();
        $db->TestDelete($id);

        wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=test&action=index&message=delete_success'));
        exit;
    }

    /**
     * Pause a running test, or start a paused one again.
     *
     * Pausing leaves the test and everything it has collected alone; visitors
     * simply stop being sorted into it. It is the answer to "I want this to stop"
     * that does not involve deleting the results.
     *
     * @return void
     */
    public function RepoToggleActive()
    {
        if (!isset($_GET['id'])) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=error_delete'));
            exit;
        }

        $id = (int) $_GET['id'];

        if (!isset($_GET['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'convertpro-toggle-test_' . $id)
            || !current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=security_error'));
            exit;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $active = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT active FROM {$wpdb->prefix}convertpro WHERE id = %d", $id)
        );

        $now_active = $active ? 0 : 1;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'convertpro',
            array('active' => $now_active),
            array('id' => $id)
        );

        wp_redirect(admin_url('admin.php?page=convertpro-settings&message=' . ($now_active ? 'resume_success' : 'pause_success')));
        exit;
    }

    /**
     * Start a fresh run of a test.
     *
     * Bumps the test's run number, which changes the assignment cookie names so
     * visitors who were already bucketed get re-assigned, and keeps the previous
     * round's interactions intact under the old run number.
     *
     * @return void
     */
    /**
     * Record what someone did with the review ask, then send them on.
     *
     * "Happy" and "not happy" both end up at the same place: the report, with
     * the review link and, for the unhappy, the support link. We are not
     * deciding who is allowed to rate the plugin — see docs/review-prompt-plan.md.
     *
     * @return void
     */
    public function RepoReview()
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if (!isset($_GET['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'convertpro-review')
            || !current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=security_error'));
            exit;
        }

        $answer = isset($_GET['answer']) ? sanitize_key(wp_unslash($_GET['answer'])) : '';

        if (!in_array($answer, array('happy', 'unhappy', 'later', 'dismissed', 'clicked'), true)) {
            $answer = 'dismissed';
        }

        $state = convertpro_review_state();
        $changes = array();

        if (!$state['asked_at']) {
            $changes['asked_at'] = time();
        }

        if ('clicked' === $answer) {
            // Going to WordPress.org. All we ever learn is that they went.
            $changes['clicked_at'] = time();
        } else {
            $changes['answer'] = $answer;
            $changes['answered_at'] = time();
        }

        convertpro_review_update($changes);

        if ('clicked' === $answer) {
            wp_redirect(convertpro_review_url());
            exit;
        }

        $back = $id
            ? admin_url('admin.php?page=convertpro-settings&scope=test&action=report&id=' . $id)
            : admin_url('admin.php?page=convertpro-settings');

        wp_redirect($back);
        exit;
    }

    public function RepoReset()
    {
        if (!isset($_GET['id'])) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=error_reset'));
            exit;
        }

        $id = (int) $_GET['id'];

        if (!isset($_GET['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'convertpro-reset-test_' . $id)
            || !current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=convertpro-settings&message=security_error'));
            exit;
        }

        global $wpdb;

        // Next run: previously collected data stays behind under the old run.
        $run = convertpro_get_test_run($id) + 1;
        update_option('convertpro_test_run_' . $id, $run);

        // Refill each variation's quota so the new run starts from a clean split.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}convertpro_variations SET remaining = percentage WHERE splittest_id = %d",
                $id
            )
        );

        wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=statistics&action=report&id=' . $id . '&message=reset_success'));
        exit;
    }

    /**
     * update test repo
     * from database
     * @return void
     */
    public function Repoupdate()
    {
        // write a code here
        $test_id = isset($_POST['test-id']) ? (int) $_POST['test-id'] : 0;

        // As in RepoStore: an unverified post is answered with a form and a
        // fresh nonce, never with the values it carried.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'convertpro-nonce') || !current_user_can('manage_options')) {
            $this->bail('security_error', $test_id);
        }
        if (!isset($_POST['test-id'])) {
            $this->bail('error_update_data_missing');
        }

        $problem = $this->validate_test($test_id);

        if ($problem) {
            convertpro_stash_form($test_id);
            $this->bail($problem, $test_id);
        }

        $db = new Storedatabase();
        $db->updateTest($test_id);

        // Rows that came back with an id are updated, rows without one are new,
        // and anything the form no longer carries was removed. Before this, every
        // row was pushed through updateTestVariation() — including new ones, which
        // arrive with an empty id and so updated row 0, meaning nothing. Adding a
        // version to a test that already existed silently did nothing, and
        // removing one only removed it from the screen.
        $submitted = (isset($_POST['test-variation']) && is_array($_POST['test-variation']))
            ? wp_unslash($_POST['test-variation'])
            : array();

        $before = $this->saved_shares($test_id);
        $existing = array_keys($before);
        $kept = array();
        $after = array();

        foreach ($submitted as $variation) {
            $variation['postId'] = isset($variation['page-id']) ? (int) $variation['page-id'] : 0;
            $variation['pageId'] = $variation['postId'];
            $variation_id = isset($variation['id']) ? (int) $variation['id'] : 0;

            if ($variation_id && in_array($variation_id, $existing, true)) {
                $db->updateTestVariation($variation_id, $variation);
                $kept[] = $variation_id;
                $after[$variation_id] = (int) $variation['percentage'];
                continue;
            }

            $db->CreateTestVariation($test_id, $variation);
        }

        foreach (array_diff($existing, $kept) as $removed) {
            $this->delete_variation($removed, $test_id);
        }

        // Traffic is handed out from `remaining`, the share each version has left
        // in the current cycle. If the split changed at all — a share edited, a
        // version added, a version removed — the cycle in progress belongs to the
        // old split, and leaving the untouched versions part-way through it means
        // the traffic people actually get is neither the old split nor the new
        // one. Restart the whole cycle, or leave it entirely alone.
        if ($before !== $after) {
            $this->restart_cycle($test_id);
        }

        wp_redirect(admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . $test_id . '&message=save_success'));
        exit;
    }
}
