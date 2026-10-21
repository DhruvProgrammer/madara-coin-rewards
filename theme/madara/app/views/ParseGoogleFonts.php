<?php

	/**
	 * Class parseGoogleFonts
	 *
	 * @since Madara Alpha 1.0
	 * @package madara
	 */

	namespace App\Views;

	use App\Models\Entity;

	class ParseGoogleFonts {
		private $typography;

		public function __construct() {
			$this->typography = new Entity\Typography();
		}

		public function render() {
			/**
			 * Main Font Famly
			 * Heading Font Family
			 * Navigation Font Family
			 */
			$googleFonts = array();

			$mainGoogleFont = $this->typography->getMainFontFamily();
			if($mainGoogleFont){
				array_push( $googleFonts, $mainGoogleFont );
			}
			
			$headingGoogleFont = $this->typography->getHeadingFontFamily();
			if($headingGoogleFont){
				array_push( $googleFonts, $headingGoogleFont );
			}

			$navGoogleFont = $this->typography->getNavigationFontFamily();
			if($navGoogleFont){
				array_push( $googleFonts, $navGoogleFont );
			}

			$metaGoogleFont = $this->typography->getMetaFontFamily();
			if($metaGoogleFont){
				array_push( $googleFonts, $metaGoogleFont );
			}

			return $googleFonts;
		}
	}