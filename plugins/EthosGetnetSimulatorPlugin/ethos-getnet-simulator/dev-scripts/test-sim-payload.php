<?php
/**
 * Standalone test for the Getnet simulator: payload builder fidelity and
 * fixture -> status shortcode rendering (no WordPress needed).
 *
 * Usage: php dev-scripts/test-sim-payload.php
 * Exit code 0 = all assertions passed.
 */

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Payment plugin location (sibling repo in this workspace). Override with
 * ETHOS_PAYMENT_PLUGIN_PATH when the layout differs.
 */
$payment_plugin = getenv( 'ETHOS_PAYMENT_PLUGIN_PATH' ) ?: '';

if ( '' === $payment_plugin ) {
	$candidates = [
		__DIR__ . '/../../../EthosPaymentIntegrationPlugin/ethos-payment-integration', // workspace: plugins/<wrapper>/<plugin>
		__DIR__ . '/../../../ethos-payment-integration', // plugin deployed directly in the plugins dir
	];

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate . '/includes/getnet/webhook.php' ) ) {
			$payment_plugin = $candidate;
			break;
		}
	}
}

if ( '' === $payment_plugin || ! file_exists( $payment_plugin . '/includes/getnet/webhook.php' ) ) {
	echo "FAIL: payment plugin not found — set ETHOS_PAYMENT_PLUGIN_PATH to ethos-payment-integration\n";
	exit( 1 );
}


// Minimal WP stubs (same approach as test-webhook-mapping.php).
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_string( $value ) ? trim( strip_tags( $value ) ) : $value;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, $decimals );
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = [] ) {
		return []; // fixtures use a fake project uuid -> no WP event pages
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		return '';
	}
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		return '';
	}
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

require $payment_plugin . '/includes/getnet/webhook.php';
require __DIR__ . '/../includes/payload.php';
require $payment_plugin . '/includes/getnet/shortcode.php';

use function ethos\getnetsim\build_sim_webhook_payload;
use function ethos\payments\getnet\map_getnet_webhook_to_participant;
use function ethos\payments\getnet\get_getnet_webhook_seen_key;
use function ethos\payments\getnet\render_getnet_payment_status_for_participant;

$failures = 0;
$tests    = 0;

function check( string $name, bool $condition ): void {
	global $failures, $tests;
	$tests++;
	if ( $condition ) {
		echo "PASS: {$name}\n";
	} else {
		$failures++;
		echo "FAIL: {$name}\n";
	}
}

$intent = [
	'intent_id' => 'sim_11111111-1111-1111-1111-111111111111',
	'order_id'  => '22222222-2222-2222-2222-222222222222',
	'amount'    => 15000,
];

// --- Payload builder: production mapping accepts simulator payloads ---

$credit = build_sim_webhook_payload( $intent, 'credit_authorized' );
check( 'builder returns payload', is_array( $credit ) );

$mapped = map_getnet_webhook_to_participant( $credit );
check( 'sim credit_authorized maps to Pago', $mapped['fut_set_statusoperacao'] === \ethos\payments\getnet\GETNET_STATUS_PAID );
check( 'sim amount comes from stored intent', $mapped['fut_mon_valorpago'] === 150.0 );
check( 'sim reference combines intent and payment ids', $mapped['fut_txt_referenciapagseguro'] === $intent['intent_id'] . '&' . $credit['payment']['result']['payment_id'] );
check( 'sim intent id propagates as order context', $credit['order_id'] === $intent['order_id'] );

$pix = build_sim_webhook_payload( $intent, 'pix_pending' );
check( 'sim pix_pending maps to Aguardando', map_getnet_webhook_to_participant( $pix )['fut_set_statusoperacao'] === \ethos\payments\getnet\GETNET_STATUS_AWAITING );
check( 'sim pix uses instant_payment method', $pix['payment']['method'] === 'instant_payment' );

$denied = build_sim_webhook_payload( $intent, 'credit_denied' );
check( 'sim denied keeps reference but no status', ! array_key_exists( 'fut_set_statusoperacao', map_getnet_webhook_to_participant( $denied ) ) );

$installments = build_sim_webhook_payload( $intent, 'credit_installments' );
check( 'sim installments propagate', $installments['payment']['installment']['number'] === 3 );

check( 'invalid outcome returns null', build_sim_webhook_payload( $intent, 'not_a_real_outcome' ) === null );

// --- Idempotency: fixed payment_id => same seen key (re-delivery) ---

$first  = build_sim_webhook_payload( $intent, 'credit_authorized', 'simpay_fixed' );
$second = build_sim_webhook_payload( $intent, 'credit_authorized', 'simpay_fixed' );
check( 're-delivery with same payment_id yields same seen key', get_getnet_webhook_seen_key( $first ) === get_getnet_webhook_seen_key( $second ) );
check( 'seen key is intent&payment', get_getnet_webhook_seen_key( $first ) === $intent['intent_id'] . '&simpay_fixed' );
check( 'fresh attempt yields different key', get_getnet_webhook_seen_key( build_sim_webhook_payload( $intent, 'credit_authorized' ) ) !== get_getnet_webhook_seen_key( $first ) );

// --- Fixtures -> render core (status shortcode states, no CRM) ---

$project = (object) [ 'Id' => '00000000-0000-0000-0000-00000000f1x7', 'Name' => 'Congresso Ethos 2026 (fixture)' ];

$fixture = function ( array $attributes ) {
	return (object) [ 'Attributes' => $attributes ];
};

$paid_html = render_getnet_payment_status_for_participant( $fixture( [
	'fut_set_statusoperacao' => 969830003,
	'fut_txt_nro_inscricao'  => '1044',
	'fut_lk_projeto'         => $project,
	'fut_mon_valorpago'      => 150.0,
] ) );
check( 'paid fixture renders success message', str_contains( $paid_html, 'form__message--success' ) );
check( 'paid fixture shows amount paid', str_contains( $paid_html, 'Amount paid' ) );
check( 'paid fixture shows registration summary', str_contains( $paid_html, 'payment-status__summary' ) );

$paid_no_amount = render_getnet_payment_status_for_participant( $fixture( [
	'fut_set_statusoperacao' => 969830003,
] ) );
check( 'paid without amount: success, no amount line', str_contains( $paid_no_amount, 'form__message--success' ) && ! str_contains( $paid_no_amount, 'Amount paid' ) );

$awaiting_html = render_getnet_payment_status_for_participant( $fixture( [
	'fut_set_statusoperacao' => (object) [ 'Value' => 969830001 ],
] ) );
check( 'awaiting fixture (OptionSetValue object) renders info', str_contains( $awaiting_html, 'form__message--info' ) );
check( 'awaiting fixture mentions pending confirmation', str_contains( $awaiting_html, 'pending' ) );

$pending_html = render_getnet_payment_status_for_participant( $fixture( [
	'fut_set_statusoperacao' => 969830000,
	'fut_txt_nro_inscricao'  => '1042',
	'fut_lk_projeto'         => $project,
] ) );
check( 'pending fixture renders info message', str_contains( $pending_html, 'form__message--info' ) );
check( 'pending fixture falls back to CRM project name', str_contains( $pending_html, 'Congresso Ethos 2026 (fixture)' ) );

$no_status_html = render_getnet_payment_status_for_participant( $fixture( [] ) );
check( 'no-status fixture renders not-confirmed message', str_contains( $no_status_html, 'form__message--info' ) );

$cancelled_html = render_getnet_payment_status_for_participant( $fixture( [
	'fut_set_statusoperacao' => 969830007,
] ) );
check( 'cancelled fixture renders error message', str_contains( $cancelled_html, 'form__message--error' ) );

echo "\n{$tests} tests, {$failures} failures\n";
exit( $failures > 0 ? 1 : 0 );
