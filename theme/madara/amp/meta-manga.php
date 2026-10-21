<?php

$manga_id = get_the_ID();

global $wp_manga_functions;
$rank              = $wp_manga_functions->get_manga_rank( $manga_id );
$views             = $wp_manga_functions->get_manga_monthly_views( $manga_id );

$wp_manga_settings = get_option( 'wp_manga_settings' );
$user_rate = isset( $wp_manga_settings['user_rating'] ) ? $wp_manga_settings['user_rating'] : 1;

if($user_rate){
	$rate        = $wp_manga_functions->get_total_review( $manga_id );
	$vote        = $wp_manga_functions->get_total_vote( $manga_id );
}

$alternative = $wp_manga_functions->get_manga_alternative( $manga_id );

$authors     = $wp_manga_functions->get_manga_authors( $manga_id );

$artists     = $wp_manga_functions->get_manga_artists( $manga_id );

$genres     = $wp_manga_functions->get_manga_genres( $manga_id );

$type = $wp_manga_functions->get_manga_type( $manga_id );

$release = $wp_manga_functions->get_manga_release( $manga_id );

$status = $wp_manga_functions->get_manga_status( $manga_id );

?>
<div class="amp-wp-meta amp-wp-manga">
	<h5>
		<?php echo esc_html__( 'Rank', 'madara' ); ?>
	</h5>
	<?php $wp_manga_functions->print_ranking_views( $manga_id ); ?>
</div>

	<?php if($user_rate){ ?>
<div class="amp-wp-meta amp-wp-manga">
	<h5><?php echo esc_attr__( 'Rating', 'madara' ); ?></h5>
	<span property="v:itemreviewed"><span class="rate-title" title="<?php echo esc_attr(get_the_title());?>"><?php echo esc_html(get_the_title());?></span></span><?php echo sprintf(wp_kses_post('<span rel="v:rating"> <span typeof="v:Rating"> Average <span property="v:average" id="averagerate"> %s</span> / <span property="v:best">5</span> </span> out of <span property="v:count" id="countrate">%s</span></span>', array('span' => array('rel'=>1,'typeof'=>1,'property'=>1,'id'=>1))), $rate, $vote ? $vote : 0);?>
</div>	
	<?php } ?>

<div class="amp-wp-meta amp-wp-manga">
	<h5>
		<?php echo esc_html__( 'Alternative', 'madara' ); ?>
	</h5>
	<?php echo wp_kses_post( $alternative ); ?>
</div>
<div class="amp-wp-meta amp-wp-manga">
	<h5>
		<?php echo esc_html__( 'Author(s)', 'madara' ); ?>
	</h5>
	<?php echo wp_kses_post( $authors ); ?>
</div>
<div class="amp-wp-meta amp-wp-manga">	
	<h5>
		<?php echo esc_html__( 'Artist(s)', 'madara' ); ?>
	</h5>
	<?php echo wp_kses_post( $artists ); ?>
</div>
<div class="amp-wp-meta amp-wp-manga">	
	<h5>
		<?php echo esc_html__( 'Genre(s)', 'madara' ); ?>
	</h5>
	<?php echo wp_kses_post( $genres ); ?>
</div>
<div class="amp-wp-meta amp-wp-manga">	
	<h5>
		<?php echo esc_html__( 'Type', 'madara' ); ?>
	</h5>
	<?php echo wp_kses_post( $type ); ?>
</div>
<div class="amp-wp-meta amp-wp-manga">	
	<h5>
		<?php echo esc_html__( 'Release', 'madara' ); ?>
	</h5>
	<?php
		echo wp_kses_post( $release );
	?>
</div>
<div class="amp-wp-meta amp-wp-manga">	
	<h5>
		<?php echo esc_html__( 'Status', 'madara' ); ?>
	</h5>
	<?php
		echo wp_kses_post( $status );
	?>
</div>
</div>