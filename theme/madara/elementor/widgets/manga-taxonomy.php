<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor Manga Taxonomies Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_Taxonomies extends Elementor_Madara_Widget {

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
		return 'manga-taxonomies';
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
		return esc_html__( 'Manga Taxnomies', 'madara' );
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

		$taxonomies = get_object_taxonomies( 'wp-manga' );
		$choices = [];
		foreach ( $taxonomies as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy ); 
			$choices[$tax->name] = $tax->label;
		}

		$this->add_control(
			'taxonomy',
			[
				'label' => esc_html__( 'Taxonomy', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $choices,
				'default' => $choices[array_keys($choices)[0]],
			]
		);

		$this->add_control(
			'exclude_terms',
			[
				'label' => esc_html__( 'Exclude Terms', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'A list of terms to exclude from the results, separated by a comma', 'madara' ),
			]
		);

		$this->add_control(
			'items_per_row',
			[
				'label' => esc_html__( 'Number of Items Per Row', 'madara' ),
				'default' => 1,
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					1 => 1,
					2 => 2,
					3 => 3,
					4 => 4,
					6 => 6
				],
			]
		);

		$this->add_control(
			'item_border',
			[
				'label' => esc_html__( 'Item Border Bottom', 'madara' ),
				'default' => 1,
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					0 => esc_html__('No', 'madara'),
					1 => esc_html__('Yes', 'madara')
				],
			]
		);

		$this->add_control(
			'show_count',
			[
				'label' => esc_html__( 'Show Manga counts', 'madara' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
				'return_value' => 'yes',
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

		$taxonomy = ( ! empty( $settings['taxonomy'] ) ) ? $settings['taxonomy'] : 'wp-manga-genre';
		$show_count = ( ! empty( $settings['show_count'] ) ) ? $settings['show_count'] : 'yes';
		$exclude_terms = isset( $settings['exclude_terms'] ) ? $settings['exclude_terms'] : '';
		$item_border = isset($settings['item_border']) ? $settings['item_border'] : 0;

        $arr_excluded = array();
		if( !empty( $exclude_terms ) ){

			$arr = explode( ',', $exclude_terms );
			//get genre id from genre slug
			foreach( $arr as $term ){
				$term = trim( $term );
				$term_obj = get_term_by( 'slug', $term, $taxonomy );

				if( $term_obj != false ) {
					$arr_excluded[] = $term_obj->term_id;
				}
			}

			$arr_excluded = array_merge( $arr_excluded, $arr );
		}

		$terms = get_terms( $taxonomy, array( 'hide_empty' => false, 'exclude' => $arr_excluded ) );
		
        $this->before_widget('manga-widget widget-manga-taxonomy');
		?>
		<div class="genres_wrap">
            <div class="row">
                <div class="col-md-12">
					<?php
					$this->render_title();
					$items_per_row = ( ! empty( $settings['items_per_row'] ) ) ? $settings['items_per_row'] : 1;
					$item_class = $item_border ? 'bordered ' : '';
					switch($items_per_row){
						case 2: 
							$item_class .= 'col-xs-6 col-sm-6';
							break;
						case 3: 
							$item_class .= 'col-xs-6 col-md-4 col-6';
							break;
						case 4: 
							$item_class .= 'col-xs-6 col-sm-4 col-md-3 col-6';
							break;
						case 6: 
							$item_class .= 'col-xs-6 col-sm-4 col-md-3 col-lg-2 col-6';
							break;
						default:
							$item_class .= 'col-12';
							break;
					}
					?>
					<div class="genres__collapse" style="display:block;">
						<div class="row genres">
							<ul class="list-unstyled">
								<?php foreach ( $terms as $term ) : 
									$url = get_term_link( $term, $taxonomy );
									if(!is_wp_error($url)){
									?>
									<li class="<?php echo esc_attr($item_class);?>">
										<a href="<?php echo esc_url( $url ) ?>"><?php echo esc_attr( $term->name ); ?> <?php if($show_count == 'yes'){?>(<?php echo $term->count;?>)<?php }?></a>
									</li>
								<?php 
									}
								endforeach; ?>
							</ul>
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
		$this->before_widget_template('manga-widget widget-manga-taxonomy');
		$this->title_template();
		$taxonomy = 'wp-manga-genre';
		$terms = get_terms( $taxonomy, array( 'hide_empty' => false ) );
		?>
		<# 
		var items_per_row = settings.items_per_row ? parseInt(settings.items_per_row) : 1;
		var item_class = '';
		var item_border = settings.item_border ? parseInt(settings.item_border) : 0;
		switch(items_per_row){
			case 2: 
				item_class = 'col-xs-6 col-sm-6';
				break;
			case 3: 
				item_class = 'col-xs-6 col-md-4 col-6';
				break;
			case 4: 
				item_class = 'col-xs-6 col-sm-4 col-md-3 col-6';
				break;
			case 6: 
				item_class = 'col-xs-6 col-sm-4 col-md-3 col-lg-2 col-6';
				break;
			default:
				item_class = 'col-12';
				break;
		}
		item_class += item_border ? ' bordered' : '';
		#>
		<div class="genres_wrap">
            <div class="row">
                <div class="col-md-12">
					<div class="genres__collapse" style="display:block;">
						<div class="row genres">
							<ul class="list-unstyled">
								<?php foreach ( $terms as $term ) : ?>
									<li class="{{ item_class }}">
										<a href="<?php echo esc_url( get_term_link( $term, $taxonomy ) ) ?>"><?php echo esc_attr( $term->name ); ?> <# if(settings.show_count == 'yes'){ #>(<?php echo $term->count;?>)<# } #></a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
            </div>
        </div>
		<?php
		$this->after_widget_template();
	}

}