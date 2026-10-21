/**
 * Block editor registration for Madara Coin & Rewards.
 *
 * Registers each plugin page as a server-rendered block with a Layout
 * and Columns control in the inspector. The block list is handed over from
 * PHP via the `mcrBlocks` global so the two never drift apart.
 *
 * No build step, no bundler, no external dependency: this is plain
 * wp.element code, the same style WordPress core ships.
 */
( function ( blocks, blockEditor, element, components, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ServerSideRender = serverSideRender;

	var config = window.mcrBlocks || { blocks: [] };

	/**
	 * Renders the block preview in the editor.
	 *
	 * @param {Object} props Block props.
	 * @return {Object} Element.
	 */
	function edit( props ) {
		var block = props.block;
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		var controls = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: i18n.__( 'Layout', 'madara-coin-rewards' ), initialOpen: true },
				el( SelectControl, {
					label: i18n.__( 'Layout', 'madara-coin-rewards' ),
					value: attributes.layout,
					options: block.layouts,
					onChange: function ( value ) {
						setAttributes( { layout: value } );
					}
				} ),
				el( RangeControl, {
					label: i18n.__( 'Columns', 'madara-coin-rewards' ),
					value: attributes.columns || 0,
					min: 0,
					max: 4,
					help: i18n.sprintf(
						/* translators: %d: default number of columns */
						i18n.__( '0 uses the site default (%d).', 'madara-coin-rewards' ),
						block.defaultColumns
					),
					onChange: function ( value ) {
						setAttributes( { columns: value } );
					}
				} )
			)
		);

		var preview = el( ServerSideRender, {
			block: block.name,
			attributes: attributes
		} );

		return el( Fragment, {}, controls, preview );
	}

	config.blocks.forEach( function ( block ) {
		blocks.registerBlockType( 'madara-coin-rewards/' + block.name, {
			title: block.title,
			icon: 'coins',
			category: 'widgets',
			keywords: [ 'coins', 'rewards', 'madara', 'manga' ],
			supports: {
				html: false,
				multiple: true
			},
			attributes: {
				layout: { type: 'string', default: '' },
				columns: { type: 'number', default: 0 }
			},
			edit: edit
		} );
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.element,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
);
