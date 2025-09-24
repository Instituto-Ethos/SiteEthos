<?php

namespace hacklabr;

function get_posts_grid_data($attributes): \WP_Query {
    $post__not_in = [];

    if (!empty($attributes['preventRepeatPosts'])) {
        $post__not_in = get_used_post_ids();
    }

    $query_args = build_posts_query($attributes, $post__not_in);
    $query = new \WP_Query($query_args);

    return $query;
}

function render_posts_grid_callback( $attributes ) {
    $attributes = (array) $attributes;

    // Calculate postsPerPage if pagination is disabled
    if ( empty( $attributes['enablePagination'] ) || $attributes['enablePagination'] === false ) {
        $attributes['postsPerPage'] = (int) ( $attributes['postsPerColumn'] ?? 1 ) * (int) ( $attributes['postsPerRow'] ?? 1 );
    }

    $html_inner = render_posts_grid_inner( $attributes, 1 );


    /**
     * Checks if the 'preventRepeatPosts' attribute is set and not empty.
     * If true, assigns the result of get_used_post_ids() to the 'postNotIn' attribute,
     * which is used to exclude previously displayed posts from the current query.
     */
    if ( ! empty( $attributes['preventRepeatPosts'] ) && ! empty( $attributes['preventRepeatPosts'] ) ) {
        $attributes['postNotIn'] = get_used_post_ids();
    }

    $config = [
        'attributes' => $attributes,
        'rest'       => [
            'root'  => rest_url( 'hacklabr/v1/' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ],
    ];

    // Add pagination navigation if enabled
    if ( ! empty( $attributes['enablePagination'] ) ) {
        $html_inner .= '<div class="hacklabr-posts-grid__nav"></div>';
    }

    return sprintf(
        '<div class="hacklabr-posts-grid__root" data-config="%s">%s</div>',
        esc_attr( wp_json_encode( $config ) ),
        $html_inner
    );
}

function render_posts_grid_inner( array $attributes, int $page = 1 ): string {
    $posts_per_row    = (int) ( $attributes['postsPerRow'] ?? 1 );
    $posts_per_column = (int) ( $attributes['postsPerColumn'] ?? 1 );

    $card_model       = $attributes['cardModel'] ?? '';
    $card_modifiers   = $attributes['cardModifiers'] ?? [];
    $hide_author      = (bool)( $attributes['hideAuthor'] ?? false );
    $hide_categories  = (bool)( $attributes['hideCategories'] ?? false );
    $hide_date        = (bool)( $attributes['hideDate'] ?? false );
    $hide_excerpt     = (bool)( $attributes['hideExcerpt'] ?? false );

    $enable_pagination = (bool)( $attributes['enablePagination'] ?? false );

    $query_attributes = normalize_posts_query( $attributes );

    /**
     * If the 'preventRepeatPosts' attribute is set and the 'postNotIn' attribute contains post IDs,
     * assigns those IDs to $post__not_in. This helps prevent displaying duplicate posts in the grid.
     */
    $post__not_in = [];

    if ( ! empty( $attributes['preventRepeatPosts'] ) && ! empty( $attributes['postNotIn'] ) ) {
        $post__not_in = $attributes['postNotIn'];
    }

    $args = build_posts_query( $query_attributes, $post__not_in );
    $args['paged'] = max( 1, (int) $page );

    if ( ! $enable_pagination ) {
        $args['posts_per_page'] = $posts_per_column;
    }

    if ( isset( $args['order_by'] ) && ! isset( $args['orderby'] ) ) {
        $args['orderby'] = $args['order_by'];
        unset( $args['order_by'] );
    }

    $q = new \WP_Query( $args );

    ob_start(); ?>
    <div class="hacklabr-posts-grid-block" style="--grid-columns: <?= (int) $posts_per_row ?>">
        <?php foreach ($q->posts as $post) :

            if ( function_exists( __NAMESPACE__ . '\\mark_post_id_as_used' ) ) {
                mark_post_id_as_used( $post->ID );
            }

            get_template_part(
                'template-parts/post-card',
                $card_model ?: null,
                [
                    'hide_author'     => $hide_author,
                    'hide_categories' => $hide_categories,
                    'hide_date'       => $hide_date,
                    'hide_excerpt'    => $hide_excerpt,
                    'modifiers'       => $card_modifiers,
                    'post'            => $post,
                    'show_taxonomies' => $attributes['showTaxonomies'] ?? [],
                ]
            );
        endforeach; ?>
    </div>
    <?php
    wp_reset_postdata();

    return ob_get_clean();
}

function register_posts_grid_rest_route() {
    register_rest_route( 'hacklabr/v1', '/posts-grid', [
        'methods'  => 'POST',
        'callback' => function( \WP_REST_Request $req ) {
            $attributes = sanitize_request_attributes( (array) $req->get_param( 'attributes' ) );
            $page       = max(1, (int) $req->get_param( 'page') );

            $qa = normalize_posts_query( $attributes );

            $args = build_posts_query( $qa, [] );
            $args['paged'] = $page;

            if ( isset( $args['order_by'] ) && ! isset( $args['orderby'] ) ) {
                $args['orderby'] = $args['order_by'];
                unset($args['order_by']);
            }

            $q = new \WP_Query( $args );

            ob_start(); ?>
            <div class="hacklabr-posts-grid-block" style="--grid-columns: <?= (int) ( $attributes['postsPerRow'] ?? 1 ) ?>">
            <?php foreach ( $q->posts as $post ) {
                get_template_part('template-parts/post-card', ( $attributes['cardModel'] ?? '' ) ?: null, [
                    'hide_author'     => (bool)( $attributes['hideAuthor'] ?? false ),
                    'hide_categories' => (bool)( $attributes['hideCategories'] ?? false ),
                    'hide_date'       => (bool)( $attributes['hideDate'] ?? false ),
                    'hide_excerpt'    => (bool)( $attributes['hideExcerpt'] ?? false ),
                    'modifiers'       => $attributes['cardModifiers'] ?? [],
                    'post'            => $post,
                    'show_taxonomies' => $attributes['showTaxonomies'] ?? [],
                ]);
            } ?>
            </div>
            <?php
            $html = ob_get_clean();
            $resp = ['html' => $html, 'page' => $page, 'totalPages' => (int) $q->max_num_pages, 'total' => (int) $q->found_posts];
            wp_reset_postdata();
            return $resp;
        },
        'permission_callback' => '__return_true'
    ]);
}

add_action('rest_api_init', 'hacklabr\\register_posts_grid_rest_route');
