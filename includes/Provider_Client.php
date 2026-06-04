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

	public function image_candidates( string $query, array $options = array() ) {
		$provider = sanitize_key( (string) ( $options['provider'] ?? 'auto' ) );
		if ( ! in_array( $provider, array( 'auto', 'cloud', 'unsplash', 'pixabay', 'pexels', 'ai_generated' ), true ) ) {
			$provider = 'auto';
		}

		if ( 'ai_generated' === $provider || $this->should_include_ai_generated_images( $options ) ) {
			$result = $this->search_ai_generated_images( $query, $options );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return $this->normalize_image_source_candidates_response(
				array(
					'provider'       => 'ai_generated',
					'provider_mode'  => 'ai_generated',
					'active_sources' => array( array( 'provider' => 'ai_generated', 'count' => count( (array) ( $result['images'] ?? array() ) ) ) ),
					'images'         => is_array( $result['images'] ?? null ) ? $result['images'] : array(),
					'raw'            => is_array( $result['raw'] ?? null ) ? $result['raw'] : array(),
				),
				$query,
				'ai_generated'
			);
		}

		return $this->execute_image_source_cloud_request( $query, $options, $provider );
	}

	private function should_include_ai_generated_images( array $options ): bool {
		if ( ! empty( $options['include_ai_generated'] ) ) {
			return true;
		}

		foreach ( array( 'generated_image_url', 'ai_image_url', 'image_url', 'regular_url' ) as $key ) {
			if ( '' !== trim( (string) ( $options[ $key ] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function search_ai_generated_images( string $query, array $options ) {
		$prompt = trim( sanitize_textarea_field( (string) ( $options['generation_prompt'] ?? $options['prompt'] ?? $query ) ) );
		$url    = $this->first_non_empty_url(
			array(
				$options['generated_image_url'] ?? '',
				$options['ai_image_url'] ?? '',
				$options['image_url'] ?? '',
				$options['regular_url'] ?? '',
			)
		);

		if ( '' !== $url ) {
			return array(
				'provider' => 'ai_generated',
				'images'   => array(
					$this->normalize_ai_generated_image_candidate(
						array_merge(
							$options,
							array(
								'regular_url' => $url,
								'prompt'      => $prompt,
							)
						),
						$query,
						$prompt
					),
				),
				'raw'      => array(),
			);
		}

		$request = array(
			'query'       => $query,
			'prompt'      => $prompt,
			'orientation' => sanitize_key( (string) ( $options['orientation'] ?? '' ) ),
			'color'       => sanitize_key( (string) ( $options['color'] ?? '' ) ),
			'per_page'    => max( 1, min( 4, (int) ( $options['per_page'] ?? 1 ) ) ),
			'purpose'     => sanitize_key( (string) ( $options['purpose'] ?? 'article_image_candidate' ) ),
		);

		$result = apply_filters( 'magick_ai_toolbox_ai_image_generation_request', null, $request, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( null === $result ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_ai_image_runtime',
				__( 'No AI image generation runtime handled this image candidate request. Provide a generated_image_url or register the magick_ai_toolbox_ai_image_generation_request filter.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$candidates = $this->extract_ai_generated_image_candidates( $result );
		if ( array() === $candidates ) {
			return new WP_Error(
				'magick_ai_toolbox_empty_ai_image_response',
				__( 'The AI image generation runtime did not return an image URL candidate.', 'magick-ai-toolbox' ),
				array( 'status' => 502 )
			);
		}

		$images = array();
		foreach ( $candidates as $candidate ) {
			$normalized = $this->normalize_ai_generated_image_candidate( $candidate, $query, $prompt );
			if ( '' !== (string) ( $normalized['regular_url'] ?? '' ) ) {
				$images[] = $normalized;
			}
		}

		if ( array() === $images ) {
			return new WP_Error(
				'magick_ai_toolbox_empty_ai_image_response',
				__( 'The AI image generation runtime did not return an image URL candidate.', 'magick-ai-toolbox' ),
				array( 'status' => 502 )
			);
		}

		return array(
			'provider' => 'ai_generated',
			'images'   => array_slice( $images, 0, max( 1, min( 4, (int) ( $options['per_page'] ?? 1 ) ) ) ),
			'raw'      => is_array( $result ) ? $this->sanitize_payload( $result ) : array(),
		);
	}

	private function dedupe_image_candidates( array $images ): array {
		$seen = array();
		$out  = array();

		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			$key = (string) ( $image['source_url'] ?? $image['html_url'] ?? $image['regular_url'] ?? $image['id'] ?? '' );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$out[]        = $image;
		}

		return $out;
	}

	private function normalize_image_candidate_contract( array $candidate ): array {
		$provider = sanitize_key( (string) ( $candidate['provider'] ?? 'external' ) );
		$source_type = sanitize_key( (string) ( $candidate['source_type'] ?? '' ) );
		if ( '' === $source_type ) {
			if ( 'ai_generated' === $provider ) {
				$source_type = 'ai_generated';
			} elseif ( in_array( $provider, array( 'unsplash', 'pixabay', 'pexels' ), true ) ) {
				$source_type = 'stock';
			} else {
				$source_type = 'external';
			}
		}

		$download_url = $this->first_non_empty_url(
			array(
				$candidate['download_url'] ?? '',
				$candidate['regular_url'] ?? '',
				$candidate['url'] ?? '',
				$candidate['image_url'] ?? '',
				$candidate['generated_image_url'] ?? '',
				$candidate['output_url'] ?? '',
				$candidate['small_url'] ?? '',
			)
		);
		$thumbnail_url = $this->first_non_empty_url(
			array(
				$candidate['thumbnail_url'] ?? '',
				$candidate['thumb_url'] ?? '',
				$candidate['small_url'] ?? '',
				$download_url,
			)
		);
		$source_url = esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) );
		$prompt = trim( sanitize_textarea_field( (string) ( $candidate['prompt'] ?? $candidate['generation_prompt'] ?? '' ) ) );
		$model = sanitize_text_field( (string) ( $candidate['model'] ?? $candidate['generation_model'] ?? '' ) );
		$license_review_status = $this->normalize_license_review_status( (string) ( $candidate['license_review_status'] ?? '' ), $source_type );
		$provider_origin = sanitize_key( (string) ( $candidate['provider_origin'] ?? 'toolbox' ) );
		$warnings = $this->sanitize_string_list( $candidate['warnings'] ?? array() );
		$file_name = sanitize_file_name( (string) ( $candidate['file_name'] ?? '' ) );
		$suggested_filename = sanitize_file_name( (string) ( $candidate['suggested_filename'] ?? $file_name ) );
		if ( '' === $file_name && '' !== $suggested_filename ) {
			$file_name = $suggested_filename;
		}
		$filename_basis = is_array( $candidate['filename_basis'] ?? null )
			? $this->sanitize_payload( $candidate['filename_basis'] )
			: array(
				'owner'                          => 'wordpress_write_ability_final',
				'strategy'                       => 'candidate_suggested_filename',
				'final_sanitize_unique_required' => true,
			);

		$candidate['contract_version']              = 'image_candidate.v1';
		$candidate['source_type']                   = $source_type;
		$candidate['provider']                      = $provider;
		$candidate['provider_origin']               = '' !== $provider_origin ? $provider_origin : 'toolbox';
		$candidate['download_url']                  = $download_url;
		$candidate['thumbnail_url']                 = $thumbnail_url;
		$candidate['source_url']                    = $source_url;
		$candidate['prompt']                        = $prompt;
		$candidate['model']                         = $model;
		$candidate['license_review_status']         = $license_review_status;
		$candidate['requires_human_license_review'] = 'not_required' !== $license_review_status;
		$candidate['warnings']                      = $warnings;
		$candidate['file_name']                     = $file_name;
		$candidate['suggested_filename']            = '' !== $suggested_filename ? $suggested_filename : $file_name;
		$candidate['filename_basis']                = $filename_basis;
		$candidate['provenance']                    = array(
			'provider'          => $provider,
			'provider_origin'   => $candidate['provider_origin'],
			'source_type'       => $source_type,
			'source_url'        => $source_url,
			'download_location' => esc_url_raw( (string) ( $candidate['download_location'] ?? '' ) ),
			'photographer'      => sanitize_text_field( (string) ( $candidate['photographer'] ?? '' ) ),
			'generation_provider' => sanitize_key( (string) ( $candidate['generation_provider'] ?? $candidate['provider_name'] ?? '' ) ),
			'generation_model'  => $model,
		);

		return $candidate;
	}

	private function normalize_license_review_status( string $status, string $source_type ): string {
		$status = sanitize_key( $status );
		if ( in_array( $status, array( 'required', 'reviewed', 'not_required' ), true ) ) {
			return $status;
		}
		if ( in_array( $status, array( 'needs_human_review', 'needs_review', 'human_review_required' ), true ) ) {
			return 'required';
		}
		if ( 'owned' === $source_type ) {
			return 'not_required';
		}
		return 'required';
	}

	private function first_non_empty_url( array $urls ): string {
		foreach ( $urls as $url ) {
			$clean = esc_url_raw( (string) $url );
			if ( '' !== $clean ) {
				return $clean;
			}
		}

		return '';
	}

	private function extract_ai_generated_image_candidates( $result ): array {
		if ( ! is_array( $result ) ) {
			return array();
		}

		if ( is_array( $result['images'] ?? null ) ) {
			return array_values( array_filter( $result['images'], 'is_array' ) );
		}

		if ( is_array( $result['candidates'] ?? null ) ) {
			return array_values( array_filter( $result['candidates'], 'is_array' ) );
		}

		if ( $this->is_list( $result ) ) {
			return array_values( array_filter( $result, 'is_array' ) );
		}

		return array( $result );
	}

	private function normalize_ai_generated_image_candidate( array $candidate, string $query, string $fallback_prompt ): array {
		$url = $this->first_non_empty_url(
			array(
				$candidate['regular_url'] ?? '',
				$candidate['url'] ?? '',
				$candidate['image_url'] ?? '',
				$candidate['generated_image_url'] ?? '',
				$candidate['output_url'] ?? '',
			)
		);

		$thumb_url = $this->first_non_empty_url(
			array(
				$candidate['thumb_url'] ?? '',
				$candidate['thumbnail_url'] ?? '',
				$candidate['small_url'] ?? '',
				$url,
			)
		);
		$small_url = $this->first_non_empty_url(
			array(
				$candidate['small_url'] ?? '',
				$candidate['preview_url'] ?? '',
				$url,
			)
		);
		$provider = sanitize_key( (string) ( $candidate['generation_provider'] ?? $candidate['provider_name'] ?? 'ai_generated' ) );
		$model    = sanitize_text_field( (string) ( $candidate['model'] ?? $candidate['generation_model'] ?? '' ) );
		$prompt   = trim( sanitize_textarea_field( (string) ( $candidate['prompt'] ?? $candidate['generation_prompt'] ?? $fallback_prompt ) ) );
		$alt      = trim( sanitize_textarea_field( (string) ( $candidate['alt_description'] ?? $candidate['alt'] ?? $candidate['description'] ?? $query ) ) );

		return array(
			'id'                            => sanitize_text_field( (string) ( $candidate['id'] ?? ( '' !== $url ? md5( $url ) : '' ) ) ),
			'provider'                      => 'ai_generated',
			'provider_name'                 => $provider,
			'provider_origin'               => sanitize_key( (string) ( $candidate['provider_origin'] ?? 'toolbox' ) ),
			'source_type'                   => 'ai_generated',
			'description'                   => $alt,
			'alt_description'               => $alt,
			'thumb_url'                     => $thumb_url,
			'small_url'                     => $small_url,
			'regular_url'                   => $url,
			'html_url'                      => esc_url_raw( (string) ( $candidate['html_url'] ?? $candidate['source_url'] ?? '' ) ),
			'download_location'             => '',
			'source_url'                    => esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) ),
			'photographer'                  => '',
			'photographer_url'              => '',
			'attribution'                   => sanitize_text_field( (string) ( $candidate['attribution'] ?? __( 'AI-generated image candidate.', 'magick-ai-toolbox' ) ) ),
			'prompt'                        => $prompt,
			'model'                         => $model,
			'generation_prompt'             => $prompt,
			'generation_model'              => $model,
			'generation_provider'           => $provider,
			'license_review_status'         => $this->normalize_license_review_status( (string) ( $candidate['license_review_status'] ?? 'required' ), 'ai_generated' ),
			'requires_human_license_review' => true,
		);
	}

	private function sanitize_provider_error_data( $data ): array {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$allowed = array();
		foreach ( array( 'status', 'provider_status', 'http_code', 'reason', 'request_id' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$allowed[ $key ] = is_numeric( $data[ $key ] ) ? (int) $data[ $key ] : sanitize_text_field( (string) $data[ $key ] );
			}
		}

		return $allowed;
	}

	public function vector_search( string $input, int $max_results = 4, string $input_type = 'auto' ) {
		if ( '' === trim( $input ) ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_vector_input',
				__( 'A query or vector field is required for vector search.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return $this->with_output_contract(
			array(
				'provider'          => 'cloud_site_knowledge',
				'provider_mode'     => 'cloud_managed',
				'status'            => 'cloud_managed',
				'message'           => __( 'Low-level vector provider configuration has moved to Magick AI Cloud. Use search-site-knowledge for Cloud-managed semantic site context.', 'magick-ai-toolbox' ),
				'target_ability_id' => 'magick-ai-toolbox/search-site-knowledge',
				'results'           => array(),
				'requested_input'   => array(
					'input_type'  => sanitize_key( $input_type ),
					'max_results' => max( 1, min( 20, $max_results ) ),
				),
			),
			'site_knowledge_context',
			'site_knowledge_context'
		);
	}

	public function search_site_knowledge( array $input ) {
		$query = trim( sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ) );
		if ( '' === $query ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_site_knowledge_query',
				__( 'A query is required for site knowledge search.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$intent = sanitize_key( (string) ( $input['intent'] ?? 'site_search' ) );
		if (
			! in_array(
				$intent,
				array(
					'site_search',
					'related_content',
					'writing_context',
					'internal_links',
					'refresh_suggestions',
					'image_context',
					'faq_candidates',
					'content_gap_analysis',
					'duplicate_check',
				),
				true
			)
		) {
			$intent = 'site_search';
		}

		$filters = is_array( $input['filters'] ?? null ) ? $this->sanitize_payload( $input['filters'] ) : array();
		$payload = array(
			'contract_version' => 'site_knowledge_search.v1',
			'query'            => $query,
			'intent'           => $intent,
			'current_post_id'  => absint( $input['current_post_id'] ?? 0 ),
			'max_results'      => max( 1, min( 20, absint( $input['max_results'] ?? 8 ) ) ),
			'filters'          => is_array( $filters ) ? $filters : array(),
			'write_posture'    => 'suggestion_only',
		);

		return $this->execute_site_knowledge_cloud_request(
			'magick-ai-cloud/site-knowledge-search',
			'site_knowledge_search.v1',
			'inline',
			$payload,
			'site_knowledge_results',
			'site_knowledge_context'
		);
	}

	public function get_site_knowledge_status( array $input ) {
		$payload = array(
			'contract_version' => 'site_knowledge_status.v1',
			'include_coverage' => ! empty( $input['include_coverage'] ),
			'write_posture'    => 'suggestion_only',
		);

		return $this->execute_site_knowledge_cloud_request(
			'magick-ai-cloud/site-knowledge-status',
			'site_knowledge_status.v1',
			'inline',
			$payload,
			'site_knowledge_status',
			'site_knowledge_status'
		);
	}

	public function request_site_knowledge_sync( array $input ) {
		$sync_mode = sanitize_key( (string) ( $input['sync_mode'] ?? 'refresh' ) );
		if ( ! in_array( $sync_mode, array( 'refresh', 'rebuild', 'delete' ), true ) ) {
			$sync_mode = 'refresh';
		}

		$payload = array(
			'contract_version' => 'site_knowledge_sync.v1',
			'sync_mode'        => $sync_mode,
			'post_ids'         => $this->sanitize_absint_list( $input['post_ids'] ?? array() ),
			'max_posts'        => max( 1, min( 50, absint( $input['max_posts'] ?? 20 ) ) ),
			'documents'        => array(),
			'write_posture'    => 'suggestion_only',
		);

		if ( 'delete' !== $sync_mode ) {
			$payload['documents'] = $this->collect_site_knowledge_documents( $payload['post_ids'], $payload['max_posts'] );
		}

		return $this->execute_site_knowledge_cloud_request(
			'magick-ai-cloud/site-knowledge-sync',
			'site_knowledge_sync.v1',
			'whole_run_offload',
			$payload,
			'site_knowledge_sync_request',
			'site_knowledge_sync_request'
		);
	}

	private function cloud_web_search_notice(): array {
		return array(
			'provider'       => 'cloud_web_search',
			'provider_mode'  => 'cloud_managed',
			'active_sources' => array(),
			'results'        => array(),
			'status'         => 'cloud_managed',
			'message'        => __( 'External web search is provided by Magick AI Cloud. Toolbox no longer stores local web search provider configuration.', 'magick-ai-toolbox' ),
		);
	}

	private function cloud_web_search_error_notice( WP_Error $error ): array {
		$notice                 = $this->cloud_web_search_notice();
		$notice['status']       = 'failed';
		$notice['error_code']   = sanitize_key( (string) $error->get_error_code() );
		$notice['error']        = sanitize_text_field( $error->get_error_message() );
		$notice['result_count'] = 0;

		return $notice;
	}

	private function cloud_web_search_for_content( string $query, string $intent = 'writing_context', int $max_results = 3 ): array {
		$result = $this->test_cloud_web_search(
			array(
				'query'        => $query,
				'intent'       => $intent,
				'provider'     => 'auto',
				'max_results'  => $max_results,
				'recency_days' => 'news' === $intent ? 7 : 30,
			)
		);

		return is_wp_error( $result ) ? $this->cloud_web_search_error_notice( $result ) : $result;
	}

	public function test_cloud_web_search( array $input ) {
		$query = trim( sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ) );
		if ( '' === $query ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_web_search_query',
				__( 'A query is required for Cloud web search testing.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$intent = sanitize_key( (string) ( $input['intent'] ?? 'news' ) );
		if ( ! in_array( $intent, array( 'general_research', 'fact_check', 'news', 'writing_context', 'competitor_research', 'source_discovery', 'external_links' ), true ) ) {
			$intent = 'news';
		}

		$provider = sanitize_key( (string) ( $input['provider'] ?? 'auto' ) );
		if ( ! in_array( $provider, array( 'auto', 'tavily', 'bocha', 'apify' ), true ) ) {
			$provider = 'auto';
		}

		$max_results  = max( 1, min( 5, absint( $input['max_results'] ?? 3 ) ) );
		$recency_days = max( 0, min( 30, absint( $input['recency_days'] ?? 7 ) ) );
		$runtime_input = array(
			'contract_version'    => 'web_search.v1',
			'query'               => $query,
			'intent'              => $intent,
			'provider'            => $provider,
			'max_results'         => $max_results,
			'recency_days'        => $recency_days,
			'enhance_with_reader' => ! empty( $input['enhance_with_reader'] ),
			'evidence_policy'     => array(
				'required_sources' => 1,
				'no_hit_policy'    => 'abstain',
			),
			'write_posture'       => 'suggestion_only',
		);

		$runtime_payload = array(
			'ability_name'        => 'magick-ai-cloud/web-search',
			'ability_family'      => 'knowledge',
			'contract_version'    => 'web_search.v1',
			'channel'             => 'toolbox_admin',
			'execution_kind'      => 'web_search',
			'profile_id'          => 'web-search.managed',
			'execution_pattern'   => 'inline',
			'data_classification' => 'public',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 3600,
			'timeout_seconds'     => 30,
			'retry_max'           => 0,
			'input'               => $this->sanitize_payload( $runtime_input ),
			'policy'              => array(
				'allow_fallback' => true,
			),
		);

		$runtime_payload = apply_filters( 'magick_ai_toolbox_web_search_runtime_payload', $runtime_payload, $runtime_input );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'magick_ai_toolbox_invalid_web_search_runtime_payload',
				__( 'The web search runtime payload was not valid.', 'magick-ai-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$handled = apply_filters( 'magick_ai_toolbox_web_search_cloud_request', null, $runtime_payload, $runtime_input );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_cloud_web_search_response( $handled, $runtime_payload );
		}

		$client = function_exists( 'magick_ai_cloud_addon_runtime_client' ) ? magick_ai_cloud_addon_runtime_client() : null;
		if ( ! is_object( $client ) || ! method_exists( $client, 'execute_runtime' ) ) {
			return new WP_Error(
				'magick_ai_toolbox_web_search_cloud_unavailable',
				__( 'Connect Magick AI Cloud before testing managed web search.', 'magick-ai-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$trace_id        = $this->trace_id( 'web_search' );
		$idempotency_key = $this->trace_id( 'web_search_cloud_test' );
		$response        = $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_cloud_web_search_response( is_array( $response ) ? $response : array(), $runtime_payload );
	}

	public function diagnose_automatic_web_search( array $input ) {
		$topic = trim( sanitize_text_field( (string) ( $input['topic'] ?? $input['query'] ?? '' ) ) );
		if ( '' === $topic ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_web_search_diagnostic_topic',
				__( 'A topic is required for the Cloud web search workflow diagnostic.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$scenario = sanitize_key( (string) ( $input['scenario'] ?? 'article_assistant' ) );
		if ( ! in_array( $scenario, array( 'article_assistant', 'discoverability', 'publish_preflight' ), true ) ) {
			$scenario = 'article_assistant';
		}

		if ( 'article_assistant' === $scenario ) {
			$artifact = $this->build_article_assistant(
				array(
					'topic'         => $topic,
					'title'         => sanitize_text_field( (string) ( $input['title'] ?? $topic ) ),
					'source_policy' => 'strict_sources',
					'draft_notes'   => sanitize_textarea_field( (string) ( $input['draft_notes'] ?? '' ) ),
				)
			);
		} else {
			$artifact = $this->build_content_discoverability_brief(
				array(
					'topic'                  => $topic,
					'title'                  => sanitize_text_field( (string) ( $input['title'] ?? $topic ) ),
					'external_search_intent' => 'publish_preflight' === $scenario ? 'fact_check' : 'writing_context',
					'include_external_search' => true,
				)
			);
		}

		if ( is_wp_error( $artifact ) ) {
			return $artifact;
		}

		$artifact = is_array( $artifact ) ? $artifact : array();
		$search   = $this->extract_workflow_web_search_report( $artifact, $scenario );
		$status   = sanitize_key( (string) ( $search['status'] ?? '' ) );
		$triggered = array() !== $search && ! in_array( $status, array( '', 'cloud_managed', 'skipped' ), true );

		return $this->with_output_contract(
			array(
				'provider'              => 'toolbox',
				'scenario'              => $scenario,
				'topic'                 => $topic,
				'status'                => $triggered ? $status : 'not_triggered',
				'search_triggered'      => $triggered,
				'workflow_artifact_type' => sanitize_key( (string) ( $artifact['artifact_type'] ?? '' ) ),
				'workflow_search'       => $search,
				'result_count'          => absint( $search['result_count'] ?? 0 ),
				'source_count'          => absint( $search['source_count'] ?? 0 ),
				'provider_mode'         => sanitize_key( (string) ( $search['provider_mode'] ?? '' ) ),
				'cloud_provider'        => sanitize_key( (string) ( $search['provider'] ?? '' ) ),
				'handoff'               => array(
					'cloud_runtime'          => 'magick_ai_cloud_addon',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			'web_search_diagnostics',
			'workflow_search_diagnostic'
		);
	}

	public function build_article_brief( string $topic, bool $include_vector = true ) {
		$research  = $this->cloud_web_search_notice();
		$images    = $this->image_candidates( $topic, array( 'per_page' => 6 ) );
		$knowledge = $include_vector ? $this->vector_search( $topic, 4, 'text' ) : null;

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
					'Use Cloud web search or operator-provided references for current external sources.',
					'Select image candidate and preserve attribution.',
					'Create WordPress draft or media proposals through Abilities/Core.',
				),
			),
		);
	}

	public function build_article_assistant( array $input ) {
		$topic = trim( sanitize_text_field( (string) ( $input['topic'] ?? '' ) ) );
		$title = trim( sanitize_text_field( (string) ( $input['title'] ?? $topic ) ) );
		if ( '' === $topic ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_article_assistant_topic',
				__( 'A topic is required to build an article assistant workbench.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}
		if ( '' === $title ) {
			$title = $topic;
		}

		$reviewed_draft = trim( sanitize_textarea_field( (string) ( $input['reviewed_draft_markdown'] ?? ( $input['content_markdown'] ?? '' ) ) ) );
		$draft_notes    = trim( sanitize_textarea_field( (string) ( $input['draft_notes'] ?? '' ) ) );
		$goal           = trim( sanitize_textarea_field( (string) ( $input['article_goal'] ?? '' ) ) );
		$audience       = trim( sanitize_text_field( (string) ( $input['target_audience'] ?? '' ) ) );
		$angle          = trim( sanitize_text_field( (string) ( $input['angle'] ?? '' ) ) );
		$language       = trim( sanitize_text_field( (string) ( $input['language'] ?? 'zh-CN' ) ) );
		$tone           = trim( sanitize_text_field( (string) ( $input['tone'] ?? '' ) ) );
		$target_words   = absint( $input['target_word_count'] ?? ( $input['desired_length'] ?? 1200 ) );
		$target_words   = max( 500, min( 5000, $target_words ) );
		$source_policy  = sanitize_key( (string) ( $input['source_policy'] ?? 'strict_sources' ) );
		if ( ! in_array( $source_policy, array( 'strict_sources', 'review_required', 'operator_notes_only' ), true ) ) {
			$source_policy = 'strict_sources';
		}

		$reference_urls = $this->sanitize_string_list( $input['reference_urls'] ?? array() );
		$must_include   = $this->sanitize_string_list( $input['must_include'] ?? array() );
		$must_avoid     = $this->sanitize_string_list( $input['must_avoid'] ?? array() );
		$context        = $this->settings->get_content_context_for_ability();
		$validation     = $this->settings->validate_content_context_for_ability();
		$context_status = sanitize_key( (string) ( $validation['status'] ?? 'needs_attention' ) );

		$research = 'operator_notes_only' === $source_policy
			? $this->cloud_web_search_notice()
			: $this->cloud_web_search_for_content( $topic, 'writing_context', 4 );
		$images   = $this->image_candidates( $topic, array( 'per_page' => 6 ) );
		$knowledge = $this->vector_search( $topic, 4, 'text' );

		$discoverability = $this->build_content_discoverability_brief(
			array(
				'topic'            => $topic,
				'title'            => $title,
				'content_markdown' => '' !== $reviewed_draft ? $reviewed_draft : $draft_notes,
				'include_external_search' => false,
			)
		);

		$goal_brief = array(
			'topic'             => $topic,
			'title'             => $title,
			'article_goal'      => $goal,
			'target_audience'   => '' !== $audience ? $audience : $this->sanitize_payload( $context['target_audience'] ?? array() ),
			'angle'             => $angle,
			'language'          => $language,
			'tone'              => $tone,
			'target_word_count' => $target_words,
			'source_policy'     => $source_policy,
			'must_include'      => $must_include,
			'must_avoid'        => $must_avoid,
			'context_status'    => $context_status,
		);
		$evidence_pack = $this->article_assistant_evidence_pack( $research, $knowledge, $reference_urls );
		$outline       = $this->article_assistant_outline( $title, $topic, $must_include );
		$draft_candidate = $this->article_assistant_draft_candidate( $reviewed_draft, $draft_notes, $outline, $evidence_pack );
		$discoverability_pack = is_wp_error( $discoverability ) ? array(
			'error' => $discoverability->get_error_message(),
		) : $this->sanitize_payload( $discoverability );
		$risk_report = $this->article_assistant_risk_report(
			$reviewed_draft,
			$draft_notes,
			$context,
			$validation,
			$evidence_pack,
			$must_avoid,
			$source_policy
		);

		$write_plan = null;
		if ( true === ( $risk_report['ready_for_proposal'] ?? false ) ) {
			$write_plan = $this->build_article_write_plan(
				array(
					'title'                  => $title,
					'topic'                  => $topic,
					'content_markdown'       => $reviewed_draft,
					'article_goal_brief'     => $goal_brief,
					'research_evidence_pack' => $evidence_pack,
					'article_outline'        => $outline,
					'article_draft_candidate' => $draft_candidate,
					'discoverability_pack'   => $discoverability_pack,
					'article_risk_report'    => $risk_report,
					'needs_review'           => $risk_report['needs_review'] ?? array(),
					'risk_level'             => $risk_report['risk_level'] ?? 'medium',
				)
			);
		}

		return array(
			'artifact_type'          => 'article_assistant_workbench',
			'composition_role'       => 'article_assistant_workbench',
			'version'                => 1,
			'source_recipe_id'       => 'article_draft_v1',
			'source_recipe_ref'      => 'workflow/wordpress_article_draft',
			'source_recipe_provider' => 'magick-ai-abilities',
			'recipe_execution'       => 'local_operator_orchestration',
			'write_posture'          => 'core_proposal_handoff',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'provider_execution'     => 'server_side_toolbox',
			'workflow_runtime'       => false,
			'batch_execution'        => false,
			'proposal_mode'          => 'single',
			'provider'               => 'toolbox',
			'article_goal_brief'     => $goal_brief,
			'research_evidence_pack' => $evidence_pack,
			'image_candidates'       => is_wp_error( $images ) ? array( 'error' => $images->get_error_message() ) : $images,
			'article_outline'        => $outline,
			'article_draft_candidate' => $draft_candidate,
			'discoverability_pack'   => $discoverability_pack,
			'article_risk_report'    => $risk_report,
			'article_write_plan'     => is_wp_error( $write_plan ) ? array( 'error' => $write_plan->get_error_message() ) : $write_plan,
			'handoff'                => array(
				'assistant_ability_id'   => 'magick-ai-toolbox/build-article-assistant',
				'write_plan_ability_id'  => 'magick-ai-toolbox/build-article-write-plan',
				'recipe_id'              => 'article_draft_v1',
				'recipe_ref'             => 'workflow/wordpress_article_draft',
				'core_route'             => '/wp-json/magick-ai-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'next_steps'             => array(
					'Review the goal brief, evidence, image candidates, outline, and risk report.',
					'Revise the reviewed draft until ready_for_proposal is true.',
					'Submit only the article_write_plan to Core proposal intake; Toolbox does not approve or execute it.',
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
			'source_recipe_id'       => 'article_draft_v1',
			'source_recipe_ref'      => 'workflow/wordpress_article_draft',
			'source_recipe_provider' => 'magick-ai-abilities',
			'recipe_execution'       => 'local_operator_orchestration',
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
					'recipe_step'       => 'host_governed_create_draft',
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
				'recipe_id'              => 'article_draft_v1',
				'recipe_ref'             => 'workflow/wordpress_article_draft',
				'core_route'             => '/wp-json/magick-ai-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_article_batch_write_plan( array $input ) {
		$articles = is_array( $input['articles'] ?? null ) ? array_values( $input['articles'] ) : array();
		if ( count( $articles ) < 2 || count( $articles ) > 5 ) {
			return new WP_Error(
				'magick_ai_toolbox_article_batch_size_invalid',
				__( 'Article batch write plans require 2 to 5 reviewed draft articles.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic          = sanitize_text_field( (string) ( $input['topic'] ?? 'Article batch draft plan' ) );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		$risk_level    = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'medium' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;
		$article_artifacts  = array();
		$write_actions      = array();
		$preview            = array();

		foreach ( $articles as $index => $article ) {
			$article = is_array( $article ) ? $article : array();
			$title   = trim( sanitize_text_field( (string) ( $article['title'] ?? '' ) ) );
			$content = trim( sanitize_textarea_field( (string) ( $article['content_markdown'] ?? ( $article['content'] ?? '' ) ) ) );
			if ( '' === $title || '' === $content ) {
				return new WP_Error(
					'magick_ai_toolbox_article_batch_item_invalid',
					__( 'Every article batch item requires title and content_markdown.', 'magick-ai-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$action_id = 'create_article_draft_' . ( $index + 1 );
			$excerpt   = sanitize_textarea_field( (string) ( $article['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) );
			$article_artifacts[] = array(
				'article_goal_brief'      => is_array( $article['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $article['article_goal_brief'] ) : array(
					'topic' => $topic,
					'title' => $title,
				),
				'research_evidence_pack'  => is_array( $article['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $article['research_evidence_pack'] ) : array(
					'sources' => is_array( $article['sources'] ?? null ) ? $this->sanitize_payload( $article['sources'] ) : array(),
				),
				'article_outline'         => is_array( $article['article_outline'] ?? null ) ? $this->sanitize_payload( $article['article_outline'] ) : array(
					'title'    => $title,
					'sections' => array(),
				),
				'article_draft_candidate' => is_array( $article['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $article['article_draft_candidate'] ) : array(
					'content_markdown' => $content,
				),
				'discoverability_pack'    => is_array( $article['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $article['discoverability_pack'] ) : array(
					'excerpt' => $excerpt,
				),
				'article_risk_report'     => is_array( $article['article_risk_report'] ?? null ) ? $this->sanitize_payload( $article['article_risk_report'] ) : array(
					'risk_level'         => $risk_level,
					'blocked_claims'     => $blocked_claims,
					'ready_for_proposal' => $ready_for_proposal,
				),
			);
			$write_actions[] = array(
				'action_id'         => $action_id,
				'target_ability_id' => 'magick-ai/create-draft',
				'recipe_step'       => 'host_governed_create_draft',
				'input'             => array(
					'title'          => $title,
					'content'        => $content,
					'content_format' => sanitize_key( (string) ( $article['content_format'] ?? 'plain' ) ),
					'excerpt'        => $excerpt,
					'status'         => 'draft',
					'dry_run'        => true,
					'commit'         => false,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Create one reviewed AI-assisted article draft through Core governance.', 'magick-ai-toolbox' ),
			);
			$preview[] = array(
				'action_id' => $action_id,
				'title'     => $title,
				'status'    => 'draft',
				'excerpt'   => $excerpt,
			);
		}

		return array(
			'artifact_type'             => 'article_batch_write_plan',
			'composition_role'          => 'core_article_batch_write_plan',
			'version'                   => 1,
			'source_recipe_id'          => 'article_batch_draft_v1',
			'source_recipe_ref'         => 'workflow/wordpress_article_batch_draft',
			'source_recipe_provider'    => 'magick-ai-toolbox',
			'recipe_execution'          => 'local_operator_orchestration',
			'write_posture'             => 'core_proposal_handoff',
			'direct_wordpress_write'    => false,
			'batch_id'                  => 'article_batch_write_' . substr( md5( $topic . '|' . wp_json_encode( $preview ) ), 0, 12 ),
			'requires_approval'         => true,
			'dry_run'                   => true,
			'commit_execution'          => false,
			'proposal_mode'             => 'batch',
			'batch_approval'            => true,
			'publish_allowed'           => false,
			'partial_success'           => false,
			'action_count'              => count( $write_actions ),
			'articles'                  => $article_artifacts,
			'preview'                   => $preview,
			'article_batch_risk_report' => array(
				'risk_level'         => $risk_level,
				'blocked_claims'     => $blocked_claims,
				'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
				'ready_for_proposal' => $ready_for_proposal,
			),
			'write_actions'             => $write_actions,
			'handoff'                   => array(
				'plan_ability_id'        => 'magick-ai-toolbox/build-article-batch-write-plan',
				'recipe_id'              => 'article_batch_draft_v1',
				'recipe_ref'             => 'workflow/wordpress_article_batch_draft',
				'core_route'             => '/wp-json/magick-ai-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_article_media_batch_write_plan( array $input ) {
		$articles = is_array( $input['articles'] ?? null ) ? array_values( $input['articles'] ) : array();
		if ( count( $articles ) < 1 || count( $articles ) > 5 ) {
			return new WP_Error(
				'magick_ai_toolbox_article_media_batch_size_invalid',
				__( 'Article media batch write plans require 1 to 5 reviewed draft articles.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic          = sanitize_text_field( (string) ( $input['topic'] ?? 'Article media batch draft plan' ) );
		$search_images  = true === (bool) ( $input['search_images'] ?? false );
		$image_provider = sanitize_key( (string) ( $input['image_provider'] ?? $input['provider'] ?? '' ) );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		$risk_level     = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'medium' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;
		$article_artifacts  = array();
		$write_actions      = array();
		$preview            = array();
		$media_workflow     = array();

		foreach ( $articles as $index => $article ) {
			$article = is_array( $article ) ? $article : array();
			$title   = trim( sanitize_text_field( (string) ( $article['title'] ?? '' ) ) );
			$content = trim( sanitize_textarea_field( (string) ( $article['content_markdown'] ?? ( $article['content'] ?? '' ) ) ) );
			if ( '' === $title || '' === $content ) {
				return new WP_Error(
					'magick_ai_toolbox_article_media_batch_item_invalid',
					__( 'Every article media batch item requires title and content_markdown.', 'magick-ai-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$candidate = $this->resolve_article_media_candidate( $article, $title, $topic, $search_images, $image_provider );
			if ( is_wp_error( $candidate ) ) {
				$candidate->add_data(
					array_merge(
						(array) $candidate->get_error_data(),
						array(
							'status' => 400,
							'index'  => $index,
						)
					)
				);
				return $candidate;
			}

			$image_url = (string) ( $candidate['regular_url'] ?? $candidate['small_url'] ?? $candidate['url'] ?? '' );
			if ( '' === $image_url ) {
				return new WP_Error(
					'magick_ai_toolbox_article_media_url_missing',
					__( 'Every article media batch item requires a selected image URL.', 'magick-ai-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$position      = $index + 1;
			$create_id     = 'create_article_draft_' . $position;
			$upload_id     = 'upload_featured_image_' . $position;
			$metadata_id   = 'update_featured_image_details_' . $position;
			$featured_id   = 'set_featured_image_' . $position;
			$excerpt       = sanitize_textarea_field( (string) ( $article['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) );
			$provider      = sanitize_key( (string) ( $candidate['provider'] ?? 'external' ) );
			$candidate_source_type = sanitize_key( (string) ( $candidate['source_type'] ?? '' ) );
			if ( 'ai_generated' === $provider || 'ai_generated' === $candidate_source_type ) {
				$source_type = 'ai_generated';
			} elseif ( in_array( $provider, array( 'unsplash', 'pixabay', 'pexels' ), true ) || 'stock' === $candidate_source_type ) {
				$source_type = 'stock';
			} else {
				$source_type = 'external';
			}
			$source_url    = esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) );
			$photographer  = sanitize_text_field( (string) ( $candidate['photographer'] ?? $candidate['photographer_name'] ?? '' ) );
			$attribution   = sanitize_textarea_field( (string) ( $candidate['attribution'] ?? $candidate['attribution_text'] ?? '' ) );
			$alt           = sanitize_textarea_field( (string) ( $candidate['alt_description'] ?? $candidate['description'] ?? $title ) );
			$description   = sanitize_textarea_field( (string) ( $candidate['description'] ?? $alt ) );
			$file_name     = sanitize_file_name( (string) ( $article['file_name'] ?? $candidate['file_name'] ?? '' ) );

			$article_artifacts[] = array(
				'article_goal_brief'      => is_array( $article['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $article['article_goal_brief'] ) : array(
					'topic'       => $topic,
					'title'       => $title,
					'image_query' => sanitize_text_field( (string) ( $article['image_query'] ?? $title ) ),
				),
				'research_evidence_pack'  => is_array( $article['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $article['research_evidence_pack'] ) : array(
					'sources' => is_array( $article['sources'] ?? null ) ? $this->sanitize_payload( $article['sources'] ) : array(),
				),
				'article_outline'         => is_array( $article['article_outline'] ?? null ) ? $this->sanitize_payload( $article['article_outline'] ) : array(
					'title'    => $title,
					'sections' => array(),
				),
				'article_draft_candidate' => is_array( $article['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $article['article_draft_candidate'] ) : array(
					'content_markdown' => $content,
				),
				'discoverability_pack'    => is_array( $article['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $article['discoverability_pack'] ) : array(
					'excerpt' => $excerpt,
				),
				'article_risk_report'     => is_array( $article['article_risk_report'] ?? null ) ? $this->sanitize_payload( $article['article_risk_report'] ) : array(
					'risk_level'         => $risk_level,
					'blocked_claims'     => $blocked_claims,
					'ready_for_proposal' => $ready_for_proposal,
				),
				'featured_image_candidate' => $this->sanitize_payload( $candidate ),
			);

			$write_actions[] = array(
				'action_id'         => $create_id,
				'target_ability_id' => 'magick-ai/create-draft',
				'recipe_step'       => 'host_governed_create_draft',
				'input'             => array(
					'title'          => $title,
					'content'        => $content,
					'content_format' => sanitize_key( (string) ( $article['content_format'] ?? 'plain' ) ),
					'excerpt'        => $excerpt,
					'status'         => 'draft',
					'dry_run'        => true,
					'commit'         => false,
					'idempotency_key' => 'article-media-draft-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Create one reviewed AI-assisted article draft through Core governance.', 'magick-ai-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $upload_id,
				'target_ability_id' => 'magick-ai/upload-media-from-url',
				'recipe_step'       => 'host_governed_upload_featured_image',
				'depends_on'        => array( $create_id ),
				'input'             => array(
					'url'               => $image_url,
					'title'             => $title,
					'file_name'         => $file_name,
					'alt'               => $alt,
					'caption'           => $attribution,
					'description'       => $description,
					'source_type'       => $source_type,
					'source_page_url'   => $source_url,
					'photographer_name' => $photographer,
					'attribution_text'  => $attribution,
					'copyright_notice'  => sanitize_text_field( (string) ( $candidate['copyright_notice'] ?? '' ) ),
					'attach_to_post_id' => '$outputs.' . $create_id . '.post_id',
					'dry_run'           => true,
					'commit'            => false,
					'idempotency_key'   => 'article-media-upload-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Upload the reviewed image-source candidate into the media library after Core approval.', 'magick-ai-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $metadata_id,
				'target_ability_id' => 'magick-ai/update-media-details',
				'recipe_step'       => 'host_governed_update_featured_image_metadata',
				'depends_on'        => array( $upload_id ),
				'input'             => array(
					'attachment_id'     => '$outputs.' . $upload_id . '.attachment_id',
					'alt'               => $alt,
					'caption'           => $attribution,
					'description'       => $description,
					'source_type'       => $source_type,
					'source_page_url'   => $source_url,
					'photographer_name' => $photographer,
					'attribution_text'  => $attribution,
					'dry_run'           => true,
					'commit'            => false,
					'idempotency_key'   => 'article-media-details-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Apply reviewed image attribution and accessibility metadata after upload.', 'magick-ai-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $featured_id,
				'target_ability_id' => 'magick-ai/set-post-featured-image',
				'recipe_step'       => 'host_governed_set_featured_image',
				'depends_on'        => array( $create_id, $upload_id ),
				'input'             => array(
					'post_id'        => '$outputs.' . $create_id . '.post_id',
					'attachment_id'  => '$outputs.' . $upload_id . '.attachment_id',
					'dry_run'        => true,
					'commit'         => false,
					'idempotency_key' => 'article-media-featured-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Set the uploaded, reviewed media item as the draft featured image after Core approval.', 'magick-ai-toolbox' ),
			);

			$media_workflow[] = array(
				'article_index'      => $index,
				'title'              => $title,
				'image_query'        => sanitize_text_field( (string) ( $article['image_query'] ?? $title ) ),
				'candidate_provider' => $provider,
				'source_url'         => $source_url,
				'download_location'  => esc_url_raw( (string) ( $candidate['download_location'] ?? '' ) ),
				'attribution'        => $attribution,
				'action_ids'         => array( $create_id, $upload_id, $metadata_id, $featured_id ),
			);
			$preview[] = array(
				'action_id'         => $create_id,
				'title'             => $title,
				'status'            => 'draft',
				'excerpt'           => $excerpt,
				'featured_image_url' => $image_url,
				'attribution'       => $attribution,
			);
		}

		return array(
			'artifact_type'             => 'article_media_batch_write_plan',
			'composition_role'          => 'core_article_media_batch_write_plan',
			'version'                   => 1,
			'source_recipe_id'          => 'article_media_batch_draft_v1',
			'source_recipe_ref'         => 'workflow/wordpress_article_media_batch_draft',
			'source_recipe_provider'    => 'magick-ai-toolbox',
			'recipe_execution'          => 'local_operator_orchestration',
			'write_posture'             => 'core_proposal_handoff',
			'direct_wordpress_write'    => false,
			'batch_id'                  => 'article_media_batch_write_' . substr( md5( $topic . '|' . wp_json_encode( $preview ) ), 0, 12 ),
			'requires_approval'         => true,
			'dry_run'                   => true,
			'commit_execution'          => false,
			'proposal_mode'             => 'batch',
			'batch_approval'            => true,
			'publish_allowed'           => false,
			'partial_success'           => false,
			'action_count'              => count( $write_actions ),
			'articles'                  => $article_artifacts,
			'media_workflow'            => $media_workflow,
			'preview'                   => $preview,
			'article_batch_risk_report' => array(
				'risk_level'         => $risk_level,
				'blocked_claims'     => $blocked_claims,
				'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
				'ready_for_proposal' => $ready_for_proposal,
			),
			'write_actions'             => $write_actions,
			'handoff'                   => array(
				'plan_ability_id'        => 'magick-ai-toolbox/build-article-media-batch-write-plan',
				'recipe_id'              => 'article_media_batch_draft_v1',
				'recipe_ref'             => 'workflow/wordpress_article_media_batch_draft',
				'core_route'             => '/wp-json/magick-ai-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function build_image_candidate_adoption_plan( array $input ) {
		$raw_candidate = $input['image_candidate'] ?? ( $input['candidate'] ?? array() );
		if ( is_string( $raw_candidate ) ) {
			$decoded = json_decode( $raw_candidate, true );
			$raw_candidate = is_array( $decoded ) ? $decoded : array();
		}
		$candidate = is_array( $raw_candidate ) ? $raw_candidate : array();
		if ( empty( $candidate ) ) {
			$direct_url = $this->first_non_empty_url(
				array(
					$input['download_url'] ?? '',
					$input['image_url'] ?? '',
					$input['url'] ?? '',
				)
			);
			if ( '' !== $direct_url ) {
				$candidate = array(
					'download_url'           => $direct_url,
					'thumbnail_url'          => $input['thumbnail_url'] ?? '',
					'source_url'             => $input['source_url'] ?? '',
					'source_type'            => $input['source_type'] ?? 'external',
					'provider'               => $input['provider'] ?? 'manual',
					'provider_origin'        => $input['provider_origin'] ?? 'toolbox',
					'title'                  => $input['title'] ?? '',
					'description'            => $input['description'] ?? ( $input['alt'] ?? '' ),
					'alt_description'        => $input['alt'] ?? '',
					'attribution'            => $input['attribution_text'] ?? '',
					'photographer'           => $input['photographer_name'] ?? '',
					'prompt'                 => $input['prompt'] ?? '',
					'model'                  => $input['model'] ?? '',
					'license_review_status'  => $input['license_review_status'] ?? '',
					'warnings'               => $this->sanitize_string_list( $input['warnings'] ?? array() ),
				);
			}
		}
		if ( empty( $candidate ) ) {
			return new WP_Error(
				'magick_ai_toolbox_image_candidate_required',
				__( 'A selected image URL or image_candidate object is required before building an adoption plan.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$candidate = $this->normalize_image_candidate_contract( $candidate );
		$image_url = $this->first_non_empty_url(
			array(
				$candidate['download_url'] ?? '',
				$candidate['regular_url'] ?? '',
				$candidate['small_url'] ?? '',
				$candidate['url'] ?? '',
			)
		);
		if ( '' === $image_url ) {
			return new WP_Error(
				'magick_ai_toolbox_image_candidate_url_missing',
				__( 'The selected image candidate must include a download_url, regular_url, small_url, or url.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		$set_featured_image = $post_id > 0 && ! empty( $input['set_featured_image'] );
		$title = trim( sanitize_text_field( (string) ( $input['title'] ?? $candidate['title'] ?? $candidate['description'] ?? __( 'Selected image candidate', 'magick-ai-toolbox' ) ) ) );
		$alt = trim( sanitize_textarea_field( (string) ( $input['alt'] ?? $candidate['alt_description'] ?? $candidate['description'] ?? $title ) ) );
		$description = trim( sanitize_textarea_field( (string) ( $input['description'] ?? $candidate['description'] ?? $alt ) ) );
		$attribution = trim( sanitize_textarea_field( (string) ( $input['attribution_text'] ?? $candidate['attribution'] ?? '' ) ) );
		$source_type = sanitize_key( (string) ( $candidate['source_type'] ?? 'external' ) );
		if ( ! in_array( $source_type, array( 'owned', 'ai_generated', 'stock', 'external', 'manual_upload', 'test' ), true ) ) {
			$source_type = 'external';
		}
		if ( 'manual_upload' === $source_type ) {
			$source_type = 'owned';
		}

		$source_url = esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) );
		$photographer = sanitize_text_field( (string) ( $candidate['photographer'] ?? $candidate['photographer_name'] ?? '' ) );
		$file_name = sanitize_file_name( (string) ( $input['file_name'] ?? $candidate['file_name'] ?? $candidate['suggested_filename'] ?? '' ) );
		$filename_policy = array(
			'owner'                          => 'wordpress_write_ability_final',
			'proposed_filename'              => $file_name,
			'final_sanitize_unique_required' => true,
			'preserve_attachment_metadata'   => true,
			'source'                         => '' !== $file_name ? 'reviewed_or_candidate_suggestion' : 'wordpress_default',
		);
		$upload_id = 'upload_image_candidate';
		$metadata_id = 'update_image_candidate_details';
		$featured_id = 'set_image_candidate_featured_image';

		$upload_input = array(
			'url'               => $image_url,
			'title'             => $title,
			'file_name'         => $file_name,
			'alt'               => $alt,
			'caption'           => $attribution,
			'description'       => $description,
			'source_type'       => $source_type,
			'source_page_url'   => $source_url,
			'photographer_name' => $photographer,
			'attribution_text'  => $attribution,
			'copyright_notice'  => sanitize_text_field( (string) ( $input['copyright_notice'] ?? $candidate['copyright_notice'] ?? '' ) ),
			'dry_run'           => true,
			'commit'            => false,
			'idempotency_key'   => 'image-candidate-upload-' . substr( md5( $image_url . '|' . $post_id ), 0, 12 ),
		);
		if ( $post_id > 0 ) {
			$upload_input['attach_to_post_id'] = $post_id;
		}

		$write_actions = array(
			array(
				'action_id'         => $upload_id,
				'target_ability_id' => 'magick-ai/upload-media-from-url',
				'recipe_step'       => 'host_governed_upload_image_candidate',
				'input'             => $upload_input,
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => true,
				'reason'            => __( 'Import the reviewed image candidate into the media library after Core approval.', 'magick-ai-toolbox' ),
			),
			array(
				'action_id'         => $metadata_id,
				'target_ability_id' => 'magick-ai/update-media-details',
				'recipe_step'       => 'host_governed_update_image_candidate_metadata',
				'depends_on'        => array( $upload_id ),
				'input'             => array(
					'attachment_id'     => '$outputs.' . $upload_id . '.attachment_id',
					'title'             => $title,
					'alt'               => $alt,
					'caption'           => $attribution,
					'description'       => $description,
					'source_type'       => $source_type,
					'source_page_url'   => $source_url,
					'photographer_name' => $photographer,
					'attribution_text'  => $attribution,
					'copyright_notice'  => sanitize_text_field( (string) ( $input['copyright_notice'] ?? $candidate['copyright_notice'] ?? '' ) ),
					'dry_run'           => true,
					'commit'            => false,
					'idempotency_key'   => 'image-candidate-details-' . substr( md5( $image_url . '|' . $post_id ), 0, 12 ),
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => true,
				'reason'            => __( 'Apply reviewed image candidate metadata after media import.', 'magick-ai-toolbox' ),
			),
		);

		if ( $set_featured_image ) {
			$write_actions[] = array(
				'action_id'         => $featured_id,
				'target_ability_id' => 'magick-ai/set-post-featured-image',
				'recipe_step'       => 'host_governed_set_image_candidate_featured_image',
				'depends_on'        => array( $upload_id ),
				'input'             => array(
					'post_id'        => $post_id,
					'attachment_id'  => '$outputs.' . $upload_id . '.attachment_id',
					'dry_run'        => true,
					'commit'         => false,
					'idempotency_key' => 'image-candidate-featured-' . substr( md5( $image_url . '|' . $post_id ), 0, 12 ),
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => true,
				'reason'            => __( 'Set the imported image candidate as the post featured image after Core approval.', 'magick-ai-toolbox' ),
			);
		}

		return array(
			'artifact_type'               => 'image_candidate_adoption_plan',
			'composition_role'            => 'core_image_candidate_adoption_plan',
			'version'                     => 1,
			'candidate_contract_version'  => 'image_candidate.v1',
			'source_recipe_id'            => 'image_candidate_adoption_v1',
			'source_recipe_ref'           => 'workflow/image_candidate_adoption',
			'source_recipe_provider'      => 'magick-ai-toolbox',
			'recipe_execution'            => 'local_operator_orchestration',
			'write_posture'               => 'core_proposal_handoff',
			'direct_wordpress_write'      => false,
			'proposed_filename'           => $file_name,
			'filename_policy'             => $filename_policy,
			'batch_id'                    => 'image_candidate_adoption_' . substr( md5( $image_url . '|' . $post_id . '|' . wp_json_encode( $write_actions ) ), 0, 12 ),
			'requires_approval'           => true,
			'dry_run'                     => true,
			'commit_execution'            => false,
			'proposal_mode'               => 'batch',
			'batch_approval'              => true,
			'action_count'                => count( $write_actions ),
			'selected_image_candidate'    => $this->sanitize_payload( $candidate ),
			'preview'                     => array(
				array(
					'action_id'        => $upload_id,
					'image_url'        => $image_url,
					'thumbnail_url'    => esc_url_raw( (string) ( $candidate['thumbnail_url'] ?? $image_url ) ),
					'source_type'      => $source_type,
					'provider'         => sanitize_key( (string) ( $candidate['provider'] ?? 'external' ) ),
					'provider_origin'  => sanitize_key( (string) ( $candidate['provider_origin'] ?? 'toolbox' ) ),
					'proposed_filename' => $file_name,
					'filename_policy'   => $filename_policy,
					'post_id'           => $post_id,
					'set_featured_image' => $set_featured_image,
					'attribution'      => $attribution,
				),
			),
			'write_actions'               => $write_actions,
			'handoff'                     => array(
				'plan_ability_id'        => 'magick-ai-toolbox/build-image-candidate-adoption-plan',
				'recipe_id'              => 'image_candidate_adoption_v1',
				'recipe_ref'             => 'workflow/image_candidate_adoption',
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
		$exceptions        = is_array( $context['exceptions'] ?? null ) ? $this->sanitize_payload( $context['exceptions'] ) : array();
		$proposal_template = array();
		$candidates        = array();
		$include_external_search = ! array_key_exists( 'include_external_search', $input ) || ! empty( $input['include_external_search'] );
		$external_search_intent  = sanitize_key( (string) ( $input['external_search_intent'] ?? 'writing_context' ) );
		if ( ! in_array( $external_search_intent, array( 'fact_check', 'news', 'writing_context', 'competitor_research', 'source_discovery', 'external_links' ), true ) ) {
			$external_search_intent = 'writing_context';
		}
		$external_research = $include_external_search
			? $this->cloud_web_search_for_content( sanitize_text_field( (string) ( $source['topic'] ?? $source['title'] ?? '' ) ), $external_search_intent, 3 )
			: $this->cloud_web_search_notice();
		$sections          = array(
			'seo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['seo'] ?? '' ) ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
			'aeo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['aeo'] ?? '' ) ),
				'allow_faq_generation' => ! empty( $context['rules']['allow_faq_generation'] ),
				'allow_answer_summary' => ! empty( $context['rules']['allow_aeo_summary'] ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
			'geo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['geo'] ?? '' ) ),
				'allow_geo_summary'  => ! empty( $context['rules']['allow_geo_summary'] ),
				'allow_structured_data_suggestions' => ! empty( $context['rules']['allow_structured_data_suggestions'] ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
		);

		foreach ( $allowed_fields as $field ) {
			$proposal_template[ $field ] = array(
				'instruction' => $this->content_discoverability_field_instruction( $field ),
				'value'       => null,
			);
			$group = $this->content_discoverability_field_group( $field );
			$sections[ $group ]['allowed_fields'][] = $field;
			$sections[ $group ]['proposal_template'][ $field ] = $proposal_template[ $field ];

			$candidate = $this->content_discoverability_candidate( $field, $source, $context );
			if ( null !== $candidate ) {
				$candidates[ $field ] = $candidate;
				$sections[ $group ]['candidate_suggestions'][ $field ] = $candidate;
			}
		}

		return array(
			'artifact_type'          => 'content_discoverability_brief',
			'composition_role'       => 'seo_aeo_geo_brief',
			'version'                => 1,
			'primary_contract'       => true,
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'context_validation'     => $validation,
			'content_context'        => $context,
			'exceptions'             => $exceptions,
			'special_cases'          => $exceptions,
			'source'                 => $source,
			'external_research'      => $external_research,
			'seo'                    => $sections['seo'],
			'aeo'                    => $sections['aeo'],
			'geo'                    => $sections['geo'],
			'ai_instructions'        => array(
				'Use the content_context as the site-level rule source.',
				'Use only facts present in the supplied source, public site context, or cited evidence.',
				'Use external_research only as suggestion evidence and preserve source URLs for operator review.',
				'Do not invent customer cases, ranking guarantees, source citations, or unavailable product features.',
				'Return suggestions only for proposal_allowed_fields.',
				'Respect forbidden claims and preserve the requested brand voice.',
				'Apply exceptions and special_cases before generating FAQ, HowTo, schema, or confident product claims.',
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

	public function build_ai_article_writing_pack( array $input ) {
		$brief = $this->build_content_discoverability_brief( $input );
		if ( is_wp_error( $brief ) ) {
			return $brief;
		}

		$brief              = is_array( $brief ) ? $brief : array();
		$source             = is_array( $brief['source'] ?? null ) ? $brief['source'] : array();
		$context            = is_array( $brief['content_context'] ?? null ) ? $brief['content_context'] : array();
		$validation         = is_array( $brief['context_validation'] ?? null ) ? $brief['context_validation'] : array();
		$rules              = is_array( $context['rules'] ?? null ) ? $context['rules'] : array();
		$keywords           = is_array( $context['keywords'] ?? null ) ? $context['keywords'] : array();
		$claims             = is_array( $context['claims'] ?? null ) ? $context['claims'] : array();
		$topic              = sanitize_text_field( (string) ( $source['topic'] ?? ( $input['topic'] ?? '' ) ) );
		$title              = sanitize_text_field( (string) ( $source['title'] ?? ( $input['title'] ?? $topic ) ) );
		$language           = sanitize_text_field( (string) ( $input['language'] ?? 'zh-CN' ) );
		$article_type       = sanitize_key( (string) ( $input['article_type'] ?? 'practical_guide' ) );
		$target_word_count  = absint( $input['target_word_count'] ?? 1200 );
		$target_word_count  = max( 500, min( 5000, $target_word_count ) );
		$context_status     = sanitize_key( (string) ( $validation['status'] ?? 'needs_attention' ) );
		$ready_for_writing  = in_array( $context_status, array( 'ready', 'ready_with_warnings' ), true );
		$proposal_fields    = $this->sanitize_string_list( $brief['proposal_allowed_fields'] ?? array() );
		$primary_keywords   = $this->sanitize_string_list( $keywords['primary'] ?? array() );
		$long_tail_keywords = $this->sanitize_string_list( $keywords['long_tail'] ?? array() );
		$entity_keywords    = $this->sanitize_string_list( $keywords['entities'] ?? array() );
		$forbidden_claims   = $this->sanitize_string_list( $claims['forbidden'] ?? array() );

		return array(
			'artifact_type'          => 'ai_article_writing_pack',
			'composition_role'       => 'ai_article_writing_pack',
			'version'                => 1,
			'primary_contract'       => false,
			'contract_role'          => 'openclaw_natural_language_fallback',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'provider_execution'     => 'none',
			'ready_for_writing'      => $ready_for_writing,
			'context_status'         => $context_status,
			'source'                 => $source,
			'topic'                  => $topic,
			'title'                  => $title,
			'language'               => $language,
			'article_type'           => $article_type,
			'target_word_count'      => $target_word_count,
			'content_context'        => $context,
			'context_validation'     => $validation,
			'discoverability_brief'  => $brief,
			'exceptions'             => is_array( $brief['exceptions'] ?? null ) ? $brief['exceptions'] : array(),
			'special_cases'          => is_array( $brief['special_cases'] ?? null ) ? $brief['special_cases'] : array(),
			'article_prompt_pack'    => array(
				'user_intent'      => sanitize_textarea_field( (string) ( $input['user_intent'] ?? 'Write one article from the supplied topic and site rules.' ) ),
				'writing_goal'     => sprintf(
					'Write one %1$s article in %2$s about: %3$s.',
					$article_type,
					$language,
					'' !== $topic ? $topic : $title
				),
				'style_rules'      => array_filter(
					array(
						(string) ( $context['brand_voice'] ?? '' ),
						(string) ( $rules['seo'] ?? '' ),
						(string) ( $rules['aeo'] ?? '' ),
						(string) ( $rules['geo'] ?? '' ),
					)
				),
				'keyword_targets'  => array(
					'primary'   => $primary_keywords,
					'long_tail' => $long_tail_keywords,
					'entities'  => $entity_keywords,
				),
				'proposal_fields'  => $proposal_fields,
				'forbidden_claims' => $forbidden_claims,
			),
			'suggested_article_structure' => $this->article_writing_pack_structure( $rules ),
			'ai_instructions'      => array(
				'Use this pack as the local site-context source before writing.',
				'If ready_for_writing is false, stop and ask the operator to complete Toolbox Content Context.',
				'Write from the supplied source and topic; do not invent product facts, customer cases, rankings, citations, or unavailable features.',
				'Respect forbidden claims, brand voice, SEO rules, AEO rules, and GEO rules.',
				'Return article draft text and proposal-ready SEO/AEO/GEO suggestions only.',
				'Do not write WordPress data. Final WordPress writes must go through Core proposal approval and commit preflight.',
			),
			'handoff'              => array(
				'pack_ability_id'       => 'magick-ai-toolbox/build-ai-article-writing-pack',
				'brief_ability_id'      => 'magick-ai-toolbox/build-content-discoverability-brief',
				'write_plan_ability_id' => 'magick-ai-toolbox/build-article-write-plan',
				'final_writes'          => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'next_steps'            => array(
					'Use the pack to draft one article and SEO/AEO/GEO suggestions.',
					'After human review, convert the reviewed draft with build-article-write-plan.',
					'Send write-like outcomes through Core proposal, approval, and commit preflight.',
				),
			),
		);
	}

	public function build_media_brief( string $post_context ) {
		return $this->image_candidates( $this->post_context_to_image_query( $post_context ), array( 'per_page' => 8 ) );
	}

	public function build_media_derivative_handoff( array $input ) {
		$attachment_id = absint( $input['attachment_id'] ?? 0 );
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'magick_ai_toolbox_missing_attachment_id',
				__( 'An attachment_id is required to build a media derivative handoff.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$overrides = array( 'attachment_id' => $attachment_id );
		if ( '' !== trim( (string) ( $input['target_format'] ?? '' ) ) ) {
			$overrides['target_format'] = sanitize_key( (string) $input['target_format'] );
		}
		if ( '' !== trim( (string) ( $input['max_width'] ?? '' ) ) ) {
			$overrides['max_width'] = absint( $input['max_width'] );
		}
		if ( '' !== trim( (string) ( $input['quality'] ?? '' ) ) ) {
			$overrides['quality'] = absint( $input['quality'] );
		}
		$overrides = array_merge( $overrides, $this->media_derivative_watermark_overrides( $input ) );

		$core_policy_available = function_exists( 'magick_ai_core_get_media_derivative_settings' );
		$core_policy = $core_policy_available ? magick_ai_core_get_media_derivative_settings() : $this->fallback_media_derivative_policy();
		$ability_input = function_exists( 'magick_ai_core_build_media_derivative_ability_input' )
			? magick_ai_core_build_media_derivative_ability_input( $overrides )
			: $this->fallback_media_derivative_ability_input( $overrides, $core_policy );

		$warnings = array();
		if ( ! $core_policy_available ) {
			$warnings[] = __( 'Magick AI Core media policy helper is unavailable; fallback defaults were used for this one-run handoff.', 'magick-ai-toolbox' );
		}
		if ( ! empty( $core_policy['watermark_enabled'] ) && empty( $core_policy['watermark_configured'] ) ) {
			$warnings[] = __( 'Watermark is enabled in policy but no logo attachment is configured.', 'magick-ai-toolbox' );
		}

		return array(
			'artifact_type'          => 'media_derivative_handoff',
			'composition_role'       => 'media_derivative_operator_handoff',
			'version'                => 1,
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'provider'               => 'toolbox',
			'attachment_id'          => $attachment_id,
			'core_policy_available'  => $core_policy_available,
			'core_policy'            => $this->sanitize_payload( $core_policy ),
			'ability_id'             => 'magick-ai/build-media-derivative-cloud-request',
			'ability_input'          => $this->sanitize_payload( $ability_input ),
			'optimization_plan_ability_id' => 'magick-ai/build-media-optimization-plan',
			'preferred_core_route'   => '/wp-json/magick-ai-adapter/v1/proposals/from-plan',
			'required_reviewed_input' => array( 'media_details_input', 'derivative_artifact' ),
			'warnings'               => $warnings,
			'handoff'                => array(
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'default_user_intent'    => 'optimize_this_media_item',
				'do_not_split_user_intent' => true,
				'legacy_derivative_only' => 'lower_level_review_only',
				'next_steps'             => array(
					'Run the local media derivative request ability with ability_input.',
					'Use Cloud Addon only as a verified transport when available.',
					'Add reviewed media_details_input before Core proposal submission.',
					'Submit Adapter from_plan_request to /proposals/from-plan so Core creates one media optimization proposal.',
					'If Core lacks magick-ai/build-media-optimization-plan, update Core and Abilities instead of splitting the same user intent into two proposals.',
				),
			),
		);
	}

	private function fallback_media_derivative_policy(): array {
		return array(
			'enabled'                  => false,
			'target_format'            => 'webp',
			'max_width'                => 1600,
			'quality'                  => 82,
			'watermark_enabled'        => false,
			'watermark_configured'     => false,
			'watermark_attachment_id'  => 0,
			'watermark_position'       => 'bottom_right',
			'watermark_opacity'        => 80,
			'watermark_scale'          => 20,
			'watermark_margin'         => 24,
			'use_cloud_when_available' => true,
			'policy_owner'             => 'magick_ai_core',
			'final_write_owner'        => 'local_wordpress_host',
		);
	}

	private function fallback_media_derivative_ability_input( array $overrides, array $policy ): array {
		$input = array(
			'attachment_id'    => absint( $overrides['attachment_id'] ?? 0 ),
			'preferred_format' => sanitize_key( (string) ( $overrides['target_format'] ?? $policy['target_format'] ?? 'webp' ) ),
			'target_max_width' => max( 320, min( 7680, absint( $overrides['max_width'] ?? $policy['max_width'] ?? 1600 ) ) ),
			'quality'          => max( 1, min( 100, absint( $overrides['quality'] ?? $policy['quality'] ?? 82 ) ) ),
		);
		if ( is_array( $overrides['watermark'] ?? null ) && ! empty( $overrides['watermark'] ) ) {
			$input['watermark'] = $this->sanitize_payload( $overrides['watermark'] );
		}
		return $input;
	}

	private function media_derivative_watermark_overrides( array $input ): array {
		$mode = sanitize_key( (string) ( $input['watermark_mode'] ?? 'core' ) );
		if ( 'off' === $mode ) {
			return array( 'watermark_enabled' => false );
		}
		if ( 'override' !== $mode ) {
			return array();
		}

		$position = sanitize_key( (string) ( $input['watermark_position'] ?? 'bottom_right' ) );
		if ( ! in_array( $position, array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ), true ) ) {
			$position = 'bottom_right';
		}
		$opacity = '' !== trim( (string) ( $input['watermark_opacity'] ?? '' ) )
			? absint( $input['watermark_opacity'] )
			: 80;

		return array(
			'watermark_enabled' => true,
			'watermark'         => array(
				'type'          => 'image',
				'position'      => $position,
				'opacity'       => round( max( 0, min( 100, $opacity ) ) / 100, 3 ),
				'scale_percent' => max( 1, min( 100, absint( $input['watermark_scale'] ?? 20 ) ) ),
				'margin_px'     => max( 0, min( 1000, absint( $input['watermark_margin'] ?? 24 ) ) ),
			),
		);
	}

	private function execute_site_knowledge_cloud_request( string $ability_name, string $contract_version, string $execution_pattern, array $input, string $artifact_type, string $composition_role ) {
		$runtime_payload = array(
			'ability_name'        => $ability_name,
			'contract_version'    => $contract_version,
			'execution_pattern'   => $execution_pattern,
			'input'               => $this->sanitize_payload( $input ),
			'data_classification' => 'public_site_content',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 86400,
			'timeout_seconds'     => 'whole_run_offload' === $execution_pattern ? 60 : 20,
			'retry_max'           => 'whole_run_offload' === $execution_pattern ? 1 : 0,
			'policy'              => array(
				'allow_fallback' => true,
			),
		);

		$runtime_payload = apply_filters( 'magick_ai_toolbox_site_knowledge_runtime_payload', $runtime_payload, $ability_name, $contract_version );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'magick_ai_toolbox_invalid_site_knowledge_runtime_payload',
				__( 'The site knowledge runtime payload was not valid.', 'magick-ai-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$handled = apply_filters( 'magick_ai_toolbox_site_knowledge_cloud_request', null, $runtime_payload, $ability_name, $contract_version );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_site_knowledge_cloud_response( $handled, $artifact_type, $composition_role, $runtime_payload );
		}

		$client = function_exists( 'magick_ai_cloud_addon_runtime_client' ) ? magick_ai_cloud_addon_runtime_client() : null;
		if ( ! is_object( $client ) || ! method_exists( $client, 'execute_runtime' ) ) {
			return new WP_Error(
				'magick_ai_toolbox_site_knowledge_cloud_unavailable',
				__( 'Connect Magick AI Cloud before using site knowledge abilities.', 'magick-ai-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$trace_id        = $this->trace_id( 'site_knowledge' );
		$idempotency_key = $this->trace_id( str_replace( '.', '_', $contract_version ) );
		$response        = $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key );
		if ( is_wp_error( $response ) ) {
			if ( $this->is_cloud_concurrency_error( $response ) ) {
				return $this->site_knowledge_active_run_response( $artifact_type, $composition_role, $runtime_payload );
			}
			return $response;
		}

		return $this->normalize_site_knowledge_cloud_response( is_array( $response ) ? $response : array(), $artifact_type, $composition_role, $runtime_payload );
	}

	private function execute_image_source_cloud_request( string $query, array $options, string $provider ) {
		$per_page = max( 1, min( 30, (int) ( $options['per_page'] ?? 8 ) ) );
		$input    = array(
			'query'              => $query,
			'provider'           => $provider,
			'provider_origin'    => 'cloud',
			'per_page'           => $per_page,
			'orientation'        => sanitize_key( (string) ( $options['orientation'] ?? '' ) ),
			'color'              => sanitize_key( (string) ( $options['color'] ?? '' ) ),
			'purpose'            => sanitize_key( (string) ( $options['purpose'] ?? 'image_reference_candidate' ) ),
			'candidate_contract' => 'image_candidate.v1',
		);
		$runtime_payload = array(
			'ability_name'        => 'magick-ai-toolbox/search-image-source',
			'contract_version'    => 'image_source_cloud_request.v1',
			'execution_pattern'   => 'step_offload',
			'input'               => $this->sanitize_payload( $input ),
			'data_classification' => 'public_reference_media',
			'storage_mode'        => 'result_only',
			'retention_ttl'       => 3600,
			'timeout_seconds'     => 20,
			'retry_max'           => 0,
			'policy'              => array(
				'allow_fallback' => true,
			),
		);

		$runtime_payload = apply_filters( 'magick_ai_toolbox_image_source_runtime_payload', $runtime_payload, $query, $options );
		if ( ! is_array( $runtime_payload ) ) {
			return new WP_Error(
				'magick_ai_toolbox_invalid_image_source_runtime_payload',
				__( 'The image-source runtime payload was not valid.', 'magick-ai-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$handled = apply_filters( 'magick_ai_toolbox_image_source_cloud_request', null, $runtime_payload, $query, $options );
		if ( is_wp_error( $handled ) ) {
			return $handled;
		}
		if ( is_array( $handled ) ) {
			return $this->normalize_image_source_candidates_response( $handled, $query, $provider, $runtime_payload );
		}

		$client = function_exists( 'magick_ai_cloud_addon_runtime_client' ) ? magick_ai_cloud_addon_runtime_client() : null;
		if ( ! is_object( $client ) || ! method_exists( $client, 'execute_runtime' ) ) {
			return new WP_Error(
				'magick_ai_toolbox_image_source_cloud_unavailable',
				__( 'Connect Magick AI Cloud before searching managed image-source candidates. You can still use Adopt New Image with a reviewed image URL.', 'magick-ai-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$trace_id        = $this->trace_id( 'image_source' );
		$idempotency_key = $this->trace_id( 'image_source_cloud_request' );
		$response        = $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_image_source_candidates_response( is_array( $response ) ? $response : array(), $query, $provider, $runtime_payload );
	}

	private function normalize_cloud_web_search_response( array $response, array $runtime_payload ): array {
		$result = $this->extract_cloud_runtime_result( $response );
		$input  = is_array( $runtime_payload['input'] ?? null ) ? $runtime_payload['input'] : array();

		$results = array();
		foreach ( array_slice( is_array( $result['results'] ?? null ) ? $result['results'] : array(), 0, max( 1, min( 10, (int) ( $input['max_results'] ?? 3 ) ) ) ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$results[] = array(
				'title'                  => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'url'                    => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'snippet'                => sanitize_textarea_field( (string) ( $item['snippet'] ?? $item['content'] ?? '' ) ),
				'score'                  => is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null,
				'source'                 => sanitize_key( (string) ( $item['source'] ?? $result['provider'] ?? '' ) ),
				'write_posture'          => sanitize_key( (string) ( $item['write_posture'] ?? 'suggestion_only' ) ),
				'direct_wordpress_write' => false,
			);
		}

		$payload = $this->with_output_contract(
			array(
				'provider'             => sanitize_key( (string) ( $result['provider'] ?? $input['provider'] ?? 'cloud_web_search' ) ),
				'provider_mode'        => sanitize_key( (string) ( $input['provider'] ?? 'auto' ) ),
				'contract_version'     => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'web_search.v1' ) ),
				'cloud_ability'        => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'magick-ai-cloud/web-search' ) ),
				'cloud_runtime'        => 'magick_ai_cloud_addon',
				'status'               => sanitize_key( (string) ( $result['status'] ?? ( $response['status'] ?? 'unknown' ) ) ),
				'run_id'               => sanitize_text_field( (string) ( $response['run_id'] ?? ( ( $response['data']['run_id'] ?? null ) ?: ( $result['run_id'] ?? '' ) ) ) ),
				'query'                => sanitize_text_field( (string) ( $input['query'] ?? '' ) ),
				'intent'               => sanitize_key( (string) ( $result['intent'] ?? $input['intent'] ?? '' ) ),
				'max_results'          => max( 1, min( 10, (int) ( $input['max_results'] ?? 3 ) ) ),
				'result_count'         => count( $results ),
				'evidence_gate'        => is_array( $result['evidence_gate'] ?? null ) ? $this->sanitize_payload( $result['evidence_gate'] ) : array(),
				'reader_enhancement'   => is_array( $result['reader_enhancement'] ?? null ) ? $this->sanitize_payload( $result['reader_enhancement'] ) : array(),
				'provider_call_count'  => absint( $response['provider_call_count'] ?? ( $response['data']['provider_call_count'] ?? 0 ) ),
				'results'              => $results,
				'handoff'              => array(
					'cloud_runtime'          => 'magick_ai_cloud_addon',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			'web_search_results',
			'external_web_evidence'
		);

		if ( (bool) $this->settings->get( 'include_raw_responses' ) ) {
			$payload['cloud_response'] = $this->sanitize_payload( $response );
		}

		return $payload;
	}

	private function normalize_image_source_candidates_response( array $response, string $query, string $provider_mode, array $runtime_payload = array() ): array {
		$result = array();
		foreach ( array( 'result', 'output', 'data' ) as $key ) {
			if ( is_array( $response[ $key ] ?? null ) ) {
				$result = $response[ $key ];
				break;
			}
		}
		if ( array() === $result ) {
			$result = $response;
		}

		$images = array();
		foreach ( array( 'images', 'candidates', 'image_candidates' ) as $key ) {
			if ( is_array( $result[ $key ] ?? null ) ) {
				$images = $result[ $key ];
				break;
			}
		}

		$contract_images = array();
		foreach ( array_slice( $this->dedupe_image_candidates( $images ), 0, max( 1, min( 30, (int) ( $runtime_payload['input']['per_page'] ?? 8 ) ) ) ) as $image ) {
			if ( is_array( $image ) ) {
				$image['provider_origin'] = $image['provider_origin'] ?? 'cloud';
				$contract_images[]        = $this->normalize_image_candidate_contract( $image );
			}
		}

		$active_sources = is_array( $result['active_sources'] ?? null ) ? $this->sanitize_payload( $result['active_sources'] ) : array();
		if ( array() === $active_sources && $provider_mode ) {
			$active_sources[] = array(
				'provider' => 'cloud' === $provider_mode || 'auto' === $provider_mode ? 'cloud_image_sources' : $provider_mode,
				'count'    => count( $contract_images ),
			);
		}

		$payload = $this->with_output_contract(
			array(
				'provider'                   => 'magick_ai_cloud',
				'provider_mode'              => $provider_mode,
				'candidate_contract_version' => 'image_candidate.v1',
				'cloud_ability'              => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? 'magick-ai-toolbox/search-image-source' ) ),
				'cloud_runtime'              => 'magick_ai_cloud_addon',
				'active_sources'             => $active_sources,
				'provider_errors'            => is_array( $result['provider_errors'] ?? null ) ? $this->sanitize_payload( $result['provider_errors'] ) : array(),
				'query'                      => $query,
				'images'                     => $contract_images,
				'handoff'                    => array(
					'candidate_contract'    => 'image_candidate.v1',
					'final_writes'          => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			'image_source_candidates',
			'image_source_candidates'
		);

		return $this->with_optional_raw( $payload, is_array( $response['raw'] ?? null ) ? $response['raw'] : $response );
	}

	private function normalize_site_knowledge_cloud_response( array $response, string $artifact_type, string $composition_role, array $runtime_payload ): array {
		$result = $this->extract_cloud_runtime_result( $response );

		$results = is_array( $result['results'] ?? null ) ? $this->sanitize_payload( $result['results'] ) : array();
		$results = $this->filter_current_public_site_knowledge_results( $results );

		$payload = $this->with_output_contract(
			array(
				'provider'          => 'magick_ai_cloud',
				'contract_version'  => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? '' ) ),
				'cloud_ability'     => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? '' ) ),
				'execution_pattern' => sanitize_key( (string) ( $runtime_payload['execution_pattern'] ?? 'inline' ) ),
				'status'            => sanitize_key( (string) ( $result['status'] ?? ( $response['status'] ?? 'unknown' ) ) ),
				'run_id'            => sanitize_text_field( (string) ( $response['run_id'] ?? ( ( $response['data']['run_id'] ?? null ) ?: ( $result['run_id'] ?? '' ) ) ) ),
				'results'           => $results,
				'coverage'          => is_array( $result['coverage'] ?? null ) ? $this->sanitize_payload( $result['coverage'] ) : array(),
				'sync'              => is_array( $result['sync'] ?? null ) ? $this->sanitize_payload( $result['sync'] ) : array(),
				'progress'          => is_array( $result['progress'] ?? null ) ? $this->sanitize_payload( $result['progress'] ) : array(),
				'active_run'        => is_array( $result['active_run'] ?? null ) ? $this->sanitize_payload( $result['active_run'] ) : array(),
				'intent'            => sanitize_key( (string) ( $result['intent'] ?? '' ) ),
				'evidence_gate'     => is_array( $result['evidence_gate'] ?? null ) ? $this->sanitize_payload( $result['evidence_gate'] ) : array(),
				'handoff'           => array(
					'cloud_runtime'          => 'magick_ai_cloud_addon',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			$artifact_type,
			$composition_role
		);

		if ( (bool) $this->settings->get( 'include_raw_responses' ) ) {
			$payload['cloud_response'] = $this->sanitize_payload( $response );
		}

		return $payload;
	}

	private function filter_current_public_site_knowledge_results( array $results ): array {
		if ( ! function_exists( 'get_post_status' ) || ! function_exists( 'get_post_type' ) ) {
			return $results;
		}

		return array_values(
			array_filter(
				$results,
				function ( $result ): bool {
					if ( ! is_array( $result ) ) {
						return false;
					}

					$source_type = sanitize_key( (string) ( $result['source_type'] ?? '' ) );
					$post_id     = absint( $result['post_id'] ?? 0 );
					if ( 0 >= $post_id ) {
						return false;
					}

					if ( 'comment' === $source_type ) {
						if ( ! function_exists( 'get_comment' ) ) {
							return false;
						}
						$comment = get_comment( absint( $result['source_id'] ?? 0 ) );
						if ( ! $comment || 'approve' !== (string) $comment->comment_approved ) {
							return false;
						}
					}

					return 'publish' === get_post_status( $post_id )
						&& in_array( get_post_type( $post_id ), array( 'post', 'page' ), true );
				}
			)
		);
	}

	private function extract_cloud_runtime_result( array $response ): array {
		foreach ( array( 'result', 'output' ) as $key ) {
			if ( is_array( $response[ $key ] ?? null ) ) {
				return $response[ $key ];
			}
		}

		$data = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		foreach ( array( 'result', 'output', 'result_json' ) as $key ) {
			if ( is_array( $data[ $key ] ?? null ) ) {
				return $data[ $key ];
			}
		}

		if ( is_array( $data['run']['result'] ?? null ) ) {
			return $data['run']['result'];
		}

		if ( array() !== $data && ( isset( $data['artifact_type'] ) || isset( $data['results'] ) || isset( $data['coverage'] ) || isset( $data['sync'] ) ) ) {
			return $data;
		}

		return $response;
	}

	private function is_cloud_concurrency_error( WP_Error $error ): bool {
		$code    = (string) $error->get_error_code();
		$message = (string) $error->get_error_message();
		return false !== strpos( $code, 'concurrency' ) || false !== strpos( $message, 'max active cloud runs' );
	}

	private function site_knowledge_active_run_response( string $artifact_type, string $composition_role, array $runtime_payload ): array {
		return $this->with_output_contract(
			array(
				'provider'          => 'magick_ai_cloud',
				'contract_version'  => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? '' ) ),
				'cloud_ability'     => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? '' ) ),
				'execution_pattern' => sanitize_key( (string) ( $runtime_payload['execution_pattern'] ?? 'inline' ) ),
				'status'            => 'syncing',
				'results'           => array(),
				'coverage'          => array(),
				'sync'              => array(
					'sync_mode'          => sanitize_key( (string) ( $runtime_payload['input']['sync_mode'] ?? 'refresh' ) ),
					'accepted_documents' => 0,
					'indexed_documents'  => 0,
					'indexed_chunks'     => 0,
					'failed_documents'   => 0,
				),
				'progress'          => array(
					'status'              => 'running',
					'stage'               => 'queued',
					'message'             => __( 'Cloud indexing is already running for this site.', 'magick-ai-toolbox' ),
					'processed_documents' => 0,
					'total_documents'     => 0,
					'indexed_chunks'      => 0,
					'failed_documents'    => 0,
					'percent'             => 0,
				),
				'message'           => __( 'A Cloud run is already active for this site. Refresh status before starting another sync.', 'magick-ai-toolbox' ),
				'handoff'           => array(
					'cloud_runtime'          => 'magick_ai_cloud_addon',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			$artifact_type,
			$composition_role
		);
	}

	private function sanitize_absint_list( $value ): array {
		$items = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
		return array_values(
			array_filter(
				array_map( 'absint', $items ),
				static fn( int $item ): bool => 0 < $item
			)
		);
	}

	private function collect_site_knowledge_documents( array $post_ids, int $max_posts ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 50, $max_posts ) ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( array() !== $post_ids ) {
			$args['post__in'] = $post_ids;
			$args['orderby']  = 'post__in';
		}

		$posts = get_posts( $args );
		if ( ! is_array( $posts ) ) {
			return array();
		}

		$documents = array();
		$indexed_post_ids = array();
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$post_id = absint( $post->ID ?? 0 );
			if ( 0 >= $post_id ) {
				continue;
			}

			$indexed_post_ids[] = $post_id;
			$content = wp_strip_all_tags( (string) ( $post->post_content ?? '' ) );
			$excerpt = function_exists( 'get_the_excerpt' ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '';
			$documents[] = array(
				'post_id'         => $post_id,
				'post_type'       => function_exists( 'get_post_type' ) ? sanitize_key( (string) get_post_type( $post ) ) : '',
				'post_status'     => function_exists( 'get_post_status' ) ? sanitize_key( (string) get_post_status( $post ) ) : 'publish',
				'title'           => function_exists( 'get_the_title' ) ? sanitize_text_field( (string) get_the_title( $post ) ) : '',
				'url'             => function_exists( 'get_permalink' ) ? esc_url_raw( (string) get_permalink( $post ) ) : '',
				'modified_gmt'    => sanitize_text_field( (string) ( $post->post_modified_gmt ?? '' ) ),
				'excerpt'         => sanitize_textarea_field( (string) $excerpt ),
				'content_excerpt' => wp_trim_words( $content, 600, '' ),
				'content_hash'    => md5( $content ),
			);
		}

		if ( array() !== $indexed_post_ids ) {
			$documents = array_merge(
				$documents,
				$this->collect_site_knowledge_comments(
					array_values( array_unique( $indexed_post_ids ) ),
					max( 1, min( 100, max( 1, $max_posts ) * 3 ) )
				)
			);
		}

		return $documents;
	}

	private function collect_site_knowledge_comments( array $post_ids, int $max_comments ): array {
		if ( array() === $post_ids || ! function_exists( 'get_comments' ) ) {
			return array();
		}

		$comments = get_comments(
			array(
				'post__in' => array_values( array_unique( array_map( 'absint', $post_ids ) ) ),
				'status'   => 'approve',
				'type'     => 'comment',
				'number'   => max( 1, min( 100, $max_comments ) ),
				'orderby'  => 'comment_date_gmt',
				'order'    => 'DESC',
			)
		);
		if ( ! is_array( $comments ) ) {
			return array();
		}

		$documents = array();
		foreach ( $comments as $comment ) {
			if ( ! is_object( $comment ) ) {
				continue;
			}

			$comment_id = absint( $comment->comment_ID ?? 0 );
			$post_id    = absint( $comment->comment_post_ID ?? 0 );
			if ( 0 >= $comment_id || 0 >= $post_id || ! in_array( $post_id, $post_ids, true ) ) {
				continue;
			}

			$content = wp_strip_all_tags( (string) ( $comment->comment_content ?? '' ) );
			if ( '' === trim( $content ) ) {
				continue;
			}

			$documents[] = array(
				'comment_id'      => $comment_id,
				'post_id'         => $post_id,
				'comment_status'  => 'approve',
				'created_gmt'     => sanitize_text_field( (string) ( $comment->comment_date_gmt ?? '' ) ),
				'url'             => function_exists( 'get_comment_link' ) ? esc_url_raw( (string) get_comment_link( $comment ) ) : '',
				'content_excerpt' => wp_trim_words( $content, 280, '' ),
				'content_hash'    => md5( $content ),
			);
		}

		return $documents;
	}

	private function trace_id( string $prefix ): string {
		$prefix = sanitize_key( $prefix );
		$uuid   = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true );

		return $prefix . '_' . $uuid;
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
				array(
					'status'          => 502,
					'provider_status' => $status,
				)
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

	private function text_request( string $url, string $method, array $headers = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => array_merge(
				array(
					'Accept' => 'text/plain',
				),
				$headers
			),
		);

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		if ( 200 > $status || 299 < $status ) {
			return new WP_Error(
				'magick_ai_toolbox_provider_error',
				__( 'The provider text request failed.', 'magick-ai-toolbox' ),
				array(
					'status'          => $status,
					'provider_status' => $status,
				)
			);
		}

		return $raw;
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

	private function article_assistant_evidence_pack( $research, $knowledge, array $reference_urls ): array {
		$sources = array();
		foreach ( $reference_urls as $url ) {
			$sources[] = array(
				'source_type'         => 'operator_reference',
				'title'               => $url,
				'url'                 => esc_url_raw( $url ),
				'summary'             => '',
				'verification_status' => 'operator_supplied_candidate',
			);
		}

		if ( is_array( $research ) ) {
			foreach ( array_slice( is_array( $research['results'] ?? null ) ? $research['results'] : array(), 0, 8 ) as $item ) {
				$item = is_array( $item ) ? $item : array();
				$sources[] = array(
					'source_type'         => 'cloud_web_search',
					'title'               => sanitize_text_field( (string) ( $item['title'] ?? $item['url'] ?? '' ) ),
					'url'                 => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
					'summary'             => sanitize_textarea_field( (string) ( $item['content'] ?? ( $item['snippet'] ?? '' ) ) ),
					'verification_status' => 'source_candidate',
				);
			}
		}

		$knowledge_points = array();
		if ( is_array( $knowledge ) && is_array( $knowledge['points'] ?? null ) ) {
			$knowledge_points = $this->sanitize_payload( array_slice( $knowledge['points'], 0, 4 ) );
		}

		return array(
			'sources'              => array_values( array_filter( $sources, static fn( array $source ): bool => '' !== (string) ( $source['title'] ?? '' ) || '' !== (string) ( $source['url'] ?? '' ) ) ),
			'research_status'      => is_wp_error( $research ) ? array(
				'error' => $research->get_error_message(),
			) : array(
				'provider'        => is_array( $research ) ? sanitize_key( (string) ( $research['provider'] ?? 'cloud_web_search' ) ) : 'cloud_web_search',
				'provider_mode'   => is_array( $research ) ? sanitize_key( (string) ( $research['provider_mode'] ?? '' ) ) : '',
				'status'          => is_array( $research ) ? sanitize_key( (string) ( $research['status'] ?? '' ) ) : '',
				'result_count'    => is_array( $research ) ? absint( $research['result_count'] ?? 0 ) : 0,
				'active_sources'  => is_array( $research ) ? $this->sanitize_payload( $research['active_sources'] ?? array() ) : array(),
			),
			'site_knowledge'       => is_wp_error( $knowledge ) ? array(
				'error' => $knowledge->get_error_message(),
			) : array(
				'points' => $knowledge_points,
			),
			'evidence_policy'      => 'Source candidates are planning evidence only. Operators must verify citations and factual claims before Core proposal handoff.',
			'direct_wordpress_write' => false,
		);
	}

	private function extract_workflow_web_search_report( array $artifact, string $scenario ): array {
		if ( 'article_assistant' === $scenario ) {
			$evidence = is_array( $artifact['research_evidence_pack'] ?? null ) ? $artifact['research_evidence_pack'] : array();
			$status   = is_array( $evidence['research_status'] ?? null ) ? $evidence['research_status'] : array();
			$sources  = array_filter(
				is_array( $evidence['sources'] ?? null ) ? $evidence['sources'] : array(),
				static fn( $source ): bool => is_array( $source ) && 'cloud_web_search' === (string) ( $source['source_type'] ?? '' )
			);

			return array(
				'status'        => sanitize_key( (string) ( $status['status'] ?? '' ) ),
				'provider'      => sanitize_key( (string) ( $status['provider'] ?? 'cloud_web_search' ) ),
				'provider_mode' => sanitize_key( (string) ( $status['provider_mode'] ?? '' ) ),
				'result_count'  => absint( $status['result_count'] ?? count( $sources ) ),
				'source_count'  => count( $sources ),
				'sources'       => $this->sanitize_payload( array_values( $sources ) ),
			);
		}

		$research = is_array( $artifact['external_research'] ?? null ) ? $artifact['external_research'] : array();
		$results  = is_array( $research['results'] ?? null ) ? $research['results'] : array();

		return array(
			'status'        => sanitize_key( (string) ( $research['status'] ?? '' ) ),
			'provider'      => sanitize_key( (string) ( $research['provider'] ?? 'cloud_web_search' ) ),
			'provider_mode' => sanitize_key( (string) ( $research['provider_mode'] ?? '' ) ),
			'result_count'  => absint( $research['result_count'] ?? count( $results ) ),
			'source_count'  => count( $results ),
			'evidence_gate' => is_array( $research['evidence_gate'] ?? null ) ? $this->sanitize_payload( $research['evidence_gate'] ) : array(),
			'sources'       => $this->sanitize_payload( array_slice( $results, 0, 5 ) ),
		);
	}

	private function article_assistant_outline( string $title, string $topic, array $must_include ): array {
		$sections = array(
			array(
				'section'         => 'direct_answer',
				'heading_hint'    => __( 'Direct answer', 'magick-ai-toolbox' ),
				'purpose'         => __( 'State the useful answer or thesis with only supported facts.', 'magick-ai-toolbox' ),
				'evidence_needed' => true,
			),
			array(
				'section'         => 'context',
				'heading_hint'    => __( 'Context', 'magick-ai-toolbox' ),
				'purpose'         => __( 'Explain why the topic matters to the target reader.', 'magick-ai-toolbox' ),
				'evidence_needed' => true,
			),
			array(
				'section'         => 'main_body',
				'heading_hint'    => __( 'Practical breakdown', 'magick-ai-toolbox' ),
				'purpose'         => __( 'Organize steps, examples, comparisons, or tradeoffs that the evidence supports.', 'magick-ai-toolbox' ),
				'evidence_needed' => true,
			),
			array(
				'section'         => 'geo_summary',
				'heading_hint'    => __( 'AI-readable summary', 'magick-ai-toolbox' ),
				'purpose'         => __( 'Summarize the grounded conclusion without ranking or outcome guarantees.', 'magick-ai-toolbox' ),
				'evidence_needed' => true,
			),
			array(
				'section'         => 'conclusion',
				'heading_hint'    => __( 'Next step', 'magick-ai-toolbox' ),
				'purpose'         => __( 'Close with a practical next step for the reader.', 'magick-ai-toolbox' ),
				'evidence_needed' => false,
			),
		);

		return array(
			'title'        => $title,
			'topic'        => $topic,
			'sections'     => $sections,
			'must_include' => $must_include,
		);
	}

	private function article_assistant_draft_candidate( string $reviewed_draft, string $draft_notes, array $outline, array $evidence_pack ): array {
		$has_reviewed_draft = '' !== trim( $reviewed_draft );
		return array(
			'content_markdown'      => $has_reviewed_draft ? $reviewed_draft : '',
			'draft_notes'           => $draft_notes,
			'draft_source'          => $has_reviewed_draft ? 'operator_supplied_reviewed_draft' : 'operator_notes_or_outline_only',
			'ready_for_write_plan'  => $has_reviewed_draft,
			'outline_ref'           => $this->sanitize_payload( $outline ),
			'used_sources'          => array_values(
				array_filter(
					array_map(
						static fn( $source ): string => is_array( $source ) ? (string) ( $source['url'] ?? $source['title'] ?? '' ) : '',
						is_array( $evidence_pack['sources'] ?? null ) ? $evidence_pack['sources'] : array()
					)
				)
			),
			'needs_human_input'     => $has_reviewed_draft ? array() : array(
				'Paste the operator-reviewed article body before creating a Core-ready article_write_plan.',
			),
		);
	}

	private function article_assistant_risk_report( string $reviewed_draft, string $draft_notes, array $context, array $validation, array $evidence_pack, array $must_avoid, string $source_policy ): array {
		$text = $reviewed_draft . "\n" . $draft_notes;
		$blocked_claims = array();
		foreach ( array_merge( $this->sanitize_string_list( $context['claims']['forbidden'] ?? array() ), $must_avoid ) as $claim ) {
			if ( '' !== $claim && false !== stripos( $text, $claim ) ) {
				$blocked_claims[] = $claim;
			}
		}
		$blocked_claims = array_values( array_unique( $blocked_claims ) );

		$needs_review = array();
		if ( '' === trim( $reviewed_draft ) ) {
			$needs_review[] = 'reviewed_draft_required';
		}
		if ( empty( $evidence_pack['sources'] ) && 'operator_notes_only' !== $source_policy ) {
			$needs_review[] = 'source_evidence_required';
		}
		$context_status = sanitize_key( (string) ( $validation['status'] ?? 'needs_attention' ) );
		if ( ! in_array( $context_status, array( 'ready', 'ready_with_warnings' ), true ) ) {
			$needs_review[] = 'content_context_needs_attention';
		}
		if ( array() !== $blocked_claims ) {
			$needs_review[] = 'blocked_claims_present';
		}

		$risk_level = 'low';
		if ( array() !== $blocked_claims ) {
			$risk_level = 'high';
		} elseif ( array() !== $needs_review ) {
			$risk_level = 'medium';
		}

		return array(
			'risk_level'         => $risk_level,
			'blocked_claims'     => $blocked_claims,
			'needs_review'       => array_values( array_unique( $needs_review ) ),
			'source_policy'      => $source_policy,
			'context_status'     => $context_status,
			'ready_for_proposal' => 'low' === $risk_level && '' !== trim( $reviewed_draft ),
			'legal_posture'      => 'local_operator_review_required',
		);
	}

	private function article_writing_pack_structure( array $rules ): array {
		$structure = array(
			array(
				'section' => 'title',
				'purpose' => 'Use a clear article title aligned with the primary keyword and source topic.',
			),
			array(
				'section' => 'direct_answer',
				'purpose' => 'Open with a concise answer or definition that an answer engine can extract.',
			),
			array(
				'section' => 'context',
				'purpose' => 'Explain why the topic matters to the target audience using only supported facts.',
			),
			array(
				'section' => 'main_body',
				'purpose' => 'Use practical headings, steps, examples, comparisons, or checklists where the source supports them.',
			),
			array(
				'section' => 'geo_summary',
				'purpose' => 'Include a fact-dense summary suitable for generated search citation.',
			),
			array(
				'section' => 'conclusion',
				'purpose' => 'Close with a practical next step without claiming guaranteed ranking or outcomes.',
			),
		);

		if ( ! empty( $rules['allow_faq_generation'] ) ) {
			$structure[] = array(
				'section' => 'faq',
				'purpose' => 'Add 3 to 5 grounded FAQ items only when the brief allows FAQ suggestions.',
			);
		}

		return $structure;
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

	private function resolve_article_media_candidate( array $article, string $title, string $topic, bool $search_images, string $image_provider ) {
		$candidate = array();
		foreach ( array( 'image_candidate', 'featured_image', 'featured_image_candidate' ) as $key ) {
			if ( is_array( $article[ $key ] ?? null ) ) {
				$candidate = $article[ $key ];
				break;
			}
		}

		if ( empty( $candidate ) && ! empty( $article['image_url'] ) ) {
			$candidate = array(
				'url'             => esc_url_raw( (string) $article['image_url'] ),
				'regular_url'     => esc_url_raw( (string) $article['image_url'] ),
				'description'     => sanitize_textarea_field( (string) ( $article['image_alt'] ?? $title ) ),
				'alt_description' => sanitize_textarea_field( (string) ( $article['image_alt'] ?? $title ) ),
				'provider'        => sanitize_key( (string) ( $article['image_provider'] ?? 'external' ) ),
				'source_url'      => esc_url_raw( (string) ( $article['image_source_url'] ?? '' ) ),
				'photographer'    => sanitize_text_field( (string) ( $article['photographer_name'] ?? '' ) ),
				'attribution'     => sanitize_textarea_field( (string) ( $article['attribution_text'] ?? '' ) ),
			);
		}

		if ( empty( $candidate ) && $search_images ) {
			$query  = trim( sanitize_text_field( (string) ( $article['image_query'] ?? $title . ' ' . $topic ) ) );
			$result = $this->image_candidates(
				$query,
				array(
					'provider' => $image_provider,
					'per_page' => 1,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$images = is_array( $result['images'] ?? null ) ? array_values( $result['images'] ) : array();
			if ( empty( $images ) || ! is_array( $images[0] ?? null ) ) {
				return new WP_Error(
					'magick_ai_toolbox_article_media_candidate_missing',
					__( 'Image-source search did not return a usable candidate for an article media batch item.', 'magick-ai-toolbox' ),
					array( 'status' => 502 )
				);
			}
			$candidate = $images[0];
		}

		if ( empty( $candidate ) ) {
			return new WP_Error(
				'magick_ai_toolbox_article_media_candidate_required',
				__( 'Every article media batch item requires image_candidate, featured_image, image_url, or search_images=true.', 'magick-ai-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return $this->sanitize_payload( $candidate );
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

	private function content_discoverability_field_group( string $field ): string {
		if ( in_array( $field, array( 'faq', 'answer_summary' ), true ) ) {
			return 'aeo';
		}

		if ( in_array( $field, array( 'geo_summary', 'structured_data_hints' ), true ) ) {
			return 'geo';
		}

		return 'seo';
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
