<?php

namespace hacklabr;

/**
 * Build a dynamic class list from attributes data.
 *
 * @param string $base_class Default block class.
 * @param array $attributes Block attributes.
 * @return string Space-separated class list.
 */
function build_class_list ($base_class = '', $attributes = []) {
    $class_list = [];

    if (!empty($base_class)) {
        $class_list[] = $base_class;
    }

    if (!empty($attributes['className'])) {
        $class_list[] = trim($attributes['className']);
    }

    if (!empty($attributes['align'])) {
        $class_list[] = 'align' . $attributes['align'];
    }

    if (!empty($attributes['gridGap'])) {
        $class_list[] = '-gap-' . $attributes['gridGap'];
    }

    return concat_class_list($class_list);
}

/**
 * Builds a WP_Query args array to query posts for a block.
 *
 * This takes in the block attributes and an optional array of post IDs
 * to exclude. It returns a WP_Query args array to query posts according
 * to the attributes, excluding the given IDs.
 *
 * @param array $attributes Block attributes.
 * @param array $post__not_in Optional array of post IDs to exclude.
 * @return array WP_Query args array.
 */
function build_posts_query ($attributes, $post__not_in = []) {
    $posts_to_show = $attributes['postsPerPage'] ? intval($attributes['postsPerPage']) : 3;
    $post_type = $attributes['postType'] ?: 'post';
    $taxonomy = $attributes['taxonomy'] ?: '';
    $query_terms = $attributes['queryTerms'] ?: [];
    $compare = $attributes['compare'] ?: 'OR';
    $order = $attributes['order'] ?: 'desc';
    $order_by = $attributes['orderBy'] ?: 'date';
    $show_children = $attributes['showChildren'] ?: true;

    $no_post_type = $attributes['noPostType'] ?: 'post';
    $no_taxonomy = $attributes['noTaxonomy'] ?: '';
    $no_query_terms = $attributes['noQueryTerms'] ?: [];
    $no_compare = $attributes['noCompare'] ?: 'OR';

    $no_post__not_in = [];

    if ($no_post_type) {
        $no_args = [
            'post_type' => $no_post_type,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];

        if ($no_taxonomy && $no_query_terms) {
            $no_args['tax_query'] = ['relation' => $no_compare];

            foreach ($no_query_terms as $no_term) {
                $no_args['tax_query'][] = [
                    'taxonomy' => $no_taxonomy,
                    'field' => 'slug',
                    'terms' => [$no_term],
                ];
            }

            $no_post__not_in = get_posts($no_args);
        }
    }

    $args = [
        'ignore_sticky_posts' => true,
        'order' => $order,
        'order_by' => $order_by,
        'post_type' => $post_type,
        'posts_per_page' => $posts_to_show,
    ];

    if ($taxonomy && $query_terms) {
        $args['tax_query'] = ['relation' => $compare];

        foreach ($query_terms as $term) {
            $args['tax_query'][] = [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => [$term],
            ];
        }
    }

    if (!$show_children) {
        $args['post_parent'] = 0;
    }

    $args['post__not_in'] = array_merge(
        $no_post__not_in,
        $post__not_in,
        get_the_ID() ? [get_the_ID()]: []
    );

    return $args;
}

function compute_block_transient_key ( string $namespace, array $attributes = [] ) {
    return $namespace . '_' . md5( serialize( $attributes ) );
}

/**
 * Deletes the transient matching the namespace and attributes.
 *
 * @param string $namespace The block id, or a more generic identifier.
 * @param array $attributes A dictionary of block attributes.
 * @return bool True if the transient was deleted, false otherwise.
 */
function delete_block_transient ( string $namespace, array $attributes = [] ) {
    return delete_transient( compute_block_transient_key( $namespace, $attributes ) );
}

/**
 * Retrives the value of the transient matching the namespace and attributes.
 *
 * If the transient does not exist, does not have a value, or has expired, then the return value will be false.
 *
 * @param string $namespace The block id, or a more generic identifier.
 * @param array $attributes A dictionary of block attributes.
 * @return mixed Value of the transient.
 */
function get_block_transient ( string $namespace, array $attributes = [] ) {
    return get_transient( compute_block_transient_key( $namespace, $attributes ) );
}

/**
 * Retrives the IDs of the posts already displayed by other Gutenberg blocks.
 *
 * @return array The list of post IDs.
 */
function get_used_post_ids () {
    global $newspack_blocks_post_id;
    global $hacklabr_blocks_post_ids;

    if ( ! $newspack_blocks_post_id ) {
        $newspack_blocks_post_id = [];
    }

    if ( ! $hacklabr_blocks_post_ids ) {
        $hacklabr_blocks_post_ids = [];
    }

    $post__not_in = array_merge( $hacklabr_blocks_post_ids, array_keys( $newspack_blocks_post_id ) );
    $post__not_in = array_unique( $post__not_in, SORT_STRING );

    return $post__not_in;
}

/**
 * Mark the post ID as displayed, so the same post won't appear in other Gutenberg posts.
 *
 * @param int $post_id The post ID.
 * @return void
 */
function mark_post_id_as_used ( int $post_id ) {
    global $newspack_blocks_post_id;
    global $hacklabr_blocks_post_ids;

    if ( ! $newspack_blocks_post_id ) {
        $newspack_blocks_post_id = [];
    }

    if ( ! $hacklabr_blocks_post_ids ) {
        $hacklabr_blocks_post_ids = [];
    }

    $hacklabr_blocks_post_ids[] = $post_id;
    $newspack_blocks_post_id[$post_id] = true;
}

/**
 * Normalize the passed attributes for use in posts query and its caching,
 * setting defaults and removing extraneous (e.g. presentational) attributes.
 *
 * @param array $attributes Block attributes.
 * @return array The normalized attributes.
 */
function normalize_posts_query ($attributes) {
    $default_attributes = [
        'compare' => 'OR',
        'noCompare' => 'OR',
        'noPostType' => 'post',
        'noQueryTerms' => [],
        'noTaxonomy' => '',
        'order' => 'desc',
        'orderBy' => 'date',
        'postsPerPage' => 3,
        'postType' => 'post',
        'preventRepeatPosts' => true,
        'queryTerms' => [],
        'showChildren' => true,
        'taxonomy' => '',
    ];

    $normalized_attributes = [];

    foreach ($default_attributes as $key => $default_value) {
        if (isset($attributes[$key])) {
            $normalized_attributes[$key] = $attributes[$key];
        } else {
            $normalized_attributes[$key] = $default_value;
        }
    }

    return $normalized_attributes;
}

/**
 * Sets/updates the value for the transient matching the namespace and attributes.
 *
 * @param string $namespace The block id, or a more generic identifier.
 * @param array $attributes A dictionary of block attributes.
 * @param mixed $value Transient value. Must be serializable if non-scalar.
 * @param int $expiration Time until expiration in seconds. If 0, no expiration.
 * @return bool True if the value was set, false otherwise.
 */
function set_block_transient ( string $namespace, array $attributes, $value, int $expiration = HOUR_IN_SECONDS ) {
    return set_transient( compute_block_transient_key( $namespace, $attributes ), $value, $expiration );
}

/**
 * Sanitizes and normalizes the attributes for a posts query request.
 *
 * This function ensures that all expected attributes are present, properly typed,
 * and safe for use in queries. It applies default values, type casting, and validation
 * for specific keys such as post type, order by, taxonomy, and others.
 *
 * @param array $attributes The raw attributes to sanitize.
 * @return array The sanitized and normalized attributes ready for use in a posts query.
 *
 * @version 1.0.0
 */
function sanitize_request_attributes( array $attributes ): array {
    $sanitized_attributes = [];

    $sanitized_attributes['cardModel']        = (string) ( $attributes['cardModel'] ?? '' );
    $sanitized_attributes['cardModifiers']    = array_values( $attributes['cardModifiers'] ?? [] );
    $sanitized_attributes['gridGap']          = (string) ( $attributes['gridGap'] ?? 'medium' );

    $sanitized_attributes['enablePagination'] = ! empty( $attributes['enablePagination'] );
    $sanitized_attributes['hideAuthor']       = ! empty( $attributes['hideAuthor'] );
    $sanitized_attributes['hideCategories']   = ! empty( $attributes['hideCategories'] );
    $sanitized_attributes['hideDate']         = ! empty( $attributes['hideDate'] );
    $sanitized_attributes['hideExcerpt']      = ! empty( $attributes['hideExcerpt'] );
    $sanitized_attributes['showChildren']     = ! empty( $attributes['showChildren'] );
    $sanitized_attributes['showTaxonomies']   = array_map( 'sanitize_key', $attributes['showTaxonomies'] ?? [] );

    $sanitized_attributes['postsPerColumn']   = max( 1, (int)($attributes['postsPerColumn'] ?? 1 ) );
    $sanitized_attributes['postsPerRow']      = max( 1, (int)( $attributes['postsPerRow'] ?? 1 ) );

    $sanitized_attributes['postsPerPage']     = (int) ( $attributes['postsPerPage'] ?? 0 );

    $allowed_post_types = get_allowed_post_types();
    $post_type = (string)( $attributes['postType'] ?? 'post' );
    $sanitized_attributes['postType'] = in_array( $post_type, $allowed_post_types, true ) ? $post_type : 'post';

    $allowed_orderby = get_allowed_orderby();
    $order_by = (string)( $attributes['orderBy'] ?? 'date' );
    $sanitized_attributes['orderBy'] = in_array( $order_by, $allowed_orderby, true ) ? $order_by : 'date';

    $taxonomy = sanitize_key( $attributes['taxonomy'] ?? '' );
    if ( $taxonomy && taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( $sanitized_attributes['postType'], $taxonomy ) ) {
        $out['taxonomy']   = $taxonomy;
        $out['queryTerms'] = array_map( 'sanitize_title', $attributes['queryTerms'] ?? [] );
    } else {
        $out['taxonomy']   = '';
        $out['queryTerms'] = [];
    }

    $sanitized_attributes['post_status'] = 'publish';
    $sanitized_attributes['has_password'] = false;

    return (array) apply_filters( 'hacklabr/sanitize_request_attributes', $sanitized_attributes );
}

/**
 * Retrieves an array of allowed public post types.
 *
 * This function returns the names of all public post types,
 * allowing customization via the 'hacklabr/get_allowed_post_types' filter.
 *
 * @return array List of allowed public post type names.
 *
 * @since 1.0.0
 */
function get_allowed_post_types(): array {
    return (array) apply_filters( 'hacklabr/get_allowed_post_types', array_values( get_post_types( ['public' => true], 'names' ) ?: [] ) );
}

/**
 * Retrieves an array of allowed values for the 'orderby' parameter in queries.
 *
 * This function returns a list of valid 'orderby' options that can be used to sort query results.
 * The list can be modified using the 'hacklabr/get_allowed_orderby' filter.
 *
 * @return array List of allowed 'orderby' values.
 *
 * @since 1.0.0
 */
function get_allowed_orderby(): array {
    return (array) apply_filters( 'hacklabr/get_allowed_orderby', [
        'date',
        'title',
        'modified',
        'menu_order',
        'rand',
        'ID'
    ] );
}

/**
 * Retrieves the maximum number of items allowed per page.
 *
 * This function returns the maximum number of items that can be displayed per page.
 * The value can be modified using the 'hacklabr/get_max_items_per_page' filter.
 *
 * @return int The maximum number of items per page.
 *
 * @since 1.0.0
 */
function get_max_items_per_page(): int {
    return (int) apply_filters( 'hacklabr/get_max_items_per_page', 99 );
}
