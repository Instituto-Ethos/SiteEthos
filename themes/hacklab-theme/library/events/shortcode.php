<?php

namespace hacklabr;

function register_event_registration_shortcode() {
    if ( is_singular( 'tribe_events' ) ) {
        add_shortcode( 'event-registration', 'hacklabr\\render_event_registration_form' );
    }
}
add_action( 'wp_head', 'hacklabr\\register_event_registration_shortcode' );
