<?php

namespace hacklabr;

function add_user_to_pmpro_group ($user_id, $group_id) {
    $group = get_pmpro_group($group_id);

    $parent_level_id = $group->group_parent_level_id;
    $child_level_id = Fields\get_pmpro_child_level($parent_level_id);

    $membership = \PMProGroupAcct_Group_Member::create($user_id, $child_level_id, $group->id);

    assert($membership instanceof \PMProGroupAcct_Group_Member);

    \pmpro_changeMembershipLevel($child_level_id, $user_id);

    return $membership;
}

function create_pmpro_group ($user_id, $level_id = 11) {
    $group = \PMProGroupAcct_Group::create($user_id, $level_id, 100);

    assert($group instanceof \PMProGroupAcct_Group);

    \pmpro_changeMembershipLevel($level_id, $user_id);

    return $group;
}

function get_pmpro_group ($group_id) {
    return new \PMProGroupAcct_Group($group_id);
}

function update_group_level ($group_id, $level_id = 11) {
    global $wpdb;

    $group = get_pmpro_group($group_id);

    if ($group->group_parent_level_id == $level_id) {
        return $group;
    }

    $child_members = $group->get_active_members(false);
    $child_level_id = Fields\get_pmpro_child_level($level_id);

    $wpdb->update($wpdb->prefix . 'pmprogroupacct_groups',
    [
        'group_parent_level_id' => $level_id,
    ], [
        'id' => $group_id,
    ], ['%d'], ['%d']);

    \pmpro_changeMembershipLevel($level_id, $group->group_parent_user_id);

    foreach ($child_members as $child_member) {
        \pmpro_changeMembershipLevel($child_level_id, $child_member->group_child_user_id);
    }

    return $group;
}

function require_approval_for_login ($user) {
    if (!class_exists('PMPro_Approvals') || empty($user) || is_wp_error($user)) {
        return $user;
    }

    assert($user instanceof \WP_User);

    $group_id = get_user_meta($user->ID, '_pmpro_group', true);

    if (!empty($group_id)) {
        $group = get_pmpro_group($group_id);
        $level_id = $group->group_parent_level_id;
        $child_level_id = Fields\get_pmpro_child_level($level_id);

        if (!\PMPro_Approvals::isApproved($user->ID, $level_id) && !\PMPro_Approvals::isApproved($user->ID, $child_level_id)) {
            return new \WP_Error('ethos_not_approved', 'Associação ainda não foi aprovada.');
        }
    }

    return $user;
}
add_filter('wp_authenticate_user', 'hacklabr\\require_approval_for_login');

function register_organization_cpt () {
    register_post_type('organizacao', [
        'label' => __('Organizations', 'hacklabr'),
        'public' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-building',
        'supports' => ['author', 'custom-fields', 'thumbnail', 'title'],
    ]);
}
add_action('init', 'hacklabr\\register_organization_cpt');
