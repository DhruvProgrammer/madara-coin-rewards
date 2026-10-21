<?php

if ( ! function_exists( 'madara_customizer_get_custom_social_item' ) ) {
	function madara_customizer_get_custom_social_item() {
		$defaults = skyrocket_generate_defaults();
		$output = array();
		$social_icons = skyrocket_generate_social_urls();
		$social_urls = explode( ',', get_theme_mod( 'custom_social_account', [] ) );

		foreach( $social_urls as $key => $value ) {
			if ( !empty( $value ) ) {
				$domain = str_ireplace( 'www.', '', parse_url( $value, PHP_URL_HOST ) );
				$index = array_search( strtolower( $domain ), array_column( $social_icons, 'url' ) );
				$output[] = sprintf( '<li class="nosocial"><a href="%2$s"><i class="%3$s"></i></a></li>',
                    $social_icons[$index]['class'],
                    esc_url( $value ),
                    'fas fa-globe'
                );
			}
		}

		if ( !empty( $output ) ) {
			array_unshift( $output, '<ul class="social-icons">' );
			$output[] = '</ul>';
		}

		return implode( '', $output );
	}
}

function madara_customize_register_socials( $wp_customize ) {
    $section = 'socials';


    $socials = array(
        'facebook' => esc_html__( 'Facebook', 'madara' ),
        'twitter'  => esc_html__( 'Twitter', 'madara' ),
        'linkedin'         => esc_html__( 'LinkedIn', 'madara' ),
        'tumblr'      => esc_html__( 'Tumblr', 'madara' ),
        'google-plus'     => esc_html__( 'Google Plus', 'madara' ),
        'pinterest' => esc_html__( 'Pinterest', 'madara' ),
        'youtube' => esc_html__( 'Youtube', 'madara' ),
        'flickr' => esc_html__( 'Flickr', 'madara' ),
        'dribbble' => esc_html__( 'Dribbble', 'madara' ),
        'behance' => esc_html__( 'Behance', 'madara' ),
        'envelop' => esc_html__( 'Email', 'madara' ),
        'rss' => esc_html__( 'RSS', 'madara' ),
    );

    $socials = apply_filters( 'madara_social_accounts', $socials );

    foreach ( $socials as $key => $name ) {
        $wp_customize->add_control(
            new WP_Customize_Control(
                $wp_customize,
                $key,
                array(
                    'label'          => $name,
                    'section'        => $section,
                    'settings'       => $key,
                    'type'           => 'text',
                    'description' => ''
                )
            )
        );
    }

    $wp_customize->add_control( new Skyrocket_Sortable_Repeater_Custom_Control( $wp_customize, 'custom_social_account',
        array(
            'label' => esc_html__( 'Custom Social Accounts', 'madara' ),
            'description' => esc_html__( 'Add new Social Account', 'madara' ),
            'section' => $section,
            'button_labels' => array(
                'add' => __( 'Add New Profile', 'madara' ),
            ),
            'inputs' => array(
                'icon' => ['type' => 'text', 'label' => 'Icon Class', 'placeholder' => 'fab fa-facebook-f'],
                'default' => ['type' => 'url', 'label' => 'URL', 'placeholder' => 'https://']
            )
        )
    ) );

    $wp_customize->selective_refresh->add_partial( 'custom_social_account',
        array(
            'selector' => '.social',
            'container_inclusive' => false,
            'render_callback' => function() {
                echo madara_customizer_get_custom_social_item();
            },
            'fallback_refresh' => true
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'open_social_link_new_tab',
            array(
                'label'          => esc_html__( 'Open Social link in new tab?', 'madara' ),
                'section'        => $section,
                'settings'       => 'open_social_link_new_tab',
                'type'           => 'select',
                'description' => '',
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
}