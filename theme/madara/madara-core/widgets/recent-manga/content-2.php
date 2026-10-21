<?php
	
	/**
	 * The Template for Manga Item layout 2 in WP Manga Recent Posts widget
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/widgets/recent-manga/content-2.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */

?>

<?php if ( has_post_thumbnail() ) { ?>
    <div class="popular-img widget-thumbnail c-image-hover">
        <a title="<?php echo esc_attr( get_the_title() ); ?>" href="<?php echo esc_url( get_the_permalink() ); ?>">
			<?php
				echo madara_thumbnail( 'manga_wg_post_2' );
			?>
        </a>
    </div>
<?php } ?>

<div class="popular-content">
    <h5 class="widget-title">
        <a title="<?php echo esc_attr( get_the_title() ); ?>" href="<?php echo esc_url( get_the_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
    </h5>
	
    <div class="posts-date"><?php echo get_the_date(); ?></div>

</div>