<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Madara Base Widget.
 *
 * @since 1.0.0
 */
abstract class Elementor_Madara_Widget extends \Elementor\Widget_Base {
    /**
	 * Get custom help URL.
	 *
	 * Retrieve a URL where the user can get more information about the widget.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget help URL.
	 */
	public function get_custom_help_url() {
		return 'https://mangabooth.ticksy.com';
	}

	/**
	 * Get widget promotion data.
	 *
	 * Retrieve the widget promotion data.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @return array Widget promotion data.
	 */
	protected function get_upsale_data() {
		return [
			'condition' => true,
			'image' => esc_url( ELEMENTOR_ASSETS_URL . 'images/go-pro.svg' ),
			'image_alt' => esc_attr__( 'Upgrade', 'madara' ),
			'title' => esc_html__( 'Enhance your site features', 'madara' ),
			'description' => esc_html__( 'Get more premiums add-ons from our Marketplace', 'madara' ),
			'upgrade_url' => esc_url( 'https://mangabooth.com/' ),
			'upgrade_text' => esc_html__( 'Check it', 'madara' ),
		];
	}

	protected function register_core_settings(){
        $this->start_controls_section(
			'wrapper_section',
			[
				'label' => esc_html__( 'Wrapper Settings', 'madara' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

        $this->add_control(
			'madara_wg_layout',
			[
				'label' => esc_html__( 'Layout', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'default' => esc_html__( 'Default', 'madara' ),
					'bordered' => esc_html__( 'Bordered', 'madara' ),
					'background' => esc_html__( 'Background', 'madara' )
				],
				'default' => 'default',
			]
		);

		$this->add_control(
			'madara_wg_heading',
			[
				'label' => esc_html__( 'Heading Tag', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'h1' => esc_html__( 'H1', 'madara' ),
					'h2' => esc_html__( 'H2', 'madara' ),
					'h3' => esc_html__( 'H3', 'madara' ),
					'h4' => esc_html__( 'H4', 'madara' )
				],
				'default' => 'h4',
			]
		);

        $this->add_control(
			'widget_css_class',
			[
				'label' => esc_html__( 'Custom Variation - CSS Classes. For example, use "bottom0" to disable margin bottom', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

        $this->add_control(
			'madara_wg_custom_widget_width',
			[
				'label' => esc_html__( 'Layout', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'col-12 col-md-12' => esc_html__( 'col-md-12', 'madara' ),
					'col-12 col-md-11' => esc_html__( 'col-md-11', 'madara' ),
                    'col-12 col-md-10' => esc_html__( 'col-md-10', 'madara' ),
                    'col-12 col-md-9' => esc_html__( 'col-md-9', 'madara' ),
                    'col-12 col-md-8' => esc_html__( 'col-md-8', 'madara' ),
                    'col-12 col-md-7' => esc_html__( 'col-md-7', 'madara' ),
                    'col-12 col-md-6' => esc_html__( 'col-md-6', 'madara' ),
                    'col-12 col-md-5' => esc_html__( 'col-md-5', 'madara' ),
                    'col-12 col-md-4' => esc_html__( 'col-md-4', 'madara' ),
                    'col-12 col-md-3' => esc_html__( 'col-md-3', 'madara' ),
                    'col-12 col-md-2' => esc_html__( 'col-md-2', 'madara' ),
                    'col-12 col-md-1' => esc_html__( 'col-md-1', 'madara' ),
				],
				'default' => 'col-12 col-md-12',
			]
		);

        $this->add_control(
			'madara_wg_heading_style',
			[
				'label' => esc_html__( 'Heading Style', 'madara' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'heading-style-1' => esc_html__( 'Style 1', 'madara' ),
					'heading-style-2' => esc_html__( 'Style 2', 'madara' )
				],
				'default' => 'heading-style-1',
			]
		);

        $this->add_control(
			'madara_wg_custom_icon',
			[
				'label' => esc_html__( 'Font Icon - CSS Classes', 'madara' ),
				'type' => \Elementor\Controls_Manager::TEXT,
			]
		);

        $this->end_controls_section();
    }

    protected function before_widget($custom_widget_class = '', $custom_content_class = '') {
        $params = $this->get_settings_for_display();

        $widget_name = $this->get_name();

        $classes = array();
        $classes[] = $params['madara_wg_custom_widget_width'];
        $classes[] = $params['widget_css_class'];
        $classes[] = $params['madara_wg_layout'];
        $classes[] = $params['madara_wg_heading_style'];
		$classes[] = $custom_widget_class;

        echo '<div class="row"><div id="madara-elementor-' . $this->get_id() . '" class="widget-elementor widget widget_' . $widget_name . ' ' . implode(" ", $classes) . '"><div class="widget__inner ' . $widget_name . '__inner"><div class="c-widget-content widget-content ' . $custom_content_class . '">';
    }

    protected function after_widget(){
        echo '</div></div></div></div>';
    }

    protected function before_title(){
        $params = $this->get_settings_for_display();
        $widget_icon = $params['madara_wg_custom_icon'];
		$wg_heading_tag = $params['madara_wg_heading'];

        if(!empty($widget_icon)){
            echo '<div class="widget-heading"><' . $wg_heading_tag . ' class="heading"><i class="' . $widget_icon . '"></i>';
        } else {
            echo '<div class="widget-heading"><' . $wg_heading_tag . ' class="heading">';
        } 
    }

    protected function after_title(){
        $params = $this->get_settings_for_display();
		$wg_heading_tag = $params['madara_wg_heading'];
		
        echo '</h' . $wg_heading_tag . '></div>';
    }

    protected function render_title(){
        $settings = $this->get_settings_for_display();
        if(!empty($settings['title'])){
			$this->before_title();
			
			echo $settings['title'];
			
			$this->after_title();
		}
    }

    protected function before_widget_template($custom_class = '', $custom_content_class = ''){
        $widget_name = $this->get_name();
        ?>
        <#
        widget_name = '<?php echo $widget_name;?>';
		classes = 'widget widget_' + widget_name + ' ' + settings.widget_css_class + ' ' + settings.madara_wg_layout + ' ' + settings.madara_wg_heading_style + ' ' + settings.madara_wg_custom_widget_width;

        before_widget = '<div class="row"><div class="widget-elementor ' + classes + '  <?php echo $custom_class;?>"><div class="widget__inner ' + widget_name + '__inner"><div class="widget-content <?php echo $custom_content_class;?>">';            
        #>
        {{{ before_widget }}}
        <?php
    }

    protected function after_widget_template(){
        ?>
        <# after_widget = '</div></div></div></div>'; #>
        {{{ after_widget }}}
        <?php        
    }

    protected function title_template(){
        
        ?>
        <#
        widget_icon = settings.madara_wg_custom_icon;
        if(widget_icon){
            before_title = '<div class="widget-heading"><h4 class="heading"><i class="' + widget_icon + '"></i>';
        } else {
            before_title = '<div class="widget-heading"><h4 class="heading">';
        }

		after_title = '</h4></div>';

        if(settings.title){
            #>
			{{{ before_title }}}
			
			{{{ settings.title }}}
			
			{{{ after_title }}}
            <#
		}
        #>
        <?php
    }
}