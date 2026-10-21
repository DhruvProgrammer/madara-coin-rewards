<?php

	/**
	 * Class Typography
	 *
	 * @package madara
	 */

	namespace App\Models\Entity;

	use App\Models;

	class Typography extends Models\Metadata {
		private $defaultFonts = array();

		public function __construct() {
			$this->defaultFonts = [
				'Crimson+Text',
				'Playfair+Display',
				'Montserrat:400,700'
			];
		}

		public function getMainFontFamily() {
			if ( $this->getOption( 'main_font_on_google', 'off' ) == 'on' ) {
				return $this->getOption( 'main_font_google_family', '' );
			}
		}

		public function getHeadingFontFamily() {
			if ( $this->getOption( 'heading_font_on_google', 'off' ) == 'on' ) {
				$fontFamily = $this->getOption( 'heading_font_google_family', '' );

				return $fontFamily;
			}
		}

		public function getNavigationFontFamily() {
			if ( $this->getOption( 'navigation_font_on_google', 'off' ) == 'on' ) {
				$fontFamily = $this->getOption( 'navigation_font_google_family', '' );
				
				return $fontFamily;
			}
		}

		public function getMetaFontFamily() {
			if ( $this->getOption( 'meta_font_on_google', 'off' ) == 'on' ) {
				$fontFamily = $this->getOption( 'meta_font_google_family', '' );

				return $fontFamily;
			}
		}

		public function getGoogleFontName( $fontFamily ) {
			$fontFamilyName = is_array($fontFamily) ? $fontFamily[0]['family'] : $fontFamily;

			if ( \App\Helpers\Common::isStartWith( $fontFamilyName, 'http' ) ) {
				$idx = strpos( $fontFamilyName, '=' );
				if ( $idx > - 1 ) {
					$fontFamilyName = substr( $fontFamilyName, $idx );
				}
			}

			$idx = strpos( $fontFamilyName, ':' );

			if ( $idx > - 1 ) {
				$fontFamilyName = substr( $fontFamilyName, 0, $idx );
				$fontFamilyName = str_replace( '+', ' ', $fontFamilyName );
			}

			return $fontFamilyName;
		}
	}