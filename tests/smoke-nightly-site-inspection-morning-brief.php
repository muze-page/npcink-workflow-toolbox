<?php
/**
 * Validates the Nightly Site Inspection / Morning Brief Phase 1 replay fixture.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

require_once $root . '/modules/local-automation-runtime/src/Contract/Replay_Validator.php';

$fixture_path = $root . '/modules/local-automation-runtime/tests/fixtures/nightly-site-inspection-morning-brief-dry-run.json';
$fixture      = json_decode( (string) file_get_contents( $fixture_path ), true );

if ( ! is_array( $fixture ) ) {
	fwrite( STDERR, "Nightly Site Inspection replay fixture is not valid JSON.\n" );
	exit( 1 );
}

$validator = new \Npcink\LocalAutomationRuntime\Contract\Replay_Validator();
$result    = $validator->validate( $fixture );

if ( true !== ( $result['valid'] ?? false ) ) {
	fwrite( STDERR, 'Nightly Site Inspection replay fixture failed: ' . implode( ', ', $result['errors'] ?? array() ) . "\n" );
	exit( 1 );
}

if ( 'nightly_site_inspection_morning_brief' !== ( $fixture['task_profile'] ?? '' ) ) {
	fwrite( STDERR, "Nightly Site Inspection replay fixture has the wrong task profile.\n" );
	exit( 1 );
}

if ( false !== ( $fixture['preview']['morning_brief']['safety']['direct_wordpress_write'] ?? true ) ) {
	fwrite( STDERR, "Nightly Site Inspection replay fixture must not permit direct WordPress writes.\n" );
	exit( 1 );
}

echo "Nightly Site Inspection replay fixture: ok\n";
