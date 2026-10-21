<?php 

/**
	 * The Template for printing out a manga property (Rank) in Manga Detail page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/single/info-rank.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.8
	 */

	$manga_id = get_query_var('post_id');
	 
?>
	 <div class="post-content_item">
	<div class="summary-heading">
		<h5>
			<?php echo esc_html__( 'Rank', 'madara' ); ?>
		</h5>
	</div>
	<div class="summary-content">
		<?php 
		
		if(method_exists($wp_manga_functions, 'print_ranking_views')){
			$wp_manga_functions->print_ranking_views( $manga_id );
		} else {
			?>
			<?php echo sprintf( _n( ' %1s, it has %2s monthly view', ' %1s, it has %2s monthly views', $views, 'madara' ), $rank, $views ); ?>
			<?php
		}
		
		 ?>
	</div>
</div>