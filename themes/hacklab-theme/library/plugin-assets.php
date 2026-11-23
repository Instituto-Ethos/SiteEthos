<?php

namespace hacklabr;

/**
 * Dequeues plugin assets that their plugins enqueue globally, on the requests that
 * do not use them (round R01, epic #282, characterization gap G4).
 *
 * Every predicate below depends only on the request — URL/template, queried content
 * and plugin options — and never on the logged-in user, so the HTML a page cache
 * stores is valid for every visitor it is served to.
 *
 * Assets that other queued assets depend on are dequeued but never deregistered:
 * WP_Dependencies::all_deps() skips any queued item that lists an unregistered
 * dependency, so a deregister would silently drop the dependents as well.
 */
function dequeue_unused_plugin_assets(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_embed() ) {
		return;
	}

	// Events Calendar PRO's Swiper is kept on every calendar view request. Elsewhere
	// nothing instantiates it: of the scripts The Events Calendar loads outside the
	// views, the only one that references Swiper is the map provider, and it does so
	// inside the map tooltip slider, which only exists in the map view.
	if ( ! plugin_assets_is_calendar_view() ) {
		plugin_assets_remove_script( 'tribe-swiper' );
	}

	// CoBlocks' GoDaddy Styles and its dependency wp-components only style React
	// controls under `.godaddy-styles` / `.components-*`, which are rendered in
	// wp-admin only.
	wp_dequeue_style( 'godaddy-styles' );
	wp_dequeue_style( 'wp-components' );

	if ( ! plugin_assets_needs_pmpro_styles() ) {
		foreach ( array_keys( wp_styles()->registered ) as $handle ) {
			if ( in_array( $handle, [ 'pmpro_frontend_base', 'pmpro_frontend_base_rtl' ], true ) || str_starts_with( $handle, 'pmpro_frontend_variation_' ) ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	// Assets whose markup only comes from shortcodes/blocks placed in content.
	// Requests where the content cannot be inspected keep them.
	$dynamics = plugin_assets_content_has( [ 'msdyncrm' ] );
	if ( false === $dynamics ) {
		wp_dequeue_style( 'wordpresscrm-layout' );
		wp_dequeue_style( 'wordpresscrm-jquery' );
		wp_dequeue_style( 'jquery-datetimepicker' );
	}

	$cf7 = plugin_assets_content_has( [ 'contact-form-7' ] );
	if ( false === $cf7 ) {
		wp_dequeue_style( 'contact-form-7' );
		wp_dequeue_style( 'walcf7-datepicker-css' );
		wp_dequeue_script( 'swv' );
	}

	$icons = plugin_assets_content_has( [ 'bi-', 'wp:atbs' ] );
	if ( false === $icons ) {
		wp_dequeue_style( 'atbs-blocks-bootstrap-icons' );
	}
}

add_action( 'wp_enqueue_scripts', 'hacklabr\\dequeue_unused_plugin_assets', PHP_INT_MAX );

/**
 * Whether the request is a The Events Calendar view (list, month, ...), as opposed
 * to a single event or to a page that just embeds a view.
 */
function plugin_assets_is_calendar_view(): bool {
	if ( ! function_exists( 'tribe_is_event_query' ) || ! function_exists( 'tribe_is_event' ) ) {
		return false;
	}

	return tribe_is_event_query() && ! tribe_is_event();
}

/**
 * Stops a script from being printed without affecting the scripts that list it as a
 * dependency: the handle gives way, in each dependent, to the script's own
 * dependencies, and is then dequeued. The script stays registered so that anything
 * registering against it later in the request still resolves.
 */
function plugin_assets_remove_script( string $handle ): void {
	global $wp_scripts;

	if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
		return;
	}

	$transitive = (array) $wp_scripts->registered[ $handle ]->deps;

	foreach ( $wp_scripts->registered as $script ) {
		$position = array_search( $handle, $script->deps, true );

		if ( false === $position ) {
			continue;
		}

		array_splice( $script->deps, $position, 1, $transitive );
		$script->deps = array_values( array_unique( $script->deps ) );
	}

	wp_dequeue_script( $handle );
}

/**
 * Whether Paid Memberships Pro styles may be used by the request.
 */
function plugin_assets_needs_pmpro_styles(): bool {
	if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
		return false;
	}

	// The pages PMPro assigns to itself (checkout, confirmation, account, login, ...).
	$pmpro_pages = get_option( 'pmpro_pages' );

	if ( is_array( $pmpro_pages ) ) {
		$page_ids = array_values( array_filter( array_map( 'intval', $pmpro_pages ) ) );

		if ( $page_ids && is_page( $page_ids ) ) {
			return true;
		}
	}

	// Membership entry point and the plan page of the associates area.
	if ( is_page( [ 'associe-se', 'meu-plano' ] ) ) {
		return true;
	}

	// PMPro shortcodes in content (also covers the Add Ons, e.g. [pmprogroupacct_...]).
	if ( true === plugin_assets_content_has( [ '[pmpro', '[membership' ] ) ) {
		return true;
	}

	// Content restriction: the "membership required" notice replaces the content of
	// a restricted post, or the excerpt of a restricted post shown in any loop.
	$post = plugin_assets_queried_post();

	if ( $post ) {
		$levels = pmpro_has_membership_access( $post->ID, null, true );

		if ( is_array( $levels ) && ! empty( $levels[1] ) ) {
			return true;
		}
	}

	return plugin_assets_has_restricted_terms();
}

/**
 * Whether any term is restricted by a membership level.
 */
function plugin_assets_has_restricted_terms(): bool {
	global $wpdb;

	static $has = null;

	if ( null !== $has ) {
		return $has;
	}

	if ( empty( $wpdb->pmpro_memberships_categories ) || empty( $wpdb->pmpro_membership_levels ) ) {
		return $has = true; // Cannot tell: keep the styles.
	}

	// Same join PMPro runs to resolve a term restriction.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- PMPro custom tables have no object cache layer.
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->pmpro_memberships_categories} mc JOIN {$wpdb->pmpro_membership_levels} m ON mc.membership_id = m.id" );

	return $has = (bool) $count;
}

/**
 * The post a singular request displays, if any.
 */
function plugin_assets_queried_post(): ?\WP_Post {
	if ( ! is_singular() ) {
		return null;
	}

	$post = get_queried_object();

	return $post instanceof \WP_Post ? $post : null;
}

/**
 * The raw content a request may render, or null when it cannot be determined.
 *
 * Only singular requests are inspectable: their content is the queried post, the
 * reusable blocks it references and the classic text/HTML/block widgets. Listing
 * requests (archives, search, calendar views) may render any number of posts.
 */
function plugin_assets_request_content(): ?string {
	static $resolved = false, $content = null;

	if ( $resolved ) {
		return $content;
	}

	$resolved = true;

	$post = plugin_assets_queried_post();

	if ( ! $post ) {
		return $content;
	}

	$parts = [ $post->post_content ];

	if ( preg_match_all( '/"ref":(\d+)/', $post->post_content, $matches ) ) {
		foreach ( array_unique( $matches[1] ) as $block_id ) {
			$block = get_post( (int) $block_id );

			if ( $block instanceof \WP_Post ) {
				$parts[] = $block->post_content;
			}
		}
	}

	foreach ( [ 'widget_text', 'widget_custom_html', 'widget_block' ] as $option ) {
		$widgets = get_option( $option );

		if ( is_array( $widgets ) && $widgets ) {
			$parts[] = (string) wp_json_encode( $widgets );
		}
	}

	return $content = strtolower( implode( "\n", $parts ) );
}

/**
 * Whether the request renders markup from any of the given content tokens.
 *
 * Returns null when the content cannot be inspected.
 */
function plugin_assets_content_has( array $tokens ): ?bool {
	$content = plugin_assets_request_content();

	if ( null === $content ) {
		return null;
	}

	foreach ( $tokens as $token ) {
		if ( false !== strpos( $content, strtolower( $token ) ) ) {
			return true;
		}
	}

	return false;
}
