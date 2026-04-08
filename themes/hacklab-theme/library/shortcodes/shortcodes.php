<?php

namespace hacklabr;

function nome_do_contato_shortcode ($attributes) {
    $attrs = shortcode_atts([
        'fallback' => '',
        'postid' => null,
    ], $attributes);

    if (!empty($attrs['postid'])) {
        $post_id = intval($attrs['postid']);
    } else {
        $post_id = null;
    }

    return get_primary_contact_name($post_id) ?: $attrs['fallback'];
}

function nome_do_gerente_shortcode ($attributes) {
    $attrs = shortcode_atts([
        'fallback' => '',
        'postid' => null,
    ], $attributes);

    if (!empty($attrs['postid'])) {
        $post_id = intval($attrs['postid']);
    } else {
        $post_id = null;
    }

    $manager = get_manager_data($post_id);

    if ($manager) {
        if (!empty($manager->email)) {
            return sprintf( '<a href="mailto:%s">%s</a>', $manager->email, $manager->name );
        } else {
            return $manager->name;
        }
    }

    return $attrs['fallback'];
}

function nome_da_empresa_shortcode ($attributes) {
    $attrs = shortcode_atts([
        'fallback' => '',
        'postid' => null,
    ], $attributes);


    if (!empty($attrs['postid'])) {
        $post_id = intval($attrs['postid']);
    } else {
        $post_id = null;
    }

    return get_organization_name($post_id) ?: $attrs['fallback'];
}

/**
 * Renders the company size shortcode HTML.
 *
 * Retrieves company size data from the CRM and generates a grid layout
 * displaying the percentage and label for each company size category.
 *
 * @return string The generated HTML for the company size grid.
 *
 * @since 1.0.0
 */
function render_company_size_shortcode() : string {
    $data = \ethos\crm\get_organizations_company_size_data();

    $enum = [
        'micro_small' => __( 'Micro e pequenas empresas', 'hacklabr' ),
        'medium'      => __( 'Médias empresas', 'hacklabr' ),
        'large'       => __( 'Grandes empresas', 'hacklabr' )
    ];

    $html = '<div class="company-size">';
    $html .= '<div class="company-size__grid">';
    foreach( $data['percentages'] as $key => $value ) {
        $html .= '<div class="company-size__item">';
        $html .= '<div class="company-size__percent">' . $value . '%</div>';
        $html .= '<div class="company-size__label">' . $enum[$key] . '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function render_company_count_shortcode() : string {
    $data = \ethos\crm\get_organizations_count_data();

    $last_updated_text = '';

    if ( ! empty( $data['last_updated'] ) ) {
        // Convert ISO 8601 to Brazilian format
        $date = new \DateTime( $data['last_updated'] );
        $last_updated_text = sprintf(
            '<div class="company-count__updated">Atualizado em: %s</div>',
            $date->format( 'd/m/Y' )
        );
    }

    $html = '<div class="company-count">';
    $html .= '<div class="company-count__container">';
    $html .= $last_updated_text;
    $html .= '<div class="company-count__total">';
    $html .= '<span class="company-count__label">Total de empresas associadas:</span>';
    $html .= '<span class="company-count__number">' . $data['total'] . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function register_shortcodes() {
    add_shortcode( 'nome-do-contato', 'hacklabr\\nome_do_contato_shortcode' );
    add_shortcode( 'nome-do-gerente', 'hacklabr\\nome_do_gerente_shortcode' );
    add_shortcode( 'nome-da-empresa', 'hacklabr\\nome_da_empresa_shortcode' );
    add_shortcode( 'porte-das-organizacoes', 'hacklabr\\render_company_size_shortcode' );
    add_shortcode( 'contagem-de-organizacoes', 'hacklabr\\render_company_count_shortcode' );
}

add_action( 'init', 'hacklabr\\register_shortcodes' );
