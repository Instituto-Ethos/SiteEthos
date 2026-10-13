<?php

namespace ethos\getnetsim;

defined( 'ABSPATH' ) || exit;

function sim_register_admin_page(): void {
	add_management_page(
		'Getnet Simulator',
		'Getnet Simulator',
		'manage_options',
		'ethos-getnet-simulator',
		'ethos\getnetsim\render_sim_admin_page'
	);
}
add_action( 'admin_menu', 'ethos\getnetsim\sim_register_admin_page' );

/**
 * Mapa de labels de fut_set_statusoperacao para exibição no painel.
 */
function sim_get_status_labels(): array {
	return [
		969830000 => 'Pendente',
		969830001 => 'Aguardando pagamento',
		969830003 => 'Pago',
		969830007 => 'Cancelada',
		969830050 => 'Empenhado',
		969830051 => 'Negociado',
		969830052 => 'Pago diretamente',
	];
}

function sim_status_label( ?int $status ): string {
	$labels = sim_get_status_labels();

	if ( null === $status ) {
		return 'Sem status (0)';
	}

	return $labels[ $status ] ?? ( 'Desconhecido (' . $status . ')' );
}

function render_sim_admin_page(): void {
	$result = sim_admin_handle_post();

	$order    = isset( $_GET['p'] ) ? sanitize_text_field( wp_unslash( $_GET['p'] ) ) : '';
	$order    = isset( $_POST['order'] ) && '' === $order ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : $order;
	$loaded   = sim_admin_load_participant( $order );

	?>
	<div class="wrap">
		<h1>Getnet Simulator</h1>

		<?php sim_admin_render_banner(); ?>

		<?php if ( null !== $result ) : ?>
			<div class="notice notice-info"><pre style="white-space:pre-wrap"><?php echo esc_html( $result ); ?></pre></div>
		<?php endif; ?>

		<h2>Participante (fut_participante)</h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
			<input type="hidden" name="page" value="ethos-getnet-simulator">
			<p>
				<label for="ethos-getnet-sim-order">UUID do participante (order_id):</label><br>
				<input type="text" class="regular-text" id="ethos-getnet-sim-order" name="p" value="<?php echo esc_attr( $order ); ?>" placeholder="00000000-0000-0000-0000-000000000000">
				<button type="submit" class="button">Consultar</button>
			</p>
		</form>

		<?php if ( null !== $loaded ) : ?>
			<?php sim_admin_render_participant( $order, $loaded ); ?>
		<?php elseif ( '' !== $order ) : ?>
			<div class="notice notice-warning"><p>Participante não encontrado no CRM (ou integração Dynamics indisponível).</p></div>
		<?php endif; ?>

		<h2>Disparar desfecho simulado</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'ethos_getnet_sim_admin' ); ?>
			<input type="hidden" name="ethos_getnet_sim_action" value="outcome">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="ethos-getnet-sim-fire-order">order_id (UUID)</label></th>
					<td><input type="text" class="regular-text" id="ethos-getnet-sim-fire-order" name="order" required value="<?php echo esc_attr( $order ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ethos-getnet-sim-fire-outcome">Desfecho</label></th>
					<td>
						<select id="ethos-getnet-sim-fire-outcome" name="outcome">
							<?php foreach ( sim_admin_outcome_labels() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ethos-getnet-sim-fire-amount">Valor (R$)</label></th>
					<td><input type="number" step="0.01" min="0.01" id="ethos-getnet-sim-fire-amount" name="amount" value="<?php echo esc_attr( $loaded['default_amount'] ?? '100.00' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ethos-getnet-sim-fire-intent">intent_id <span style="font-weight:400">(opcional — idempotência)</span></label></th>
					<td><input type="text" class="regular-text" id="ethos-getnet-sim-fire-intent" name="intent_id" placeholder="sim_…"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ethos-getnet-sim-fire-payment">payment_id <span style="font-weight:400">(opcional — re-entrega)</span></label></th>
					<td><input type="text" class="regular-text" id="ethos-getnet-sim-fire-payment" name="payment_id" placeholder="simpay_…"></td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary">Disparar via process_getnet_webhook_payload()</button>
				<span class="description">Grava no CRM de sandbox — idempotência e máquina de transições aplicadas.</span>
			</p>
		</form>

		<h2>Intents simuladas recentes</h2>
		<?php sim_admin_render_recent_intents(); ?>

		<h2>Manutenção</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'ethos_getnet_sim_admin' ); ?>
			<input type="hidden" name="ethos_getnet_sim_action" value="clear_seen">
			<p>
				<button type="submit" class="button">Limpar seen-keys sim_*</button>
				<span class="description">Remove a idempotência das entregas simuladas — a re-entrega volta a ser processada.</span>
			</p>
		</form>

		<h2>Galeria de estados — shortcode de status</h2>
		<p class="description">
			Renderização de <code>render_getnet_payment_status_for_participant()</code> (núcleo do
			<code>[ethos-event-payment-status]</code>) com fixtures fabricadas — nenhum CRM envolvido.
			O botão “Complete seu pagamento” exige contato + evento reais: use a via do participante acima.
		</p>
		<?php sim_admin_render_gallery(); ?>
	</div>
	<?php
}

function sim_admin_outcome_labels(): array {
	return [
		'credit_authorized'   => 'Cartão autorizado (Pago)',
		'credit_installments' => 'Cartão autorizado 3x (Pago)',
		'debit_authorized'    => 'Débito autorizado (Pago)',
		'pix_pending'         => 'PIX pendente (Aguardando)',
		'pix_authorized'      => 'PIX confirmado tarde (Pago)',
		'boleto_pending'      => 'Boleto pendente (Aguardando)',
		'credit_denied'       => 'Cartão negado (mantém status)',
	];
}

/**
 * Processa POSTs do painel. Retorna texto de resultado ou null.
 */
function sim_admin_handle_post(): ?string {
	if ( empty( $_POST['ethos_getnet_sim_action'] ) ) {
		return null;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return 'Sem permissão.';
	}

	check_admin_referer( 'ethos_getnet_sim_admin' );

	$action = sanitize_text_field( wp_unslash( $_POST['ethos_getnet_sim_action'] ) );

	if ( 'clear_seen' === $action ) {
		$removed = clear_sim_seen_keys();

		return "seen-keys sim_* removidas: {$removed}";
	}

	if ( 'outcome' !== $action ) {
		return null;
	}

	if ( ! function_exists( '\ethos\payments\getnet\process_getnet_webhook_payload' ) ) {
		return 'ERRO: process_getnet_webhook_payload() indisponível — atualize o plugin Ethos Payment Integration.';
	}

	$order    = sanitize_text_field( wp_unslash( $_POST['order'] ?? '' ) );
	$outcome  = sanitize_text_field( wp_unslash( $_POST['outcome'] ?? '' ) );
	$amount   = floatval( wp_unslash( $_POST['amount'] ?? 100 ) );
	$intent   = sanitize_text_field( wp_unslash( $_POST['intent_id'] ?? '' ) );
	$payment  = sanitize_text_field( wp_unslash( $_POST['payment_id'] ?? '' ) );

	if ( '' === $order ) {
		return 'ERRO: informe o order_id (UUID do participante).';
	}

	$intent_data = [
		'intent_id' => '' !== $intent ? $intent : 'sim_' . wp_generate_uuid4(),
		'order_id'  => $order,
		'amount'    => (int) round( $amount * 100 ),
	];

	$payload = build_sim_webhook_payload( $intent_data, $outcome, '' !== $payment ? $payment : null );

	if ( null === $payload ) {
		return 'ERRO: desfecho inválido.';
	}

	$response = \ethos\payments\getnet\process_getnet_webhook_payload( $payload );

	return sprintf(
		"intent: %s\npayment: %s\nHTTP %d\n%s",
		$intent_data['intent_id'],
		$payload['payment']['result']['payment_id'],
		$response->get_status(),
		wp_json_encode( $response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
	);
}

/**
 * Carrega o participante do CRM (fresco) para exibição. null = não achado.
 */
function sim_admin_load_participant( string $order ): ?array {
	if ( '' === $order || ! function_exists( '\hacklabr\get_crm_entity_by_id' ) ) {
		return null;
	}

	$participant = \hacklabr\get_crm_entity_by_id( 'fut_participante', $order, [ 'cache' => false ] );

	if ( empty( $participant ) ) {
		return null;
	}

	$attributes = is_array( $participant->Attributes ?? null ) ? $participant->Attributes : [];

	$status = $attributes['fut_set_statusoperacao'] ?? null;

	if ( is_object( $status ) && isset( $status->Value ) ) {
		$status = (int) $status->Value;
	} elseif ( is_numeric( $status ) ) {
		$status = (int) $status;
	} else {
		$status = null;
	}

	return [
		'status'         => $status,
		'reference'      => (string) ( $attributes['fut_txt_referenciapagseguro'] ?? '' ),
		'amount_paid'    => $attributes['fut_mon_valorpago'] ?? null,
		'amount_net'     => $attributes['fut_mon_valorliquido'] ?? null,
		'registration'   => (string) ( $attributes['fut_txt_nro_inscricao'] ?? '' ),
		'default_amount' => is_numeric( $attributes['fut_mon_valorpago'] ?? null ) && (float) $attributes['fut_mon_valorpago'] > 0
			? number_format( (float) $attributes['fut_mon_valorpago'], 2, '.', '' )
			: '100.00',
	];
}

function sim_admin_render_participant( string $order, array $loaded ): void {
	?>
	<table class="widefat striped" style="max-width:860px">
		<tbody>
			<tr><td><strong>Status</strong></td><td><?php echo esc_html( sim_status_label( $loaded['status'] ) ); ?> <code><?php echo esc_html( (string) $loaded['status'] ); ?></code></td></tr>
			<tr><td><strong>Nº inscrição</strong></td><td><?php echo esc_html( $loaded['registration'] ?: '—' ); ?></td></tr>
			<tr><td><strong>Referência de pagamento</strong></td><td><code><?php echo esc_html( $loaded['reference'] ?: '—' ); ?></code></td></tr>
			<tr><td><strong>Valor pago / líquido</strong></td><td><?php echo esc_html( (string) $loaded['amount_paid'] ); ?> / <?php echo esc_html( (string) $loaded['amount_net'] ); ?></td></tr>
			<tr><td><strong>Página de status</strong></td><td><a href="<?php echo esc_url( get_sim_status_url( $order ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_sim_status_url( $order ) ); ?></a></td></tr>
		</tbody>
	</table>
	<?php
}

function sim_admin_render_banner(): void {
	$processor   = function_exists( '\ethos\payments\getnet\process_getnet_webhook_payload' );
	$environment = \ethos\payments\get_payment_integration_option( 'getnet', 'getnet_environment', 'sandbox' );
	$webhook_url = rest_url( 'ethos-payments/v1/getnet/webhook' );
	$has_creds   = '' !== \ethos\payments\get_payment_integration_option( 'getnet', 'getnet_webhook_user' );
	?>
	<div class="notice notice-warning">
		<p>
			<strong>Simulador ATIVO</strong> — ambiente <code><?php echo esc_html( $environment ); ?></code>.
			Nenhuma requisição chega à Getnet. Processador compartilhado:
			<?php echo $processor ? '<span style="color:#00a32a">disponível</span>' : '<span style="color:#d63638">INDISPONÍVEL (atualize o plugin de pagamentos)</span>'; ?>.
		</p>
		<p>
			Webhook real: <code><?php echo esc_html( $webhook_url ); ?></code>
			<?php if ( $has_creds ) : ?>
				— credenciais Basic geradas (veja o curl no README do simulador).
			<?php else : ?>
				— <strong>sem credenciais Basic</strong> (rota devolve 500 até o bootstrap gerá-las).
			<?php endif; ?>
		</p>
	</div>
	<?php
}

function sim_admin_render_recent_intents(): void {
	$intents = array_reverse( get_sim_intents(), true );
	$intents = array_slice( $intents, 0, 10, true );

	if ( empty( $intents ) ) {
		echo '<p class="description">Nenhuma intent simulada ainda — faça uma inscrição em um evento pago com o simulador ativo.</p>';
		return;
	}

	$rows = '';

	foreach ( $intents as $intent ) {
		$order = (string) ( $intent['order_id'] ?? '' );

		$rows .= sprintf(
			'<tr>
				<td>%s</td>
				<td><code>%s</code></td>
				<td><a href="%s"><code>%s</code></a></td>
				<td>R$ %s</td>
				<td><a class="button button-small" href="%s">abrir no painel</a> <a class="button button-small" href="%s" target="_blank" rel="noopener">status</a></td>
			</tr>',
			esc_html( gmdate( 'd/m H:i', intval( $intent['created_at'] ?? 0 ) ) ),
			esc_html( (string) ( $intent['intent_id'] ?? '' ) ),
			esc_url( add_query_arg( 'p', rawurlencode( $order ) ) ),
			esc_html( $order ),
			esc_html( number_format_i18n( intval( $intent['amount'] ?? 0 ) / 100, 2 ) ),
			esc_url( admin_url( 'tools.php?page=ethos-getnet-simulator&p=' . rawurlencode( $order ) ) ),
			esc_url( get_sim_status_url( $order ) )
		);
	}

	printf(
		'<table class="widefat striped" style="max-width:1000px">
			<thead><tr><th>Criada (UTC)</th><th>Intent</th><th>Order</th><th>Valor</th><th>Ações</th></tr></thead>
			<tbody>%s</tbody>
		</table>',
		$rows // phpcs:ignore WordPress.Security.EscapeOutput -- escapado por célula acima
	);
}

/**
 * Fixtures fabricadas — mesma forma de acesso da entidade CRM
 * ($participant->Attributes[...], OptionSetValue como {Value}).
 */
function sim_get_fixtures(): array {
	$project = (object) [
		'Id'   => '00000000-0000-0000-0000-00000000f1x7',
		'Name' => 'Congresso Ethos 2026 (fixture)',
	];

	return [
		'Pendente — sem status (novo)' => [
			'description' => 'Inscrição recém-criada em evento pago: Attributes sem status.',
			'participant' => (object) [ 'Attributes' => [] ],
		],
		'Pendente (0) com resumo' => [
			'description' => 'Status Pendente com nº de inscrição e projeto do CRM (sem página WP → nome do projeto).',
			'participant' => (object) [ 'Attributes' => [
				'fut_set_statusoperacao' => 969830000,
				'fut_txt_nro_inscricao'  => '1042',
				'fut_lk_projeto'         => $project,
			] ],
		],
		'Aguardando pagamento (1)' => [
			'description' => 'OptionSetValue em forma de objeto {Value} — exercita get_getnet_option_set_value().',
			'participant' => (object) [ 'Attributes' => [
				'fut_set_statusoperacao' => (object) [ 'Value' => 969830001 ],
				'fut_txt_nro_inscricao'  => '1043',
				'fut_lk_projeto'         => $project,
			] ],
		],
		'Pago (3) com valor' => [
			'description' => 'Webhook de autorização aplicado — sucesso + valor pago.',
			'participant' => (object) [ 'Attributes' => [
				'fut_set_statusoperacao' => 969830003,
				'fut_txt_nro_inscricao'  => '1044',
				'fut_lk_projeto'         => $project,
				'fut_mon_valorpago'      => 150.0,
			] ],
		],
		'Pago (3) sem valor' => [
			'description' => 'Pago sem fut_mon_valorpago — mensagem de sucesso, sem linha de valor.',
			'participant' => (object) [ 'Attributes' => [
				'fut_set_statusoperacao' => 969830003,
			] ],
		],
		'Cancelada (7)' => [
			'description' => 'Inscrição cancelada — mensagem de erro; webhook nunca ressuscita.',
			'participant' => (object) [ 'Attributes' => [
				'fut_set_statusoperacao' => 969830007,
				'fut_lk_projeto'         => $project,
			] ],
		],
	];
}

function sim_admin_render_gallery(): void {
	if ( ! function_exists( '\ethos\payments\getnet\render_getnet_payment_status_for_participant' ) ) {
		echo '<div class="notice notice-error"><p>render_getnet_payment_status_for_participant() indisponível — atualize o plugin Ethos Payment Integration.</p></div>';
		return;
	}

	foreach ( sim_get_fixtures() as $title => $fixture ) {
		$html = \ethos\payments\getnet\render_getnet_payment_status_for_participant( $fixture['participant'] );

		printf(
			'<div class="ethos-getnet-sim-fixture card" style="max-width:860px;padding:12px;margin:12px 0">
				<h3 style="margin-top:0">%s</h3>
				<p class="description">%s</p>
				<div style="border:1px dashed #c3c4c7;padding:12px">%s</div>
			</div>',
			esc_html( $title ),
			esc_html( $fixture['description'] ),
			$html // phpcs:ignore WordPress.Security.EscapeOutput — renderização do shortcode real (escapa internamente)
		);
	}
}
