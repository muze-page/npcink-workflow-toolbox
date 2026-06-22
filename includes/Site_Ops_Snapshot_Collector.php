<?php
/**
 * Bounded read-only evidence collection for Site Ops Insights.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Site_Ops_Snapshot_Collector {
	private const DEFAULT_POST_LIMIT    = 24;
	private const DEFAULT_MEDIA_LIMIT   = 24;
	private const DEFAULT_COMMENT_LIMIT = 80;

	/**
	 * Builds a bounded public-site snapshot without storing state.
	 *
	 * @return array<string,mixed>
	 */
	public function collect( int $post_limit = self::DEFAULT_POST_LIMIT, int $media_limit = self::DEFAULT_MEDIA_LIMIT, int $comment_limit = self::DEFAULT_COMMENT_LIMIT ): array {
		$timestamp = $this->current_gmt_timestamp();

		return array(
			'run_id'       => 'site-ops-insights-' . gmdate( 'YmdHis', $timestamp ),
			'site_id'      => $this->site_id(),
			'generated_at' => gmdate( 'c', $timestamp ),
			'site'         => $this->collect_site(),
			'posts'        => $this->collect_posts( $post_limit ),
			'media'        => $this->collect_media( $media_limit ),
			'comments'     => $this->collect_comments( $comment_limit ),
			'taxonomies'   => $this->collect_taxonomies(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_site(): array {
		return array(
			'name'        => (string) get_bloginfo( 'name' ),
			'description' => (string) get_bloginfo( 'description' ),
			'url_host'    => (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
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
				'title'                  => wp_strip_all_tags( (string) $post->post_title ),
				'published_at'           => $this->post_date_gmt( $post ),
				'modified_at'            => $this->post_modified_gmt( $post ),
				'word_count'             => $this->word_count( $content ),
				'internal_link_count'    => $this->internal_link_count( $content ),
				'categories'             => $this->term_names( $post_id, 'category' ),
				'tags'                   => $this->term_names( $post_id, 'post_tag' ),
				'featured_image_present' => (int) get_post_thumbnail_id( $post_id ) > 0,
				'missing_alt_count'      => $this->missing_alt_count( $post_id, $content ),
				'meta_description_present' => '' !== $this->meta_description( $post_id ),
				'excerpt_present'        => '' !== trim( (string) $post->post_excerpt ),
				'approved_comment_count' => (int) get_comments_number( $post_id ),
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
				'object_type'     => 'attachment',
				'object_id'       => $attachment_id,
				'title'           => wp_strip_all_tags( (string) $attachment->post_title ),
				'filename_present' => '' !== basename( (string) get_attached_file( $attachment_id ) ),
				'alt_present'     => '' !== trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
				'caption_present' => '' !== trim( (string) $attachment->post_excerpt ),
				'parent_post_id'  => (int) $attachment->post_parent,
			);
		}

		return $snapshot;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_comments( int $limit ): array {
		$recent_approved = get_comments(
			array(
				'status'  => 'approve',
				'number'  => max( 1, min( 100, $limit ) ),
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'type'    => 'comment',
			)
		);
		$approved_total = get_comments(
			array(
				'status' => 'approve',
				'type'   => 'comment',
				'count'  => true,
			)
		);
		$pending_total = get_comments(
			array(
				'status' => 'hold',
				'type'   => 'comment',
				'count'  => true,
			)
		);

		$question_like_count = 0;
		$long_comment_count  = 0;
		$active_post_ids     = array();
		foreach ( $recent_approved as $comment ) {
			if ( ! $comment instanceof \WP_Comment ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( (string) $comment->comment_content ) );
			if ( '' === $text ) {
				continue;
			}
			if ( false !== strpos( $text, '?' ) || false !== strpos( $text, '？' ) ) {
				++$question_like_count;
			}
			if ( str_word_count( $text ) >= 40 || mb_strlen( $text ) >= 120 ) {
				++$long_comment_count;
			}
			$post_id = (int) $comment->comment_post_ID;
			if ( $post_id > 0 ) {
				$active_post_ids[] = $post_id;
			}
		}

		return array(
			'approved_total'      => (int) $approved_total,
			'pending_total'       => (int) $pending_total,
			'recent_sample_count' => count( $recent_approved ),
			'question_like_count' => $question_like_count,
			'long_comment_count'  => $long_comment_count,
			'active_post_count'   => count( array_unique( $active_post_ids ) ),
			'privacy'             => array(
				'approved_public_comments_only' => true,
				'comment_text_returned'         => false,
				'author_email_returned'         => false,
				'ip_address_returned'           => false,
				'user_agent_returned'           => false,
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function collect_taxonomies(): array {
		return array(
			'category' => $this->taxonomy_summary( 'category' ),
			'post_tag' => $this->taxonomy_summary( 'post_tag' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function taxonomy_summary( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 100,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array(
				'total'       => 0,
				'empty_count' => 0,
				'low_count'   => 0,
			);
		}

		$empty_count = 0;
		$low_count   = 0;
		foreach ( $terms as $term ) {
			$count = (int) ( $term->count ?? 0 );
			if ( 0 === $count ) {
				++$empty_count;
			}
			if ( $count > 0 && $count <= 1 ) {
				++$low_count;
			}
		}

		return array(
			'total'       => count( $terms ),
			'empty_count' => $empty_count,
			'low_count'   => $low_count,
		);
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

	private function missing_alt_count( int $post_id, string $content ): int {
		$missing     = 0;
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

		$site_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
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

	private function word_count( string $content ): int {
		$text = trim( wp_strip_all_tags( $content ) );
		if ( '' === $text ) {
			return 0;
		}

		$count = str_word_count( $text );
		if ( $count > 0 ) {
			return $count;
		}

		return (int) ceil( mb_strlen( preg_replace( '/\s+/u', '', $text ) ?: '' ) / 2 );
	}

	private function post_date_gmt( \WP_Post $post ): string {
		$date_gmt  = (string) $post->post_date_gmt;
		$timestamp = '' !== $date_gmt ? strtotime( $date_gmt . ' UTC' ) : false;
		return false === $timestamp ? '' : gmdate( 'c', $timestamp );
	}

	private function post_modified_gmt( \WP_Post $post ): string {
		$modified_gmt = (string) $post->post_modified_gmt;
		$timestamp    = '' !== $modified_gmt ? strtotime( $modified_gmt . ' UTC' ) : false;
		return false === $timestamp ? '' : gmdate( 'c', $timestamp );
	}

	private function site_id(): string {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return sanitize_key( '' !== $host ? $host : 'local-site' );
	}

	private function current_gmt_timestamp(): int {
		if ( function_exists( 'current_time' ) ) {
			return (int) current_time( 'timestamp', true );
		}

		return time();
	}
}
