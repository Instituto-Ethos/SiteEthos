<?php

namespace hacklabr;

function register_event_routes () {
    register_rest_route('hacklabr/v2', '/events/cnpj', [
        'methods' => 'POST',
        'callback' => 'hacklabr\\rest_events_cnpj_callback',
        'permission_callback' => '__return_true',
        'args' => [
            'cnpj' => [
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && strlen($param) === 14;
                },
            ],
        ],
    ]);
}
add_action('rest_api_init', 'hacklabr\\register_event_routes');

function rest_events_cnpj_callback (\WP_REST_Request $request) {
    $cnpj = sanitize_text_field($request->get_param('cnpj'));

    $posts = get_posts([
        'post_type' => 'organizacao',
        'meta_query' => [
            [ 'key' => 'cnpj', 'value' => $cnpj ],
        ],
    ]);

    if (empty($posts)) {
        return new \WP_REST_Response(null, 404);
    }

    $post = $posts[0];

    $response = [
        'cnpj' => $cnpj,
        'nome_fantasia' => get_post_meta($post->ID, 'nome_fantasia', true),
        'razao_social' => get_post_meta($post->ID, 'razao_social', true),
    ];

    return new \WP_REST_Response($response, 200);
}
