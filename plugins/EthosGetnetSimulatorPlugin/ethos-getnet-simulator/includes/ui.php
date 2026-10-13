<?php

namespace ethos\getnetsim;

defined( 'ABSPATH' ) || exit;

/**
 * Substitui o botão do lightbox real pelo checkout simulado (filtro
 * 'ethos_payments/getnet_checkout_button' do render_getnet_lightbox_button).
 * Só intercepta intents criadas pelo simulador (prefixo sim_) — intents
 * reais seguem para o loader da Getnet.
 *
 * O modal é server-rendered oculto junto ao botão; o JS apenas alterna
 * visibilidade e dispara a rota de outcome. Valor e order vêm do store
 * server-side — nunca do browser.
 */
function render_sim_checkout_button( $replacement, string $payment_intent_id, string $button_label, string $button_class ) {
	if ( null !== $replacement || ! str_starts_with( $payment_intent_id, 'sim_' ) ) {
		return $replacement;
	}

	$intent = get_sim_intent( $payment_intent_id );

	sim_enqueue_checkout_assets();

	if ( null === $intent ) {
		// Intent simulada perdida (store podado?): botão desabilitado + aviso,
		// em vez de abrir um lightbox real com id inexistente.
		return sprintf(
			'<button type="button" class="button button--solid %s" disabled>%s</button><p class="form__message form__message--error">%s</p>',
			esc_attr( $button_class ),
			esc_html( $button_label ),
			'Simulador: intent não encontrada no store (expirada?). Gere um novo checkout.'
		);
	}

	$amount_brl = number_format_i18n( intval( $intent['amount'] ) / 100, 2 );
	$order_id   = (string) $intent['order_id'];

	$outcomes = [
		'credit_authorized' => 'Aprovar cartão de crédito',
		'credit_denied'     => 'Negar cartão de crédito',
		'pix_pending'       => 'PIX registrado (pendente)',
		'pix_authorized'    => 'PIX confirmado (tarde)',
		'boleto_pending'    => 'Boleto registrado (pendente)',
	];

	$outcome_buttons = '';

	foreach ( $outcomes as $outcome => $label ) {
		$outcome_buttons .= sprintf(
			'<button type="button" class="button button--solid ethos-getnet-sim-outcome" data-outcome="%s">%s</button>',
			esc_attr( $outcome ),
			esc_html( $label )
		);
	}

	$modal_id = 'ethos-getnet-sim-modal-' . sanitize_html_class( $payment_intent_id );

	return sprintf(
		'<button type="button" class="button button--solid %1$s" data-ethos-getnet-sim-trigger="%2$s" aria-controls="%7$s">%3$s</button>
		<div class="ethos-getnet-sim-backdrop" id="%7$s" hidden data-ethos-getnet-sim-modal data-intent="%2$s" data-token="%8$s" data-last-outcome="" data-last-payment="">
			<div class="ethos-getnet-sim-modal" role="dialog" aria-modal="true" aria-label="Checkout simulado">
				<p class="ethos-getnet-sim-modal__title">Simulador de pagamento <span class="ethos-getnet-sim-badge">Getnet SIM</span></p>
				<p class="ethos-getnet-sim-modal__meta">%4$s · inscrição <code>%5$s</code></p>
				<div class="ethos-getnet-sim-modal__actions">%6$s</div>
				<button type="button" class="button ethos-getnet-sim-redeliver" hidden>Reenviar mesmo pagamento (idempotência)</button>
				<a class="button ethos-getnet-sim-status-link" href="%9$s" hidden>Ver página de status</a>
				<pre class="ethos-getnet-sim-result" hidden></pre>
				<button type="button" class="button ethos-getnet-sim-close">Fechar</button>
			</div>
		</div>',
		esc_attr( $button_class ),
		esc_attr( $payment_intent_id ),
		esc_html( $button_label ),
		esc_html( 'R$ ' . $amount_brl ),
		esc_html( $order_id ),
		$outcome_buttons,
		esc_attr( $modal_id ),
		esc_attr( (string) ( $intent['token'] ?? '' ) ),
		esc_url( get_sim_status_url( $order_id ) )
	);
}
add_filter( 'ethos_payments/getnet_checkout_button', 'ethos\getnetsim\render_sim_checkout_button', 10, 4 );

function sim_register_checkout_assets(): void {
	wp_register_style(
		'ethos-getnet-sim',
		plugins_url( 'assets/css/sim-checkout.css', ETHOS_GETNET_SIM_FILE ),
		[],
		ETHOS_GETNET_SIM_VERSION
	);

	wp_register_script(
		'ethos-getnet-sim',
		plugins_url( 'assets/js/sim-checkout.js', ETHOS_GETNET_SIM_FILE ),
		[],
		ETHOS_GETNET_SIM_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ethos\getnetsim\sim_register_checkout_assets' );

function sim_enqueue_checkout_assets(): void {
	wp_enqueue_style( 'ethos-getnet-sim' );
	wp_enqueue_script( 'ethos-getnet-sim' );

	wp_localize_script( 'ethos-getnet-sim', 'ethosGetnetSim', [
		'restUrl' => esc_url_raw( rest_url( 'ethos-getnet-sim/v1/outcome' ) ),
	] );
}
