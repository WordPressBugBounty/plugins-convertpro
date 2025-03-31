<?php
if ($scope == "edit") {
    $formUrl = admin_url('admin.php?page=convertpro-settings&scope=test&action=update&id=' . $test->id);
} else {
    $formUrl = admin_url('admin.php?page=convertpro-settings&scope=test&action=store');
}
?>
<div class="meassage">
    <?php
    // phpcs:ignore
    if (isset($_GET['message']) && ($_GET['message'] == "save_success" || $_GET['message'] == "store_success")) {
        // Verify nonce

        // phpcs:ignore
        if ($_GET['message'] == "save_success") {
    ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Update successfully saved', 'convertpro'); ?></p>
            </div>
        <?php
        }
        // phpcs:ignore
        elseif ($_GET['message'] == "store_success") {
        ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Test successfully created', 'convertpro'); ?></p>
            </div>
    <?php
        }
    }
    ?>

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
                <p><?php echo esc_html__('This is for your reference and will only visible to you', 'convertpro') ?></p>
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
                    <p><?php echo esc_html__('Visitors will visit this page for this performing this test', 'convertpro') ?></p>
                </div>
                <div class="url-identfier">
                    <span><?php echo esc_url(home_url('/')); ?></span>
                    <input name="test-uri" type="text" placeholder="identifier" pattern="^([A-Za-z0-9\-_\/]*)$" title="Only input letters, numbers, dashes and underscores" value="<?php echo isset($test->test_uri) ? esc_attr($test->test_uri) : ''; ?>" />

                </div>
                <?php if (isset($test->test_uri)) : ?>
                    <div class="convertpro-pageview">
                        <div class="convertpro-pageview">
                            <a class="pageview-btn" id="test-page-url" target="_blank" href="<?php echo esc_url(home_url() . '/' . $test->test_uri . '/'); ?>"><?php esc_html_e('View Page', 'convertpro'); ?></a>
                        </div>

                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="convertpro-test-variations">
            <div class="headline">
                <div class="name"><?php esc_html_e('Variation Name', 'convertpro'); ?>:</div>
                <div class="post"><?php esc_html_e('Page', 'convertpro'); ?>:</div>
                <div class="percentage"><?php esc_html_e('Percentage', 'convertpro'); ?>:</div>
                <div class="convertpro-class-gen"><?php esc_html_e('Custom Class', 'convertpro'); ?>:</div>

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
                                                                                        } ?>><?php echo esc_html($page->post_title); ?></option>

                                <?php } ?>
                            </select>
                        </div>

                        <div class="percentage">
                            <input id="test-name" name="test-variation[<?php echo esc_attr($i); ?>][percentage]" type="number" value="<?php echo esc_attr($variation->percentage); ?>" placeholder="<?php esc_html_e('Percentage', 'convertpro'); ?>" required />
                        </div>
                        <div class="convertpro-class-gen">
                            <input id="convertpro-class-name" name="test-variation[<?php echo esc_attr($i); ?>][customclass]" type="text" value="<?php echo esc_attr($variation->class_name); ?>" placeholder="<?php esc_html_e('Custom Class', 'convertpro'); ?>" />
                            <div class="copy-button" onclick="copyToClipboard(this.previousElementSibling)">Copy</div>
                        </div>

                        <div class="actions">
                            <div class="button-delete">&times;</div>
                            <input id="test-name" name="test-variation[<?php echo esc_attr(($i)); ?>][id]" type="hidden" value="<?php echo esc_attr(($variation->id)); ?>" required />
                        </div>
                    </div>


                <?php $i++;
                } ?>

            </div>
            <?php do_action('convertpro-variation-btn') ?>
        </div>



        <div class="convertpro-conversion-page-wrapper">
            <div for="test-conversion-page" class="test-conversion-page">
                <h4><?php esc_html_e('Conversion Page', 'convertpro'); ?></h4>
                <p><?php echo esc_html__('When customer visits this page, we track that as a conversion', 'convertpro'); ?></p>
            </div>
            <select required name="test-conversion-page" id="test-conversion-page" style="width: 100%;">
                <option value="null" disabled selected><?php echo esc_attr('Select Conversion Page'); ?></option>
                <?php
                foreach ($pages as $page) { ?>
                    <option value="<?php echo esc_attr($page->ID); ?>" <?php if (isset($test->conversion_page_id) && $test->conversion_page_id == $page->ID) {
                                                                            echo ('selected="selected"');
                                                                        } ?>><?php echo esc_html($page->post_title); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="submit-btn">
            <input type="hidden" name="variation-count" value="<?php echo esc_attr($i); ?>" />
            <input type="hidden" name="test-id" value="<?php echo (isset($test->id) ? esc_attr($test->id) : ''); ?>" />
            <input class="test-button-save" type="submit" value="<?php esc_html_e('Save Test', 'convertpro'); ?>" />

        </div>
    </form>

</div>