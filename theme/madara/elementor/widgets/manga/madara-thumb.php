<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/../base-widget.php';

/**
 * Elementor - Manga Thumb section, to be used in Manga Detail page.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Madara_Thumb extends Elementor_Madara_Widget {

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
		return 'madara-thumb';
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
		return esc_html__( 'Manga Featured Image', 'madara' );
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
		return 'eicon-menu-toggle';
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
		return [ 'manga' ];
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
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Section Settings', 'madara' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'hide_on_reading',
			[
				'label' => esc_html__( 'Hide in Chapter Reading page', 'madara' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
				'return_value' => 'yes',
			]
		);

		$this->end_controls_section();
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
		$settings = $this->get_settings_for_display();
		if(is_singular('wp-manga')){
			if(isset($settings['hide_on_reading']) && $settings['hide_on_reading'] == 'yes'){ 
				global $wp_manga_functions;
				if ( $wp_manga_functions->is_manga_reading_page() ) {
					return;
				}
			}
			$thumb_size = array( 193, 278 );
		?>
		<div class="summary_image">
			<a href="<?php echo get_the_permalink(); ?>">
				<?php echo madara_thumbnail( $thumb_size ); ?>
			</a>
		</div>
		<?php
		}
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
		<# 
		dummy_thumb = '<?php echo get_template_directory_uri();?>/images/elementor-post-vthumb.jpg';
		#>
		<div class="summary_image">
			<a href="#">
				<img width="193" height="278" data-src="{{dummy_thumb}}" class="img-responsive effect-fade lazyloaded" src="{{dummy_thumb}}" style="padding-top:278px;" alt="" >
			</a>
		</div>
		<?php
	}

}