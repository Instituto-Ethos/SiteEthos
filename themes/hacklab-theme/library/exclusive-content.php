<?php

namespace hacklabr;

function redirect_exclusive_singles_for_logged_users () {
    if (is_user_logged_in()) {
        return;
    }

    if (is_singular() && has_category(['curadoria', 'exclusivo-do-associado'])) {
        wp_safe_redirect(get_login_page_url());
        exit;
    }
}
add_action('template_redirect', 'hacklabr\\redirect_exclusive_singles_for_logged_users');
