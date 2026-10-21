<?php
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use App\Madara;

require_once __DIR__ . '/../base-widget.php';

/**
 * Elementor - Manga Chapters section, to be used in Manga Detail page.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Madara_Chapters extends Elementor_Madara_Widget
{

	public function __construct($data = [], $args = null)
	{
		parent::__construct($data, $args);
	}

	/**
	 * Get widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget name.
	 */
	public function get_name()
	{
		return 'madara-chapters';
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
	public function get_title()
	{
		return esc_html__('Manga Chapters', 'madara');
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
	public function get_icon()
	{
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
	public function get_categories()
	{
		return ['manga'];
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
	public function get_keywords()
	{
		return ['madara', 'manga'];
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
	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$post_id = get_the_ID();
		if (is_singular('wp-manga')) {
			if(isset($settings['hide_on_reading']) && $settings['hide_on_reading'] == 'yes'){
				global $wp_manga_functions;
				if ( $wp_manga_functions->is_manga_reading_page() ) {
					return;
				}
			}
			
			do_action('wp-manga-chapter-listing', $post_id);
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
	protected function content_template()
	{
?>
		<div class="<?php echo madara_get_default_heading_style();?> font-heading">
			<h2 class="h4">
				<i class="icon ion-ios-star"></i>
				LATEST MANGA RELEASES
			</h2>
			<a href="#" title="Change Order" class="btn-reverse-order"><i class="icon ion-md-swap"></i></a>
		</div>
		<div class="page-content-listing single-page">
			<div class="listing-chapters_wrap cols-1 show-more">
				<ul class="main version-chap no-volumn">
					<li class="wp-manga-chapter">
						<a href="#">Chapter 1</a>
						<span class="chapter-release-date">
							<i>September 2, 2024</i> </span>
					</li>
					<li class="wp-manga-chapter">
						<a href="#">Chapter 2</a>
						<span class="chapter-release-date">
							<i>September 3, 2024</i> </span>
					</li>
					<li class="wp-manga-chapter">
						<a href="#">Chapter 3</a>
						<span class="chapter-release-date">
							<i>September 4, 2024</i> </span>
					</li>
				</ul>
			</div>
		</div>
<?php
	}
}
