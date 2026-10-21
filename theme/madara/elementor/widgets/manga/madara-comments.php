<?php
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/../base-widget.php';

/**
 * Elementor - Manga Comments section, to be used in Manga Detail page.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Madara_Comments extends Elementor_Madara_Widget
{

	public function __construct($data = [], $args = null)
	{
		parent::__construct($data, $args);
	}

	/**
	 * Get widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget name.
	 */
	public function get_name()
	{
		return 'madara-comments';
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
	public function get_title()
	{
		return esc_html__('Manga Comments', 'madara');
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
	public function get_icon()
	{
		return 'eicon-menu-toggle';
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
	public function get_categories()
	{
		return ['manga'];
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
	public function get_keywords()
	{
		return ['madara', 'manga'];
	}

	/**
	 * Register controls.
	 *
	 * Add input fields to allow the user to customize the widget settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function register_controls()
	{
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__('Section Settings', 'madara'),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'hide_on_reading',
			[
				'label' => esc_html__('Hide in Chapter Reading page', 'madara'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
				'return_value' => 'yes',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render()
	{
		$settings = $this->get_settings_for_display();
		if (is_singular('wp-manga')) {
			if (isset($settings['hide_on_reading']) && $settings['hide_on_reading'] == 'yes') {
				global $wp_manga_functions;
				if ($wp_manga_functions->is_manga_reading_page()) {
					return;
				}
			}
			do_action('wp_manga_discussion');
		}
	}

	/**
	 * Render widget output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function content_template()
	{
?>
		<div id="madara-comments" class="comments-area">
			<div id="respond" class="comment-respond">
				<h4 id="reply-title" class="comment-reply-title">Leave a Reply <small><a rel="nofollow" id="cancel-comment-reply-link" href="#" style="display:none;">Cancel reply</a></small></h4>
				<form action="#" method="post" id="commentform" class="comment-form" novalidate="">
					<p class="comment-form-comment"><textarea id="comment" name="comment" cols="45" rows="8" aria-required="true" placeholder="Comment" required="required"></textarea></p>
					<p class="form-submit"><input name="submit" type="submit" id="submit" class="submit" value="Post Comment"> <input type="hidden" name="comment_post_ID" value="549" id="comment_post_ID">
						<input type="hidden" name="comment_parent" id="comment_parent" value="0">
					</p><input type="hidden" id="_wp_unfiltered_html_comment_disabled" name="_wp_unfiltered_html_comment" value="c74f18e0cd">
					<script type="text/javascript">
						/* <![CDATA[ */
						(function() {
							if (window === window.parent) {
								document.getElementById('_wp_unfiltered_html_comment_disabled').name = '_wp_unfiltered_html_comment';
							}
						})();
						/* ]]> */
					</script>
					<input type="hidden" name="wp_manga_chapter_id" value="0">
				</form>
			</div><!-- #respond -->




		</div>
<?php
	}
}
