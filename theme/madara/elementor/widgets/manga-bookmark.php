<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor Manga Bookmark Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_Bookmark extends Elementor_Madara_Widget {

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
		return 'manga-bookmark';
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
		return esc_html__( 'My Bookmarks', 'madara' );
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
				'label' => esc_html__( 'Widget Title', 'madara' ),
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

		$this->add_control(
			'style',
			[
				'label' => esc_html__( 'Style', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'style-1' => esc_html__( 'Style 1', 'madara' ),
					'style-2' => esc_html__( 'Style 2', 'madara' )
				],
				'default' => 'style-1',
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
		$style = isset( $settings['style'] ) ? $settings['style'] : 'style-1';
        
		$this->before_widget('c-popular manga-bookmark-widget', $style);
		$this->render_title();

		global $wp_manga_template;
		$user_id = get_current_user_id();
		if( $user_id == 0 ) {
			return;
		}

		$manga_bookmark = get_user_meta( $user_id, '_wp_manga_bookmark', true );
		
		// get the latest bookmark 
		if(is_array(($manga_bookmark))){
			$manga_bookmark = array_reverse( $manga_bookmark );

			foreach( $manga_bookmark as $manga ) {

				if( $count == 0 ) {
					break;
				}

				$manga_post = get_post( intval( $manga['id'] ) );

				if( $manga_post == null || $manga_post->post_status !== 'publish' ) {
					continue;
				}

				$count--;

				global $post;

				$post = $manga_post;
				?>
					<div class="popular-item-wrap">

						<?php if ( $style == 'style-1' ) {
							$wp_manga_template->load_template( 'widgets/recent-manga/content-1', false );
						} else {
							$wp_manga_template->load_template( 'widgets/recent-manga/content-2', false );
						} ?>

					</div>
				<?php
				//reset for the next loop
				$chapter_slug = '';
			}
		}
					
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
		$widget_name = $this->get_name();
        ?>
        <#
        widget_name = '<?php echo $widget_name;?>';
		classes = 'c-popular manga-bookmark-widget widget widget_' + widget_name + ' ' + settings.widget_css_class + ' ' + settings.madara_wg_layout + ' ' + settings.madara_wg_heading_style + ' ' + settings.madara_wg_custom_widget_width;
		style = settings.style;
        before_widget = '<div class="row"><div class="widget-elementor ' + classes + '"><div class="widget__inner ' + widget_name + '__inner"><div class="widget-content ' + style + '">';            
        #>
        {{{ before_widget }}}
		<?php

		$this->title_template();
		
		?>
		<div class="popular-item-wrap">
			<# 
			
			dummy_thumb = '<?php echo get_template_directory_uri();?>/images/elementor-post-thumb.jpg';
		
			arr = Array.from({length: settings.number_of_posts}, (v, i) => i);

			_.each(arr, function(item, index){ #>
				<div class="popular-img widget-thumbnail c-image-hover">
					<a title="Manga Title {{ index }}" href="#">
						<img src="{{ dummy_thumb }}" />
					</a>
				</div>
				<div class="popular-content">
					<h5 class="widget-title">
						<a title="Manga Title {{ index }}" href="#">Manga Title {{ index }}</a>
					</h5>
				<# if(style == 'style-1') { #>
					<div class="list-chapter">
						<div class="posts-date">August 20, 2024</div>
					</div>
				<# } else { #>
					<div class="posts-date">August 20, 2024</div>
				<# } #>
				</div>
			<# }); #>
				</div>
		<?php
		$this->after_widget_template();
	}

}