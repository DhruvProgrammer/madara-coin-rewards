<?php
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use App\Madara;

require_once __DIR__ . '/../base-widget.php';

/**
 * Elementor - Manga Properties section, to be used in Manga Detail page.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Elementor_Madara_Properties extends Elementor_Madara_Widget
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
		return 'madara-properties';
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
		return esc_html__('Madara Properties', 'madara');
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
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Section Settings', 'madara' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'hide_on_reading',
			[
				'label' => esc_html__( 'Hide in Chapter Reading page', 'madara' ),
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

		$post_id            = get_the_ID();
		if (is_singular('wp-manga')) {
			if(isset($settings['hide_on_reading']) && $settings['hide_on_reading'] == 'yes'){
				global $wp_manga_functions;
				if ( $wp_manga_functions->is_manga_reading_page() ) {
					return;
				}
			}

			$info_summary_layout = Madara::getOption('manga_profile_summary_layout', 1);

			if ($info_summary_layout == 1) {
?>
				<div class="summary_content">
					<div class="post-content">
						<?php get_template_part('html/ajax-loading/ball-pulse'); ?>

						<?php do_action('wp-manga-manga-properties', $post_id); ?>

						<?php do_action('wp-manga-after-manga-properties', $post_id); ?>

					</div>
					<div class="post-status">

						<?php do_action('wp-manga-manga-status', $post_id); ?>

					</div>

					<?php

					$is_oneshot = is_manga_oneshot($post_id);

					if (!$is_oneshot) {
						set_query_var('manga_id', $post_id);
						get_template_part('madara-core/single/quick-buttons');
					}

					?>
				</div>
			<?php
			} else {
			?>
				<div class="summary_content">
					<div class="post-content">
						<?php get_template_part('html/ajax-loading/ball-pulse'); ?>

						<?php do_action('wp-manga-manga-properties', $post_id); ?>

						<?php do_action('wp-manga-after-manga-properties', $post_id); ?>

						<div class="post-status">

							<?php do_action('wp-manga-manga-status', $post_id); ?>

						</div>

						<?php if (get_the_content() != '') {
						?>
							<div class="manga-excerpt">
								<?php
								global $post;
								echo apply_filters('the_content', $post->post_content);
								?>
							</div>
						<?php } ?>

						<?php
						$is_oneshot = is_manga_oneshot($post_id);

						if (!$is_oneshot) {
							set_query_var('manga_id', $post_id);
							get_template_part('madara-core/single/quick-buttons');
						}
						?>
					</div>
				</div>
		<?php
			}
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
		<div class="summary_content">
			<div class="post-content">
				<div class="loader-inner ball-pulse">
					<div></div>
					<div></div>
					<div></div>
				</div>
				<div class="post-rating">
					<div class="post-total-rating allow_vote"><i class="ratings_stars rating_current ion-ios-star"></i><i class="ratings_stars rating_current ion-ios-star"></i><i class="ratings_stars rating_current ion-ios-star"></i><i class="ratings_stars rating_current ion-ios-star"></i><i class="ratings_stars rating_current ion-ios-star"></i><span class="score font-meta total_votes">5</span></div>
					<div class="user-rating allow_vote"><i class="ratings_stars ion-ios-star-outline"></i><i class="ratings_stars ion-ios-star-outline"></i><i class="ratings_stars ion-ios-star-outline"></i><i class="ratings_stars ion-ios-star-outline"></i><i class="ion-ios-star-outline ratings_stars"></i><span class="score font-meta total_votes">Your Rating</span></div><input type="hidden" class="rating-post-id" value="549">
				</div>

				<div class="post-content_item">
					<div class="summary-heading">
						<h5>Rating</h5>
					</div>
					<div class="summary-content vote-details" vocab="https://schema.org/" typeof="AggregateRating">
						<span property="itemReviewed" typeof="Book"><span class="rate-title" property="name" title="Manga 1000">Manga 1000</span></span><span> <span> Average <span property="ratingValue" id="averagerate"> 5</span> / <span property="bestRating">5</span> </span> </span> out of <span property="ratingCount" id="countrate">1</span>
					</div>
				</div>
				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Rank </h5>
					</div>
					<div class="summary-content"> 1st, it has 1M monthly views </div>
				</div>
				<div class="post-content_item">
					<div class="summary-heading">
						<h5>Alternative</h5>
					</div>
					<div class="summary-content">
						An alternative name for manga
					</div>
				</div>

				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Author(s) </h5>
					</div>
					<div class="summary-content">
						<div class="author-content">
							<a href="#" rel="tag">MangaBooth</a>
						</div>
					</div>
				</div>
				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Artist(s) </h5>
					</div>
					<div class="summary-content">
						<div class="artist-content">
							<a href="#" rel="tag">MangaBooth</a>
						</div>
					</div>
				</div>

				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Genre(s) </h5>
					</div>
					<div class="summary-content">
						<div class="genres-content">
							<a href="#" rel="tag">Genre 1</a>, <a href="#" rel="tag">Genre 2</a>, <a href="#" rel="tag">Genre 3</a>
						</div>
					</div>
				</div>

				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Type </h5>
					</div>
					<div class="summary-content"> Manga </div>
				</div>
				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Tag(s) </h5>
					</div>
					<div class="summary-content">
						<div class="tags-content">
							<a href="#" rel="tag">Tag 1</a>, <a href="#/" rel="tag">Tag 2</a>
						</div>
					</div>
				</div>
			</div>
			<div class="post-status">


				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Release </h5>
					</div>
					<div class="summary-content">
						<a href="#" rel="tag">2024</a>
					</div>
				</div>
				<div class="post-content_item">
					<div class="summary-heading">
						<h5> Status </h5>
					</div>
					<div class="summary-content"> OnGoing </div>
				</div>
				<div class="manga-action">
					<div class="count-comment">
						<div class="action_icon">
							<a href="#manga-discussion"><i class="icon ion-md-chatbubbles"></i></a>
						</div>
						<div class="action_detail">
							<span class="disqus-comment-count" data-disqus-url="#">Comments</span>
						</div>
					</div>
					<div class="add-bookmark">
						<div class="action_icon"><a href="#" class="wp-manga-action-button" data-action="bookmark" title="Bookmark"><i class="icon ion-ios-bookmark"></i></a></div>
						<div class="action_detail"><span>1000 Users bookmarked This</span></div>
					</div>
				</div>
			</div>

			<div id="init-links" class="nav-links">
				<a href="#" id="btn-read-last" class="c-btn c-btn_style-1">Read First</a>
			<a href="#" id="btn-read-first" class="c-btn c-btn_style-1">Read Last</a>
			</div>

		</div>
<?php
	}
}
