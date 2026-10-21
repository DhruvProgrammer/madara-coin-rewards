<?php
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/../base-widget.php';

/**
 * Elementor - Manga Summary section, to be used in Manga Detail page.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Madara_Summary extends Elementor_Madara_Widget
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
		return 'madara-summary';
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
		return esc_html__('Manga Summary', 'madara');
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
	protected function register_controls()
	{
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__('Section Settings', 'madara'),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'heading',
			[
				'label' => esc_html__( 'Section Heading', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'Summary',
			]
		);

		$this->add_control(
			'hide_on_reading',
			[
				'label' => esc_html__('Hide in Chapter Reading page', 'madara'),
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
		if (is_singular('wp-manga')) {
			if (isset($settings['hide_on_reading']) && $settings['hide_on_reading'] == 'yes') {
				global $wp_manga_functions;
				if ($wp_manga_functions->is_manga_reading_page()) {
					return;
				}
			}

			if(isset($settings['heading'])){?>
			<div class="<?php echo madara_get_default_heading_style();?> font-heading">
				<h2 class="h4">
					<i class="icon ion-ios-star"></i>
					<?php echo $settings['heading'];?>
				</h2>
			</div>
			<?php }?>
			<div class="description-summary show-more">
				<div class="summary__content show-more">
					<?php echo get_the_content();?>
				</div>

				<div class="c-content-readmore">
					<span class="btn btn-link content-readmore">
						Show more </span>
				</div>

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
	protected function content_template()
	{
		?>
		<# if(settings.heading){#>
		<div class="<?php echo madara_get_default_heading_style();?> font-heading">
			<h2 class="h4">
				<i class="icon ion-ios-star"></i>
				{{ settings.heading }}
			</h2>
		</div>
		<# } #>
		<div class="description-summary show-more">
			<div class="summary__content show-more">
				<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
			</div>

			<div class="c-content-readmore">
				<span class="btn btn-link content-readmore">Show more </span>
			</div>

		</div>
<?php
	}
}
