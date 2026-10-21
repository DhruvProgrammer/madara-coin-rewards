<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor Recent Mangas Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_Recent extends Elementor_Madara_Widget {

	public function __construct($data = [], $args = null) {
		parent::__construct($data, $args);
  
		//wp_register_script( 'madara-elementor', get_template_directory_uri() . '/elementor/js/widgets.js', [ 'elementor-frontend' ], '1.0.0', true );
	 }

	 public function get_script_depends() {
		return [];
		//return [ 'madara-elementor' ];
	}

	/**
	 * Get widget name.
	 *
	 * Retrieve list widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'manga-recent';
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
		return esc_html__( 'Manga Posts', 'madara' );
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
		return 'eicon-bullet-list';
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
				'placeholder' => esc_html__( 'Widget Title', 'madara' ),
			]
		);

		$this->add_control(
			'number_of_post',
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
			'genre',
			[
				'label' => esc_html__( 'Genres', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Slugs of manga genres, separated by a comma', 'madara' ),
			]
		);

		$this->add_control(
			'author',
			[
				'label' => esc_html__( 'Authors', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Slugs of manga authors, separated by a comma', 'madara' ),
			]
		);

		$this->add_control(
			'artist',
			[
				'label' => esc_html__( 'Artist', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Slugs of manga artists, separated by a comma', 'madara' ),
			]
		);

		$this->add_control(
			'release',
			[
				'label' => esc_html__( 'Release Year', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Slugs of manga releases, separated by a comma', 'madara' ),
			]
		);

		$this->add_control(
			'manga_tags',
			[
				'label' => esc_html__( 'Manga Tags', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Slugs of manga tags, separated by a comma', 'madara' ),
			]
		);

		$this->add_control(
			'order_by',
			[
				'label' => esc_html__( 'Order By', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'latest' => esc_html__( 'Latest', 'madara' ),
					'alphabet' => esc_html__( 'alphabet', 'madara' ),
					'rating' => esc_html__( 'Rating', 'madara' ),
					'trending' => esc_html__( 'Trending', 'madara' ),
					'views' => esc_html__( 'Views', 'madara' ),
					'new-manga' => esc_html__( 'New Manga', 'madara' ),
					'random' => esc_html__( 'Random', 'madara' )
				],
				'default' => 'latest',
			]
		);

		$this->add_control(
			'timerange',
			[
				'label' => esc_html__( 'Time Range', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'all' => esc_html__( 'All time', 'madara' ),
					'year' => esc_html__( 'Year', 'madara' ),
					'month' => esc_html__( 'Month', 'madara' ),
					'week' => esc_html__( 'Week', 'madara' ),
					'day' => esc_html__( 'Day', 'madara' )
				],
				'default' => 'all',
				'description' => esc_html__( 'Affected when order by is trending', 'madara' )
			]
		);

		$this->add_control(
			'order',
			[
				'label' => esc_html__( 'Order', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'desc' => esc_html__( 'Descending', 'madara' ),
					'asc' => esc_html__( 'Ascending', 'madara' )
				],
				'default' => 'desc',
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

		$this->add_control(
			'button',
			[
				'label' => esc_html__( 'Readmore Button Text', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Readmore button text. Leave blank to disable this button', 'madara' ),
			]
		);
		
		$this->add_control(
			'url',
			[
				'label' => esc_html__( 'Readmore Button URL', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'https://', 'madara' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'item_section',
			[
				'label' => esc_html__( 'Loop Item Settings', 'madara' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'number_chapters',
			[
				'label' => esc_html__( ' Number of Chapters', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'0' => esc_html__( 'Hidden', 'madara' ),
					'1' => 1,
					'2' => 2,
					'3' => 3,
					'4' => 4
				],
				'default' => '2',
			]
		);

		$this->add_control(
			'show_volume',
			[
				'label' => esc_html__( 'Show Volume', 'madara' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'text_color',
			[
				'label' => esc_html__( 'Text Color', 'madara' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .widget.c-popular .popular-item-wrap .popular-content .chapter-item span.post-on' => 'color: {{VALUE}}'
				],
			]
		);

		$this->add_control(
			'separator_color',
			[
				'label' => esc_html__( 'Separator Color', 'madara' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'body.text-ui-light {{WRAPPER}} .widget.c-popular .popular-item-wrap' => 'border-color: {{VALUE}} !important'
				],
			]
		);

		$this->end_controls_section();

		$this->register_core_settings();
	}

	/**
	 * Render list widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		global $wp_manga_template, $wp_manga;

		$style = $settings['style'];
		$number_of_post = $settings['number_of_post'];
		$order = $settings['order'];
		$order_by = $settings['order_by'];
		$timerange = $settings['timerange'];
		
		global $widget_setting_number_chapters;
		$widget_setting_number_chapters = $settings['number_chapters'];

		$query_args = array(
			'post_type'      => 'wp-manga',
			'post_status'    => 'publish',
			'posts_per_page' => $number_of_post,
			'order'          => $order,
			'orderby'        => $order_by
		);

		if($timerange != 'week'){
			// the "week" time range will be used if "timerange" arg is missing
			$query_args['timerange'] = $timerange;
		}

		$genre = $settings['genre'];;

		if ( $genre && '' != $genre ) {
			$query_args['tax_query']['relation'] = 'OR';
			$genre_array                         = explode( ',', $genre );
			foreach ( $genre_array as $g ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-genre',
					'terms'    => trim($g),
					'field'    => 'slug',
				);
			}
		}
		
		$manga_tags = $settings['manga_tags'];
		if ( $manga_tags && '' != $manga_tags ) {
			$query_args['tax_query']['relation'] = 'OR';
			$manga_tags_arr                         = explode( ',', $manga_tags );
			foreach ( $manga_tags_arr as $g ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-tag',
					'terms'    => trim($g),
					'field'    => 'slug',
				);
			}
		}

		$author = $settings['author'];;
		if ( $author && '' != $author ) {
			$query_args['tax_query']['relation'] = 'OR';
			$author_array                        = explode( ',', $author );
			foreach ( $author_array as $au ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-author',
					'terms'    => trim($au),
					'field'    => 'slug',
				);
			}
		}

		$artist = $settings['artist'];;
		if ( $artist && '' != $artist ) {
			$query_args['tax_query']['relation'] = 'OR';
			$artist_array                        = explode( ',', $artist );
			foreach ( $artist_array as $ar ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-artist',
					'terms'    => trim($ar),
					'field'    => 'slug',
				);
			}
		}

		$release = $settings['release'];;
		if ( $release && '' != $release ) {
			$query_args['tax_query']['relation'] = 'OR';
			$release_array                       = explode( ',', $release );
			foreach ( $release_array as $r ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'wp-manga-release',
					'terms'    => $r,
					'field'    => 'slug',
				);
			}
		}

		$custom_css = '';
		$text_color = isset($settings['text_color']) ? $settings['text_color'] : '';
		
		if($text_color){
			$custom_css .= '
			#madara-elementor-' . $this->get_id() . '.widget.c-popular .popular-item-wrap .popular-content .chapter-item span.post-on{color: ' . $text_color . '}';
		}

		$separator_color = isset($settings['separator_color']) ? $settings['separator_color'] : '';
		if($separator_color){
			$custom_css .= '#madara-elementor-' . $this->get_id() . '.widget.c-popular .popular-item-wrap{border-color: ' . $separator_color . ' !important}';
		}

		$show_volume = isset($settings['show_volume']) ? $settings['show_volume'] : '';
		if($show_volume != 'yes'){
			$custom_css .= '#madara-elementor-' . $this->get_id() . ' .popular-item-wrap .vol.font-meta{display:none}';
		}
		
		if($custom_css){
		?>
		<style type="text/css">
			<?php echo $custom_css;?>
		</style>
		<?php
		}
	
		$queried_posts = $wp_manga->mangabooth_manga_query( $query_args );
		
		$this->before_widget('c-popular manga-widget widget-manga-recent', $style);

		$this->render_title();
		
		while ( $queried_posts->have_posts() ) {

			$queried_posts->the_post();
			?>
			<div class="popular-item-wrap">

				<?php if ( $style == 'style-1' ) {
					$wp_manga_template->load_template( 'widgets/recent-manga/content-1', false );
				} else {
					$wp_manga_template->load_template( 'widgets/recent-manga/content-2', false );
				} 
				?>

			</div>
			<?php
			wp_reset_postdata();
		}

		?>
		<?php 
		
		if($settings['button']) { ?>
		<span class="c-wg-button-wrap">
			<a class="widget-view-more" href="<?php echo esc_url($settings['url']);?>"><?php echo $settings['button'];?></a>
		</span>
		<?php } ?>
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
		
		$widget_name = $this->get_name();
        ?>
        <#
        widget_name = '<?php echo $widget_name;?>';
		classes = 'widget widget_' + widget_name + ' ' + settings.widget_css_class + ' ' + settings.madara_wg_layout + ' ' + settings.madara_wg_heading_style + ' ' + settings.madara_wg_custom_widget_width;

        before_widget = '<div class="row"><div class="widget-elementor ' + classes + '  c-popular manga-widget widget-manga-recent"><div class="widget__inner ' + widget_name + '__inner"><div class="widget-content ' + settings.style + '">';            
        #>
        {{{ before_widget }}}
		<#
		
		number_of_post = settings.number_of_post;
		arr = Array.from({length: number_of_post}, (v, i) => i);

		if(settings.style == 'style-1'){
			dummy_thumb = '<?php echo get_template_directory_uri();?>/images/elementor-post-vthumb.jpg';
		} else {
			dummy_thumb = '<?php echo get_template_directory_uri();?>/images/elementor-post-thumb.jpg';
		}
		
		#>
		<?php
		$this->title_template();
		?>
		<# _.each(arr, function(item, index){ #>
			<div class="popular-item-wrap">
				<# if ( settings.style == 'style-1' ) { #>
					<div class="popular-img widget-thumbnail c-image-hover">
						<a title="Manga Title {{ index }}" href="#">
							<img src="{{ dummy_thumb }}"/>
						</a>
					</div>
					<div class="popular-content">
						<h5 class="widget-title">
							<a title="Manga Title {{ index }}" href="#">Manga Title {{ index }}</a>
						</h5>
						<div class="list-chapter">
		                    <div class="chapter-item">
								<span class="chapter font-meta">
									<a href="#" class="btn-link">Chapter 1</a>
								</span>
								<# if(settings.show_volume == 'yes') { #>
								<span class="vol font-meta">
									<a href="#"> Vol 1 </a>
								</span>
								<# } #>
								<span class="post-on font-meta">August 22, 2024</span>
							</div>
					        <div class="chapter-item">
								<span class="chapter font-meta">
									<a href="#" class="btn-link">Chapter 2</a>
								</span>
								<# if(settings.show_volume == 'yes') { #>
								<span class="vol font-meta">
									<a href="#"> Vol 1 </a>
								</span>
								<# } #>
								<span class="post-on font-meta">August 22, 2024</span>
							</div>
					    </div>
					</div>
				<# } else { #>
					<div class="popular-img widget-thumbnail c-image-hover">
						<a title="Manga Title {{ index }}" href="#">
							<img src="{{ dummy_thumb }}"/>
						</a>
					</div>
					<div class="popular-content">
						<h5 class="widget-title">
							<a title="Manga Title {{ index }}" href="#">Manga Title {{ index }}</a>
						</h5>
						<div class="posts-date">August 22, 2024</div>
					</div>					
				<# } #>

			</div>
		<# }) #>
		<# if(settings.button) { #>
		<span class="c-wg-button-wrap">
			<a class="widget-view-more" href="{{ settings.url }}">{{ settings.button }}</a>
		</span>
		<# } #>
		<?php
		$this->after_widget_template();
	}

}