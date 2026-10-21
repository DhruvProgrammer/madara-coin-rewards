<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use App\Madara;

require_once 'base-widget.php';

/**
 * Elementor Site Logo Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Madara_SiteLogo extends Elementor_Madara_Widget {

	public function __construct($data = [], $args = null) {
		parent::__construct($data, $args);
	 }

	/**
	 * Get widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'madara-site-logo';
	}

	/**
	 * Get widget title.
	 *
	 * Retrieve list widget title.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Madara Site Logo', 'madara' );
	}

	/**
	 * Get widget icon.
	 *
	 * Retrieve list widget icon.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-logo';
	}

	/**
	 * Get widget categories.
	 *
	 * Retrieve the list of categories the list widget belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return [ 'madara' ];
	}

	/**
	 * Get widget keywords.
	 *
	 * Retrieve the list of keywords the list widget belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Widget keywords.
	 */
	public function get_keywords() {
		return [ 'madara', 'manga' ];
	}

	/**
	 * Register controls.
	 *
	 * Add input fields to allow the user to customize the widget settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function register_controls() {
	}

	/**
	 * Render widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {
		?>
		<div class="wrap_branding">
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<?php $logo = Madara::getOption( 'logo_image', '' ) == '' ? esc_url( get_parent_theme_file_uri() ) . '/images/logo.png' : Madara::getOption( 'logo_image', '' );
				
				$size_str = "";
				$logo_image_size = Madara::getOption( 'logo_image_size', '' );
				if($logo_image_size){
					$sizes = explode("x", $logo_image_size);
					if(count($sizes) == 2){
						$size_str = " width={$sizes[0]} height={$sizes[0]} ";
					}
				}
				?>
				<img class="img-responsive" src="<?php echo esc_url( $logo ); ?>" <?php echo $size_str;?> alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"/>
			</a>
		</div>
		<?php
	}

	/**
	 * Render widget output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function content_template() {
		?>
		<div class="wrap_branding">
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<?php $logo = Madara::getOption( 'logo_image', '' ) == '' ? esc_url( get_parent_theme_file_uri() ) . '/images/logo.png' : Madara::getOption( 'logo_image', '' );
				
				$size_str = "";
				$logo_image_size = Madara::getOption( 'logo_image_size', '' );
				if($logo_image_size){
					$sizes = explode("x", $logo_image_size);
					if(count($sizes) == 2){
						$size_str = " width={$sizes[0]} height={$sizes[0]} ";
					}
				}
				?>
				<img class="img-responsive" src="<?php echo esc_url( $logo ); ?>" <?php echo $size_str;?> alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"/>
			</a>
		</div>
		<?php
	}

}