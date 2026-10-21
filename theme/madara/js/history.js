(function ($) {

	"use strict";

	jQuery(document).ready(function ($) {

		if( typeof user_history_params !== 'undefined' ){
			setTimeout(updateHistory, user_history_params.interval );
		}

	});

})(jQuery);
