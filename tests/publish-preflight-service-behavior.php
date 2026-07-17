<?php
/**
 * Focused behavior checks for deterministic publish-preflight artifacts.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

function npcink_toolbox_preflight_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	echo "PASS: {$message}\n";
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function absint( $value ): int {
	return max( 0, (int) $value );
}

function sanitize_key( $key ): string {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
}

function sanitize_text_field( $value ): string {
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) ?? '' );
}

function sanitize_textarea_field( $value ): string {
	return trim( wp_strip_all_tags( (string) $value ) );
}

function wp_strip_all_tags( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function wp_trim_words( $text, int $num_words = 55, string $more = '&hellip;' ): string {
	$words = preg_split( '/\s+/', trim( (string) $text ) ) ?: array();
	return count( $words ) > $num_words ? implode( ' ', array_slice( $words, 0, $num_words ) ) . $more : implode( ' ', $words );
}

function wp_html_excerpt( $text, int $count, string $more = '' ): string {
	$text = wp_strip_all_tags( $text );
	return strlen( $text ) > $count ? substr( $text, 0, $count ) . $more : $text;
}

/**
 * Indexes artifact rows by their stable name or id.
 *
 * @param array<int,array<string,mixed>> $items Artifact rows.
 * @param string                        $key Row key.
 * @return array<string,array<string,mixed>>
 */
function npcink_toolbox_preflight_index( array $items, string $key ): array {
	$indexed = array();
	foreach ( $items as $item ) {
		if ( is_array( $item ) && '' !== (string) ( $item[ $key ] ?? '' ) ) {
			$indexed[ (string) $item[ $key ] ] = $item;
		}
	}

	return $indexed;
}

require_once dirname( __DIR__ ) . '/includes/Publish_Preflight_Service.php';

$service = new Npcink_Toolbox\Publish_Preflight_Service();
$context = array(
	'post_id'        => 42,
	'title'          => 'Governed WordPress AI workflows',
	'excerpt'        => 'A concise summary for archives and search results.',
	'content_text'   => 'This article explains governed WordPress AI workflows and review boundaries.',
	'category_ids'   => array( 3 ),
	'tag_ids'        => array( 7 ),
	'featured_media' => 11,
);
$discoverability = array(
	'candidate_suggestions' => array(
		'seo_title'       => 'Governed WordPress AI Workflows',
		'seo_description' => 'Review safe, governed AI workflows for WordPress operators.',
	),
);

$sections = $service->build_sections( $context, $discoverability, array( 'results' => array() ) );
npcink_toolbox_preflight_assert( array( 'checks', 'duplicate_check', 'seo_handoff', 'pre_publish_review' ) === array_keys( $sections ), 'Publish preflight keeps the established section order and shape.' );

$checks = npcink_toolbox_preflight_index( (array) ( $sections['checks']['items'] ?? array() ), 'id' );
npcink_toolbox_preflight_assert( array( 'title', 'excerpt', 'terms', 'featured_media' ) === array_keys( $checks ), 'Local checks keep the four established check ids.' );
npcink_toolbox_preflight_assert( array( 'ok', 'ok', 'ok', 'ok' ) === array_column( $checks, 'status' ), 'Complete editor context passes every local check.' );
npcink_toolbox_preflight_assert( 'suggestion_only' === (string) ( $sections['checks']['write_posture'] ?? '' ) && false === (bool) ( $sections['checks']['direct_wordpress_write'] ?? true ), 'Local checks remain suggestion-only and no-write.' );

$seo_handoff = (array) ( $sections['seo_handoff'] ?? array() );
$seo_template = (array) ( $seo_handoff['proposal_payload_template'] ?? array() );
$seo_input = (array) ( $seo_template['input'] ?? array() );
$seo_preview = (array) ( $seo_template['preview'] ?? array() );
npcink_toolbox_preflight_assert( 'seo_meta_handoff_preview.v1' === (string) ( $seo_handoff['artifact_type'] ?? '' ) && true === (bool) ( $seo_handoff['proposal_ready'] ?? false ), 'Complete context produces the established proposal-ready SEO preview.' );
npcink_toolbox_preflight_assert( 'npcink-abilities-toolkit/set-post-seo-meta' === (string) ( $seo_handoff['target_ability_id'] ?? '' ), 'SEO preview keeps the governed Toolkit ability id.' );
npcink_toolbox_preflight_assert( true === (bool) ( $seo_input['dry_run'] ?? false ) && false === (bool) ( $seo_input['commit'] ?? true ) && false === (bool) ( $seo_preview['commit_execution'] ?? true ), 'SEO proposal input remains dry-run and non-committing.' );
npcink_toolbox_preflight_assert( 2 === count( (array) ( $seo_preview['field_patch'] ?? array() ) ) && false === (bool) ( $seo_handoff['direct_wordpress_write'] ?? true ), 'SEO preview preserves two reviewable field patches without a Toolbox write.' );

$review = (array) ( $sections['pre_publish_review'] ?? array() );
$review_items = npcink_toolbox_preflight_index( (array) ( $review['items'] ?? array() ), 'name' );
$review_statuses = array_map( static fn( array $item ): string => (string) ( $item['status'] ?? '' ), $review_items );
npcink_toolbox_preflight_assert(
	array(
		'summary'        => 'ok',
		'categories'     => 'ok',
		'tags'           => 'ok',
		'featured_image' => 'ok',
		'internal_links' => 'review',
		'seo_meta'       => 'review',
		'duplicate_risk' => 'ok',
	) === $review_statuses,
	'Unified review maps complete context, manual link review, SEO review, and no duplicate evidence exactly.'
);
npcink_toolbox_preflight_assert( 'pre_publish_review.v1' === (string) ( $review['artifact_type'] ?? '' ) && 'core_proposal_required' === (string) ( $review['final_write_path'] ?? '' ), 'Unified review keeps its v1 artifact and governed final-write path.' );
npcink_toolbox_preflight_assert(
	array( 'summary_suggestions', 'category_suggestions', 'tag_suggestions', 'internal_links', 'image_candidates', 'seo_meta_single_post_handoff' ) === (array) ( $review['next_actions'] ?? array() ),
	'Unified review keeps the established next-action contract.'
);
npcink_toolbox_preflight_assert( false === (bool) ( $review['direct_wordpress_write'] ?? true ) && false === (bool) ( $review['handoff']['direct_wordpress_write'] ?? true ), 'Unified review and handoff both prohibit direct WordPress writes.' );

$missing_sections = $service->build_sections(
	array(
		'post_id'      => 0,
		'content_text' => 'Fallback description text remains a suggestion.',
	),
	array(),
	array(
		'results' => array(
			array( 'post_id' => 99, 'title' => 'Related public article' ),
			'invalid-row',
		),
	)
);
$missing_checks = npcink_toolbox_preflight_index( (array) ( $missing_sections['checks']['items'] ?? array() ), 'id' );
npcink_toolbox_preflight_assert( array( 'warning', 'warning', 'warning', 'warning' ) === array_column( $missing_checks, 'status' ), 'Missing editor metadata produces the established local warning states.' );
$missing_review = npcink_toolbox_preflight_index( (array) ( $missing_sections['pre_publish_review']['items'] ?? array() ), 'name' );
npcink_toolbox_preflight_assert( 'warning' === (string) ( $missing_review['summary']['status'] ?? '' ) && 'warning' === (string) ( $missing_review['seo_meta']['status'] ?? '' ), 'Missing excerpt and proposal target remain visible warnings.' );
npcink_toolbox_preflight_assert( 'review' === (string) ( $missing_review['duplicate_risk']['status'] ?? '' ), 'Related Site Knowledge evidence preserves duplicate-risk review status.' );
npcink_toolbox_preflight_assert( false === (bool) ( $missing_sections['seo_handoff']['proposal_ready'] ?? true ), 'SEO handoff fails closed without a current post id.' );

echo "Publish preflight service behavior checks passed.\n";
