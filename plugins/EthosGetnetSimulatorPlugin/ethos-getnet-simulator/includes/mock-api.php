<?php

namespace ethos\getnetsim;

defined( 'ABSPATH' ) || exit;

/**
 * Intercepts every Getnet web-checkout API call via the
 * 'ethos_payments/pre_getnet_request' filter (getnet_request()) — no
 * credentials, no HTTP. Fakes the endpoints used by the paid events flow:
 *
 *   POST /dpy/web-checkout/v1/payment-intent           → cria intent sim_<uuid>
 *   GET  /dpy/web-checkout/v1/sellers/{id}             → meios habilitados
 *   PUT  /dpy/web-checkout/v1/{technical,business}-…   → sucesso canônico
 *
 * Unknown paths return an explicit WP_Error (better visible than a silent
 * fallthrough to the real API without credentials).
 */
function mock_getnet_request( $pre, string $method, string $path, ?array $body ) {
	if ( null !== $pre ) {
		return $pre;
	}

	if ( 'POST' === $method && '/dpy/web-checkout/v1/payment-intent' === $path ) {
		return mock_create_payment_intent( $body ?? [] );
	}

	$sellers_prefix = '/dpy/web-checkout/v1/sellers/';

	if ( 'GET' === $method && str_starts_with( $path, $sellers_prefix ) ) {
		return [
			'code' => 200,
			'body' => [
				'seller_id'       => substr( $path, strlen( $sellers_prefix ) ),
				'payment_methods' => [
					'credit'          => [ 'enabled' => true, 'max_installments' => 12 ],
					'debit'           => [ 'enabled' => true ],
					'bankslip'        => [ 'enabled' => true ],
					'instant_payment' => [ 'enabled' => true ],
				],
			],
		];
	}

	$config_prefixes = [
		'/dpy/web-checkout/v1/technical-configurations/',
		'/dpy/web-checkout/v1/business-configurations/',
	];

	if ( 'PUT' === $method ) {
		foreach ( $config_prefixes as $prefix ) {
			if ( str_starts_with( $path, $prefix ) ) {
				return [
					'code' => 200,
					'body' => [ 'status' => 'OK', 'simulated' => true ],
				];
			}
		}
	}

	return new \WP_Error(
		'getnet_sim_unsupported',
		sprintf( 'Getnet Simulator: no mock for %s %s', $method, $path )
	);
}
add_filter( 'ethos_payments/pre_getnet_request', 'ethos\getnetsim\mock_getnet_request', 10, 4 );

/**
 * Fabrica a resposta do POST /payment-intent: id sim_<uuid> + store
 * server-side (order_id e amount em centavos — o modal e a rota de outcome
 * leem daqui; o valor NUNCA vem do browser).
 *
 * @return array{code:int,body:array}
 */
function mock_create_payment_intent( array $intent_data ): array {
	$intent_id = 'sim_' . wp_generate_uuid4();

	store_sim_intent( [
		'intent_id'  => $intent_id,
		'order_id'   => (string) ( $intent_data['order_id'] ?? '' ),
		'amount'     => intval( $intent_data['payment']['amount'] ?? 0 ),
		'title'      => (string) ( $intent_data['product'][0]['title'] ?? '' ),
		'expires_at' => (string) ( $intent_data['expires_at'] ?? '' ),
		'created_at' => time(),
	] );

	return [
		'code' => 201,
		'body' => [
			'payment_intent_id' => $intent_id,
			'status'            => 'PENDING',
			'expires_at'        => (string) ( $intent_data['expires_at'] ?? '' ),
		],
	];
}

/**
 * Store de intents simuladas (option não-autoload, podada >35d — expires_at
 * máximo do gateway é 31d — e limitada a 200 entradas).
 */
function get_sim_intents(): array {
	$intents = get_option( 'ethos_getnet_sim_intents', [] );

	return is_array( $intents ) ? $intents : [];
}

function store_sim_intent( array $intent ): void {
	$intents = get_sim_intents();

	$intents[ $intent['intent_id'] ] = $intent;

	$now = time();

	foreach ( $intents as $id => $data ) {
		if ( ( $now - intval( $data['created_at'] ?? 0 ) ) > 35 * DAY_IN_SECONDS ) {
			unset( $intents[ $id ] );
		}
	}

	if ( count( $intents ) > 200 ) {
		$intents = array_slice( $intents, -200, null, true );
	}

	update_option( 'ethos_getnet_sim_intents', $intents, false );
}

function get_sim_intent( string $intent_id ): ?array {
	$intent = get_sim_intents()[ $intent_id ] ?? null;

	return is_array( $intent ) ? $intent : null;
}

/**
 * Remove apenas as seen-keys de intents simuladas (prefixo sim_) do option
 * de idempotência do webhook — permite "reprocessar" uma entrega no dev.
 *
 * @return int Número de chaves removidas.
 */
function clear_sim_seen_keys(): int {
	$seen = get_option( 'ethos_getnet_webhook_seen', [] );

	if ( ! is_array( $seen ) ) {
		return 0;
	}

	$removed = 0;

	foreach ( $seen as $key => $timestamp ) {
		if ( str_starts_with( (string) $key, 'sim_' ) ) {
			unset( $seen[ $key ] );
			$removed++;
		}
	}

	if ( $removed > 0 ) {
		update_option( 'ethos_getnet_webhook_seen', $seen, false );
	}

	return $removed;
}

/**
 * URL da página de status para um order_id (mesma composição do checkout
 * real: getnet_status_url + ?order=).
 */
function get_sim_status_url( string $order_id ): string {
	$status_url = \ethos\payments\get_payment_integration_option( 'getnet', 'getnet_status_url' );

	if ( '' === $status_url ) {
		$status_url = home_url( '/' );
	}

	return add_query_arg( 'order', rawurlencode( $order_id ), $status_url );
}
