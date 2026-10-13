<?php

namespace ethos\getnetsim;

defined( 'ABSPATH' ) || exit;

/**
 * Catálogo de desfechos simulados (mesmos cenários do `wp getnet
 * simulate-webhook`). 'method'/'status' espelham o formato real do payload
 * de notificação do web-checkout da Getnet.
 *
 * @return array<string, array{method:string,status:string,installments:int}>
 */
function get_sim_outcomes(): array {
	return [
		'credit_authorized'   => [ 'method' => 'credit',          'status' => 'Authorized', 'installments' => 1 ],
		'credit_installments' => [ 'method' => 'credit',          'status' => 'Authorized', 'installments' => 3 ],
		'debit_authorized'    => [ 'method' => 'debit',           'status' => 'Authorized', 'installments' => 1 ],
		'pix_pending'         => [ 'method' => 'instant_payment', 'status' => 'Pending',    'installments' => 0 ],
		'pix_authorized'      => [ 'method' => 'instant_payment', 'status' => 'Authorized', 'installments' => 0 ],
		'boleto_pending'      => [ 'method' => 'bankslip',        'status' => 'Pending',    'installments' => 0 ],
		'credit_denied'       => [ 'method' => 'credit',          'status' => 'Denied',     'installments' => 0 ],
	];
}

/**
 * Monta um payload de webhook fiel ao contrato da Getnet a partir da intent
 * SIMULADA guardada server-side. O amount vem SEMPRE da intent — nunca do
 * request. O payment_id é novo a cada chamada (mesma semântica do gateway:
 * uma intent pode gerar várias tentativas), exceto quando $payment_id é
 * informado (re-entrega deliberada para teste de idempotência).
 *
 * @param array      $intent     Intent simulada (mock_create_payment_intent / painel).
 * @param string     $outcome    Chave de get_sim_outcomes().
 * @param string|null $payment_id Id fixo para re-entrega (opcional).
 * @return array|null null = outcome inválido.
 */
function build_sim_webhook_payload( array $intent, string $outcome, ?string $payment_id = null ): ?array {
	$outcomes = get_sim_outcomes();

	if ( ! isset( $outcomes[ $outcome ] ) ) {
		return null;
	}

	$config = $outcomes[ $outcome ];

	$payload = [
		'payment_intent_id' => (string) ( $intent['intent_id'] ?? '' ),
		'checkout_id'       => 'simco_' . wp_generate_uuid4(),
		'order_id'          => (string) ( $intent['order_id'] ?? '' ),
		'mode'              => 'instant',
		'payment'           => [
			'method'   => $config['method'],
			'amount'   => intval( $intent['amount'] ?? 0 ),
			'currency' => 'BRL',
			'result'   => [
				'payment_id'           => ( $payment_id !== null && '' !== $payment_id )
					? $payment_id
					: 'simpay_' . wp_generate_uuid4(),
				'status'               => $config['status'],
				'authorization_code'   => '999999',
				'transaction_datetime' => gmdate( 'c' ),
			],
		],
		'created_at' => gmdate( 'c' ),
		'updated_at' => gmdate( 'c' ),
	];

	if ( $config['installments'] > 0 ) {
		$payload['payment']['installment'] = [
			'number' => $config['installments'],
			'type'   => 'no_interest',
		];
	}

	return $payload;
}
