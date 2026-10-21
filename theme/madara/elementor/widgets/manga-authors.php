<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor Manga Authors Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_Authors extends Elementor_Madara_Widget {

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
		return 'manga-authors';
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
		return esc_html__( 'Manga Authors', 'madara' );
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
			'exclude_author',
			[
				'label' => esc_html__( 'Exclude Authors/Artists', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'data',
			[
				'label' => esc_html__( 'Data', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'wp-manga-author' => esc_html__( 'Manga Author', 'madara' ),
					'wp-manga-artist' => esc_html__( 'Manga Artist', 'madara' )
				],
				'default' => 'wp-manga-author',
			]
		);

		$this->add_control(
			'layout',
			[
				'label' => esc_html__( 'Layout', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'layout-1' => esc_html__( 'Layout 1 - 2 columns', 'madara' ),
					'layout-2' => esc_html__( 'Layout 2 - 6 columns', 'madara' )
				],
				'default' => 'layout-1',
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

		$title = isset( $settings['title'] ) ? $settings['title'] : '';
        $exclude_author = isset( $settings['exclude_author'] ) ? $settings['exclude_author'] : '';
        $layout = isset( $settings['layout'] ) ? $settings['layout'] : 'layout-1';
		$data = isset( $settings['data'] ) ? $settings['data'] : 'wp-manga-author';
		$show_manga_counts = (isset( $settings['show_count'] ) && $settings['show_count'] = 'yes') ? $settings['show_count'] : 'true';
		
		$this->before_widget();
		?>
		
		<div class="genres_wrap">
            <div class="row">
                <div class="col-md-12">
                    <?php
                    $this->render_title();

                    if( !empty( $exclude_author ) ){

                        $authors = explode( ',', $exclude_author );
                        $exclude_author = array();

                        //get author id from author slug
                        foreach( $authors as $author ){
                            $author = trim( $author );
                            $author_obj = get_term_by( 'slug', $author, $data );

                            if( $author_obj != false ) {
                                $exclude_author[] = $author_obj->term_id;
                            }
                        }

                        $exclude_author = array_merge( $exclude_author, $authors );

                    }

                    //author query
                    $author_args = array(
                        'taxonomy' => $data,
                        'hide_empty' => true,
                        'exclude' => $exclude_author
                    );
                    $authors = get_terms( $author_args );

                    if( !empty( $authors ) && !is_wp_error( $authors ) ) {
                        ?>
                        <div class="genres__collapse" style="display:block;">
                            <div class="row genres">
                                <ul class="list-unstyled">
                                    <?php
                                    foreach( $authors as $author ) {
                                        ?>
                                        <li class="<?php echo $layout == 'layout-2' ? 'col-xs-6 col-sm-4 col-md-2' : 'col-xs-6 col-sm-6'; ?>">
                                            <a href="<?php echo esc_url( get_term_link( $author ) ); ?>">
                                                <?php echo esc_html( $author->name ); ?>
                                                <?php
                                                if( $show_manga_counts == 'true' ) {
                                                    ?>
                                                    <span class="count">
                                                        (<?php echo esc_html( $author->count ); ?>)
                                                    </span>
                                                    <?php
                                                }
                                                ?>
                                            </a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        <?php
                    }
                    ?>

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
		$this->before_widget_template();
	?>
		<div class="genres_wrap">
            <div class="row">
                <div class="col-md-12">
                    <?php
                    $this->title_template();

                    $author_args = array(
                        'taxonomy' => 'wp-manga-author',
                        'hide_empty' => true
                    );
                    $authors = get_terms( $author_args );

                    if( !empty( $authors ) && !is_wp_error( $authors ) ) {
                        ?>
                        <div class="genres__collapse" style="display:block;">
                            <div class="row genres">
                                <ul class="list-unstyled">
                                    <?php
                                    foreach( $authors as $author ) {
                                        ?>
										<# if(settings.layout == 'layout-2') {
											class = 'col-xs-6 col-sm-4 col-md-2';
										} else {
											class = 'col-xs-6 col-sm-6';
										}
										#>
                                        <li class="{{ class }}">
                                            <a href="<?php echo esc_url( get_term_link( $author ) ); ?>">
                                                <?php echo esc_html( $author->name ); ?>
                                                <# if(settings.show_count) { #>
                                                    <span class="count">
                                                        (<?php echo esc_html( $author->count ); ?>)
                                                    </span>
                                                <# } #>
                                            </a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        <?php
                    }
                    ?>

                </div>
            </div>
        </div>
		<?php
		$this->after_widget_template();
	}

}