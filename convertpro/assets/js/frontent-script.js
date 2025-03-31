jQuery(document).ready(function ($) {
  var adminPageUrl = window.location.href;
  var previousUrl = document.referrer;

/*   jQuery.ajax({
    url: convertpro_object.ajaxurl, // Use passed ajaxurl
    type: "POST",
    data: {
      action: "convertpro_ajax_action",
      previous_url: previousUrl,
      security: convertpro_object.nonce, // Use passed nonce
    },
    success: function (response) {
      // Handle the response
      // console.log('AJAX request successful:', response);
    },
    error: function (xhr, status, error) {
      console.error("AJAX request failed:", error);
    },
  }); */

//   convertpro_check_class();

//   jQuery.ajax({
//     url: convertpro_object.ajaxurl, // WordPress AJAX URL
//     type: "POST",
//     data: {
//       action: "get_conversion_page_permalink",
//     },
//     success: function (response) {
//       console.log("finded"); // Permalink retrieved, you can use it as needed
//     },
//     error: function (xhr, status, error) {
//       console.error(xhr.responseText);
//     },
//   });
});

async function convertpro_check_class() {
  for (const element of convertpro_elm_object.variations) {
    const activeClass = element.class;
    const variationId = element.id;
    const testId = element.testId;

    if (
      activeClass != "" &&
      jQuery("." + activeClass).length > 0 &&
      getCookie("convert_pro_elm_test_" + testId).length == 0
    ) {
      console.log("." + activeClass);
      await jQuery.post(
        convertpro_elm_object.ajaxUrl,
        {
          action: "convertpro_store_visit_data",
          active_class: activeClass,
          variation_id: variationId,
          test_id: testId,
        },
        function (data) {
          if (getCookie("convert_pro_elm_test_" + testId)) {
            jQuery("." + activeClass).show();
          }
        },
        "json"
      );
    }
  }
}

function getCookie(cname) {
  var name = cname + "=";
  var decodedCookie = decodeURIComponent(document.cookie);
  var ca = decodedCookie.split(";");
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == " ") {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}
