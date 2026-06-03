<?php
/**
 * Minimal third-party provider client for Toolbox actions.
 *
 * @package Magick_AI_Toolbox
 */

namespace Magick_AI_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Client {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function web_research( string $query, array $options = array() ) {
		$api_key = $this->settings->get_tavily_api_key();
		if ( '' === $api_key ) {
			return $this->missing_secret_error( 'tavily_api_key', __( 'Configure a Tavily API key before running web research.', 'magick-ai-toolbox' ) );
		}

		$body = array(
			'query'               => $query,
			'search_depth'        => (string) $this->settings->get( 'tavily_search_depth' ),
			'include_answer'      => (bool) $this->settings->get( 'tavily_include_answer' ),
			'include_raw_content' => (bool) $this->settings->get( 'tavily_include_raw' ),
			'include_images'      => (bool) $this->settings->get( 'tavily_include_images' ),
			'include_favicon'     => true,
			'max_results'         => max( 1, min( 10, (int) ( $options['max_results'] ?? 5 ) ) ),
		);

		if ( ! empty( $options['include_domains'] ) && is_array( $options['include_domains'] ) ) {
			$body['include_domains'] = array_values( $options['include_domains'] );
		}

		if ( ! empty( $options['exclude_domains'] ) && is_array( $options['exclude_domains'] ) ) {
			$body['exclude_domains'] = array_values( $options['exclude_domains'] );
		}

		if ( ! empty( $options['time_range'] ) && in_array( $options['time_range'], array( 'day', 'week', 'month', 'year' ), true ) ) {
			$body['time_range'] = $options['time_range'];
		}

		$response = $this->json_request(
			'https://api.tavily.com/search',
			'POST',
			array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$results = array();
		foreach ( (array) ( $response['results'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$results[] = array(
				'title'       => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'url'         => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'content'     => sanitize_textarea_field( (string) ( $item['content'] ?? '' ) ),
				'raw_content' => isset( $item['raw_content'] ) ? sanitize_textarea_field( (string) $item['raw_content'] ) : '',
				'score'       => isset( $item['score'] ) ? (float) $item['score'] : 0.0,
				'favicon'     => esc_url_raw( (string) ( $item['favicon'] ?? '' ) ),
			);
		}

		return $this->with_optional_raw(
			$this->with_output_contract(
				array(
					'provider' => 'tavily',
					'query'    => $query,
					'answer'   => sanitize_textarea_field( (string) ( $response['answer'] ?? '' ) ),
					'results'  => $results,
					'images'   => is_array( $response['images'] ?? null ) ? $response['images'] : array(),
				),
				'research_evidence',
				'research_evidence'
			),
			$response
		);
	}

	public function image_candidates( string $query, array $options = array() ) {
		$access_key = $this->settings->get_unsplash_access_key();
		if ( '' === $access_key ) {
			return $this->missing_secret_error( 'unsplash_access_key', __( 'Configure an Unsplash access key before searching image candidates.', 'magick-ai-toolbox' ) );
		}

		$params = array(
			'query'    => $query,
			'per_page' => max( 1, min( 30, (int) ( $options['per_page'] ?? 8 ) ) ),
		);

		foreach ( array( 'orientation', 'color' ) as $key ) {
			if ( ! empty( $options[ $key ] ) ) {
				$params[ $key ] = sanitize_key( (string) $options[ $key ] );
			}
		}

		$response = $this->json_request(
			add_query_arg( $params, 'https://api.unsplash.com/search/photos' ),
			'GET',
			array(
				'Authorization' => 'Client-ID ' . $access_key,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$utm_source = sanitize_key( (string) $this->settings->get( 'unsplash_utm_source' ) );
		$images     = array();
		foreach ( (array) ( $response['results'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$user = is_array( $item['user'] ?? null ) ? $item['user'] : array();
			$urls = is_array( $item['urls'] ?? null ) ? $item['urls'] : array();
			$links = is_array( $item['links'] ?? null ) ? $item['links'] : array();
			$profile_url = (string) ( $user['links']['html'] ?? '' );
			if ( '' !== $profile_url && '' !== $utm_source ) {
				$profile_url = add_query_arg( array( 'utm_source' => $utm_source, 'utm_medium' => 'referral' ), $profile_url );
			}

			$images[] = array(
				'id'                => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
				'description'       => sanitize_textarea_field( (string) ( $item['description'] ?? $item['alt_description'] ?? '' ) ),
				'alt_description'   => sanitize_textarea_field( (string) ( $item['alt_description'] ?? '' ) ),
				'thumb_url'         => esc_url_raw( (string) ( $urls['thumb'] ?? '' ) ),
				'small_url'         => esc_url_raw( (string) ( $urls['small'] ?? '' ) ),
				'regular_url'       => esc_url_raw( (string) ( $urls['regular'] ?? '' ) ),
				'html_url'          => esc_url_raw( (string) ( $links['html'] ?? '' ) ),
				'download_location' => esc_url_raw( (string) ( $links['download_location'] ?? '' ) ),
				'photographer'      => sanitize_text_field( (string) ( $user['name'] ?? '' ) ),
				'photographer_url'  => esc_url_raw( $profile_url ),
				'attribution'       => sprintf(
					/* translators: %s: photographer name. */
					__( 'Photo by %s on Unsplash.', 'magick-ai-toolbox' ),
					sanitize_text_field( (string) ( $user['name'] ?? 'Unsplash' ) )
				),
			);
		}

		return $this->with_optional_raw(
			$this->with_output_contract(
				array(
					'provider' => 'unsplash',
					'query'    => $query,
					'images'   => $images,
				),
				'image_source_candidates',
				'image_source_candidates'
			),
			$response
		);
	}

	public function vector_search( string $input, int $max_results = 4, string $input_type = 'auto' ) {
		if ( '' === trim( $input ) ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_vector_input',
				__( 'A query or vector field is required for vector search.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$endpoint = untrailingslashit( (string) $this->settings->get( 'qdrant_endpoint' ) );
		$collection = trim( (string) $this->settings->get( 'qdrant_collection' ) );
		if ( '' === $endpoint || '' === $collection ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_qdrant_connection',
				__( 'Configure a Qdrant endpoint and collection before running vector search.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$input_type = sanitize_key( $input_type );
		if ( ! in_array( $input_type, array( 'auto', 'text', 'vector', 'qdrant_query' ), true ) ) {
			$input_type = 'auto';
		}

		$embedding = null;
		$decoded = null;
		if ( 'text' !== $input_type ) {
			$decoded = json_decode( $input, true );
		}

		if ( is_array( $decoded ) ) {
			$resolved_input_type = $this->is_list( $decoded ) ? 'vector' : 'qdrant_query';
			if ( 'vector' === $resolved_input_type ) {
				$dimension_error = $this->validate_vector_dimensions( $decoded, __( 'The supplied vector', 'magick-ai-toolbox' ) );
				if ( is_wp_error( $dimension_error ) ) {
					return $dimension_error;
				}
			}

			$body = $this->build_qdrant_query_body( $decoded, $max_results );
		} elseif ( 'vector' === $input_type || 'qdrant_query' === $input_type ) {
			return new WP_Error(
				'magick_ai_toolbox_invalid_vector_json',
				__( 'Vector search requires a JSON array vector or a Qdrant query object when input_type is vector or qdrant_query.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		} else {
			$resolved_input_type = 'text';
			$embedding = $this->create_embedding( $input );
			if ( is_wp_error( $embedding ) ) {
				return $embedding;
			}

			$body = $this->build_qdrant_query_body( $embedding['vector'], $max_results );
		}

		$api_key = $this->settings->get_qdrant_api_key();
		$headers = array();
		if ( '' !== $api_key ) {
			$headers['api-key'] = $api_key;
		}

		$response = $this->json_request(
			$endpoint . '/collections/' . rawurlencode( $collection ) . '/points/query',
			'POST',
			$headers,
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payload = $this->with_output_contract(
			array(
				'provider'   => 'qdrant',
				'input_type' => $resolved_input_type,
				'collection' => $collection,
				'points'     => is_array( $response['result']['points'] ?? null ) ? $response['result']['points'] : array(),
			),
			'local_style_context',
			'local_style_context'
		);

		if ( is_array( $embedding ) ) {
			$payload['embedding_provider']   = $embedding['provider'];
			$payload['embedding_model']      = $embedding['model'];
			$payload['embedding_dimensions'] = $embedding['dimensions'];
		}

		return $this->with_optional_raw( $payload, $response );
	}

	public function build_article_brief( string $topic, bool $include_vector = true ) {
		$research  = $this->web_research( $topic, array( 'max_results' => 5 ) );
		$images    = $this->image_candidates( $topic, array( 'per_page' => 6 ) );
		$knowledge = null;
		if ( $include_vector && (bool) $this->settings->get( 'enable_vector_search' ) && $this->settings->has_qdrant_connection() ) {
			$knowledge = $this->vector_search( $topic, 4, 'text' );
		}

		return array(
			'artifact_type'             => 'article_planning_bundle',
			'composition_role'          => 'article_planning_bundle',
			'write_posture'             => 'suggestion_only',
			'direct_wordpress_write'    => false,
			'provider'                  => 'toolbox',
			'topic'                     => $topic,
			'research'                  => is_wp_error( $research ) ? array( 'error' => $research->get_error_message() ) : $research,
			'images'                    => is_wp_error( $images ) ? array( 'error' => $images->get_error_message() ) : $images,
			'knowledge'                 => is_wp_error( $knowledge ) ? array( 'error' => $knowledge->get_error_message() ) : $knowledge,
			'handoff'                   => array(
				'write_posture' => 'suggestion_only',
				'next_steps'    => array(
					'Review sources.',
					'Select image candidate and preserve attribution.',
					'Create WordPress draft or media proposals through Abilities/Core.',
				),
			),
		);
	}

	public function build_article_write_plan( array $input ) {
		$title   = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
		$content = trim( sanitize_textarea_field( (string) ( $input['content_markdown'] ?? ( $input['content'] ?? '' ) ) ) );
		if ( '' === $title || '' === $content ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_article_plan_input',
				__( 'A title and content_markdown are required to build an article write plan.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic   = trim( sanitize_text_field( (string) ( $input['topic'] ?? $title ) ) );
		$context = $this->settings->get_content_context_for_ability();
		$forbidden_claims = $this->sanitize_string_list( $context['claims']['forbidden'] ?? array() );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		foreach ( $forbidden_claims as $claim ) {
			if ( '' !== $claim && false !== stripos( $content, $claim ) ) {
				$blocked_claims[] = $claim;
			}
		}
		$blocked_claims = array_values( array_unique( array_filter( $blocked_claims ) ) );

		$risk_level = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'low' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;

		$goal_brief = is_array( $input['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $input['article_goal_brief'] ) : array(
			'topic'           => $topic,
			'target_audience' => $this->sanitize_payload( $context['target_audience'] ?? array() ),
			'brand_voice'     => sanitize_textarea_field( (string) ( $context['brand_voice'] ?? '' ) ),
		);
		$evidence_pack = is_array( $input['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $input['research_evidence_pack'] ) : array(
			'sources' => is_array( $input['sources'] ?? null ) ? $this->sanitize_payload( $input['sources'] ) : array(),
		);
		$outline = is_array( $input['article_outline'] ?? null ) ? $this->sanitize_payload( $input['article_outline'] ) : array(
			'title'    => $title,
			'sections' => array(),
		);
		$draft_candidate = is_array( $input['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $input['article_draft_candidate'] ) : array(
			'content_markdown'  => $content,
			'used_sources'      => $this->sanitize_string_list( $input['used_sources'] ?? array() ),
			'unverified_claims' => $this->sanitize_string_list( $input['unverified_claims'] ?? array() ),
			'needs_human_input' => $this->sanitize_string_list( $input['needs_human_input'] ?? array() ),
		);
		$discoverability_pack = is_array( $input['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $input['discoverability_pack'] ) : array(
			'seo_title'       => sanitize_text_field( (string) ( $input['seo_title'] ?? $title ) ),
			'seo_description' => sanitize_textarea_field( (string) ( $input['seo_description'] ?? wp_trim_words( wp_strip_all_tags( $content ), 24, '' ) ) ),
			'excerpt'         => sanitize_textarea_field( (string) ( $input['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) ),
		);

		$risk_report = array(
			'risk_level'         => $risk_level,
			'blocked_claims'     => $blocked_claims,
			'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
			'ready_for_proposal' => $ready_for_proposal,
		);

		return array(
			'artifact_type'          => 'article_write_plan',
			'composition_role'       => 'core_article_write_plan',
			'version'                => 1,
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'batch_id'               => 'article_write_' . substr( md5( $title . '|' . $content ), 0, 12 ),
			'requires_approval'      => true,
			'dry_run'                => true,
			'commit_execution'       => false,
			'proposal_mode'          => 'single',
			'article_goal_brief'     => $goal_brief,
			'research_evidence_pack' => $evidence_pack,
			'article_outline'        => $outline,
			'article_draft_candidate' => $draft_candidate,
			'discoverability_pack'   => $discoverability_pack,
			'article_risk_report'    => $risk_report,
			'write_actions'          => array(
				array(
					'action_id'         => 'create_article_draft',
					'target_ability_id' => 'magick-ai/create-draft',
					'input'             => array(
						'title'   => $title,
						'content' => $content,
						'excerpt' => (string) ( $discoverability_pack['excerpt'] ?? '' ),
						'status'  => 'draft',
						'dry_run' => true,
						'commit'  => false,
					),
					'risk'              => 'medium',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => $ready_for_proposal,
					'reason'            => __( 'Create a reviewed AI-assisted article draft through Core governance.', 'magick-ai-toolbox' ),
				),
			),
			'handoff'                => array(
				'plan_ability_id'        => 'magick-ai-toolbox/build-article-write-plan',
				'core_route'             => '/wp-json/magick-ai-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_content_discoverability_brief( array $input ) {
		$source = $this->resolve_discoverability_source( $input );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$context           = $this->settings->get_content_context_for_ability();
		$validation        = $this->settings->validate_content_context_for_ability();
		$allowed_fields    = $this->sanitize_string_list( $context['proposal_allowed_fields'] ?? array() );
		$proposal_template = array();
		$candidates        = array();

		foreach ( $allowed_fields as $field ) {
			$proposal_template[ $field ] = array(
				'instruction' => $this->content_discoverability_field_instruction( $field ),
				'value'       => null,
			);

			$candidate = $this->content_discoverability_candidate( $field, $source, $context );
			if ( null !== $candidate ) {
				$candidates[ $field ] = $candidate;
			}
		}

		return array(
			'artifact_type'          => 'content_discoverability_brief',
			'composition_role'       => 'seo_aeo_geo_brief',
			'version'                => 1,
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'context_validation'     => $validation,
			'content_context'        => $context,
			'source'                 => $source,
			'ai_instructions'        => array(
				'Use the content_context as the site-level rule source.',
				'Use only facts present in the supplied source, public site context, or cited evidence.',
				'Do not invent customer cases, ranking guarantees, source citations, or unavailable product features.',
				'Return suggestions only for proposal_allowed_fields.',
				'Respect forbidden claims and preserve the requested brand voice.',
				'Final WordPress writes must go through Core proposal approval.',
			),
			'proposal_allowed_fields' => $allowed_fields,
			'proposal_template'      => $proposal_template,
			'candidate_suggestions'  => $candidates,
			'handoff'                => array(
				'brief_ability_id'       => 'magick-ai-toolbox/build-content-discoverability-brief',
				'context_ability_id'     => 'magick-ai-toolbox/get-content-discoverability-context',
				'validation_ability_id'  => 'magick-ai-toolbox/validate-content-discoverability-context',
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_media_brief( string $post_context ) {
		return $this->image_candidates( $this->post_context_to_image_query( $post_context ), array( 'per_page' => 8 ) );
	}

	private function build_qdrant_query_body( array $decoded, int $max_results ): array {
		$is_vector = $this->is_list( $decoded );
		$limit = max( 1, min( 10, $max_results ) );
		$vector_name = trim( (string) $this->settings->get( 'qdrant_vector_name' ) );

		if ( $is_vector ) {
			$body = array(
				'query'        => array_map( 'floatval', $decoded ),
				'limit'        => $limit,
				'with_payload' => true,
			);

			if ( '' !== $vector_name ) {
				$body['using'] = $vector_name;
			}

			return $body;
		}

		$decoded['limit'] = isset( $decoded['limit'] ) ? max( 1, min( 10, (int) $decoded['limit'] ) ) : $limit;
		if ( ! array_key_exists( 'with_payload', $decoded ) ) {
			$decoded['with_payload'] = true;
		}
		if ( '' !== $vector_name && ! array_key_exists( 'using', $decoded ) ) {
			$decoded['using'] = $vector_name;
		}

		return $decoded;
	}

	private function create_embedding( string $query ) {
		$provider = sanitize_key( (string) $this->settings->get( 'embedding_provider' ) );
		if ( 'jina' === $provider ) {
			return $this->create_jina_embedding( $query );
		}

		return $this->create_siliconflow_embedding( $query );
	}

	private function create_siliconflow_embedding( string $query ) {
		$api_key = $this->settings->get_siliconflow_api_key();
		if ( '' === $api_key ) {
			return $this->missing_secret_error( 'siliconflow_api_key', __( 'Configure a SiliconFlow API key before running text-to-vector search.', 'magick-ai-toolbox' ) );
		}

		$base_url = untrailingslashit( (string) ( $this->settings->get( 'siliconflow_base_url' ) ?: 'https://api.siliconflow.com/v1' ) );
		$model = trim( (string) $this->settings->get( 'siliconflow_model' ) );
		if ( '' === $model ) {
			$model = 'BAAI/bge-m3';
		}

		$response = $this->json_request(
			$base_url . '/embeddings',
			'POST',
			array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			array(
				'model' => $model,
				'input' => $query,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$vector = $response['data'][0]['embedding'] ?? null;
		if ( ! is_array( $vector ) || array() === $vector ) {
			return new WP_Error(
				'magick_ai_toolbox_invalid_embedding_response',
				__( 'The embedding provider did not return an embedding vector.', 'magick-ai-toolbox' ),
				array( 'status' => 502 )
			);
		}

		$dimension_error = $this->validate_vector_dimensions( $vector, __( 'The SiliconFlow embedding', 'magick-ai-toolbox' ) );
		if ( is_wp_error( $dimension_error ) ) {
			return $dimension_error;
		}

		return array(
			'provider'   => 'siliconflow',
			'model'      => sanitize_text_field( (string) ( $response['model'] ?? $model ) ),
			'vector'     => array_map( 'floatval', $vector ),
			'dimensions' => count( $vector ),
		);
	}

	private function create_jina_embedding( string $query ) {
		$api_key = $this->settings->get_jina_api_key();
		if ( '' === $api_key ) {
			return $this->missing_secret_error( 'jina_api_key', __( 'Configure a Jina AI API key before running Jina text-to-vector search.', 'magick-ai-toolbox' ) );
		}

		$base_url = untrailingslashit( (string) ( $this->settings->get( 'jina_base_url' ) ?: 'https://api.jina.ai/v1' ) );
		$model = trim( (string) $this->settings->get( 'jina_model' ) );
		if ( '' === $model ) {
			$model = 'jina-embeddings-v3';
		}

		$body = array(
			'model' => $model,
			'input' => $query,
		);

		$dimensions = $this->expected_embedding_dimensions();
		if ( 0 < $dimensions ) {
			$body['dimensions'] = $dimensions;
		}

		$response = $this->json_request(
			$base_url . '/embeddings',
			'POST',
			array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$vector = $response['data'][0]['embedding'] ?? null;
		if ( ! is_array( $vector ) || array() === $vector ) {
			return new WP_Error(
				'magick_ai_toolbox_invalid_embedding_response',
				__( 'The embedding provider did not return an embedding vector.', 'magick-ai-toolbox' ),
				array( 'status' => 502 )
			);
		}

		$dimension_error = $this->validate_vector_dimensions( $vector, __( 'The Jina embedding', 'magick-ai-toolbox' ) );
		if ( is_wp_error( $dimension_error ) ) {
			return $dimension_error;
		}

		return array(
			'provider'   => 'jina',
			'model'      => sanitize_text_field( (string) ( $response['model'] ?? $model ) ),
			'vector'     => array_map( 'floatval', $vector ),
			'dimensions' => count( $vector ),
		);
	}

	private function expected_embedding_dimensions(): int {
		return max( 0, (int) $this->settings->get( 'embedding_dimensions' ) );
	}

	private function validate_vector_dimensions( array $vector, string $label ) {
		$expected = $this->expected_embedding_dimensions();
		if ( 0 === $expected ) {
			return null;
		}

		$actual = count( $vector );
		if ( $actual === $expected ) {
			return null;
		}

		return new WP_Error(
			'magick_ai_toolbox_embedding_dimension_mismatch',
			sprintf(
				/* translators: 1: vector label, 2: actual dimensions, 3: expected dimensions. */
				__( '%1$s has %2$d dimensions, but the configured Qdrant collection expects %3$d.', 'magick-ai-toolbox' ),
				$label,
				$actual,
				$expected
			),
			array( 'status' => 400 )
		);
	}

	private function json_request( string $url, string $method, array $headers = array(), ?array $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => array_merge(
				array(
					'Accept' => 'application/json',
				),
				$headers
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'magick_ai_toolbox_provider_invalid_json',
				__( 'The provider returned an invalid JSON response.', 'magick-ai-toolbox' ),
				array( 'status' => 502 )
			);
		}

		if ( 200 > $status || 299 < $status ) {
			$message = sanitize_text_field( (string) ( $data['error']['message'] ?? $data['message'] ?? __( 'The provider request failed.', 'magick-ai-toolbox' ) ) );
			return new WP_Error(
				'magick_ai_toolbox_provider_error',
				$message,
				array(
					'status'          => $status,
					'provider_status' => $status,
				)
			);
		}

		return $data;
	}

	private function missing_secret_error( string $secret, string $message ): WP_Error {
		return new WP_Error(
			'magick_ai_toolbox_missing_' . sanitize_key( $secret ),
			$message,
			array( 'status' => 400 )
		);
	}

	private function with_optional_raw( array $payload, array $raw ): array {
		if ( (bool) $this->settings->get( 'include_raw_responses' ) ) {
			$payload['raw'] = $raw;
		}

		return $payload;
	}

	private function with_output_contract( array $payload, string $artifact_type, string $composition_role ): array {
		return array_merge(
			array(
				'artifact_type'          => $artifact_type,
				'composition_role'       => $composition_role,
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
			),
			$payload
		);
	}

	private function sanitize_string_list( $value ): array {
		$items = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( "\n", (string) $value ) ) );
		return array_values(
			array_filter(
				array_map(
					static fn( $item ): string => sanitize_textarea_field( (string) $item ),
					$items
				),
				static fn( string $item ): bool => '' !== $item
			)
		);
	}

	private function sanitize_payload( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $key => $child ) {
				$sanitized[ is_string( $key ) ? sanitize_key( $key ) : $key ] = $this->sanitize_payload( $child );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_textarea_field( (string) $value );
	}

	private function resolve_discoverability_source( array $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$title   = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
		$topic   = trim( sanitize_text_field( (string) ( $input['topic'] ?? '' ) ) );
		$content = trim( sanitize_textarea_field( (string) ( $input['content'] ?? ( $input['content_markdown'] ?? '' ) ) ) );
		$excerpt = trim( sanitize_textarea_field( (string) ( $input['excerpt'] ?? '' ) ) );

		if ( 0 < $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error(
					'magick_ai_toolbox_post_not_found',
					__( 'The requested post was not found.', 'magick-ai-toolbox' ),
					array( 'status' => 404 )
				);
			}

			$title   = '' !== $title ? $title : get_the_title( $post );
			$content = '' !== $content ? $content : wp_strip_all_tags( (string) $post->post_content );
			$excerpt = '' !== $excerpt ? $excerpt : wp_strip_all_tags( get_the_excerpt( $post ) );
			$topic   = '' !== $topic ? $topic : $title;

			return array(
				'input_type'      => 'post',
				'post_id'         => $post_id,
				'post_type'       => get_post_type( $post ),
				'post_status'     => get_post_status( $post ),
				'title'           => sanitize_text_field( (string) $title ),
				'topic'           => sanitize_text_field( (string) $topic ),
				'excerpt'         => sanitize_textarea_field( (string) $excerpt ),
				'content_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 180, '' ),
			);
		}

		if ( '' === $title && '' === $topic ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_discoverability_source',
				__( 'A post_id, topic, or title is required to build a content discoverability brief.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $title ) {
			$title = $topic;
		}
		if ( '' === $topic ) {
			$topic = $title;
		}

		return array(
			'input_type'      => 'supplied_context',
			'post_id'         => 0,
			'post_type'       => sanitize_key( (string) ( $input['post_type'] ?? 'post' ) ),
			'post_status'     => sanitize_key( (string) ( $input['post_status'] ?? 'draft' ) ),
			'title'           => $title,
			'topic'           => $topic,
			'excerpt'         => $excerpt,
			'content_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 180, '' ),
		);
	}

	private function content_discoverability_field_instruction( string $field ): string {
		$instructions = array(
			'seo_title'             => __( 'Suggest a concise search title based on the source topic and primary keywords. Avoid clickbait and unsupported claims.', 'magick-ai-toolbox' ),
			'seo_description'       => __( 'Suggest a meta description that summarizes the reader problem, topic, and value using verified source facts only.', 'magick-ai-toolbox' ),
			'slug'                  => __( 'Suggest a short, readable URL slug from the title or topic.', 'magick-ai-toolbox' ),
			'excerpt'               => __( 'Suggest an editorial excerpt grounded in the supplied content.', 'magick-ai-toolbox' ),
			'faq'                   => __( 'Suggest FAQ question and answer pairs only when the context allows FAQ generation and the source supports the answers.', 'magick-ai-toolbox' ),
			'answer_summary'        => __( 'Suggest a direct one-sentence AEO answer summary grounded in the supplied source.', 'magick-ai-toolbox' ),
			'geo_summary'           => __( 'Suggest a standalone GEO summary that is easy for AI systems to quote without adding unsupported facts.', 'magick-ai-toolbox' ),
			'structured_data_hints' => __( 'Suggest schema hints only when the source supports them; do not claim schema has been applied.', 'magick-ai-toolbox' ),
		);

		return $instructions[ $field ] ?? __( 'Suggest a reviewable content improvement grounded in the supplied source.', 'magick-ai-toolbox' );
	}

	private function content_discoverability_candidate( string $field, array $source, array $context ) {
		$title   = sanitize_text_field( (string) ( $source['title'] ?? $source['topic'] ?? '' ) );
		$topic   = sanitize_text_field( (string) ( $source['topic'] ?? $title ) );
		$content = sanitize_textarea_field( (string) ( $source['content_excerpt'] ?? '' ) );
		$excerpt = sanitize_textarea_field( (string) ( $source['excerpt'] ?? '' ) );
		$text    = '' !== $excerpt ? $excerpt : $content;

		if ( '' === $text ) {
			$text = $topic;
		}

		if ( 'seo_title' === $field ) {
			return wp_trim_words( $title, 12, '' );
		}
		if ( 'seo_description' === $field ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 26, '' );
		}
		if ( 'slug' === $field ) {
			return sanitize_title( $title );
		}
		if ( 'excerpt' === $field ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 36, '' );
		}
		if ( 'answer_summary' === $field && ! empty( $context['rules']['allow_aeo_summary'] ) ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 28, '' );
		}
		if ( 'geo_summary' === $field && ! empty( $context['rules']['allow_geo_summary'] ) ) {
			return wp_trim_words( wp_strip_all_tags( $text ), 42, '' );
		}
		if ( 'faq' === $field && ! empty( $context['rules']['allow_faq_generation'] ) ) {
			return array(
				array(
					'question' => sprintf(
						/* translators: %s: topic. */
						__( 'What should readers know about %s?', 'magick-ai-toolbox' ),
						$topic
					),
					'answer_guidance' => __( 'Answer only with facts supported by the supplied source and site context.', 'magick-ai-toolbox' ),
				),
				array(
					'question' => sprintf(
						/* translators: %s: topic. */
						__( 'How does %s affect the target audience?', 'magick-ai-toolbox' ),
						$topic
					),
					'answer_guidance' => __( 'Connect the answer to target audience needs without inventing outcomes or guarantees.', 'magick-ai-toolbox' ),
				),
			);
		}
		if ( 'structured_data_hints' === $field && ! empty( $context['rules']['allow_structured_data_suggestions'] ) ) {
			return array(
				'Article',
				! empty( $context['rules']['allow_faq_generation'] ) ? 'FAQPage candidate if final FAQ answers are verified' : 'FAQPage disabled by context',
			);
		}

		return null;
	}

	private function post_context_to_image_query( string $post_context ): string {
		$decoded = json_decode( $post_context, true );
		if ( is_array( $decoded ) ) {
			$title = trim( sanitize_text_field( (string) ( $decoded['title'] ?? '' ) ) );
			if ( '' !== $title ) {
				return $title;
			}

			$excerpt = trim( sanitize_textarea_field( (string) ( $decoded['excerpt'] ?? '' ) ) );
			if ( '' !== $excerpt ) {
				return wp_trim_words( $excerpt, 12, '' );
			}
		}

		return wp_trim_words( wp_strip_all_tags( $post_context ), 12, '' );
	}

	private function is_list( array $value ): bool {
		$index = 0;
		foreach ( $value as $key => $unused ) {
			unset( $unused );
			if ( $key !== $index ) {
				return false;
			}
			++$index;
		}

		return true;
	}
}
