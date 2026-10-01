<?php

namespace hacklabr;

function is_conteudo_url ($prefix) {
    $url = $_SERVER['REQUEST_URI'];

    return str_starts_with($url, $prefix) || str_starts_with($url, '/conteudo' . $prefix);
}

function build_event_url ($base_url, $args) {
    if ($query_args = http_build_query($args)) {
        return $base_url . '?' . $query_args;
    } else {
        return $base_url;
    }
}

function redirect_legacy_event_urls () {
    $request_url = $_SERVER['REQUEST_URI'];

    if (is_conteudo_url('/inscricao-evento') || is_conteudo_url('/inscricao-conferencia')) {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $event_id = (int) $_GET['id'];

            $post = get_single_tribe_event([
                'meta_query' => [
                    [ 'key' => '_ethos_crm:fut_pf_id', 'value' => $event_id ],
                ],
                'order' => 'DESC',
            ]);

            if ($post) {
                unset($_GET['id']);
                wp_safe_redirect(build_event_url(get_permalink($post->ID), $_GET));
                die;
            }
        }
    }

    if (is_conteudo_url('/certificado-evento') || is_conteudo_url('/certificado-conferencia')) {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $event_id = (int) $_GET['id'];

            $post = get_single_tribe_event([
                'meta_query' => [
                    [ 'key' => '_ethos_crm:fut_pf_id', 'value' => $event_id ],
                ],
                'order' => 'DESC',
            ]);

            if ($post) {
                wp_safe_redirect(get_permalink($post->ID) . "?certificado");
                die;
            }

        }
    }

    if (str_starts_with($request_url, '/conteudo/acesso-a-pagamento')) {
        if ($query_vars = http_build_query($_GET)) {
            $query_vars = '?' . $query_vars;
        }
        wp_redirect( '/acesso-a-pagamento'. $query_vars, 301 );
        die;
    }

    // Redirect of old pages
    if (str_starts_with($request_url, '/faca-parte-associe-se/')) {
        wp_redirect(str_replace('/faca-parte-associe-se/', '/associe-se/', $request_url), 301);
        die;
    }
    if (str_starts_with($request_url, '/conteudo/')) {
        wp_redirect(str_replace('/conteudo/', '/', $request_url), 301);
        die;
    }
};
add_action('template_redirect', 'hacklabr\\redirect_legacy_event_urls');
