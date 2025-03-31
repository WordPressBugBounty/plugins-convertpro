jQuery(document).ready(function () {

    testconversiontype();

    // jQuery('input[name="test-uri"]').keypress(function() {
	// 	onTestUriChanged(jQuery(this).val());
	// });

	// jQuery('input[name="test-uri"]').change(function() {
	// 	onTestUriChanged(jQuery(this).val());
	// });
    // Define your query variables and values
    registerFormSubmit();

    // test type select and show
    convertprotesttypecng();
    convertprochangeTestTypeLayout(jQuery('select[name="convertpro-test-type"]'));
    // elements class check
    delete_button_alert();


});

function delete_button_alert() {
    jQuery(".delete-button").click(function(e) {
        e.preventDefault();
        if (confirm("Are you sure you want to delete it?")) {
            console.log("Delete confirmed");
            jQuery(this).closest('form').submit();
        }
    });
}

function convertprotesttypecng() {
    jQuery('select[name="convertpro-test-type"]').change(function() {
		convertprochangeTestTypeLayout(this);
	});
}

function convertprochangeTestTypeLayout(selector) {
    var val = jQuery(selector).val();
    if (val === "null") {
        jQuery('.convertpro-uri-wrapper').hide();
        jQuery('.convertpro-test-variations').hide();
    } else if (val === "elements") {
        jQuery('.convertpro-uri-wrapper').hide();
        jQuery('.convertpro-test-variations').addClass("convertpro-variations-elements");
		jQuery('.convertpro-test-variations').removeClass("convertpro-variations-posts");
        jQuery(".convertpro-test-variations").show();
    } else if (val === "pages") {
        jQuery('.convertpro-uri-wrapper').show();
        jQuery('.convertpro-test-variations').removeClass("convertpro-variations-elements");
		jQuery('.convertpro-test-variations').addClass("convertpro-variations-posts");
        jQuery(".convertpro-test-variations").show();
    }
}

/**
 * show alert when delete btn click
 */

function testconversiontype() {
    let conversionType = jQuery('select[name="test-conversion-type"]');

    if ( jQuery(conversionType).val() === "url") {
        jQuery(".test-conversion-url-wrapper").show();
        jQuery(".convertpro-conversion-page-wrapper").hide();
    } else {
        jQuery(".convertpro-conversion-page-wrapper").show();
        jQuery(".test-conversion-url-wrapper").hide();
    }

    jQuery(conversionType).change(function() {
		if (jQuery(this).val() === "url") {
			jQuery(".convertpro-conversion-page-wrapper").slideUp();
			jQuery('select[name="test-conversion-page"]').val("null");
			jQuery(".test-conversion-url-wrapper").slideDown();
		} else {
			jQuery(".convertpro-conversion-page-wrapper").slideDown();
			jQuery(".test-conversion-url-wrapper").slideUp();
		}
    });
}

function registerFormSubmit() {
    jQuery("#convertpro-test-form").submit(function(e) {

        var inputs = jQuery(this).find(".convertpro-data-variation .percentage input");
        var percentageCount = 0;

        // Iterate over the inputs
        jQuery.each(inputs, function(index, value) {
            // Log the integer value of each input
            percentageCount+= (getInt(jQuery(value).val()));
        });

        if (percentageCount != 100) {
            var message = 'All variations are %PERCENTAGE% counted together but it has to be 100';
            alert(message.replace("%PERCENTAGE%", percentageCount));
            return false;
		}
    });
}
function getInt(value) {
    // Convert value to an integer (dummy implementation)
    return parseInt(value, 10);
}

// In your Javascript (external .js resource or <script> tag)
// jQuery(document).ready(function() {
//     jQuery('select').select2();
// });


// In your Javascript (external .js resource or <script> tag)
