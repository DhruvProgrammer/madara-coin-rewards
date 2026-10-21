<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor Manga History Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_History extends Elementor_Madara_Widget {

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
		return 'manga-history';
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
		return esc_html__( 'Manga Reading History', 'madara' );
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
		return 'eicon-editor-list-ul';
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

		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Widget Settings', 'madara' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'title',
			[
				'label' => esc_html__( 'Heading Title', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '',
			]
		);

		$this->add_control(
			'number_of_posts',
			[
				'label' => esc_html__( 'Number of items', 'madara' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'placeholder' => '6',
				'min' => 1,
				'max' => 100,
				'step' => 1,
				'default' => 6
			]
		);

		$this->end_controls_section();

		$this->register_core_settings();
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

        $count = isset( $settings['number_of_posts'] ) ? $settings['number_of_posts'] : 10;
        
		$this->before_widget();
		$this->render_title();
		?>
		
		<div class="my-history">
			<!-- -->
			<span class="no-histories" style="display:none"><?php esc_html_e('You don\'t have anything in history', 'madara');?></span>
		</div>
		<script type="text/javascript">
		jQuery(document).on('ready', function(){
			jQuery.ajax({
				url: manga.ajax_url,
				type: 'GET',
				data: {
					action: 'guest_histories',
					count: <?php echo $count;?>
				},
				success: function(html){
					if(html && html != "0") {
						jQuery('.elementor-element-<?php echo $this->get_id();?> .my-history').html(html);
					} else {
						jQuery('.elementor-element-<?php echo $this->get_id();?> .no-histories').show();
					}
				},
				complete: function(e){
					
				}
			});
		});
		</script>

			<?php
		$this->after_widget();
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
		$this->before_widget_template();
		$this->title_template();
		?>
		<div class="my-history">
			<# 
			
			arr = Array.from({length: settings.number_of_posts}, (v, i) => i);

			_.each(arr, function(item, index){ #>
			<div class="my-history-item-wrap">
				<div class="my-history-title">
					<a title="Manga Title {{ index }}" href="#">Manga Title {{ index }}</a>
				</div>
			</div>
			<# }); #>
		</div>
		<?php
		$this->after_widget_template();
	}

}