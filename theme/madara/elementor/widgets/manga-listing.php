<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor Manga Listing Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_Listing extends Elementor_Madara_Widget {

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
		return 'manga-listing';
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
		return esc_html__( 'Manga Listing', 'madara' );
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
		return 'eicon-post-list';
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
				'placeholder' => esc_html__( 'Title', 'madara' ),
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
			'chapter_type',
			[
				'label' => esc_html__( "Manga Chapter Type", "madara" ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'All', 'madara' ),
					'manga' => esc_html__( 'Image (Manga)', 'madara' ),
					'text' => esc_html__( 'Text (Novel)', 'madara' ),
					'video' => esc_html__( 'Video', 'madara' )
				],
				'default' => '',
				'description' => esc_html__( "Type of Manga Chapter to query", 'madara' )
			]
		);

		global $wp_manga_post_type;
		$manga_status = $wp_manga_post_type->get_manga_status();
		$manga_status_choices = array('all' => esc_html__( 'All', 'madara' ));
		foreach($manga_status as $key => $value){
			$manga_status_choices = array_merge($manga_status_choices, array($key => $value));
		}

		$this->add_control(
			'status',
			[
				'label' => esc_html__( 'Status', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $manga_status_choices,
				'default' => 'all',
				'description' => esc_html__( 'Manga Status to query', 'madara' )
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
			'manga_tags',
			[
				'label' => esc_html__( 'Manga Tags', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Slugs of manga tags, separated by a comma', 'madara' ),
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
			'items_per_row',
			[
				'label' => esc_html__( 'Items Per Row', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					0 => "Auto",
					2 => 2,
					3 => 3,
					4 => 4,
					6 => 6,
				],
				'default' => 0,
				'descript' => esc_html__( 'Choose number of items per row for this layout', 'madara' ),
			]
		);

		$this->add_control(
			'item_column_gap',
			[
				'label' => esc_html__( 'Item Column Gap (For "Auto" Items Per Row)', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__('Gap between columns', 'madara'),
				'default' => '0.5rem',
				'selectors' => [
					'{{WRAPPER}} .page-content-listing.auto-cols' => 'grid-column-gap: {{VALUE}} !important'
				]
			]
		);

		$this->add_control(
			'item_row_gap',
			[
				'label' => esc_html__( 'Item Row Gap (For "Auto" Items Per Row)', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__('Gap between rows', 'madara'),
				'default' => '0.5rem',
				'selectors' => [
					'{{WRAPPER}} .page-content-listing.auto-cols' => 'grid-row-gap: {{VALUE}} !important'
				]
			]
		);

		$this->add_control(
			'order_by',
			[
				'label' => esc_html__( 'Order By', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'latest' => esc_html__( 'Latest', 'madara' ),
					'alphabet' => esc_html__( 'Alphabet', 'madara' ),
					'rating' => esc_html__( 'Rating', 'madara' ),
					'trending' => esc_html__( 'Trending', 'madara' ),
					'views' => esc_html__( 'Views', 'madara' ),
					'new-manga' => esc_html__( 'New Manga', 'madara' ),
					"input" => esc_html__( "Input (only available when using ids parameter)", "madara" ),
					"random" => esc_html__( 'Random', 'madara' )
				],
				'default' => 'latest',
			]
		);

		$this->add_control(
			'ids',
			[
				'label' => esc_html__( 'IDs', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'List of post IDs to query, separated by a comma. If this value is not empty, cats, tags and featured are omitted', "madara" )
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

		$this->end_controls_section();

		$this->start_controls_section(
			'item_section',
			[
				'label' => esc_html__( 'Loop Item Settings', 'madara' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'item_layout',
			[
				'label' => esc_html__( 'Item Layout', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'default' => esc_html__( 'Default (Use Site Setting)', 'madara' ),
					'small' => esc_html__( 'Left Thumbnail', 'madara' ),
					'big_thumbnail' => esc_html__( 'Big Thumbnail', 'madara' ),
					'big_thumbnail_2' => esc_html__( 'Big Thumbnail 2 (Name On Top)', 'madara' ),
					'chapters' => esc_html__( 'Simple List', 'madara' ),
					'minimal' => esc_html__( 'Minimal', 'madara' )
				],
				'default' => 'default',
			]
		);

		$this->add_control(
			'show_rating',
			[
				'label' => esc_html__( 'Manga Rating', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'on' => esc_html__( 'Visible', 'madara' ),
					'off' => esc_html__( 'Hidden', 'madara' )
				],
				'default' => 'on',
			]
		);

		$this->add_control(
			'show_volume',
			[
				'label' => esc_html__( 'Chapter Volume', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'on' => esc_html__( 'Visible', 'madara' ),
					'off' => esc_html__( 'Hidden', 'madara' )
				],
				'default' => 'on',
			]
		);

		$this->add_control(
			'latest_chapters_count',
			[
				'label' => esc_html__( 'Number of Latest Chapters', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					0 => 0,
					1 => 1,
					2 => 2,
					3 => 3,
					4 => 4,
				],
				'default' => 0,
				'descript' => esc_html__( 'Number of latest chapters to show', 'madara' ),
			]
		);

		$this->add_control(
			'text_color',
			[
				'label' => esc_html__( 'Text Color', 'madara' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .views, {{WRAPPER}} .font-meta,{{WRAPPER}} .score,.text-ui-light {{WRAPPER}} .chapter a,{{WRAPPER}} .font-title a,{{WRAPPER}} .widget.heading-style-2 .widget-heading .heading, {{WRAPPER}} .item-minimal .item a, body.page {{WRAPPER}} .page-content-listing .page-listing-item .page-item-detail .item-summary .list-chapter .chapter-item a' => 'color: {{VALUE}} !important',
					'.text-ui-light {{WRAPPER}} .chapter' => 'border-color: {{VALUE}} !important'
				],
			]
		);

		$this->add_control(
			'chapter_bgcolor',
			[
				'label' => esc_html__( 'Chapter Background Color', 'madara' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'body.page {{WRAPPER}} .page-content-listing .page-listing-item .page-item-detail .item-summary .list-chapter .chapter-item .chapter' => 'background-color: {{VALUE}} !important'
				],
			]
		);

		$this->add_control(
			'separator_color',
			[
				'label' => esc_html__( 'Separator Color', 'madara' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .page-content-listing.item-big_thumbnail .bigthumbnail2 .item-summary' => 'border-top-color: {{VALUE}} !important',
					'{{WRAPPER}} .widget.heading-style-2 .widget-heading{border-bottom-color: {{VALUE}} !important}'
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

		$item_layout = $settings['item_layout'];

		if($item_layout == 'default'){
			// get global setting
			$item_layout = App\Madara::getOption('manga_archives_item_layout', 'default');
		} else {
			if($item_layout == 'small'){
				// convert to the correct name
				$item_layout = 'default';
			}			
		}

		$bigthumbnail_layout2 = '';
		if($item_layout == 'big_thumbnail_2'){
			$item_layout = 'big_thumbnail';
			$bigthumbnail_layout2 = 'overlay';
		}

		$number_of_post = $settings['number_of_post'];
		$order = $settings['order'];
		$order_by = $settings['order_by'];
		$timerange = $settings['timerange'];
		$ids = $settings['ids'];

		$status = $settings['status'];

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

		$genre = $settings['genre'];

		$meta_query = array();

		if ( ! empty( $status ) && $status != 'all' ) {
			array_push( $meta_query, array(
				'key'     => '_wp_manga_status',
				'value'   => $status,
				));
		}

		$manga_type = $settings['chapter_type'];
		if ( ! empty( $manga_type ) ) {
			array_push( $meta_query, array(
					'key'     => '_wp_manga_chapter_type',
					'value'   => $manga_type,
					));
		} 

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

		$query_args['meta_query'] = $meta_query;
	
		$queried_posts = $wp_manga->mangabooth_manga_query( $query_args );
		
		$items_per_row = intval($settings['items_per_row']);

		$custom_css = '';
		$text_color = isset($settings['text_color']) ? $settings['text_color'] : '';
		
		if($text_color){
			$custom_css .= '
			#madara-elementor-' . $this->get_id() . ', #madara-elementor-' . $this->get_id() . ' .item-minimal .item a, #madara-elementor-' . $this->get_id() . ' .score, .text-ui-light #madara-elementor-' . $this->get_id() . ' .chapter a,#madara-elementor-' . $this->get_id() . ' .font-title a, #madara-elementor-' . $this->get_id() . '.widget.heading-style-2 .widget-heading .heading, body.page #madara-elementor-' . $this->get_id() . ' .page-content-listing .page-listing-item .page-item-detail .item-summary .list-chapter .chapter-item a{color:' . $text_color . '}
			.text-ui-light #madara-elementor-' . $this->get_id() . ' .chapter{border-color:' . $text_color . '}';
		}

		$chapter_bgcolor = isset($settings['chapter_bgcolor']) ? $settings['chapter_bgcolor'] : '';
		
		if($chapter_bgcolor){
			$custom_css .= 'body.page #madara-elementor-' . $this->get_id() . ' .page-content-listing .page-listing-item .page-item-detail .item-summary .list-chapter .chapter-item .chapter{background-color:' . $chapter_bgcolor . '}';
		}

		$separator_color = isset($settings['separator_color']) ? $settings['separator_color'] : '';
		if($separator_color){
			$custom_css .= '#madara-elementor-' . $this->get_id() . ' .page-content-listing.item-big_thumbnail .bigthumbnail2 .item-summary{border-top-color:' . $separator_color . '}';
			$custom_css .= '#madara-elementor-' . $this->get_id() . '.widget.heading-style-2 .widget-heading{border-bottom-color:' . $separator_color . '}';
		}

		$show_volume = isset($settings['show_volume']) ? $settings['show_volume'] : 'on';
		if($show_volume == 'off'){
			$custom_css .= '#madara-elementor-' . $this->get_id() . ' .page-content-listing .vol.font-meta{display:none}';
		}

		$item_column_gap = isset($settings['item_column_gap']) ? $settings['item_column_gap'] : 'item_column_gap';
		if($item_column_gap){
			$custom_css .= '#madara-elementor-' . $this->get_id() . '.widget-elementor.widget_manga-listing .page-content-listing.auto-cols{grid-column-gap: ' . $item_column_gap . '}';
		}

		$item_row_gap = isset($settings['item_row_gap']) ? $settings['item_row_gap'] : 'item_row_gap';
		if($item_row_gap){
			$custom_css .= '#madara-elementor-' . $this->get_id() . '.widget-elementor.widget_manga-listing .page-content-listing.auto-cols{grid-row-gap: ' . $item_row_gap . '}';
		}
		
		if($custom_css){
		?>
		<style type="text/css">
			<?php echo $custom_css;?>
		</style>
		<?php
		}
		
		$this->before_widget('c-popular manga-widget widget-manga-recent', $item_layout);

		$this->render_title();
		?>
		<div class="c-page">
			<div class="c-page__content">
				<!-- Tab panes -->
				<div class="tab-content-wrap">
					<div role="tabpanel" class="c-tabs-item">
						<div class="page-content-listing item-<?php echo esc_attr($item_layout);?> <?php echo $items_per_row === 0 ? 'auto-cols': '';?>">
							<?php
								if ( $queried_posts->have_posts() ) {

									global $wp_query;
									$index = 1;
									$wp_query->set( 'madara_post_count', madara_get_post_count( $queried_posts ) );

									$wp_query->set( 'sidebar', 'full' );
									
									$wp_query->set('manga_archives_item_layout', $item_layout);
									$wp_query->set('manga_archives_item_bigthumbnail', $bigthumbnail_layout2);
									
									$wp_query->set('manga_archives_item_columns', $items_per_row);

									$wp_query->set('manga_archive_latest_chapters_count', $settings['latest_chapters_count']);
									
									$wp_query->set('manga_archive_show_rating', $settings['show_rating']);
									
									if($item_layout == 'chapters'){
										$html = '<table class="manga-shortcodes manga-chapters-listing">
										<thead>
										<th class="genre">' . esc_html__('Genre','madara') . '</th>
										<th class="title">' . esc_html__('Title','madara') . '</th>
										<th class="release">' . esc_html__('Release','madara') . '</th>
										<th class="author">' . esc_html__('Author','madara') . '</th>
										<th class="time">' . esc_html__('Time','madara') . '</th>
										</thead><tbody>';
										echo apply_filters('wp-manga-shortcode-manga-listing-layout-chapters-header', $html);
									}

									while ( $queried_posts->have_posts() ) {

										$wp_query->set( 'madara_loop_index', $index );
										$index ++;

										$queried_posts->the_post();
										
										if($item_layout == 'chapters'){
											get_template_part( 'madara-core/shortcodes/manga-listing/chapter-item');
										} else {
											if($item_layout == 'minimal'){
												$views = get_post_meta( get_the_ID(), '_wp_manga_views', true );

												if($settings['order_by'] == 'trending'){
													switch($settings['timerange']){
														case 'year': 
															$views = get_post_meta( get_the_ID(), '_wp_manga_year_views_value', true );
															break;
														case 'month':
															$views = get_post_meta( get_the_ID(), '_wp_manga_month_views_value', true );
															break;
														case 'week':
															$views = get_post_meta( get_the_ID(), '_wp_manga_week_views_value', true );
															break;
														case 'day':
															$views = get_post_meta( get_the_ID(), '_wp_manga_day_views_value', true );
															break;
													}
												}

												echo '<div class="item">';
												echo '<h3 class="h5"><a href="' . get_the_permalink() . '">' . get_the_title() . '</a></h3>';
												echo '<div class="views"><i class="fa fa-eye"></i> ' . wp_manga_number_format_short(intval($views)) . '</div>';
												echo '</div>';
											} else {
												get_template_part( 'madara-core/content/content', 'archive' );
											}											
										}
									}
									
									if($item_layout == 'chapters'){
										$html = '</tbody></table>';
										echo apply_filters('wp-manga-shortcode-manga-listing-layout-chapters-footer', $html);
									}

								} else {
									get_template_part( 'madara-core/content/content-none' );
								}

								wp_reset_postdata();

							?>
						</div>
					</div>
				</div>
			</div>
		</div>
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
		?>
		<?php
		$widget_name = $this->get_name();
        ?>
        <#
        widget_name = '<?php echo $widget_name;?>';
		classes = 'widget widget_' + widget_name + ' ' + settings.widget_css_class + ' ' + settings.madara_wg_layout + ' ' + settings.madara_wg_heading_style + ' ' + settings.madara_wg_custom_widget_width;
		

        before_widget = '<div class="row"><div class="widget-elementor ' + classes + '  c-popular manga-widget widget-manga-recent"><div class="widget__inner ' + widget_name + '__inner"><div class="widget-content layout-' + settings.item_layout + '">';            
        #>
        {{{ before_widget }}}
        <?php

		?>
		<#
		
		number_of_post = settings.number_of_post;
		items_per_row = parseInt(settings.items_per_row ? settings.items_per_row : 0);
		arr = Array.from({length: number_of_post}, (v, i) => i);
		dummy_thumb = '<?php echo get_template_directory_uri();?>/images/elementor-post-vthumb.jpg';
		main_col_class = '';
		
		switch(items_per_row){
			case 2:
				main_col_class = 'col-12 col-md-6';
				break;
			case 3:
				main_col_class = 'col-12 col-md-4';
				break;
			case 4:
				main_col_class = 'col-12 col-md-3';
				break;
			case 6:
				main_col_class = 'col-12 col-md-2';
				break;
		}

		item_layout = settings.item_layout;
		if(item_layout == 'default'){
			item_layout = '<?php echo App\Madara::getOption('manga_archives_item_layout', 'default');?>';
		}
		
		bigthumbnail_layout2 = '';
		if(item_layout == 'big_thumbnail_2'){
			item_layout = 'big_thumbnail';
			bigthumbnail_layout2 = 'overlay';
			
		}
		if(items_per_row == 0){
			main_col_class = 'auto-col';
		}
		
		if(bigthumbnail_layout2 == 'overlay'){
			main_col_class += ' bigthumbnail2';
		}
		
		#>

		<div class="c-page">
			<div class="c-page__content">
				<?php
				$this->title_template();
				?>

				<div class="tab-content-wrap">
					<div role="tabpanel" class="c-tabs-item">
						<div class="page-content-listing item-{{ item_layout }} {{ items_per_row == 0 ? 'auto-cols' : '' }}">
							<# if(settings.item_layout == 'chapters'){ #>
								<table class="manga-shortcodes manga-chapters-listing">
									<thead>
									<th class="genre"><?php echo esc_html__('Genre','madara');?></th>
									<th class="title"><?php echo esc_html__('Title','madara');?></th>
									<th class="release"><?php echo esc_html__('Release','madara');?></th>
									<th class="author"><?php echo esc_html__('Author','madara');?></th>
									<th class="time"><?php echo esc_html__('Time','madara');?></th>
									</thead><tbody>
							<# } #>
								<# _.each(arr, function(item, index){ #>
									<# if(settings.item_layout == 'chapters'){ #>
										<tr>
											<td class="genre">
												<a href="#">Genre 1</a>, <a href="#">Genre 2</a>, <a href="#">Genre 3</a>
											</td>
											<td class="title"><a href="#" title="Manga Title {{ index }}">Manga Title {{ index }}</a></td>
											<td class="release">
												<a href="#" title="Manga Title {{ index }} - Chapter 10">Chapter 10</a>
											</td>
											<td class="author"><a href="#"><?php echo esc_html__('Author Name', 'madara');?></a></td>
											<td class="time"><?php echo date(get_option('date_format')); ?></td>
										</tr>
									<# } else { #>
										<# if(settings.item_layout == 'minimal') { #>
											<div class="item">
												<h3 class="h5"><a href="#">Manga Title {{ index }}</a></h3>
												<div class="views"><i class="fa fa-eye"></i> 1,234</div>
											</div>
										<# }  else { #>
											<# if(items_per_row) { #>
												<# if( (index + 1) % items_per_row == 1 ) { #>
											<div class="page-listing-item">
												<div class="row row-eq-height">
												<# } #>
											<# } else { #>
												<div class="page-listing-item">
											<# } #>

											<div class="{{ main_col_class }}">
												<div class="page-item-detail">												
													<div id="manga-item-{{ index }}" class="item-thumb c-image-hover">
														<a href="#">
															<img src="{{{ dummy_thumb }}}"/>
														</a>
														<# if(bigthumbnail_layout2 == 'overlay'){ #>
															<div class="overlay-content">
																<div class="post-title font-title">
																	<h3 class="h5">
																		<a href="#">Manga Title {{ index + 1 }}</a>
																	</h3>
																</div>
																<# if(true){ #>
																<div class="author meta">
																	<a href="#">MADARA AUTHOR</a>
																</div>
																<# } #>
															</div>
														<# } #>
													</div>
													<# if(bigthumbnail_layout2 == '' || (settings.show_rating == 'on' || settings.latest_chapters_count > 0)){ #>
													<div class="item-summary">
														<# if(bigthumbnail_layout2 == '') { #>
														<div class="post-title font-title">
															<h3 class="h5">
																<a href="#">Manga Title {{ index + 1 }}</a>
															</h3>
														</div>
														<# } #>
														<# if(settings.show_rating == 'on') { #>
														<div class="meta-item rating">
															<div class="post-total-rating allow_vote"><i class="ion-ios-star ratings_stars rating_current"></i><i class="ion-ios-star ratings_stars rating_current"></i><i class="ion-ios-star ratings_stars rating_current"></i><i class="ion-ios-star ratings_stars rating_current"></i><i class="ion-ios-star ratings_stars rating_current"></i><span class="score font-meta total_votes">5</span></div>
														</div>
														<# } #>
														<# if(settings.latest_chapters_count > 0) { #>
														<div class="list-chapter">
															<# for(i = 1; i <= settings.latest_chapters_count; i++){ #>
															<div class="chapter-item">
																<span class="chapter font-meta">
																	<a href="#" class="btn-link">Chapter {{ i }}</a>
																</span>
																<span class="post-on font-meta"><?php echo date(get_option('date_format')); ?></span>
															</div>
															<# } #>
														</div>
														<# } #>
													</div>
													<# } #>
												</div>

											</div>
											<# if(items_per_row) { #>
												<# if ( ((index + 1) % items_per_row == 0 ) || ( (index + 1) == number_of_post ) ) { #>
												</div>
											</div>
												<# } #>
											<# } else { #>
											</div>
											<# } #>
										<# } #>
									<# } #>
								<# }) #>
								
							<# if(settings.item_layout == 'chapters'){ #>
								</tbody></table>
							<# } #>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$this->after_widget_template();
	}

}