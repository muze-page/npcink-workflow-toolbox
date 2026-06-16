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

function esc_url_raw( $value ): string {
	return trim( (string) $value );
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
	'Progressive taxonomy ranking ignores English stopword-only matches.'
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
	'Progressive taxonomy ranking ignores generic WordPress taxonomy tokens such as post and format.'
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
	'Progressive taxonomy ranking supports Chinese title taxonomy matches.'
);

$preflight_candidates = npcink_toolbox_progressive_candidates_by_kind( $section, 'preflight' );
npcink_toolbox_progressive_assert(
	! empty( $preflight_candidates )
	&& ! array_filter(
		$preflight_candidates,
		static fn( array $candidate ): bool => 'operator_review_only_no_write' !== (string) ( $candidate['action_policy'] ?? '' )
	),
	'Progressive preflight candidates are operator-review-only no-write items.'
);
