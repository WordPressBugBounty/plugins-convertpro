<div class="wrap convertpro-test-index">
    <div class="messages">
        <?php
        // phpcs:ignore
        if (isset($_GET['message'])) {
            $allowed_messages = array(
                "security_error",
                "error_delete",
                "delete_success",
                "error_update_data_missing",
                "conversion_page_missing",
                "conversion_url_missing"
            );
            // phpcs:ignore
            if (in_array($_GET['message'], $allowed_messages, true)) {
                // phpcs:ignore
                switch ($_GET['message']) {
                    case "security_error":
                        $message = esc_html__('Security Error', 'convertpro');
                        break;
                    case "error_delete":
                        $message = esc_html__('Not set Id', 'convertpro');
                        break;
                    case "delete_success":
                        $message = esc_html__('Test successfully deleted', 'convertpro');
                        break;
                    case "error_update_data_missing":
                        $message = esc_html__('Form data missing. Contact support.', 'convertpro');
                        break;
                    case "conversion_page_missing":
                        $message = esc_html__('Conversion page missing.', 'convertpro');
                        break;
                    case "conversion_url_missing":
                        $message = esc_html__('Conversion url missing.', 'convertpro');
                        break;
                    default:
                        $message = '';
                        break;
                }
                // phpcs:ignore
                if (!empty($message)) {
                    // phpcs:ignore
        ?>
                    <div class="notice notice-<?php
                                                // phpcs:ignore
                                                echo ($_GET['message'] === 'delete_success') ? 'success' : 'warning'; ?> is-dismissible">
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php
                }
            } else {
                // Nonce verification failed, handle error
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Nonce verification failed', 'convertpro'); ?></p>
                </div>
        <?php
            }
        }
        ?>

    </div>
    
    <div class="convertpro-content-wrapper">
        <div class="padding-wrapper">
            <div class="convertpro-title-btn">
                <h4><?php esc_html_e('All Tests', 'convertpro'); ?></h4>

                <a class="add-test-button" href="<?php echo esc_url(admin_url('admin.php?page=convertpro-settings&scope=test&action=create')); ?>">
                    <span><svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 4.5V9M9 9V13.5M9 9H13.5M9 9L4.5 9" stroke="#F9FAFB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg></span><?php esc_html_e('Add Test', 'convertpro'); ?>
                </a>
            </div>
            <table class="wp-list-table widefat fixed striped posts">
                <tbody id="the-list">
                    <?php foreach ($tests as $test) { ?>
                        <tr id="test-<?php echo esc_attr($test->id); ?>" class="all-test">
                            <td class="name-col">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . esc_attr($test->id))); ?>"><?php echo esc_html($test->name); ?></a>
                            </td>
                            <td class="button-col">
                                <a class="report-button" href="<?php echo esc_url(admin_url('admin.php?page=convertpro-settings&scope=statistics&action=report&id=' . $test->id)); ?>"><?php esc_html_e('Full Report', 'convertpro'); ?></a>
                                <a class="edit-button" href="<?php echo esc_url(admin_url('admin.php?page=convertpro-settings&scope=test&action=edit&id=' . $test->id)); ?>"><svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M15.2008 3.29917L15.7311 2.76884V2.76884L15.2008 3.29917ZM4.875 16.2766V17.0266C5.07391 17.0266 5.26468 16.9476 5.40533 16.8069L4.875 16.2766ZM2.25 16.2766H1.5C1.5 16.6908 1.83579 17.0266 2.25 17.0266V16.2766ZM2.25 13.5983L1.71967 13.068C1.57902 13.2086 1.5 13.3994 1.5 13.5983H2.25ZM13.0795 3.8295C13.5188 3.39017 14.2311 3.39017 14.6705 3.8295L15.7311 2.76884C14.706 1.74372 13.0439 1.74372 12.0188 2.76884L13.0795 3.8295ZM14.6705 3.8295C15.1098 4.26884 15.1098 4.98116 14.6705 5.4205L15.7311 6.48116C16.7562 5.45603 16.7562 3.79397 15.7311 2.76884L14.6705 3.8295ZM14.6705 5.4205L4.34467 15.7463L5.40533 16.8069L15.7311 6.48116L14.6705 5.4205ZM4.875 15.5266H2.25V17.0266H4.875V15.5266ZM12.0188 2.76884L1.71967 13.068L2.78033 14.1286L13.0795 3.8295L12.0188 2.76884ZM1.5 13.5983V16.2766H3V13.5983H1.5ZM10.8938 4.9545L13.5455 7.60616L14.6061 6.5455L11.9545 3.89384L10.8938 4.9545Z" fill="#080E13" fill-opacity="0.7" />
                                    </svg></a>

                                <form class="delete-form" action="<?php echo esc_url(admin_url('admin.php?page=convertpro-settings&scope=test&action=delete&id=' . $test->id)); ?>" style="display: inline;" method="post">
                                    <input name="nonce" type="hidden" value="<?php echo esc_attr(wp_create_nonce('convertpro-nonce')); ?>" />
                                    <?php
                                    // Encode the SVG image to base64
                                    $svg_data = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="19" viewBox="0 0 18 19" fill="none"><path d="M14.25 5.75L13.5995 14.8569C13.5434 15.6418 12.8903 16.25 12.1033 16.25H5.89668C5.10972 16.25 4.45656 15.6418 4.40049 14.8569L3.75 5.75M7.5 8.75V13.25M10.5 8.75V13.25M11.25 5.75V3.5C11.25 3.08579 10.9142 2.75 10.5 2.75H7.5C7.08579 2.75 6.75 3.08579 6.75 3.5V5.75M3 5.75H15" stroke="#EE2626" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                                    $svg_base64 = base64_encode($svg_data);
                                    ?>

                                    <input type="submit" class="delete-button" style="background-image: url('data:image/svg+xml;base64,<?php echo esc_attr($svg_base64); ?>'); background-repeat: no-repeat; background-position: center;">
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (sizeof($tests) == 0) { ?>
                        <tr id="new-test" class="iedit hentry">
                            <td class="name-col" colspan="2" align="center">
                                <?php esc_html_e('Add your first test', 'convertpro'); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>

            </table>
        </div>
    </div>

</div>