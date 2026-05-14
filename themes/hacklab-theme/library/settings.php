<?php

namespace hacklabr;

function get_default_card_model () {
    return 'vertical';
}

function get_card_models () {
    return [
        'cover' => [
            'slug' => 'cover',
            'label' => __('Cover card', 'hacklabr'),
        ],
        'horizontal' => [
            'slug' => 'horizontal',
            'label' => __('Horizontal card', 'hacklabr'),
        ],
        'vertical' => [
            'slug' => 'vertical',
            'label' => __('Vertical card', 'hacklabr'),
        ],
    ];
}

function get_card_modifiers () {
    return [
        'vertical-thumbnail' => [
            'slug' => 'vertical-thumbnail',
            'label' => __('Vertical thumbnail', 'hacklabr'),
        ]
    ];
}

function register_youtube_settings () {
    register_setting(
        'general',
        'youtube_key',
        'esc_attr'
    );

    add_settings_field(
        'youtube_key',
        '<label for="youtube_key">' . __( 'YouTube Key', 'hacklabr') . '</label>',
        'hacklabr\\youtube_key_html',
        'general'
    );
}
add_action( 'admin_init', 'hacklabr\\register_youtube_settings' );

function youtube_key_html() {
    $youtube_key_option = get_option( 'youtube_key', '' );
    echo '<input type="text" name="youtube_key" id="youtube_key" value="' . $youtube_key_option . '" autocomplete="off">';
    echo '<p><i>Crie uma chave de API do YouTube em <a href="https://console.cloud.google.com/apis/credentials">https://console.cloud.google.com/apis/credentials</a></i></p>';
}

/**
 * Registers the reconciliation frequency setting and its admin UI field.
 *
 * Adds the `_ethos_reconciliation_frequency` option to the `sync_settings_group`
 * option group and renders it on the plugin's "Configurações de Sync" page
 * (`sync-settings` slug). Input is sanitized via `sanitize_reconciliation_frequency()`.
 *
 * @since 1.0.0
 */
function register_reconciliation_settings () {
    register_setting( 'sync_settings_group', '_ethos_reconciliation_frequency', [
        'sanitize_callback' => 'hacklabr\\sanitize_reconciliation_frequency',
    ] );

    add_settings_section(
        'reconciliation_settings_section',
        'Reconciliação de Organizações',
        null,
        'sync-settings'
    );

    add_settings_field(
        '_ethos_reconciliation_frequency',
        'Frequência da reconciliação',
        'hacklabr\\reconciliation_frequency_field_callback',
        'sync-settings',
        'reconciliation_settings_section'
    );
}
add_action( 'admin_init', 'hacklabr\\register_reconciliation_settings' );

/**
 * Renders the HTML select element for the reconciliation frequency setting.
 *
 * Options: Diariamente, Semanalmente, Quinzenalmente, Apenas manual.
 *
 * @since 1.0.0
 */
function reconciliation_frequency_field_callback() {
    $current = get_option( '_ethos_reconciliation_frequency', 'weekly' );
    $options = [
        'daily'    => 'Diariamente',
        'weekly'   => 'Semanalmente',
        'biweekly' => 'Quinzenalmente',
        'manual'   => 'Apenas manual',
    ];
    echo '<select name="_ethos_reconciliation_frequency">';
    foreach ( $options as $value => $label ) {
        $selected = selected( $current, $value, false );
        echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Define a frequência com que as organizações do WP são comparadas com o CRM. Organizações que não existem mais como ativas no CRM são movidas para a lixeira.</p>';
}

/**
 * Sanitizes the reconciliation frequency option value.
 *
 * Only allows predefined values: 'daily', 'weekly', 'biweekly', 'manual'.
 * Falls back to 'weekly' for any unexpected input.
 *
 * @since 1.0.0
 *
 * @param mixed $value Raw input value from the settings form.
 * @return string Sanitized frequency identifier.
 */
function sanitize_reconciliation_frequency( $value ) : string {
    $allowed = [ 'daily', 'weekly', 'biweekly', 'manual' ];
    return in_array( $value, $allowed, true ) ? $value : 'weekly';
}

