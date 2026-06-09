<?php

namespace hacklabr;

require_once get_theme_file_path( 'library/the-events-calendar/Meta_Save.php' );

add_action( 'init', 'hacklabr\replace_events_meta_save_class' );

// @todo Avaliar remocao: estes filtros pertenciam ao TEC Pro pre-6.0 (Tribe__Events__Pro__Recurrence__Navigation).
//       No TEC Pro v7+ com Custom Tables V1, os hooks `tribe_events_pro_detect_recurrence_redirect` e
//       `tribe_events_pro_recurrence_redirect_url` nao sao mais aplicados (apply_filters). Este codigo e inativo.
//       O redirect de ocorrencias para o evento pai e feito por redirect_recurring_events_to_parent() em utils.php.
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

add_filter( 'register_post_type_args', function ( $args, $post_type ) {
    if ( $post_type === 'tribe_event_series' ) {
        $args['exclude_from_search'] = true;
    }
    return $args;
}, 20, 2 );

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

function get_venue_from_crm( string|null $venue ): string|null {
    if ( is_singular( 'tribe_events' ) ) {
        $post_id = get_the_ID();

        $place = get_post_meta( $post_id, '_ethos_crm:fut_txt_local', true );
        if ( ! empty( $place ) ) {
            return str_replace( [ "\n", "\r" ], [ '<br/>', '' ], $place );
        }
    }

    return $venue;
}

add_filter( 'tribe_get_venue', 'hacklabr\\get_venue_from_crm' );

add_action( 'ethos_job:fix_orphaned_events', 'hacklabr\\fix_orphaned_events_batch' );

function fix_orphaned_events_batch() {
    global $wpdb;

    $batch_size   = 10;
    $max_batches  = 50;
    $tec_table    = $wpdb->prefix . 'tec_events';
    $batch_option = '_ethos_fix_orphaned_events_batch';

    $orphans = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$tec_table} te ON te.post_id = p.ID
         WHERE p.post_type = 'tribe_events'
           AND p.post_status = 'publish'
           AND te.event_id IS NULL
         ORDER BY p.ID
         LIMIT %d",
        $batch_size
    ) );

    if ( empty( $orphans ) ) {
        delete_option( $batch_option );
        do_action( 'logger', 'fix_orphaned_events: Nenhum evento orfao restante. Tarefa concluida.', 'info' );
        return;
    }

    $batch_num   = (int) get_option( $batch_option, 0 ) + 1;
    $events_service = tribe( \TEC\Events\Custom_Tables\V1\Updates\Events::class );
    $fixed   = 0;
    $skipped = 0;

    foreach ( $orphans as $post_id ) {
        $start = get_post_meta( $post_id, '_EventStartDate', true );
        if ( empty( $start ) ) {
            $skipped++;
            do_action( 'logger', "fix_orphaned_events: Post {$post_id} ignorado - sem _EventStartDate", 'warning' );
            continue;
        }

        $result = $events_service->update( $post_id );
        if ( $result ) {
            $fixed++;
        } else {
            $skipped++;
            do_action( 'logger', "fix_orphaned_events: Post {$post_id} falhou ao atualizar custom tables", 'error' );
        }
    }

    $remaining = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$tec_table} te ON te.post_id = p.ID
         WHERE p.post_type = 'tribe_events'
           AND p.post_status = 'publish'
           AND te.event_id IS NULL"
    );

    do_action( 'logger', sprintf(
        'fix_orphaned_events: Batch %d/%d concluido - Corrigidos: %d, Ignorados: %d, Restantes: %d',
        $batch_num, $max_batches, $fixed, $skipped, $remaining
    ), 'info' );

    if ( $remaining > 0 && $batch_num < $max_batches ) {
        update_option( $batch_option, $batch_num );
        \ethos\crm\ensure_jobs_table();
        \ethos\crm\schedule_job( 'fix_orphaned_events', '' );
    } else {
        delete_option( $batch_option );
        if ( $batch_num >= $max_batches ) {
            do_action( 'logger', sprintf(
                'fix_orphaned_events: Limite de %d batches atingido. Restantes: %d. Interrompido para evitar loop infinito.',
                $max_batches, $remaining
            ), 'warning' );
        } else {
            do_action( 'logger', 'fix_orphaned_events: Todos os eventos orfaos foram processados. Tarefa concluida.', 'info' );
        }
    }
}
