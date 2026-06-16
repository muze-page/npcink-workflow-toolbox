<?php
/**
 * Collects a bounded local WordPress snapshot for manual inspection previews.
 *
 * @package Npcink_Local_Automation_Runtime
 */

namespace Npcink\LocalAutomationRuntime\NightlyInspection;

final class Snapshot_Collector {
	private const DEFAULT_POST_LIMIT  = 12;
	private const DEFAULT_MEDIA_LIMIT = 8;

	/**
	 * Builds a read-only snapshot from published local WordPress content.
	 *
	 * @return array<string,mixed>
	 */
	public function collect( int $post_limit = self::DEFAULT_POST_LIMIT, int $media_limit = self::DEFAULT_MEDIA_LIMIT ): array {
		$timestamp = $this->current_gmt_timestamp();

		return array(
			'run_id'       => 'manual-nightly-preview-' . gmdate( 'YmdHis', $timestamp ),
			'site_id'      => $this->site_id(),
			'generated_at' => gmdate( 'c', $timestamp ),
			'posts'        => $this->collect_posts( $post_limit ),
			'media'        => $this->collect_media( $media_limit ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_posts( int $limit ): array {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'numberposts'    => max( 1, min( 50, $limit ) ),
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'cache_results'  => true,
			)
		);

		$snapshot = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$post_id = (int) $post->ID;
			$content = (string) $post->post_content;
			$snapshot[] = array(
				'object_type'            => (string) $post->post_type,
				'object_id'              => $post_id,
				'title'                  => (string) $post->post_title,
				'content'                => $content,
				'meta_description'       => $this->meta_description( $post_id ),
				'modified_at'            => $this->post_modified_gmt( $post ),
				'internal_link_count'    => $this->internal_link_count( $content ),
				'categories'             => $this->term_names( $post_id, 'category' ),
				'tags'                   => $this->term_names( $post_id, 'post_tag' ),
				'featured_image_present' => $this->featured_image_present( $post_id ),
				'missing_alt_count'      => $this->missing_alt_count( $post_id, $content ),
			);
		}

		return $snapshot;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_media( int $limit ): array {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'numberposts'    => max( 1, min( 50, $limit ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'cache_results'  => true,
			)
		);

		$snapshot = array();
		foreach ( $attachments as $attachment ) {
			if ( ! $attachment instanceof \WP_Post ) {
				continue;
			}
			$attachment_id = (int) $attachment->ID;
			$snapshot[] = array(
				'object_type' => 'attachment',
				'object_id'   => $attachment_id,
				'title'       => (string) $attachment->post_title,
				'filename'    => basename( (string) get_attached_file( $attachment_id ) ),
				'alt'         => trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			);
		}

		return $snapshot;
	}

	private function meta_description( int $post_id ): string {
		foreach ( array( '_yoast_wpseo_metadesc', '_aioseo_description', 'rank_math_description' ) as $meta_key ) {
			$value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @return array<int,string>
	 */
	private function term_names( int $post_id, string $taxonomy ): array {
		$terms = wp_get_post_terms(
			$post_id,
			$taxonomy,
			array(
				'fields' => 'names',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static fn( $term ): string => trim( (string) $term ),
					$terms
				)
			)
		);
	}

	private function featured_image_present( int $post_id ): bool {
		return (int) get_post_thumbnail_id( $post_id ) > 0;
	}

	private function missing_alt_count( int $post_id, string $content ): int {
		$missing = 0;
		$featured_id = (int) get_post_thumbnail_id( $post_id );
		if ( $featured_id > 0 && '' === trim( (string) get_post_meta( $featured_id, '_wp_attachment_image_alt', true ) ) ) {
			++$missing;
		}

		if ( preg_match_all( '/<img\b[^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[0] as $image_tag ) {
				if ( ! preg_match( '/\salt\s*=\s*([\'"])(.*?)\1/i', (string) $image_tag, $alt_match ) || '' === trim( (string) ( $alt_match[2] ?? '' ) ) ) {
					++$missing;
				}
			}
		}

		return $missing;
	}

	private function internal_link_count( string $content ): int {
		if ( ! preg_match_all( '/<a\b[^>]*\shref\s*=\s*([\'"])(.*?)\1/i', $content, $matches ) ) {
			return 0;
		}

		$site_host = $this->site_host();
		$count     = 0;
		foreach ( $matches[2] as $href ) {
			$href = trim( (string) $href );
			if ( '' === $href || '#' === $href[0] || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) ) {
				continue;
			}
			$host = (string) wp_parse_url( $href, PHP_URL_HOST );
			if ( '' === $host || ( '' !== $site_host && strtolower( $host ) === strtolower( $site_host ) ) ) {
				++$count;
			}
		}

		return $count;
	}

	private function post_modified_gmt( \WP_Post $post ): string {
		$modified_gmt = (string) $post->post_modified_gmt;
		$timestamp    = '' !== $modified_gmt ? strtotime( $modified_gmt . ' UTC' ) : false;
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'c', $timestamp );
	}

	private function site_id(): string {
		$host = $this->site_host();
		if ( '' !== $host ) {
			return $host;
		}

		return 'local-site';
	}

	private function site_host(): string {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return trim( $host );
	}

	private function current_gmt_timestamp(): int {
		$timestamp = current_time( 'timestamp', true );
		if ( is_numeric( $timestamp ) ) {
			return (int) $timestamp;
		}

		return time();
	}
}
