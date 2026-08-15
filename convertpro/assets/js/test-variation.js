jQuery(document).ready(function () {

    testconversiontype();

    // test type select and show
    convertprotesttypecng();
    convertprochangeTestTypeLayout(jQuery('select[name="convertpro-test-type"]'));
    // elements class check
    delete_button_alert();

    convertproFormValidation();

});

function delete_button_alert() {
    jQuery(".delete-button").click(function(e) {
        e.preventDefault();
        if (confirm("Are you sure you want to delete it?")) {
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
    jQuery('.convertpro-variation-help').hide();

    if (val === "null") {
        jQuery('.convertpro-uri-wrapper').hide();
        jQuery('.convertpro-test-variations').hide();
    } else if (val === "elements") {
        jQuery('.convertpro-uri-wrapper').hide();
        jQuery('.convertpro-test-variations').addClass("convertpro-variations-elements");
		jQuery('.convertpro-test-variations').removeClass("convertpro-variations-posts");
        jQuery(".convertpro-test-variations").show();
        jQuery('.convertpro-help-elements').show();
    } else if (val === "pages") {
        jQuery('.convertpro-uri-wrapper').show();
        jQuery('.convertpro-test-variations').removeClass("convertpro-variations-elements");
		jQuery('.convertpro-test-variations').addClass("convertpro-variations-posts");
        jQuery(".convertpro-test-variations").show();
        jQuery('.convertpro-help-pages').show();
    }
}

function testconversiontype() {
    var conversionType = jQuery('select[name="test-conversion-type"]');

    function apply(animate) {
        var isClick = conversionType.val() === 'click';

        if (animate) {
            jQuery('.convertpro-conversion-page-wrapper').toggle(!isClick);
            jQuery('.test-conversion-url-wrapper').toggle(isClick);
        } else {
            jQuery('.convertpro-conversion-page-wrapper')[isClick ? 'hide' : 'show']();
            jQuery('.test-conversion-url-wrapper')[isClick ? 'show' : 'hide']();
        }
    }

    apply(false);

    conversionType.change(function () {
        apply(true);
    });
}

/**
 * Check the test form as it is filled in, so problems show up where they happen
 * instead of after a save round-trip.
 */
function convertproFormValidation() {
    var form = jQuery('#convertpro-test-form');

    if (!form.length) {
        return;
    }

    var data = window.convertproForm || {};
    var text = data.i18n || {};
    var takenSlugs = data.takenSlugs || [];
    var takenUris = data.takenUris || [];
    var touched = {};
    var showEverything = false;
    var pendingRowErrors = [];

    function typeValue() {
        return form.find('select[name="convertpro-test-type"]').val();
    }

    function rows() {
        return form.find('.convertpro-data-variation');
    }

    function fieldIn(row, selector) {
        return row.find(selector).first();
    }

    function clearErrors() {
        form.find('.convertpro-field-error').remove();
        form.find('.convertpro-invalid').removeClass('convertpro-invalid');
    }

    function flag(input, message, key) {
        // Only speak up once the user has been in the field, so a fresh form is
        // not covered in red before they have typed anything. The outline has to
        // wait for the same moment as the message — showing one without the other
        // is what made an untouched form look like it had already failed.
        if (key && !showEverything && !touched[key]) {
            return;
        }

        input.addClass('convertpro-invalid');

        var holder = input.closest('.convertpro-data-variation');

        if (holder.length) {
            // Variation rows are a grid; a message inside one would break it, so
            // they are collected underneath the whole block.
            pendingRowErrors.push(message);
            return;
        }

        input.closest('div').append(
            jQuery('<p class="convertpro-field-error"></p>').text(message)
        );
    }

    function validate() {
        clearErrors();
        pendingRowErrors = [];

        var type = typeValue();
        var conversion = form.find('#test-conversion-page');
        var conversionVal = conversion.val();
        var problems = 0;

        // Name
        var name = form.find('input[name="test-name"]');
        if (!jQuery.trim(name.val())) {
            flag(name, text.nameMissing, 'name');
            problems++;
        }

        if (!type || type === 'null') {
            problems++;
        }

        // Test URL, page tests only
        if (type === 'pages') {
            var uri = form.find('input[name="test-uri"]');
            var uriVal = jQuery.trim(uri.val()).replace(/^\/+|\/+$/g, '');

            if (!uriVal) {
                flag(uri, text.uriMissing, 'uri');
                problems++;
            } else if (uriVal.indexOf('/') !== -1) {
                flag(uri, text.uriSlash, 'uri');
                problems++;
            } else if (jQuery.inArray(uriVal, takenSlugs) !== -1) {
                flag(uri, text.uriTakenContent, 'uri');
                problems++;
            } else if (jQuery.inArray(uriVal, takenUris) !== -1) {
                flag(uri, text.uriTakenTest, 'uri');
                problems++;
            }
        }

        // Conversion goal
        var goal = form.find('#test-conversion-type').val() || 'page';

        if (goal === 'click') {
            var selector = form.find('#test-conversion-selector');

            if (!jQuery.trim(selector.val())) {
                flag(selector, text.selectorMissing, 'selector');
                problems++;
            }

            // A click goal has no conversion page to clash with.
            conversionVal = null;
        } else if (!conversionVal || conversionVal === 'null') {
            flag(conversion, text.conversionMissing, 'conversion');
            problems++;
        }

        // Variations
        var seenPages = [];
        var seenClasses = [];
        var total = 0;

        if (rows().length < 2) {
            pendingRowErrors.push(text.needTwo);
            problems++;
        }

        rows().each(function (index) {
            var row = jQuery(this);
            var percentInput = fieldIn(row, '.percentage input');
            var percent = parseInt(percentInput.val(), 10);

            if (isNaN(percent) || percent < 1 || percent > 100) {
                flag(percentInput, text.percentRange, 'row' + index);
                problems++;
            } else {
                total += percent;
            }

            if (type === 'pages') {
                var pageSelect = fieldIn(row, '.post select');
                var page = pageSelect.val();

                if (!page || page === 'null') {
                    flag(pageSelect, text.pageMissing, 'row' + index);
                    problems++;
                } else if (jQuery.inArray(page, seenPages) !== -1) {
                    flag(pageSelect, text.pageDuplicate, 'row' + index);
                    problems++;
                } else if (page === conversionVal) {
                    flag(pageSelect, text.pageIsConversion, 'row' + index);
                    problems++;
                } else {
                    seenPages.push(page);
                }
            } else if (type === 'elements') {
                var classInput = fieldIn(row, '.convertpro-class-gen input');
                var className = jQuery.trim(classInput.val());

                if (!className) {
                    flag(classInput, text.classMissing, 'row' + index);
                    problems++;
                } else if (jQuery.inArray(className, seenClasses) !== -1) {
                    flag(classInput, text.classDuplicate, 'row' + index);
                    problems++;
                } else {
                    seenClasses.push(className);
                }
            }
        });

        if (rows().length && total !== 100) {
            pendingRowErrors.push((text.percentTotal || '%s').replace('%s', total));
            problems++;
        }

        renderRowErrors();
        updateTotalBadge(total);
        greyOutTakenPages(seenPages, conversionVal);

        form.find('.test-button-save').toggleClass('is-blocked', problems > 0);

        return problems === 0;
    }

    function renderRowErrors() {
        var holder = form.find('.convertpro-row-errors');

        if (!holder.length) {
            holder = jQuery('<div class="convertpro-row-errors"></div>');
            form.find('#variations-container').after(holder);
        }

        holder.empty();

        jQuery.each(pendingRowErrors, function (i, message) {
            holder.append(jQuery('<p class="convertpro-field-error"></p>').text(message));
        });
    }

    function updateTotalBadge(total) {
        var badge = form.find('.convertpro-share-total');

        if (!badge.length) {
            badge = jQuery('<span class="convertpro-share-total"></span>');
            form.find('.convertpro-test-variations .headline .percentage').append(badge);
        }

        badge.text(total + '%').toggleClass('is-off', total !== 100);
    }

    /**
     * A page can only play one role, so the ones already spoken for are greyed
     * out rather than left available to pick by mistake.
     */
    function greyOutTakenPages(usedPages, conversionVal) {
        var conversion = form.find('#test-conversion-page');
        var chosen = [];

        form.find('.convertpro-data-variation .post select').each(function () {
            var val = jQuery(this).val();
            if (val && val !== 'null') {
                chosen.push(val);
            }
        });

        conversion.find('option').each(function () {
            var opt = jQuery(this);
            if (opt.val() === 'null') {
                return;
            }
            opt.prop('disabled', jQuery.inArray(opt.val(), chosen) !== -1);
        });

        form.find('.convertpro-data-variation .post select').each(function () {
            var self = jQuery(this);
            var mine = self.val();

            self.find('option').each(function () {
                var opt = jQuery(this);
                if (opt.val() === 'null' || opt.val() === mine) {
                    return;
                }
                opt.prop('disabled', jQuery.inArray(opt.val(), chosen) !== -1 || opt.val() === conversionVal);
            });
        });
    }

    // Mark fields the user has actually visited, so messages appear as they work
    // rather than all at once on a blank form.
    form.on('blur change', 'input[name="test-name"]', function () { touched.name = true; validate(); });
    form.on('blur change', 'input[name="test-uri"]', function () { touched.uri = true; validate(); });
    form.on('change', '#test-conversion-page', function () { touched.conversion = true; validate(); });
    form.on('change', '#test-conversion-type', function () { validate(); });
    form.on('blur change input', '#test-conversion-selector', function () { touched.selector = true; validate(); });
    form.on('blur change', '.convertpro-data-variation input, .convertpro-data-variation select', function () {
        var index = jQuery(this).closest('.convertpro-data-variation').index();
        touched['row' + index] = true;
        validate();
    });
    form.on('input', 'input[name="test-name"], input[name="test-uri"], .convertpro-data-variation input', validate);
    form.on('change', 'select[name="convertpro-test-type"]', validate);
    form.on('click', '.button-delete, .add-variation, #add-variation', function () {
        setTimeout(validate, 60);
    });

    form.submit(function () {
        // On save, every problem is shown, not just the ones in fields the user
        // happened to visit.
        showEverything = true;

        if (!validate()) {
            var first = form.find('.convertpro-invalid').first();

            if (first.length) {
                jQuery('html, body').animate({ scrollTop: first.offset().top - 120 }, 200);
                first.focus();
            }

            return false;
        }
    });

    validate();
}
