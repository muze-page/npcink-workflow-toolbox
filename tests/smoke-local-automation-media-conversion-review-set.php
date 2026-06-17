<?php
/**
 * Validates the local automation runtime media conversion review-set fixture.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

require_once $root . '/modules/local-automation-runtime/src/Contract/Media_Conversion_Review_Set_Validator.php';
require_once $root . '/modules/local-automation-runtime/src/Contract/Media_Conversion_Review_Set_Normalizer.php';

$fixture_path = $root . '/modules/local-automation-runtime/tests/fixtures/media-conversion-review-set.json';
$fixture      = json_decode( (string) file_get_contents( $fixture_path ), true );

if ( ! is_array( $fixture ) ) {
	fwrite( STDERR, "Local automation media conversion review-set fixture is not valid JSON.\n" );
	exit( 1 );
}

$validator = new \Npcink\LocalAutomationRuntime\Contract\Media_Conversion_Review_Set_Validator();
$normalizer = new \Npcink\LocalAutomationRuntime\Contract\Media_Conversion_Review_Set_Normalizer();
$result    = $validator->validate( $fixture );

if ( true !== ( $result['valid'] ?? false ) ) {
	fwrite( STDERR, 'Local automation media conversion review-set fixture failed: ' . implode( ', ', $result['errors'] ?? array() ) . "\n" );
	exit( 1 );
}

$plan_fixture_path = $root . '/modules/local-automation-runtime/tests/fixtures/media-derivative-batch-plan.json';
$plan_fixture      = json_decode( (string) file_get_contents( $plan_fixture_path ), true );
if ( ! is_array( $plan_fixture ) ) {
	fwrite( STDERR, "Media derivative batch plan fixture is not valid JSON.\n" );
	exit( 1 );
}

$normalized = $normalizer->from_media_derivative_batch_plan( $plan_fixture );
$normalized_result = $validator->validate( $normalized );
if ( true !== ( $normalized_result['valid'] ?? false ) ) {
	fwrite( STDERR, 'Normalized media derivative batch plan failed review-set validation: ' . implode( ', ', $normalized_result['errors'] ?? array() ) . "\n" );
	exit( 1 );
}
if ( true !== ( $normalized['retryable'] ?? null ) || true !== ( $normalized['retry_guidance']['retryable'] ?? null ) ) {
	fwrite( STDERR, "Normalized media derivative batch plan did not preserve rebuildable review-set retryability.\n" );
	exit( 1 );
}
if ( 2 !== (int) ( $normalized['eligibility_summary']['selected_count'] ?? 0 ) || 1 !== (int) ( $normalized['eligibility_summary']['blocked_count'] ?? 0 ) ) {
	fwrite( STDERR, "Normalized media derivative batch plan did not preserve selected and blocked counts.\n" );
	exit( 1 );
}
if ( 'npcink-abilities-toolkit/build-media-derivative-cloud-request' !== (string) ( $normalized['selected_items'][0]['target_ability_id'] ?? '' ) ) {
	fwrite( STDERR, "Normalized media derivative batch plan did not preserve the media derivative target ability.\n" );
	exit( 1 );
}

$negative = $fixture;
$negative['safety']['local_queue_created'] = true;
$negative_result = $validator->validate( $negative );
if ( true === ( $negative_result['valid'] ?? false ) || ! in_array( 'safety.local_queue_created_not_false', $negative_result['errors'] ?? array(), true ) ) {
	fwrite( STDERR, "Local automation media conversion review-set did not fail closed on local queue ownership.\n" );
	exit( 1 );
}

$negative = $fixture;
$negative['selected_items'][0]['direct_wordpress_write'] = true;
$negative_result = $validator->validate( $negative );
if ( true === ( $negative_result['valid'] ?? false ) || ! in_array( 'selected_items.0.direct_wordpress_write_not_false', $negative_result['errors'] ?? array(), true ) ) {
	fwrite( STDERR, "Local automation media conversion review-set did not fail closed on direct WordPress writes.\n" );
	exit( 1 );
}

echo "Local automation media conversion review-set fixture: ok\n";
