<?php

namespace hacklabr;

class API {

    static function init () {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    static function register_routes() {
        register_rest_route('hacklabr/v2', '/block_settings', [
            'methods' => 'GET',
            'callback' => 'hacklabr\API::rest_block_settings_callback',
            'permission_callback' => 'hacklabr\API::rest_permission_to_edit_posts',
        ]);

        register_rest_route('hacklabr/v2', '/forms', [
            'methods' => 'GET',
            'callback' => 'hacklabr\API::rest_forms_callback',
            'permission_callback' => 'hacklabr\API::rest_permission_to_edit_posts',
        ]);

        register_rest_route('hacklabr/v2', '/options', [
            'methods' => 'GET',
            'callback' => 'hacklabr\API::rest_options_callback',
            'permission_callback' => 'hacklabr\API::rest_permission_to_edit_posts',
        ]);

        register_rest_route('hacklabr/v2', '/post_types', [
            'methods' => 'GET',
            'callback' => 'hacklabr\API::rest_post_types_callback',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hacklabr/v2', '/taxonomies/(?P<post_type>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => 'hacklabr\API::rest_taxonomies_callback',
            'args' => [
                'post_type' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return post_type_exists(sanitize_text_field($param));
                    },
                ],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hacklabr/v2', '/terms/(?P<taxonomy>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => 'hacklabr\API::rest_terms_callback',
            'args' => [
                'search' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'taxonomy' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return taxonomy_exists(sanitize_text_field($param));
                    },
                ],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hacklabr/v2', '/crm/entity', [
            'methods' => 'PUT',
            'callback' => 'hacklabr\API::rest_crm_entity_callback',
            'args' => [
                'entityId' => [
                    'required' => true,
                ],
                'entityName' => [
                    'required' => true,
                ]
            ],
            'permission_callback' => 'hacklabr\API::rest_permission_to_edit_posts',
        ]);

        register_rest_route( 'hacklabr/v2', '/organizations', [
            'methods' => 'GET',
            'callback' => 'hacklabr\API::rest_organizations_callback',
            'args' => [
                's' => [
                    'required' => true,
                ],
            ],
            'permission_callback' => 'hacklabr\API::rest_permission_to_edit_others_associates',
        ]);
    }

    static function send_html ($html) {
        header('Content-Type: text/html');
        echo $html;
        exit();
    }

    static function rest_permission_to_edit_posts () {
        return current_user_can('edit_posts');
    }

    static function rest_block_settings_callback () {
        $response = [
            'cardModels' => [
                'options' => get_card_models(),
                'default' => get_default_card_model(),
            ],
            'cardModifiers' => [
                'options' => get_card_modifiers(),
            ],
        ];

        return new \WP_REST_Response($response, 200);
    }

    static function rest_crm_entity_callback (\WP_REST_Request $request) {
        $entity_id = sanitize_text_field($request->get_param('entityId'));
        $entity_name = sanitize_text_field($request->get_param('entityName'));

        \ethos\crm\sync_next_entity([$entity_name, $entity_id]);

        return new \WP_REST_Response($request->get_params(), 200);
    }

    static function rest_forms_callback () {
        global $hacklabr_registered_forms;

        $response = [];

        if (!empty($hacklabr_registered_forms)) {
            foreach ($hacklabr_registered_forms as $form_id => $form) {
                if (empty($form['options']['hidden'])) {
                    $response[$form_id] = [
                        'slug' => $form_id,
                        'label' => $form['name'],
                    ];
                }
            }
        }

        return new \WP_REST_Response((object) $response, 200);
    }

    static function rest_options_callback () {
        $response = [
            'youtubeKey' => get_option('youtube_key', ''),
        ];

        return new \WP_REST_Response($response, 200);
    }

    static function rest_post_types_callback () {
        $args = [
            'public' => true,
            'show_in_rest' => true,
        ];

        $post_types = get_post_types($args, 'objects');

        $response = [];

        foreach ($post_types as $post_type) {
            $item = [
                'hierarchical' => $post_type->hierarchical,
                'icon' => $post_type->menu_icon,
                'label' => $post_type->label,
                'slug' => $post_type->name,
            ];

            if ($post_type->rest_namespace && $post_type->rest_base) {
                $item['rest_endpoint'] = $post_type->rest_namespace . '/' . $post_type->rest_base;
            } else {
                $item['rest_endpoint'] = null;
            }

            $response[$post_type->name] = $item;
        }

        return new \WP_REST_Response($response, 200);
    }

    static function rest_taxonomies_callback (\WP_REST_Request $request) {
        $post_type = sanitize_text_field($request->get_param('post_type'));

        $taxonomies = get_object_taxonomies($post_type, 'objects');

        $response = [];

        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy->show_in_rest) {
                $item = [
                    'hierarchical' => $taxonomy->hierarchical,
                    'label' => $taxonomy->label,
                    'slug' => $taxonomy->name,
                ];

                if ($taxonomy->rest_namespace && $taxonomy->rest_base) {
                    $item['rest_endpoint'] = $taxonomy->rest_namespace . '/' . $taxonomy->rest_base;
                } else {
                    $item['rest_endpoint'] = null;
                }

                $response[$taxonomy->name] = $item;
            }
        }

        return new \WP_REST_Response($response, 200);
    }

    static function rest_terms_callback (\WP_REST_Request $request) {
        $taxonomy = sanitize_text_field($request->get_param('taxonomy'));
        $search = sanitize_text_field($request->get_param('search'));

        $args = [
            'hide_empty' => true,
            'number' => 0,
            'taxonomy' => $taxonomy,
        ];

        if (!empty($search)) {
            $args['search'] = $search;
        }

        $terms = get_terms($args);

        $response = [];

        foreach ($terms as $term) {
            $item = [
                'id' => $term->term_id,
                'parent' => $term->parent,
                'slug' => $term->slug,
                'name' => $term->name,
            ];

            $response[$term->slug] = $item;
        }

        return new \WP_REST_Response($response, 200);
    }

    static function rest_organizations_callback (\WP_REST_Request $request) {
        $s = sanitize_text_field( $request->get_param( 's' ) );

        $args = [
            'post_type'      => 'organizacao',
            'posts_per_page' => 99,
            'orderby'        => 'relevance',
            's'              => $s,
        ];

        $organizations = get_posts( $args );

        $response = [];

        if ( $organizations && is_array( $organizations ) ) {
            foreach ( $organizations as $organization ) {
                $item = [
                    'id' => $organization->ID,
                    'title' => $organization->post_title,
                ];

                $response[] = $item;
            }
        }

        return rest_ensure_response( $response );
    }

    static function rest_permission_to_edit_others_associates () {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        return (bool) current_user_can( 'edit_others_associates' );
    }
}


API::init();
