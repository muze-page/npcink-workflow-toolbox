<?php
/**
 * Validates the deterministic Nightly Site Inspection builder.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Rule_Scorer.php';
require_once $root . '/modules/local-automation-runtime/src/NightlyInspection/Morning_Brief_Builder.php';

$fixture_path = $root . '/modules/local-automation-runtime/tests/fixtures/nightly-inspection-snapshot.json';
$snapshot     = json_decode( (string) file_get_contents( $fixture_path ), true );

if ( ! is_array( $snapshot ) ) {
	fwrite( STDERR, "Nightly inspection snapshot fixture is not valid JSON.\n" );
	exit( 1 );
}

$builder = new \Npcink\LocalAutomationRuntime\NightlyInspection\Morning_Brief_Builder();
$brief   = $builder->build( $snapshot );

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

if ( 'nightly_site_inspection_result.v1' !== ( $brief['contract_version'] ?? '' ) ) {
	$fail( 'Morning Brief contract version mismatch.' );
}
if ( 2 !== ( $brief['summary']['scanned_posts'] ?? 0 ) ) {
	$fail( 'Morning Brief scanned post count mismatch.' );
}
if ( 1 !== ( $brief['summary']['scanned_media'] ?? 0 ) ) {
	$fail( 'Morning Brief scanned media count mismatch.' );
}
if ( true !== ( $brief['safety']['requires_local_review'] ?? false ) ) {
	$fail( 'Morning Brief must require local review.' );
}
foreach ( array( 'direct_wordpress_write', 'cloud_scheduler_truth', 'cloud_called', 'cron_registered', 'action_scheduler_used', 'custom_tables_created' ) as $false_flag ) {
	if ( false !== ( $brief['safety'][ $false_flag ] ?? true ) ) {
		$fail( 'Morning Brief safety flag must be false: ' . $false_flag );
	}
}

$priorities = $brief['priorities'] ?? array();
if ( ! is_array( $priorities ) || count( $priorities ) < 2 ) {
	$fail( 'Morning Brief should include post and media priorities.' );
}

$first = $priorities[0] ?? array();
if ( 101 !== ( $first['object_id'] ?? 0 ) ) {
	$fail( 'Lowest-scored stale post should be the top priority.' );
}

$reason_codes = $first['reason_codes'] ?? array();
foreach ( array( 'missing_meta_description', 'thin_content', 'stale_year_marker', 'missing_internal_links', 'missing_image_alt' ) as $expected_code ) {
	if ( ! in_array( $expected_code, $reason_codes, true ) ) {
		$fail( 'Top priority is missing expected reason code: ' . $expected_code );
	}
}

$preparation = $brief['writing_preparation'] ?? array();
if ( ! is_array( $preparation ) || count( $preparation ) < 1 ) {
	$fail( 'Morning Brief should include writing preparation evidence.' );
}
if ( true !== ( $preparation[0]['forbidden_output_absent'] ?? false ) ) {
	$fail( 'Writing preparation must confirm forbidden writing output is absent.' );
}
if ( false === strpos( (string) ( $preparation[0]['evidence_summary'] ?? '' ), 'No article title, body, FAQ copy, or SEO copy is generated.' ) ) {
	$fail( 'Writing preparation must not generate writing copy.' );
}

echo "Nightly inspection builder: ok\n";
