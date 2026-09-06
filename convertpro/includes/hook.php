<?php

if (!defined('ABSPATH')) {
    exit; // Called directly, nothing to do here.
}
add_action('convertpro-variation-btn', 'convertpro_vari_btn');

function convertpro_vari_btn()
{
    $convertpro_variation_limit = convertpro_free_limit('variations');
    ?>
    <div class="variation-btn">
        <a class="vari-btn" href="#">
            <span>
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 4.5V9M9 9V13.5M9 9H13.5M9 9L4.5 9" stroke="#F9FAFB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <?php echo esc_html__('Add another version', 'convertpro'); ?>
        </a>
    </div>
    <script>
        jQuery(document).ready(function($) {
            // 0 means no limit. Sites that were using EasyTest before the limits
            // existed get 0 and never see the button disappear.
            var maxVariations = <?php echo (int) $convertpro_variation_limit; ?>;

            // A test that already has more versions than the limit keeps them, so
            // the starting count is the floor rather than the limit.
            var allowedVariations = Math.max(maxVariations, jQuery('.convertpro-data-variation').length);

            function refreshControls() {
                var rows = jQuery('.convertpro-data-variation').length;

                // Two is the minimum a test can compare, so at two there is
                // nothing to remove. Hiding the control beats letting someone
                // click it and watch nothing happen, and the class collapses the
                // column so the row does not end in an empty box.
                jQuery('.button-delete').toggle(rows > 2);
                jQuery('.convertpro-test-variations').toggleClass('has-delete', rows > 2);

                if (!maxVariations) {
                    return;
                }

                var atLimit = rows >= allowedVariations;

                jQuery('.variation-btn').toggle(!atLimit);
                jQuery('.convertpro-variation-cap').toggle(atLimit);
            }

            function generateUniqueClassName(prefix) {
                return prefix + Math.random().toString(36).substr(2, 9);
            }

            // The number in test-variation[N] is what decides where each row lands
            // in the array PHP receives, so the rows have to be renumbered every
            // time one is added or removed. Without it, deleting a row from the
            // middle and then adding one gives two rows the same number, PHP keeps
            // only the last, and the test saves with a version missing.
            function renumberVariations() {
                jQuery('.convertpro-data-variation').each(function(index) {
                    jQuery(this).find('[name^="test-variation["]').each(function() {
                        var renumbered = jQuery(this).attr('name')
                            .replace(/^test-variation\[[^\]]*\]/, 'test-variation[' + index + ']');

                        jQuery(this).attr('name', renumbered);
                    });
                });
            }

            function addNewVariation() {
                var variationsContainer = jQuery('#variations-container');
                var currentVariationsCount = jQuery('.convertpro-data-variation').length;

                if (maxVariations && currentVariationsCount >= allowedVariations) {
                    refreshControls();
                    return;
                }

                    var newIndex = currentVariationsCount;
                    var uniqueClassName = generateUniqueClassName('celm-');

                    var newVariation = `
                        <div class="convertpro-data-variation" data-variation-id="">
                            <input type="hidden" name="test-variation[${newIndex}][id]" value="">
                            <div class="name">
                                <input class="variation-name" name="test-variation[${newIndex}][name]" type="text" value="" placeholder="Name" required />
                            </div>
                            <div class="post">
                                <select name="test-variation[${newIndex}][page-id]">
                                    <option value="null" disabled selected>Select Page</option>
                                    <?php
                                    $pages = get_pages();
                                    foreach ($pages as $page) { ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>"><?php echo esc_html(convertpro_page_option_label($page, $pages)); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="percentage">
                                <input class="variation-percentage" name="test-variation[${newIndex}][percentage]" type="number" value="" placeholder="Percentage" required />
                            </div>
                            <div class="convertpro-class-gen">
                                <input id="convertpro-class-name" name="test-variation[${newIndex}][customclass]" type="text" value="${uniqueClassName}" placeholder="Custom Class" />
                                <div class="copy-button" onclick="copyToClipboard(this.previousElementSibling)">Copy</div>
                            </div>
                            <div class="actions">
                                <div class="button-delete">&times;</div>
                            </div>
                        </div>
                    `;

                    variationsContainer.append(newVariation);
                    renumberVariations();
                    refreshControls();
            }

            // Handle click on Add Variation button
            jQuery(document).on('click', '.vari-btn', function(e) {
                e.preventDefault();
                addNewVariation();
            });

            // Handle click on Delete button for variations
            jQuery(document).on('click', '.button-delete', function() {
                if (jQuery('.convertpro-data-variation').length > 2) {
                    jQuery(this).closest('.convertpro-data-variation').remove();
                    renumberVariations();
                    refreshControls();
                }
            });

            refreshControls();
        });

        function copyToClipboard(inputElement) {
            // Select the input field's text
            inputElement.select();
            inputElement.setSelectionRange(0, 99999); // For mobile devices

            // Copy the text inside the input field
            document.execCommand("copy");

            // Optional: Show a message to the user that the text has been copied

        }
    </script>
    <style>
        .convertpro-class-gen {
            position: relative;
            display: inline-block;
        }

        .copy-button {
            display: none;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #080E13;
            color: #fff;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 12px;
        }

        .convertpro-class-gen:hover .copy-button {
            display: block;
        }
    </style>
<?php } ?>