<?php
/**
 * Local WordPress smoke for content discoverability abilities.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-content-discoverability.php -- [post_id]
 *
 * @package Magick_AI_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$toolbox_smoke_target_abilities = array(
	'magick-ai-toolbox/get-content-discoverability-context'      => 'cap.toolbox.context.read',
	'magick-ai-toolbox/validate-content-discoverability-context' => 'cap.toolbox.context.read',
	'magick-ai-toolbox/build-content-discoverability-brief'      => 'cap.toolbox.workflow_suggest',
);

function toolbox_content_smoke_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_content_smoke_info( string $message ): void {
	echo "INFO: {$message}\n";
}

function toolbox_content_smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_content_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_content_smoke_fail( $message );
	}

	toolbox_content_smoke_pass( $message );
}

function toolbox_content_smoke_definition( array $definitions, string $ability_id ): array {
	$definition = $definitions[ $ability_id ] ?? array();

	return is_array( $definition ) ? $definition : array();
}

function toolbox_content_smoke_call( array $definitions, string $ability_id, array $input = array() ) {
	$definition = toolbox_content_smoke_definition( $definitions, $ability_id );
	$callback   = $definition['execute_callback'] ?? null;

	if ( ! is_callable( $callback ) ) {
		toolbox_content_smoke_fail( "{$ability_id} does not expose a callable execute callback." );
	}

	return call_user_func( $callback, $input );
}

function toolbox_content_smoke_index_catalog( array $catalog ): array {
	$index = array();

	foreach ( $catalog as $key => $entry ) {
		$entry = is_array( $entry ) ? $entry : array();
		foreach ( array( 'ability_id', 'wp_ability_id' ) as $field ) {
			$ability_id = sanitize_text_field( (string) ( $entry[ $field ] ?? '' ) );
			if ( '' !== $ability_id ) {
				$index[ $ability_id ] = $entry;
			}
		}

		$meta           = is_array( $entry['meta'] ?? null ) ? $entry['meta'] : array();
		$magick_meta    = is_array( $meta['magick'] ?? null ) ? $meta['magick'] : array();
		$wp_ability_id = sanitize_text_field( (string) ( $magick_meta['wp_ability_id'] ?? '' ) );
		if ( '' !== $wp_ability_id ) {
			$index[ $wp_ability_id ] = $entry;
		}

		$key = sanitize_key( (string) $key );
		if ( '' !== $key ) {
			$index[ $key ] = $entry;
		}
	}

	return $index;
}

function toolbox_content_smoke_catalog(): array {
	$args = array(
		'include_disabled'     => true,
		'include_internal'     => true,
		'include_wp_abilities' => true,
		'skip_cache'           => true,
	);

	if ( function_exists( 'magick_ai_open_platform_get_ability_catalog' ) ) {
		$catalog = magick_ai_open_platform_get_ability_catalog( $args );
		return is_array( $catalog ) ? $catalog : array();
	}

	$catalog = apply_filters( 'magick_ai_open_platform_ability_catalog', array(), $args );
	return is_array( $catalog ) ? $catalog : array();
}

function toolbox_content_smoke_find_sample_post_id( array $script_args ): int {
	$requested = absint( $script_args[0] ?? 0 );
	if ( $requested > 0 && get_post( $requested ) ) {
		return $requested;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'draft', 'publish', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	return absint( $posts[0] ?? 0 );
}

function toolbox_content_smoke_find_agent_gateway_tools( array $target_ability_ids ): array {
	if ( ! function_exists( 'magick_ai_open_platform_get_projection_matrix' ) ) {
		return array(
			'available' => false,
			'found'     => array(),
			'missing'   => $target_ability_ids,
		);
	}

	$projection = magick_ai_open_platform_get_projection_matrix(
		array(
			'include_disabled'     => true,
			'include_internal'     => true,
			'include_wp_abilities' => true,
			'skip_cache'           => true,
		)
	);
	$tool_map   = is_array( $projection['agent_gateway_tool_map'] ?? null ) ? $projection['agent_gateway_tool_map'] : array();
	$found      = array();

	foreach ( $tool_map as $tool_name => $tool ) {
		$tool       = is_array( $tool ) ? $tool : array();
		$ability_id = sanitize_text_field( (string) ( $tool['ability_id'] ?? $tool['wp_ability_id'] ?? '' ) );
		if ( in_array( $ability_id, $target_ability_ids, true ) ) {
			$found[ $ability_id ] = sanitize_key( (string) ( $tool['tool_name'] ?? $tool_name ) );
		}
	}

	return array(
		'available' => true,
		'found'     => $found,
		'missing'   => array_values( array_diff( $target_ability_ids, array_keys( $found ) ) ),
	);
}

$script_args = isset( $args ) && is_array( $args ) ? $args : array();

toolbox_content_smoke_assert(
	function_exists( 'magick_ai_abilities_get_registered' ),
	'Magick AI Abilities registry is available.'
);

$definitions = magick_ai_abilities_get_registered();
$definitions = is_array( $definitions ) ? $definitions : array();

foreach ( $toolbox_smoke_target_abilities as $ability_id => $expected_scope ) {
	$definition = toolbox_content_smoke_definition( $definitions, $ability_id );
	$meta       = is_array( $definition['meta'] ?? null ) ? $definition['meta'] : array();

	toolbox_content_smoke_assert( ! empty( $definition ), "{$ability_id} is registered." );
	toolbox_content_smoke_assert( true === (bool) ( $definition['project_to_magick_catalog'] ?? false ), "{$ability_id} opts into Magick catalog projection." );
	toolbox_content_smoke_assert( true === (bool) ( $meta['show_in_rest'] ?? false ), "{$ability_id} is REST-discoverable." );
	toolbox_content_smoke_assert( true === (bool) ( $meta['readonly'] ?? false ), "{$ability_id} is readonly." );
	toolbox_content_smoke_assert( false === (bool) ( $meta['direct_wordpress_write'] ?? true ), "{$ability_id} disables direct WordPress writes." );
	toolbox_content_smoke_assert( 'core_proposal_required' === (string) ( $meta['final_write_path'] ?? '' ), "{$ability_id} points final writes to Core proposals." );
	toolbox_content_smoke_assert( 'none' === (string) ( $meta['provider_secret_exposure'] ?? '' ), "{$ability_id} exposes no provider secrets." );
	toolbox_content_smoke_assert( $expected_scope === (string) ( $definition['required_scope'] ?? '' ), "{$ability_id} declares {$expected_scope}." );
}

$catalog       = toolbox_content_smoke_catalog();
$catalog_index = toolbox_content_smoke_index_catalog( $catalog );
foreach ( array_keys( $toolbox_smoke_target_abilities ) as $ability_id ) {
	$row = is_array( $catalog_index[ $ability_id ] ?? null ) ? $catalog_index[ $ability_id ] : array();
	toolbox_content_smoke_assert( ! empty( $row ), "{$ability_id} appears in the Magick compatibility catalog." );
	toolbox_content_smoke_assert( 'wp_ability' === (string) ( $row['executor_type'] ?? '' ), "{$ability_id} projects as a wp_ability executor." );
}

$validation = toolbox_content_smoke_call( $definitions, 'magick-ai-toolbox/validate-content-discoverability-context' );
$validation = is_array( $validation ) ? $validation : array();
$status     = sanitize_key( (string) ( $validation['status'] ?? '' ) );
toolbox_content_smoke_assert(
	in_array( $status, array( 'ready', 'ready_with_warnings' ), true ),
	"Content discoverability context is usable for AI callers ({$status})."
);

$sample_post_id = toolbox_content_smoke_find_sample_post_id( $script_args );
toolbox_content_smoke_assert( $sample_post_id > 0, 'A local post is available for the sample brief.' );
toolbox_content_smoke_info( 'Sample post id: ' . $sample_post_id );

$brief = toolbox_content_smoke_call(
	$definitions,
	'magick-ai-toolbox/build-content-discoverability-brief',
	array(
		'post_id' => $sample_post_id,
	)
);
if ( is_wp_error( $brief ) ) {
	toolbox_content_smoke_fail( 'Brief build returned WP_Error: ' . $brief->get_error_code() );
}
$brief = is_array( $brief ) ? $brief : array();

toolbox_content_smoke_assert( 'content_discoverability_brief' === (string) ( $brief['artifact_type'] ?? '' ), 'Sample brief declares content_discoverability_brief.' );
toolbox_content_smoke_assert( 'suggestion_only' === (string) ( $brief['write_posture'] ?? '' ), 'Sample brief is suggestion-only.' );
toolbox_content_smoke_assert( false === (bool) ( $brief['direct_wordpress_write'] ?? true ), 'Sample brief disables direct WordPress writes.' );
toolbox_content_smoke_assert( ! empty( $brief['proposal_template'] ) && is_array( $brief['proposal_template'] ), 'Sample brief returns a proposal template.' );
toolbox_content_smoke_assert( ! empty( $brief['handoff'] ) && is_array( $brief['handoff'] ), 'Sample brief returns governed handoff metadata.' );

$agent_gateway = toolbox_content_smoke_find_agent_gateway_tools( array_keys( $toolbox_smoke_target_abilities ) );
if ( empty( $agent_gateway['available'] ) ) {
	toolbox_content_smoke_info( 'Agent Gateway projection matrix is not available in this WordPress runtime.' );
} elseif ( empty( $agent_gateway['missing'] ) ) {
	toolbox_content_smoke_pass( 'Agent Gateway direct tool map includes all content discoverability abilities.' );
} else {
	toolbox_content_smoke_info(
		'Agent Gateway direct tool map does not include '
		. count( $agent_gateway['missing'] )
		. ' content discoverability abilities; Core-side allowed_channels/tool-name admission is required before OpenClaw can call them as wp_* tools.'
	);
}

echo "Content discoverability smoke passed.\n";
