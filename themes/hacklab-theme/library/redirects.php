<?php

namespace hacklabr;

function hide_admin_bar( bool $show ): bool {
    if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
        return $show;
    }

    return false;
}

add_filter( 'show_admin_bar', 'hacklabr\\hide_admin_bar' );

function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! $user || is_wp_error( $user ) ) {
        return $redirect_to;
    }

    if ( user_can( $user, 'edit_others_associates' ) ) {
        return home_url( '/selecionar-organizacao' );
    }

    if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) {
        return admin_url();
    }

    if ( get_user_meta( $user->ID, '_ethos_from_crm', true ) == '1' ) {
        $welcome_page = get_page_by_path( 'boas-vindas' );

        if ( $welcome_page && is_associates_area_page( $welcome_page ) ) {
            return get_permalink( $welcome_page->ID );
        }
    }

    return home_url( '/' );
}

add_filter( 'login_redirect', 'hacklabr\\redirect_after_login', 10, 3 );

function prevent_admin_access() {
    if ( wp_doing_ajax() ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    $user = wp_get_current_user();

    if ( user_can( $user, 'edit_others_associates' ) ) {
        wp_safe_redirect( home_url( '/associados/boas-vindas' ) );
        exit;
    }

    if ( in_array( 'subscriber', (array) $user->roles, true ) ) {
        wp_safe_redirect( home_url( '/associados/boas-vindas' ) );
        exit;
    }
}

add_action( 'admin_init', 'hacklabr\\prevent_admin_access' );
