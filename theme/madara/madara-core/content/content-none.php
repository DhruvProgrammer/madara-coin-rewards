<?php 

	/**
	 * The Template for content of a No Results search page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/content/content-none.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 
?><div class="no-results not-found">
    <div class="results_content">
        <div class="icon-not-found">
            <i class="icon ion-android-sad"></i>
        </div>
        <div class="not-found-content">
            <p>
				<?php
					if ( is_tax() ) {
						$tax = get_queried_object();
						echo sprintf( esc_html__( 'There is no Manga in this %s - %s', 'madara' ), $tax->name, get_taxonomy( $tax->taxonomy )->label );
					} elseif ( is_manga_posttype_archive() && ! is_search() ) {
						esc_html_e( 'There is no Manga yet', 'madara' );
					} elseif ( is_search() ) {
						esc_html_e( 'No matches found. Try a different search...', 'madara' );
					}
				?>
            </p>
        </div>
    </div>
</div>
