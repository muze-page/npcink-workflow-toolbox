<?php
/**
 * Validates the bundled local automation runtime Phase 1 replay fixture.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

require_once $root . '/modules/local-automation-runtime/src/Contract/Replay_Validator.php';

$fixture_path = $root . '/modules/local-automation-runtime/tests/fixtures/dry-run-replay.json';
$fixture      = json_decode( (string) file_get_contents( $fixture_path ), true );

if ( ! is_array( $fixture ) ) {
	fwrite( STDERR, "Local automation runtime replay fixture is not valid JSON.\n" );
	exit( 1 );
}

$validator = new \Npcink\LocalAutomationRuntime\Contract\Replay_Validator();
$result    = $validator->validate( $fixture );

if ( true !== ( $result['valid'] ?? false ) ) {
	fwrite( STDERR, 'Local automation runtime replay fixture failed: ' . implode( ', ', $result['errors'] ?? array() ) . "\n" );
	exit( 1 );
}

echo "Local automation runtime replay fixture: ok\n";
