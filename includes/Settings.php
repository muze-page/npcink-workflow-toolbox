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

		register_setting(
			'npcink_toolbox_media_optimization',
			Plugin::MEDIA_OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_media_optimization' ),
				'default'           => $this->media_optimization_defaults(),
			)
		);

		$this->maybe_migrate_core_media_policy();
	}

	public function defaults(): array {
		return array(
			'include_raw_responses'             => false,
			'enable_image_source'               => true,
			'nightly_inspection_enabled'        => false,
			'nightly_inspection_time'           => '03:00',
			'nightly_inspection_post_limit'     => 12,
			'nightly_inspection_media_limit'    => 8,
			'nightly_inspection_pro_enabled'    => false,
			'nightly_inspection_cloud_payload_mode' => 'metadata_only',
			'nightly_inspection_cloud_retention_days' => 7,
		);
	}

	public function get_all(): array {
		$value = get_option( Plugin::OPTION_NAME, array() );
		$defaults = $this->defaults();
		$value    = is_array( $value ) ? array_intersect_key( $value, $defaults ) : array();
		return array_merge( $defaults, $value );
	}

	public function media_optimization_defaults(): array {
		return array(
			'enabled'                  => false,
			'target_format'            => 'webp',
			'max_width'                => 1600,
			'quality'                  => 82,
			'watermark_enabled'        => false,
			'watermark_type'           => 'image',
			'watermark_attachment_id'  => 0,
			'watermark_position'       => 'bottom_right',
			'watermark_opacity'        => 80,
			'watermark_scale'          => 20,
			'watermark_text'           => 'AI',
			'watermark_font_size'      => 48,
			'watermark_color'          => '#FFFFFF',
			'watermark_background'     => 'rgba(0,0,0,0.35)',
			'watermark_margin'         => 24,
			'use_cloud_when_available' => true,
		);
	}

	public function get_media_optimization_settings(): array {
		$value    = get_option( Plugin::MEDIA_OPTION_NAME, array() );
		$defaults = $this->media_optimization_defaults();
		$value    = is_array( $value ) ? array_intersect_key( $value, $defaults ) : array();
		return $this->sanitize_media_optimization( array_merge( $defaults, $value ) );
	}

	public function media_optimization_policy_summary(): array {
		$settings = $this->get_media_optimization_settings();

		return array_merge(
			$settings,
			array(
				'watermark_configured' => $this->media_watermark_configured( $settings ),
				'policy_owner'         => 'npcink_toolbox',
				'final_write_owner'    => 'local_wordpress_host',
			)
		);
	}

	public function build_media_derivative_ability_input( array $overrides = array() ): array {
		if ( isset( $overrides['preferred_format'] ) && ! isset( $overrides['target_format'] ) ) {
			$overrides['target_format'] = $overrides['preferred_format'];
		}
		if ( isset( $overrides['target_max_width'] ) && ! isset( $overrides['max_width'] ) ) {
			$overrides['max_width'] = $overrides['target_max_width'];
		}

		$settings = $this->sanitize_media_optimization( array_merge( $this->get_media_optimization_settings(), $overrides ) );
		$input    = array(
			'preferred_format' => $settings['target_format'],
			'target_max_width' => $settings['max_width'],
			'quality'          => $settings['quality'],
		);

		$attachment_id = absint( $overrides['attachment_id'] ?? 0 );
		if ( $attachment_id > 0 ) {
			$input['attachment_id'] = $attachment_id;
		}

		if ( ! empty( $settings['watermark_enabled'] ) ) {
			if ( 'text' === (string) $settings['watermark_type'] ) {
				$input['watermark'] = array(
					'type'       => 'text',
					'text'       => $settings['watermark_text'],
					'position'   => $settings['watermark_position'],
					'opacity'    => round( (int) $settings['watermark_opacity'] / 100, 3 ),
					'font_size'  => $settings['watermark_font_size'],
					'color'      => $settings['watermark_color'],
					'background' => $settings['watermark_background'],
					'margin_px'  => $settings['watermark_margin'],
				);
			} elseif ( absint( $settings['watermark_attachment_id'] ) > 0 ) {
				$input['watermark'] = array(
					'type'          => 'image',
					'position'      => $settings['watermark_position'],
					'opacity'       => round( (int) $settings['watermark_opacity'] / 100, 3 ),
					'scale_percent' => $settings['watermark_scale'],
					'margin_px'     => $settings['watermark_margin'],
				);
			}
		}

		if (
			( ! array_key_exists( 'watermark_enabled', $overrides ) || ! empty( $overrides['watermark_enabled'] ) )
			&& is_array( $overrides['watermark'] ?? null )
			&& ! empty( $overrides['watermark'] )
		) {
			$input['watermark'] = $this->sanitize_media_derivative_watermark_plan( $overrides['watermark'], $settings );
		}
		if ( is_array( $overrides['crop'] ?? null ) && ! empty( $overrides['crop'] ) ) {
			$input['crop'] = $this->sanitize_media_derivative_crop_plan( $overrides['crop'] );
		}

		return $input;
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

	/**
	 * Returns bounded local fallback preview settings for Nightly Site Inspection.
	 *
	 * @return array<string,bool|int|string>
	 */
	public function get_nightly_inspection_settings(): array {
		$settings = $this->get_all();

		return array(
			'enabled'     => ! empty( $settings['nightly_inspection_enabled'] ),
			'time'        => (string) $settings['nightly_inspection_time'],
			'post_limit'  => (int) $settings['nightly_inspection_post_limit'],
			'media_limit' => (int) $settings['nightly_inspection_media_limit'],
			'pro_enabled' => ! empty( $settings['nightly_inspection_pro_enabled'] ),
			'cloud_payload_mode' => (string) $settings['nightly_inspection_cloud_payload_mode'],
			'cloud_retention_days' => (int) $settings['nightly_inspection_cloud_retention_days'],
			'cloud_retention_ttl' => (int) $settings['nightly_inspection_cloud_retention_days'] * ( defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 ),
		);
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

		if ( ! function_exists( 'npcink_cloud_addon_runtime_client' ) ) {
			return 'cloud_addon_not_installed';
		}

		if ( function_exists( 'npcink_cloud_addon_is_configured' ) && ! npcink_cloud_addon_is_configured() ) {
			return 'cloud_addon_not_connected';
		}

		return 'cloud_runtime_unavailable';
	}

	private function cloud_runtime_client() {
		if ( function_exists( 'npcink_cloud_addon_runtime_client' ) ) {
			return npcink_cloud_addon_runtime_client();
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
			'include_raw_responses'             => ! empty( $input['include_raw_responses'] ),
			'enable_image_source'               => ! empty( $input['enable_image_source'] ),
			'nightly_inspection_enabled'        => ! empty( $input['nightly_inspection_enabled'] ),
			'nightly_inspection_time'           => $this->sanitize_nightly_inspection_time( $input['nightly_inspection_time'] ?? '03:00' ),
			'nightly_inspection_post_limit'     => max( 1, min( 50, absint( $input['nightly_inspection_post_limit'] ?? 12 ) ) ),
			'nightly_inspection_media_limit'    => max( 1, min( 50, absint( $input['nightly_inspection_media_limit'] ?? 8 ) ) ),
			'nightly_inspection_pro_enabled'    => ! empty( $input['nightly_inspection_pro_enabled'] ),
			'nightly_inspection_cloud_payload_mode' => $this->sanitize_nightly_inspection_cloud_payload_mode( $input['nightly_inspection_cloud_payload_mode'] ?? 'metadata_only' ),
			'nightly_inspection_cloud_retention_days' => max( 1, min( 90, absint( $input['nightly_inspection_cloud_retention_days'] ?? 7 ) ) ),
		);

		return $sanitized;
	}

	private function sanitize_nightly_inspection_cloud_payload_mode( $value ): string {
		$mode = sanitize_key( (string) $value );
		return in_array( $mode, array( 'metadata_only', 'excerpt' ), true ) ? $mode : 'metadata_only';
	}

	private function sanitize_nightly_inspection_time( $value ): string {
		$time = trim( sanitize_text_field( (string) $value ) );
		if ( 1 !== preg_match( '/^([0-9]{2}):([0-9]{2})$/', $time, $matches ) ) {
			return '03:00';
		}

		$hour   = (int) $matches[1];
		$minute = (int) $matches[2];
		if ( $hour > 23 || $minute > 59 ) {
			return '03:00';
		}

		return sprintf( '%02d:%02d', $hour, $minute );
	}

	public function sanitize_media_optimization( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$format = sanitize_key( (string) ( $input['target_format'] ?? 'webp' ) );
		if ( ! in_array( $format, $this->allowed_media_derivative_formats(), true ) ) {
			$format = 'webp';
		}

		$watermark_type = sanitize_key( (string) ( $input['watermark_type'] ?? 'image' ) );
		if ( ! in_array( $watermark_type, array( 'image', 'text' ), true ) ) {
			$watermark_type = 'image';
		}

		$position = sanitize_key( (string) ( $input['watermark_position'] ?? 'bottom_right' ) );
		if ( ! in_array( $position, $this->allowed_media_watermark_positions(), true ) ) {
			$position = 'bottom_right';
		}

		$text = trim( sanitize_text_field( (string) ( $input['watermark_text'] ?? 'AI' ) ) );
		if ( '' === $text ) {
			$text = 'AI';
		}
		$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 64 ) : substr( $text, 0, 64 );

		return array(
			'enabled'                  => ! empty( $input['enabled'] ),
			'target_format'            => $format,
			'max_width'                => max( 320, min( 7680, absint( $input['max_width'] ?? 1600 ) ) ),
			'quality'                  => max( 1, min( 100, absint( $input['quality'] ?? 82 ) ) ),
			'watermark_enabled'        => ! empty( $input['watermark_enabled'] ),
			'watermark_type'           => $watermark_type,
			'watermark_attachment_id'  => absint( $input['watermark_attachment_id'] ?? 0 ),
			'watermark_position'       => $position,
			'watermark_opacity'        => max( 0, min( 100, absint( $input['watermark_opacity'] ?? 80 ) ) ),
			'watermark_scale'          => max( 1, min( 100, absint( $input['watermark_scale'] ?? 20 ) ) ),
			'watermark_text'           => $text,
			'watermark_font_size'      => max( 8, min( 256, absint( $input['watermark_font_size'] ?? 48 ) ) ),
			'watermark_color'          => $this->sanitize_media_derivative_watermark_color( $input['watermark_color'] ?? '#FFFFFF', '#FFFFFF' ),
			'watermark_background'     => $this->sanitize_media_derivative_watermark_color( $input['watermark_background'] ?? 'rgba(0,0,0,0.35)', 'rgba(0,0,0,0.35)' ),
			'watermark_margin'         => max( 0, min( 1000, absint( $input['watermark_margin'] ?? 24 ) ) ),
			'use_cloud_when_available' => ! empty( $input['use_cloud_when_available'] ),
		);
	}

	public function allowed_media_derivative_formats(): array {
		return array( 'webp', 'avif', 'jpeg', 'png', 'original' );
	}

	public function allowed_media_watermark_positions(): array {
		return array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' );
	}

	private function maybe_migrate_core_media_policy(): void {
		if ( null !== get_option( Plugin::MEDIA_OPTION_NAME, null ) ) {
			return;
		}

		$core_value = get_option( 'npcink_governance_core_media_derivative_settings', null );
		if ( ! is_array( $core_value ) ) {
			return;
		}

		add_option( Plugin::MEDIA_OPTION_NAME, $this->sanitize_media_optimization( $core_value ) );
	}

	private function media_watermark_configured( array $settings ): bool {
		if ( empty( $settings['watermark_enabled'] ) ) {
			return false;
		}

		if ( 'text' === (string) ( $settings['watermark_type'] ?? '' ) ) {
			return '' !== trim( (string) ( $settings['watermark_text'] ?? '' ) );
		}

		return absint( $settings['watermark_attachment_id'] ?? 0 ) > 0;
	}

	private function sanitize_media_derivative_watermark_plan( array $watermark, array $settings ): array {
		$type = sanitize_key( (string) ( $watermark['type'] ?? 'image' ) );
		if ( ! in_array( $type, array( 'image', 'text' ), true ) ) {
			$type = 'image';
		}

		$position = sanitize_key( (string) ( $watermark['position'] ?? $settings['watermark_position'] ?? 'bottom_right' ) );
		if ( ! in_array( $position, $this->allowed_media_watermark_positions(), true ) ) {
			$position = 'bottom_right';
		}

		$opacity = is_numeric( $watermark['opacity'] ?? null )
			? (float) $watermark['opacity']
			: ( (int) ( $settings['watermark_opacity'] ?? 80 ) / 100 );
		$opacity   = round( max( 0, min( 1, $opacity ) ), 3 );
		$margin_px = max( 0, min( 1000, absint( $watermark['margin_px'] ?? $settings['watermark_margin'] ?? 24 ) ) );

		if ( 'text' === $type ) {
			$text = trim( sanitize_text_field( (string) ( $watermark['text'] ?? 'AI' ) ) );
			if ( '' === $text ) {
				$text = 'AI';
			}
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 64 ) : substr( $text, 0, 64 );

			return array(
				'type'       => 'text',
				'text'       => $text,
				'position'   => $position,
				'opacity'    => $opacity,
				'font_size'  => max( 8, min( 256, absint( $watermark['font_size'] ?? 48 ) ) ),
				'color'      => $this->sanitize_media_derivative_watermark_color( $watermark['color'] ?? '#FFFFFF', '#FFFFFF' ),
				'background' => $this->sanitize_media_derivative_watermark_color( $watermark['background'] ?? 'rgba(0,0,0,0.35)', 'rgba(0,0,0,0.35)' ),
				'margin_px'  => $margin_px,
			);
		}

		$artifact_id = sanitize_text_field( (string) ( $watermark['artifact_id'] ?? '' ) );
		$sanitized   = array(
			'type'          => 'image',
			'position'      => $position,
			'opacity'       => $opacity,
			'scale_percent' => max( 1, min( 100, absint( $watermark['scale_percent'] ?? $settings['watermark_scale'] ?? 20 ) ) ),
			'margin_px'     => $margin_px,
		);
		if ( '' !== $artifact_id ) {
			$sanitized['artifact_id'] = $artifact_id;
		}

		return $sanitized;
	}

	private function sanitize_media_derivative_crop_plan( array $crop ): array {
		$aspect_ratio = trim( sanitize_text_field( (string) ( $crop['aspect_ratio'] ?? '16:9' ) ) );
		if ( 1 !== preg_match( '/^([1-9][0-9]{0,2}):([1-9][0-9]{0,2})$/', $aspect_ratio, $matches ) || (int) $matches[1] > 100 || (int) $matches[2] > 100 ) {
			$aspect_ratio = '16:9';
		}

		$position = sanitize_key( (string) ( $crop['position'] ?? 'center' ) );
		if ( ! in_array( $position, array( 'top_left', 'top', 'top_right', 'left', 'center', 'right', 'bottom_left', 'bottom', 'bottom_right' ), true ) ) {
			$position = 'center';
		}

		return array(
			'type'         => 'aspect_ratio',
			'aspect_ratio' => $aspect_ratio,
			'position'     => $position,
		);
	}

	private function sanitize_media_derivative_watermark_color( $value, string $default ): string {
		$color = trim( sanitize_text_field( (string) $value ) );
		if ( 'transparent' === strtolower( $color ) ) {
			return 'transparent';
		}
		if ( 1 === preg_match( '/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color ) ) {
			return strtoupper( $color );
		}
		if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $color, $matches ) ) {
			$r     = max( 0, min( 255, (int) $matches[1] ) );
			$g     = max( 0, min( 255, (int) $matches[2] ) );
			$b     = max( 0, min( 255, (int) $matches[3] ) );
			$alpha = isset( $matches[4] ) && '' !== $matches[4] ? max( 0, min( 1, (float) $matches[4] ) ) : null;

			return null === $alpha
				? sprintf( 'rgb(%d,%d,%d)', $r, $g, $b )
				: sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( sprintf( '%.3F', $alpha ), '0' ), '.' ) );
		}

		return $default;
	}

	private function has_cloud_request_filter(): bool {
		foreach ( array(
			'npcink_toolbox_web_search_cloud_request',
			'npcink_toolbox_hosted_ai_cloud_request',
			'npcink_toolbox_site_knowledge_cloud_request',
			'npcink_toolbox_image_source_cloud_request',
			'npcink_toolbox_nightly_inspection_cloud_batch_cloud_request',
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
