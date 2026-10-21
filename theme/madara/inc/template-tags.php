<?php
	/**
	 * Template Tags hold functions to print out HTML
	 *
	 * @package madara
	 */

	use App\Madara;

	/**
	 * get information of current page in Project Listing
	 */
	function madara_pagination_current_page_info( $custom_query = null ) {
		if ( ! $custom_query ) {
			$wp_query = madara_get_global_wp_query();

			$custom_query = $wp_query;
		}

		$vars         = $custom_query->query_vars;
		$current_page = $vars['paged'];
		$current_page = $current_page == 0 ? 1 : $current_page;
		$start_index  = ( $current_page - 1 ) * $vars['posts_per_page'] + 1;
		$end_index    = $start_index + $vars['posts_per_page'] - 1;
		$total        = $custom_query->found_posts;

		if ( $end_index > $total ) {
			$end_index = $total;
		}

		$current_category = esc_html__( 'All', 'madara' );

		if ( is_tax( 'ct_portfolio_cat' ) ) {
			$term = get_queried_object();
			if ( $term ) {
				$current_category = $term->name;
			}
		}

		$filter_text = ct_portfolio_get_filter_condition_in_words();

		if ( $filter_text == '' ) {

			if ( $total > 1 ) {

				$html = sprintf( wp_kses( __( '<div class="c-meta"><div class="item-meta"><ul><li><p>Showing <span>%d-%d</span> of <span>%d</span> projects in <span>%s</span></p></li></ul></div></div>', 'madara' ), array(
					'ul'   => array(),
					'li'   => array(),
					'p'    => array(),
					'span' => array(),
					'div'  => array( 'class' => array() )
				) ), $start_index, $end_index, $total, $current_category );

			} else {

				$html = sprintf( wp_kses( __( '<div class="c-meta"><div class="item-meta"><ul><li><p>Showing <span>%d-%d</span> of <span>%d</span> project in <span>%s</span></p></li></ul></div></div>', 'madara' ), array(
					'ul'   => array(),
					'li'   => array(),
					'p'    => array(),
					'span' => array(),
					'div'  => array( 'class' => array() )
				) ), $start_index, $end_index, $total, $current_category );

			}

		} else {

			if ( $total > 1 ) {
				$html = sprintf( wp_kses( __( '<div class="c-meta"><div class="item-meta"><ul><li><p>Showing <span>%d-%d</span> of <span>%d</span> projects found</li></ul></div></div>', 'madara' ), array(
					'ul'   => array(),
					'li'   => array(),
					'p'    => array(),
					'span' => array(),
					'div'  => array( 'class' => array() )
				) ), $start_index, $end_index, $total );
			} else {
				$html = sprintf( wp_kses( __( '<div class="c-meta"><div class="item-meta"><ul><li><p>Showing <span>%d-%d</span> of <span>%d</span> projects found</li></ul></div></div>', 'madara' ), array(
					'ul'   => array(),
					'li'   => array(),
					'p'    => array(),
					'span' => array(),
					'div'  => array( 'class' => array() )
				) ), $start_index, $end_index, $total );
			}
		}

		$html = apply_filters( 'madara_pagination_current_page_info', $html );

		return $html;
	}

	/**
	 * Get AOS properties string for Header
	 */
	function madara_get_header_aos_properties() {
		$header_aos = Madara::getOption( 'header_aos', '' );
		$properties = '';
		if ( $header_aos != '' ) {
			$properties .= 'data-aos="' . esc_attr( $header_aos ) . '" data-aos-once="true"';

			$header_aos_delay = Madara::getOption( 'header_aos_delay', '500' );
			if ( $header_aos_delay != '' ) {
				$properties .= ' data-aos-delay="' . $header_aos_delay . '"';
			}
		}

		return $properties;
	}

	function cursor_image_url( $params ){

		if( function_exists( 'is_manga_reading_page' ) && is_manga_reading_page() ){

			$prev_path = "images/cursorLeft.png";
			$next_path = "images/cursorRight.png";

			$parent_theme_uri = get_template_directory_uri();
			$child_theme_path = get_stylesheet_directory();
			$child_theme_uri = get_stylesheet_directory_uri();
			
			if( file_exists( $child_theme_path . '/' . $prev_path ) ){
				$prev_url = $child_theme_uri . '/' . $prev_path;
			} else {
				$prev_url = $parent_theme_uri . '/' . $prev_path;
			}

			if( file_exists( $child_theme_path . '/' . $next_path ) ){
				$next_url = $child_theme_uri . '/' . $next_path;
			} else {
				$next_url = $parent_theme_uri . '/' . $next_path;
			}

			$params = array_merge( $params, array(
				'cursorPrev' => $prev_url,
				'cursorNext' => $next_url
			) );
		}

		return $params;

	}
	add_action( 'madara_js_params', 'cursor_image_url' );
	
	function madara_adult_filter_button(){
		if(Madara::getOption('manga_adult_content', 'off') == 'on'){
			$adult_filter = 'off';
			if(isset($_COOKIE['wpmanga-adult']) && $_COOKIE['wpmanga-adult']) {
				$adult_filter = 'on';
			}
			?>
			<div class="section_adult <?php echo esc_attr($adult_filter);?>">
				<a href="<?php echo esc_url(home_url('/'));?>" target="_self" title="<?php esc_attr_e('Family Safe','madara');?>">
					<span class="dot"><!-- --></span><span><?php esc_html_e('Family Safe', 'madara');?></span>
				</a>
			</div>
			<?php
		}
	}

	add_action('wp_manga_chapter_content', 'madara_chapter_content', 10, 2);
	
	function madara_chapter_content($cur_chap, $manga_id){
		global $wp_manga, $wp_manga_functions;
		$style    = isset( $_GET['style'] ) ? $_GET['style'] : $wp_manga_functions->get_reading_style();

		if ( $wp_manga->is_content_manga( $manga_id ) ) {
			$GLOBALS['wp_manga_template']->load_template( 'reading-content/content', 'reading-content', true );
		} else {
			$GLOBALS['wp_manga_template']->load_template( 'reading-content/content', 'reading-' . $style, true );
		}
	}

	function madara_advance_searchform($atts){

	$bg = isset($atts['bg']) ? $atts['bg'] : '';
	if($bg){
		$bg = strpos($bg, "#") !== false ? ('background-color:' . $bg) : 'background-image: url(' . $bg . ')';
	}
	$container = isset($atts['container']) ? $atts['container'] : 0;

		$s         = esc_html(isset( $_GET['s'] ) ? $_GET['s'] : '');
		$s_genre   = isset( $_GET['genre'] ) ? $_GET['genre'] : array();
		$s_author  = isset( $_GET['author'] ) ? $_GET['author'] : '';
		$s_artist  = isset( $_GET['artist'] ) ? $_GET['artist'] : '';
		$s_release = isset( $_GET['release'] ) ? $_GET['release'] : '';
		$s_status  = isset( $_GET['status'] ) ? $_GET['status'] : array();
		$s_adult = isset( $_GET['adult'] ) ? $_GET['adult'] : '';
		$s_genre_condition = isset( $_GET['op'] ) ? $_GET['op'] : '';

		$s_args = madara_get_search_args();
		
		global $s_query;
		$s_query = madara_manga_query( $s_args );
		
		$search_header_background = madara_output_background_options( 'search_header_background' );
		$madara_ajax_search = Madara::getOption('madara_ajax_search', 'on');
?>
    <!--<header class="site-header">-->
    <div class="c-search-header__wrapper" style="<?php echo $bg ? $bg : (esc_attr( $search_header_background != '' ? $search_header_background : 'background-image: url(' . madara_get_default_background() . ');')); ?>">
		<?php if($container){?>
		<div class="container">
		<?php } ?>
        <div class="search-content">
			<form role="search" method="get" class="search-form manga-search-form <?php echo (esc_html($madara_ajax_search) == 'on' ? 'ajax' : '');?>" action="<?php echo home_url('/');?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Search for:', 'madara' ); ?></span>
					<input type="text" class="search-field manga-search-field" placeholder="<?php esc_html_e( 'Search...', 'madara' ); ?>" value="<?php echo esc_attr( stripcslashes( $s )); ?>" name="s">
					<input type="submit" class="search-submit" value="<?php esc_html_e( 'Search', 'madara' ); ?>">
					<div class="loader-inner line-scale">
							<div></div>
							<div></div>
							<div></div>
							<div></div>
							<div></div>
					</div>
					<i class="icon ion-md-search"></i>	
					<input type="hidden" name="post_type" value="wp-manga">
					<script>
						jQuery(document).ready(function ($) {
							$('form.search-form input.search-field[name="s"]').keyup(function () {
								var s = $(this).val();
								$('form.search-advanced-form input[name="s"]').val(s);
							});

							$('.search-form').on('submit', function(e){
								e.preventDefault();
								$('.search-advanced-form').submit();
							});
						});
					</script>
			</form>
			<a class="btn-search-adv collapsed" data-toggle="collapse" data-target="#search-advanced"><span class="label"><?php esc_html_e( 'Advanced', 'madara' ); ?></span>
				<span class="icon-search-adv"></span></a>
		</div>
		<div class="collapse" id="search-advanced">
			<form action="<?php echo home_url('/');?>" method="get" role="form" class="search-advanced-form">
				<input type="hidden" name="s" id="adv-s" value="<?php echo esc_attr($s);?>">
				<input type="hidden" name="post_type" value="wp-manga">
				<!-- Manga Genres -->
				<div class="form-group checkbox-group row">
					<?php
						$genre_args = array(
							'taxonomy'   => 'wp-manga-genre',
							'hide_empty' => false
						);

						$genres = get_terms( $genre_args );

						if ( ! empty( $genres ) ) {
							foreach ( $genres as $genre ) {
								$checked = array_search( $genre->slug, $s_genre ) !== false ? 'checked' : '';
								?>
								<div class="checkbox col-6 col-sm-4 col-md-2 ">
									<input id="<?php echo esc_attr( $genre->slug ); ?>" value="<?php echo esc_attr( $genre->slug ); ?>" name="genre[]" type="checkbox" <?php echo esc_attr( $checked ); ?>/>
									<label for="<?php echo esc_attr( $genre->slug ); ?>"> <?php echo esc_html( $genre->name ); ?> </label>
								</div>
								<?php
							}
						}
					?>

				</div>
				<!-- Genre Condition -->
				<div class="form-group">
					<span class="label"><?php esc_html_e( 'Genres condition', 'madara' ); ?></span>
					<select name="op" class="form-control">
						<option value="" <?php selected($s_genre_condition, '');?>><?php esc_html_e('OR (having one of selected genres)', 'madara');?></option>
						<option value="1" <?php selected($s_genre_condition, 1);?>><?php esc_html_e('AND (having all selected genres)', 'madara');?></option>
					</select>
				</div>
				<!-- Manga Author -->
				<div class="form-group">
					<span class="label"><?php esc_html_e( 'Author', 'madara' ); ?></span>
					<input type="text" class="form-control" name="author" placeholder="<?php esc_attr_e( 'Author', 'madara' ) ?>" value="<?php echo esc_attr( $s_author ); ?>">
				</div>
				<!-- Manga Artist -->
				<div class="form-group">
					<span class="label"><?php esc_html_e( 'Artist', 'madara' ); ?></span>
					<input type="text" class="form-control" name="artist" placeholder="<?php esc_attr_e( 'Artist', 'madara' ); ?>" value="<?php echo esc_attr( $s_artist ); ?>">
				</div>
				<!-- Manga Release -->
				<div class="form-group">
					<span class="label"><?php esc_html_e( 'Year of Released', 'madara' ); ?></span>
					<input type="text" class="form-control" name="release" placeholder="<?php esc_attr_e( 'Year', 'madara' ); ?>" value="<?php echo esc_attr( $s_release ); ?>">
				</div>
				<!-- Manga Adult Content -->
				<div class="form-group">
					<span class="label"><?php esc_html_e( 'Adult content', 'madara' ); ?></span>
					<select name="adult" class="form-control">
						<option value="" <?php selected($s_adult, '');?>><?php esc_html_e('All', 'madara');?></option>
						<option value="0" <?php selected($s_adult, 0);?>><?php esc_html_e('None adult content', 'madara');?></option>
						<option value="1" <?php selected($s_adult, 1);?>><?php esc_html_e('Only adult content', 'madara');?></option>
					</select>
				</div>
				<!-- Manga Status -->
				<div class="form-group">
					<span class="label"><?php esc_html_e( 'Status', 'madara' ); ?></span>
					<?php
					
					global $wp_manga_post_type;
					$default_status = $wp_manga_post_type->get_manga_status();
					foreach($default_status as $key => $value){
						?>
						<div class="checkbox-inline">
							<input id="<?php esc_attr_e($key);?>" type="checkbox" name="status[]" <?php echo in_array( $key, $s_status ) ? 'checked' : '' ; ?> value="<?php esc_attr_e($key);?>" />
							<label for="<?php esc_attr_e($key);?>"><?php esc_html_e( $value ); ?></label>
						</div>
					<?php
						}
					
					?>
				</div>
				<?php do_action('madara_after_advance_search_fields');?>
				<div class="form-group group-btn">
					<button aria-label="search" type="submit" class="c-btn c-btn_style-1 search-adv-submit"><?php esc_html_e( 'Search', 'madara' ); ?></button>
					<button aria-label="reset" type="submit" class="c-btn c-btn_style-2 search-adv-reset"><?php esc_html_e( 'Reset', 'madara' ); ?></button>
				</div>
			</form>
		</div>
		<?php if($container){?>
		</div>
		<?php } ?>
    </div><!--</header>-->
    <script type="text/javascript">
		var manga_args = <?php echo str_replace( '\/', '/', json_encode( $s_args ) ); ?>;
    </script>
		<?php
	}

add_shortcode('madara_advance_searchform', 'shortcode_madara_advance_searchform');
function shortcode_madara_advance_searchform($atts, $content = ''){
	ob_start();
	echo '<div class="shortcode-advancesearchform">';
	madara_advance_searchform($atts);
	echo '</div>';
	$html = ob_get_contents();
	ob_end_clean();
	return $html;
}

add_shortcode('madara_allterms', 'shortcode_madara_allterms');
function shortcode_madara_allterms($atts, $content = ''){
	$taxonomy = isset($atts['taxonomy']) ? $atts['taxonomy'] : 'wp-manga-tag';

	if(!taxonomy_exists($taxonomy)){
		return 'Taxonomy is invalid';
	}

	ob_start();

	$layout = isset($atts['layout']) ? $atts['layout'] : 'autoblock';
	$order = isset($atts['order']) ? $atts['order'] : 'name'; // count or name

	echo '<div class="shortcode-alltags">';
	
	$terms = get_terms(array(
		'taxonomy' => $taxonomy,
		'hide_empty' => true,
		'orderby' => $order,
		'order' => $order == 'count' ? 'desc' : 'asc'
	));

	if(isset($terms->invalid_taxonomy)){
		echo 'Invalid taxonomy';
	} else {
		echo '<div class="layout-' . $layout . '">';
		foreach($terms as $term){
			$id = $term->term_id;
			$name = $term->name;
			$thumb = get_term_meta( $id, 'wp-manga_img', true );
			$image = wp_get_attachment_image_url( $thumb, 'large');

			echo '<div class="item" ' . (($layout == 'autoblock' && $image) ? 'style="background-image:url(' . $image . ')"' : '') . '>';
				echo '<div class="inner">';
				echo '<a class="title" href="' . get_term_link($term) . '" title="' . $name . '"><h3>' . $name . ' ' . ($layout == 'list' ? '(' . $term->count . ')' : '') . '</h3></a>';
				if($layout == 'autoblock'){
					echo '<div class="desc">' . $term->description . '</div>';
					echo '<div class="count">' . $term->count . '</div>';
				}
				
				echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	echo '</div>';
	$html = ob_get_contents();
	ob_end_clean();
	return $html;
}

add_filter('madara_archive_chapter_date', 'madara_custom_archive_chapter_meta', 10, 4); 
function madara_custom_archive_chapter_meta($time_diff, $chapter_id, $chapter_date, $url){
	$manga_archives_item_latestchapter_meta = Madara::getOption('manga_single_chapter_meta', 'date');
	
	if($manga_archives_item_latestchapter_meta != 'date'){
		if($manga_archives_item_latestchapter_meta == 'hide'){
			return '';
		} else {
			
			$wp_manga_settings = get_option( 'wp_manga_settings' );
			global $wp_manga_functions;
			
			if(isset($wp_manga_settings['chapter_view']) && $wp_manga_settings['chapter_view'] && method_exists($wp_manga_functions, 'get_chapter_views')){
				if($manga_archives_item_latestchapter_meta == 'views'){
					
					return '<span class="views"><i class="fa fa-eye"></i> ' . number_format($wp_manga_functions->get_chapter_views($chapter_id)) . '</span>';
				} elseif($manga_archives_item_latestchapter_meta == 'both'){
					global $wp_manga_functions;
					return '<span class="timediff">'. $time_diff . '</span><span class="views"><i class="fa fa-eye"></i> ' . number_format($wp_manga_functions->get_chapter_views($chapter_id)) . '</span>';
				}
			}
		}
	}

	return $time_diff;
}



add_filter('wp_manga_discussion_section_opening', 'madara_discussion_section_opening');
function madara_discussion_section_opening($opening_html){
	$opening_html = '<div id="manga-discussion" class="' . madara_get_default_heading_style() . ' font-heading">
	<h4 class="h4"> <i class="' . madara_default_heading_icon(false) . '"></i> ' . esc_html__( 'MANGA DISCUSSION', WP_MANGA_TEXTDOMAIN ) . '</h4>';

	return $opening_html;
}

function madara_encrypt_chapter_content($plaintext, $secret_key) {
    $iv = random_bytes(12); // recommended IV size for GCM: 96 bits
    $cipher = "aes-256-gcm";

    $tag = "";
    $encrypted = openssl_encrypt(
        $plaintext,
        $cipher,
        $secret_key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return [
        'encrypted' => base64_encode($encrypted),
        'iv'        => base64_encode($iv),
        'tag'       => base64_encode($tag)
    ];
}

function madara_derive_key_from_token($token) {
    return hash_pbkdf2(
        "sha256",
        $token,
        "novel-protect",
        50000,
        32,          // 32 bytes = 256-bit key
        true         // raw binary output
    );
}

add_action('madara_text_chapter_content', 'madara_text_chapter_content', 10, 1);
function madara_text_chapter_content($post){
	$setting = Madara::getOption('madara_enable_chapter_protection', 'off');
	if($setting == 'on'){
	$content = apply_filters('the_content', $post->post_content);

	if (!session_id()) session_start();

	if (!isset($_SESSION['chapter_key'])) {
		$_SESSION['chapter_key'] = bin2hex(random_bytes(32)); // 256-bit key
	}

	$token = hash('sha256', $_SESSION['chapter_key']);
	$aes_key = madara_derive_key_from_token($token);

	// encrypted
	$enc = madara_encrypt_chapter_content($content, $aes_key);
	?>

	<div class="text-left chapter-content-protected" 
	data-enc="<?php echo $enc['encrypted']; ?>" 
	data-iv="<?php echo $enc['iv']; ?>"
	data-tag="<?php echo $enc['tag']; ?>"
	data-token="<?php echo hash('sha256', $_SESSION['chapter_key']); ?>">
	</div>
<?php
	} else {
		?>
		<div class="text-left">
			<?php echo apply_filters('the_content', $post->post_content); ?>
		</div>
	<?php
	}
}
?>