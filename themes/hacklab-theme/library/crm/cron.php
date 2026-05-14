<?php

namespace ethos\crm;

function ensure_jobs_table () {
    global $wpdb;

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ethos_jobs (
        job_id bigint(20) unsigned NOT NULL auto_increment,
        job_name varchar(255) NOT NULL,
        job_payload longtext NOT NULL,
        PRIMARY KEY (job_id),
        UNIQUE KEY action_payload (job_name, job_payload)
    )");
}

function call_next_job () {
    global $wpdb;

    $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ethos_jobs LIMIT 1", \OBJECT);
    if (empty($row)) {
        return false;
    }

    try {
        do_action('ethos_job:' . $row->job_name, json_decode($row->job_payload));
        $result = $wpdb->delete($wpdb->prefix . 'ethos_jobs', [ 'job_id' => $row->job_id ], ['%d']);
        return !empty($result);
    } catch (\Throwable $err) {
        do_action('logger', $err->getMessage());
        return false;
    }
}
add_action('hacklabr\\run_every_5_minutes', 'ethos\\crm\\call_next_job');

function schedule_job (string $name, mixed $payload) {
    global $wpdb;

    $json_payload = json_encode($payload);

    // If job already exists, don't try to re-enqueue it
    $query_sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ethos_jobs WHERE job_name = %s AND job_payload = %s LIMIT 1", [
        $name,
        $json_payload
    ]);
    $query = $wpdb->get_row($query_sql, \OBJECT);
    if (!empty($query)) {
        return false;
    }

    $result = $wpdb->insert($wpdb->prefix . 'ethos_jobs', [
        'job_name' => $name,
        'job_payload' => $json_payload,
    ], ['%s', '%s']);
    return !empty($result);
}

function enqueue_last_modified_items (string $entity_name, string|null $last_sync) {
    $entities = \hacklabr\get_crm_entities($entity_name, [
        'cache' => false,
        'orderby' => 'modifiedon',
        'order' => 'DESC',
        'per_page' => 100,
    ]);

    foreach( $entities->Entities as $entity ) {
        if (empty($last_sync) || strcmp($entity->Attributes['modifiedon'], $last_sync) > 0) {
            schedule_job('sync_entity', [$entity_name, $entity->Id]);
        }
    }
}

function sync_next_entity (array $args) {
    [$entity_name, $entity_id] = $args;

    \hacklabr\forget_cached_crm_entity($entity_name, $entity_id);
    $entity = \hacklabr\get_crm_entity_by_id($entity_name, $entity_id);

    if (empty($entity)) {
        do_action( 'ethos_crm:log', "Entity $entity_name - $entity_id not found", 'debug' );
        return;
    }

    switch ($entity_name) {
        case 'account':
            import_account($entity, true);
            break;
        case 'contact':
            import_contact($entity, null, true);
            break;
        case 'fut_projeto':
            import_fut_projeto($entity);
            break;
    }
}
add_action('ethos_job:sync_entity', 'ethos\\crm\\sync_next_entity');

function get_last_crm_sync (): string|null {
    return get_option('_ethos_last_crm_sync', null);
}

function update_last_crm_sync (string|null $datetime = null) {
    if (empty($datetime)) {
        // Use format returned by CRM `modifiedon` attribute
        $datetime = date_format(date_create('now'), 'Y-m-d\TH:i:sp');
    }

    update_option('_ethos_last_crm_sync', $datetime);

    return $datetime;
}

function run_syncs () {
    $last_sync = get_last_crm_sync();

    update_last_crm_sync();

    ensure_jobs_table();

    enqueue_last_modified_items('account', $last_sync);
    enqueue_last_modified_items('contact', $last_sync);
    enqueue_last_modified_items('fut_projeto', $last_sync);
}
add_action('hacklabr\\run_every_hour', 'ethos\\crm\\run_syncs');

function manually_sync_entity() {
    //@TODO Deprecate in favor of `PUT /hacklabr/v2/crm/entity` endpoint

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!empty($_GET['import_account'])) {
        $entity_id = filter_input(INPUT_GET, 'import_account', FILTER_SANITIZE_ADD_SLASHES);
        sync_next_entity(['account', $entity_id]);
    } elseif (!empty($_GET['import_contact'])) {
        $entity_id = filter_input(INPUT_GET, 'import_contact', FILTER_SANITIZE_ADD_SLASHES);
        sync_next_entity(['contact', $entity_id]);
    } elseif (!empty($_GET['import_fut_projeto'])) {
        $entity_id = filter_input(INPUT_GET, 'import_fut_projeto', FILTER_SANITIZE_ADD_SLASHES);
        sync_next_entity(['fut_projeto', $entity_id]);
    }
}
add_action('admin_init', 'ethos\\crm\\manually_sync_entity');

/**
 * Resets WordPress internal query log and object cache to free memory.
 *
 * Intended to be called periodically inside long-running loops (e.g. bulk post
 * updates) to prevent memory exhaustion from accumulated query logs and cached objects.
 *
 * @since 1.0.0
 *
 * @global wpdb    $wpdb            WordPress database abstraction object.
 * @global WP_Object_Cache $wp_object_cache WordPress object cache instance.
 */
function stop_the_insanity () : void {
    global $wpdb, $wp_object_cache;

    $wpdb->queries = [];

    if ( is_object( $wp_object_cache ) ) {
        $wp_object_cache->group_ops = [];
        $wp_object_cache->stats = [];
        $wp_object_cache->memcache_debug = [];
        $wp_object_cache->cache = [];
    }

    if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
        $wp_object_cache->__remoteset();
    }
}

/**
 * Compares all published organizations in WordPress against active accounts in
 * the Dynamics 365 CRM and moves orphaned organizations to the trash.
 *
 * An organization is considered orphan when it has a `_ethos_crm_account_id` meta
 * value but the corresponding CRM account is either inactive, not associated
 * ("Associado"/"Grupo Econômico"), or no longer exists.
 *
 * Results are persisted in the `_ethos_last_reconciliation` option and transient
 * caches for organization counts are invalidated.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return array {
 *     Reconciliation result.
 *
 *     @type string $datetime  MySQL timestamp of execution.
 *     @type int    $total_wp  Total published organizations with CRM account ID.
 *     @type int    $total_crm Total active/associated accounts found in CRM.
 *     @type int    $trashed   Number of organizations moved to trash.
 *     @type array  $orphans   {
 *         List of trashed organizations.
 *
 *         @type int    $post_id    WordPress post ID.
 *         @type string $post_title Organization title.
 *         @type string $account_id CRM account UUID.
 *     }
 * }
 */
function reconcile_organizations () : array {
    global $wpdb;

    do_action( 'ethos_crm:log', 'Reconciliation: starting', 'debug' );

    $crm_active_ids = [];

    $accounts = \hacklabr\iterate_crm_entities( 'account', [
        'cache'    => false,
        'per_page' => 100,
        'orderby'  => 'name',
        'order'    => 'ASC',
    ] );

    foreach ( $accounts as $account ) {
        if ( is_active_account( $account ) ) {
            $crm_active_ids[] = strtolower( $account->Id );
        }
    }

    $crm_active_ids = array_flip( $crm_active_ids );

    $sql = "
        SELECT p.ID AS post_id, p.post_title, pm.meta_value AS account_id
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm
            ON pm.post_id = p.ID AND pm.meta_key = %s
        WHERE p.post_type = %s
          AND p.post_status = %s
    ";

    $wp_orgs = $wpdb->get_results(
        $wpdb->prepare( $sql, '_ethos_crm_account_id', 'organizacao', 'publish' )
    );

    $orphans = [];

    foreach ( $wp_orgs as $org ) {
        $account_id_lower = strtolower( $org->account_id );
        if ( ! isset( $crm_active_ids[ $account_id_lower ] ) ) {
            $orphans[] = $org;
        }
    }

    $trashed = 0;
    $trashed_orphans = [];

    foreach ( $orphans as $i => $orphan ) {
        $result = wp_update_post( [
            'ID'          => $orphan->post_id,
            'post_status' => 'trash',
        ], true );

        if ( is_wp_error( $result ) ) {
            do_action( 'ethos_crm:log', "Reconciliation: FAILED to trash organization \"{$orphan->post_title}\" (post {$orphan->post_id}): " . $result->get_error_message(), 'error' );
            continue;
        }

        do_action( 'ethos_crm:log', "Reconciliation: trashed organization \"{$orphan->post_title}\" (post {$orphan->post_id}, account {$orphan->account_id})", 'debug' );

        $trashed++;
        $trashed_orphans[] = $orphan;

        if ( ( $i + 1 ) % 20 === 0 ) {
            stop_the_insanity();
        }
    }

    delete_transient( 'organizations_count_data' );
    delete_transient( 'organizations_company_size_data' );

    $result = [
        'datetime'  => current_time( 'mysql' ),
        'total_wp'  => count( $wp_orgs ),
        'total_crm' => count( $crm_active_ids ),
        'trashed'   => $trashed,
        'orphans'   => array_map( function ( $orphan ) {
            return [
                'post_id'    => $orphan->post_id,
                'post_title' => $orphan->post_title,
                'account_id' => $orphan->account_id,
            ];
        }, $trashed_orphans ),
    ];

    update_option( '_ethos_last_reconciliation', $result );

    do_action( 'ethos_crm:log', "Reconciliation: finished. WP={$result['total_wp']}, CRM={$result['total_crm']}, Trashed={$trashed}", 'debug' );

    return $result;
}

/**
 * Enqueues a reconciliation job into the ethos_jobs table for async processing.
 *
 * Called by the `hacklabr\run_reconciliation` WP-Cron event. The actual reconciliation
 * is performed by `handle_reconcile_job()` when the job is picked up by `call_next_job()`.
 *
 * @since 1.0.0
 */
function run_reconciliation () {
    ensure_jobs_table();
    schedule_job( 'reconcile_organizations', [] );
}

/**
 * Job handler that executes the organization reconciliation.
 *
 * Triggered by the `ethos_job:reconcile_organizations` action when the job
 * is dequeued by `call_next_job()`.
 *
 * @since 1.0.0
 *
 * @param array $args Job payload (unused, kept for interface compatibility).
 */
function handle_reconcile_job ( array $args ) {
    reconcile_organizations();
}
add_action( 'ethos_job:reconcile_organizations', 'ethos\\crm\\handle_reconcile_job' );

add_action( 'hacklabr\\run_reconciliation', 'ethos\\crm\\run_reconciliation' );
