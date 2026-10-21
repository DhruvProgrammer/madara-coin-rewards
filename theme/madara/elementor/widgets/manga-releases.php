<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor Manga Releases Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_Releases extends Elementor_Madara_Widget {

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
		return 'manga-releases';
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
		return esc_html__( 'Manga Releases', 'madara' );
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
			'exclude',
			[
				'label' => esc_html__( 'Exclude Years', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '',
				'description' => esc_html__('Use Release Years Term ID or slug, separated by comma', 'madara')
			]
		);

		$this->add_control(
			'number',
			[
				'label' => esc_html__( 'Number of years', 'madara' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'placeholder' => '6',
				'min' => 0,
				'max' => 100,
				'step' => 1,
				'default' => 6,
				'description' => esc_html__('Fill-in 0 to list all Release Years', 'madara')
			]
		);

		$this->add_control(
			'go_release',
			[
				'label' => esc_html__( 'Enable Go Releases', 'madara' ),
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

        $exclude    = isset( $settings['exclude'] ) ? $settings['exclude'] : '';
        $number     = isset( $settings['number'] ) ? $settings['number'] : '20';
        $go_release = isset( $settings['go_release'] ) ? $settings['go_release'] : '';

		$this->before_widget('wp_manga_wg_year_release c-released');
		$this->render_title();

		if( !empty( $exclude ) ) {
            $exclude_years = explode( ',', $exclude );
            $exclude = array();

            foreach( $exclude_years as $year ) {
                $year_obj = get_term_by( 'slug', $year, 'wp-manga-release' );
                if( $year_obj != false ) {
                    $exclude[] = $year_obj->term_id;
                }
            }

            $exclude = array_merge( $exclude, $exclude_years );
        }

        $release_years = get_terms(
            array(
                'taxonomy' => 'wp-manga-release',
                'hide_empty' => true,
                'exclude' => $exclude,
                'number' => $number,
				'orderby' => 'name',
				'order' => 'desc'
            )
        );

        if( is_wp_error( $release_years ) ) {
            return;
        }

		?>
		<div class="c-released_content">
			<div class="released-item-wrap">
				<ul class="list-released">

					<?php
						$flag = 0;
						foreach( $release_years as $year ) {
							$flag ++;
							if( $flag % 4 == 1 ) {
								echo '<li>';
							}
							?>
								<a href="<?php echo esc_url( get_term_link( $year ) ); ?>"><?php echo esc_html( $year->name ); ?></a>
							<?php
							if( $flag % 4 == 0 ) {
								echo '</li>';
							}
						}
					?>

				</ul>
			</div>
			<?php if( $go_release == 'yes' ) { ?>
				<div class="released-search">
					<form action="<?php echo esc_url( home_url() ); ?>" method="get">
						<input type="text" placeholder="<?php esc_html_e( 'Other...', 'madara' ) ?>" name="wp-manga-release" value="">
						<input type="submit" value="<?php esc_html_e( 'Go', 'madara' ); ?>">
					</form>
				</div>
			<?php } ?>
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
		$this->before_widget_template('wp_manga_wg_year_release c-released');
		$this->title_template();

		$release_years = get_terms(
            array(
                'taxonomy' => 'wp-manga-release',
                'hide_empty' => true,
                'exclude' => '',
                'number' => 20,
				'orderby' => 'name',
				'order' => 'desc'
            )
        );
		
		?>
		<div class="c-released_content">
			<div class="released-item-wrap">
				<ul class="list-released">
					<?php
						$flag = 0;
						foreach( $release_years as $year ) {
							$flag ++;
							if( $flag % 4 == 1 ) {
								echo '<li>';
							}
							?>
								<a href="<?php echo esc_url( get_term_link( $year ) ); ?>"><?php echo esc_html( $year->name ); ?></a>
							<?php
							if( $flag % 4 == 0 ) {
								echo '</li>';
							}
						}
					?>

				</ul>
			</div>
			<# if( settings.go_release == 'yes' ) { #>
				<div class="released-search">
					<form action="<?php echo esc_url( home_url('/') ); ?>" method="get">
						<input type="text" placeholder="<?php esc_html_e( 'Other...', 'madara' ) ?>" name="wp-manga-release" value="">
						<input type="submit" value="<?php esc_html_e( 'Go', 'madara' ); ?>">
					</form>
				</div>
			<# } #>
		</div>
		<?php
		$this->after_widget_template();
	}

}