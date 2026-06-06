<?php
/**
 * Settings storage and sanitization.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public function register(): void {
		register_setting(
			'npcink_toolbox',
			Plugin::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);

		register_setting(
			'npcink_toolbox_content_context',
			Plugin::CONTEXT_OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_content_context' ),
				'default'           => $this->content_context_defaults(),
			)
		);
	}

	public function defaults(): array {
		return array(
			'include_raw_responses' => false,
			'enable_image_source'   => true,
		);
	}

	public function get_all(): array {
		$value = get_option( Plugin::OPTION_NAME, array() );
		$defaults = $this->defaults();
		$value    = is_array( $value ) ? array_intersect_key( $value, $defaults ) : array();
		return array_merge( $defaults, $value );
	}

	public function content_context_defaults(): array {
		return array(
			'site_positioning'                 => '',
			'target_audience'                  => array(),
			'brand_voice'                      => '',
			'primary_keywords'                 => array(),
			'long_tail_keywords'               => array(),
			'entity_keywords'                  => array(),
			'allowed_claims'                   => array(),
			'forbidden_claims'                 => array(),
			'disallowed_topics'                => array(),
			'cautious_topics'                  => array(),
			'no_structured_output_topics'      => array(),
			'human_confirmation_required'      => array(),
			'seo_rules'                        => '',
			'aeo_rules'                        => '',
			'geo_rules'                        => '',
			'allow_faq_generation'             => true,
			'allow_aeo_summary'                => true,
			'allow_geo_summary'                => true,
			'allow_structured_data_suggestions' => true,
			'proposal_allowed_fields'          => array(
				'seo_title',
				'seo_description',
				'slug',
				'excerpt',
				'faq',
				'answer_summary',
				'geo_summary',
			),
		);
	}

	public function get_content_context(): array {
		$value = get_option( Plugin::CONTEXT_OPTION_NAME, array() );
		return array_merge( $this->content_context_defaults(), is_array( $value ) ? $value : array() );
	}

	public function get_content_context_for_ability(): array {
		$context = $this->get_content_context();

		return array(
			'context_type'                    => 'content_discoverability',
			'composition_role'                => 'site_context',
			'version'                         => 1,
			'write_posture'                   => 'suggestion_only',
			'final_write_path'                => 'core_proposal_required',
			'direct_wordpress_write'          => false,
			'site_positioning'                => $context['site_positioning'],
			'target_audience'                 => $context['target_audience'],
			'brand_voice'                     => $context['brand_voice'],
			'keywords'                        => array(
				'primary'   => $context['primary_keywords'],
				'long_tail' => $context['long_tail_keywords'],
				'entities'  => $context['entity_keywords'],
			),
			'claims'                          => array(
				'allowed'   => $context['allowed_claims'],
				'forbidden' => $context['forbidden_claims'],
			),
			'exceptions'                      => array(
				'disallowed_topics'           => $context['disallowed_topics'],
				'cautious_topics'             => $context['cautious_topics'],
				'no_structured_output_topics' => $context['no_structured_output_topics'],
				'human_confirmation_required' => $context['human_confirmation_required'],
			),
			'rules'                           => array(
				'seo'                               => $context['seo_rules'],
				'aeo'                               => $context['aeo_rules'],
				'geo'                               => $context['geo_rules'],
				'allow_faq_generation'              => (bool) $context['allow_faq_generation'],
				'allow_aeo_summary'                 => (bool) $context['allow_aeo_summary'],
				'allow_geo_summary'                 => (bool) $context['allow_geo_summary'],
				'allow_structured_data_suggestions' => (bool) $context['allow_structured_data_suggestions'],
			),
			'proposal_allowed_fields'         => $context['proposal_allowed_fields'],
			'handoff'                         => array(
				'consumer'               => 'abilities_or_agent_gateway',
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}

	public function validate_content_context_for_ability(): array {
		$context = $this->get_content_context_for_ability();
		$checks  = array();

		$this->append_context_check( $checks, 'site_positioning', __( 'Site positioning is filled.', 'npcink-toolbox' ), $context['site_positioning'], 'required' );
		$this->append_context_check( $checks, 'target_audience', __( 'Target audience is filled.', 'npcink-toolbox' ), $context['target_audience'], 'required' );
		$this->append_context_check( $checks, 'brand_voice', __( 'Brand voice is filled.', 'npcink-toolbox' ), $context['brand_voice'], 'required' );
		$this->append_context_check( $checks, 'keywords.primary', __( 'Primary keywords are filled.', 'npcink-toolbox' ), $context['keywords']['primary'], 'required' );
		$this->append_context_check( $checks, 'rules.seo', __( 'SEO rules are filled.', 'npcink-toolbox' ), $context['rules']['seo'], 'required' );
		$this->append_context_check( $checks, 'rules.aeo', __( 'AEO rules are filled.', 'npcink-toolbox' ), $context['rules']['aeo'], 'required' );
		$this->append_context_check( $checks, 'rules.geo', __( 'GEO rules are filled.', 'npcink-toolbox' ), $context['rules']['geo'], 'required' );
		$this->append_context_check( $checks, 'proposal_allowed_fields', __( 'Proposal suggestion fields are selected.', 'npcink-toolbox' ), $context['proposal_allowed_fields'], 'required' );

		$this->append_context_check( $checks, 'keywords.long_tail', __( 'Long-tail keywords are filled.', 'npcink-toolbox' ), $context['keywords']['long_tail'], 'recommended' );
		$this->append_context_check( $checks, 'keywords.entities', __( 'Entity keywords are filled.', 'npcink-toolbox' ), $context['keywords']['entities'], 'recommended' );
		$this->append_context_check( $checks, 'claims.allowed', __( 'Allowed claims are filled.', 'npcink-toolbox' ), $context['claims']['allowed'], 'recommended' );
		$this->append_context_check( $checks, 'claims.forbidden', __( 'Forbidden claims are filled.', 'npcink-toolbox' ), $context['claims']['forbidden'], 'recommended' );
		$this->append_context_check( $checks, 'exceptions', __( 'Exception and special-case rules are filled when needed.', 'npcink-toolbox' ), $context['exceptions'], 'recommended' );

		$missing_required    = array_values( array_filter( $checks, static fn( array $check ): bool => 'required' === $check['severity'] && ! $check['passed'] ) );
		$missing_recommended = array_values( array_filter( $checks, static fn( array $check ): bool => 'recommended' === $check['severity'] && ! $check['passed'] ) );
		$passed_count        = count( array_filter( $checks, static fn( array $check ): bool => (bool) $check['passed'] ) );
		$total_count         = max( 1, count( $checks ) );
		$status              = empty( $missing_required ) ? ( empty( $missing_recommended ) ? 'ready' : 'ready_with_warnings' ) : 'needs_attention';

		return array(
			'artifact_type'          => 'content_discoverability_context_validation',
			'composition_role'       => 'context_preflight',
			'version'                => 1,
			'status'                 => $status,
			'score'                  => round( $passed_count / $total_count, 2 ),
			'checks'                 => $checks,
			'missing_required'       => $missing_required,
			'missing_recommended'    => $missing_recommended,
			'context_summary'        => array(
				'has_site_positioning' => '' !== trim( (string) $context['site_positioning'] ),
				'target_audience_count' => count( (array) $context['target_audience'] ),
				'primary_keyword_count' => count( (array) $context['keywords']['primary'] ),
				'proposal_field_count'  => count( (array) $context['proposal_allowed_fields'] ),
			),
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
		);
	}

	public function get( string $key ) {
		$settings = $this->get_all();
		return $settings[ $key ] ?? null;
	}

	public function configured_image_source_providers(): array {
		return $this->cloud_runtime_available()
			? array( 'cloud_image_sources' )
			: array();
	}

	public function has_image_source_provider(): bool {
		return array() !== $this->configured_image_source_providers();
	}

	public function cloud_runtime_available(): bool {
		if ( $this->has_cloud_request_filter() ) {
			return true;
		}

		$client = $this->cloud_runtime_client();
		return is_object( $client ) && method_exists( $client, 'execute_runtime' );
	}

	public function cloud_runtime_unavailable_reason(): string {
		if ( $this->cloud_runtime_available() ) {
			return '';
		}

		if ( ! function_exists( 'npcink_cloud_addon_runtime_client' ) && ! function_exists( 'magick_ai_cloud_addon_runtime_client' ) ) {
			return 'cloud_addon_not_installed';
		}

		if ( function_exists( 'npcink_cloud_addon_is_configured' ) && ! npcink_cloud_addon_is_configured() ) {
			return 'cloud_addon_not_connected';
		}

		if ( function_exists( 'magick_ai_cloud_addon_is_configured' ) && ! magick_ai_cloud_addon_is_configured() ) {
			return 'cloud_addon_not_connected';
		}

		return 'cloud_runtime_unavailable';
	}

	private function cloud_runtime_client() {
		if ( function_exists( 'npcink_cloud_addon_runtime_client' ) ) {
			return npcink_cloud_addon_runtime_client();
		}

		if ( function_exists( 'magick_ai_cloud_addon_runtime_client' ) ) {
			return magick_ai_cloud_addon_runtime_client();
		}

		return null;
	}

	public function cloud_runtime_status(): array {
		$available = $this->cloud_runtime_available();

		return array(
			'registered'           => true,
			'cloud_required'       => true,
			'available'            => $available,
			'unavailable_reason'   => $available ? '' : $this->cloud_runtime_unavailable_reason(),
			'connection_owner'     => 'cloud_addon',
			'provider_detail_owner' => 'cloud_service',
		);
	}

	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$sanitized = array(
			'include_raw_responses' => ! empty( $input['include_raw_responses'] ),
			'enable_image_source'   => ! empty( $input['enable_image_source'] ),
		);

		return $sanitized;
	}

	private function has_cloud_request_filter(): bool {
		foreach ( array(
			'npcink_toolbox_web_search_cloud_request',
			'npcink_toolbox_free_gpt55_cloud_request',
			'npcink_toolbox_site_knowledge_cloud_request',
			'npcink_toolbox_image_source_cloud_request',
		) as $filter ) {
			if ( has_filter( $filter ) ) {
				return true;
			}
		}

		return false;
	}

	public function sanitize_content_context( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$allowed_proposal_fields = array(
			'seo_title',
			'seo_description',
			'slug',
			'excerpt',
			'faq',
			'answer_summary',
			'geo_summary',
			'structured_data_hints',
		);
		$proposal_fields = isset( $input['proposal_allowed_fields'] ) && is_array( $input['proposal_allowed_fields'] )
			? array_values( array_intersect( $allowed_proposal_fields, array_map( 'sanitize_key', $input['proposal_allowed_fields'] ) ) )
			: array();

		return array(
			'site_positioning'                 => sanitize_textarea_field( (string) ( $input['site_positioning'] ?? '' ) ),
			'target_audience'                  => $this->sanitize_context_list( $input['target_audience'] ?? array() ),
			'brand_voice'                      => sanitize_textarea_field( (string) ( $input['brand_voice'] ?? '' ) ),
			'primary_keywords'                 => $this->sanitize_context_list( $input['primary_keywords'] ?? array() ),
			'long_tail_keywords'               => $this->sanitize_context_list( $input['long_tail_keywords'] ?? array() ),
			'entity_keywords'                  => $this->sanitize_context_list( $input['entity_keywords'] ?? array() ),
			'allowed_claims'                   => $this->sanitize_context_list( $input['allowed_claims'] ?? array() ),
			'forbidden_claims'                 => $this->sanitize_context_list( $input['forbidden_claims'] ?? array() ),
			'disallowed_topics'                => $this->sanitize_context_list( $input['disallowed_topics'] ?? array() ),
			'cautious_topics'                  => $this->sanitize_context_list( $input['cautious_topics'] ?? array() ),
			'no_structured_output_topics'      => $this->sanitize_context_list( $input['no_structured_output_topics'] ?? array() ),
			'human_confirmation_required'      => $this->sanitize_context_list( $input['human_confirmation_required'] ?? array() ),
			'seo_rules'                        => sanitize_textarea_field( (string) ( $input['seo_rules'] ?? '' ) ),
			'aeo_rules'                        => sanitize_textarea_field( (string) ( $input['aeo_rules'] ?? '' ) ),
			'geo_rules'                        => sanitize_textarea_field( (string) ( $input['geo_rules'] ?? '' ) ),
			'allow_faq_generation'             => ! empty( $input['allow_faq_generation'] ),
			'allow_aeo_summary'                => ! empty( $input['allow_aeo_summary'] ),
			'allow_geo_summary'                => ! empty( $input['allow_geo_summary'] ),
			'allow_structured_data_suggestions' => ! empty( $input['allow_structured_data_suggestions'] ),
			'proposal_allowed_fields'          => $proposal_fields,
		);
	}

	private function get_secret( string $option_key, string $constant_name, string $env_name ): string {
		if ( defined( $constant_name ) && '' !== (string) constant( $constant_name ) ) {
			return (string) constant( $constant_name );
		}

		$env_key = getenv( $env_name );
		if ( is_string( $env_key ) && '' !== $env_key ) {
			return $env_key;
		}

		return (string) $this->get( $option_key );
	}

	private function sanitize_secret_input( array $input, array $current, string $key, bool $clear ): string {
		if ( $clear ) {
			return '';
		}

		$new_key = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
		if ( '' !== $new_key ) {
			return sanitize_text_field( $new_key );
		}

		return (string) ( $current[ $key ] ?? '' );
	}

	private function sanitize_context_list( $value ): array {
		if ( is_array( $value ) ) {
			$items = $value;
		} else {
			$items = preg_split( '/[\r\n,]+/', (string) $value );
		}

		$items = array_map(
			static fn( $item ): string => sanitize_text_field( trim( (string) $item ) ),
			is_array( $items ) ? $items : array()
		);

		return array_values( array_filter( array_unique( $items ) ) );
	}

	private function append_context_check( array &$checks, string $field, string $message, $value, string $severity ): void {
		$checks[] = array(
			'field'    => $field,
			'severity' => $severity,
			'passed'   => $this->context_value_present( $value ),
			'message'  => $message,
		);
	}

	private function context_value_present( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( $this->context_value_present( $item ) ) {
					return true;
				}
			}

			return false;
		}

		return '' !== trim( (string) $value );
	}
}
