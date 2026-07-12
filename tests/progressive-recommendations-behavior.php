<?php
/**
 * Focused behavior checks for editor progressive recommendations.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

$npcink_toolbox_progressive_cloud_calls = 0;
$npcink_toolbox_progressive_taxonomy_inputs = array();
$npcink_toolbox_progressive_transients = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private array $data;

		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): array {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;

		public function __construct( $data = null ) {
			$this->data = $data;
		}

		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params;

		public function __construct( array $params = array() ) {
			$this->params = $params;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params(): array {
			return $this->params;
		}
	}
}

function npcink_toolbox_progressive_assert( bool $condition, string $message ): void {
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
	return preg_replace( '/[^a-z0-9_\\-]/', '', $key ) ?? '';
}

function sanitize_text_field( $value ): string {
	return trim( preg_replace( '/\\s+/', ' ', wp_strip_all_tags( (string) $value ) ) ?? '' );
}

function sanitize_textarea_field( $value ): string {
	return trim( wp_strip_all_tags( (string) $value ) );
}

function sanitize_title( $value ): string {
	$value = strtolower( sanitize_text_field( $value ) );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ) ?? '', '-' );
}

function esc_url_raw( $value, $protocols = null ): string {
	return trim( (string) $value );
}

function wp_parse_url( string $url, int $component = -1 ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
}

function get_transient( string $key ) {
	global $npcink_toolbox_progressive_transients;
	return $npcink_toolbox_progressive_transients[ $key ] ?? false;
}

function set_transient( string $key, $value, int $expiration = 0 ): bool {
	global $npcink_toolbox_progressive_transients;
	$npcink_toolbox_progressive_transients[ $key ] = $value;
	return true;
}

function get_option( string $key, $default = false ) {
	return $default;
}

function apply_filters( string $hook, $value, ...$args ) {
	if ( in_array( $hook, array( 'npcink_toolbox_web_search_runtime_payload', 'npcink_toolbox_site_knowledge_runtime_payload', 'npcink_toolbox_hosted_ai_runtime_payload', 'npcink_toolbox_cloud_data_classification' ), true ) ) {
		return $value;
	}
	if ( 'npcink_toolbox_web_search_cloud_request' === $hook ) {
		$runtime_input = is_array( $args[1] ?? null ) ? $args[1] : array();
		$source_url = (string) ( $runtime_input['source_url'] ?? '' );
		return array(
			'status' => 'ok',
			'run_id' => 'writing-pack-source-run',
			'data'   => array(
				'result' => array(
					'artifact_type'    => 'source_extraction_preview',
					'contract_version' => 'source_extraction_preview.v1',
					'output_contract'  => 'source_extraction_preview.v1',
					'status'           => 'ready',
					'intent'           => 'source_extraction_preview',
					'requested_url'    => $source_url,
					'resolved_url'     => $source_url,
					'url_match'        => 'matched',
					'title'            => 'Exact source article',
					'content_hash'     => 'sha256-exact-source',
					'content_trust'    => 'untrusted_external_source',
					'prompt_injection_review_required' => true,
					'coverage'         => array( 'level' => 'partial', 'reader_bounded' => true, 'complete_capture_claimed' => false ),
					'results'          => array(
						array(
							'title'          => 'Exact source article',
							'url'            => $source_url,
							'reader_excerpt' => 'Bounded evidence about a staged source workflow.',
							'reader_status'  => 'ready',
						),
					),
				),
			),
		);
	}
	if ( 'npcink_toolbox_site_knowledge_cloud_request' === $hook ) {
		return array(
			'status' => 'ok',
			'run_id' => 'writing-pack-knowledge-run',
			'data'   => array(
				'result' => array(
					'status'  => 'ready',
					'intent'  => 'writing_support_plan',
					'results' => array(
						array( 'post_id' => 88, 'title' => 'Existing site article', 'url' => 'https://example.test/existing', 'score' => 0.82 ),
					),
				),
			),
		);
	}
	if ( 'npcink_toolbox_hosted_ai_cloud_request' === $hook ) {
		$runtime_input = is_array( $args[1] ?? null ) ? $args[1] : array();
		if ( 'article_draft_from_writing_pack' === (string) ( $runtime_input['intent'] ?? '' ) ) {
			return array(
				'status' => 'ok',
				'run_id' => 'writing-pack-draft-run',
				'data'   => array(
					'result' => array(
						'status'      => 'ready',
						'output_json' => array(
							'title'   => 'A reviewed writing-pack workflow',
							'excerpt' => 'A source-grounded planning and review path before drafting.',
							'sections' => array(
								array( 'heading' => 'Verify the source', 'body' => 'Review bounded source evidence before using factual claims.', 'supporting_fact_refs' => array( 'fact_ledger:0' ) ),
								array( 'heading' => 'Confirm the plan', 'body' => 'Confirm audience, focus, angle, and outline before draft generation.', 'supporting_fact_refs' => array() ),
							),
							'verification_notes' => array( 'Verify every source-supported claim.' ),
							'source_attribution_notes' => array( 'Confirm quotation and attribution requirements.' ),
						),
					),
				),
			);
		}
		return array(
			'status' => 'ok',
			'run_id' => 'writing-pack-ai-run',
			'data'   => array(
				'result' => array(
					'status'      => 'ready',
					'output_json' => array(
						'editorial_direction' => array( 'audience' => 'WordPress site operators', 'article_goal' => 'Plan a distinct article.', 'reader_problem' => 'Source use lacks review.', 'focus_points' => array( 'Evidence', 'Distinct angle' ) ),
						'research_basis' => array( 'source_summary' => array( 'A staged workflow.' ), 'fact_ledger' => array( array( 'claim' => 'The source describes stages.', 'evidence_basis' => 'reader_excerpt', 'verification_status' => 'source_supported' ) ) ),
						'site_adaptation' => array( 'overlap_map' => array( 'Governance basics already exist.' ), 'site_style_signals' => array( 'Use operational language.' ), 'unique_angle' => 'Focus on the pre-generation review gate.' ),
						'writing_plan' => array( 'title_directions' => array( 'From evidence to writing pack' ), 'reader_promise' => 'A safer planning path.', 'content_type' => 'tutorial', 'outline' => array( 'Verify', 'Compare', 'Review' ) ),
						'risk_review' => array( 'rights_risks' => array( 'Confirm quotation rights.' ), 'similarity_risks' => array( 'Do not mirror structure.' ) ),
					),
				),
			),
		);
	}

	return $value;
}

function wp_strip_all_tags( $value ): string {
	return strip_tags( (string) $value );
}

function wp_trim_words( $text, int $num_words = 55, ?string $more = null ): string {
	$words = preg_split( '/\\s+/', trim( (string) $text ) );
	if ( ! is_array( $words ) || count( $words ) <= $num_words ) {
		return trim( (string) $text );
	}
	return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( null === $more ? '' : $more );
}

function wp_json_encode( $value ): string {
	return (string) json_encode( $value );
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function rest_ensure_response( $value ): WP_REST_Response {
	return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value );
}

function get_object_taxonomies( string $post_type ): array {
	return 'post' === $post_type ? array( 'category', 'post_tag' ) : array();
}

function get_terms( array $args ): array {
	$taxonomy = (string) ( $args['taxonomy'] ?? '' );
	if ( 'category' === $taxonomy ) {
		return array(
			(object) array( 'term_id' => 11, 'name' => 'AI Workflow', 'slug' => 'ai-workflow', 'description' => 'AI workflow planning', 'count' => 8 ),
			(object) array( 'term_id' => 12, 'name' => 'Operations', 'slug' => 'operations', 'description' => 'Operator process', 'count' => 5 ),
			(object) array( 'term_id' => 13, 'name' => 'This', 'slug' => 'this', 'description' => 'this', 'count' => 9 ),
			(object) array( 'term_id' => 14, 'name' => '渐进推荐', 'slug' => 'progressive-recommendation', 'description' => '渐进推荐 系统', 'count' => 6 ),
			(object) array( 'term_id' => 15, 'name' => 'Post Formats', 'slug' => 'post-formats', 'description' => 'post format archive', 'count' => 12 ),
			(object) array( 'term_id' => 16, 'name' => 'Editorial Ops', 'slug' => 'editorial-ops', 'description' => 'workflow only', 'count' => 10 ),
		);
	}
	if ( 'post_tag' === $taxonomy ) {
		return array(
			(object) array( 'term_id' => 21, 'name' => 'recommendation', 'slug' => 'recommendation', 'description' => 'recommendation system', 'count' => 7 ),
			(object) array( 'term_id' => 22, 'name' => 'latency', 'slug' => 'latency', 'description' => 'fast response', 'count' => 4 ),
		);
	}
	return array();
}

function npcink_abilities_toolkit_get_registered(): array {
	return array(
		'npcink-abilities-toolkit/suggest-post-taxonomy-terms' => array(
			'execute_callback' => 'npcink_toolbox_progressive_taxonomy_suggestions',
		),
	);
}

function npcink_toolbox_progressive_taxonomy_suggestion_item( string $taxonomy, int $term_id, string $name, string $slug, array $signals, string $reason ): array {
	return array(
		'taxonomy'        => $taxonomy,
		'term_id'         => $term_id,
		'name'            => $name,
		'slug'            => $slug,
		'score'           => 4.5,
		'confidence'      => 0.9,
		'match_signals'   => $signals,
		'reason'          => $reason,
		'evidence_refs'   => array( 'toolkit_stub' ),
		'related_context' => array(
			'source_count' => in_array( 'related_site_knowledge_term', $signals, true ) ? 1 : 0,
		),
	);
}

function npcink_toolbox_progressive_taxonomy_suggestions( array $input ): array {
	global $npcink_toolbox_progressive_taxonomy_inputs;
	$npcink_toolbox_progressive_taxonomy_inputs[] = $input;

	$title = (string) ( $input['title'] ?? '' );
	$items = array();
	if ( false !== stripos( $title, 'AI Workflow' ) || false !== stripos( $title, 'Fast AI recommendation workflow' ) ) {
		$items[] = npcink_toolbox_progressive_taxonomy_suggestion_item(
			'category',
			11,
			'AI Workflow',
			'ai-workflow',
			array( 'current_draft_match', 'title_term_name_match' ),
			'Matched tokens: ai, workflow.'
		);
		$items[] = npcink_toolbox_progressive_taxonomy_suggestion_item(
			'post_tag',
			21,
			'recommendation',
			'recommendation',
			array( 'current_draft_match' ),
			'Matched tokens: recommendation.'
		);
	}
	if ( false !== strpos( $title, '渐进推荐' ) ) {
		$items[] = npcink_toolbox_progressive_taxonomy_suggestion_item(
			'category',
			14,
			'渐进推荐',
			'progressive-recommendation',
			array( 'current_draft_match', 'title_term_name_match' ),
			'Matched tokens: 渐进推荐.'
		);
	}

	return array(
		'success' => true,
		'data'    => array(
			'artifact_type'          => 'article_taxonomy_suggestions.v1',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'taxonomy_terms'         => array(
				'candidate_type'         => 'taxonomy_tag_candidates',
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
				'ranking_context'        => array(
					'related_term_policy' => 'ranking_evidence_only_no_term_creation_or_assignment',
				),
				'items'                  => $items,
			),
		),
	);
}

function get_posts( array $args ): array {
	return array(
		(object) array( 'ID' => 31, 'post_type' => 'attachment', 'post_title' => 'AI workflow diagram', 'post_excerpt' => 'Recommendation workflow', 'post_content' => 'Fast recommendation pipeline' ),
	);
}

function get_post( int $post_id ) {
	if ( 31 === $post_id ) {
		return (object) array( 'ID' => 31, 'post_type' => 'attachment', 'post_title' => 'AI workflow diagram', 'post_excerpt' => 'Recommendation workflow', 'post_content' => 'Fast recommendation pipeline' );
	}
	return null;
}

function get_post_type( $post ): string {
	return is_object( $post ) ? (string) ( $post->post_type ?? '' ) : '';
}

function wp_attachment_is_image( int $attachment_id ): bool {
	return 31 === $attachment_id;
}

function wp_get_attachment_image_src( int $attachment_id, string $size ) {
	return array( 'https://example.test/workflow-thumb.jpg', 300, 200 );
}

function get_post_meta( int $post_id, string $key, bool $single = false ): string {
	return '_wp_attachment_image_alt' === $key ? 'AI workflow recommendation diagram' : '';
}

function wp_get_attachment_url( int $attachment_id ): string {
	return 'https://example.test/workflow.jpg';
}

function npcink_cloud_addon_runtime_client() {
	global $npcink_toolbox_progressive_cloud_calls;
	++$npcink_toolbox_progressive_cloud_calls;
	return null;
}

if ( ! class_exists( 'Npcink_Toolbox\\Plugin' ) ) {
	class Npcink_Toolbox_Progressive_Plugin_Stub {
		public const OPTION_NAME         = 'npcink_toolbox_settings';
		public const CONTEXT_OPTION_NAME = 'npcink_toolbox_content_context';
		public const MEDIA_OPTION_NAME   = 'npcink_toolbox_media_settings';
	}
	class_alias( Npcink_Toolbox_Progressive_Plugin_Stub::class, 'Npcink_Toolbox\\Plugin' );
}

require_once dirname( __DIR__ ) . '/includes/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Client.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Controller.php';

$settings   = new Npcink_Toolbox\Settings();
$client     = new Npcink_Toolbox\Provider_Client( $settings );
$controller = new Npcink_Toolbox\Rest_Controller( $settings, $client );

function npcink_toolbox_progressive_request( Npcink_Toolbox\Rest_Controller $controller, array $payload ): array {
	$response = $controller->editor_content_support( new WP_REST_Request( $payload ) );
	$data     = $response instanceof WP_REST_Response ? $response->get_data() : $response;
	return is_array( $data ) ? $data : array();
}

function npcink_toolbox_progressive_section( array $data ): array {
	return is_array( $data['sections']['progressive_recommendations'] ?? null ) ? $data['sections']['progressive_recommendations'] : array();
}

function npcink_toolbox_progressive_candidates_by_kind( array $section, string $kind ): array {
	$candidates = is_array( $section['recommendation_candidates'] ?? null ) ? $section['recommendation_candidates'] : array();
	return array_values(
		array_filter(
			$candidates,
			static fn( array $candidate ): bool => $kind === (string) ( $candidate['kind'] ?? '' )
		)
	);
}

function npcink_toolbox_progressive_candidate_by_value( array $candidates, string $value ): array {
	foreach ( $candidates as $candidate ) {
		if ( $value === (string) ( $candidate['value'] ?? '' ) ) {
			return $candidate;
		}
	}
	return array();
}

$response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent' => 'progressive_recommendations',
		)
	)
);
$data     = $response instanceof WP_REST_Response ? $response->get_data() : $response;

npcink_toolbox_progressive_assert( is_array( $data ), 'Progressive response is an array payload.' );
npcink_toolbox_progressive_assert( 'editor_content_support_flow' === ( $data['artifact_type'] ?? '' ), 'Progressive response keeps the editor content-support flow artifact.' );
npcink_toolbox_progressive_assert( isset( $data['sections']['progressive_recommendations'] ), 'Progressive response includes the local progressive section even without draft text.' );
npcink_toolbox_progressive_assert( 'editor_progressive_recommendations.v1' === ( $data['sections']['progressive_recommendations']['artifact_type'] ?? '' ), 'Progressive section declares the v1 artifact.' );
npcink_toolbox_progressive_assert( false === ( $data['sections']['progressive_recommendations']['remote_execution_policy']['cloud_calls'] ?? true ), 'Progressive section declares no Cloud calls.' );
npcink_toolbox_progressive_assert( 'editor_recommendation_set.v1' === ( $data['recommendation_set']['contract_version'] ?? '' ), 'Progressive response includes the recommendation set wrapper.' );
npcink_toolbox_progressive_assert( 0 === $npcink_toolbox_progressive_cloud_calls, 'Progressive response does not touch the Cloud runtime.' );

$response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'        => 'progressive_recommendations',
			'post_id'       => 123,
			'post_type'     => 'post',
			'title'         => 'Fast AI recommendation workflow',
			'excerpt'       => 'A progressive recommendation system for operators.',
			'content'       => 'The editor should show high confidence recommendations before deeper Cloud research.',
			'category_ids'  => '',
			'tag_ids'       => '',
			'featured_media' => '',
		)
	)
);
$data     = $response instanceof WP_REST_Response ? $response->get_data() : $response;
$section  = is_array( $data['sections']['progressive_recommendations'] ?? null ) ? $data['sections']['progressive_recommendations'] : array();
$set      = is_array( $data['recommendation_set'] ?? null ) ? $data['recommendation_set'] : array();

npcink_toolbox_progressive_assert( ! empty( $section['recommendation_candidates'] ), 'Progressive response returns reviewable recommendation candidates.' );
npcink_toolbox_progressive_assert( ! empty( $section['preflight_candidates'] ), 'Progressive response converts local preflight warnings into candidates.' );
npcink_toolbox_progressive_assert( ! empty( $section['media_library_candidates'] ), 'Progressive response includes bounded local media-library candidates.' );
npcink_toolbox_progressive_assert( ! empty( $set['artifacts']['preflight'] ), 'Recommendation set counts preflight candidates.' );
npcink_toolbox_progressive_assert( ! empty( $set['artifact_counts']['preflight'] ), 'Recommendation set exposes additive artifact_counts metadata.' );
npcink_toolbox_progressive_assert( true === ( $set['no_write'] ?? false ), 'Recommendation set declares no_write=true.' );
npcink_toolbox_progressive_assert( 'local' === ( $set['source_layer'] ?? '' ), 'Progressive recommendation set declares the local source layer.' );
npcink_toolbox_progressive_assert( ! empty( $set['generated_at'] ), 'Recommendation set includes a generated_at timestamp.' );
npcink_toolbox_progressive_assert( is_array( $set['retrieval_sources'] ?? null ) && in_array( 'current_editor_context', $set['retrieval_sources'], true ), 'Recommendation set exposes retrieval sources at the wrapper level.' );
npcink_toolbox_progressive_assert( is_array( $set['candidates'] ?? null ) && ! empty( $set['candidates'] ), 'Recommendation set exposes lightweight candidate refs.' );
npcink_toolbox_progressive_assert( false === ( $set['governance']['direct_wordpress_write'] ?? true ), 'Recommendation set preserves no direct WordPress write posture.' );
npcink_toolbox_progressive_assert( is_array( $set['proposal_targets'] ?? null ), 'Recommendation set exposes definition-only Core handoff targets.' );
foreach ( $set['proposal_targets'] as $proposal_target ) {
	npcink_toolbox_progressive_assert( ! empty( $proposal_target['candidate_id'] ) && ! empty( $proposal_target['required_ability_id'] ), 'Core handoff target links a candidate to a stable ability id.' );
	npcink_toolbox_progressive_assert( 'definition_only_user_trigger_required' === ( $proposal_target['handoff_status'] ?? '' ), 'Core handoff target remains definition-only.' );
	npcink_toolbox_progressive_assert( false === ( $proposal_target['direct_wordpress_write'] ?? true ), 'Core handoff target does not authorize Toolbox writes.' );
	npcink_toolbox_progressive_assert( ! isset( $proposal_target['core_route'] ) && ! isset( $proposal_target['rest_route'] ) && ! isset( $proposal_target['execution_status'] ) && ! isset( $proposal_target['approval_status'] ), 'Core handoff target omits raw routes and runtime state.' );
}
npcink_toolbox_progressive_assert( 0 === $npcink_toolbox_progressive_cloud_calls, 'Progressive response with draft context still does not touch the Cloud runtime.' );

$empty_section = npcink_toolbox_progressive_section( npcink_toolbox_progressive_request( $controller, array( 'intent' => 'progressive_recommendations' ) ) );
npcink_toolbox_progressive_assert( array() === npcink_toolbox_progressive_candidates_by_kind( $empty_section, 'category' ) && array() === npcink_toolbox_progressive_candidates_by_kind( $empty_section, 'tag' ), 'Progressive empty-context prefetch keeps taxonomy profile out of high-confidence candidates.' );

$stopword_section = npcink_toolbox_progressive_section(
	npcink_toolbox_progressive_request(
		$controller,
		array(
			'intent'    => 'progressive_recommendations',
			'post_type' => 'post',
			'title'     => 'This is the article',
			'excerpt'   => 'This is for the editor.',
			'content'   => 'This is the local context.',
		)
	)
);
$stopword_categories = npcink_toolbox_progressive_candidates_by_kind( $stopword_section, 'category' );
npcink_toolbox_progressive_assert(
	! array_filter(
		$stopword_categories,
		static fn( array $candidate ): bool => 'This' === (string) ( $candidate['value'] ?? '' )
	),
	'Progressive taxonomy delegation does not locally add English stopword-only matches.'
);

$generic_taxonomy_section = npcink_toolbox_progressive_section(
	npcink_toolbox_progressive_request(
		$controller,
		array(
			'intent'    => 'progressive_recommendations',
			'post_type' => 'post',
			'title'     => 'WordPress post editor checklist',
			'excerpt'   => 'A post workflow note.',
			'content'   => 'The post editor should show local recommendations.',
		)
	)
);
$generic_taxonomy_categories = npcink_toolbox_progressive_candidates_by_kind( $generic_taxonomy_section, 'category' );
npcink_toolbox_progressive_assert(
	! array_filter(
		$generic_taxonomy_categories,
		static fn( array $candidate ): bool => 'Post Formats' === (string) ( $candidate['value'] ?? '' )
	),
	'Progressive taxonomy delegation does not locally add generic WordPress taxonomy tokens such as post and format.'
);

$exact_title_section = npcink_toolbox_progressive_section(
	npcink_toolbox_progressive_request(
		$controller,
		array(
			'intent'    => 'progressive_recommendations',
			'post_type' => 'post',
			'title'     => 'AI Workflow for editors',
			'excerpt'   => 'A local recommendations note.',
			'content'   => 'Editors need a predictable recommendation cockpit.',
		)
	)
);
$exact_title_category = npcink_toolbox_progressive_candidate_by_value(
	npcink_toolbox_progressive_candidates_by_kind( $exact_title_section, 'category' ),
	'AI Workflow'
);
npcink_toolbox_progressive_assert(
	! empty( $exact_title_category )
	&& in_array( '词条名称在标题中完整出现，优先级更高。', $exact_title_category['quality_issues'] ?? array(), true ),
	'Progressive taxonomy delegation displays Toolkit exact title term-name evidence.'
);
global $npcink_toolbox_progressive_taxonomy_inputs;
$latest_taxonomy_input = end( $npcink_toolbox_progressive_taxonomy_inputs );
npcink_toolbox_progressive_assert(
	is_array( $latest_taxonomy_input )
	&& 'both' === ( $latest_taxonomy_input['taxonomy'] ?? '' )
	&& 'AI Workflow for editors' === ( $latest_taxonomy_input['title'] ?? '' )
	&& 'post' === ( $latest_taxonomy_input['post_type'] ?? '' ),
	'Progressive taxonomy delegation passes draft context to the Toolkit ability.'
);

$single_token_section = npcink_toolbox_progressive_section(
	npcink_toolbox_progressive_request(
		$controller,
		array(
			'intent'    => 'progressive_recommendations',
			'post_type' => 'post',
			'title'     => 'AI hype',
			'excerpt'   => 'Short editorial note.',
			'content'   => 'Brief local note.',
		)
	)
);
$single_token_categories = npcink_toolbox_progressive_candidates_by_kind( $single_token_section, 'category' );
npcink_toolbox_progressive_assert(
	! npcink_toolbox_progressive_candidate_by_value( $single_token_categories, 'AI Workflow' ),
	'Progressive taxonomy delegation does not locally add single-token weak matches.'
);

$description_only_section = npcink_toolbox_progressive_section(
	npcink_toolbox_progressive_request(
		$controller,
		array(
			'intent'    => 'progressive_recommendations',
			'post_type' => 'post',
			'title'     => 'Workflow checklist',
			'excerpt'   => 'A local workflow note.',
			'content'   => 'Workflow review only.',
		)
	)
);
$description_only_categories = npcink_toolbox_progressive_candidates_by_kind( $description_only_section, 'category' );
npcink_toolbox_progressive_assert(
	! npcink_toolbox_progressive_candidate_by_value( $description_only_categories, 'Editorial Ops' ),
	'Progressive taxonomy delegation does not locally add description-only matches.'
);

$chinese_section = npcink_toolbox_progressive_section(
	npcink_toolbox_progressive_request(
		$controller,
		array(
			'intent'    => 'progressive_recommendations',
			'post_type' => 'post',
			'title'     => '渐进推荐 系统',
			'excerpt'   => '编辑器本地推荐候选。',
			'content'   => '渐进推荐 要先返回高置信候选。',
		)
	)
);
$chinese_categories = npcink_toolbox_progressive_candidates_by_kind( $chinese_section, 'category' );
npcink_toolbox_progressive_assert(
	(bool) array_filter(
		$chinese_categories,
		static fn( array $candidate ): bool => '渐进推荐' === (string) ( $candidate['value'] ?? '' )
	),
	'Progressive taxonomy delegation displays Toolkit Chinese title taxonomy matches.'
);

$preflight_candidates = npcink_toolbox_progressive_candidates_by_kind( $section, 'preflight' );
$preflight_targets    = array_column( $preflight_candidates, 'target_field' );
npcink_toolbox_progressive_assert(
	! empty( $preflight_candidates )
	&& ! array_filter(
		$preflight_candidates,
		static fn( array $candidate ): bool => 'operator_review_only_no_write' !== (string) ( $candidate['action_policy'] ?? '' )
	),
	'Progressive preflight candidates are operator-review-only no-write items.'
);
npcink_toolbox_progressive_assert(
	array( 'taxonomy_terms', 'featured_media' ) === array_values( array_slice( $preflight_targets, 0, 2 ) ),
	'Progressive preflight candidates keep a stable local review order for the active draft context.'
);

$writing_pack_method = new ReflectionMethod( Npcink_Toolbox\Rest_Controller::class, 'editor_article_writing_pack' );
$writing_pack_method->setAccessible( true );
$writing_pack = $writing_pack_method->invoke(
	$controller,
	array(
		'source_url'      => 'https://example.com/reference',
		'input_mode'      => 'url_reference',
		'user_instruction' => '',
	),
	array(
		'status'        => 'ready',
		'url_match'     => 'matched',
		'requested_url' => 'https://example.com/reference',
		'resolved_url'  => 'https://example.com/reference',
		'title'         => 'Reference article',
		'content_hash'  => 'sha256-source',
		'content_trust' => 'untrusted_external_source',
		'coverage'      => array(
			'level'                    => 'partial',
			'reader_bounded'           => true,
			'complete_capture_claimed' => false,
		),
	),
	array(
		'results' => array(
			array(
				'post_id' => 88,
				'title'   => 'Existing site article',
				'url'     => 'https://example.test/existing',
				'score'   => 0.82,
			),
		),
	),
	array(
		'status'      => 'ready',
		'output_json' => array(
			'editorial_direction' => array(
				'audience'       => 'WordPress site operators',
				'article_goal'   => 'Help operators decide how to use source evidence safely.',
				'reader_problem' => 'External references are easy to copy without adapting.',
				'focus_points'   => array( 'Exact evidence', 'Distinct site angle' ),
			),
			'research_basis' => array(
				'source_summary' => array( 'The source describes a bounded workflow.' ),
				'fact_ledger'    => array(
					array(
						'claim'               => 'The source uses a staged workflow.',
						'evidence_basis'      => 'bounded_reader_excerpt',
						'verification_status' => 'source_supported',
					),
				),
			),
			'site_adaptation' => array(
				'overlap_map'        => array( 'Existing article covers governance basics.' ),
				'site_style_signals' => array( 'Use concise operational language.' ),
				'unique_angle'       => 'Focus on the review gate before generation.',
			),
			'writing_plan' => array(
				'title_directions' => array( 'From source evidence to a reviewed writing pack' ),
				'reader_promise'   => 'A safer article-planning path.',
				'content_type'     => 'tutorial',
				'outline'          => array( 'Verify source', 'Compare site coverage', 'Review the pack' ),
			),
			'risk_review' => array(
				'rights_risks'     => array( 'Confirm quotation and image rights.' ),
				'similarity_risks' => array( 'Do not mirror the source structure.' ),
			),
		),
	),
	'Bounded exact source evidence for a staged workflow.'
);
npcink_toolbox_progressive_assert(
	'article_writing_pack.v1' === ( $writing_pack['artifact_type'] ?? '' )
	&& 'url_reference' === ( $writing_pack['input_mode'] ?? '' )
	&& false === ( $writing_pack['inputs']['editorial_brief']['audience']['operator_confirmed'] ?? true )
	&& false === ( $writing_pack['inputs']['editorial_brief']['focus_points']['operator_confirmed'] ?? true )
	&& 'WordPress site operators' === ( $writing_pack['inputs']['editorial_brief']['audience']['value'] ?? '' )
	&& 'source_supported' === ( $writing_pack['research_basis']['fact_ledger'][0]['verification_status'] ?? '' )
	&& 88 === ( $writing_pack['site_adaptation']['related_articles'][0]['post_id'] ?? 0 )
	&& false === ( $writing_pack['generation_admission']['article_generation_allowed'] ?? true )
	&& empty( $writing_pack['generation_admission']['missing_required_fields'] )
	&& 'suggestion_only' === ( $writing_pack['write_posture'] ?? '' )
	&& false === ( $writing_pack['direct_wordpress_write'] ?? true )
	&& str_starts_with( (string) ( $writing_pack['content_fingerprint'] ?? '' ), 'sha256:' ),
	'Article writing pack behavior keeps URL evidence, inferred editorial fields, Site Knowledge overlap, traceable facts, and no-generation/no-write admission.'
);

$unsupported_writing_pack_mode = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'     => 'source_adaptation_review',
			'input_mode' => 'freeform_legacy',
		)
	)
);
npcink_toolbox_progressive_assert(
	is_wp_error( $unsupported_writing_pack_mode )
	&& 'npcink_toolbox_writing_pack_input_mode_not_supported' === $unsupported_writing_pack_mode->get_error_code(),
	'Article writing pack rejects unknown input modes instead of silently falling back to URL mode.'
);

$manual_writing_pack_response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'       => 'source_adaptation_review',
			'input_mode'   => 'manual_brief',
			'source_stage' => 'research_plan',
			'editorial_brief' => array(
				'audience'       => 'Independent WordPress publishers',
				'article_goal'   => 'Explain a review-first drafting workflow.',
				'reader_problem' => 'AI drafts can drift from editorial intent.',
				'focus_points'   => array( 'Structured planning', 'Human confirmation' ),
			),
		)
	)
);
$manual_writing_pack_data = $manual_writing_pack_response instanceof WP_REST_Response ? $manual_writing_pack_response->get_data() : $manual_writing_pack_response;
npcink_toolbox_progressive_assert(
	is_array( $manual_writing_pack_data )
	&& 'article_writing_pack.v1' === ( $manual_writing_pack_data['artifact_type'] ?? '' )
	&& 'manual_brief' === ( $manual_writing_pack_data['input_mode'] ?? '' )
	&& empty( $manual_writing_pack_data['sections']['article_writing_pack']['inputs']['source_materials'] ?? array( 'unexpected' ) )
	&& empty( $manual_writing_pack_data['sections']['article_writing_pack']['research_basis']['fact_ledger'] ?? array( 'unexpected' ) )
	&& 'Independent WordPress publishers' === ( $manual_writing_pack_data['sections']['article_writing_pack']['inputs']['editorial_brief']['audience']['value'] ?? '' )
	&& true === ( $manual_writing_pack_data['sections']['article_writing_pack']['inputs']['editorial_brief']['audience']['operator_confirmed'] ?? false )
	&& false === ( $manual_writing_pack_data['sections']['article_writing_pack']['generation_admission']['article_generation_allowed'] ?? true ),
	'Manual brief mode builds the same writing-pack artifact without inventing a URL source or admitting draft generation.'
);

$writing_pack_response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'       => 'source_adaptation_review',
			'input_mode'   => 'url_reference',
			'source_stage' => 'research_plan',
			'source_url'   => 'https://example.com/reference',
			'user_instruction' => 'This must be ignored until a typed manual input mode exists.',
		)
	)
);
$writing_pack_response_data = $writing_pack_response instanceof WP_REST_Response ? $writing_pack_response->get_data() : $writing_pack_response;
npcink_toolbox_progressive_assert(
	is_array( $writing_pack_response_data )
	&& 'article_writing_pack.v1' === ( $writing_pack_response_data['artifact_type'] ?? '' )
	&& 'url_reference' === ( $writing_pack_response_data['input_mode'] ?? '' )
	&& 'source_extraction_preview.v1' === ( $writing_pack_response_data['sections']['source_article']['output_contract'] ?? '' )
	&& 'Existing site article' === ( $writing_pack_response_data['sections']['article_writing_pack']['site_adaptation']['related_articles'][0]['title'] ?? '' )
	&& 'WordPress site operators' === ( $writing_pack_response_data['sections']['article_writing_pack']['inputs']['editorial_brief']['audience']['value'] ?? '' )
	&& '' === ( $writing_pack_response_data['sections']['article_writing_pack']['inputs']['editorial_brief']['operator_instruction']['value'] ?? 'unexpected' )
	&& false === ( $writing_pack_response_data['sections']['article_writing_pack']['inputs']['source_materials'][0]['operator_confirmed'] ?? true )
	&& false === ( $writing_pack_response_data['sections']['article_writing_pack']['generation_admission']['article_generation_allowed'] ?? true )
	&& 'operator_review_only_no_insert' === ( $writing_pack_response_data['final_write_path'] ?? '' )
	&& false === ( $writing_pack_response_data['direct_wordpress_write'] ?? true ),
	'Article writing pack route composes exact-source, Site Knowledge, hosted planning, and no-generation/no-write boundaries end to end.'
);

$mixed_writing_pack_response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'       => 'source_adaptation_review',
			'input_mode'   => 'mixed',
			'source_stage' => 'research_plan',
			'source_url'   => 'https://example.com/reference',
			'editorial_brief' => array(
				'audience'       => 'Plugin maintainers',
				'article_goal'   => 'Show why confirmation precedes drafting.',
				'focus_points'   => array( 'Fingerprint review', 'No automatic write' ),
			),
		)
	)
);
$mixed_writing_pack_data = $mixed_writing_pack_response instanceof WP_REST_Response ? $mixed_writing_pack_response->get_data() : $mixed_writing_pack_response;
npcink_toolbox_progressive_assert(
	is_array( $mixed_writing_pack_data )
	&& 'mixed' === ( $mixed_writing_pack_data['input_mode'] ?? '' )
	&& 'Plugin maintainers' === ( $mixed_writing_pack_data['sections']['article_writing_pack']['inputs']['editorial_brief']['audience']['value'] ?? '' )
	&& true === ( $mixed_writing_pack_data['sections']['article_writing_pack']['inputs']['editorial_brief']['audience']['operator_confirmed'] ?? false )
	&& 'matched' === ( $mixed_writing_pack_data['sections']['article_writing_pack']['inputs']['source_materials'][0]['url_match'] ?? '' ),
	'Mixed mode keeps exact URL evidence while operator editorial fields override AI inference in the same contract.'
);

$reviewed_pack = $writing_pack_response_data['sections']['article_writing_pack'];
$unconfirmed_draft_response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'                => 'source_adaptation_review',
			'input_mode'            => 'url_reference',
			'source_stage'          => 'draft',
			'reviewed_writing_pack' => $reviewed_pack,
			'writing_pack_confirmation' => array(
				'status'                   => 'needs_review',
				'confirmed'                => false,
				'base_content_fingerprint' => $reviewed_pack['content_fingerprint'],
			),
		)
	)
);
npcink_toolbox_progressive_assert(
	is_wp_error( $unconfirmed_draft_response )
	&& 'npcink_toolbox_writing_pack_review_confirmation_required' === $unconfirmed_draft_response->get_error_code(),
	'Draft generation fails closed when the operator confirmation envelope is absent.'
);

$stale_confirmation_response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'                => 'source_adaptation_review',
			'input_mode'            => 'url_reference',
			'source_stage'          => 'draft',
			'reviewed_writing_pack' => $reviewed_pack,
			'writing_pack_confirmation' => array(
				'status'                   => 'confirmed_by_operator',
				'confirmed'                => true,
				'base_content_fingerprint' => 'sha256:stale-pack',
			),
		)
	)
);
npcink_toolbox_progressive_assert(
	is_wp_error( $stale_confirmation_response )
	&& 409 === (int) ( $stale_confirmation_response->get_error_data()['status'] ?? 0 )
	&& 'npcink_toolbox_writing_pack_review_fingerprint_mismatch' === $stale_confirmation_response->get_error_code(),
	'Draft generation rejects a confirmation envelope whose base fingerprint does not match the reviewed pack.'
);

$confirmed_draft_response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'                => 'source_adaptation_review',
			'input_mode'            => 'url_reference',
			'source_stage'          => 'draft',
			'reviewed_writing_pack' => $reviewed_pack,
			'writing_pack_confirmation' => array(
				'status'                   => 'confirmed_by_operator',
				'confirmed'                => true,
				'base_content_fingerprint' => $reviewed_pack['content_fingerprint'],
			),
		)
	)
);
$confirmed_draft_data = $confirmed_draft_response instanceof WP_REST_Response ? $confirmed_draft_response->get_data() : $confirmed_draft_response;
npcink_toolbox_progressive_assert(
	is_array( $confirmed_draft_data )
	&& 'article_draft_preview.v1' === ( $confirmed_draft_data['artifact_type'] ?? '' )
	&& 'article_writing_pack_review.v1' === ( $confirmed_draft_data['sections']['writing_pack_review']['artifact_type'] ?? '' )
	&& true === ( $confirmed_draft_data['sections']['writing_pack_review']['article_generation_allowed'] ?? false )
	&& false === ( $confirmed_draft_data['sections']['writing_pack_review']['durable_approval_state'] ?? true )
	&& 'ready' === ( $confirmed_draft_data['sections']['article_draft_preview']['status'] ?? '' )
	&& 'Verify the source' === ( $confirmed_draft_data['sections']['article_draft_preview']['sections'][0]['heading'] ?? '' )
	&& false === ( $confirmed_draft_data['sections']['article_draft_preview']['direct_wordpress_write'] ?? true )
	&& false === ( $confirmed_draft_data['sections']['article_draft_preview']['body_insertion'] ?? true )
	&& 'operator_review_only_no_insert' === ( $confirmed_draft_data['final_write_path'] ?? '' ),
	'Confirmed writing packs admit one synchronous structured draft preview without durable approval state, insertion, save, or publish.'
);

$legacy_adapt_response = $controller->editor_content_support(
	new WP_REST_Request(
		array(
			'intent'       => 'source_adaptation_review',
			'input_mode'   => 'url_reference',
			'source_stage' => 'adapt',
			'source_url'   => 'https://example.com/reference',
		)
	)
);
$legacy_adapt_data = $legacy_adapt_response instanceof WP_REST_Response ? $legacy_adapt_response->get_data() : $legacy_adapt_response;
npcink_toolbox_progressive_assert(
	is_array( $legacy_adapt_data )
	&& 'source_adaptation_review.v1' === ( $legacy_adapt_data['artifact_type'] ?? '' )
	&& 'article_writing_pack.v1' === ( $legacy_adapt_data['primary_artifact_type'] ?? '' )
	&& 'article_writing_pack.v1' === ( $legacy_adapt_data['sections']['article_writing_pack']['artifact_type'] ?? '' ),
	'Legacy adapt stage preserves its outer artifact while adding the canonical article writing pack.'
);
