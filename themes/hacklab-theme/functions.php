<?php

namespace hacklabr;

load_theme_textdomain('hacklabr');

require __DIR__ . '/library/ethos-associados-redirects.php';

require __DIR__ . '/library/layout-parts/layout-parts.php';
require __DIR__ . '/library/supports.php';
require __DIR__ . '/library/sidebars.php';
require __DIR__ . '/library/menus.php';
require __DIR__ . '/library/settings.php';
require __DIR__ . '/library/assets.php';
require __DIR__ . '/library/crm/index.php';
require __DIR__ . '/library/membership.php';
require __DIR__ . '/library/forms.php';
require __DIR__ . '/library/form-fields.php';
require __DIR__ . '/library/search.php';
require __DIR__ . '/library/cron.php';
require __DIR__ . '/library/api/index.php';
require __DIR__ . '/library/sanitizers/index.php';
require __DIR__ . '/library/template-tags/index.php';
require __DIR__ . '/library/utils.php';
require __DIR__ . '/library/the-events-calendar/index.php';
require __DIR__ . '/library/blocks/index.php';
require __DIR__ . '/library/associates-area.php';
require __DIR__ . '/library/remove-thumbnail-and-excerpt.php';
require __DIR__ . '/library/shortcodes/shortcodes.php';

require __DIR__ . '/library/forms/helpers.php';
require __DIR__ . '/library/forms/custom-fields.php';
require __DIR__ . '/library/forms/custom-forms.php';
require __DIR__ . '/library/forms/registration.php';
require __DIR__ . '/library/forms/edit-organization.php';
require __DIR__ . '/library/forms/my-data.php';
require __DIR__ . '/library/forms/requests.php';

require __DIR__ . '/library/events/index.php';

add_action( 'init', function() {
    if(isset($_GET['crm_sync_events']) && current_user_can('manage_options')) {
        ini_set('max_execution_time', 0);
        echo "IMPORTANDO EVENTOS <pre>";
        $number = intval($_GET['crm_sync_events']) ?: 5;
        do_get_crm_events($number);
        die;
    }
}, 150 );

add_action( 'init', function() {
    if( isset( $_GET['crm_sync_event'] ) && current_user_can( 'manage_options' ) ) {
        if ( \function_exists( 'hacklabr\\do_get_crm_event' ) ) {
            $crm_sync_event = sanitize_text_field( $_GET['crm_sync_event'] );
            ini_set( 'max_execution_time', 0 );
            echo "IMPORTANDO EVENTO: " . $crm_sync_event . "<pre>";

            $result = do_get_crm_event( $crm_sync_event );

            if ( $result ) {
                echo "Evento importado com sucesso. ID: $result";
            } else {
                echo "Erro ao importar evento.";
            }

            die;
        }
    }
}, 150 );

/**
 * Adds a custom filter to the WordPress search query in admin to include meta fields in the search.
 *
 * Hooks:
 * - `admin_init`: Initializes the custom filter when the admin area is initialized.
 * - `posts_search`: Modifies the search query to include posts with a specific meta key and value.
 *
 * @param string $search The existing search SQL.
 * @param WP_Query $query The current WP_Query instance.
 * @return string Modified search SQL to include meta fields.
 */
add_action( 'admin_init', function() {
    add_filter( 'posts_search', function( $search, $query ) {
        global $wpdb;

        if ( current_user_can( 'manage_options' ) && $query->is_main_query() && ! empty( $query->query['s'] ) ) {
            $meta_key = 'entity_fut_projeto';
            $like = '%' . $wpdb->esc_like( $query->query['s'] ) . '%';

            $search .= $wpdb->prepare("
                OR EXISTS (
                    SELECT * FROM {$wpdb->postmeta}
                    WHERE post_id = {$wpdb->posts}.ID
                    AND meta_key = %s
                    AND meta_value LIKE %s
                )
            ", $meta_key, $like);
        }

        return $search;
    }, 10, 2 );
} );

/**
 * Updates the term counts for the specified terms and taxonomy.
 * This function calls the `wp_update_term_count_now()` function to update the term counts.
 *
 * @example https://domain.com/?update_terms_count=1&terms=5,10&taxonomy=category
 */
add_action( 'init', function() {

    if ( ! current_user_can( 'manage_options' ) )
        return;

    if ( isset( $_GET['update_terms_count'] ) && isset( $_GET['terms'] ) && isset( $_GET['taxonomy'] ) ) {
        $terms = array_map( 'intval', explode( ',', $_GET['terms'] ) );
        $taxonomy = sanitize_text_field( $_GET['taxonomy'] );
        echo "<pre>";
        wp_update_term_count_now( $terms, $taxonomy );
        echo "Termos atualizados com sucesso.";
        die;
    }
}, 150 );
