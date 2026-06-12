<?php
/**
 * Analyze opening diversity in generated AI summary candidates.
 *
 * This script is local/offline only. It reads generated eval JSON and reports
 * repeated audience-label openings such as 面向 or 适合.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 3 );
$script_args = array_slice( $argv ?? array(), 1 );

function npcink_summary_openings_arg_map( array $script_args ): array {
	$parsed = array();
	foreach ( $script_args as $arg ) {
		$arg = (string) $arg;
		if ( ! str_contains( $arg, '=' ) ) {
			continue;
		}
		list( $key, $value ) = explode( '=', $arg, 2 );
		$parsed[ trim( $key ) ] = trim( $value );
	}

	return $parsed;
}

function npcink_summary_openings_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_openings_path( string $path, string $root ): string {
	if ( str_starts_with( $path, '/' ) ) {
		return $path;
	}

	return $root . '/' . ltrim( $path, '/' );
}

function npcink_summary_opening_prefix( string $summary ): string {
	$value = trim( preg_replace( '/\s+/u', '', $summary ) ?? $summary );
	if ( '' === $value ) {
		return 'empty';
	}

	$audience_prefixes = array(
		'面向',
		'适合',
		'需要',
		'想',
		'不想',
		'针对',
	);
	foreach ( $audience_prefixes as $prefix ) {
		if ( str_starts_with( $value, $prefix ) ) {
			return $prefix;
		}
	}

	$action_prefixes = array(
		'从',
		'通过',
		'以',
		'一个',
		'这款',
		'这个',
		'可',
		'用',
	);
	foreach ( $action_prefixes as $prefix ) {
		if ( str_starts_with( $value, $prefix ) ) {
			return $prefix;
		}
	}

	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $value, 0, 2, 'UTF-8' );
	}

	return substr( $value, 0, 6 );
}

function npcink_summary_opening_bucket( string $prefix ): string {
	return in_array( $prefix, array( '面向', '适合', '需要', '想', '不想', '针对' ), true ) ? 'audience_label' : 'other';
}

$arg_map = npcink_summary_openings_arg_map( $script_args );
$input   = npcink_summary_openings_path( (string) ( $arg_map['input'] ?? 'dev/evaluation/summary/generated/muze-candidates.json' ), $root );

if ( ! is_file( $input ) ) {
	npcink_summary_openings_fail( 'Generated summary candidate file not found: ' . $input );
}

$decoded = json_decode( (string) file_get_contents( $input ), true );
if ( ! is_array( $decoded ) || ! is_array( $decoded['samples'] ?? null ) ) {
	npcink_summary_openings_fail( 'Input JSON is invalid or missing samples: ' . $input );
}

$prefix_counts      = array();
$bucket_counts      = array();
$candidate_count    = 0;
$sample_count       = 0;
$generation_errors  = 0;
$same_bucket_pairs  = array();
$same_prefix_pairs  = array();

foreach ( $decoded['samples'] as $sample ) {
	if ( ! is_array( $sample ) ) {
		continue;
	}

	if ( isset( $sample['generation_error'] ) ) {
		$generation_errors++;
		continue;
	}

	$candidates = is_array( $sample['candidates'] ?? null ) ? $sample['candidates'] : array();
	if ( array() === $candidates ) {
		continue;
	}

	$sample_count++;
	$sample_prefixes = array();
	$sample_buckets  = array();
	foreach ( $candidates as $candidate ) {
		if ( ! is_array( $candidate ) ) {
			continue;
		}
		$summary = (string) ( $candidate['summary'] ?? '' );
		if ( '' === trim( $summary ) ) {
			continue;
		}

		$prefix = npcink_summary_opening_prefix( $summary );
		$bucket = npcink_summary_opening_bucket( $prefix );
		$prefix_counts[ $prefix ] = ( $prefix_counts[ $prefix ] ?? 0 ) + 1;
		$bucket_counts[ $bucket ] = ( $bucket_counts[ $bucket ] ?? 0 ) + 1;
		$sample_prefixes[] = $prefix;
		$sample_buckets[]  = $bucket;
		$candidate_count++;
	}

	if ( count( $sample_prefixes ) >= 2 && 1 === count( array_unique( $sample_prefixes ) ) ) {
		$same_prefix_pairs[] = (string) ( $sample['id'] ?? 'unknown' ) . ':' . $sample_prefixes[0];
	}
	if ( count( $sample_buckets ) >= 2 && 1 === count( array_unique( $sample_buckets ) ) ) {
		$same_bucket_pairs[] = (string) ( $sample['id'] ?? 'unknown' ) . ':' . $sample_buckets[0];
	}
}

arsort( $prefix_counts );
arsort( $bucket_counts );

$audience_count = (int) ( $bucket_counts['audience_label'] ?? 0 );
$audience_rate  = $candidate_count > 0 ? round( $audience_count * 100 / $candidate_count, 1 ) : 0.0;

echo "Summary opening diversity\n";
echo 'Input: ' . $input . "\n";
echo 'Samples: ' . $sample_count . "\n";
echo 'Candidates: ' . $candidate_count . "\n";
echo 'Generation errors: ' . $generation_errors . "\n";
echo 'Audience-label openings: ' . $audience_count . ' / ' . $candidate_count . ' (' . $audience_rate . "%)\n";
echo 'Same-prefix candidate pairs: ' . count( $same_prefix_pairs ) . "\n";
echo 'Same-bucket candidate pairs: ' . count( $same_bucket_pairs ) . "\n";
echo "\nPrefix counts:\n";
foreach ( $prefix_counts as $prefix => $count ) {
	echo '- ' . $prefix . ': ' . $count . "\n";
}

if ( array() !== $same_prefix_pairs ) {
	echo "\nSame-prefix samples:\n";
	foreach ( $same_prefix_pairs as $sample ) {
		echo '- ' . $sample . "\n";
	}
}

if ( array() !== $same_bucket_pairs ) {
	echo "\nSame-bucket samples:\n";
	foreach ( $same_bucket_pairs as $sample ) {
		echo '- ' . $sample . "\n";
	}
}
