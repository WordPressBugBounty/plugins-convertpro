<?php
add_action('convertpro-variation-btn', 'convertpro_vari_btn');

function convertpro_vari_btn()
{ ?>
    <div class="variation-btn">
        <a class="vari-btn" href="#">
            <span>
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 4.5V9M9 9V13.5M9 9H13.5M9 9L4.5 9" stroke="#F9FAFB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <?php echo esc_html__('Add More Variation', 'convertpro'); ?>
        </a>
    </div>
    <script>
        jQuery(document).ready(function($) {
            var maxVariations = 2;

            function generateUniqueClassName(prefix) {
                return prefix + Math.random().toString(36).substr(2, 9);
            }

            function addNewVariation() {
                var variationsContainer = jQuery('#variations-container');
                var currentVariationsCount = jQuery('.convertpro-data-variation').length;

                // if (currentVariationsCount < maxVariations) {
                    var newIndex = currentVariationsCount;
                    var uniqueClassName = generateUniqueClassName('celm-');

                    var newVariation = `
                        <div class="convertpro-data-variation" data-variation-id="">
                            <input type="hidden" name="test-variation[${newIndex}][id]" value="">
                            <div class="name">
                                <input class="variation-name" name="test-variation[${newIndex}][name]" type="text" value="" placeholder="name" required />
                            </div>
                            <div class="post">
                                <select name="test-variation[${newIndex}][page-id]">
                                    <option value="null" disabled selected>Select Page</option>
                                    <?php
                                    $pages = get_pages();
                                    foreach ($pages as $page) { ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>"><?php echo esc_html($page->post_title); ?></option>
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
                                <input type="hidden" name="test-variation[${newIndex}][id]" value="" required />
                            </div>
                        </div>
                    `;

                    variationsContainer.append(newVariation);

         /*        } else {
                    // Show alert to buy Pro when max variations reached
                    alert('Please upgrade to Pro to add more variations.');
                    // window.location.href = 'https://example.com';
                } */
            }

            // Handle click on Add Variation button
            jQuery(document).on('click', '.vari-btn', function(e) {
                e.preventDefault();
                addNewVariation();
            });

            // Handle click on Delete button for variations
            jQuery(document).on('click', '.button-delete', function() {
                if(jQuery('.button-delete').length > 2){
                    var variationContainer = jQuery(this).closest('.convertpro-data-variation');
                    variationContainer.remove();
                }else{
                    alert("You must have 2 variations");
                }
            });
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