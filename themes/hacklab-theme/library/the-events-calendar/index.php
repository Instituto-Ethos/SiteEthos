<?php

namespace hacklabr;

require_once get_theme_file_path( 'library/the-events-calendar/Meta_Save.php' );

add_action( 'init', 'hacklabr\replace_events_meta_save_class' );

// Keep past occurrences accessible: avoid TEC Pro recurrence redirect.
add_filter( 'tribe_events_pro_detect_recurrence_redirect', '__return_false', 10, 2 );
add_filter( 'tribe_events_pro_recurrence_redirect_url', function( $url ) {
    $wp_query = tribe_get_global_query_object();

    if ( empty( $url ) || is_null( $wp_query ) ) {
        return $url;
    }

    $event_date = $wp_query->get( 'eventDate' );
    $event_ts   = $event_date ? strtotime( $event_date ) : false;

    // If the requested occurrence is in the past, skip redirect.
    if ( $event_ts && $event_ts < current_time( 'timestamp' ) ) {
        return null;
    }

    return $url;
}, 10 );

function replace_events_meta_save_class() {
    remove_action( 'save_post', [ 'Tribe__Events__Main', 'addEventMeta' ], 15 );
    add_action( 'save_post', 'hacklabr\ethos_events_save_meta', 10, 2 );
}

function ethos_events_save_meta( $post_id, $post ) {
    if ( 'tribe_events' !== $post->post_type ) {
        return;
    }

    $context = new \Tribe__Events__Meta__Context();
    $meta_save = new \Ethos_Events_Meta_Save( $post_id, $post, $context );
    $meta_save->maybe_save();
}

add_action( 'admin_menu', 'hacklabr\ethos_events_add_metabox' );

function ethos_events_add_metabox() {
    add_meta_box(
        'tribe_events_event_details',
        'The Events Calendar',
        [ tribe( 'tec.admin.event-meta-box' ), 'init_with_event' ],
        'tribe_events',
        'normal',
        'high'
    );
}

function get_event_url( $subscription ) {
    $event_id = $subscription->Attributes['fut_lk_projeto']?->Id ?? null;

    if ( ! empty( $event_id ) ) {
        $post_id = event_exists_on_wp( $event_id ) ?: null;

        if ( $post_id ) {
            return get_permalink( $post_id );
        }
    }

    $inscription_id = $subscription->Attributes['fut_txt_nro_inscricao'] ?? '';

    $matches = [];

    if ( preg_match( '/^[A-Z]+(\d+)/', $inscription_id, $matches ) && ! empty( $matches[1] ) ) {
        return get_home_url( null, '/conteudo/inscricao-evento?id=' . $matches[1] );
    }

    return '#';
}
