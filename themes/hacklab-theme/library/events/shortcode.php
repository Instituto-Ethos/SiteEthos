<?php

namespace hacklabr;

function register_event_registration_shortcode() {
    add_shortcode( 'event-registration', 'hacklabr\\render_event_registration_form' );
}
add_action( 'init', 'hacklabr\\register_event_registration_shortcode' );
