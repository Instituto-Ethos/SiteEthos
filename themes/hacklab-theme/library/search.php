<?php

namespace hacklabr;

/**
 * @todo refactor filters on search
 */
function join_meta_table( $join ) {
    global $wpdb;

    if ( is_search() ) {
        $join .=' LEFT JOIN '.$wpdb->postmeta. ' hl_meta ON '. $wpdb->posts . '.ID = hl_meta.post_id ';
    }

    return $join;
}

// add_filter( 'posts_join', 'hacklabr\\join_meta_table' );

function modify_where_clause( $where ) {
    global $pagenow, $wpdb;

    if ( is_search() ) {
        $where = preg_replace(
            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(".$wpdb->posts.".post_title LIKE $1) OR (hl_meta.meta_value LIKE $1)", $where );
    }
    return $where;
}

// add_filter( 'posts_where', 'hacklabr\\modify_where_clause' );

function prevent_duplicates( $where ) {
    global $wpdb;

    if ( is_search() ) {
        return "DISTINCT";
    }
    return $where;
}

// add_filter( 'posts_distinct', 'hacklabr\\prevent_duplicates' );

function post_types_in_search_results( $query ) {
    if ( $query->is_main_query() && $query->is_search() && ! is_admin() ) {

        $term = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : false;
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : false;

        if($term) {
            $tax_query = [
                [
                    'field'    => 'slug',
                    'taxonomy' => 'category',
                    'terms'    => [ $term ]
                ]
            ];

            $query->set( 'tax_query', $tax_query );

        }

        if($post_type) {
            if($post_type === 'events'){
                $query->set( 'post_type', 'tribe_events' );
                $query->set( 'post_parent', 0 );
            }else{
                $query->set( 'post_type', explode(',', $post_type) );

            }
        }
    }
}
add_action( 'pre_get_posts', 'hacklabr\\post_types_in_search_results' );

function exclude_event_children_from_search( $where, $query ) {
    if ( $query->is_search() && ! $query->is_admin() ) {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_type != 'tribe_events' OR {$wpdb->posts}.post_parent = 0)";
    }
    return $where;
}
add_filter( 'posts_where', 'hacklabr\\exclude_event_children_from_search', 10, 2 );

