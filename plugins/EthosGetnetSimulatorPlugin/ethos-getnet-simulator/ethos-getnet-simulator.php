<?php
/**
 * Plugin Name:       Ethos Getnet Simulator
 * Description:       Dev tool — previews the paid events flow (checkout, webhook and payment status shortcode) without Getnet credentials. Deve permanecer DESATIVADO em produção.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Hacklab
 * Author URI:        https://hacklab.com.br/
 * License:           GPL v2 or later
 * Text Domain:       ethos-getnet-sim
 */

defined( 'ABSPATH' ) || exit;

define( 'ETHOS_GETNET_SIM_VERSION', '0.1.0' );
define( 'ETHOS_GETNET_SIM_FILE', __FILE__ );
define( 'ETHOS_GETNET_SIM_PATH', plugin_dir_path( __FILE__ ) );

/**
 * O simulador pode operar? Exige o plugin de pagamentos carregado e o
 * ambiente Getnet DIFERENTE de produção. Todo hook/registro consulta esta
 * função — em produção o simulador é inerte (e se auto-desativa via admin).
 */
function ethos_getnet_sim_active(): bool {
	if ( ! function_exists( '\ethos\payments\get_payment_integration_option' ) ) {
		return false;
	}

	$environment = \ethos\payments\get_payment_integration_option( 'getnet', 'getnet_environment', 'sandbox' );

	return 'production' !== $environment;
}

/**
 * Carrega os módulos do simulador apenas quando permitido. Em production
 * nada é carregado: nenhum filtro é registrado, nenhuma rota existe.
 */
function ethos_getnet_sim_bootstrap(): void {
	if ( ! ethos_getnet_sim_active() ) {
		return;
	}

	require_once ETHOS_GETNET_SIM_PATH . 'includes/mock-api.php';
	require_once ETHOS_GETNET_SIM_PATH . 'includes/payload.php';
	require_once ETHOS_GETNET_SIM_PATH . 'includes/ui.php';
	require_once ETHOS_GETNET_SIM_PATH . 'includes/rest.php';
	require_once ETHOS_GETNET_SIM_PATH . 'includes/admin.php';

	// Credenciais de webhook (Basic auth): garante que a rota REAL do
	// webhook responda (sem isso ela devolve 500 por falta de user/password)
	// e permite testá-la via curl — sem precisar da API da Getnet.
	if ( function_exists( '\ethos\payments\getnet\ensure_getnet_webhook_credentials' ) ) {
		\ethos\payments\getnet\ensure_getnet_webhook_credentials();
	}
}
add_action( 'plugins_loaded', 'ethos_getnet_sim_bootstrap', 20 );

/**
 * Ativação: recusa se o plugin de pagamentos não estiver ativo ou se o
 * ambiente Getnet for produção.
 */
function ethos_getnet_sim_activate(): void {
	if ( ! function_exists( '\ethos\payments\get_payment_integration_option' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'The Getnet Simulator requires the Ethos Payment Integration plugin to be active.', 'ethos-getnet-sim' ) );
	}

	if ( ! ethos_getnet_sim_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'The Getnet Simulator cannot be used while the Getnet environment is set to production.', 'ethos-getnet-sim' ) );
	}
}
register_activation_hook( __FILE__, 'ethos_getnet_sim_activate' );

/**
 * Guarda de runtime: se o ambiente virar produção com o simulador ativo,
 * auto-desativa (contexto admin — deactivate_plugins só existe aí) e avisa.
 * Em requests não-admin o simulador já é inerte (ethos_getnet_sim_active()).
 */
function ethos_getnet_sim_guard_production(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$is_production = function_exists( '\ethos\payments\get_payment_integration_option' )
		&& \ethos\payments\get_payment_integration_option( 'getnet', 'getnet_environment', 'sandbox' ) === 'production';

	if ( ! $is_production ) {
		return;
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );

	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Getnet Simulator deactivated: the Getnet environment is set to production.', 'ethos-getnet-sim' )
		);
	} );
}
add_action( 'admin_init', 'ethos_getnet_sim_guard_production' );

/**
 * Aviso persistente enquanto o simulador estiver ativo (lembrete visual de
 * que pagamentos são simulados) + erro se o plugin de pagamentos sumiu.
 */
function ethos_getnet_sim_admin_notices(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$payment_plugin_loaded = function_exists( '\ethos\payments\get_payment_integration_option' );

	if ( ! $payment_plugin_loaded ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Getnet Simulator is inactive: the Ethos Payment Integration plugin is not active.', 'ethos-getnet-sim' )
		);
		return;
	}

	if ( ethos_getnet_sim_active() ) {
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Getnet SIMULATOR active.', 'ethos-getnet-sim' ),
			esc_html__( 'Payments are simulated — no request reaches Getnet. Do not use in production.', 'ethos-getnet-sim' )
		);
	}
}
add_action( 'admin_notices', 'ethos_getnet_sim_admin_notices' );
