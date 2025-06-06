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

function sync_next_entity ($args) {
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
