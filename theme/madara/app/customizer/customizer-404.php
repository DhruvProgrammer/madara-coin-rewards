<?php

function madara_customize_register_404( $wp_customize ) {
    $section = '404';

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'page404_head_tag',
            array(
                'label'          => esc_html__( 'Head Title Tag', 'madara' ),
                'section'        => $section,
                'settings'       => 'page404_head_tag',
                'type'           => 'text',
                'description' => esc_html__( 'Content of Title Tag (to be appeared on browser Tab Name)', 'madara' ),
            )
        )
    );

    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'page404_featured_image', array(
        'label'      => esc_html__( 'Page Featured Image', 'madara' ),
        'section'    => $section,
        'settings'   => 'page404_featured_image',
        'description' => esc_html__( 'Upload your Featured Image into 404 Page', 'madara' ),
    ) ) );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'page404_title',
            array(
                'label'          => esc_html__( 'Page Title', 'madara' ),
                'section'        => $section,
                'settings'       => 'page404_title',
                'type'           => 'text',
                'description' => esc_html__( 'Title of the Page', 'madara' ),
            )
        )
    );

    $wp_customize->add_control( new Skyrocket_TinyMCE_Custom_control( $wp_customize, 'page404_content',
			array(
				'label' => esc_html__( 'Page Content', 'madara' ),
				'description' => esc_html__( 'Content of the 404 Page', 'madara' ),
				'section' => $section,
				'input_attrs' => array(
					'toolbar1' => 'bold italic bullist numlist alignleft aligncenter alignright link',
					'mediaButtons' => true,
				)
			)
		) );
}