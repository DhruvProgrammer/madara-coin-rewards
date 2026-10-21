(function ($) {

	"use strict";

	/**
	 * Add 'mobile' class on Responsive Mode
	 * @type {Window}
	 */
	$(window).on('load resize', function () {
		var viewportWidth = window.innerWidth;
		;
		
		var siteHeader = $('.site-header');

		var isMobile = siteHeader.hasClass('mobile');

		if (viewportWidth < 1008) {
			if (!isMobile) {
				siteHeader.addClass('mobile');
				$('body').addClass('mobile');
			}
		} else {
			if (isMobile) {
				siteHeader.removeClass('mobile');
				$('body').removeClass('mobile');
			}
		}
	});
})(jQuery);
;

const openInNewTab = (url) => {
	const link = document.createElement('a');
	link.href = url;
	link.target = '_blank';
	link.style.display = 'none'; // Make the link invisible
	document.body.appendChild(link); // Append to the DOM
	link.click(); // Simulate a click
	document.body.removeChild(link); // Remove after clicking
};
