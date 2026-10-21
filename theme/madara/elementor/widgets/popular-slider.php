<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Manga Popular Slider
 *
 * @since 1.0.0
 */
class Elementor_Manga_Popular_Slider extends Elementor_Madara_Widget {

	public function __construct($data = [], $args = null) {
		parent::__construct($data, $args);
  
		wp_register_script( 'madara-elementor', get_template_directory_uri() . '/elementor/js/widgets.js', [ 'elementor-frontend' ], '1.0.0', true );
	 }

	 public function get_script_depends() {
		return [ 'madara-elementor' ];
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
		return 'popular-slider';
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
		return esc_html__( 'Manga Popular Slider', 'madara' );
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
		return 'eicon-slides';
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
				'placeholder' => esc_html__( 'Manga Slider', 'madara' ),
			]
		);

		$this->add_control(
			'number_of_post',
			[
				'label' => esc_html__( 'Number of posts to query', 'madara' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'placeholder' => '6',
				'min' => 1,
				'max' => 100,
				'step' => 1,
				'default' => 6
			]
		);

		$this->add_control(
			'number_to_show',
			[
				'label' => esc_html__( 'Number of Posts to show', 'madara' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'placeholder' => '3',
				'min' => 1,
				'max' => 5,
				'step' => 1,
				'default' => 3
			]
		);

		$this->add_control(
			'manga_type',
			[
				'label' => esc_html__( 'Manga Type', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'All', 'madara' ),
					'manga' => esc_html__( 'Manga', 'madara' ),
					'text' => esc_html__( 'Text', 'madara' ),
					'video' => esc_html__( 'Video', 'madara' )
				],
				'default' => '',
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
					'rand' => esc_html__( 'Random', 'madara' )
				],
				'default' => 'latest',
			]
		);

		$this->add_control(
			'timerange',
			[
				'label' => esc_html__( 'Trending By', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'all' => esc_html__( 'All time', 'madara' ),
					'year' => esc_html__( 'Year', 'madara' ),
					'month' => esc_html__( 'Month', 'madara' ),
					'week' => esc_html__( 'Week', 'madara' ),
					'day' => esc_html__( 'Day', 'madara' )
				],
				'default' => 'all',
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
			'autoplay',
			[
				'label' => esc_html__( 'Autoplay', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'1' => esc_html__( 'Yes', 'madara' ),
					'0' => esc_html__( 'No', 'madara' )
				],
				'default' => '0',
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

		global $wp_manga_functions, $wp_manga_template, $wp_manga;

		$classes = [];
		$autoplay = $settings['autoplay'];
		$data_style = $settings['style'];
		$classes[] = $data_style;
		$data_style = $data_style == 'style-3' ? 'style-1' : $data_style;
		$number_to_show = $settings['number_to_show'];
		$number_of_post = $settings['number_of_post'];
		$order = $settings['order'];
		$order_by = $settings['order_by'];
		$timerange = $settings['timerange'];
		$manga_type = $settings['manga_type'];

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
		
		if($manga_type != ''){
			$query_args['meta_query_value'] = $manga_type;
			$query_args['key'] = '_wp_manga_chapter_type';
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
	
		$queried_posts = $wp_manga->mangabooth_manga_query( $query_args );
		
		$this->before_widget();

		?>
		
		<div class="popular-slider <?php echo implode(" ", $classes ); ?>" data-autoplay="<?php echo esc_attr($autoplay);?>" data-style="<?php echo esc_attr( $data_style ); ?>" data-count="<?php echo esc_attr( $number_to_show ); ?>">
			<?php $this->render_title(); ?>
			<div class="slider__container" role="toolbar">
				<?php while ( $queried_posts->have_posts() ) {
					$queried_posts->the_post();
					$wp_manga_template->load_template( 'widgets/popular-slider/slider', false );
				}
					wp_reset_postdata();
				?>
			</div>
		</div>
			<?php
		wp_reset_postdata();
		$this->after_widget();
	}

	/**
	 * Render list widget output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function content_template() {
		?>
		<?php
		$this->before_widget_template();
		?>
		<#
		widget_name = 'popular-slider';
		autoplay = settings.autoplay;
		data_style = settings.style;
		classes = widget_name + ' ' + data_style;
		number_to_show = settings.number_to_show;		
		number_of_post = settings.number_of_post;
		arr = Array.from({length: number_of_post}, (v, i) => i);
		order = settings.order;
		order_by = settings.order_by;
		timerange = settings.timerange;
		manga_type = settings.manga_type;
		if(data_style == 'style-1'){
			// horizontal thumb
			dummy_thumb = '<?php echo get_template_directory_uri();?>/images/elementor-post-thumb.jpg';
		} else {
			// vertical thumb
			dummy_thumb = '<?php echo get_template_directory_uri();?>/images/elementor-post-vthumb.jpg';
			
		}
		
		#>
		<?php
		$this->title_template();
		?>
		
		<div class="{{ classes }}" data-autoplay="{{ autoplay }}" data-style="{{ data_style }}" data-count="{{ number_to_show }}">
			<div class="slider__container rendered" role="toolbar">
				<# _.each(arr, function(item, index){ #>
					<div class="slider__item">
						<div class="slider__thumb">
							<div class="slider__thumb_item c-image-hover">
								<a href="#">
									<img src="{{{ dummy_thumb }}}"/>
									<div class="slider-overlay"></div>
								</a>
							</div>
						</div>
						<div class="slider__content">
							<div class="slider__content_item">
								<div class="post-title font-title">
									<h4>
										<a href="#">Manga Title {{{ (index + 1) }}}</a>
									</h4>
								</div>
								<div class="post-on font-meta">
									<span>
										01/01/2024
									</span>
								</div>
								<div class="chapter-item ">
									<span class="chapter">
										<a href="#">Chapter 1</a>
									</span>
									<span class="chapter">
										<a href="#">Chapter 2</a>
									</span>
								</div>
							</div>
						</div>
					</div>
				<# }); #>
			</div>
        </div>
		<?php
		$this->after_widget_template();
	}

}