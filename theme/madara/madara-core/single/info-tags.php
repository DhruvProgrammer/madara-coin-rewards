<?php

/**
	 * The Template for printing out a manga property (Tags) in Manga Detail page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/single/info-tags.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 
	 ?><?php

$madara = \App\Madara::getInstance();
$setting = $madara->getOption('manga_single_tags_post', 'info');
if($tags != '' && ($setting == 'info' || $setting == 'both')) {?>
<div class="post-content_item">
	<div class="summary-heading">
		<h5>
			<?php echo esc_html__( 'Tag(s)', 'madara' ); ?>
		</h5>
	</div>
	<div class="summary-content">
		<div class="tags-content">
			<?php echo wp_kses_post( $tags ); ?>
		</div>
	</div>
</div>

<?php }