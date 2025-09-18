<?php

namespace hacklabr;

use WP_Query;


/**
 * Registers a custom role 'manager_associates' with specific capabilities.
 *
 * This function adds a new user role called 'Manager associates' with the following capabilities:
 * - 'read': Allows reading posts.
 * - 'edit_others_posts': Disallowed, cannot edit others' posts.
 * - 'edit_others_associates': Allowed, can edit others' associates (custom capability).
 *
 * Uses the WordPress native function {@see add_role()}.
 * @link https://developer.wordpress.org/reference/functions/add_role/
 *
 * Hooked to the 'after_switch_theme' action to ensure the role is added when the theme is activated.
 *
 * @since 1.0.0
 *
 * @return void
 */
function add_associates_manager_role() {
    add_role(
        'manager_associates',
        __( 'Admin-Relacionamento', 'hacklabr' ),
        [
            'read' => true,
            'edit_others_posts' => false,
            'edit_others_associates' => true,
        ]
    );
}
add_action( 'after_switch_theme', __NAMESPACE__ . '\\add_associates_manager_role' );

function add_associates_rewrite_rule() {
    add_rewrite_rule(
        '^associados/([^/]*)/?',
        'index.php?pagename=$matches[1]',
        'top'
    );
}
add_action( 'init', 'hacklabr\\add_associates_rewrite_rule' );

function modify_associates_permalink( $url, $post_id ) {
    if ( is_page() && get_page_template_slug( $post_id ) == 'template-associates-area.php' ) {
        $post_name = get_post_field( 'post_name', $post_id );

        if ( $post_name && strpos( $url, '/associados/' . $post_name ) === false ) {
            $url = home_url( '/associados/' . $post_name );
        }
    }

    return $url;
}
add_filter( 'page_link', 'hacklabr\\modify_associates_permalink', 10, 2 );

function redirect_associates_template() {
    if ( is_page() && get_page_template_slug() == 'template-associates-area.php' ) {
        global $post;

        /**
         * Redirects non-logged-in users to the login page
         */
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( get_login_page_url() );
            exit;
        }

        if ( ! show_associated_page( $post ) ) {
            wp_redirect( home_url('/associados/boas-vindas'));
            exit;
        }

        $current_url = untrailingslashit( $_SERVER['REQUEST_URI'] );
        $new_url = home_url( '/associados/' . get_post_field( 'post_name', get_queried_object_id() ) );

        if ( strpos( $current_url, '/associados/' ) === false ) {
            wp_safe_redirect( $new_url );
            exit;
        }
    }
}
add_action( 'template_redirect', 'hacklabr\\redirect_associates_template' );

function redirect_manager_associates_on_login( $user_login, $user ) {
    if ( in_array( 'manager_associates', (array) $user->roles, true ) ) {
        wp_safe_redirect( home_url( '/selecionar-organizacao/' ) );
        exit;
    }
}

add_action( 'wp_login', 'hacklabr\\redirect_manager_associates_on_login', 10, 2 );

function show_associated_page($page) {
    $admin_pages = [
        'meu-plano',
        'minhas-solicitacoes',
        'pagamentos',
        'perfil-da-empresa',
    ];

    if(in_array($page->post_name, $admin_pages)){
        $user_id = get_current_user_id();

        /**
         * Checks if the current user has the capability to edit other associates.
         *
         * @return bool True if the user can edit other associates, false otherwise.
         */
        if ( current_user_can( 'edit_others_associates' ) ) {
            return true;
        }

        return (bool) get_user_meta($user_id, '_ethos_admin', true);
    }
    return true;
}

function pmpro_login_redirect_url( $redirect_to, $request, $user ) {
    if ( ! \function_exists( 'pmpro_url' ) || empty( $user->ID ) ) {
        return $redirect_to;
    }

    global $wpdb;

    $is_member = $wpdb->get_var( "SELECT membership_id FROM $wpdb->pmpro_memberships_users WHERE status = 'active' AND user_id = '" . esc_sql( $user->ID ) . "' LIMIT 1" );
    if ( $is_member ) {

        $get_welcome_page = get_page_by_path( 'boas-vindas', OBJECT, 'page' );

        if ( $get_welcome_page ) {
            $template = get_page_template_slug( $get_welcome_page->ID );
            if ( $template == 'template-associates-area.php' ) {
                $redirect_to = get_permalink( $get_welcome_page->ID );
            }
        }

    }

    return $redirect_to;
}
add_filter( 'pmpro_login_redirect_url', 'hacklabr\\pmpro_login_redirect_url', 20, 3 );

function add_recaptcha_to_password_recovery(){
    pmpro_init_recaptcha();
    pmpro_recaptcha_get_html();
}
add_action( 'pmpro_lost_password_before_submit_button', 'hacklabr\\add_recaptcha_to_password_recovery' );

/**
 * Filters the main query on the 'curadoria' category archive page to only include posts of type 'publicacao'.
 *
 * @param WP_Query $query The WP_Query instance.
 */
function filter_curadoria_category_archive_query( WP_Query $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( $query->is_category( 'curadoria' ) ) {
        $query->set( 'post_type', ['publicacao'] );
        $query->set( 'ignore_sticky_posts', true );
    }
}

add_action( 'pre_get_posts', __NAMESPACE__ . '\\filter_curadoria_category_archive_query' );

function add_link_select_organization( $associates_areas, $get_params ) {
    if ( current_user_can( 'edit_others_associates' ) ) {
        echo '<li class="content-sidebar__list-item"><a href="/selecionar-organizacao">Selecionar organização</a></li>';
    }
}

add_action( 'hacklabr\\before_associates_area_list_items', __NAMESPACE__ . '\\add_link_select_organization', 10, 2 );
