<?php
/**
 * Smoke checks for image candidate Agent feedback capture.
 *
 * @package Npcink_Toolbox
 */

$root     = dirname( __DIR__ );
$admin_js = file_get_contents( $root . '/assets/admin.js' );
$client   = file_get_contents( $root . '/includes/Provider_Client.php' );

function npcink_toolbox_image_feedback_smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "[fail] {$message}\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "[ok] {$message}\n" );
}

npcink_toolbox_image_feedback_smoke_assert(
	false !== $admin_js && false !== $client,
	'Image feedback smoke can read the required source files.'
);

npcink_toolbox_image_feedback_smoke_assert(
	false !== strpos( $admin_js, 'function imageAgentFeedbackPayload' )
	&& false !== strpos( $admin_js, "postJson(config.restUrl, 'agent-feedback'" )
	&& false !== strpos( $admin_js, "local_surface: surface" )
	&& false !== strpos( $admin_js, "source_runtime: sourceRuntime" )
	&& false !== strpos( $admin_js, "redaction_status: 'metadata_only'" )
	&& false !== strpos( $admin_js, "retention_class: 'quality_eval'" ),
	'Admin UI sends image candidate feedback as metadata-only Cloud eval payloads.'
);

npcink_toolbox_image_feedback_smoke_assert(
	false !== strpos( $admin_js, "appendImageAgentFeedbackControls(section, payload, 'toolbox_ai_image_generation')" )
	&& false !== strpos( $admin_js, 'appendImageAgentFeedbackControls(' )
	&& false !== strpos( $admin_js, "payload.provider_mode === 'ai_generated' ? 'toolbox_ai_image_generation' : 'toolbox_image_candidates'" )
	&& false !== strpos( $admin_js, "data-toolbox-image-agent-feedback" )
	&& false !== strpos( $admin_js, 'Quick image feedback' ),
	'Admin UI exposes quick image feedback on image-source and AI-generated candidate results.'
);

npcink_toolbox_image_feedback_smoke_assert(
	false !== strpos( $admin_js, 'Useful candidates' )
	&& false !== strpos( $admin_js, 'Adoption planned' )
	&& false !== strpos( $admin_js, 'Low visual quality' )
	&& false !== strpos( $admin_js, 'Source risk' )
	&& false !== strpos( $admin_js, "labels: ['visual_quality_low', 'operator_confidence_low']" )
	&& false !== strpos( $admin_js, "labels: ['source_or_license_risk', 'operator_confidence_low']" ),
	'Admin UI records fixed image quality labels without free-form media payload capture.'
);

npcink_toolbox_image_feedback_smoke_assert(
	false !== strpos( $admin_js, 'WordPress media import and writes remain local.' )
	&& false !== strpos( $client, "'visual_quality_low'" )
	&& false !== strpos( $client, "'source_or_license_risk'" )
	&& false !== strpos( $client, "'production_mutation'      => false" )
	&& false !== strpos( $client, "'final_write_truth'        => 'wordpress_local'" ),
	'Provider client accepts image feedback labels while keeping media imports and WordPress writes local.'
);

fwrite( STDOUT, "Image Agent feedback UI smoke: ok\n" );
