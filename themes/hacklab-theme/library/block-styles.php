<?php

namespace hacklabr;

/**
 * Prints in <head> the block styles that WordPress would otherwise print in the footer.
 *
 * The theme loads block assets on demand (library/supports.php). In a classic theme
 * WordPress then holds the global styles back until `wp_footer` and enqueues each block's
 * stylesheet only when the block renders (WP_Block::render()), so both reach the browser
 * after the first paint. Until then the page is laid out without them: on production
 * (2026-11-23) the home columns were first painted stacked and became side by side once
 * the footer was parsed — a layout shift of 0.53.
 *
 * This brings forward the global styles and the stylesheets of the blocks the request
 * renders; blocks that are not on the page keep being left out.
 */
function print_block_styles_in_head(): void {
	if ( is_admin() ) {
		return;
	}

	// Called outside `wp_enqueue_scripts` on purpose: with assets loaded on demand,
	// wp_enqueue_global_styles() returns early inside that action.
	wp_enqueue_global_styles();

	$handles = [ 'global-styles' ];

	foreach ( block_styles_rendered_blocks() as $block_name ) {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		if ( ! $block_type ) {
			continue;
		}

		foreach ( array_merge( (array) $block_type->style_handles, (array) $block_type->view_style_handles ) as $handle ) {
			$handles[] = $handle;
		}
	}

	// Printed in place: wp_print_styles() has already run earlier in wp_head. Priority
	// 102 keeps these after the theme's inline stylesheet (99) and the Customizer CSS
	// (101), the order they already have today in the footer.
	wp_print_styles( array_values( array_unique( $handles ) ) );
}

add_action( 'wp_head', 'hacklabr\\print_block_styles_in_head', 102 );

/**
 * Names of the blocks the request renders, nested and reusable blocks included.
 */
function block_styles_rendered_blocks(): array {
	$names = [];

	foreach ( block_styles_content_sources() as $content ) {
		block_styles_collect_blocks( parse_blocks( $content ), $names, [] );
	}

	return array_keys( $names );
}

/**
 * The block contents a request may render: the main post, the posts of the main query
 * and the classic text/HTML widgets.
 */
function block_styles_content_sources(): array {
	$contents = [];

	$posts = get_queried_object() instanceof \WP_Post ? [ get_queried_object() ] : [];

	global $wp_query;

	if ( $wp_query instanceof \WP_Query && is_array( $wp_query->posts ) ) {
		$posts = $posts + $wp_query->posts;
	}

	foreach ( $posts as $post ) {
		if ( $post instanceof \WP_Post ) {
			$contents[] = $post->post_content;
		}
	}

	foreach ( [ 'widget_text', 'widget_custom_html', 'widget_block' ] as $option ) {
		$widgets = get_option( $option );

		if ( is_array( $widgets ) ) {
			foreach ( $widgets as $instance ) {
				if ( is_array( $instance ) && ! empty( $instance['content'] ) ) {
					$contents[] = $instance['content'];
				}

				if ( is_array( $instance ) && ! empty( $instance['text'] ) ) {
					$contents[] = $instance['text'];
				}
			}
		}
	}

	return $contents;
}

/**
 * Collects the block names found in a list of parsed blocks, following reusable blocks.
 *
 * @param array[] $blocks   Output of parse_blocks().
 * @param array   $names    Found block names, as name => true.
 * @param int[]   $visited  IDs of the reusable blocks already followed.
 */
function block_styles_collect_blocks( array $blocks, array &$names, array $visited ): void {
	foreach ( $blocks as $block ) {
		if ( empty( $block['blockName'] ) ) {
			continue;
		}

		$names[ $block['blockName'] ] = true;

		if ( ! empty( $block['innerBlocks'] ) ) {
			block_styles_collect_blocks( $block['innerBlocks'], $names, $visited );
		}

		if ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
			$ref = (int) $block['attrs']['ref'];

			if ( in_array( $ref, $visited, true ) ) {
				continue;
			}

			$reusable = get_post( $ref );

			if ( $reusable instanceof \WP_Post ) {
				$visited[] = $ref;
				block_styles_collect_blocks( parse_blocks( $reusable->post_content ), $names, $visited );
			}
		}
	}
}
