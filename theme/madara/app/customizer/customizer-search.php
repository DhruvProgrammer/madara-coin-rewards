<?php

function madara_customize_register_search( $wp_customize ) {
    $section = 'search';

    madara_customizer_register_background_controls($wp_customize, 'search_header_background', 'Search Header', $section);

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'madara_search_use_archivelayout',
            array(
                'label'          => esc_html__( 'Use The Same Archives layout', 'madara' ),
                'section'        => $section,
                'settings'       => 'madara_search_use_archivelayout',
                'type'           => 'select',
                'description' => esc_html__( 'Use the same layout in Manga Archives or use default Search Result layout', 'madara' ),
                'choices' => array(
                    'no' => esc_html__( 'Use Search Result layout', 'madara' ),
                    'yes' => esc_html__( 'Use Archives Layout', 'madara' )
				),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_search_exclude_tags',
            array(
                'label'          => esc_html__( 'Manga Search - Exclude Tags', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_search_exclude_tags',
                'type'           => 'text',
                'description' => esc_html__( 'Exclude mangas from Search Results if they have these tags. Enter a list of tag slug, separated by comma', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_search_exclude_genres',
            array(
                'label'          => esc_html__( 'Manga Search - Exclude Genres', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_search_exclude_genres',
                'type'           => 'text',
                'description' => esc_html__( 'Exclude mangas from Search Results if they have these genres. Enter a list of genre slug, separated by comma', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Control(
            $wp_customize,
            'manga_search_exclude_authors',
            array(
                'label'          => esc_html__( 'Manga Search - Exclude Authors', 'madara' ),
                'section'        => $section,
                'settings'       => 'manga_search_exclude_authors',
                'type'           => 'text',
                'description' => esc_html__( 'Exclude mangas from Search Results if they belong to these authors. Enter a list of author slug, separated by comma', 'madara' ),
            )
        )
    );

    $wp_customize->add_control(
        new Skyrocket_Toggle_Switch_Custom_control(
            $wp_customize,
            'madara_ajax_search',
            array(
                'label'          => esc_html__( 'Ajax Search', 'madara' ),
                'section'        => $section,
                'settings'       => 'madara_ajax_search',
                'type'           => 'select',
                'description' => esc_html__( 'Enable or Disable Ajax Search for Manga', 'madara' ),
                'choices' => array(
                    'off' => esc_html__( 'Off', 'madara' ),
                    'on' => esc_html__( 'On', 'madara' )
				),
            )
        )
    );
}