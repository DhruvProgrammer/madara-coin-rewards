<?php

$manga_id = get_the_ID();

global $wp_manga_functions, $wp_manga_database, $wp_manga_setting;
		
$sort_option = $wp_manga_database->get_sort_setting();
$manga = $wp_manga_functions->get_all_chapters( $manga_id, $sort_option['sort'] );

$current_read_chapter = 0;
if ( is_user_logged_in() ) {
	$user_id = get_current_user_id();
	$history = madara_get_current_reading_chapter($user_id, $manga_id);
	if($history){
		$current_read_chapter = $history['c'];
	}
}

use App\Madara;
$manga_single_chapters_list = Madara::getOption( 'manga_single_chapters_list', 'on' );
$chapters_order             = Madara::getOption( 'manga_chapters_order', '*_desc' );

?>
<h2 class="h4">
	<i class="<?php madara_default_heading_icon(); ?>"></i>
	<?php echo esc_attr__( 'LATEST MANGA RELEASES', 'madara' ); ?>
</h2>
<?php if ( $manga ) : ?>

			<?php do_action( 'madara_before_chapter_listing' ) ?>

			<ul class="main version-chap">
				<?php
					$single = isset( $manga['0']['chapters'] ) ? $manga['0']['chapters'] : null;
					
					// ONE VOLUMN/NO VOLUMN

					if ( $single ) { ?>

						<?php 
						$style     = $wp_manga_functions->get_reading_style();
						foreach ( $single as $chapter ) {
							$link      = $wp_manga_functions->build_chapter_url( $manga_id, $chapter ) . 'amp';
							$time_diff = $wp_manga_functions->get_time_diff( $chapter['date'] );
							$time_diff = apply_filters( 'madara_archive_chapter_date', '<i>' . $time_diff . '</i>', $chapter['chapter_id'], $chapter['date'], $link );

							?>

							<li class="wp-manga-chapter <?php echo esc_attr($current_read_chapter == $chapter['chapter_id'] ? 'reading':'');?> <?php echo apply_filters('wp_manga_chapter_item_class','', $chapter, $manga_id);?>">
								<?php do_action('wp_manga_before_chapter_name',$chapter, $manga_id);?>
								
								<a href="<?php echo esc_url( $link ); ?>">
									<?php echo isset( $chapter['chapter_name'] ) ? wp_kses_post( $chapter['chapter_name'] . $wp_manga_functions->filter_extend_name( $chapter['chapter_name_extend'] ) ) : ''; ?>
								</a>

								<?php if ( $time_diff ) { ?>
									<span class="chapter-release-date">
										<?php echo wp_kses_post( $time_diff ); ?>
									</span>
								<?php } ?>
								
								<?php do_action('wp_manga_after_chapter_name',$chapter, $manga_id);?>

							</li>

						<?php } //endforeach ?>

						<?php unset( $manga['0'] );
					}//endif;
				?>

				<?php
				
					// with VOLUMNS

					if ( ! empty( $manga ) ) {

						if ( strpos($chapters_order, '_desc') !== false ) {
							$manga = array_reverse( $manga );
						}
						
						$style = $wp_manga_functions->get_reading_style();

						foreach ( $manga as $vol_id => $vol ) {

							$chapters = isset( $vol['chapters'] ) ? $vol['chapters'] : null;

							$chapters_parent_class = $chapters ? 'parent has-child' : 'parent no-child';
							$chapters_child_class  = $chapters ? 'has-child' : 'no-child';
							$first_volume_class    = isset( $first_volume ) ? '' : ' active';
							
							if ( $chapters ) {
									
								$manga_id = get_the_ID();
								
								foreach ( $chapters as $chapter ) {
									$link          = $wp_manga_functions->build_chapter_url( $manga_id, $chapter, $style );
									
									$manga_paged_var = $wp_manga_setting->get_manga_option('manga_paged_var', 'manga-paged');
									$page = get_query_var($manga_paged_var);
									
									$amp_url = $link . 'amp';
									if($page && $page != 1){
										$amp_url .= '/p/' . $page;
									}
									
									$c_extend_name = madara_get_global_wp_manga_functions()->filter_extend_name( $chapter['chapter_name_extend'] );
									$time_diff     = $wp_manga_functions->get_time_diff( $chapter['date'] );
									$time_diff     = apply_filters( 'madara_archive_chapter_date', '<i>' . $time_diff . '</i>', $chapter['chapter_id'], $chapter['date'], $amp_url );

									?>

									<li class="wp-manga-chapter <?php echo apply_filters('wp_manga_chapter_item_class','', $chapter, $manga_id);?>">
										
										<?php do_action('wp_manga_before_chapter_name',$chapter, $manga_id);?>
										<a href="<?php echo esc_url( $amp_url ); ?>">
											<?php echo isset( $vol['volume_name'] ) ? $vol['volume_name'] . ' - ' : ''; ?> <?php echo wp_kses_post( $chapter['chapter_name'] . $c_extend_name ) ?>
										</a>

										<?php if ( $time_diff ) { ?>
											<span class="chapter-release-date">
												<?php echo wp_kses_post( $time_diff ); ?>
											</span>
										<?php } ?>
										
										<?php do_action('wp_manga_after_chapter_name',$chapter, $manga_id);?>

									</li>

								<?php }
							}
							
							$first_volume = false; ?>

						<?php } //endforeach; ?>

					<?php } //endif-empty( $volume);
				?>
			</ul>

			<?php do_action( 'madara_after_chapter_listing' ) ?>

		<?php else : ?>

			<?php echo esc_html__( 'Manga has no chapter yet.', 'madara' ); ?>

		<?php endif; ?>