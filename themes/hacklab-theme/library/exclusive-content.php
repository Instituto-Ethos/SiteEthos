<?php

namespace hacklabr;

function post_type_has_category ($post_types) {
    if ($post_types === 'any') {
        return true;
    }

    $categorized_post_types = get_taxonomy('category')->object_type;

    if (is_array($post_types)) {
        foreach ($categorized_post_types as $categorized) {
            if (in_array($categorized, $post_types)) {
                return true;
            }
        }
    } elseif (in_array($post_types, $categorized_post_types)) {
        return true;
    }

    return false;
}

function exclude_exclusive_content_from_results (\WP_Query $query): \WP_Query {
    if (is_user_logged_in()) {
        return $query;
    }

    $post_types = $query->get('post_type') ?: 'post';

    if (post_type_has_category($post_types)) {
        $tax_query = [
            'taxonomy' => 'category',
            'field' => 'slug',
            'terms' => ['curadoria', 'exclusivo-do-associado'],
            'operator' => 'NOT IN',
        ];

        $old_tax_query = $query->get('tax_query');
        if (empty($old_tax_query)) {
            $query->set('tax_query', [ 'relation' => 'AND', $old_tax_query, $tax_query ]);
        } else {
            $query->set('tax_query', [ 'relation' => 'AND', $tax_query ]);
        }
    }

    if ($query->is_search() || $post_types === 'page' || (is_array($post_types) && in_array('page', $post_types))) {
        $meta_query = [
            'relation' => 'OR',
            [ 'key' => '_wp_page_template', 'compare' => 'NOT EXISTS', 'value' => 'bug #23268' ],
            [ 'key' => '_wp_page_template', 'compare' => '!=', 'value' => 'template-associates-area.php' ],
        ];

        $old_meta_query = $query->get('meta_query');
        if (empty($old_meta_query)) {
            $query->set('meta_query', [ 'relation' => 'AND', $old_meta_query, $meta_query ]);
        } else {
            $query->set('meta_query', [ 'relation' => 'AND', $meta_query ]);
        }
    }

    return $query;
}
add_filter('pre_get_posts', 'hacklabr\\exclude_exclusive_content_from_results');

function redirect_exclusive_content_for_unlogged_users () {
    if (is_user_logged_in()) {
        return;
    }

    if (is_singular() && has_category(['curadoria', 'exclusivo-do-associado'])) {
        wp_safe_redirect(get_login_page_url());
        exit;
    }
}
add_action('template_redirect', 'hacklabr\\redirect_exclusive_content_for_unlogged_users');
