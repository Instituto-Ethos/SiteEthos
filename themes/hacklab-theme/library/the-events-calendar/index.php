<?php

namespace hacklabr;

defined( 'ABSPATH' ) || exit;

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

/**
 * Ensures that an event post has all the meta required by TEC Custom Tables V1.
 *
 * TEC requires `_EventStartDateUTC`, `_EventEndDateUTC`, and `_EventTimezone` to
 * populate the `wp_tec_events` table. Events created before TEC v6 or imported
 * through non-standard flows may be missing these UTC dates.
 *
 * @param int $post_id The event post ID.
 * @return array List of meta keys that were added/updated.
 */
function ensure_event_utc_meta( int $post_id ): array {
    $fixed = [];

    $timezone    = get_post_meta( $post_id, '_EventTimezone', true );
    $start_local = get_post_meta( $post_id, '_EventStartDate', true );
    $end_local   = get_post_meta( $post_id, '_EventEndDate', true );
    $start_utc   = get_post_meta( $post_id, '_EventStartDateUTC', true );
    $end_utc     = get_post_meta( $post_id, '_EventEndDateUTC', true );
    $duration    = get_post_meta( $post_id, '_EventDuration', true );

    // Default timezone: use existing, or fall back to site default, or UTC-3.
    if ( empty( $timezone ) ) {
        $timezone = get_option( 'timezone_string' ) ?: 'UTC-3';
        update_post_meta( $post_id, '_EventTimezone', $timezone );
        $fixed[] = '_EventTimezone';
    }

    // Build DateTime objects from local dates in the event timezone.
    if ( ! empty( $start_local ) && empty( $start_utc ) ) {
        try {
            $tz = \Tribe__Timezones::build_timezone_object( $timezone );
            $dt = new \DateTime( $start_local, $tz );
            $start_utc = $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
            update_post_meta( $post_id, '_EventStartDateUTC', $start_utc );
            $fixed[] = '_EventStartDateUTC';
        } catch ( \Throwable $e ) {
            do_action( 'logger', "ensure_event_utc_meta: Failed to convert start date for Post {$post_id}: " . $e->getMessage(), 'error' );
        }
    }

    if ( ! empty( $end_local ) && empty( $end_utc ) ) {
        try {
            $tz = \Tribe__Timezones::build_timezone_object( $timezone );
            $dt = new \DateTime( $end_local, $tz );
            $end_utc = $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
            update_post_meta( $post_id, '_EventEndDateUTC', $end_utc );
            $fixed[] = '_EventEndDateUTC';
        } catch ( \Throwable $e ) {
            do_action( 'logger', "ensure_event_utc_meta: Failed to convert end date for Post {$post_id}: " . $e->getMessage(), 'error' );
        }
    }

    // Calculate duration if missing and both UTC dates are available.
    if ( empty( $duration ) && ! empty( $start_utc ) && ! empty( $end_utc ) ) {
        $diff = strtotime( $end_utc ) - strtotime( $start_utc );
        if ( $diff > 0 ) {
            update_post_meta( $post_id, '_EventDuration', $diff );
            $fixed[] = '_EventDuration';
        }
    }

    // Sanity check: fix end_utc that is chronologically before start_utc.
    if ( ! empty( $start_utc ) && ! empty( $end_utc ) && strtotime( $end_utc ) < strtotime( $start_utc ) ) {
        if ( ! empty( $end_local ) && trim( $end_local ) !== trim( $start_local ) ) {
            try {
                $tz = \Tribe__Timezones::build_timezone_object( $timezone );
                $dt = new \DateTime( $end_local, $tz );
                $end_utc = $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
            } catch ( \Throwable $e ) {
                $end_utc = $start_utc;
            }
        } else {
            $end_utc = $start_utc;
        }
        update_post_meta( $post_id, '_EventEndDateUTC', $end_utc );
        $fixed[] = '_EventEndDateUTC';
    }

    // Fix duration: ensure non-negative and consistent with corrected UTC dates.
    if ( ! empty( $start_utc ) && ! empty( $end_utc ) ) {
        $diff = strtotime( $end_utc ) - strtotime( $start_utc );
        if ( empty( $duration ) || (int) $duration <= 0 ) {
            update_post_meta( $post_id, '_EventDuration', max( 0, $diff ) );
            $fixed[] = '_EventDuration';
        }
    }

    return $fixed;
}

function fix_orphaned_events_batch() {
    global $wpdb;

    $batch_size    = 10;
    $max_batches   = 50;
    $tec_table     = $wpdb->prefix . 'tec_events';
    $batch_option  = '_ethos_fix_orphaned_events_batch';
    $cursor_option = '_ethos_fix_orphaned_events_cursor';
    $last_id       = (int) get_option( $cursor_option, 0 );

    do_action( 'logger', 'fix_orphaned_events: Batch iniciando...', 'debug' );

    // Bail if TEC custom table does not exist (plugin deactivated).
    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tec_table ) );
    if ( $table_exists !== $tec_table ) {
        delete_option( $batch_option );
        delete_option( $cursor_option );
        do_action( 'logger', 'fix_orphaned_events: Tabela ' . $tec_table . ' nao encontrada. TEC esta ativo? Resultado SHOW TABLES: ' . var_export( $table_exists, true ), 'error' );
        return;
    }

    $orphans = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$tec_table} te ON te.post_id = p.ID
         WHERE p.post_type = %s
           AND p.post_status = %s
           AND te.event_id IS NULL
           AND p.ID > %d
         ORDER BY p.ID
         LIMIT %d",
        'tribe_events', 'publish', $last_id, $batch_size
    ) );

    do_action( 'logger', sprintf(
        'fix_orphaned_events: Query retornou %d orfaos. IDs: %s',
        count( $orphans ),
        empty( $orphans ) ? '(nenhum)' : implode( ', ', $orphans )
    ), 'debug' );

    if ( empty( $orphans ) ) {
        $any_remaining = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$tec_table} te ON te.post_id = p.ID
             WHERE p.post_type = %s
               AND p.post_status = %s
               AND te.event_id IS NULL",
            'tribe_events', 'publish'
        ) );
        delete_option( $batch_option );
        delete_option( $cursor_option );

        if ( $any_remaining > 0 ) {
            do_action( 'logger', sprintf(
                'fix_orphaned_events: Cursor chegou ao fim. %d orfaos restantes (possivelmente nao corrigiveis). Cursor reiniciado para nova passada.',
                $any_remaining
            ), 'warning' );
        } else {
            do_action( 'logger', 'fix_orphaned_events: Nenhum evento orfao restante. Tarefa concluida.', 'info' );
        }
        return;
    }

    $batch_num   = (int) get_option( $batch_option, 0 ) + 1;
    $events_service = tribe( \TEC\Events\Custom_Tables\V1\Updates\Events::class );
    $fixed   = 0;
    $skipped = 0;

    // Advance cursor to last ID in this batch (advances even on failure).
    update_option( $cursor_option, (int) end( $orphans ) );

    foreach ( $orphans as $post_id ) {
        $start = get_post_meta( $post_id, '_EventStartDate', true );
        if ( empty( $start ) ) {
            $skipped++;
            do_action( 'logger', "fix_orphaned_events: Post {$post_id} ignorado - sem _EventStartDate", 'warning' );
            continue;
        }

        // Ensure UTC dates and timezone meta exist before calling Events::update().
        $meta_fixed = ensure_event_utc_meta( $post_id );
        if ( ! empty( $meta_fixed ) ) {
            do_action( 'logger', sprintf(
                'fix_orphaned_events: Post %d meta preenchida: %s',
                $post_id, implode( ', ', $meta_fixed )
            ), 'debug' );
        }

        // Diagnostic: dump all relevant meta for the first post in the batch.
        if ( $fixed === 0 && $skipped === 0 ) {
            $diag = [
                '_EventStartDate'     => get_post_meta( $post_id, '_EventStartDate', true ),
                '_EventEndDate'       => get_post_meta( $post_id, '_EventEndDate', true ),
                '_EventStartDateUTC'  => get_post_meta( $post_id, '_EventStartDateUTC', true ),
                '_EventEndDateUTC'    => get_post_meta( $post_id, '_EventEndDateUTC', true ),
                '_EventTimezone'      => get_post_meta( $post_id, '_EventTimezone', true ),
                '_EventDuration'      => get_post_meta( $post_id, '_EventDuration', true ),
                'post_type'           => get_post_type( $post_id ),
                'post_status'         => get_post_status( $post_id ),
            ];
            do_action( 'logger', sprintf(
                'fix_orphaned_events: DIAG Post %d meta dump: %s',
                $post_id, wp_json_encode( $diag )
            ), 'debug' );
        }

        do_action( 'logger', "fix_orphaned_events: Processando Post {$post_id} (_EventStartDate={$start})", 'debug' );

        try {
            // Step 1: Get the data TEC extracts from post meta.
            $event_data = \TEC\Events\Custom_Tables\V1\Models\Event::data_from_post( $post_id );

            if ( empty( $event_data ) ) {
                $skipped++;
                do_action( 'logger', "fix_orphaned_events: Post {$post_id} - data_from_post retornou vazio (post_type incorreto?)", 'error' );
                continue;
            }

            // Step 2: Attempt the upsert directly to capture DB errors.
            $upsert = \TEC\Events\Custom_Tables\V1\Models\Event::upsert( [ 'post_id' ], $event_data );

            if ( $upsert === false ) {
                $skipped++;
                $db_error = ! empty( $wpdb->last_error ) ? $wpdb->last_error : '(sem erro SQL)';
                do_action( 'logger', sprintf(
                    'fix_orphaned_events: Post %d UPSERT falhou. DB error: %s | data: %s',
                    $post_id, $db_error, wp_json_encode( $event_data )
                ), 'error' );

                // If upsert failed, don't even try Events::update().
                continue;
            }

            do_action( 'logger', sprintf(
                'fix_orphaned_events: Post %d UPSERT OK (result=%s). Chamando Events::update para occurrences...',
                $post_id, var_export( $upsert, true )
            ), 'debug' );

            // Step 3: Complete the occurrence save via Events::update().
            $result = $events_service->update( $post_id );

            if ( $result ) {
                $fixed++;
                do_action( 'logger', "fix_orphaned_events: Post {$post_id} corrigido com sucesso", 'debug' );
            } else {
                $skipped++;
                do_action( 'logger', "fix_orphaned_events: Post {$post_id} upsert OK mas Events::update falhou (occurrences?)", 'error' );
            }
        } catch ( \Throwable $e ) {
            $skipped++;
            do_action( 'logger', "fix_orphaned_events: Post {$post_id} lancou excecao: " . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine(), 'error' );
        }
    }

    $remaining = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$tec_table} te ON te.post_id = p.ID
         WHERE p.post_type = %s
           AND p.post_status = %s
           AND te.event_id IS NULL",
        'tribe_events', 'publish'
    ) );

    do_action( 'logger', sprintf(
        'fix_orphaned_events: Batch %d/%d concluido - Corrigidos: %d, Ignorados: %d, Restantes: %d',
        $batch_num, $max_batches, $fixed, $skipped, $remaining
    ), 'info' );

    if ( $remaining > 0 && $batch_num < $max_batches ) {
        update_option( $batch_option, $batch_num );
        do_action( 'logger', sprintf(
            'fix_orphaned_events: Re-enfileirando proximo batch (%d). Restantes: %d',
            $batch_num + 1, $remaining
        ), 'debug' );
        \ethos\crm\schedule_job( 'fix_orphaned_events', '' );
    } else {
        delete_option( $batch_option );
        if ( $batch_num >= $max_batches ) {
            // Keep cursor so next run continues from where it left off.
            do_action( 'logger', sprintf(
                'fix_orphaned_events: Limite de %d batches atingido. Restantes: %d. Cursor preservado (ID=%d) para proxima execucao.',
                $max_batches, $remaining, (int) get_option( $cursor_option, 0 )
            ), 'warning' );
        } else {
            delete_option( $cursor_option );
            do_action( 'logger', 'fix_orphaned_events: Todos os eventos orfaos foram processados. Tarefa concluida.', 'info' );
        }
    }
}
