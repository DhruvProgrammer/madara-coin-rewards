<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

use App\Models\Database;

/**
 * Elementor Posts Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_WP_Posts extends Elementor_Madara_Widget {

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
		return 'wp-posts';
	}

	/**
	 * Get widget title.
	 *
	 * Retrieve widget title.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'WP Posts', 'madara' );
	}

	/**
	 * Get widget icon.
	 *
	 * Retrieve widget icon.
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
				'placeholder' => esc_html__( 'Latest Blog', 'madara' ),
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
			'order_by',
			[
				'label' => esc_html__( 'Choose how to query posts', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Latest Posts', 'madara' ),
					'rand' => esc_html__( 'Random Posts', 'madara' ),
					'comment_count' => esc_html__( 'Most Commented', 'madara' )
				],
				'default' => '',
			]
		);

		$this->add_control(
			'order',
			[
				'label' => esc_html__( 'Choose how to order posts', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'asc' => esc_html__( 'Ascending', 'madara' ),
					'desc' => esc_html__( 'Descending', 'madara' )
				],
				'default' => 'desc',
			]
		);

		$this->add_control(
			'category',
			[
				'label' => esc_html__( 'Category (ID or Slug)', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'tags',
			[
				'label' => esc_html__( 'Tags (separated by a comma)', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

		$this->add_control(
			'post_ids',
			[
				'label' => esc_html__( 'Post IDs - if this param is used, other params are ignored', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT
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
		$instance = $this->get_settings_for_display();

		global $wp_manga_functions, $wp_manga_template, $wp_manga;

		$number_of_posts = isset( $instance['number_of_posts'] ) && $instance['number_of_posts'] != '' ? $instance['number_of_posts'] : '3';
			$order_by        = isset( $instance['order_by'] ) && $instance['order_by'] != '' ? $instance['order_by'] : 'date';
			$order           = isset( $instance['order'] ) && $instance['order'] != '' ? $instance['order'] : 'ASC';
			$cats            = isset( $instance['category'] ) && $instance['category'] != '' ? $instance['category'] : '';
			$tags            = isset( $instance['tags'] ) && $instance['tags'] != '' ? $instance['tags'] : '';
			$post_ids        = isset( $instance['post_ids'] ) && $instance['post_ids'] != '' ? $instance['post_ids'] : '';

		$the_query = Database::getPosts( $number_of_posts, $order, 1, $order_by, array(
			'categories' => $cats,
			'tags'       => $tags,
			'ids'        => $post_ids
		) );
		
		$this->before_widget();
		?>
		<div class="c-widget-content style-2">
			<?php
				$this->render_title();

				while ( $the_query->have_posts() ) {

					$the_query->the_post();
					$post_title = get_the_title();
					$post_url   = get_the_permalink();

					?>
					<div class="popular-item-wrap">

						<?php if ( has_post_thumbnail() ) { ?>
							<div class="popular-img widget-thumbnail c-image-hover">
								<a title="<?php echo esc_attr( $post_title ); ?>" href="<?php echo esc_url( $post_url ); ?>">
									<?php
										echo madara_thumbnail( 'manga_wg_post_2' );
									?>
								</a>
							</div>
						<?php } ?>

						<div class="popular-content">
							<h5 class="widget-title">
								<a title="<?php echo esc_attr( $post_title ); ?>" href="<?php echo esc_url( $post_url ); ?>"><?php echo esc_html( $post_title ); ?></a>
							</h5>

							<div class="posts-date"><?php echo get_the_date(); ?></div>

						</div>

					</div>

					<?php
					wp_reset_postdata();

				}
			?>

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
		$the_query = Database::getPosts( 6, 'desc', 1, 'date');
		
		$this->before_widget_template();
		?>
		
		<div class="c-widget-content style-2">
			<?php
				$this->title_template();

				while ( $the_query->have_posts() ) {

					$the_query->the_post();
					$post_title = get_the_title();
					$post_url   = get_the_permalink();

					?>
					<div class="popular-item-wrap">

						<?php if ( has_post_thumbnail() ) { ?>
							<div class="popular-img widget-thumbnail c-image-hover">
								<a title="<?php echo esc_attr( $post_title ); ?>" href="<?php echo esc_url( $post_url ); ?>">
									<?php
										echo madara_thumbnail( 'manga_wg_post_2' );
									?>
								</a>
							</div>
						<?php } ?>

						<div class="popular-content">
							<h5 class="widget-title">
								<a title="<?php echo esc_attr( $post_title ); ?>" href="<?php echo esc_url( $post_url ); ?>"><?php echo esc_html( $post_title ); ?></a>
							</h5>

							<div class="posts-date"><?php echo get_the_date(); ?></div>

						</div>

					</div>

					<?php
					wp_reset_postdata();

				}
			?>

		</div>
		<?php
		$this->after_widget_template();
	}

}