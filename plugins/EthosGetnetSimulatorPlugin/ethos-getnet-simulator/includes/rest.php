<?php

namespace ethos\getnetsim;

defined( 'ABSPATH' ) || exit;

function sim_register_rest_routes(): void {
	register_rest_route( 'ethos-getnet-sim/v1', '/outcome', [
		'methods'             => \WP_REST_Server::CREATABLE,
		'callback'            => 'ethos\getnetsim\rest_sim_outcome',
		'permission_callback' => 'ethos\getnetsim\rest_sim_outcome_permission',
		'args'                => [
			'intent_id'  => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'outcome'    => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'payment_id' => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'nonce'      => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	] );
}
add_action( 'rest_api_init', 'ethos\getnetsim\sim_register_rest_routes' );

/**
 * Gate: simulador ativo (não-produção) + (admin OU nonce emitido no render
 * do botão — permite testar o fluxo anônimo, como um inscrito real).
 */
function rest_sim_outcome_permission( \WP_REST_Request $request ): bool {
	if ( ! ethos_getnet_sim_active() ) {
		return false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	$nonce = sanitize_text_field( (string) $request->get_param( 'nonce' ) );

	return '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'ethos_getnet_sim_outcome' );
}

/**
 * Dispara um desfecho simulado contra o PROCESSADOR REAL do webhook
 * (process_getnet_webhook_payload): idempotência por (intent, payment),
 * máquina de transições, PATCH no CRM e invalidação de cache — tudo
 * idêntico à produção, menos a Basic auth (exclusiva da rota REST).
 */
function rest_sim_outcome( \WP_REST_Request $request ) {
	if ( ! function_exists( '\ethos\payments\getnet\process_getnet_webhook_payload' ) ) {
		return new \WP_Error(
			'getnet_sim_processor_missing',
			'The payment plugin does not expose process_getnet_webhook_payload(). Update Ethos Payment Integration.',
			[ 'status' => 500 ]
		);
	}

	$intent_id = sanitize_text_field( (string) $request->get_param( 'intent_id' ) );
	$outcome   = sanitize_text_field( (string) $request->get_param( 'outcome' ) );
	$intent    = get_sim_intent( $intent_id );

	if ( null === $intent ) {
		return new \WP_Error( 'getnet_sim_intent_not_found', 'Unknown simulated intent.', [ 'status' => 404 ] );
	}

	$payment_id = sanitize_text_field( (string) $request->get_param( 'payment_id' ) );
	$payload    = build_sim_webhook_payload( $intent, $outcome, '' !== $payment_id ? $payment_id : null );

	if ( null === $payload ) {
		return new \WP_Error( 'getnet_sim_invalid_outcome', 'Invalid outcome.', [ 'status' => 400 ] );
	}

	$response = \ethos\payments\getnet\process_getnet_webhook_payload( $payload );

	return new \WP_REST_Response( [
		'intent_id'  => $intent_id,
		'payment_id' => $payload['payment']['result']['payment_id'],
		'outcome'    => $outcome,
		'webhook'    => [
			'status' => $response->get_status(),
			'body'   => $response->get_data(),
		],
	], 200 );
}
