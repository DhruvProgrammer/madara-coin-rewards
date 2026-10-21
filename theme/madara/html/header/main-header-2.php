<?php

	$madara_ParseSocials = new App\Views\ParseSocials();

	/**
	 * Hook to wrap Main Header div
	 */
	do_action( 'madara_main_header_before', 2 );


?>
    <div class="search-navigation">
        <div class="search-navigation__wrap">
            <ul class="main-menu-search nav-menu">
                <li class="menu-search">
                    <a href="javascript:;" class="open-search-main-menu"> <i class="icon ion-ios-search"></i>
                        <i class="ion-android-close"></i> </a>

                </li>
            </ul>
        </div>
    </div>

    <div class="c-togle__menu">
        <button aria-label="open" type="button" class="menu_icon__open">
            <span></span> <span></span> <span></span>
        </button>
    </div>

    <div class="menu-collapse_content">
        <div class="main-menu">
			<?php get_template_part( 'html/header/main-nav' ); ?>
        </div>
    </div>

<?php

	/**
	 * Hook to wrap Main Header div
	 */
	do_action( 'madara_main_header_after', 2 );

?>