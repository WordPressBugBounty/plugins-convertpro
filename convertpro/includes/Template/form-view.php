<?php

if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}

// A save that was turned away comes back as a fresh page load, so what someone
// typed only survives if it was put aside first. Fill the form from that when
// there is one, otherwise from the saved test as before. The draft is tied to
// the test it was typed against, so a rejected new test cannot reappear on a
// different test's form and be saved over it.
if (is_object($test)) {
    $convertpro_draft = convertpro_take_form_draft(isset($test->id) ? (int) $test->id : 0);

    if ($convertpro_draft) {
        $test->name = $convertpro_draft['name'];
        $test->test_type = $convertpro_draft['test_type'];
        $test->test_uri = $convertpro_draft['test_uri'];
        $test->conversion_type = $convertpro_draft['conversion_type'];
        $test->conversion_page_id = $convertpro_draft['conversion_page_id'];
        $test->conversion_url = $convertpro_draft['conversion_url'];

        if (!empty($convertpro_draft['variations'])) {
            $test->variations = array();

            foreach ($convertpro_draft['variations'] as $convertpro_row) {
                $test->variations[] = (object) $convertpro_row;
            }
        }
    }
}

if ($scope == "edit") {
    $formUrl = admin_url('admin.php?page=convertpro-settings&scope=test&action=update&id=' . $test->id);
} else {
    $formUrl = admin_url('admin.php?page=convertpro-settings&scope=test&action=store');
}
?>
<div class="meassage">
    <?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $convertpro_message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';

    $convertpro_notices = array(
        'save_success' => array('success', __('Saved.', 'convertpro')),
        'store_success' => array('success', __('Your test is ready. It starts running as soon as visitors arrive.', 'convertpro')),
        'error_name_missing' => array('error', __('Give the test a name so you can recognise it later.', 'convertpro')),
        'error_type_missing' => array('error', __('Pick whether you are testing whole pages or an element.', 'convertpro')),
        'conversion_page_missing' => array('error', __('Choose the page that counts as a conversion, so we know when a visitor succeeded.', 'convertpro')),
        'error_selector_missing' => array('error', __('Tell us what to watch for a click, such as a CSS class or part of the link address.', 'convertpro')),
        'error_uri_missing' => array('error', __('Add a test URL. This is the link you share, and it decides which version each visitor sees.', 'convertpro')),
        'error_test_page_invalid_chars' => array('error', __('The test URL cannot contain a slash. Use letters, numbers, dashes and underscores.', 'convertpro')),
        'error_uri_taken_by_content' => array('error', __('You already have a page or post at that address. Pick a different test URL, or the real page would start redirecting people.', 'convertpro')),
        'error_uri_taken_by_test' => array('error', __('Another test already uses that URL. Give this one its own address.', 'convertpro')),
        'error_need_two_variations' => array('error', __('A test needs at least two versions to compare.', 'convertpro')),
        'error_test_limit' => array('error', sprintf(
            /* translators: %d: number of tests the free plugin runs at once. */
            _n(
                'EasyTest runs %d test at a time. Delete one you are done with to make room.',
                'EasyTest runs %d tests at a time. Delete one you are done with to make room.',
                convertpro_free_limit('tests'),
                'convertpro'
            ),
            convertpro_free_limit('tests')
        )),
        'error_variation_limit' => array('error', sprintf(
            /* translators: %d: number of versions allowed per test. */
            _n('A test compares %d version.', 'A test compares %d versions — A against B.', convertpro_free_limit('variations'), 'convertpro'),
            convertpro_free_limit('variations')
        )),
        'error_percentage_range' => array('error', __('Each share has to be between 1 and 100.', 'convertpro')),
        'error_percentage_total' => array('error', __('The shares have to add up to 100. Right now they do not.', 'convertpro')),
        'error_variation_page_missing' => array('error', __('Every version needs a page to send visitors to.', 'convertpro')),
        'error_variation_page_duplicate' => array('error', __('Two versions point at the same page, so there would be nothing to compare. Pick a different page for one of them.', 'convertpro')),
        'error_conversion_is_variation' => array('error', __('The conversion page is also one of the versions being tested, so everyone would convert the moment they arrive. Choose a different page.', 'convertpro')),
        'error_variation_class_missing' => array('error', __('Every version needs a CSS class. Copy it into your page builder on the element you want to test.', 'convertpro')),
        'error_variation_class_duplicate' => array('error', __('Two versions use the same CSS class. Each one needs its own.', 'convertpro')),
        'error_variation_class_invalid' => array('error', __('A CSS class can only use letters, numbers, dashes and underscores — something like hero-version-a. Symbols such as * or a comma would match the wrong part of your page.', 'convertpro')),
        'security_error' => array('error', __('That request could not be verified. Please try again.', 'convertpro')),
        'error_update_data_missing' => array('error', __('Something was missing from that request. Please try again.', 'convertpro')),
        'error_test_not_saved' => array('error', __('The test could not be saved. Nothing was lost — everything you filled in is still here, so try again.', 'convertpro')),
    );

    if (isset($convertpro_notices[$convertpro_message])) :
        list($convertpro_notice_type, $convertpro_notice_text) = $convertpro_notices[$convertpro_message];
    ?>
        <div class="notice notice-<?php echo esc_attr($convertpro_notice_type); ?> is-dismissible">
            <p><?php echo esc_html($convertpro_notice_text); ?></p>
        </div>
    <?php endif; ?>

    <?php
    // A cache that answers before WordPress does will hand every visitor the same
    // version, and the test will look like it is running while measuring nothing.
    // We cannot reach those caches from here, so say what needs adding by hand.
    $convertpro_edge = convertpro_detect_edge_cache();

    if ($convertpro_edge) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php
                        /* translators: %s: name of the caching service, e.g. Cloudflare. */
                        printf(esc_html__('%s is in front of this site.', 'convertpro'), esc_html($convertpro_edge['name']));
                        ?></strong>
                <?php echo esc_html($convertpro_edge['how']); ?>
            </p>
        </div>
    <?php endif; ?>

</div>

<script>
    function copyToClipboard(inputElement) {
        // Select the input field's text
        inputElement.select();
        inputElement.setSelectionRange(0, 99999); // For mobile devices

        // Copy the text inside the input field
        document.execCommand("copy");

        // Optional: Show a message to the user that the text has been copied

    }
</script>


<div class="convertpro-create-wrapper">
    <div class="test-top-area">
        <div class="back-test">
            <a href="<?php echo esc_url(admin_url('admin.php?page=convertpro-settings')); ?>"><svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7.5 14.75L2.25 9.5M2.25 9.5L7.5 4.25M2.25 9.5L15.75 9.5" stroke="#080E13" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg><?php echo esc_html__('Back to All Tests', 'convertpro') ?></a>
        </div>
        <div class="create-update-test">
            <h4><?php echo esc_html($text); ?></h4>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url($formUrl); ?>" id="convertpro-test-form">
        <input name="nonce" type="hidden" value="<?php echo esc_attr(wp_create_nonce('convertpro-nonce')); ?>" />
        <div class="test-top-wrap">
            <div class="test-name-wrap">
                <label for="test-name"><?php esc_html_e('Test name', 'convertpro'); ?></label>
                <p><?php echo esc_html__('Only you see this. Name it so you can tell it apart later.', 'convertpro') ?></p>
                <input id="test-name" class="text-name" name="test-name" type="text" value="<?php echo (isset($test->name) ? esc_attr($test->name) : ""); ?>" placeholder="<?php esc_attr_e('Add a name', 'convertpro'); ?>" required />
            </div>

            <!-- /.test type select start-->
            <div class="test-type-wrapper">
                <div class="test-type-title">
                    <h2><?php esc_html_e('Test type', 'convertpro'); ?>:</h2>
                </div>
                <div class="test-type-select">
                    <select name="convertpro-test-type" style="width: 100%;">
                        <?php if (!isset($test->test_type)) { ?>
                            <option value="null" selected="selected"><?php esc_html_e('Please select test type ...', 'convertpro'); ?></option>
                        <?php } ?>
                        <option value="elements" <?php if (isset($test->test_type) && $test->test_type == "elements") {
                                                        echo ('selected="selected"');
                                                    } ?>><?php esc_html_e('Elements', 'convertpro'); ?></option>
                        <option value="pages" <?php if (isset($test->test_type) && $test->test_type == "pages") {
                                                    echo ('selected="selected"');
                                                } ?>><?php esc_html_e('Page', 'convertpro'); ?></option>
                    </select>
                </div>
            </div>
            <!-- /.test type select end -->

            <!-- /.select page showing this content start -->
            <div class="convertpro-uri-wrapper">
                <div class="convertpro-headline" style="margin-top: 14px;">
                    <label><?php esc_html_e('Test URL', 'convertpro'); ?></label>
                    <p><?php echo esc_html__('Send people here and EasyTest passes them on to one of the versions below. Make up an address of your own — it cannot be a page you already have.', 'convertpro') ?></p>
                </div>
                <div class="url-identfier">
                    <span><?php echo esc_url(home_url('/')); ?></span>
                    <input name="test-uri" type="text" placeholder="<?php esc_attr_e('summer-offer', 'convertpro'); ?>" pattern="^([A-Za-z0-9\-_\/]*)$" title="<?php esc_attr_e('Letters, numbers, dashes and underscores only', 'convertpro'); ?>" value="<?php echo isset($test->test_uri) ? esc_attr($test->test_uri) : ''; ?>" />

                </div>
                <?php if (isset($test->test_uri) && '' !== $test->test_uri) : ?>
                    <div class="convertpro-pageview">
                        <a class="pageview-btn" id="test-page-url" target="_blank" href="<?php echo esc_url(home_url() . '/' . $test->test_uri . '/'); ?>"><?php esc_html_e('View Page', 'convertpro'); ?></a>
                    </div>

                    <?php
                    $convertpro_previews = array();

                    foreach ($test->variations as $convertpro_variation) {
                        if (!empty($convertpro_variation->page_id) && get_post($convertpro_variation->page_id)) {
                            $convertpro_previews[] = array(
                                'name' => $convertpro_variation->name,
                                'url' => get_permalink($convertpro_variation->page_id),
                            );
                        }
                    }
                    ?>

                    <?php if ($convertpro_previews) : ?>
                        <p class="convertpro-preview-note">
                            <?php esc_html_e('Opening the test URL puts you on one version and keeps you there, the same as it would any visitor. To look at the others, open them directly:', 'convertpro'); ?>
                            <?php foreach ($convertpro_previews as $convertpro_index => $convertpro_preview) : ?>
                                <a href="<?php echo esc_url($convertpro_preview['url']); ?>" target="_blank"><?php echo esc_html($convertpro_preview['name']); ?></a><?php echo $convertpro_index < count($convertpro_previews) - 1 ? ', ' : ''; ?>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="convertpro-test-variations">
            <div class="headline">
                <div class="name"><?php esc_html_e('Version name', 'convertpro'); ?>:</div>
                <div class="post"><?php esc_html_e('Page', 'convertpro'); ?>:</div>
                <div class="percentage"><?php esc_html_e('Share', 'convertpro'); ?>:</div>
                <div class="convertpro-class-gen"><?php esc_html_e('CSS class', 'convertpro'); ?>:</div>
                <?php // Matches the row's actions column so every label sits over its own field. ?>
                <div class="actions" aria-hidden="true"></div>

            </div>
            <div id="variations-container">
                <?php
                $i = 0;
                foreach ($test->variations as $variation) {

                ?>
                    <div class="convertpro-data-variation" data-variation-id="<?php echo esc_attr($variation->id); ?>">
                        <input type="hidden" name="test-variation[<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr($variation->id); ?>">
                        <div class="name">
                            <input class="variation-name" name="test-variation[<?php echo esc_attr($i); ?>][name]" type="text" value="<?php echo esc_attr($variation->name); ?>" />
                        </div>
                        <div class="post">
                            <select name="test-variation[<?php echo esc_attr($i); ?>][page-id]">
                                <option value="null" disabled selected><?php echo esc_attr('Select Page'); ?></option>
                                <?php foreach ($pages as $page) { ?>
                                    <option value="<?php echo esc_attr($page->ID); ?>" <?php if (isset($variation->page_id) && $variation->page_id == $page->ID) {
                                                                                            echo 'selected="selected"';
                                                                                        } ?>><?php echo esc_html(convertpro_page_option_label($page, $pages)); ?></option>

                                <?php } ?>
                            </select>
                        </div>

                        <div class="percentage">
                            <input name="test-variation[<?php echo esc_attr($i); ?>][percentage]" type="number" min="1" max="100" step="1" value="<?php echo esc_attr($variation->percentage); ?>" placeholder="<?php esc_attr_e('Share', 'convertpro'); ?>" required />
                        </div>
                        <div class="convertpro-class-gen">
                            <input name="test-variation[<?php echo esc_attr($i); ?>][customclass]" type="text" value="<?php echo esc_attr($variation->class_name); ?>" placeholder="<?php esc_attr_e('CSS class', 'convertpro'); ?>" />
                            <div class="copy-button" onclick="copyToClipboard(this.previousElementSibling)">Copy</div>
                        </div>

                        <div class="actions">
                            <div class="button-delete">&times;</div>
                        </div>
                    </div>


                <?php $i++;
                } ?>

            </div>
            <?php do_action('convertpro-variation-btn') ?>

            <?php $convertpro_variation_cap = convertpro_free_limit('variations'); ?>
            <?php if ($convertpro_variation_cap) : ?>
                <p class="convertpro-variation-cap" style="display:none">
                    <?php
                    printf(
                        /* translators: %d: number of versions a test compares. */
                        esc_html(_n('A test compares %d version.', 'A test compares %d versions — A against B. Run a second test to try a third idea.', $convertpro_variation_cap, 'convertpro')),
                        (int) $convertpro_variation_cap
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php
            // Only the note for the current type starts visible; the script swaps
            // them when the type changes.
            $convertpro_current_type = isset($test->test_type) ? $test->test_type : '';
            ?>
            <p class="convertpro-variation-help convertpro-help-elements"<?php echo 'elements' === $convertpro_current_type ? '' : ' style="display:none"'; ?>>
                <?php esc_html_e('Copy each class and paste it into the CSS class field of the matching element in your page builder. Visitors see one version and the rest stay hidden.', 'convertpro'); ?>
            </p>
            <p class="convertpro-variation-help convertpro-help-pages"<?php echo 'pages' === $convertpro_current_type ? '' : ' style="display:none"'; ?>>
                <?php esc_html_e('The shares decide how traffic is divided and need to add up to 100. Once someone lands on a version, they keep seeing it.', 'convertpro'); ?>
            </p>
        </div>



        <?php $convertpro_goal = (isset($test->conversion_type) && 'click' === $test->conversion_type) ? 'click' : 'page'; ?>

        <div class="convertpro-goal-card">
            <div class="test-conversion-page">
                <h4><?php esc_html_e('What counts as a conversion', 'convertpro'); ?></h4>
                <p><?php esc_html_e('How we know a visitor did the thing you are testing for.', 'convertpro'); ?></p>
            </div>
            <select name="test-conversion-type" id="test-conversion-type" style="width: 100%;">
                <option value="page" <?php selected($convertpro_goal, 'page'); ?>><?php esc_html_e('Reaching a page on this site', 'convertpro'); ?></option>
                <option value="click" <?php selected($convertpro_goal, 'click'); ?>><?php esc_html_e('Clicking a link or button', 'convertpro'); ?></option>
            </select>

            <div class="convertpro-conversion-page-wrapper convertpro-goal-field">
                <label for="test-conversion-page"><?php esc_html_e('Which page', 'convertpro'); ?></label>
                <p class="convertpro-goal-hint"><?php esc_html_e('Reaching this page counts as a conversion.', 'convertpro'); ?></p>
                <select name="test-conversion-page" id="test-conversion-page" style="width: 100%;">
                    <option value="null" disabled selected><?php esc_html_e('Select a page', 'convertpro'); ?></option>
                    <?php
                    foreach ($pages as $page) { ?>
                        <option value="<?php echo esc_attr($page->ID); ?>" <?php if (isset($test->conversion_page_id) && $test->conversion_page_id == $page->ID) {
                                                                                echo ('selected="selected"');
                                                                            } ?>><?php echo esc_html(convertpro_page_option_label($page, $pages)); ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="test-conversion-url-wrapper convertpro-goal-field">
                <label for="test-conversion-selector"><?php esc_html_e('Which link or button', 'convertpro'); ?></label>
                <p class="convertpro-goal-hint"><?php esc_html_e('Its CSS class or id, or part of the address it points to. Use this when the next step is somewhere we cannot see, like a checkout on another site. Separate several with commas.', 'convertpro'); ?></p>
                <input type="text" name="test-conversion-selector" id="test-conversion-selector"
                    value="<?php echo isset($test->conversion_url) ? esc_attr($test->conversion_url) : ''; ?>"
                    placeholder="<?php esc_attr_e('.buy-now, #checkout-button, or checkout.example.com', 'convertpro'); ?>" />
            </div>
        </div>
        <div class="submit-btn">
            <input type="hidden" name="variation-count" value="<?php echo esc_attr($i); ?>" />
            <input type="hidden" name="test-id" value="<?php echo (isset($test->id) ? esc_attr($test->id) : ''); ?>" />
            <input class="test-button-save" type="submit" value="<?php esc_html_e('Save Test', 'convertpro'); ?>" />

        </div>
    </form>

</div>