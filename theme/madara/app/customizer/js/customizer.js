jQuery(document).ready(function ($) {
	"use strict";

	var toggleSiteCustomColorControls = (visible) => {
		if (visible == false) {
			$('#customize-control-main_color').hide();
			$('#customize-control-main_color_end').hide();
			$('#customize-control-link_color_hover').hide();
			$('#customize-control-star_color').hide();
			$('#customize-control-star_color').hide();
			$('#customize-control-hot_badges_bg_color').hide();
			$('#customize-control-new_badges_bg_color').hide();
			$('#customize-control-custom_badges_bg_color').hide();
			$('#customize-control-btn_bg').hide();
			$('#customize-control-btn_color').hide();
			$('#customize-control-btn_hover_bg').hide();
			$('#customize-control-btn_hover_color').hide();
		} else {
			$('#customize-control-main_color').show();
			$('#customize-control-main_color_end').show();
			$('#customize-control-link_color_hover').show();
			$('#customize-control-star_color').show();
			$('#customize-control-star_color').show();
			$('#customize-control-hot_badges_bg_color').show();
			$('#customize-control-new_badges_bg_color').show();
			$('#customize-control-custom_badges_bg_color').show();
			$('#customize-control-btn_bg').show();
			$('#customize-control-btn_color').show();
			$('#customize-control-btn_hover_bg').show();
			$('#customize-control-btn_hover_color').show();
		}
	}

	var toggleHeaderCustomColors = (visible) => {
		if (visible) {
			$('#customize-control-nav_item_color').show();
			$('#customize-control-nav_item_hover_color').show();
			$('#customize-control-nav_sub_bg').show();
			$('#customize-control-nav_sub_bg_border_color').show();
			$('#customize-control-nav_sub_item_color').show();
			$('#customize-control-nav_sub_item_hover_color').show();
			$('#customize-control-nav_sub_item_hover_bg').show();
		} else {
			$('#customize-control-nav_item_color').hide();
			$('#customize-control-nav_item_hover_color').hide();
			$('#customize-control-nav_sub_bg').hide();
			$('#customize-control-nav_sub_bg_border_color').hide();
			$('#customize-control-nav_sub_item_color').hide();
			$('#customize-control-nav_sub_item_hover_color').hide();
			$('#customize-control-nav_sub_item_hover_bg').hide();
		}
	}

	var toggleHeaderBottomCustomColors = (visible) => {
		if (visible) {
			$('#customize-control-header_bottom_bg').show();
			$('#customize-control-bottom_nav_item_color').show();
			$('#customize-control-bottom_nav_item_hover_color').show();
			$('#customize-control-bottom_nav_sub_bg').show();
			$('#customize-control-bottom_nav_sub_item_color').show();
			$('#customize-control-bottom_nav_sub_item_hover_color').show();
			$('#customize-control-bottom_nav_sub_border_bottom').show();
		} else {
			$('#customize-control-header_bottom_bg').hide();
			$('#customize-control-bottom_nav_item_color').hide();
			$('#customize-control-bottom_nav_item_hover_color').hide();
			$('#customize-control-bottom_nav_sub_bg').hide();
			$('#customize-control-bottom_nav_sub_item_color').hide();
			$('#customize-control-bottom_nav_sub_item_hover_color').hide();
			$('#customize-control-bottom_nav_sub_border_bottom').hide();
		}
	}

	var toggleMobileMenuCustomColors = (visible) => {
		if (visible) {
			$('#customize-control-mobile_browser_header_color').show();
			$('#customize-control-canvas_menu_background').show();
			$('#customize-control-canvas_menu_color').show();
			$('#customize-control-canvas_menu_hover').show();
		} else {
			$('#customize-control-mobile_browser_header_color').hide();
			$('#customize-control-canvas_menu_background').hide();
			$('#customize-control-canvas_menu_color').hide();
			$('#customize-control-canvas_menu_hover').hide();
		}
	}

	var toggleCustomFontsControls = (isDefault) => {
		var font_using_custom_visible = isDefault ? wp.customize.settings.settings.font_using_custom.value : $('#font_using_custom').is(':checked');
		if (!font_using_custom_visible) {
			$('#customize-control-main_font_on_google').hide();
			$('#customize-control-main_font_google_family').hide();
			$('#customize-control-main_font_family').hide();
			$('#customize-control-main_font_size').hide();
			$('#customize-control-main_font_weight').hide();
			$('#customize-control-main_font_line_height').hide();
			$('#customize-control-heading_font_on_google').hide();
			$('#customize-control-heading_font_google_family').hide();
			$('#customize-control-heading_font_family').hide();
			$('#customize-control-heading_font_size_h1').hide();
			$('#customize-control-h1_line_height').hide();
			$('#customize-control-h1_font_weight').hide();
			$('#customize-control-heading_font_size_h2').hide();
			$('#customize-control-h2_line_height').hide();
			$('#customize-control-h2_font_weight').hide();
			$('#customize-control-heading_font_size_h3').hide();
			$('#customize-control-h3_line_height').hide();
			$('#customize-control-h3_font_weight').hide();
			$('#customize-control-heading_font_size_h4').hide();
			$('#customize-control-h4_line_height').hide();
			$('#customize-control-h4_font_weight').hide();
			$('#customize-control-heading_font_size_h5').hide();
			$('#customize-control-h5_line_height').hide();
			$('#customize-control-h5_font_weight').hide();
			$('#customize-control-heading_font_size_h6').hide();
			$('#customize-control-h6_line_height').hide();
			$('#customize-control-h6_font_weight').hide();
			$('#customize-control-navigation_font_on_google').hide();
			$('#customize-control-navigation_font_google_family').hide();
			$('#customize-control-navigation_font_family').hide();
			$('#customize-control-navigation_font_size').hide();
			$('#customize-control-navigation_font_weight').hide();
			$('#customize-control-meta_font_on_google').hide();
			$('#customize-control-meta_font_google_family').hide();
			$('#customize-control-meta_font_family').hide();
			$('#customize-control-custom_font_1').hide();
			$('#customize-control-custom_font_2').hide();
			$('#customize-control-custom_font_3').hide();
		} else {
			const main_font_on_google = isDefault ? wp.customize.settings.settings.main_font_on_google.value : $('#main_font_on_google').is(':checked');
			$('#customize-control-main_font_on_google').show();
			if (main_font_on_google) {
				$('#customize-control-main_font_google_family').show();
				$('#customize-control-main_font_family').hide();
			} else {
				$('#customize-control-main_font_google_family').hide();
				$('#customize-control-main_font_family').show();
			}

			$('#customize-control-main_font_size').show();
			$('#customize-control-main_font_weight').show();
			$('#customize-control-main_font_line_height').show();
			$('#customize-control-heading_font_on_google').show();

			const heading_font_on_google = isDefault ? wp.customize.settings.settings.heading_font_on_google.value : $('#heading_font_on_google').is(':checked');
			if (heading_font_on_google) {
				$('#customize-control-heading_font_google_family').show();
				$('#customize-control-heading_font_family').hide();
			} else {
				$('#customize-control-heading_font_google_family').hide();
				$('#customize-control-heading_font_family').show();
			}

			$('#customize-control-heading_font_size_h1').show();
			$('#customize-control-h1_line_height').show();
			$('#customize-control-h1_font_weight').show();
			$('#customize-control-heading_font_size_h2').show();
			$('#customize-control-h2_line_height').show();
			$('#customize-control-h2_font_weight').show();
			$('#customize-control-heading_font_size_h3').show();
			$('#customize-control-h3_line_height').show();
			$('#customize-control-h3_font_weight').show();
			$('#customize-control-heading_font_size_h4').show();
			$('#customize-control-h4_line_height').show();
			$('#customize-control-h4_font_weight').show();
			$('#customize-control-heading_font_size_h5').show();
			$('#customize-control-h5_line_height').show();
			$('#customize-control-h5_font_weight').show();
			$('#customize-control-heading_font_size_h6').show();
			$('#customize-control-h6_line_height').show();
			$('#customize-control-h6_font_weight').show();
			$('#customize-control-navigation_font_on_google').show();

			const navigation_font_on_google = isDefault ? wp.customize.settings.settings.navigation_font_on_google.value : $('#navigation_font_on_google').is(':checked');
			if (navigation_font_on_google) {
				$('#customize-control-navigation_font_google_family').show();
				$('#customize-control-navigation_font_family').hide();
			} else {
				$('#customize-control-navigation_font_google_family').hide();
				$('#customize-control-navigation_font_family').show();
			}

			$('#customize-control-navigation_font_size').hide();
			$('#customize-control-navigation_font_weight').hide();
			$('#customize-control-meta_font_on_google').hide();

			const meta_font_on_google = isDefault ? wp.customize.settings.settings.meta_font_on_google.value : $('#meta_font_on_google').is(':checked');
			if (meta_font_on_google) {
				$('#customize-control-meta_font_google_family').show();
				$('#customize-control-meta_font_family').hide();
			} else {
				$('#customize-control-meta_font_google_family').hide();
				$('#customize-control-meta_font_family').show();
			}

			$('#customize-control-custom_font_1').show();
			$('#customize-control-custom_font_2').show();
			$('#customize-control-custom_font_3').show();
		}
	}

	var toggleAMPControls = (visible) => {
		if (visible) {
			$('#customize-control-amp_image_height').show();
			$('#customize-control-amp_manga_reading_style').show();
			$('#customize-control-amp_fontawesome_key').show();
		} else {
			$('#customize-control-amp_image_height').hide();
			$('#customize-control-amp_manga_reading_style').hide();
			$('#customize-control-amp_fontawesome_key').hide();
		}
	}

	var togglePreloadingControls = (visible) => {
		if (visible != "" && visible != '-1') {
			$('#customize-control-pre_loading_logo').show();
			$('#customize-control-pre_loading_bg_color').show();
			$('#customize-control-pre_loading_icon_color').show();
			$('#customize-control-ajax_loading_effect').show();
		} else {
			$('#customize-control-pre_loading_logo').hide();
			$('#customize-control-pre_loading_bg_color').hide();
			$('#customize-control-pre_loading_icon_color').hide();
			$('#customize-control-ajax_loading_effect').hide();
		}
	}

	var toggleArchiveNavigationSameTermControls = (visible) => {
		if (visible) {
			$('#customize-control-archive_navigation_term_taxonomy').show();
		} else {
			$('#customize-control-archive_navigation_term_taxonomy').hide();
		}
	}

	wp.customize('archive_navigation_same_term', function (control, a) {
		toggleArchiveNavigationSameTermControls(wp.customize.settings.settings.archive_navigation_same_term.value);
		control.bind(function (value) {
			toggleArchiveNavigationSameTermControls(value);
		});
	});

	wp.customize('site_custom_colors', function (control, a) {
		toggleSiteCustomColorControls(wp.customize.settings.settings.site_custom_colors.value);
		control.bind(function (value) {
			toggleSiteCustomColorControls(value);
		});
	});

	wp.customize('header_custom_colors', function (control, a) {
		toggleHeaderCustomColors(wp.customize.settings.settings.header_custom_colors.value);
		control.bind(function (value) {
			toggleHeaderCustomColors(value);
		});
	});

	wp.customize('header_bottom_custom_colors', function (control, a) {
		toggleHeaderBottomCustomColors(wp.customize.settings.settings.header_bottom_custom_colors.value);
		control.bind(function (value) {
			toggleHeaderBottomCustomColors(value);
		});
	});


	wp.customize('mobile_menu_custom_color', function (control, a) {
		toggleMobileMenuCustomColors(wp.customize.settings.settings.mobile_menu_custom_color.value);
		control.bind(function (value) {
			toggleMobileMenuCustomColors(value);
		});
	});

	wp.customize('pre_loading', function (control, a) {
		togglePreloadingControls(wp.customize.settings.settings.pre_loading.value);
		control.bind(function (value) {
			togglePreloadingControls(value);
		});
	});

	wp.customize('amp', function (control, a) {
		toggleAMPControls(wp.customize.settings.settings.amp.value);
		control.bind(function (value) {
			toggleAMPControls(value);
		});
	});

	const toggleArchiveGenresControls = (visible) => {
		if (visible) {
			$('#customize-control-manga_archive_genres_collapse').show();
			$('#customize-control-manga_archive_genres_title').show();
		} else {
			$('#customize-control-manga_archive_genres_collapse').hide();
			$('#customize-control-manga_archive_genres_title').hide();
		}
	}

	wp.customize('manga_archive_genres', function (control, a) {
		toggleArchiveGenresControls(wp.customize.settings.settings.manga_archive_genres.value);
		control.bind(function (value) {
			toggleArchiveGenresControls(value);
		});
	});

	const toggleArchiveItemLayoutControls = (val) => {
		if (val == 'big_thumbnail') {
			$('#customize-control-manga_archives_item_mobile_width').show();
		} else {
			$('#customize-control-manga_archives_item_mobile_width').hide();
		}
	}

	wp.customize('manga_archives_item_layout', function (control, a) {
		toggleArchiveItemLayoutControls(wp.customize.settings.settings.manga_archives_item_layout.value);
		control.bind(function (value) {
			toggleArchiveItemLayoutControls(value);
		});
	});

	const toggleReadingHistoryControls = (val) => {
		if (val) {
			$('#customize-control-madara_reading_history_delay').show();
			$('#customize-control-madara_reading_history_items').show();
		} else {
			$('#customize-control-madara_reading_history_delay').hide();
			$('#customize-control-madara_reading_history_items').hide();
		}
	}

	wp.customize('madara_reading_history', function (control, a) {
		toggleReadingHistoryControls(wp.customize.settings.settings.madara_reading_history.value);
		control.bind(function (value) {
			toggleReadingHistoryControls(value);
		});
	});

	const toggleReadingStyleControls = (val) => {
		if (val == 'paged') {
			$('#customize-control-manga_reading_navigation_by_pointer').show();
			$('#customize-control-manga_reading_images_per_page').show();
			$('#customize-control-manga_page_reading_ajax').show();
			$('#customize-control-manga_reading_content_gaps').hide();
		} else {
			$('#customize-control-manga_page_reading_ajax').hide();
			$('#customize-control-manga_reading_content_gaps').show();
			$('#customize-control-manga_reading_navigation_by_pointer').hide();
			$('#customize-control-manga_reading_images_per_page').hide();
		}
	}

	const toggleBadgeStyleControls = (val) => {
		if (val == 2) {
			$('#customize-control-manga_badge_style').show();
		} else {
			$('#customize-control-manga_badge_style').hide();
		}
	}

	wp.customize('manga_badge_position', function (control, a) {
		toggleBadgeStyleControls(wp.customize.settings.settings.manga_badge_position.value);
		control.bind(function (value) {
			toggleBadgeStyleControls(value);
		});
	})

	wp.customize('manga_reading_style', function (control, a) {
		toggleReadingStyleControls(wp.customize.settings.settings.manga_reading_style.value);
		control.bind(function (value) {
			toggleReadingStyleControls(value);
		});
	});



	const toggleReadingStickerHeaderControls = (val) => {
		if (val != 'off') {

			$('#customize-control-manga_reading_sticky_navigation_mobile').show();
			$('#customize-control-manga_reading_sticky_navigation').show();
		} else {
			$('#customize-control-manga_reading_sticky_navigation_mobile').hide();
			$('#customize-control-manga_reading_sticky_navigation').hide();
		}
	}

	wp.customize('manga_reading_sticky_header', function (control, a) {
		toggleReadingStickerHeaderControls(wp.customize.settings.settings.manga_reading_sticky_header.value);
		control.bind(function (value) {
			toggleReadingStickerHeaderControls(value);
		});
	});

	const fontControls = ['font_using_custom', 'main_font_on_google', 'heading_font_on_google', 'navigation_font_on_google', 'meta_font_on_google'];
	fontControls.forEach(function (control_name) {
		wp.customize(control_name, function (control, a) {
			toggleCustomFontsControls(true);
			control.bind(function (value) {
				toggleCustomFontsControls();
			});
		});
	})


	/*
	require( get_template_directory() . '/css/custom.css.php' );
			$custom_css = madara_custom_CSS();
			wp_add_inline_style( 'madara-css', $custom_css );
	*/

	/**
	 * Sortable Repeater Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	// Update the values for all our input fields and initialise the sortable repeater
	$('.sortable_repeater_control').each(function () {
		var input_group = $(this).data('inputs');
		var group_count = 0;
		if (input_group) {
			group_count = Object.keys(input_group).length;
		}

		// If there is an existing customizer value, populate our rows
		var defaultValuesArray = $(this).find('.customize-control-sortable-repeater').val().split(',');
		var numRepeaterItems = defaultValuesArray.length;

		if (numRepeaterItems > 0) {
			// Fill values for the first group
			for (var i = 0; i < group_count; i++) {
				var inputs = $(this).find('.repeater-input');
				$(inputs[i]).val(defaultValuesArray[i]);
			}

			// Create a new row for each new value
			if (numRepeaterItems > group_count) {
				var i;
				for (i = group_count; i < numRepeaterItems; ++i) {
					var values = [];
					Object.keys(input_group).forEach(function (key, idx) {
						i = i + idx;
						values.push(defaultValuesArray[i]);
					})
					skyrocketAppendRow($(this), values);
				}
			}
		}
	});

	// Make our Repeater fields sortable
	$(this).find('.sortable_repeater.sortable').sortable({
		update: function (event, ui) {
			skyrocketGetAllInputs($(this).parent());
		}
	});

	// Remove item starting from it's parent element
	$('.sortable_repeater.sortable').on('click', '.customize-control-sortable-repeater-delete', function (event) {
		event.preventDefault();
		var numItems = $(this).parent().parent().find('.repeater').length;

		if (numItems > 1) {
			$(this).parent().slideUp('fast', function () {
				var parentContainer = $(this).parent().parent();
				$(this).remove();
				skyrocketGetAllInputs(parentContainer);
			})
		}
		else {
			$(this).parent().find('.repeater-input').val('');
			skyrocketGetAllInputs($(this).parent().parent().parent());
		}
	});

	// Add new item
	$('.customize-control-sortable-repeater-add').click(function (event) {
		event.preventDefault();
		skyrocketAppendRow($(this).parent());
		skyrocketGetAllInputs($(this).parent());
	});

	// Refresh our hidden field if any fields change
	$('.sortable_repeater.sortable').change(function () {
		skyrocketGetAllInputs($(this).parent());
	})

	// Add https:// to the start of the URL if it doesn't have it
	$('.sortable_repeater.sortable').on('blur', '.repeater-input', function () {
		var url = $(this);
		var val = url.val();
		if (val && url.attr('type') == 'url' && !val.match(/^.+:\/\/.*/)) {
			// Important! Make sure to trigger change event so Customizer knows it has to save the field
			url.val('https://' + val).trigger('change');
		}
	});

	// Append a new row to our list of elements
	function skyrocketAppendRow($element, defaultValues = []) {
		var input_group = $element.data('inputs');
		var group_count = 0;
		if (input_group) {
			group_count = Object.keys(input_group).length;
		}

		var newRow = '<div class="repeater" style="display:none">';

		Object.keys(input_group).forEach(function (elem, idx) {
			newRow += '<span>' + input_group[elem].label + '</span> <input type="' + input_group[elem].type + '" value="' + (defaultValues[idx] ? defaultValues[idx] : '') + '" class="repeater-input repeater-input-' + (elem == 'default' ? '' : '-' + elem) + '" placeholder="' + input_group[elem].placeholder + '" />';
		});

		newRow += '<span class="dashicons dashicons-sort"></span><a class="customize-control-sortable-repeater-delete" href="#"><span class="dashicons dashicons-no-alt"></span></a></div>';

		$element.find('.sortable').append(newRow);
		$element.find('.sortable').find('.repeater:last').slideDown('slow', function () {
			$(this).find('input').focus();
		});
	}

	// Get the values from the repeater input fields and add to our hidden field
	function skyrocketGetAllInputs($element) {
		var inputValues = $element.find('.repeater-input').map(function () {
			return $(this).val();
		}).toArray();
		// Add all the values from our repeater fields to the hidden field (which is the one that actually gets saved)
		$element.find('.customize-control-sortable-repeater').val(inputValues);
		// Important! Make sure to trigger change event so Customizer knows it has to save the field
		$element.find('.customize-control-sortable-repeater').trigger('change');
	}

	/**
	 * Slider Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	// Set our slider defaults and initialise the slider
	$('.slider-custom-control').each(function () {
		var sliderValue = $(this).find('.customize-control-slider-value').val();
		var newSlider = $(this).find('.slider');
		var sliderMinValue = parseFloat(newSlider.attr('slider-min-value'));
		var sliderMaxValue = parseFloat(newSlider.attr('slider-max-value'));
		var sliderStepValue = parseFloat(newSlider.attr('slider-step-value'));

		newSlider.slider({
			value: sliderValue,
			min: sliderMinValue,
			max: sliderMaxValue,
			step: sliderStepValue,
			change: function (e, ui) {
				// Important! When slider stops moving make sure to trigger change event so Customizer knows it has to save the field
				$(this).parent().find('.customize-control-slider-value').trigger('change');
			}
		});
	});

	// Change the value of the input field as the slider is moved
	$('.slider').on('slide', function (event, ui) {
		$(this).parent().find('.customize-control-slider-value').val(ui.value);
	});

	// Reset slider and input field back to the default value
	$('.slider-reset').on('click', function () {
		var resetValue = $(this).attr('slider-reset-value');
		$(this).parent().find('.customize-control-slider-value').val(resetValue);
		$(this).parent().find('.slider').slider('value', resetValue);
	});

	// Update slider if the input field loses focus as it's most likely changed
	$('.customize-control-slider-value').blur(function () {
		var resetValue = $(this).val();
		var slider = $(this).parent().find('.slider');
		var sliderMinValue = parseInt(slider.attr('slider-min-value'));
		var sliderMaxValue = parseInt(slider.attr('slider-max-value'));

		// Make sure our manual input value doesn't exceed the minimum & maxmium values
		if (resetValue < sliderMinValue) {
			resetValue = sliderMinValue;
			$(this).val(resetValue);
		}
		if (resetValue > sliderMaxValue) {
			resetValue = sliderMaxValue;
			$(this).val(resetValue);
		}
		$(this).parent().find('.slider').slider('value', resetValue);
	});

	/**
	 * Single Accordion Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	$('.single-accordion-toggle').click(function () {
		var $accordionToggle = $(this);
		$(this).parent().find('.single-accordion').slideToggle('slow', function () {
			$accordionToggle.toggleClass('single-accordion-toggle-rotate', $(this).is(':visible'));
		});
	});

	/**
	 * Image Checkbox Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	$('.multi-image-checkbox').on('change', function () {
		skyrocketGetAllImageCheckboxes($(this).parent().parent());
	});

	// Get the values from the checkboxes and add to our hidden field
	function skyrocketGetAllImageCheckboxes($element) {
		var inputValues = $element.find('.multi-image-checkbox').map(function () {
			if ($(this).is(':checked')) {
				return $(this).val();
			}
		}).toArray();
		// Important! Make sure to trigger change event so Customizer knows it has to save the field
		$element.find('.customize-control-multi-image-checkbox').val(inputValues).trigger('change');
	}

	/**
	 * Pill Checkbox Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	$(".pill_checkbox_control .sortable").sortable({
		placeholder: "pill-ui-state-highlight",
		update: function (event, ui) {
			skyrocketGetAllPillCheckboxes($(this).parent());
		}
	});

	$('.pill_checkbox_control .sortable-pill-checkbox').on('change', function () {
		skyrocketGetAllPillCheckboxes($(this).parent().parent().parent());
	});

	// Get the values from the checkboxes and add to our hidden field
	function skyrocketGetAllPillCheckboxes($element) {
		var inputValues = $element.find('.sortable-pill-checkbox').map(function () {
			if ($(this).is(':checked')) {
				return $(this).val();
			}
		}).toArray();
		$element.find('.customize-control-sortable-pill-checkbox').val(inputValues).trigger('change');
	}

	/**
	 * Dropdown Select2 Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	$('.customize-control-dropdown-select2').each(function () {
		$('.customize-control-select2').select2({
			allowClear: true
		});
	});

	$(".customize-control-select2").on("change", function () {
		var select2Val = $(this).val();
		$(this).parent().find('.customize-control-dropdown-select2').val(select2Val).trigger('change');
	});

	/**
	 * Googe Font Select Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	$('.google-fonts-list').each(function (i, obj) {
		if (!$(obj).hasClass('select2-hidden-accessible')) {
			$(obj).select2();
		}
	});

	$('.google-fonts-list').on('change', function () {
		var elementRegularWeight = $(this).parent().parent().find('.google-fonts-regularweight-style');
		var elementItalicWeight = $(this).parent().parent().find('.google-fonts-italicweight-style');
		var elementBoldWeight = $(this).parent().parent().find('.google-fonts-boldweight-style');
		var selectedFont = $(this).val();
		var customizerControlName = $(this).attr('control-name');
		var elementItalicWeightCount = 0;
		var elementBoldWeightCount = 0;

		// Clear Weight/Style dropdowns
		elementRegularWeight.empty();
		elementItalicWeight.empty();
		elementBoldWeight.empty();
		// Make sure Italic & Bold dropdowns are enabled
		elementItalicWeight.prop('disabled', false);
		elementBoldWeight.prop('disabled', false);

		// Get the Google Fonts control object
		var bodyfontcontrol = _wpCustomizeSettings.controls[customizerControlName];

		// Find the index of the selected font
		var indexes = $.map(bodyfontcontrol.skyrocketfontslist, function (obj, index) {
			if (obj.family === selectedFont) {
				return index;
			}
		});
		var index = indexes[0];

		// For the selected Google font show the available weight/style variants
		$.each(bodyfontcontrol.skyrocketfontslist[index].variants, function (val, text) {
			elementRegularWeight.append(
				$('<option></option>').val(text).html(text)
			);
			if (text.indexOf("italic") >= 0) {
				elementItalicWeight.append(
					$('<option></option>').val(text).html(text)
				);
				elementItalicWeightCount++;
			} else {
				elementBoldWeight.append(
					$('<option></option>').val(text).html(text)
				);
				elementBoldWeightCount++;
			}
		});

		if (elementItalicWeightCount == 0) {
			elementItalicWeight.append(
				$('<option></option>').val('').html('Not Available for this font')
			);
			elementItalicWeight.prop('disabled', 'disabled');
		}
		if (elementBoldWeightCount == 0) {
			elementBoldWeight.append(
				$('<option></option>').val('').html('Not Available for this font')
			);
			elementBoldWeight.prop('disabled', 'disabled');
		}

		// Update the font category based on the selected font
		$(this).parent().parent().find('.google-fonts-category').val(bodyfontcontrol.skyrocketfontslist[index].category);

		skyrocketGetAllSelects($(this).parent().parent());
	});

	$('.google_fonts_select_control select').on('change', function () {
		skyrocketGetAllSelects($(this).parent().parent());
	});

	function skyrocketGetAllSelects($element) {
		var selectedFont = {
			font: $element.find('.google-fonts-list').val(),
			regularweight: $element.find('.google-fonts-regularweight-style').val(),
			italicweight: $element.find('.google-fonts-italicweight-style').val(),
			boldweight: $element.find('.google-fonts-boldweight-style').val(),
			category: $element.find('.google-fonts-category').val()
		};

		// Important! Make sure to trigger change event so Customizer knows it has to save the field
		$element.find('.customize-control-google-font-selection').val(JSON.stringify(selectedFont)).trigger('change');
	}

	/**
	 * TinyMCE Custom Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	$('.customize-control-tinymce-editor').each(function () {
		// Get the toolbar strings that were passed from the PHP Class
		var tinyMCEToolbar1String = _wpCustomizeSettings.controls[$(this).attr('id')].skyrockettinymcetoolbar1;
		var tinyMCEToolbar2String = _wpCustomizeSettings.controls[$(this).attr('id')].skyrockettinymcetoolbar2;
		var tinyMCEMediaButtons = _wpCustomizeSettings.controls[$(this).attr('id')].skyrocketmediabuttons;

		wp.editor.initialize($(this).attr('id'), {
			tinymce: {
				wpautop: true,
				toolbar1: tinyMCEToolbar1String,
				toolbar2: tinyMCEToolbar2String
			},
			quicktags: true,
			mediaButtons: tinyMCEMediaButtons
		});
	});
	$(document).on('tinymce-editor-init', function (event, editor) {
		editor.on('change', function (e) {
			tinyMCE.triggerSave();
			$('#' + editor.id).trigger('change');
		});
	});

	/**
	 * WP ColorPicker Alpha Color Picker Control
	 *
	 * @author Anthony Hortin <http://maddisondesigns.com>
	 * @license http://www.gnu.org/licenses/gpl-2.0.html
	 * @link https://github.com/maddisondesigns
	 */

	// Manually initialise the wpColorPicker controls so we can add the color picker palette
	$('.wpcolorpicker-alpha-color-picker').each(function (i, obj) {
		var colorPickerInput = $(this);
		var paletteColors = _wpCustomizeSettings.controls[$(this).attr('id')].colorpickerpalette;
		var options = {
			palettes: paletteColors,
			change: function (event, ui) {
				// Set 1 ms timeout so input field is changed before change event is triggered
				// See: https://github.com/Automattic/Iris/issues/55#issuecomment-303716820
				setTimeout(function () {
					// Important! Make sure to trigger change event so Customizer knows it has to save the field
					colorPickerInput.trigger('change');
				}, 1);
			}
		};
		$(obj).wpColorPicker(options);
	});

	/**
	   * Alpha Color Picker Custom Control
	   *
	   * @author Braad Martin <http://braadmartin.com>
	   * @license http://www.gnu.org/licenses/gpl-3.0.html
	   * @link https://github.com/BraadMartin/components/tree/master/customizer/alpha-color-picker
	   */

	// Loop over each control and transform it into our color picker.
	$('.alpha-color-control').each(function () {

		// Scope the vars.
		var $control, startingColor, paletteInput, showOpacity, defaultColor, palette,
			colorPickerOptions, $container, $alphaSlider, alphaVal, sliderOptions;

		// Store the control instance.
		$control = $(this);

		// Get a clean starting value for the option.
		startingColor = $control.val().replace(/\s+/g, '');

		// Get some data off the control.
		paletteInput = $control.attr('data-palette');
		showOpacity = $control.attr('data-show-opacity');
		defaultColor = $control.attr('data-default-color');

		// Process the palette.
		if (paletteInput.indexOf('|') !== -1) {
			palette = paletteInput.split('|');
		} else if ('false' == paletteInput) {
			palette = false;
		} else {
			palette = true;
		}

		// Set up the options that we'll pass to wpColorPicker().
		colorPickerOptions = {
			change: function (event, ui) {
				var key, value, alpha, $transparency;

				key = $control.attr('data-customize-setting-link');
				value = $control.wpColorPicker('color');

				// Set the opacity value on the slider handle when the default color button is clicked.
				if (defaultColor == value) {
					alpha = acp_get_alpha_value_from_color(value);
					$alphaSlider.find('.ui-slider-handle').text(alpha);
				}

				// Send ajax request to wp.customize to trigger the Save action.
				wp.customize(key, function (obj) {
					obj.set(value);
				});

				$transparency = $container.find('.transparency');

				// Always show the background color of the opacity slider at 100% opacity.
				$transparency.css('background-color', ui.color.toString('no-alpha'));
			},
			palettes: palette // Use the passed in palette.
		};

		// Create the colorpicker.
		$control.wpColorPicker(colorPickerOptions);

		$container = $control.parents('.wp-picker-container:first');

		// Insert our opacity slider.
		$('<div class="alpha-color-picker-container">' +
			'<div class="min-click-zone click-zone"></div>' +
			'<div class="max-click-zone click-zone"></div>' +
			'<div class="alpha-slider"></div>' +
			'<div class="transparency"></div>' +
			'</div>').appendTo($container.find('.wp-picker-holder'));

		$alphaSlider = $container.find('.alpha-slider');

		// If starting value is in format RGBa, grab the alpha channel.
		alphaVal = acp_get_alpha_value_from_color(startingColor);

		// Set up jQuery UI slider() options.
		sliderOptions = {
			create: function (event, ui) {
				var value = $(this).slider('value');

				// Set up initial values.
				$(this).find('.ui-slider-handle').text(value);
				$(this).siblings('.transparency ').css('background-color', startingColor);
			},
			value: alphaVal,
			range: 'max',
			step: 1,
			min: 0,
			max: 100,
			animate: 300
		};

		// Initialize jQuery UI slider with our options.
		$alphaSlider.slider(sliderOptions);

		// Maybe show the opacity on the handle.
		if ('true' == showOpacity) {
			$alphaSlider.find('.ui-slider-handle').addClass('show-opacity');
		}

		// Bind event handlers for the click zones.
		$container.find('.min-click-zone').on('click', function () {
			acp_update_alpha_value_on_color_control(0, $control, $alphaSlider, true);
		});
		$container.find('.max-click-zone').on('click', function () {
			acp_update_alpha_value_on_color_control(100, $control, $alphaSlider, true);
		});

		// Bind event handler for clicking on a palette color.
		$container.find('.iris-palette').on('click', function () {
			var color, alpha;

			color = $(this).css('background-color');
			alpha = acp_get_alpha_value_from_color(color);

			acp_update_alpha_value_on_alpha_slider(alpha, $alphaSlider);

			// Sometimes Iris doesn't set a perfect background-color on the palette,
			// for example rgba(20, 80, 100, 0.3) becomes rgba(20, 80, 100, 0.298039).
			// To compensante for this we round the opacity value on RGBa colors here
			// and save it a second time to the color picker object.
			if (alpha != 100) {
				color = color.replace(/[^,]+(?=\))/, (alpha / 100).toFixed(2));
			}

			$control.wpColorPicker('color', color);
		});

		// Bind event handler for clicking on the 'Clear' button.
		$container.find('.button.wp-picker-clear').on('click', function () {
			var key = $control.attr('data-customize-setting-link');

			// The #fff color is delibrate here. This sets the color picker to white instead of the
			// defult black, which puts the color picker in a better place to visually represent empty.
			$control.wpColorPicker('color', '#ffffff');

			// Set the actual option value to empty string.
			wp.customize(key, function (obj) {
				obj.set('');
			});

			acp_update_alpha_value_on_alpha_slider(100, $alphaSlider);
		});

		// Bind event handler for clicking on the 'Default' button.
		$container.find('.button.wp-picker-default').on('click', function () {
			var alpha = acp_get_alpha_value_from_color(defaultColor);

			acp_update_alpha_value_on_alpha_slider(alpha, $alphaSlider);
		});

		// Bind event handler for typing or pasting into the input.
		$control.on('input', function () {
			var value = $(this).val();
			var alpha = acp_get_alpha_value_from_color(value);

			acp_update_alpha_value_on_alpha_slider(alpha, $alphaSlider);
		});

		// Update all the things when the slider is interacted with.
		$alphaSlider.slider().on('slide', function (event, ui) {
			var alpha = parseFloat(ui.value) / 100.0;

			acp_update_alpha_value_on_color_control(alpha, $control, $alphaSlider, false);

			// Change value shown on slider handle.
			$(this).find('.ui-slider-handle').text(ui.value);
		});

	});

	/**
	 * Override the stock color.js toString() method to add support for outputting RGBa or Hex.
	 */
	Color.prototype.toString = function (flag) {

		// If our no-alpha flag has been passed in, output RGBa value with 100% opacity.
		// This is used to set the background color on the opacity slider during color changes.
		if ('no-alpha' == flag) {
			return this.toCSS('rgba', '1').replace(/\s+/g, '');
		}

		// If we have a proper opacity value, output RGBa.
		if (1 > this._alpha) {
			return this.toCSS('rgba', this._alpha).replace(/\s+/g, '');
		}

		// Proceed with stock color.js hex output.
		var hex = parseInt(this._color, 10).toString(16);
		if (this.error) { return ''; }
		if (hex.length < 6) {
			for (var i = 6 - hex.length - 1; i >= 0; i--) {
				hex = '0' + hex;
			}
		}

		return '#' + hex;
	};

	/**
	 * Given an RGBa, RGB, or hex color value, return the alpha channel value.
	 */
	function acp_get_alpha_value_from_color(value) {
		var alphaVal;

		// Remove all spaces from the passed in value to help our RGBa regex.
		value = value.replace(/ /g, '');

		if (value.match(/rgba\(\d+\,\d+\,\d+\,([^\)]+)\)/)) {
			alphaVal = parseFloat(value.match(/rgba\(\d+\,\d+\,\d+\,([^\)]+)\)/)[1]).toFixed(2) * 100;
			alphaVal = parseInt(alphaVal);
		} else {
			alphaVal = 100;
		}

		return alphaVal;
	}

	/**
	 * Force update the alpha value of the color picker object and maybe the alpha slider.
	 */
	function acp_update_alpha_value_on_color_control(alpha, $control, $alphaSlider, update_slider) {
		var iris, colorPicker, color;

		iris = $control.data('a8cIris');
		colorPicker = $control.data('wpWpColorPicker');

		// Set the alpha value on the Iris object.
		iris._color._alpha = alpha;

		// Store the new color value.
		color = iris._color.toString();

		// Set the value of the input.
		$control.val(color);

		// Update the background color of the color picker.
		colorPicker.toggler.css({
			'background-color': color
		});

		// Maybe update the alpha slider itself.
		if (update_slider) {
			acp_update_alpha_value_on_alpha_slider(alpha, $alphaSlider);
		}

		// Update the color value of the color picker object.
		$control.wpColorPicker('color', color);
	}

	/**
	 * Update the slider handle position and label.
	 */
	function acp_update_alpha_value_on_alpha_slider(alpha, $alphaSlider) {
		$alphaSlider.slider('value', alpha);
		$alphaSlider.find('.ui-slider-handle').text(alpha.toString());
	}

});

/**
 * Remove attached events from the Upsell Section to stop panel from being able to open/close
 */
(function ($, api) {
	api.sectionConstructor['madara-upsell'] = api.Section.extend({

		// Remove events for this type of section.
		attachEvents: function () { },

		// Ensure this type of section is active. Normally, sections without contents aren't visible.
		isContextuallyActive: function () {
			return true;
		}
	});

	/**
	 * A colorpicker control.
	 *
	 * @class    wp.customize.ColorControl
	 * @augments wp.customize.Control
	 */
	api.BackgroundSettingControl = api.Control.extend(/** @lends wp.customize.ColorControl.prototype */{
		colorPicker: function () {
			var control = this,
				isHueSlider = this.params.mode === 'hue',
				updating = false,
				picker;

			if (isHueSlider) {
				picker = this.container.find('.color-picker-hue');
				picker.val(control.setting()).wpColorPicker({
					change: function (event, ui) {
						updating = true;
						control.setting(ui.color.h());
						updating = false;
					}
				});
			} else {
				picker = this.container.find('.color-picker-hex');
				picker.val(control.setting()).wpColorPicker({
					change: function () {
						updating = true;
						control.setting.set(picker.wpColorPicker('color'));
						updating = false;
					},
					clear: function () {
						updating = true;
						control.setting.set('');
						updating = false;
					}
				});
			}

			control.setting.bind(function (value) {
				// Bail if the update came from the control itself.
				if (updating) {
					return;
				}
				picker.val(value);
				picker.wpColorPicker('color', value);
			});

			// Collapse color picker when hitting Esc instead of collapsing the current section.
			control.container.on('keydown', function (event) {
				var pickerContainer;
				if (27 !== event.which) { // Esc.
					return;
				}
				pickerContainer = control.container.find('.wp-picker-container');
				if (pickerContainer.hasClass('wp-picker-active')) {
					picker.wpColorPicker('close');
					control.container.find('.wp-color-result').focus();
					event.stopPropagation(); // Prevent section from being collapsed.
				}
			});
		},

		ready: function () {
			var control = this;
			control.colorPicker();
			// Shortcut so that we don't have to use _.bind every time we add a callback.
			_.bindAll(control, 'restoreDefault', 'removeFile', 'openFrame', 'select');

			// Bind events, with delegation to facilitate re-rendering.
			control.container.on('click keydown', '.upload-button', control.openFrame);
			control.container.on('click keydown', '.thumbnail-image img', control.openFrame);
			control.container.on('click keydown', '.default-button', control.restoreDefault);
			control.container.on('click keydown', '.remove-button', control.removeFile);

			/**
			 * Set attachment data and render content.
			 *
			 * Note that BackgroundImage.prototype.ready applies this ready method
			 * to itself. Since BackgroundImage is an UploadControl, the value
			 * is the attachment URL instead of the attachment ID. In this case
			 * we skip fetching the attachment data because we have no ID available,
			 * and it is the responsibility of the UploadControl to set the control's
			 * attachmentData before calling the renderContent method.
			 *
			 * @param {number|string} value Attachment
			 */
			function setAttachmentDataAndRenderContent(value) {
				var hasAttachmentData = $.Deferred();

				if (control.extended(api.UploadControl)) {
					hasAttachmentData.resolve();
				} else {
					value = parseInt(value, 10);
					if (_.isNaN(value) || value <= 0) {
						delete control.params.attachment;
						hasAttachmentData.resolve();
					} else if (control.params.attachment && control.params.attachment.id === value) {
						hasAttachmentData.resolve();
					}
				}

				// Fetch the attachment data.
				if ('pending' === hasAttachmentData.state()) {
					wp.media.attachment(value).fetch().done(function () {
						control.params.attachment = this.attributes;
						hasAttachmentData.resolve();

						// Send attachment information to the preview for possible use in `postMessage` transport.
						wp.customize.previewer.send(control.setting.id + '-attachment-data', this.attributes);
					});
				}

				hasAttachmentData.done(function () {
					control.renderContent();
					control.colorPicker();
				});
			}

			// Ensure attachment data is initially set (for dynamically-instantiated controls).
			setAttachmentDataAndRenderContent(control.setting());

			// Update the attachment data and re-render the control when the setting changes.
			control.setting.bind(setAttachmentDataAndRenderContent);
		},
		pausePlayer: function () {
			this.player && this.player.pause();
		},

		cleanupPlayer: function () {
			this.player && wp.media.mixin.removePlayer(this.player);
		},

		/**
		 * Open the media modal.
		 */
		openFrame: function (event) {
			if (api.utils.isKeydownButNotEnterEvent(event)) {
				return;
			}

			event.preventDefault();

			if (!this.frame) {
				this.initFrame();
			}

			this.frame.open();
		},

		/**
		 * Create a media modal select frame, and store it so the instance can be reused when needed.
		 */
		initFrame: function () {
			this.frame = wp.media({
				button: {
					text: this.params.button_labels.frame_button
				},
				states: [
					new wp.media.controller.Library({
						title: this.params.button_labels.frame_title,
						library: wp.media.query({ type: this.params.mime_type }),
						multiple: false,
						date: false
					})
				]
			});

			// When a file is selected, run a callback.
			this.frame.on('select', this.select);
		},

		/**
		 * Reset the setting to the default value.
		 */
		restoreDefault: function (event) {
			if (api.utils.isKeydownButNotEnterEvent(event)) {
				return;
			}
			event.preventDefault();

			this.params.attachment = this.params.defaultAttachment;
			this.setting(this.params.defaultAttachment.url);
		},

		/**
		 * Called when the "Remove" link is clicked. Empties the setting.
		 *
		 * @param {Object} event jQuery Event object
		 */
		removeFile: function (event) {
			if (api.utils.isKeydownButNotEnterEvent(event)) {
				return;
			}
			event.preventDefault();

			this.params.attachment = {};
			this.setting('');
			this.renderContent(); // Not bound to setting change when emptying.
		},

		/**
		 * Callback handler for when an attachment is selected in the media modal.
		 * Gets the selected image information, and sets it within the control.
		 */
		select: function () {
			// Get the attachment from the modal frame.
			var attachment = this.frame.state().get('selection').first().toJSON();

			this.params.attachment = attachment;

			// Set the Customizer setting; the callback takes care of rendering.
			this.setting(attachment.url);
		},

		// @deprecated
		success: function () { },

		// @deprecated
		removerVisibility: function () { }
	});

	api.controlConstructor.background_setting = api.BackgroundSettingControl;
})(jQuery, wp.customize);
