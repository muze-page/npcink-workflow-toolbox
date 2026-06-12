<?php
/**
 * Offline hard-gate evaluation for AI-generated WordPress excerpts.
 *
 * This script intentionally avoids Cloud calls. It validates fixture or exported
 * summary candidates so prompt changes can be regression-tested before manual
 * editor review.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__, 3 );
$samples_arg = $argv[1] ?? '';
$samples_path = '' !== $samples_arg ? $samples_arg : $root . '/dev/evaluation/summary/samples.json';

function npcink_summary_eval_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function npcink_summary_eval_text_length( string $text ): int {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $text, 'UTF-8' );
	}

	return strlen( $text );
}

function npcink_summary_eval_contains_any( string $haystack, array $needles ): array {
	$hits = array();
	foreach ( $needles as $needle ) {
		$needle = (string) $needle;
		if ( '' === $needle ) {
			continue;
		}
		if ( false !== stripos( $haystack, $needle ) ) {
			$hits[] = $needle;
		}
	}

	return $hits;
}

function npcink_summary_eval_key( string $value ): string {
	$value = strtolower( trim( $value ) );
	$value = preg_replace( '/[^a-z0-9_-]+/', '_', $value );

	return trim( is_string( $value ) ? $value : '', '_' );
}

function npcink_summary_eval_score_candidate( array $sample, array $candidate ): array {
	$summary = trim( (string) ( $candidate['summary'] ?? $candidate['recommended_excerpt'] ?? '' ) );
	$title   = trim( (string) ( $sample['title'] ?? '' ) );
	$content = trim( (string) ( $sample['content'] ?? '' ) );
	$limits  = is_array( $sample['length'] ?? null ) ? $sample['length'] : array();
	$min     = (int) ( $limits['min'] ?? 80 );
	$max     = (int) ( $limits['max'] ?? 160 );

	$forbidden = array_values(
		array_unique(
			array_merge(
				array(
					'本文',
					'本文说明',
					'本文介绍',
					'这篇文章',
					'该文章',
					'这篇草稿',
					'草稿主张',
					'this article',
					'this draft',
					'as an ai',
				),
				is_array( $sample['must_not_contain'] ?? null ) ? $sample['must_not_contain'] : array()
			)
		)
	);
	$risky_claims = array(
		'行业领先',
		'最佳',
		'最强',
		'保证',
		'显著提升',
		'立刻见效',
		'完全自动',
		'无需人工',
		'排名第一',
		'100%',
	);

	$issues = array();
	if ( '' === $summary ) {
		$issues[] = 'empty_summary';
	}

	$length = npcink_summary_eval_text_length( $summary );
	if ( $length < $min || $length > $max ) {
		$issues[] = "length_out_of_range:{$length}:{$min}-{$max}";
	}

	foreach ( npcink_summary_eval_contains_any( $summary, $forbidden ) as $hit ) {
		$issues[] = 'forbidden_meta_phrase:' . $hit;
	}

	if ( false !== strpos( $summary, '```' ) || false !== strpos( $summary, '{' ) || false !== strpos( $summary, 'recommended_excerpt' ) ) {
		$issues[] = 'format_leak';
	}

	if ( '' !== $title && false !== mb_stripos( $summary, $title, 0, 'UTF-8' ) ) {
		$issues[] = 'title_repetition';
	}

	foreach ( npcink_summary_eval_contains_any( $summary, $risky_claims ) as $claim ) {
		if ( '' === $content || false === stripos( $content, $claim ) ) {
			$issues[] = 'unsupported_risky_claim:' . $claim;
		}
	}

	if ( array_key_exists( 'quality_status', $candidate ) || array_key_exists( 'quality_score', $candidate ) ) {
		$runtime_status = npcink_summary_eval_key( (string) ( $candidate['quality_status'] ?? '' ) );
		$runtime_score  = (int) ( $candidate['quality_score'] ?? 0 );
		if ( in_array( $runtime_status, array( 'weak', 'review' ), true ) || ( $runtime_score > 0 && $runtime_score < 70 ) ) {
			$issues[] = 'runtime_quality_gate:' . ( '' !== $runtime_status ? $runtime_status : 'score_' . $runtime_score );
		}
	}

	$score = 100;
	foreach ( $issues as $issue ) {
		if ( str_starts_with( $issue, 'forbidden_meta_phrase:' ) ) {
			$score -= 35;
		} elseif ( str_starts_with( $issue, 'unsupported_risky_claim:' ) ) {
			$score -= 30;
		} elseif ( str_starts_with( $issue, 'runtime_quality_gate:' ) ) {
			$score -= 35;
		} elseif ( str_starts_with( $issue, 'length_out_of_range:' ) ) {
			$score -= 20;
		} else {
			$score -= 25;
		}
	}

	return array(
		'id'     => (string) ( $candidate['id'] ?? 'candidate' ),
		'score'  => max( 0, $score ),
		'passed' => array() === $issues,
		'issues' => $issues,
		'length' => $length,
	);
}

if ( ! is_file( $samples_path ) ) {
	npcink_summary_eval_fail( 'Summary eval samples not found: ' . $samples_path );
}

$decoded = json_decode( (string) file_get_contents( $samples_path ), true );
if ( ! is_array( $decoded ) ) {
	npcink_summary_eval_fail( 'Summary eval samples JSON is invalid: ' . $samples_path );
}

$samples = is_array( $decoded['samples'] ?? null ) ? $decoded['samples'] : $decoded;
if ( array() === $samples ) {
	npcink_summary_eval_fail( 'Summary eval samples are empty.' );
}

$failures = 0;
$checked  = 0;
foreach ( $samples as $sample ) {
	if ( ! is_array( $sample ) ) {
		$failures++;
		echo "FAIL: invalid sample row\n";
		continue;
	}

	$candidates = is_array( $sample['candidates'] ?? null ) ? $sample['candidates'] : array();
	if ( array() === $candidates && isset( $sample['generated_excerpt'] ) ) {
		$candidates = array(
			array(
				'id'      => 'generated_excerpt',
				'summary' => $sample['generated_excerpt'],
			),
		);
	}
	if ( array() === $candidates ) {
		$failures++;
		echo 'FAIL: sample has no candidates: ' . (string) ( $sample['id'] ?? 'unknown' ) . "\n";
		continue;
	}

	foreach ( $candidates as $candidate ) {
		if ( ! is_array( $candidate ) ) {
			$failures++;
			echo 'FAIL: invalid candidate for sample ' . (string) ( $sample['id'] ?? 'unknown' ) . "\n";
			continue;
		}

		$checked++;
		$result        = npcink_summary_eval_score_candidate( $sample, $candidate );
		$expected_pass = array_key_exists( 'expected_pass', $candidate ) ? (bool) $candidate['expected_pass'] : true;
		$ok            = $expected_pass === $result['passed'];
		if ( ! $ok ) {
			$failures++;
		}

		echo sprintf(
			"%s: sample=%s candidate=%s score=%d length=%d issues=%s\n",
			$ok ? 'PASS' : 'FAIL',
			(string) ( $sample['id'] ?? 'unknown' ),
			$result['id'],
			$result['score'],
			$result['length'],
			array() === $result['issues'] ? '-' : implode( ',', $result['issues'] )
		);
	}
}

if ( 0 < $failures ) {
	npcink_summary_eval_fail( "{$failures} summary eval assertion(s) failed." );
}

echo "Summary eval passed: {$checked} candidate(s).\n";
