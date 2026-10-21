<?php

function madara_customize_register_blog( $wp_customize ) {
    $section = 'archives';

    $wp_customize->add_control(
        new Skyrocket_Image_Radio_Button_Custom_Control(
            $wp_customize,
            'archive_sidebar',
            array(
                'label'          => esc_html__( 'Blog Sidebar', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_sidebar',
                'description' => '',
                'choices' => array(
					'left' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-left.png' ),
						'name' => __( 'Left', 'madara' )
					),
					'right' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-right.png' ),
						'name' => __( 'Right', 'madara' )
                    ),
                    'full' => array(
						'image' => get_parent_theme_file_uri( '/images/options/sidebar/sidebar-hidden.png' ),
						'name' => __( 'Full', 'madara' )
					)
				)
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'archive_heading_text',
            array(
                'label'          => esc_html__( 'Blog Heading Text', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_heading_text',
                'type'           => 'text',
                'description' => esc_html__( 'Appear in Blog Listing', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'archive_heading_icon',
            array(
                'label'          => esc_html__( 'Blog Heading Icon', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_heading_icon',
                'type'           => 'text',
                'description' => esc_html__( "Icon class, for example 'fa fa-home'", "madara" ) . '</br><a href="http://fontawesome.io/icons/" target="_blank">' . esc_html__( "Font Awesome", "madara" ) . '</a>, <a href="http://ionicons.com/" target="_blank">' . esc_html__( "Ionicons", "madara" ) . '</a>',
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'archive_margin_top',
            array(
                'label'          => esc_html__( 'Blog Margin Top', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_margin_top',
                'type'           => 'text',
                'description' => esc_html__( "Margin Top in Blog Listing Content. Default's 50 (in pixel)", "madara" ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'archive_content_columns',
            array(
                'label'          => esc_html__( 'Blog Content Columns', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_content_columns',
                'type'           => 'select',
                'description' => esc_html__( 'Columns number of Blog Post', 'madara' ),
                'choices' => array(
                    '3' => esc_html__( '3 Columns', 'madara' ),
                    '2' => esc_html__( '2 Columns', 'madara' ),
                    '' => ''
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'archive_navigation',
            array(
                'label'          => esc_html__( 'Blog Navigation', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_navigation',
                'type'           => 'select',
                'description' => esc_html__( 'Choose type of navigation for blog and any listing page. For WP PageNavi, you will need to install WP PageNavi plugin', 'madara' ),
                'choices' => array(
                    'default' => esc_html__( 'Default', 'madara' ),
                    'ajax' => esc_html__( 'Ajax', 'madara' ),
                    'wp_pagenavi' => esc_html__( 'WP PageNavi', 'madara' ),
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'archive_breadcrumbs',
            array(
                'label'          => esc_html__( 'Blog BreadCrumbs', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_breadcrumbs',
                'type'           => 'select',
                'description' => esc_html__( 'Enable Breadcrumbs for Blog/Posts', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'archive_navigation_same_term',
            array(
                'label'          => esc_html__( 'Blog Navigation - Same Taxonomy Term', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_navigation_same_term',
                'type'           => 'select',
                'description' => esc_html__( 'Whether next/previous post should be in a same taxonomy term', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'archive_navigation_term_taxonomy',
            array(
                'label'          => esc_html__( 'Blog Navigation - Taxonomy Type', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_navigation_term_taxonomy',
                'type'           => 'select',
                'description' => esc_html__( 'Taxonomy type, if "Blog Navigation - Same Taxonomy Term" is ON', 'madara' ),
                'choices' => array(
                    '' => esc_html__( 'Category', 'madara' ),
                    'tag' => esc_html__( 'Tag', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'archive_post_excerpt',
            array(
                'label'          => esc_html__( 'Posts Excerpt', 'madara' ),
                'section'        => $section,
                'settings'       => 'archive_post_excerpt',
                'type'           => 'select',
                'description' => esc_html__( 'Show Posts Excerpt in Blog Listing', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
}