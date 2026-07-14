<?php
/**
 * Source-only smoke for scoped permission filters and debug payload hardening.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

class WP_REST_Request {
	private string $route;

	public function __construct( string $route ) {
		$this->route = $route;
	}

	public function get_route(): string {
		return $this->route;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ): bool {
		return 'manage_options' === $capability ? false : false;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		$GLOBALS['npcink_toolbox_security_smoke_filters'][] = array_merge( array( $hook_name, $value ), $args );

		if ( 'npcink_toolbox_rest_permission' === $hook_name ) {
			$scope = (string) ( $args[1] ?? '' );
			return 'cap.toolbox.knowledge.search' === $scope;
		}

		if ( 'npcink_toolbox_ability_permission' === $hook_name ) {
			$scope = (string) ( $args[1] ?? '' );
			return 'cap.toolbox.image_source' === $scope;
		}

		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! defined( 'NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES' ) ) {
	define( 'NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES', true );
}

$root = dirname( __DIR__ );
require_once $root . '/includes/Plugin.php';
require_once $root . '/includes/Settings.php';
require_once $root . '/includes/Provider_Client.php';
require_once $root . '/includes/Rest_Controller.php';
require_once $root . '/includes/Abilities.php';
require_once $root . '/includes/Editor_Content_Support.php';

$fail = static function ( string $message ): void {
	fwrite( STDERR, '[fail] ' . $message . "\n" );
	exit( 1 );
};

$assert = static function ( bool $condition, string $message ) use ( $fail ): void {
	if ( ! $condition ) {
		$fail( $message );
	}

	echo '[ok] ' . $message . "\n";
};

$rest_controller = ( new ReflectionClass( \Npcink_Toolbox\Rest_Controller::class ) )->newInstanceWithoutConstructor();
$route_scope     = new ReflectionMethod( \Npcink_Toolbox\Rest_Controller::class, 'rest_route_scope' );
$route_scope->setAccessible( true );
$permission = new ReflectionMethod( \Npcink_Toolbox\Rest_Controller::class, 'permission' );
$permission->setAccessible( true );

$assert( 'cap.toolbox.knowledge.search' === $route_scope->invoke( $rest_controller, '/site-knowledge/search' ), 'Site Knowledge search maps to the knowledge search scope.' );
$assert( 'cap.toolbox.nightly_inspection' === $route_scope->invoke( $rest_controller, '/nightly-inspection/cloud-batch/run_abc/result' ), 'Nightly dynamic result route maps to the nightly scope.' );
$assert( 'cap.toolbox.admin' === $route_scope->invoke( $rest_controller, '/unexpected-route' ), 'Unknown REST route falls back to the admin scope.' );

$allowed = $permission->invoke( $rest_controller, new WP_REST_Request( '/npcink-toolbox/v1/site-knowledge/search' ) );
$denied  = $permission->invoke( $rest_controller, new WP_REST_Request( '/npcink-toolbox/v1/status' ) );
$assert( true === $allowed, 'REST host filter can grant the matching scoped route.' );
$assert( false === $denied, 'REST host filter does not grant a mismatched scoped route.' );

$rest_filter_call = $GLOBALS['npcink_toolbox_security_smoke_filters'][0] ?? array();
$assert(
	'npcink_toolbox_rest_permission' === (string) ( $rest_filter_call[0] ?? '' )
	&& $rest_filter_call[2] instanceof WP_REST_Request
	&& 'cap.toolbox.knowledge.search' === (string) ( $rest_filter_call[3] ?? '' )
	&& '/site-knowledge/search' === (string) ( $rest_filter_call[4] ?? '' ),
	'REST permission filter receives request, required scope, and normalized route.'
);

$abilities          = ( new ReflectionClass( \Npcink_Toolbox\Abilities::class ) )->newInstanceWithoutConstructor();
$ability_permission = new ReflectionMethod( \Npcink_Toolbox\Abilities::class, 'can_execute_ability' );
$ability_permission->setAccessible( true );
$assert( true === $ability_permission->invoke( $abilities, 'npcink-toolbox/search-image-source', 'cap.toolbox.image_source' ), 'Ability host filter can grant the matching ability scope.' );
$assert( false === $ability_permission->invoke( $abilities, 'npcink-toolbox/request-site-knowledge-sync', 'cap.toolbox.knowledge.sync' ), 'Ability host filter denies a mismatched ability scope.' );

$ability_filter_call = null;
foreach ( $GLOBALS['npcink_toolbox_security_smoke_filters'] as $filter_call ) {
	if ( 'npcink_toolbox_ability_permission' === (string) ( $filter_call[0] ?? '' ) ) {
		$ability_filter_call = $filter_call;
		break;
	}
}
$assert(
	is_array( $ability_filter_call )
	&& 'npcink-toolbox/search-image-source' === (string) ( $ability_filter_call[2] ?? '' )
	&& 'cap.toolbox.image_source' === (string) ( $ability_filter_call[3] ?? '' ),
	'Ability permission filter receives ability id and required scope.'
);

$settings     = new \Npcink_Toolbox\Settings();
$provider     = new \Npcink_Toolbox\Provider_Client( $settings );
$with_raw     = new ReflectionMethod( \Npcink_Toolbox\Provider_Client::class, 'with_optional_raw' );
$sanitize_raw = new ReflectionMethod( \Npcink_Toolbox\Provider_Client::class, 'sanitize_debug_payload' );
$with_raw->setAccessible( true );
$sanitize_raw->setAccessible( true );

$payload = $with_raw->invoke(
	$provider,
	array( 'status' => 'ok' ),
	array( 'message' => 'Bearer sk-testsecret1234567890' )
);
$assert( ! array_key_exists( 'raw', $payload ), 'Raw payloads are force-disabled by NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES.' );
$assert( false === $settings->raw_responses_enabled(), 'The centralized settings policy honors the raw-response kill switch.' );

$editor_diagnostics = new ReflectionMethod( \Npcink_Toolbox\Editor_Content_Support::class, 'show_runtime_diagnostics' );
$editor_diagnostics->setAccessible( true );
$editor_support = new \Npcink_Toolbox\Editor_Content_Support( $settings );
$assert( false === $editor_diagnostics->invoke( $editor_support ), 'Editor diagnostics honor the centralized raw-response kill switch.' );

$redacted = $sanitize_raw->invoke(
	$provider,
	array(
		'note'       => 'Authorization header Bearer abcdefghijklmnopqrstuvwxyz123456',
		'diagnostic' => 'api_key=sk-abcdefghijklmnopqrstuvwxyz',
		'nested'     => array(
			'jwt' => 'aaaaaaaaaaaaaaaaaaaaaaaa.bbbbbbbbbbbbbbbb.cccccccccccccccc',
		),
	)
);
$encoded = wp_json_encode( $redacted );
$assert( is_string( $encoded ) && false === strpos( $encoded, 'abcdefghijklmnopqrstuvwxyz123456' ), 'Debug payload redacts bearer-shaped secrets in non-sensitive fields.' );
$assert( is_string( $encoded ) && false === strpos( $encoded, 'sk-abcdefghijklmnopqrstuvwxyz' ), 'Debug payload redacts API-key-shaped secrets in non-sensitive fields.' );
$assert( is_string( $encoded ) && false === strpos( $encoded, 'aaaaaaaaaaaaaaaaaaaaaaaa.bbbbbbbbbbbbbbbb.cccccccccccccccc' ), 'Debug payload redacts JWT-shaped strings.' );

$deep_redacted = $sanitize_raw->invoke(
	$provider,
	array(
		'level_1' => array(
			'level_2' => array(
				'level_3' => array(
					'level_4' => array(
						'level_5' => array(
							'message' => 'Bearer abcdefghijklmnopqrstuvwxyz123456',
						),
					),
				),
			),
		),
	)
);
$deep_encoded = wp_json_encode( $deep_redacted );
$assert( is_string( $deep_encoded ) && false === strpos( $deep_encoded, 'abcdefghijklmnopqrstuvwxyz123456' ), 'Debug payload redacts secrets at the maximum nesting depth.' );

$classify = new ReflectionMethod( \Npcink_Toolbox\Provider_Client::class, 'runtime_payload_data_classification' );
$storage  = new ReflectionMethod( \Npcink_Toolbox\Provider_Client::class, 'runtime_payload_storage_mode' );
$classify->setAccessible( true );
$storage->setAccessible( true );
$classification = $classify->invoke( $provider, array( 'content' => 'api_key=sk-abcdefghijklmnopqrstuvwxyz' ), 'public_site_content', array() );
$assert( 'secret' === $classification, 'Secret-shaped editor text is classified as secret before Cloud handoff.' );
$assert( 'no_store' === $storage->invoke( $provider, $classification ), 'Secret-shaped editor text forces no_store Cloud handling.' );
$requested_pii_secret = $classify->invoke(
	$provider,
	array( 'content' => 'api_key=sk-abcdefghijklmnopqrstuvwxyz' ),
	'public_site_content',
	array( 'runtime_data_classification' => 'pii' )
);
$assert( 'secret' === $requested_pii_secret, 'Detected secrets take priority over a caller-requested PII classification.' );

$deep_secret = 'api_key=sk-abcdefghijklmnopqrstuvwxyz';
for ( $depth = 0; $depth < 8; ++$depth ) {
	$deep_secret = array( 'nested' => $deep_secret );
}
$assert( 'secret' === $classify->invoke( $provider, $deep_secret, 'public_site_content', array() ), 'Uninspected payload data at the recursion budget fails closed as secret.' );
$assert( 'public_site_content' === $classify->invoke( $provider, array( 'quota' => 100 ), 'public_site_content', array() ), 'Non-secret operational metadata is not misclassified as a secret.' );

$public_host = new ReflectionMethod( \Npcink_Toolbox\Rest_Controller::class, 'editor_source_adaptation_host_is_public' );
$public_host->setAccessible( true );
$assert( false === $public_host->invoke( $rest_controller, '169.254.169.254' ), 'Source URL validation rejects link-local IP addresses.' );
$assert( false === $public_host->invoke( $rest_controller, '255.255.255.255' ), 'Source URL validation rejects reserved broadcast IP addresses.' );
$assert( false === $public_host->invoke( $rest_controller, '100.64.0.1' ), 'Source URL validation rejects shared CGNAT addresses.' );
$assert( false === $public_host->invoke( $rest_controller, '198.18.0.1' ), 'Source URL validation rejects benchmark network addresses.' );
$assert( false === $public_host->invoke( $rest_controller, '192.0.2.1' ), 'Source URL validation rejects documentation network addresses.' );
$assert( false === $public_host->invoke( $rest_controller, '::ffff:10.0.0.1' ), 'Source URL validation rechecks IPv4-mapped IPv6 private addresses.' );
$assert( true === $public_host->invoke( $rest_controller, '::ffff:93.184.216.34' ), 'Source URL validation permits an IPv4-mapped public address after rechecking it.' );
$assert( false === $public_host->invoke( $rest_controller, '2001:db8::1' ), 'Source URL validation rejects IPv6 documentation addresses.' );
$assert( true === $public_host->invoke( $rest_controller, '2606:4700:4700::1111' ), 'Source URL validation permits a global IPv6 address.' );
$assert( true === $public_host->invoke( $rest_controller, '93.184.216.34' ), 'Source URL validation permits a public IP address.' );

$provider_source = (string) file_get_contents( $root . '/includes/Provider_Client.php' );
$rest_source     = (string) file_get_contents( $root . '/includes/Rest_Controller.php' );
$assert( false === strpos( $provider_source, "settings->get( 'include_raw_responses' )" ), 'Provider normalizers cannot bypass the centralized raw-response policy.' );
$assert( false !== strpos( $rest_source, 'wp_http_validate_url' ) && false !== strpos( $rest_source, '$safe_port' ) && false !== strpos( $rest_source, '100.64.0.0/10' ) && false !== strpos( $rest_source, '2001:db8::/32' ) && false !== strpos( $rest_source, 'Cloud Addon owns fetch-time DNS and redirect validation' ), 'External source URLs reject literal special-purpose addresses and non-standard ports while Cloud Addon owns fetch-time DNS validation.' );

echo "Security permission/debug smoke: ok\n";
