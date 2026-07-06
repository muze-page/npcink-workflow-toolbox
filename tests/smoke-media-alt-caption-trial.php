<?php
/**
 * Local WordPress operator trial for the media ALT/caption review set.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-media-alt-caption-trial.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_media_alt_trial_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_media_alt_trial_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_media_alt_trial_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_media_alt_trial_fail( $message );
	}

	toolbox_media_alt_trial_pass( $message );
}

function toolbox_media_alt_trial_arg_map( array $script_args ): array {
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

function toolbox_media_alt_trial_admin_user_id(): int {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		)
	);

	return absint( $admins[0] ?? 0 );
}

function toolbox_media_alt_trial_attachment_ids( int $limit ): array {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => max( 1, min( 20, $limit ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	return array_values( array_map( 'absint', is_array( $ids ) ? $ids : array() ) );
}

function toolbox_media_alt_trial_explicit_attachment_ids( string $raw ): array {
	if ( '' === trim( $raw ) ) {
		return array();
	}

	$ids = array();
	foreach ( explode( ',', $raw ) as $value ) {
		$id = absint( trim( $value ) );
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}

	return array_values( array_unique( $ids ) );
}

function toolbox_media_alt_trial_trim_text( string $value, int $limit ): string {
	$value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $value ) ) ?? $value );
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		return mb_strlen( $value, 'UTF-8' ) > $limit ? mb_substr( $value, 0, $limit, 'UTF-8' ) : $value;
	}

	return strlen( $value ) > $limit ? substr( $value, 0, $limit ) : $value;
}

function toolbox_media_alt_trial_media_item( int $attachment_id, array $snapshot ): array {
	$url       = (string) ( $snapshot['url'] ?? '' );
	$caption   = (string) ( $snapshot['post_excerpt'] ?? '' );
	$alt       = sanitize_text_field( (string) ( $snapshot['alt'] ?? '' ) );
	$image_src = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
	$filename  = '' !== $url ? wp_basename( $url ) : wp_basename( (string) ( $snapshot['attached_file'] ?? '' ) );

	return array(
		'attachment_id'   => $attachment_id,
		'title'           => sanitize_text_field( (string) ( $snapshot['post_title'] ?? '' ) ),
		'caption'         => sanitize_textarea_field( $caption ),
		'description'     => toolbox_media_alt_trial_trim_text( (string) ( $snapshot['post_content'] ?? '' ), 240 ),
		'alt'             => $alt,
		'alt_length'      => function_exists( 'mb_strlen' ) ? mb_strlen( $alt, 'UTF-8' ) : strlen( $alt ),
		'missing_alt'     => '' === $alt,
		'missing_caption' => '' === trim( $caption ),
		'filename'        => sanitize_file_name( $filename ),
		'mime_type'       => sanitize_text_field( (string) get_post_mime_type( $attachment_id ) ),
		'thumbnail_url'   => is_array( $image_src ) ? esc_url_raw( (string) ( $image_src[0] ?? '' ) ) : '',
		'url'             => esc_url_raw( $url ),
	);
}

function toolbox_media_alt_trial_media_snapshot( array $attachment_ids, array $before ): array {
	$items       = array();
	$missing_alt = 0;

	foreach ( $attachment_ids as $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! isset( $before[ $attachment_id ] ) ) {
			continue;
		}
		$item = toolbox_media_alt_trial_media_item( $attachment_id, $before[ $attachment_id ] );
		if ( ! empty( $item['missing_alt'] ) ) {
			++$missing_alt;
		}
		$items[] = $item;
	}

	return array(
		'sample_size'       => count( $items ),
		'missing_alt_count' => $missing_alt,
		'items'             => $items,
		'snapshot_policy'   => 'operator_supplied_media_metadata_only',
	);
}

function toolbox_media_alt_trial_attachment_snapshot( array $attachment_ids ): array {
	$snapshot = array();

	foreach ( $attachment_ids as $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$post          = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			continue;
		}

		$url      = wp_get_attachment_url( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$snapshot[ $attachment_id ] = array(
			'post_title'       => (string) $post->post_title,
			'post_excerpt'     => (string) $post->post_excerpt,
			'post_content'     => (string) $post->post_content,
			'post_modified_gmt' => (string) $post->post_modified_gmt,
			'alt'              => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'attached_file'    => (string) get_post_meta( $attachment_id, '_wp_attached_file', true ),
			'metadata_hash'    => md5( wp_json_encode( is_array( $metadata ) ? $metadata : array() ) ?: '' ),
			'url'              => is_string( $url ) ? $url : '',
		);
	}

	return $snapshot;
}

function toolbox_media_alt_trial_rest_request( array $payload ): array {
	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/ai/site-helpers' );
	$request->set_body_params( $payload );
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_media_alt_trial_fail( 'REST request failed: ' . $response->get_error_code() );
	}

	$data = rest_get_server()->response_to_data( $response, false );
	if ( ! is_array( $data ) ) {
		toolbox_media_alt_trial_fail( 'REST response is not an array.' );
	}

	return $data;
}

function toolbox_media_alt_trial_write_json( string $path, array $payload ): void {
	if ( '' === $path ) {
		return;
	}

	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		toolbox_media_alt_trial_fail( 'Unable to create output directory: ' . $directory );
	}

	$encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $encoded ) || false === file_put_contents( $path, $encoded . "\n" ) ) {
		toolbox_media_alt_trial_fail( 'Unable to write JSON output: ' . $path );
	}

	toolbox_media_alt_trial_pass( 'Wrote trial JSON output.' );
}

function toolbox_media_alt_trial_markdown_text( string $value ): string {
	$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;
	return str_replace( array( '|', "\r", "\n" ), array( '\|', ' ', ' ' ), trim( $value ) );
}

function toolbox_media_alt_trial_write_markdown( string $path, array $payload ): void {
	if ( '' === $path ) {
		return;
	}

	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		toolbox_media_alt_trial_fail( 'Unable to create output directory: ' . $directory );
	}

	$summary = is_array( $payload['summary'] ?? null ) ? $payload['summary'] : array();
	$lines   = array(
		'# Media ALT/Caption Operator Trial Cases',
		'',
		'- Contract: `' . (string) ( $payload['contract'] ?? '' ) . '`',
		'- Created: `' . (string) ( $payload['created_at'] ?? '' ) . '`',
		'- Write posture: `' . (string) ( $payload['write_posture'] ?? '' ) . '`',
		'- Scanned: `' . (string) ( $summary['scanned'] ?? 0 ) . '`, selected: `' . (string) ( $summary['selected'] ?? 0 ) . '`, blocked: `' . (string) ( $summary['blocked'] ?? 0 ) . '`',
		'',
		'| Attachment | Status | Fact types | Reasons | Current ALT | Caption candidate | ALT candidates | Human outcome |',
		'| ---: | --- | --- | --- | --- | --- | --- | --- |',
	);

	foreach ( (array) ( $payload['cases'] ?? array() ) as $case ) {
		if ( ! is_array( $case ) ) {
			continue;
		}
		$metadata = is_array( $case['metadata'] ?? null ) ? $case['metadata'] : array();
		$candidate = is_array( $case['candidate'] ?? null ) ? $case['candidate'] : array();
		$lines[] = '| ' . (string) ( $case['attachment_id'] ?? '' )
			. ' | ' . toolbox_media_alt_trial_markdown_text( (string) ( $candidate['candidate_review_status'] ?? '' ) )
			. ' | ' . toolbox_media_alt_trial_markdown_text( implode( ', ', array_map( 'strval', (array) ( $candidate['candidate_fact_types'] ?? array() ) ) ) )
			. ' | ' . toolbox_media_alt_trial_markdown_text( implode( ', ', array_map( 'strval', (array) ( $candidate['review_reasons'] ?? array() ) ) ) )
			. ' | ' . toolbox_media_alt_trial_markdown_text( (string) ( $metadata['alt'] ?? '' ) )
			. ' | ' . toolbox_media_alt_trial_markdown_text( (string) ( $candidate['caption_candidate'] ?? '' ) )
			. ' | ' . toolbox_media_alt_trial_markdown_text( implode( '; ', array_map( 'strval', (array) ( $candidate['alt_candidates'] ?? array() ) ) ) )
			. ' | pending |';
	}
	$lines[] = '';
	$lines[] = 'Human outcome values should be accepted, edited, rejected, or misleading. This worksheet is local review evidence only; it is not a WordPress write authorization.';

	if ( false === file_put_contents( $path, implode( "\n", $lines ) . "\n" ) ) {
		toolbox_media_alt_trial_fail( 'Unable to write Markdown output: ' . $path );
	}

	toolbox_media_alt_trial_pass( 'Wrote trial Markdown output.' );
}

function toolbox_media_alt_trial_case_payload( array $selected, array $before, array $summary, array $reason_counts, int $max_items ): array {
	$cases = array();
	foreach ( $selected as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$attachment_id = absint( $item['attachment_id'] ?? 0 );
		if ( 0 >= $attachment_id || ! isset( $before[ $attachment_id ] ) ) {
			continue;
		}
		$cases[] = array(
			'case_id'       => 'attachment:' . $attachment_id,
			'attachment_id' => $attachment_id,
			'metadata'      => $before[ $attachment_id ],
			'candidate'     => array(
				'alt_candidates'             => array_values( array_map( 'strval', (array) ( $item['alt_candidates'] ?? array() ) ) ),
				'caption_candidate'          => (string) ( $item['caption_candidate'] ?? '' ),
				'current_alt_status'         => (string) ( $item['current_alt_status'] ?? '' ),
				'current_caption_status'     => (string) ( $item['current_caption_status'] ?? '' ),
				'review_reasons'             => array_values( array_map( 'strval', (array) ( $item['review_reasons'] ?? array() ) ) ),
				'candidate_basis'            => array_values( array_map( 'strval', (array) ( $item['candidate_basis'] ?? array() ) ) ),
				'candidate_quality_flags'    => array_values( array_map( 'strval', (array) ( $item['candidate_quality_flags'] ?? array() ) ) ),
				'candidate_quality'          => is_array( $item['candidate_quality'] ?? null ) ? $item['candidate_quality'] : array(),
				'candidate_quality_score'    => (int) ( $item['candidate_quality_score'] ?? 0 ),
				'candidate_quality_tier'     => (string) ( $item['candidate_quality_tier'] ?? '' ),
				'automation_recommendation'  => (string) ( $item['automation_recommendation'] ?? '' ),
				'visual_evidence_required'   => (bool) ( $item['visual_evidence_required'] ?? false ),
				'filtered_candidate_notes'   => array_values( array_map( 'strval', (array) ( $item['filtered_candidate_notes'] ?? array() ) ) ),
				'candidate_fact_types'       => array_values( array_map( 'strval', (array) ( $item['candidate_fact_types'] ?? array() ) ) ),
				'candidate_confidence'       => (string) ( $item['candidate_confidence'] ?? '' ),
				'candidate_review_status'    => (string) ( $item['candidate_review_status'] ?? '' ),
				'needs_context_confirmation' => (bool) ( $item['needs_context_confirmation'] ?? false ),
				'needs_human_visual_check'   => (bool) ( $item['needs_human_visual_check'] ?? false ),
				'direct_wordpress_write'     => (bool) ( $item['direct_wordpress_write'] ?? true ),
				'target_write_path'          => (string) ( $item['target_write_path'] ?? '' ),
				'operator_next_action'       => (string) ( $item['operator_next_action'] ?? '' ),
			),
			'human_review' => array(
				'outcome' => 'pending',
				'notes'   => '',
			),
		);
	}

	return array(
		'version'       => 1,
		'type'          => 'media_alt_caption_operator_trial',
		'contract'      => 'media_alt_caption_operator_trial.v1',
		'created_at'    => gmdate( 'c' ),
		'write_posture' => 'eval_only_no_wordpress_write',
		'provider_backed' => false,
		'source_policy' => 'media_library_metadata_only_no_pixel_vision',
		'summary'       => array(
			'scanned'       => (int) ( $summary['scanned_count'] ?? 0 ),
			'eligible'      => (int) ( $summary['eligible_count'] ?? 0 ),
			'selected'      => count( $selected ),
			'blocked'       => (int) ( $summary['blocked_count'] ?? 0 ),
			'max_items'     => $max_items,
			'reason_counts' => $reason_counts,
		),
		'cases'         => $cases,
	);
}

$arg_map = toolbox_media_alt_trial_arg_map( isset( $args ) && is_array( $args ) ? $args : array() );
$admin_user_id = toolbox_media_alt_trial_admin_user_id();
toolbox_media_alt_trial_assert( $admin_user_id > 0, 'Found an administrator user for the media ALT/caption trial.' );
wp_set_current_user( $admin_user_id );

$max_items      = max( 1, min( 10, absint( $arg_map['max_items'] ?? ( getenv( 'NPCINK_TOOLBOX_MEDIA_ALT_TRIAL_MAX_ITEMS' ) ?: 10 ) ) ) );
$explicit_ids   = toolbox_media_alt_trial_explicit_attachment_ids( (string) ( $arg_map['attachment_ids'] ?? ( getenv( 'MEDIA_ALT_CAPTION_TRIAL_ATTACHMENT_IDS' ) ?: '' ) ) );
$attachment_ids = ! empty( $explicit_ids ) ? array_slice( $explicit_ids, 0, $max_items ) : toolbox_media_alt_trial_attachment_ids( $max_items );
toolbox_media_alt_trial_assert( ! empty( $attachment_ids ), 'Found real image attachments for the media ALT/caption trial.' );

$before = toolbox_media_alt_trial_attachment_snapshot( $attachment_ids );
toolbox_media_alt_trial_assert( count( $before ) > 0, 'Captured before snapshot for real image attachments.' );

$cloud_filter_calls = 0;
add_filter(
	'npcink_toolbox_hosted_ai_site_helper_cloud_request',
	static function ( $handled, array $runtime_payload, array $input ) use ( &$cloud_filter_calls ) {
		++$cloud_filter_calls;
		toolbox_media_alt_trial_assert( 'media_alt_suggestions' === (string) ( $input['intent'] ?? '' ), 'Trial host filter receives the media ALT suggestions intent.' );

		return array(
			'status' => 'ready',
			'run_id' => 'local_media_alt_caption_trial',
			'result' => array(
				'status'      => 'ready',
				'model_id'    => 'local_trial_no_cloud_runtime',
				'output_text' => 'Local operator trial: use the metadata-only review set and visually confirm every selected item.',
			),
		);
	},
	10,
	3
);

$expected_source_policy = ! empty( $explicit_ids ) ? 'operator_supplied_media_metadata_only_no_pixel_vision' : 'media_library_metadata_only_no_pixel_vision';
$payload                = array(
	'intent'           => 'media_alt_suggestions',
	'focus'            => 'Local real media library operator trial',
	'media_scope'      => 'media_library_sample',
	'review_set_limit' => $max_items,
	'source_policy'    => $expected_source_policy,
);
if ( ! empty( $explicit_ids ) ) {
	$payload['media_snapshot'] = toolbox_media_alt_trial_media_snapshot( $attachment_ids, $before );
}

$data = toolbox_media_alt_trial_rest_request(
	$payload
);

toolbox_media_alt_trial_assert( 1 === $cloud_filter_calls, 'Trial used a local host filter instead of requiring Cloud runtime availability.' );
toolbox_media_alt_trial_assert( false === (bool) ( $data['direct_wordpress_write'] ?? true ), 'Site-helper response keeps direct WordPress writes disabled.' );
toolbox_media_alt_trial_assert( 'suggestion_only' === (string) ( $data['write_posture'] ?? '' ), 'Site-helper response remains suggestion-only.' );

$review_set = is_array( $data['media_alt_caption_review_set'] ?? null ) ? $data['media_alt_caption_review_set'] : array();
toolbox_media_alt_trial_assert( 'media_alt_caption_review_set.v1' === (string) ( $review_set['contract_version'] ?? '' ), 'Trial response returns the media ALT/caption review-set contract.' );
toolbox_media_alt_trial_assert( 'npcink-abilities-toolkit/build-media-alt-caption-review-set' === (string) ( $review_set['source_ability_id'] ?? '' ), 'Trial response is built by the Toolkit media ALT/caption review-set ability.' );
toolbox_media_alt_trial_assert( 'npcink-abilities-toolkit' === (string) ( $review_set['runtime_owner'] ?? '' ), 'Trial response reports Toolkit as the review-set runtime owner.' );
toolbox_media_alt_trial_assert( $expected_source_policy === (string) ( $review_set['source_policy'] ?? '' ), 'Trial uses metadata-only source policy.' );
toolbox_media_alt_trial_assert( false === (bool) ( $review_set['direct_wordpress_write'] ?? true ), 'Review set does not authorize direct WordPress writes.' );
toolbox_media_alt_trial_assert( false === (bool) ( $review_set['proposal_created'] ?? true ), 'Review set does not create a proposal.' );
toolbox_media_alt_trial_assert( false === (bool) ( $review_set['execution_created'] ?? true ), 'Review set does not create an execution.' );
toolbox_media_alt_trial_assert( false === (bool) ( $review_set['safety']['media_derivative_run_created'] ?? true ), 'Review set does not create a media derivative run.' );

$summary = is_array( $review_set['eligibility_summary'] ?? null ) ? $review_set['eligibility_summary'] : array();
$selected = is_array( $review_set['selected_items'] ?? null ) ? $review_set['selected_items'] : array();
$blocked  = is_array( $review_set['blocked_items'] ?? null ) ? $review_set['blocked_items'] : array();

toolbox_media_alt_trial_assert( (int) ( $summary['scanned_count'] ?? 0 ) === count( $before ), 'Trial scanned the real attachment sample.' );
toolbox_media_alt_trial_assert( count( $selected ) <= $max_items, 'Trial selected count stays within the configured cap.' );
toolbox_media_alt_trial_assert( (int) ( $summary['selected_count'] ?? -1 ) === count( $selected ), 'Eligibility summary selected count matches selected items.' );
toolbox_media_alt_trial_assert( (int) ( $summary['blocked_count'] ?? -1 ) === count( $blocked ), 'Eligibility summary blocked count matches blocked items.' );

foreach ( $selected as $item ) {
	toolbox_media_alt_trial_assert( true === (bool) ( $item['needs_human_visual_check'] ?? false ), 'Every selected item requires human visual review.' );
	toolbox_media_alt_trial_assert( false === (bool) ( $item['direct_wordpress_write'] ?? true ), 'Selected item keeps direct writes disabled.' );
}

$after = toolbox_media_alt_trial_attachment_snapshot( $attachment_ids );
foreach ( $before as $attachment_id => $before_item ) {
	$after_item = $after[ $attachment_id ] ?? array();
	toolbox_media_alt_trial_assert( $before_item === $after_item, 'Attachment ' . $attachment_id . ' metadata snapshot is unchanged.' );
}

$reason_counts = array();
foreach ( $selected as $item ) {
	foreach ( (array) ( $item['review_reasons'] ?? array() ) as $reason ) {
		$reason = sanitize_key( (string) $reason );
		if ( '' !== $reason ) {
			$reason_counts[ $reason ] = ( $reason_counts[ $reason ] ?? 0 ) + 1;
		}
	}
}
foreach ( $blocked as $item ) {
	$reason = sanitize_key( (string) ( $item['blocked_reason'] ?? '' ) );
	if ( '' !== $reason ) {
		$reason_counts[ 'blocked_' . $reason ] = ( $reason_counts[ 'blocked_' . $reason ] ?? 0 ) + 1;
	}
}

$case_payload = toolbox_media_alt_trial_case_payload( $selected, $before, $summary, $reason_counts, $max_items );
$output_json  = (string) ( $arg_map['output_json'] ?? ( getenv( 'NPCINK_TOOLBOX_MEDIA_ALT_TRIAL_OUTPUT_JSON' ) ?: '' ) );
$output_md    = (string) ( $arg_map['output_md'] ?? ( getenv( 'NPCINK_TOOLBOX_MEDIA_ALT_TRIAL_OUTPUT_MD' ) ?: '' ) );
toolbox_media_alt_trial_write_json( $output_json, $case_payload );
toolbox_media_alt_trial_write_markdown( $output_md, $case_payload );

echo 'INFO: Media ALT/caption trial summary=' . wp_json_encode( $case_payload['summary'] ) . PHP_EOL;
echo "Media ALT/caption operator trial passed.\n";
