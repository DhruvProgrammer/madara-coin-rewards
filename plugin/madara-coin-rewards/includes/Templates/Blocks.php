<?php
/**
 * Server-rendered blocks.
 *
 * The six blocks are thin wrappers around the shortcode renderer: the markup
 * is produced on the server by `MCR\Templates\Templates::render()`, so a block
 * and a shortcode always output exactly the same page.
 *
 * @package MCR
 */

namespace MCR\Templates;

use MCR\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's dynamic blocks.
 */
class Blocks {

	/**
	 * Editor script handle referenced by every block.
	 */
	const EDITOR_SCRIPT = 'mcr-blocks';

	/**
	 * Singleton instance.
	 *
	 * @var Blocks|null
	 */
	private static $instance = null;

	/**
	 * Whether registration was scheduled or completed.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Blocks
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the blocks.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		// The shortcodes must exist so a block preview can render.
		Shortcodes::instance()->register();

		if ( did_action( 'init' ) ) {
			$this->register_blocks();

			return;
		}

		add_action( 'init', array( $this, 'register_blocks' ), 5 );
	}

	/**
	 * Registers every block type with the block registry.
	 *
	 * @return void
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$this->register_editor_script();

		foreach ( Templates::registry() as $slug => $definition ) {
			$name = isset( $definition['block'] ) && '' !== (string) $definition['block']
				? sanitize_key( (string) $definition['block'] )
				: $slug;

			$attributes = array(
				'layout'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'columns' => array(
					'type'    => 'number',
					'default' => 0,
				),
			);

			register_block_type(
				'madara-coin-rewards/' . $name,
				array(
					'api_version'     => 2,
					'title'           => isset( $definition['label'] ) ? $definition['label'] : $slug,
					'category'        => 'widgets',
					'icon'            => 'coins',
					'editor_script'   => self::EDITOR_SCRIPT,
					'attributes'      => $attributes,
					'render_callback' => function ( $block_attributes = array() ) use ( $slug ) {
						return $this->render( $slug, is_array( $block_attributes ) ? $block_attributes : array() );
					},
					'supports'        => array(
						'html'     => false,
						'multiple' => true,
					),
				)
			);
		}
	}

	/**
	 * Renders a block.
	 *
	 * @param string $slug       Page slug.
	 * @param array  $attributes Block attributes.
	 * @return string
	 */
	public function render( $slug, array $attributes = array() ) {
		$atts = array(
			'layout'  => isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : '',
			'columns' => isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 0,
		);

		return Shortcodes::instance()->render( $slug, $atts );
	}

	/**
	 * Registers the editor script when it ships with the plugin.
	 *
	 * The handle is referenced by every block regardless; registering it only
	 * when the file exists keeps the editor free of 404s on installs where the
	 * editor bundle has not been built.
	 *
	 * @return void
	 */
	private function register_editor_script() {
		if ( ! function_exists( 'wp_register_script' ) || wp_script_is( self::EDITOR_SCRIPT, 'registered' ) ) {
			return;
		}

		$file = defined( 'MCR_PLUGIN_DIR' ) ? MCR_PLUGIN_DIR . 'assets/js/mcr-blocks.js' : '';

		if ( '' === $file || ! is_readable( $file ) ) {
			return;
		}

		wp_register_script(
			self::EDITOR_SCRIPT,
			MCR_PLUGIN_URL . 'assets/js/mcr-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			defined( 'MCR_VERSION' ) ? MCR_VERSION : false,
			true
		);

		/*
		 * Hand the block list to the editor so the PHP registry and the
		 * editor controls cannot drift apart. The layouts are described
		 * with their labels; an empty value means "use the site default".
		 */
		$blocks = array();

		foreach ( Templates::registry() as $slug => $definition ) {
			$name = isset( $definition['block'] ) && '' !== (string) $definition['block']
				? sanitize_key( (string) $definition['block'] )
				: $slug;

			$layouts = array_merge(
				array( '' => __( 'Site default', 'madara-coin-rewards' ) ),
				Templates::layouts( $slug )
			);

			$blocks[] = array(
				'name'           => $name,
				'title'          => isset( $definition['label'] ) ? (string) $definition['label'] : $slug,
				'layouts'        => $layouts,
				'defaultColumns' => max( 1, min( 4, absint( Settings::get( 'template.columns', 3 ) ) ) ),
			);
		}

		wp_localize_script(
			self::EDITOR_SCRIPT,
			'mcrBlocks',
			array( 'blocks' => $blocks )
		);
	}
}
