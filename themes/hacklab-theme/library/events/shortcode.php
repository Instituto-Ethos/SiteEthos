<?php

namespace hacklabr;

function render_event_details_shortcode (): string {
    $post_id = get_the_ID();

    $post_title = get_the_title($post_id);

    $post_content = get_the_content();

    if (empty($post_content)) {
        foreach (['fut_st_txtsitinscr', 'fut_txt_descricao'] as $attribute) {
            $meta_value = get_post_meta($post_id, '_ethos_crm:' . $attribute, true);

            if (!empty($meta_value)) {
                $post_content = str_replace( ["\n", "\r"], ['<br/>', ''], $meta_value );
                break;
            }
        }
    } else {
        $post_content = apply_filters( 'the_content', $post_content );
    }

    return sprintf('<h3 class="shortname">%s</h3>%s', $post_title, $post_content);
}

function register_event_shortcodes () {
    if (is_singular('tribe_events')) {
        add_shortcode('event-details', 'hacklabr\\render_event_details_shortcode');
        add_shortcode('event-registration', 'hacklabr\\render_event_registration_form');
    }
}
add_action('wp_head', 'hacklabr\\register_event_shortcodes');
