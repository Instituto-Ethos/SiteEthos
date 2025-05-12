<?php

namespace hacklabr;

function enforce_form_post_meta ($form_id) {
    $post_id = get_the_ID();

    if (!empty($post_id)) {
        $forms = get_post_meta($post_id, 'hacklabr_form', false);

        if (!in_array($form_id, $forms)) {
            add_post_meta($post_id, 'hacklabr_form', $form_id, false);
        }
    }
}

function render_form_callback ($attributes) {
    global $hacklabr_registered_forms;

    $form_id = $attributes['formId'] ?: null;

    if (empty($hacklabr_registered_forms) || empty($form_id)) {
        return '';
    }

    $form = $hacklabr_registered_forms[$form_id] ?? null;
    if (empty($form)) {
        return '';
    }

    $form_options = $form['options'];
    $params = call_user_func($form_options['get_params'], $form_options);

    enforce_form_post_meta($form_id);

    ob_start();

    render_form($form, $params, build_class_list('form', $attributes));

    $output = ob_get_clean();

    return apply_filters('hacklabr\\form_output', $output, $form, $params);
}
