<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'base-widget.php';

/**
 * Elementor List Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Manga_Search extends Elementor_Madara_Widget {

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
		return 'manga-search';
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
		return esc_html__( 'Manga Search', 'madara' );
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
		return 'eicon-search';
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
		
		$this->before_widget();
		?>
		
		<script>
			jQuery(document).ready(function ($) {
				if ($('.c-header__top .manga-search-form').length !== 0 && $('.c-header__top .manga-search-form.search-form').length !== 0) {

					$('form#blog-post-search').append('<input type="hidden" name="post_type" value="wp-manga">');

					$('form#blog-post-search').addClass("manga-search-form");

					$('form#blog-post-search input[name="s"]').addClass("manga-search-field");

					$('form.manga-search-form.ajax input.manga-search-field').each(function(){

					var searchIcon = $(this).parent().children('.ion-ios-search-strong');

					var append = $(this).parent();

					$(this).autocomplete({
						appendTo: append,
						source: function( request, resp ) {
							$.ajax({
								url: manga.ajax_url,
								type: 'POST',
								dataType: 'json',
								data: {
									action: 'wp-manga-search-manga',
									title: request.term,
								},
								success: function( data ) {
									resp( $.map( data.data, function( item ) {
										if ( true == data.success ) {
											return {
												label: item.title,
												value: item.title,
												url: item.url,
												type: item.type
											}
										} else {
											return {
												label: item.message,
												value: item.message,
												type: item.type,
												click: false
											}
										}
									}))
								}
							});
						},
						select: function( e, ui ) {
							if ( ui.item.url ) {
								window.location.href = ui.item.url;
							} else {
								if ( ui.item.click ) {
									return true;
								} else {
									return false;
								}
							}
						},
						open: function( e, ui ) {
							var acData = $(this).data( 'uiAutocomplete' );
							acData.menu.element.addClass('manga-autocomplete').find('li div').each(function(){
								var $self = $(this),
									keyword = $.trim( acData.term ).split(' ').join('|');
								$self.html( $self.text().replace( new RegExp( "(" + keyword + ")", "gi" ), '<span class="manga-text-highlight">$1</span>' ) );
							});
						}
					}).autocomplete( "instance" )._renderItem = function( ul, item ) {
						return $( "<li class='search-item'>" )
							.append( "<div class='manga-type-" + item.type + "'>" + item.label + "</div>" )
							.appendTo( ul );
					};
				});
				}
			});
		</script>
		<div class="search-navigation__wrap">
			<ul class="main-menu-search nav-menu">
				<li class="menu-search">
					<a href="javascript:;" class="open-search-main-menu"> <i class="icon ion-ios-search"></i>
						<i class="icon ion-android-close"></i> </a>
					<ul class="search-main-menu">
						<li>
							<form class="manga-search-form search-form ajax" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
								<input class="manga-search-field" type="text" placeholder="<?php echo esc_html__( 'Search...', 'madara' ); ?>" name="s" value="">
								<input type="hidden" name="post_type" value="wp-manga"> <i class="icon ion-ios-search"></i>
								<div class="loader-inner ball-clip-rotate-multiple">
									<div></div>
									<div></div>
								</div>
								<input type="submit" value="<?php esc_html_e( 'Search', 'madara' ); ?>">
							</form>
						</li>
					</ul>
				</li>
			</ul>
		</div>

			<?php
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
		<#
		widget_name = 'manga-search';
		
		#>
		<?php
		$this->before_widget_template();
		?>
		<div class="search-navigation__wrap">
			<ul class="main-menu-search nav-menu">
				<li class="menu-search">
					<a href="javascript:;" class="open-search-main-menu"> <i class="icon ion-ios-search"></i>
						<i class="icon ion-android-close"></i> </a>
					<ul class="search-main-menu">
						<li>
							<form class="manga-search-form search-form ajax" action="/" method="get">
								<input class="manga-search-field" type="text" placeholder="<?php echo esc_html__( 'Search...', 'madara' ); ?>" name="s" value="">
								<input type="hidden" name="post_type" value="wp-manga"> <i class="icon ion-ios-search"></i>
								<div class="loader-inner ball-clip-rotate-multiple">
									<div></div>
									<div></div>
								</div>
								<input type="submit" value="<?php esc_html_e( 'Search', 'madara' ); ?>">
							</form>
						</li>
					</ul>
				</li>
			</ul>
		</div>
		<?php
		$this->after_widget_template();
	}

}