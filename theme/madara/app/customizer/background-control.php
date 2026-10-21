<?php
/**
 * Since Madara 1.7.5
 */
if ( class_exists( 'WP_Customize_Control' ) ) {
	/**
	 * Custom Control Base Class
	 */ 
	class Madara_Background_CustomizeControl extends Skyrocket_Custom_Control {
        /**
		 * The type of control being rendered
		 */
		public $type = 'background_setting';
        
		/**
		 * Enqueue our scripts and styles
		 */
		public function enqueue() {
			wp_enqueue_style( 'madara-custom-controls-css', $this->get_resource_url() . 'css/customizer.css', array(), $this->customControlsCssVersion, 'all' );
            wp_enqueue_script( 'madara-custom-controls-js', $this->get_resource_url() . 'js/customizer.js', array( 'jquery' ), $this->skyrocketCustomControlsJsVersion, true );
			
			wp_enqueue_media();
            wp_enqueue_script( 'wp-color-picker' );
		    wp_enqueue_style( 'wp-color-picker' );
        }

		/**
	 * Don't render the control content from PHP, as it's rendered via JS on load.
	 *
	 * @since 3.4.0
	 */
		public function render_content() {
            /*
		?>
			<div class="background_setting_control">
				<?php if( !empty( $this->label ) ) { ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php } ?>
				<?php if( !empty( $this->description ) ) { ?>
					<span class="customize-control-description"><?php echo esc_html( $this->description ); ?></span>
				<?php } ?>
				
                <p>
                    <select>
                        <option value="">background-repeat</option>
                        <option value="no-repeat">No Repeat</option>
                        <option value="repeat-all">Repeat All</option>
                        <option value="repeat-x">Repeat Horizontally</option>
                        <option value="repeat-y">Repeat Vertically</option>
                    </select>
                    <select>
                        <option value="">background-attachment</option>
                        <option value="fixed">Fixed</option>
                        <option value="scroll">Scroll</option>
                        <option value="inherit">Inherit</option>
                    </select>
                    <select>
                        <option value="">background-position</option>
                        <option value="left-top">Left Top</option>
                        <option value="left-center">Left Center</option>
                        <option value="left-bottom">Left Bottom</option>
                        <option value="center-top">Center Top</option>
                        <option value="center-center">Center Center</option>
                        <option value="center-bottom">Center Bottom</option>
                        <option value="right-top">Right Top</option>
                        <option value="right-center">Right Center</option>
                        <option value="right-bottom">Right Bottom</option>
                    </select>
                    <input type="text" placeholder="background-size"/>
                </p>
                <p>
                    FILE UPLOAD
                </p>
			</div>
		<?php
            */
		}

	/**
	 * Media control mime type.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	public $mime_type = 'image';

	/**
	 * Button labels.
	 *
	 * @since 4.2.0
	 * @var array
	 */
	public $button_labels = array();

    /**
	 * Statuses.
	 *
	 * @var array
	 */
	public $statuses; // Color Picker statuses

	/**
	 * Mode.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	public $mode = 'full'; // Color Picker mode

	/**
	 * Constructor.
	 *
	 * @since 4.1.0
	 * @since 4.2.0 Moved from WP_Customize_Upload_Control.
	 *
	 * @see WP_Customize_Control::__construct()
	 *
	 * @param WP_Customize_Manager $manager Customizer bootstrap instance.
	 * @param string               $id      Control ID.
	 * @param array                $args    Optional. Arguments to override class property defaults.
	 *                                      See WP_Customize_Control::__construct() for information
	 *                                      on accepted arguments. Default empty array.
	 */
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );
        $this->statuses = array( '' => __( 'Default' ) );
		$this->button_labels = wp_parse_args( $this->button_labels, $this->get_default_button_labels() );
	}

	/**
	 * Refresh the parameters passed to the JavaScript via JSON.
	 *
	 * @since 3.4.0
	 * @since 4.2.0 Moved from WP_Customize_Upload_Control.
	 *
	 * @see WP_Customize_Control::to_json()
	 */
	public function to_json() {
		parent::to_json();

		$this->json['statuses']     = $this->statuses;
		$this->json['defaultValue'] = $this->setting->default;
		$this->json['mode']         = $this->mode;

		$this->json['label']         = html_entity_decode( $this->label, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$this->json['mime_type']     = $this->mime_type;
		$this->json['button_labels'] = $this->button_labels;
		$this->json['canUpload']     = current_user_can( 'upload_files' );

		$value = $this->value();

		if ( is_object( $this->setting ) ) {
			if ( $this->setting->default ) {
				/*
				 * Fake an attachment model - needs all fields used by template.
				 * Note that the default value must be a URL, NOT an attachment ID.
				 */
				$ext  = substr( $this->setting->default, -3 );
				$type = in_array( $ext, array( 'jpg', 'png', 'gif', 'bmp', 'webp', 'avif' ), true ) ? 'image' : 'document';

				$default_attachment = array(
					'id'    => 1,
					'url'   => $this->setting->default,
					'type'  => $type,
					'icon'  => wp_mime_type_icon( $type, '.svg' ),
					'title' => wp_basename( $this->setting->default ),
				);

				if ( 'image' === $type ) {
					$default_attachment['sizes'] = array(
						'full' => array( 'url' => $this->setting->default ),
					);
				}

				$this->json['defaultAttachment'] = $default_attachment;
			}

			if ( $value && $this->setting->default && $value === $this->setting->default ) {
				// Set the default as the attachment.
				$this->json['attachment'] = $this->json['defaultAttachment'];
			} elseif ( $value ) {
				$this->json['attachment'] = wp_prepare_attachment_for_js( $value );
			}
		}
	}

	/**
	 * Render a JS template for the content of the media control.
     * 
	 */
	public function content_template() { 
		?>
        <# var defaultValue = '#RRGGBB', defaultValueAttr = '',
			isHueSlider = data.mode === 'hue';
		if ( data.defaultValue && _.isString( data.defaultValue ) && ! isHueSlider ) {
			if ( '#' !== data.defaultValue.substring( 0, 1 ) ) {
				defaultValue = '#' + data.defaultValue;
			} else {
				defaultValue = data.defaultValue;
			}
			defaultValueAttr = ' data-default-color=' + defaultValue; // Quotes added automatically.
		} #>
		<# if ( data.label ) { #>
			<span class="customize-control-title">{{{ data.label }}}</span>
		<# } #>
		<# if ( data.description ) { #>
			<span class="description customize-control-description">{{{ data.description }}}</span>
		<# } #>
		<div class="customize-control-content">
			<label><span class="screen-reader-text">{{{ data.label }}}</span>
			<# if ( isHueSlider ) { #>
				<input class="color-picker-hue" type="text" data-type="hue" />
			<# } else { #>
				<input class="color-picker-hex" type="text" maxlength="7" placeholder="{{ defaultValue }}" {{ defaultValueAttr }} />
			<# } #>
			</label>
		</div>

		<#
		var descriptionId = _.uniqueId( 'customize-media-control-description-' );
		var describedByAttr = data.description ? ' aria-describedby="' + descriptionId + '" ' : '';
		#>

		<div class="customize-control-notifications-container"></div>

		<# if ( data.attachment && data.attachment.id ) { #>
			<div class="attachment-media-view attachment-media-view-{{ data.attachment.type }} {{ data.attachment.orientation }}">
				<div class="thumbnail thumbnail-{{ data.attachment.type }}">
					<# if ( 'image' === data.attachment.type && data.attachment.sizes && data.attachment.sizes.medium ) { #>
						<img class="attachment-thumb" src="{{ data.attachment.sizes.medium.url }}" draggable="false" alt="" />
					<# } else if ( 'image' === data.attachment.type && data.attachment.sizes && data.attachment.sizes.full ) { #>
						<img class="attachment-thumb" src="{{ data.attachment.sizes.full.url }}" draggable="false" alt="" />
					<# } else { #>
						<img class="attachment-thumb type-icon icon" src="{{ data.attachment.icon }}" draggable="false" alt="" />
						<p class="attachment-title">{{ data.attachment.title }}</p>
					<# } #>
				</div>
				<div class="actions">
					<# if ( data.canUpload ) { #>
					<button type="button" class="button remove-button">{{ data.button_labels.remove }}</button>
					<button type="button" class="button upload-button control-focus" {{{ describedByAttr }}}>{{ data.button_labels.change }}</button>
					<# } #>
				</div>
			</div>
		<# } else { #>
			<div class="attachment-media-view">
				<# if ( data.canUpload ) { #>
					<button type="button" class="upload-button button-add-media" {{{ describedByAttr }}}>{{ data.button_labels.select }}</button>
				<# } #>
				<div class="actions">
					<# if ( data.defaultAttachment ) { #>
						<button type="button" class="button default-button">{{ data.button_labels['default'] }}</button>
					<# } #>
				</div>
			</div>
		<# } #>
		<?php
	}

	/**
	 * Get default button labels.
	 *
	 * Provides an array of the default button labels based on the mime type of the current control.
	 *
	 * @since 4.9.0
	 *
	 * @return string[] An associative array of default button labels keyed by the button name.
	 */
	public function get_default_button_labels() {
		// Get just the mime type and strip the mime subtype if present.
		$mime_type = ! empty( $this->mime_type ) ? strtok( ltrim( $this->mime_type, '/' ), '/' ) : 'default';

		switch ( $mime_type ) {			
			case 'image':
			default:
				return array(
					'select'       => __( 'Select image' ),
					'site_icon'    => __( 'Select Site Icon' ),
					'change'       => __( 'Change image' ),
					'default'      => __( 'Default' ),
					'remove'       => __( 'Remove' ),
					'placeholder'  => __( 'No image selected' ),
					'frame_title'  => __( 'Select image' ),
					'frame_button' => __( 'Choose image' ),
				);
		} // End switch().
	}
    }
}