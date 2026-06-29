<?php
/**
 * WordPress admin page for Toolbox actions.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Manual_Dry_Run_Planner;
use Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run;
use Npcink\LocalAutomationRuntime\NightlyInspection\Morning_Brief_Builder;
use Npcink\LocalAutomationRuntime\NightlyInspection\Snapshot_Collector;

defined( 'ABSPATH' ) || exit;

final class Admin_Page {
	private const PARENT_MENU_SLUG = 'npcink-ai';
	private const MENU_SLUG        = 'npcink-toolbox';

	private Settings $settings;
	private string $hook_suffix = '';

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_menu(): void {
		if ( $this->has_npcink_parent_menu() ) {
			$this->hook_suffix = add_submenu_page(
				self::PARENT_MENU_SLUG,
				__( 'Npcink Workflow Toolbox', 'npcink-workflow-toolbox' ),
				__( 'Toolbox', 'npcink-workflow-toolbox' ),
				'manage_options',
				self::MENU_SLUG,
				array( $this, 'render' ),
				45
			);
			return;
		}

		$this->hook_suffix = add_management_page(
			__( 'Npcink Workflow Toolbox', 'npcink-workflow-toolbox' ),
			__( 'Npcink Workflow Toolbox', 'npcink-workflow-toolbox' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	private function has_npcink_parent_menu(): bool {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && self::PARENT_MENU_SLUG === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$style_version  = $this->asset_version( 'assets/admin.css' );
		$script_version = $this->asset_version( 'assets/admin.js' );

		wp_enqueue_style(
			'npcink-toolbox-admin',
			NPCINK_TOOLBOX_URL . 'assets/admin.css',
			array(),
			$style_version
		);

		wp_enqueue_script(
			'npcink-toolbox-admin',
			NPCINK_TOOLBOX_URL . 'assets/admin.js',
			array(),
			$script_version,
			true
		);
		wp_set_script_translations(
			'npcink-toolbox-admin',
			'npcink-workflow-toolbox',
			NPCINK_TOOLBOX_DIR . 'languages'
		);
		wp_enqueue_media();

		wp_localize_script(
			'npcink-toolbox-admin',
			'NpcinkToolbox',
			array(
				'restUrl'       => esc_url_raw( rest_url( Plugin::REST_NAMESPACE ) ),
				'adapterRestUrl' => esc_url_raw( rest_url( 'npcink-openclaw-adapter/v1' ) ),
				'coreAdminUrl'  => esc_url_raw( admin_url( 'admin.php?page=npcink-governance-core' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'dateTime'      => $this->datetime_display_config(),
				'contextOption' => Plugin::CONTEXT_OPTION_NAME,
				'contextDrafts' => array(
					'aiBlog' => $this->get_ai_blog_context_template(),
					'site'   => $this->get_site_content_context_suggestion(),
				),
				'labels'        => array(
					'running' => __( 'Running...', 'npcink-workflow-toolbox' ),
					'error'   => __( 'Request failed.', 'npcink-workflow-toolbox' ),
				)
			)
		);
	}

	private function asset_version( string $relative_path ): string {
		$path     = NPCINK_TOOLBOX_DIR . ltrim( $relative_path, '/' );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;
		return NPCINK_TOOLBOX_VERSION . ( $modified ? '-' . (string) $modified : '' );
	}

	private function toolbox_admin_url( array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::MENU_SLUG,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	private function media_library_url(): string {
		return add_query_arg(
			array(
				'mode' => 'list',
			),
			admin_url( 'upload.php' )
		);
	}

	private function image_batch_tool_url( string $tool, array $attachment_ids = array() ): string {
		$args = array(
			'tab'  => 'image',
			'tool' => $tool,
		);
		$attachment_ids = array_values(
			array_filter(
				array_map( 'absint', $attachment_ids ),
				static function ( int $attachment_id ): bool {
					return $attachment_id > 0;
				}
			)
		);
		if ( ! empty( $attachment_ids ) ) {
			$args['attachment_ids'] = implode( ',', array_slice( array_unique( $attachment_ids ), 0, 50 ) );
			$args['source']         = 'media-library-bulk';
		}

		return $this->toolbox_admin_url( $args );
	}

	/**
	 * Adds contextual AI actions to the WordPress media attachment details panel.
	 *
	 * @param array<string,array<string,mixed>> $form_fields Existing media fields.
	 * @param \WP_Post                         $post Attachment post.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_media_library_attachment_actions( array $form_fields, \WP_Post $post ): array {
		$attachment_id = absint( $post->ID ?? 0 );
		if ( $attachment_id <= 0 || ! current_user_can( 'manage_options' ) ) {
			return $form_fields;
		}
		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return $form_fields;
		}

		$form_fields['npcink_toolbox_ai_image_optimization'] = array(
			'label' => __( 'Npcink AI', 'npcink-workflow-toolbox' ),
			'input' => 'html',
			'html'  => sprintf(
				'<div class="npcink-toolbox-media-library-action"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a> <a class="button" href="%4$s">%5$s</a></p><p class="description">%6$s</p></div>',
				esc_html__( 'Work on this image from the media library. Suggestions and previews stay review-only until you submit accepted changes for review.', 'npcink-workflow-toolbox' ),
				esc_url( $this->image_batch_tool_url( 'bulk-alt', array( $attachment_id ) ) ),
				esc_html__( 'Complete ALT for this image', 'npcink-workflow-toolbox' ),
				esc_url( $this->image_batch_tool_url( 'batch-optimize', array( $attachment_id ) ) ),
				esc_html__( 'Optimize this image', 'npcink-workflow-toolbox' ),
				esc_html__( 'For multiple images, select them in the Media Library list and use the Npcink bulk actions.', 'npcink-workflow-toolbox' )
			),
		);

		return $form_fields;
	}

	/**
	 * Adds contextual Npcink AI actions to each image row in the media list table.
	 *
	 * @param array<string,string> $actions Existing row actions.
	 * @param \WP_Post            $post Attachment post.
	 * @param bool                $detached Whether the attachment is detached.
	 * @return array<string,string>
	 */
	public function filter_media_library_row_actions( array $actions, \WP_Post $post, bool $detached = false ): array {
		$attachment_id = absint( $post->ID ?? 0 );
		if ( $attachment_id <= 0 || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return $actions;
		}

		$actions['npcink_toolbox_alt'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->image_batch_tool_url( 'bulk-alt', array( $attachment_id ) ) ),
			esc_html__( 'Npcink ALT', 'npcink-workflow-toolbox' )
		);
		$actions['npcink_toolbox_optimize'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->image_batch_tool_url( 'batch-optimize', array( $attachment_id ) ) ),
			esc_html__( 'Npcink optimize', 'npcink-workflow-toolbox' )
		);

		return $actions;
	}

	/**
	 * Adds batch actions to the media library list table.
	 *
	 * @param array<string,string> $actions Existing actions.
	 * @return array<string,string>
	 */
	public function filter_media_library_bulk_actions( array $actions ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		$actions['npcink_toolbox_batch_alt']      = __( 'Npcink: complete ALT for selected images', 'npcink-workflow-toolbox' );
		$actions['npcink_toolbox_batch_optimize'] = __( 'Npcink: optimize selected images', 'npcink-workflow-toolbox' );
		return $actions;
	}

	/**
	 * Redirects selected media items to the governed Toolbox batch workbench.
	 *
	 * @param string                $redirect_to Redirect URL.
	 * @param string                $action Bulk action id.
	 * @param array<int,int|string> $post_ids Selected attachment IDs.
	 * @return string
	 */
	public function handle_media_library_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( ! in_array( $action, array( 'npcink_toolbox_batch_alt', 'npcink_toolbox_batch_optimize' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		$image_ids = array();
		foreach ( $post_ids as $post_id ) {
			$attachment_id = absint( $post_id );
			if ( $attachment_id <= 0 ) {
				continue;
			}
			if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
				continue;
			}
			$image_ids[] = $attachment_id;
		}

		$tool = 'npcink_toolbox_batch_alt' === $action ? 'bulk-alt' : 'batch-optimize';
		return $this->image_batch_tool_url( $tool, $image_ids );
	}

	private function datetime_display_config(): array {
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$now      = new \DateTimeImmutable( 'now', $timezone );

		return array(
			'format'        => 'Y-m-d H:i:s',
			'timeZone'      => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			'offsetMinutes' => (int) floor( $timezone->getOffset( $now ) / 60 ),
		);
	}

	private function get_ai_blog_context_template(): array {
		return array(
			'site_positioning'                  => __( 'A practical AI technology blog for developers, product teams, and AI tool builders. It focuses on large language model applications, agent workflows, WordPress AI integration, vector search, content automation, and AI product engineering.', 'npcink-workflow-toolbox' ),
			'target_audience'                   => array(
				__( 'AI application developers', 'npcink-workflow-toolbox' ),
				__( 'WordPress plugin developers', 'npcink-workflow-toolbox' ),
				__( 'Technical content operators', 'npcink-workflow-toolbox' ),
				__( 'AI product managers', 'npcink-workflow-toolbox' ),
				__( 'Independent developers', 'npcink-workflow-toolbox' ),
				__( 'Internal tools teams', 'npcink-workflow-toolbox' ),
			),
			'brand_voice'                       => __( 'Professional, pragmatic, clear, and restrained. Explain real use cases, engineering tradeoffs, and boundary risks. Avoid inflated marketing claims. Give direct recommendations with conditions and limits.', 'npcink-workflow-toolbox' ),
			'primary_keywords'                  => array(
				__( 'AI technology blog', 'npcink-workflow-toolbox' ),
				__( 'large language model applications', 'npcink-workflow-toolbox' ),
				__( 'AI Agent', 'npcink-workflow-toolbox' ),
				__( 'WordPress AI', 'npcink-workflow-toolbox' ),
				__( 'vector search', 'npcink-workflow-toolbox' ),
				__( 'RAG', 'npcink-workflow-toolbox' ),
				__( 'AI workflow', 'npcink-workflow-toolbox' ),
				__( 'content automation', 'npcink-workflow-toolbox' ),
			),
			'long_tail_keywords'                => array(
				__( 'how to integrate AI capabilities into WordPress', 'npcink-workflow-toolbox' ),
				__( 'AI Agent workflow design', 'npcink-workflow-toolbox' ),
				__( 'WordPress plugin development with AI tools', 'npcink-workflow-toolbox' ),
				__( 'vector search for content websites', 'npcink-workflow-toolbox' ),
				__( 'RAG and content retrieval practice', 'npcink-workflow-toolbox' ),
				__( 'AI content suggestion workflow', 'npcink-workflow-toolbox' ),
				__( 'large language model application engineering', 'npcink-workflow-toolbox' ),
			),
			'entity_keywords'                   => array( 'OpenAI', 'WordPress', 'Cloud Search', 'Site Knowledge', 'Unsplash', 'REST API', 'WordPress Abilities API', 'Npcink' ),
			'allowed_claims'                    => array(
				__( 'AI tools can assist research, generate suggestions, plan content, and improve editorial efficiency.', 'npcink-workflow-toolbox' ),
				__( 'Vector search, external search, and content context can improve retrieval and suggestion quality.', 'npcink-workflow-toolbox' ),
				__( 'Architecture advice and implementation ideas are suitable for development and testing contexts.', 'npcink-workflow-toolbox' ),
				__( 'Final publishing, SEO writes, and media changes should go through human review or governance.', 'npcink-workflow-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not claim AI output is always correct.', 'npcink-workflow-toolbox' ),
				__( 'Do not claim automatic SEO ranking improvements.', 'npcink-workflow-toolbox' ),
				__( 'Do not claim AI replaces human review, legal review, or expert judgment.', 'npcink-workflow-toolbox' ),
				__( 'Do not imply WordPress permissions, approval, or governance can be bypassed.', 'npcink-workflow-toolbox' ),
				__( 'Do not describe image-source search as AI image generation.', 'npcink-workflow-toolbox' ),
				__( 'Do not describe vector search as a complete knowledge base or automatic indexing system.', 'npcink-workflow-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported customer stories, rankings, benchmark results, or legal/medical/financial advice.', 'npcink-workflow-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Model comparisons, provider pricing, product roadmap, security posture, and production-readiness claims require current verification.', 'npcink-workflow-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions when the source does not clearly support every answer or step.', 'npcink-workflow-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Claims about implemented features, integrations, customer usage, benchmark quality, ranking impact, or availability must be confirmed by the operator.', 'npcink-workflow-toolbox' ),
			),
			'seo_rules'                         => __( "Titles should include the main topic keyword and avoid clickbait.\nDescriptions should state the problem, audience, and core conclusion.\nUse clear headings, steps, caveats, and engineering boundary notes.\nPrefer internal links to related tutorials, architecture notes, and tool reviews.", 'npcink-workflow-toolbox' ),
			'aeo_rules'                         => __( "Start with a direct answer, then add conditions, steps, and limits.\nPrefer FAQ, short definitions, comparison tables, and actionable checklists.\nAvoid abstract-only answers; include practical guidance.", 'npcink-workflow-toolbox' ),
			'geo_rules'                         => __( "Make key conclusions clear, standalone, and easy for AI systems to summarize.\nDefine important terms when they first appear.\nDistinguish implemented features, development-stage behavior, and future plans.\nAvoid inflated claims; state boundaries, inputs, outputs, and limits.", 'npcink-workflow-toolbox' ),
			'allow_faq_generation'              => true,
			'allow_aeo_summary'                 => true,
			'allow_geo_summary'                 => true,
			'allow_structured_data_suggestions' => true,
			'proposal_allowed_fields'           => array( 'seo_title', 'seo_description', 'slug', 'excerpt', 'faq', 'answer_summary', 'geo_summary', 'structured_data_hints' ),
		);
	}

	private function get_site_content_context_suggestion(): array {
		$site_name    = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		$tagline      = wp_strip_all_tags( (string) get_bloginfo( 'description' ) );
		$recent_posts = get_posts(
			array(
				'numberposts' => 8,
				'post_status' => 'publish',
				'post_type'   => 'post',
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
			)
		);
		$titles       = array();

		foreach ( $recent_posts as $post_id ) {
			$title = trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
			if ( '' !== $title ) {
				$titles[] = $title;
			}
		}

		$terms = get_terms(
			array(
				'taxonomy'   => array( 'category', 'post_tag' ),
				'hide_empty' => true,
				'number'     => 12,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		$term_names = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_names[] = wp_strip_all_tags( (string) $term->name );
			}
		}

		$primary_keywords   = array_slice( $this->unique_non_empty( $term_names ), 0, 8 );
		$entity_keywords    = array_slice( $this->unique_non_empty( array_merge( array( $site_name ), $term_names ) ), 0, 10 );
		$long_tail_keywords = array();

		foreach ( array_slice( $titles, 0, 6 ) as $title ) {
			$long_tail_keywords[] = sprintf(
				/* translators: %s: post title. */
				__( 'Practical guide: %s', 'npcink-workflow-toolbox' ),
				$title
			);
		}

		if ( empty( $primary_keywords ) ) {
			$primary_keywords = array_filter( array( $site_name, $tagline ) );
		}

		$position_parts = array_filter( array( $site_name, $tagline ) );
		$positioning    = ! empty( $position_parts )
			? sprintf(
				/* translators: %s: site name and tagline. */
				__( '%s. Use recent public posts, categories, and tags as non-secret guidance for content suggestions. Keep the site brief editable and verify recommendations before saving.', 'npcink-workflow-toolbox' ),
				implode( ' - ', $position_parts )
			)
			: __( 'A WordPress site with public content available for operator-reviewed AI content suggestions. Keep recommendations editable and verify them before saving.', 'npcink-workflow-toolbox' );

		return array(
			'site_positioning'                  => $positioning,
			'target_audience'                   => array( __( 'Current site readers', 'npcink-workflow-toolbox' ), __( 'Editors', 'npcink-workflow-toolbox' ), __( 'Site operators', 'npcink-workflow-toolbox' ) ),
			'brand_voice'                       => __( 'Use the tone implied by existing public posts. Prefer clear, accurate, and reviewable suggestions over promotional claims.', 'npcink-workflow-toolbox' ),
			'primary_keywords'                  => $primary_keywords,
			'long_tail_keywords'                => $this->unique_non_empty( $long_tail_keywords ),
			'entity_keywords'                   => $entity_keywords,
			'allowed_claims'                    => array(
				__( 'Suggestions may use public post titles, public categories, and public tags as context.', 'npcink-workflow-toolbox' ),
				__( 'Suggestions should be treated as drafts for operator review.', 'npcink-workflow-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not infer private business facts from public content.', 'npcink-workflow-toolbox' ),
				__( 'Do not claim the generated suggestions have been verified unless an operator verifies them.', 'npcink-workflow-toolbox' ),
				__( 'Do not bypass WordPress permissions, approval, or governance.', 'npcink-workflow-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported private facts, unverified business claims, and claims outside current public site content.', 'npcink-workflow-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Product status, pricing, customer examples, legal/medical/financial claims, and time-sensitive facts require operator confirmation.', 'npcink-workflow-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions unless the sampled source clearly supports them.', 'npcink-workflow-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Any claim not visible in public post titles, categories, tags, or supplied source content must be confirmed by the operator.', 'npcink-workflow-toolbox' ),
			),
			'seo_rules'                         => __( "Use public categories, tags, and recent article themes as keyword candidates.\nTitles should stay specific to the article topic and avoid clickbait.\nDescriptions should summarize the reader problem and expected value.\nSuggest internal links only when the target content is clearly related.", 'npcink-workflow-toolbox' ),
			'aeo_rules'                         => __( "Answer likely reader questions directly before giving details.\nPrefer concise definitions, steps, checklists, and FAQ suggestions.\nMark assumptions clearly when the site content does not provide enough evidence.", 'npcink-workflow-toolbox' ),
			'geo_rules'                         => __( "Use public entity names from categories, tags, and recent titles as entity hints.\nKeep conclusions standalone and easy to quote.\nDistinguish observed site content from generated recommendations.", 'npcink-workflow-toolbox' ),
			'allow_faq_generation'              => true,
			'allow_aeo_summary'                 => true,
			'allow_geo_summary'                 => true,
			'allow_structured_data_suggestions' => true,
			'proposal_allowed_fields'           => array( 'seo_title', 'seo_description', 'slug', 'excerpt', 'faq', 'answer_summary', 'geo_summary', 'structured_data_hints' ),
		);
	}

	private function unique_non_empty( array $items ): array {
		$normalized = array();

		foreach ( $items as $item ) {
			$value = trim( (string) $item );
			if ( '' !== $value ) {
				$normalized[] = $value;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'npcink-workflow-toolbox' ) );
		}

		$settings        = $this->settings->get_all();
		$content_context = $this->settings->get_content_context();
		$cloud_ready     = $this->settings->cloud_runtime_available();
		$nightly_preview = $this->nightly_inspection_preview_from_request();
		$site_ops_preview = $this->site_ops_insights_preview_from_request( $content_context, $cloud_ready );
		?>
		<div class="wrap npcink-toolbox">
			<h1><?php esc_html_e( 'Npcink Workflow Toolbox', 'npcink-workflow-toolbox' ); ?></h1>
			<p class="npcink-toolbox__scope"><?php esc_html_e( 'Check your site, improve images, and prepare safe AI suggestions without changing WordPress automatically.', 'npcink-workflow-toolbox' ); ?></p>
			<?php
			if ( ! $cloud_ready ) {
				$this->render_cloud_runtime_notice();
			}
			?>

			<nav class="npcink-toolbox__tabs" data-toolbox-tabs aria-label="<?php esc_attr_e( 'Toolbox sections', 'npcink-workflow-toolbox' ); ?>">
				<button type="button" class="npcink-toolbox__tab is-active" data-toolbox-tab-target="start" aria-selected="true"><?php esc_html_e( 'Overview', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__tab" data-toolbox-tab-target="context" aria-selected="false"><?php esc_html_e( 'Site Profile', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__tab" data-toolbox-tab-target="tools" aria-selected="false"><?php esc_html_e( 'Image Handling', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__tab" data-toolbox-tab-target="advanced" aria-selected="false"><?php esc_html_e( 'Advanced', 'npcink-workflow-toolbox' ); ?></button>
			</nav>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="start" aria-label="<?php esc_attr_e( 'Toolbox start', 'npcink-workflow-toolbox' ); ?>">
				<?php $this->render_start_panel( $content_context, $cloud_ready ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="context" aria-label="<?php esc_attr_e( 'Site context', 'npcink-workflow-toolbox' ); ?>" hidden>
				<?php $this->render_content_context_form( $content_context ); ?>
			</section>

			<section class="npcink-toolbox__panel npcink-toolbox__panel--secondary" data-toolbox-tab-panel="operations-insights" aria-label="<?php esc_attr_e( 'Full-site insights', 'npcink-workflow-toolbox' ); ?>" hidden>
				<?php $this->render_operations_insights_panel( $site_ops_preview, $content_context, $cloud_ready ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="tools" aria-label="<?php esc_attr_e( 'Image handling', 'npcink-workflow-toolbox' ); ?>" hidden>
				<?php $this->render_tool_cards( $cloud_ready, 'image' ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="advanced" aria-label="<?php esc_attr_e( 'Advanced Toolbox entries', 'npcink-workflow-toolbox' ); ?>" hidden>
				<?php $this->render_advanced_panel(); ?>
			</section>

			<section class="npcink-toolbox__panel npcink-toolbox__panel--secondary" data-toolbox-tab-panel="site-knowledge" aria-label="<?php esc_attr_e( 'Content library setup', 'npcink-workflow-toolbox' ); ?>" hidden>
				<?php $this->render_site_knowledge_panel( $cloud_ready ); ?>
			</section>

			<section class="npcink-toolbox__panel npcink-toolbox__panel--secondary" data-toolbox-tab-panel="cloud-checks" aria-label="<?php esc_attr_e( 'Cloud checks', 'npcink-workflow-toolbox' ); ?>" hidden>
				<?php $this->render_cloud_checks_panel( $settings, $cloud_ready, $nightly_preview ); ?>
			</section>
		</div>
		<?php
	}

	private function render_start_panel( array $content_context, bool $cloud_ready ): void {
		$context_ready = $this->content_context_ready( $content_context );
		$profile_action = $context_ready ? __( 'Review site profile', 'npcink-workflow-toolbox' ) : __( 'Complete site profile', 'npcink-workflow-toolbox' );
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Overview', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Choose the next site task. Advanced diagnostics stay hidden until you need them.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<div class="npcink-toolbox__start" data-toolbox-start>
			<section class="npcink-toolbox__start-priority" aria-label="<?php esc_attr_e( 'Recommended Toolbox action', 'npcink-workflow-toolbox' ); ?>">
				<div class="npcink-toolbox__start-primary">
					<span class="npcink-toolbox__eyebrow"><?php esc_html_e( 'Start here', 'npcink-workflow-toolbox' ); ?></span>
					<h3><?php esc_html_e( 'Check my site', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Generate one read-only report with content, image, and setup suggestions. Nothing is changed automatically.', 'npcink-workflow-toolbox' ); ?></p>
					<div class="npcink-toolbox__inline-actions">
						<a class="button button-primary" href="<?php echo esc_url( $this->site_ops_insights_preview_url() ); ?>"><?php esc_html_e( 'Start site check', 'npcink-workflow-toolbox' ); ?></a>
							<a class="button" href="<?php echo esc_url( $this->media_library_url() ); ?>"><?php esc_html_e( 'Choose image in media library', 'npcink-workflow-toolbox' ); ?></a>
					</div>
				</div>
				<div class="npcink-toolbox__start-status-list">
					<?php
					$this->render_start_status_row(
						__( 'AI service', 'npcink-workflow-toolbox' ),
						$cloud_ready ? 'ok' : 'warning',
						$cloud_ready ? __( 'Connected', 'npcink-workflow-toolbox' ) : __( 'Needs connection', 'npcink-workflow-toolbox' ),
						$cloud_ready ? __( 'Site checks and image suggestions can use hosted AI.', 'npcink-workflow-toolbox' ) : __( 'AI-powered checks stay disabled until the service is connected.', 'npcink-workflow-toolbox' )
					);
					$this->render_start_status_row(
						__( 'Site profile', 'npcink-workflow-toolbox' ),
						$context_ready ? 'ok' : 'neutral',
						$context_ready ? __( 'Ready', 'npcink-workflow-toolbox' ) : __( 'Needs brief', 'npcink-workflow-toolbox' ),
						$context_ready ? __( 'AI suggestions have the basic site brief they need.', 'npcink-workflow-toolbox' ) : __( 'Add the basic site brief before relying on repeated suggestions.', 'npcink-workflow-toolbox' )
					);
					$this->render_start_status_row(
						__( 'Safe mode', 'npcink-workflow-toolbox' ),
						'neutral',
						__( 'No automatic changes', 'npcink-workflow-toolbox' ),
						__( 'Reports and previews are suggestions. WordPress content, media, and SEO fields are not changed here.', 'npcink-workflow-toolbox' )
					);
					?>
				</div>
			</section>

			<div class="npcink-toolbox__section-heading npcink-toolbox__section-heading--compact">
				<div>
					<h3><?php esc_html_e( 'Common tasks', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Use these when you already know what you want to do next.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<section class="npcink-toolbox__start-actions npcink-toolbox__start-actions--secondary" aria-label="<?php esc_attr_e( 'Next actions', 'npcink-workflow-toolbox' ); ?>">
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( $this->media_library_url() ); ?>">
					<strong><?php esc_html_e( 'Choose an image from Media Library', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Open an image attachment, then use Npcink AI to generate an optimization preview.', 'npcink-workflow-toolbox' ); ?></span>
				</a>
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=context' ) ); ?>">
					<strong><?php echo esc_html( $profile_action ); ?></strong>
					<span><?php esc_html_e( 'Keep the basic site description, audience, tone, and keywords clear.', 'npcink-workflow-toolbox' ); ?></span>
				</a>
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=advanced' ) ); ?>">
					<strong><?php esc_html_e( 'Open advanced tools', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Find diagnostics, content library setup, and low-frequency workbench entries.', 'npcink-workflow-toolbox' ); ?></span>
				</a>
			</section>

			<details class="npcink-toolbox__start-advanced">
				<summary>
					<span><?php esc_html_e( 'Advanced and troubleshooting', 'npcink-workflow-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Setup, service checks, fallback previews, and lower-frequency workbench entries.', 'npcink-workflow-toolbox' ); ?></small>
				</summary>
				<div class="npcink-toolbox__start-actions" aria-label="<?php esc_attr_e( 'Advanced entries', 'npcink-workflow-toolbox' ); ?>">
					<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=cloud-checks' ) ); ?>">
						<strong><?php esc_html_e( 'AI service checks', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Use only when AI actions fail or support asks for diagnostics.', 'npcink-workflow-toolbox' ); ?></span>
					</a>
					<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=site-knowledge' ) ); ?>">
						<strong><?php esc_html_e( 'Content library setup', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Let AI search existing public posts and pages when building suggestions.', 'npcink-workflow-toolbox' ); ?></span>
					</a>
					<a class="npcink-toolbox__action-row" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>">
						<strong><?php esc_html_e( 'Preview Morning Brief', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Open a local dry-run preview for scheduled site review.', 'npcink-workflow-toolbox' ); ?></span>
					</a>
				</div>
			</details>
		</div>
		<?php
	}

	private function render_advanced_panel(): void {
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Advanced', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Use these entries only when you need setup, troubleshooting, or occasional review tools.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<section class="npcink-toolbox__advanced-home" aria-label="<?php esc_attr_e( 'Advanced Toolbox directory', 'npcink-workflow-toolbox' ); ?>">
			<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=site-knowledge' ) ); ?>">
				<strong><?php esc_html_e( 'Content library setup', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Let AI use existing public posts and pages as reference material.', 'npcink-workflow-toolbox' ); ?></span>
			</a>
			<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=cloud-checks' ) ); ?>">
				<strong><?php esc_html_e( 'AI service checks', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Check whether search, image suggestions, content library, and scheduled review are working.', 'npcink-workflow-toolbox' ); ?></span>
			</a>
			<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=operations-insights' ) ); ?>">
				<strong><?php esc_html_e( 'Site check details', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Open the detailed read-only report for the current site check.', 'npcink-workflow-toolbox' ); ?></span>
			</a>
			<a class="npcink-toolbox__action-row" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>">
				<strong><?php esc_html_e( 'Morning Brief preview', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Preview what the scheduled site review would report. Nothing is changed.', 'npcink-workflow-toolbox' ); ?></span>
			</a>
		</section>
		<?php
	}

	private function site_ops_insights_preview_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                      => self::MENU_SLUG,
					'toolbox_tab'               => 'operations-insights',
					'site_ops_insights_preview' => '1',
				),
				admin_url( 'admin.php' )
			),
			'npcink_toolbox_site_ops_insights_preview'
		);
	}

	private function site_ops_cloud_analysis_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                       => self::MENU_SLUG,
					'toolbox_tab'                => 'operations-insights',
					'site_ops_insights_preview'  => '1',
					'site_ops_cloud_analysis'    => '1',
				),
				admin_url( 'admin.php' )
			),
			'npcink_toolbox_site_ops_insights_preview'
		);
	}

	/**
	 * @param array<string,mixed> $content_context Content context.
	 * @return array<string,mixed>|null
	 */
	private function site_ops_insights_preview_from_request( array $content_context, bool $cloud_ready ): ?array {
		$requested = filter_input( INPUT_GET, 'site_ops_insights_preview', FILTER_UNSAFE_RAW );
		if ( '1' !== ( is_scalar( $requested ) ? (string) $requested : '' ) ) {
			return null;
		}

		$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		$nonce = is_scalar( $nonce ) ? (string) $nonce : '';
		if ( ! wp_verify_nonce( $nonce, 'npcink_toolbox_site_ops_insights_preview' ) ) {
			return array(
				'error' => __( 'The Full-site Insights preview link expired. Reload the page and try again.', 'npcink-workflow-toolbox' ),
			);
		}

		try {
			$collector        = new Site_Ops_Snapshot_Collector();
			$builder          = new Site_Ops_Insight_Builder();
			$request_builder  = new Site_Ops_Cloud_Request_Builder();
			$snapshot         = $collector->collect();
			$runtime_context  = array(
				'content_context_ready' => $this->content_context_ready( $content_context ),
				'cloud_ready'           => $cloud_ready,
			);
			$pack             = $builder->build(
				$snapshot,
				$runtime_context
			);
			$cloud_request    = $request_builder->build(
				$snapshot,
				$pack,
				$runtime_context
			);
			$cloud_analysis   = null;
			$cloud_requested  = filter_input( INPUT_GET, 'site_ops_cloud_analysis', FILTER_UNSAFE_RAW );
			if ( '1' === ( is_scalar( $cloud_requested ) ? (string) $cloud_requested : '' ) ) {
				if ( ! $cloud_ready ) {
					$cloud_analysis = new \WP_Error(
						'npcink_toolbox_site_ops_cloud_not_ready',
						__( 'Connect or verify Npcink Cloud before running Cloud Full-site Insights analysis.', 'npcink-workflow-toolbox' ),
						array( 'status' => 503 )
					);
				} else {
					$client         = new Provider_Client( $this->settings );
					$cloud_analysis = $client->run_site_ops_cloud_analysis( $cloud_request );
				}
			}

			return array(
				'snapshot'      => $snapshot,
				'pack'          => $pack,
				'cloud_request' => $cloud_request,
				'cloud_analysis' => $cloud_analysis,
			);
		} catch ( \Throwable $throwable ) {
			return array(
				'error' => __( 'Could not build the local Full-site Insights preview.', 'npcink-workflow-toolbox' ),
			);
		}
	}

	/**
	 * @param array<string,mixed>|null $preview Preview payload.
	 * @param array<string,mixed>      $content_context Content context.
	 */
	private function render_operations_insights_panel( ?array $preview, array $content_context, bool $cloud_ready ): void {
		$context_ready = $this->content_context_ready( $content_context );
		$pack          = isset( $preview['pack'] ) && is_array( $preview['pack'] ) ? $preview['pack'] : array();
		$cloud_request = isset( $preview['cloud_request'] ) && is_array( $preview['cloud_request'] ) ? $preview['cloud_request'] : array();
		$cloud_analysis = $preview['cloud_analysis'] ?? null;
		$summary       = isset( $pack['summary'] ) && is_array( $pack['summary'] ) ? $pack['summary'] : array();
		$findings      = isset( $pack['top_findings'] ) && is_array( $pack['top_findings'] ) ? array_slice( $pack['top_findings'], 0, 8 ) : array();
		$finding_count = count( $findings );
		$has_cloud_analysis = null !== $cloud_analysis;
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Full-site Insights', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Build a read-only site analysis report from bounded WordPress data. Findings stay suggestion-only and do not create Core proposals or WordPress writes.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<section class="npcink-toolbox__ops-status-row" aria-label="<?php esc_attr_e( 'Full-site Insights readiness', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__ops-status-main">
				<span><strong><?php esc_html_e( 'Local data', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( null === $preview ? __( 'Ready to scan', 'npcink-workflow-toolbox' ) : __( 'Scanned', 'npcink-workflow-toolbox' ) ); ?></span>
				<span><strong><?php esc_html_e( 'Site Context', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $context_ready ? __( 'Ready', 'npcink-workflow-toolbox' ) : __( 'Needs brief', 'npcink-workflow-toolbox' ) ); ?></span>
				<span><strong><?php esc_html_e( 'Cloud', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $cloud_ready ? __( 'Ready on request', 'npcink-workflow-toolbox' ) : __( 'Optional', 'npcink-workflow-toolbox' ) ); ?></span>
				<span><strong><?php esc_html_e( 'Writes', 'npcink-workflow-toolbox' ); ?></strong><?php esc_html_e( 'Disabled', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<div class="npcink-toolbox__ops-status-actions">
				<?php if ( null === $preview || isset( $preview['error'] ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $this->site_ops_insights_preview_url() ); ?>"><?php esc_html_e( 'Generate full-site report', 'npcink-workflow-toolbox' ); ?></a>
				<?php else : ?>
					<span><?php esc_html_e( 'Current snapshot is ready.', 'npcink-workflow-toolbox' ); ?></span>
					<a class="button button-small" href="<?php echo esc_url( $this->site_ops_insights_preview_url() ); ?>"><?php esc_html_e( 'Rescan', 'npcink-workflow-toolbox' ); ?></a>
				<?php endif; ?>
			</div>
		</section>

		<details class="npcink-toolbox__ops-loop-disclosure"<?php echo null === $preview ? ' open' : ''; ?>>
			<summary><?php esc_html_e( 'How to use Full-site Insights', 'npcink-workflow-toolbox' ); ?></summary>
			<section class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Full-site Insights operator loop', 'npcink-workflow-toolbox' ); ?>">
				<div>
					<strong><?php esc_html_e( '1. Scan local data', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Build a current snapshot from public content, approved comment signals, media metadata, taxonomy, Site Context, and Cloud readiness.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
				<div>
					<strong><?php esc_html_e( '2. Read the priority queue', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Use the overview, charts, dimensions, findings, and evidence tabs to decide which problem matters first.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
				<div>
					<strong><?php esc_html_e( '3. Add Cloud detail when useful', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Cloud may add AI summary, semantic ranking, trend explanation, and closure detail, but it remains review guidance only.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
				<div>
					<strong><?php esc_html_e( '4. Choose the follow-up path', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Handle simple items manually, or turn eligible items into reviewed handoff plans outside this report.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			</section>
		</details>

		<section class="npcink-toolbox__card" data-toolbox-site-ops-insights>
			<?php if ( null === $preview ) : ?>
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Site action checklist', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Scan the current site, then start with the few issues most likely to affect readers, search, and daily operations.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( isset( $preview['error'] ) ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php echo esc_html( (string) $preview['error'] ); ?></div>
			<?php elseif ( null === $preview ) : ?>
				<div class="npcink-toolbox__result-notice"><?php esc_html_e( 'No scan has run in this view yet. Generate a local report when you want a current full-site analysis snapshot.', 'npcink-workflow-toolbox' ); ?></div>
			<?php else : ?>
				<div class="npcink-toolbox__ops-workspace" data-toolbox-ops-tabs>
					<nav class="npcink-toolbox__ops-tabs" aria-label="<?php esc_attr_e( 'Full-site Insights views', 'npcink-workflow-toolbox' ); ?>">
						<button type="button" class="npcink-toolbox__ops-tab is-active" data-toolbox-ops-target="overview" aria-selected="true"><?php esc_html_e( 'Overview', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="content" aria-selected="false"><?php esc_html_e( 'Content', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="media" aria-selected="false"><?php esc_html_e( 'Media', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="comments" aria-selected="false"><?php esc_html_e( 'Comments', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="structure" aria-selected="false"><?php esc_html_e( 'Structure', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="findings" aria-selected="false"><?php esc_html_e( 'Findings', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="evidence" aria-selected="false"><?php esc_html_e( 'Evidence', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="cloud" aria-selected="false"><?php esc_html_e( 'Cloud analysis', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="advanced" aria-selected="false"><?php esc_html_e( 'Advanced', 'npcink-workflow-toolbox' ); ?></button>
					</nav>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="overview">
						<?php $this->render_site_ops_operator_brief( $findings, $summary, $cloud_analysis, $cloud_ready ); ?>
						<?php $this->render_site_ops_decision_queue( $findings ); ?>
						<?php $this->render_site_ops_handling_path_panel( $findings ); ?>
						<details class="npcink-toolbox__ops-scan-details">
							<summary>
								<strong><?php esc_html_e( 'View scan scope and charts', 'npcink-workflow-toolbox' ); ?></strong>
								<span>
									<?php
									printf(
										/* translators: 1: number of scanned posts/pages, 2: number of scanned media items, 3: number of sampled comments, 4: number of findings. */
										esc_html__( '%1$d posts/pages, %2$d media, %3$d comments, %4$d findings', 'npcink-workflow-toolbox' ),
										(int) ( $summary['scanned_posts'] ?? 0 ),
										(int) ( $summary['scanned_media'] ?? 0 ),
										(int) ( $summary['recent_comment_sample'] ?? 0 ),
										(int) ( $summary['top_finding_count'] ?? $finding_count )
									);
									?>
								</span>
							</summary>
							<?php $this->render_site_ops_local_analysis_summary( $summary, $findings ); ?>
							<?php $this->render_site_ops_visual_summary( $summary, $findings ); ?>
						</details>
						<?php if ( array() === $findings ) : ?>
							<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority site analysis findings were produced from this bounded local sample.', 'npcink-workflow-toolbox' ); ?></div>
						<?php endif; ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="content" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Content analysis', 'npcink-workflow-toolbox' ), __( 'Posts and pages: freshness, depth, metadata, and internal paths.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'content_freshness', 'content_quality', 'metadata' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="media" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Media analysis', 'npcink-workflow-toolbox' ), __( 'Image attachments and referenced media metadata, including ALT and captions.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'media' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="comments" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Comment analysis', 'npcink-workflow-toolbox' ), __( 'Approved comment signals, question-like comments, long comments, and pending moderation load.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'comments' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="structure" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Structure analysis', 'npcink-workflow-toolbox' ), __( 'Taxonomy shape, Site Context readiness, and Cloud-managed Site Knowledge readiness.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'taxonomy', 'site_context', 'site_knowledge' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="findings" hidden>
						<?php if ( array() === $findings ) : ?>
							<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority site analysis findings were produced from this bounded local sample.', 'npcink-workflow-toolbox' ); ?></div>
						<?php else : ?>
							<div class="npcink-toolbox__ops-priority-list">
								<?php foreach ( $findings as $finding ) : ?>
									<?php $this->render_site_ops_finding_row( $finding ); ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="evidence" hidden>
						<?php $this->render_site_ops_evidence_panel( $findings ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="cloud" hidden>
						<?php if ( $has_cloud_analysis ) : ?>
							<?php $this->render_site_ops_cloud_analysis_result( $cloud_analysis ); ?>
						<?php else : ?>
							<div class="npcink-toolbox__result-notice">
								<?php esc_html_e( 'Cloud analysis has not run for this local preview. Run it only when AI summary, semantic ranking, trend explanation, or heavier runtime/detail analysis is needed.', 'npcink-workflow-toolbox' ); ?>
								<?php if ( $cloud_ready ) : ?>
									<p><a class="button" href="<?php echo esc_url( $this->site_ops_cloud_analysis_url() ); ?>"><?php esc_html_e( 'Ask AI to summarize deeper', 'npcink-workflow-toolbox' ); ?></a></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="advanced" hidden>
						<details class="npcink-toolbox__result-details">
							<summary><?php esc_html_e( 'Copy insight pack JSON', 'npcink-workflow-toolbox' ); ?></summary>
							<p class="description"><?php esc_html_e( 'This local preview is not stored automatically and does not create a run, queue, Core proposal, or WordPress write.', 'npcink-workflow-toolbox' ); ?></p>
							<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( (string) wp_json_encode( $pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
						</details>
						<?php if ( array() !== $cloud_request ) : ?>
							<details class="npcink-toolbox__result-details">
								<summary><?php esc_html_e( 'Copy Cloud analysis request JSON', 'npcink-workflow-toolbox' ); ?></summary>
								<p class="description"><?php esc_html_e( 'This contract is prepared for Cloud runtime analysis. Copying it does not call Cloud, schedule work, store a local run, create Core proposals, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></p>
								<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( (string) wp_json_encode( $cloud_request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
							</details>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'No local trend chart is shown because Toolbox does not store historical Full-site Insights runs. Cross-run trend analysis belongs in Cloud runtime/detail output.', 'npcink-workflow-toolbox' ); ?></p>
					</section>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * @param array<int,mixed>         $findings Findings.
	 * @param array<string,mixed>      $summary Summary payload.
	 * @param array<string,mixed>|null $cloud_analysis Cloud analysis payload.
	 */
	private function render_site_ops_operator_brief( array $findings, array $summary, ?array $cloud_analysis, bool $cloud_ready ): void {
		$queue = array();
		foreach ( $findings as $finding ) {
			if ( is_array( $finding ) ) {
				$queue[] = $finding;
			}
			if ( count( $queue ) >= 3 ) {
				break;
			}
		}
		$deferred = array();
		foreach ( array_slice( $findings, 3 ) as $finding ) {
			if ( is_array( $finding ) ) {
				$deferred[] = $finding;
			}
			if ( count( $deferred ) >= 2 ) {
				break;
			}
		}
		$review_count = $this->count_site_ops_findings_by_boundary( $findings, 'core_handoff_candidate' );
		$manual_count = $this->count_site_ops_findings_by_boundary( $findings, 'manual_review_only' );
		$cloud_result = is_array( $cloud_analysis['result'] ?? null ) ? $cloud_analysis['result'] : array();
		$executive_summary = is_array( $cloud_result['executive_summary'] ?? null ) ? $cloud_result['executive_summary'] : array();
		$cloud_headline = trim( (string) ( $executive_summary['headline'] ?? '' ) );
		$cloud_summary = trim( (string) ( $executive_summary['summary'] ?? '' ) );
		$cloud_priority_queue = is_array( $cloud_result['priority_queue'] ?? null ) ? array_slice( $cloud_result['priority_queue'], 0, 3 ) : array();
		$semantic_ranked_findings = is_array( $cloud_result['semantic_ranked_findings'] ?? null ) ? array_slice( $cloud_result['semantic_ranked_findings'], 0, 3 ) : array();
		$cloud_next_actions = is_array( $cloud_result['operator_next_actions'] ?? null ) ? array_slice( $cloud_result['operator_next_actions'], 0, 3 ) : array();
		$analysis_closure = is_array( $cloud_result['analysis_closure'] ?? null ) ? $cloud_result['analysis_closure'] : array();
		$confidence = is_array( $cloud_result['confidence'] ?? null ) ? $cloud_result['confidence'] : array();
		$cloud_has_detail = null !== $cloud_analysis;
		$cloud_queue = array();
		foreach ( array_merge( $cloud_priority_queue, $semantic_ranked_findings ) as $finding ) {
			if ( is_array( $finding ) ) {
				$cloud_queue[] = $finding;
			}
			if ( count( $cloud_queue ) >= 3 ) {
				break;
			}
		}
		$brief_queue = $cloud_has_detail && array() !== $cloud_queue ? $cloud_queue : $queue;
		$primary = is_array( $brief_queue[0] ?? null ) ? $brief_queue[0] : array();
		$primary_title = array() !== $primary ? $this->site_ops_finding_title( $primary ) : __( 'No urgent site issue found', 'npcink-workflow-toolbox' );
		$ai_next_action = '';
		if ( is_array( $cloud_next_actions[0] ?? null ) ) {
			$first_next = $cloud_next_actions[0];
			$ai_next_action = $this->site_ops_dynamic_label( (string) ( $first_next['label'] ?? $first_next['target'] ?? $first_next['id'] ?? '' ) );
		}
		$closure_next = $this->site_ops_dynamic_label( (string) ( $analysis_closure['next_step'] ?? $analysis_closure['loop_status'] ?? '' ) );
		$confidence_level = $this->site_ops_dynamic_label( (string) ( $confidence['level'] ?? '' ) );
		?>
		<section class="npcink-toolbox__ops-operator-brief" aria-label="<?php esc_attr_e( 'Site action brief', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__ops-operator-brief-header">
				<div>
					<h3><?php esc_html_e( 'Site action brief', 'npcink-workflow-toolbox' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: 1: primary finding title, 2: review-workflow count, 3: manual-check count. */
							esc_html__( 'Start with %1$s. %2$d items may need a review workflow and %3$d are manual checks.', 'npcink-workflow-toolbox' ),
							esc_html( $primary_title ),
							(int) $review_count,
							(int) $manual_count
						);
						?>
					</p>
					<?php if ( '' !== $cloud_headline ) : ?>
						<p><?php echo esc_html( $this->site_ops_dynamic_label( $cloud_headline ) ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $cloud_summary ) : ?>
						<p><?php echo esc_html( $this->site_ops_dynamic_label( $cloud_summary ) ); ?></p>
					<?php endif; ?>
				</div>
				<span class="npcink-toolbox__ops-brief-source">
					<?php echo esc_html( $cloud_has_detail ? __( 'AI detail added', 'npcink-workflow-toolbox' ) : __( 'Local brief', 'npcink-workflow-toolbox' ) ); ?>
				</span>
			</div>
			<div class="npcink-toolbox__ops-operator-brief-grid">
				<div>
					<strong><?php esc_html_e( 'Do first', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( array() === $brief_queue ) : ?>
						<p><?php esc_html_e( 'No priority task was produced by this bounded scan.', 'npcink-workflow-toolbox' ); ?></p>
					<?php else : ?>
						<ol>
							<?php foreach ( $brief_queue as $finding ) : ?>
								<li>
									<b><?php echo esc_html( $this->site_ops_finding_title( $finding ) ); ?></b>
									<span><?php echo esc_html( $this->site_ops_finding_recommended_action( $finding ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'Defer for now', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( array() === $deferred ) : ?>
						<p><?php esc_html_e( 'Do not expand scope until the first tasks are reviewed.', 'npcink-workflow-toolbox' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $deferred as $finding ) : ?>
								<li><?php echo esc_html( $this->site_ops_finding_title( $finding ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'AI assist', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( $cloud_has_detail ) : ?>
						<p><?php esc_html_e( 'AI summary and ranking are folded into this brief. Use Cloud analysis for the detailed evidence trail before expanding work.', 'npcink-workflow-toolbox' ); ?></p>
						<?php if ( '' !== $ai_next_action ) : ?>
							<p>
								<?php
								printf(
									/* translators: %s: AI suggested next action. */
									esc_html__( 'AI next step: %s', 'npcink-workflow-toolbox' ),
									esc_html( $ai_next_action )
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( '' !== $confidence_level ) : ?>
							<p>
								<?php
								printf(
									/* translators: %s: AI confidence level. */
									esc_html__( 'AI confidence: %s', 'npcink-workflow-toolbox' ),
									esc_html( $confidence_level )
								);
								?>
							</p>
						<?php endif; ?>
					<?php elseif ( $cloud_ready ) : ?>
						<p><?php esc_html_e( 'Need a clearer explanation or semantic ranking? Ask AI for a deeper summary after reviewing the local top items.', 'npcink-workflow-toolbox' ); ?></p>
						<a class="button button-small" href="<?php echo esc_url( $this->site_ops_cloud_analysis_url() ); ?>"><?php esc_html_e( 'Ask AI to summarize deeper', 'npcink-workflow-toolbox' ); ?></a>
					<?php else : ?>
						<p><?php esc_html_e( 'Cloud is not ready, so this brief uses local rules only. Connect Cloud when you need AI summary or semantic ranking.', 'npcink-workflow-toolbox' ); ?></p>
					<?php endif; ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'Close the loop', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( '' !== $closure_next ) : ?>
						<p>
							<?php
							printf(
								/* translators: %s: Cloud-reported analysis closure or next step. */
								esc_html__( 'AI closure: %s', 'npcink-workflow-toolbox' ),
								esc_html( $closure_next )
							);
							?>
						</p>
					<?php else : ?>
						<p><?php esc_html_e( 'Open the affected examples, decide manual handling or review workflow, then leave evidence in the normal editorial path. Nothing changes automatically.', 'npcink-workflow-toolbox' ); ?></p>
					<?php endif; ?>
					<?php /* translators: %d: number of scanned posts and pages. */ ?>
					<span><?php printf( esc_html__( 'Current scan: %d posts/pages.', 'npcink-workflow-toolbox' ), (int) ( $summary['scanned_posts'] ?? 0 ) ); ?></span>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<int,mixed>    $findings Findings.
	 */
	private function render_site_ops_handling_path_panel( array $findings ): void {
		if ( array() === $findings ) {
			return;
		}

		$manual_count = $this->count_site_ops_findings_by_boundary( $findings, 'manual_review_only' );
		$review_count = $this->count_site_ops_findings_by_boundary( $findings, 'core_handoff_candidate' );
		$cloud_count  = $this->count_site_ops_findings_by_boundary( $findings, 'blocked_until_cloud_ready' );
		$watch_count  = max( 0, count( $findings ) - $manual_count - $review_count );
		$paths        = array(
			array(
				'key'        => 'manual',
				'label'      => __( 'Handle manually', 'npcink-workflow-toolbox' ),
				'count'      => $manual_count,
				'finding'    => $this->site_ops_first_finding_by_boundary( $findings, array( 'manual_review_only' ) ),
				'summary'    => __( 'Open the affected content, media, comments, taxonomy, or settings in WordPress and check it yourself.', 'npcink-workflow-toolbox' ),
				'next_step'  => __( 'Use this path for simple review notes and small operator fixes. If it becomes a write workflow, move it to review.', 'npcink-workflow-toolbox' ),
				'empty_text' => __( 'No manual-only issues in this scan.', 'npcink-workflow-toolbox' ),
			),
			array(
				'key'        => 'review',
				'label'      => __( 'Send to review workflow', 'npcink-workflow-toolbox' ),
				'count'      => $review_count,
				'finding'    => $this->site_ops_first_finding_by_boundary( $findings, array( 'core_handoff_candidate' ) ),
				'summary'    => __( 'Use this path when the next step may change article content, media metadata, SEO fields, taxonomy, or site content.', 'npcink-workflow-toolbox' ),
				'next_step'  => __( 'Choose one affected item, confirm the evidence, write the accepted note, then prepare a governed handoff outside this report.', 'npcink-workflow-toolbox' ),
				'empty_text' => __( 'No review-workflow candidates in this scan.', 'npcink-workflow-toolbox' ),
			),
			array(
				'key'        => 'watch',
				'label'      => __( 'Watch for now', 'npcink-workflow-toolbox' ),
				'count'      => $watch_count,
				'finding'    => $this->site_ops_first_finding_by_boundary( $findings, array( 'blocked_until_cloud_ready', 'suggestion_only' ) ),
				'summary'    => 0 < $cloud_count ? __( 'Keep these as notes unless Cloud detail is needed to rank or explain the issue.', 'npcink-workflow-toolbox' ) : __( 'Keep these as notes unless they block readers, search, or daily site operations.', 'npcink-workflow-toolbox' ),
				'next_step'  => __( 'Do not start a workflow yet. Review again after the first priority items are handled.', 'npcink-workflow-toolbox' ),
				'empty_text' => __( 'No observe-only issues in this scan.', 'npcink-workflow-toolbox' ),
			),
		);
		?>
		<section class="npcink-toolbox__ops-path-panel" aria-label="<?php esc_attr_e( 'Treatment paths', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__section-heading npcink-toolbox__section-heading--compact">
				<div>
					<h3><?php esc_html_e( 'Choose a treatment path', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Sort each issue before opening details: handle it manually, prepare it for review, or watch it for now.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<div class="npcink-toolbox__ops-path-grid">
				<?php foreach ( $paths as $path ) : ?>
					<?php $finding = is_array( $path['finding'] ?? null ) ? $path['finding'] : array(); ?>
					<div class="npcink-toolbox__ops-path-row npcink-toolbox__ops-path-row--<?php echo esc_attr( (string) $path['key'] ); ?>">
						<div class="npcink-toolbox__ops-path-head">
							<strong><?php echo esc_html( (string) $path['label'] ); ?></strong>
							<span>
								<?php
								printf(
									/* translators: %d: number of findings for this treatment path. */
									esc_html__( '%d issues', 'npcink-workflow-toolbox' ),
									(int) $path['count']
								);
								?>
							</span>
						</div>
						<p><?php echo esc_html( (string) $path['summary'] ); ?></p>
						<?php if ( array() !== $finding ) : ?>
							<p class="npcink-toolbox__ops-path-first">
								<?php
								printf(
									/* translators: %s: first issue title for this treatment path. */
									esc_html__( 'First item: %s', 'npcink-workflow-toolbox' ),
									esc_html( $this->site_ops_finding_title( $finding ) )
								);
								?>
							</p>
						<?php else : ?>
							<p class="npcink-toolbox__ops-path-first"><?php echo esc_html( (string) $path['empty_text'] ); ?></p>
						<?php endif; ?>
						<p class="npcink-toolbox__ops-path-next"><?php echo esc_html( (string) $path['next_step'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="npcink-toolbox__ops-path-note"><?php esc_html_e( 'This panel does not create tasks, proposals, queues, or WordPress changes.', 'npcink-workflow-toolbox' ); ?></p>
		</section>
		<?php
	}

	/**
	 * @param array<int,mixed>    $findings Findings.
	 * @param array<int,string>   $boundaries Boundaries.
	 * @return array<string,mixed>
	 */
	private function site_ops_first_finding_by_boundary( array $findings, array $boundaries ): array {
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$boundary = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
			if ( in_array( $boundary, $boundaries, true ) ) {
				return $finding;
			}
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $summary Summary payload.
	 * @param array<int,mixed>    $findings Findings.
	 */
	private function render_site_ops_local_analysis_summary( array $summary, array $findings ): void {
		$finding_count  = count( $findings );
		$high_count     = (int) ( $summary['high_priority_findings'] ?? $this->count_site_ops_findings_by_priority( $findings, 90, 101 ) );
		$taxonomy_terms = (int) ( $summary['category_terms'] ?? 0 ) + (int) ( $summary['tag_terms'] ?? 0 );
		$dimension_counts = array(
			__( 'Content coverage', 'npcink-workflow-toolbox' )   => count( $this->site_ops_findings_by_category( $findings, array( 'content_freshness', 'content_quality', 'metadata' ) ) ),
			__( 'Media coverage', 'npcink-workflow-toolbox' )     => count( $this->site_ops_findings_by_category( $findings, array( 'media' ) ) ),
			__( 'Comment coverage', 'npcink-workflow-toolbox' )   => count( $this->site_ops_findings_by_category( $findings, array( 'comments' ) ) ),
			__( 'Structure coverage', 'npcink-workflow-toolbox' ) => count( $this->site_ops_findings_by_category( $findings, array( 'taxonomy', 'site_context', 'site_knowledge' ) ) ),
		);
		?>
		<div class="npcink-toolbox__ops-summary-bar" aria-label="<?php esc_attr_e( 'Local analysis summary', 'npcink-workflow-toolbox' ); ?>">
			<div>
				<strong><?php esc_html_e( 'Coverage snapshot', 'npcink-workflow-toolbox' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: number of findings, 2: suggested first focus area. */
						esc_html__( 'Local analysis summary: %1$d findings across content, media, comments, and structure. First focus: %2$s.', 'npcink-workflow-toolbox' ),
						(int) $finding_count,
						esc_html( $this->site_ops_analysis_focus_label( $findings ) )
					);
					?>
				</span>
				<span><?php esc_html_e( 'Deterministic local analysis only; use Cloud only for AI summary, semantic ranking, trends, or heavier runtime detail.', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<div class="npcink-toolbox__ops-scope">
				<?php /* translators: %d: number of high priority findings. */ ?>
				<span><?php printf( esc_html__( '%d high priority', 'npcink-workflow-toolbox' ), (int) $high_count ); ?></span>
				<?php /* translators: %d: number of taxonomy terms. */ ?>
				<span><?php printf( esc_html__( '%d taxonomy terms', 'npcink-workflow-toolbox' ), (int) $taxonomy_terms ); ?></span>
				<span><?php echo esc_html( $this->site_ops_local_report_status( $finding_count, $high_count ) ); ?></span>
			</div>
		</div>
		<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Local coverage by area', 'npcink-workflow-toolbox' ); ?>">
			<?php foreach ( $dimension_counts as $label => $count ) : ?>
				<div>
					<strong><?php echo esc_html( (string) $label ); ?></strong>
					<?php /* translators: %d: number of findings in this analysis area. */ ?>
					<span><?php printf( esc_html__( '%d findings', 'npcink-workflow-toolbox' ), (int) $count ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function render_site_ops_decision_queue( array $findings ): void {
		$queue = array();
		foreach ( $findings as $finding ) {
			if ( is_array( $finding ) ) {
				$queue[] = $finding;
			}
			if ( count( $queue ) >= 3 ) {
				break;
			}
		}
		if ( array() === $queue ) {
			return;
		}
		?>
		<section class="npcink-toolbox__ops-decision-queue" aria-label="<?php esc_attr_e( 'Priority decision queue', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__section-heading npcink-toolbox__section-heading--compact">
				<div>
					<h3><?php esc_html_e( 'Handle these first', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Each card explains why it matters, shows sampled affected items, and gives the first safe action.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<div class="npcink-toolbox__ops-decision-list">
				<?php foreach ( $queue as $index => $finding ) : ?>
					<?php
					$boundary = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
					$follow_up = $this->site_ops_follow_up_path_detail( $finding );
					?>
					<article class="npcink-toolbox__ops-decision-card">
						<div class="npcink-toolbox__ops-decision-rank">
							<span><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
						</div>
						<div class="npcink-toolbox__ops-decision-body">
							<div class="npcink-toolbox__ops-decision-header">
								<div>
									<span class="npcink-toolbox__ops-priority-pill"><?php echo esc_html( $this->site_ops_priority_action_label( (int) ( $finding['priority_score'] ?? 0 ) ) ); ?></span>
									<h4><?php echo esc_html( $this->site_ops_finding_title( $finding ) ); ?></h4>
								</div>
								<span class="npcink-toolbox__ops-handling-pill">
									<strong><?php esc_html_e( 'Handling', 'npcink-workflow-toolbox' ); ?></strong>
									<?php echo esc_html( $this->site_ops_boundary_label( $boundary ) ); ?>
								</span>
							</div>
							<dl class="npcink-toolbox__ops-decision-main">
								<div>
									<dt><?php esc_html_e( 'Why it matters', 'npcink-workflow-toolbox' ); ?></dt>
									<dd><?php echo esc_html( $this->site_ops_finding_impact( $finding ) ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'Affected examples', 'npcink-workflow-toolbox' ); ?></dt>
									<dd><?php $this->render_site_ops_affected_examples( $finding ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'First safe action', 'npcink-workflow-toolbox' ); ?></dt>
									<dd><?php echo esc_html( $this->site_ops_finding_recommended_action( $finding ) ); ?></dd>
								</div>
							</dl>
							<?php if ( 'core_handoff_candidate' === $boundary ) : ?>
								<?php $this->render_site_ops_handoff_candidate_preview( $finding ); ?>
							<?php endif; ?>
							<details class="npcink-toolbox__ops-follow-up" aria-label="<?php esc_attr_e( 'Handling rules and limits', 'npcink-workflow-toolbox' ); ?>">
								<summary><?php esc_html_e( 'View handling rules and limits', 'npcink-workflow-toolbox' ); ?></summary>
								<div class="npcink-toolbox__ops-follow-up-copy">
									<p><strong><?php esc_html_e( 'What this means', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $follow_up['meaning'] ); ?></p>
									<p><strong><?php esc_html_e( 'Before you act', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $follow_up['needs'] ); ?></p>
									<p><strong><?php esc_html_e( 'Limit', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $follow_up['limit'] ); ?></p>
								</div>
							</details>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function render_site_ops_handoff_candidate_preview( array $finding ): void {
		$source_refs = isset( $finding['source_refs'] ) && is_array( $finding['source_refs'] ) ? array_slice( $finding['source_refs'], 0, 3 ) : array();
		$evidence    = $this->site_ops_finding_evidence_summary( $finding );
		$action      = $this->site_ops_finding_recommended_action( $finding );
		?>
		<details class="npcink-toolbox__ops-handoff-preview" aria-label="<?php esc_attr_e( 'Review workflow candidate preview', 'npcink-workflow-toolbox' ); ?>">
			<summary><?php esc_html_e( 'View review candidate', 'npcink-workflow-toolbox' ); ?></summary>
			<div class="npcink-toolbox__ops-handoff-preview-body">
				<p><?php esc_html_e( 'Use this as a handoff draft only after choosing one affected item and confirming the evidence.', 'npcink-workflow-toolbox' ); ?></p>
				<?php if ( array() !== $source_refs ) : ?>
					<div>
						<strong><?php esc_html_e( 'Candidate objects', 'npcink-workflow-toolbox' ); ?></strong>
						<ul class="npcink-toolbox__ops-handoff-candidates">
							<?php foreach ( $source_refs as $ref ) : ?>
								<?php if ( ! is_array( $ref ) ) { continue; } ?>
								<?php
								$object_id   = (int) ( $ref['object_id'] ?? 0 );
								$object_type = sanitize_key( (string) ( $ref['object_type'] ?? 'post' ) );
								$title       = trim( (string) ( $ref['title'] ?? '' ) );
								$title       = '' !== $title ? $title : __( 'Untitled item', 'npcink-workflow-toolbox' );
								$link        = $object_id > 0 && current_user_can( 'edit_post', $object_id ) ? get_edit_post_link( $object_id, '' ) : '';
								?>
								<li>
									<?php if ( is_string( $link ) && '' !== $link ) : ?>
										<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
									<?php else : ?>
										<span><?php echo esc_html( $title ); ?></span>
									<?php endif; ?>
									<em><?php echo esc_html( $this->site_ops_object_type_label( $object_type ) ); ?></em>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<div class="npcink-toolbox__ops-handoff-copy-grid">
					<div>
						<strong><?php esc_html_e( 'Evidence to carry forward', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php echo esc_html( '' !== $evidence ? $evidence : __( 'Use the finding evidence and selected object before preparing a review workflow.', 'npcink-workflow-toolbox' ) ); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Suggested review note', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php echo esc_html( $action ); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Boundary', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Draft only. Toolbox does not create proposals, queue work, approve changes, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></span>
					</div>
				</div>
			</div>
		</details>
		<?php
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function render_site_ops_affected_examples( array $finding ): void {
		$source_refs = isset( $finding['source_refs'] ) && is_array( $finding['source_refs'] ) ? array_slice( $finding['source_refs'], 0, 3 ) : array();
		if ( array() === $source_refs ) {
			echo esc_html( $this->site_ops_finding_evidence_summary( $finding ) );
			return;
		}
		?>
		<span class="npcink-toolbox__ops-evidence-summary"><?php echo esc_html( $this->site_ops_finding_evidence_summary( $finding ) ); ?></span>
		<ul class="npcink-toolbox__ops-affected-list">
			<?php foreach ( $source_refs as $ref ) : ?>
				<?php if ( ! is_array( $ref ) ) { continue; } ?>
				<?php
				$object_id   = (int) ( $ref['object_id'] ?? 0 );
				$object_type = sanitize_key( (string) ( $ref['object_type'] ?? 'post' ) );
				$title       = trim( (string) ( $ref['title'] ?? '' ) );
				$title       = '' !== $title ? $title : __( 'Untitled item', 'npcink-workflow-toolbox' );
				$link        = $object_id > 0 && current_user_can( 'edit_post', $object_id ) ? get_edit_post_link( $object_id, '' ) : '';
				?>
				<li>
					<?php if ( is_string( $link ) && '' !== $link ) : ?>
						<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<span><?php echo esc_html( $title ); ?></span>
					<?php endif; ?>
					<em><?php echo esc_html( $this->site_ops_object_type_label( $object_type ) ); ?></em>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function site_ops_object_type_label( string $object_type ): string {
		if ( 'attachment' === $object_type ) {
			return __( 'Media item', 'npcink-workflow-toolbox' );
		}
		if ( 'page' === $object_type ) {
			return __( 'Page', 'npcink-workflow-toolbox' );
		}
		return __( 'Post', 'npcink-workflow-toolbox' );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 * @return array{meaning:string,needs:string,limit:string}
	 */
	private function site_ops_follow_up_path_detail( array $finding ): array {
		$boundary = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
		if ( 'core_handoff_candidate' === $boundary ) {
			return array(
				'meaning' => __( 'This report only marks a candidate. Pick the exact content and confirm the evidence before using the normal review flow outside this report.', 'npcink-workflow-toolbox' ),
				'needs'   => __( 'A selected item, accepted edits or notes, and a matching review path.', 'npcink-workflow-toolbox' ),
				'limit'   => __( 'This report will not create the review task or change WordPress.', 'npcink-workflow-toolbox' ),
			);
		}
		if ( 'manual_review_only' === $boundary ) {
			return array(
				'meaning' => __( 'Use this as an editorial or operator checklist before deciding whether a formal review path is needed.', 'npcink-workflow-toolbox' ),
				'needs'   => __( 'Human review of the affected content, media, comments, taxonomy, or settings.', 'npcink-workflow-toolbox' ),
				'limit'   => __( 'Nothing is changed automatically from this report.', 'npcink-workflow-toolbox' ),
			);
		}
		if ( 'blocked_until_cloud_ready' === $boundary ) {
			return array(
				'meaning' => __( 'Local evidence is enough to show the blocker, but semantic ranking or trend explanation needs Cloud runtime/detail.', 'npcink-workflow-toolbox' ),
				'needs'   => __( 'Cloud readiness and an explicit Run Cloud analysis action by the operator.', 'npcink-workflow-toolbox' ),
				'limit'   => __( 'Toolbox will not retry, queue, or run Cloud analysis automatically.', 'npcink-workflow-toolbox' ),
			);
		}
		return array(
			'meaning' => __( 'Use this as decision support for the current report only.', 'npcink-workflow-toolbox' ),
			'needs'   => __( 'Operator judgment before choosing any follow-up workflow.', 'npcink-workflow-toolbox' ),
			'limit'   => __( 'No automatic action, proposal, queue, or WordPress write is created.', 'npcink-workflow-toolbox' ),
		);
	}

	/**
	 * @param array<string,mixed> $summary Summary payload.
	 * @param array<int,mixed>    $findings Findings.
	 */
	private function render_site_ops_visual_summary( array $summary, array $findings ): void {
		$priority_counts = array(
			'high'   => $this->count_site_ops_findings_by_priority( $findings, 90, 101 ),
			'medium' => $this->count_site_ops_findings_by_priority( $findings, 75, 90 ),
			'review' => $this->count_site_ops_findings_by_priority( $findings, 0, 75 ),
		);
		$priority_max = max( 1, $priority_counts['high'], $priority_counts['medium'], $priority_counts['review'] );
		$core_count = $this->count_site_ops_findings_by_boundary( $findings, 'core_handoff_candidate' );
		$manual_count = $this->count_site_ops_findings_by_boundary( $findings, 'manual_review_only' );
		$suggestion_count = max( 0, count( $findings ) - $core_count - $manual_count );
		$boundary_total = max( 1, $core_count + $manual_count + $suggestion_count );
		$core_degrees = (int) round( 360 * $core_count / $boundary_total );
		$manual_degrees = (int) round( 360 * ( $core_count + $manual_count ) / $boundary_total );
		$scope_counts = array(
			__( 'Posts/pages', 'npcink-workflow-toolbox' ) => (int) ( $summary['scanned_posts'] ?? 0 ),
			__( 'Media', 'npcink-workflow-toolbox' )       => (int) ( $summary['scanned_media'] ?? 0 ),
			__( 'Comments', 'npcink-workflow-toolbox' )    => (int) ( $summary['recent_comment_sample'] ?? 0 ),
			__( 'Findings', 'npcink-workflow-toolbox' )    => (int) ( $summary['top_finding_count'] ?? count( $findings ) ),
		);
		$scope_max = max( 1, ...array_values( $scope_counts ) );
		?>
		<div class="npcink-toolbox__ops-chart-grid" aria-label="<?php esc_attr_e( 'Full-site Insights charts', 'npcink-workflow-toolbox' ); ?>">
			<section class="npcink-toolbox__ops-chart">
				<h4><?php esc_html_e( 'Priority distribution', 'npcink-workflow-toolbox' ); ?></h4>
				<div class="npcink-toolbox__ops-bar-chart" aria-label="<?php esc_attr_e( 'Findings by priority', 'npcink-workflow-toolbox' ); ?>">
					<?php foreach ( $priority_counts as $bucket => $count ) : ?>
						<?php $height = (int) round( 100 * $count / $priority_max ); ?>
						<div class="npcink-toolbox__ops-bar" style="<?php echo esc_attr( '--bar-height:' . $height . '%;' ); ?>">
							<span><?php echo esc_html( (string) $count ); ?></span>
							<em><?php echo esc_html( $this->site_ops_priority_bucket_label( (string) $bucket ) ); ?></em>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="npcink-toolbox__ops-chart">
				<h4><?php esc_html_e( 'Review path mix', 'npcink-workflow-toolbox' ); ?></h4>
				<div class="npcink-toolbox__ops-donut-row">
					<span class="npcink-toolbox__ops-donut" style="<?php echo esc_attr( '--core-deg:' . $core_degrees . 'deg;--manual-deg:' . $manual_degrees . 'deg;' ); ?>"></span>
					<ul class="npcink-toolbox__ops-legend">
						<?php /* translators: %d: number of findings that are Core planning candidates. */ ?>
						<li><span class="is-core"></span><?php printf( esc_html__( '%d Core planning', 'npcink-workflow-toolbox' ), (int) $core_count ); ?></li>
						<?php /* translators: %d: number of findings that require manual review. */ ?>
						<li><span class="is-manual"></span><?php printf( esc_html__( '%d manual review', 'npcink-workflow-toolbox' ), (int) $manual_count ); ?></li>
						<?php /* translators: %d: number of suggestion-only findings. */ ?>
						<li><span class="is-suggestion"></span><?php printf( esc_html__( '%d suggestion only', 'npcink-workflow-toolbox' ), (int) $suggestion_count ); ?></li>
					</ul>
				</div>
			</section>
			<section class="npcink-toolbox__ops-chart">
				<h4><?php esc_html_e( 'Scan scope', 'npcink-workflow-toolbox' ); ?></h4>
				<div class="npcink-toolbox__ops-scope-bars">
					<?php foreach ( $scope_counts as $label => $count ) : ?>
						<?php $width = (int) round( 100 * $count / $scope_max ); ?>
						<div class="npcink-toolbox__ops-scope-bar" style="<?php echo esc_attr( '--bar-width:' . $width . '%;' ); ?>">
							<strong><?php echo esc_html( (string) $label ); ?></strong>
							<span><i></i></span>
							<em><?php echo esc_html( (string) $count ); ?></em>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $summary Summary payload.
	 * @param array<int,mixed>    $findings Findings.
	 * @param array<int,string>   $categories Finding categories.
	 */
	private function render_site_ops_dimension_panel( string $title, string $description, array $summary, array $findings, array $categories ): void {
		$dimension_findings = $this->site_ops_findings_by_category( $findings, $categories );
		?>
		<div class="npcink-toolbox__ops-summary-bar">
			<div>
				<strong><?php echo esc_html( $title ); ?></strong>
				<span><?php echo esc_html( $description ); ?></span>
			</div>
			<div class="npcink-toolbox__ops-scope">
				<?php /* translators: %d: number of scanned posts and pages. */ ?>
				<span><?php printf( esc_html__( '%d posts/pages', 'npcink-workflow-toolbox' ), (int) ( $summary['scanned_posts'] ?? 0 ) ); ?></span>
				<?php /* translators: %d: number of scanned media items. */ ?>
				<span><?php printf( esc_html__( '%d media', 'npcink-workflow-toolbox' ), (int) ( $summary['scanned_media'] ?? 0 ) ); ?></span>
				<?php /* translators: %d: number of sampled comments. */ ?>
				<span><?php printf( esc_html__( '%d comments', 'npcink-workflow-toolbox' ), (int) ( $summary['recent_comment_sample'] ?? 0 ) ); ?></span>
				<?php /* translators: %d: number of findings related to this analysis area. */ ?>
				<span><?php printf( esc_html__( '%d related findings', 'npcink-workflow-toolbox' ), (int) count( $dimension_findings ) ); ?></span>
			</div>
		</div>
		<?php if ( array() === $dimension_findings ) : ?>
			<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority findings were produced for this analysis area from the current bounded sample.', 'npcink-workflow-toolbox' ); ?></div>
		<?php else : ?>
			<div class="npcink-toolbox__ops-priority-list">
				<?php foreach ( $dimension_findings as $finding ) : ?>
					<?php $this->render_site_ops_finding_row( $finding ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param mixed $finding Finding payload.
	 */
	private function render_site_ops_finding_row( $finding ): void {
		if ( ! is_array( $finding ) ) {
			return;
		}
		$title   = $this->site_ops_finding_title( $finding );
		$summary = $this->site_ops_finding_evidence_summary( $finding );
		$action  = $this->site_ops_finding_recommended_action( $finding );
		?>
		<article class="npcink-toolbox__ops-priority-row">
			<div class="npcink-toolbox__ops-priority-main">
				<span class="npcink-toolbox__priority-label"><?php echo esc_html( $this->site_ops_priority_label( (int) ( $finding['priority_score'] ?? 0 ) ) ); ?></span>
				<div>
					<h3><?php echo esc_html( $title ); ?></h3>
					<p><?php echo esc_html( $summary ); ?></p>
				</div>
				<span class="npcink-toolbox__priority-score"><?php echo esc_html( (string) (int) ( $finding['priority_score'] ?? 0 ) ); ?></span>
			</div>
			<div class="npcink-toolbox__ops-action-line">
				<strong><?php esc_html_e( 'Next', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php echo esc_html( $action ); ?></span>
				<em><?php echo esc_html( $this->site_ops_boundary_label( (string) ( $finding['write_boundary'] ?? 'suggestion_only' ) ) ); ?></em>
				<span><?php echo esc_html( $this->site_ops_boundary_guidance( (string) ( $finding['write_boundary'] ?? 'suggestion_only' ) ) ); ?></span>
			</div>
		</article>
		<?php
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function render_site_ops_evidence_panel( array $findings ): void {
		$rendered = false;
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$source_refs = isset( $finding['source_refs'] ) && is_array( $finding['source_refs'] ) ? $finding['source_refs'] : array();
			if ( '' === (string) ( $finding['impact'] ?? '' ) && array() === $source_refs ) {
				continue;
			}
			$rendered = true;
			$title    = $this->site_ops_finding_title( $finding, __( 'Impact and evidence', 'npcink-workflow-toolbox' ) );
			$impact   = $this->site_ops_finding_impact( $finding );
			?>
			<details class="npcink-toolbox__result-details">
				<summary><?php echo esc_html( $title ); ?></summary>
				<p><?php echo esc_html( $impact ); ?></p>
				<?php if ( array() !== $source_refs ) : ?>
					<ul class="npcink-toolbox__usage-list">
						<?php foreach ( $source_refs as $ref ) : ?>
							<?php if ( ! is_array( $ref ) ) { continue; } ?>
							<li>
								<strong><?php echo esc_html( (string) ( $ref['title'] ?? __( 'Untitled item', 'npcink-workflow-toolbox' ) ) ); ?></strong>
								<span><?php echo esc_html( (string) ( $ref['object_type'] ?? 'post' ) . ' #' . (string) (int) ( $ref['object_id'] ?? 0 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</details>
			<?php
		}
		if ( ! $rendered ) {
			?>
			<div class="npcink-toolbox__result-notice"><?php esc_html_e( 'No evidence references were returned for this local preview.', 'npcink-workflow-toolbox' ); ?></div>
			<?php
		}
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function count_site_ops_findings_by_boundary( array $findings, string $boundary ): int {
		$count = 0;
		foreach ( $findings as $finding ) {
			if ( is_array( $finding ) && $boundary === (string) ( $finding['write_boundary'] ?? '' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param array<int,mixed>  $findings Findings.
	 * @param array<int,string> $categories Finding categories.
	 * @return array<int,array<string,mixed>>
	 */
	private function site_ops_findings_by_category( array $findings, array $categories ): array {
		$category_map = array_fill_keys( $categories, true );
		$matches      = array();
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$category = (string) ( $finding['issue_type'] ?? $finding['category'] ?? '' );
			if ( isset( $category_map[ $category ] ) ) {
				$matches[] = $finding;
			}
		}
		return $matches;
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function site_ops_analysis_focus_label( array $findings ): string {
		$priority_titles = $this->site_ops_priority_titles( $findings, 1 );
		if ( array() !== $priority_titles ) {
			return $priority_titles[0];
		}
		return __( 'the current evidence sample', 'npcink-workflow-toolbox' );
	}

	private function site_ops_local_report_status( int $finding_count, int $high_count ): string {
		if ( 0 === $finding_count ) {
			return __( 'No priority findings', 'npcink-workflow-toolbox' );
		}
		if ( $high_count > 0 ) {
			return __( 'Needs focused review', 'npcink-workflow-toolbox' );
		}
		return __( 'Review when planning', 'npcink-workflow-toolbox' );
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function count_site_ops_findings_by_priority( array $findings, int $min, int $max ): int {
		$count = 0;
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$score = (int) ( $finding['priority_score'] ?? 0 );
			if ( $score >= $min && $score < $max ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 * @return array<int,string>
	 */
	private function site_ops_priority_titles( array $findings, int $limit ): array {
		$titles = array();
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$title = trim( $this->site_ops_finding_title( $finding, '' ) );
			if ( '' !== $title ) {
				$titles[] = $title;
			}
			if ( count( $titles ) >= $limit ) {
				break;
			}
		}
		return $titles;
	}

	private function site_ops_priority_label( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'High', 'npcink-workflow-toolbox' );
		}
		if ( $score >= 75 ) {
			return __( 'Medium', 'npcink-workflow-toolbox' );
		}
		return __( 'Review', 'npcink-workflow-toolbox' );
	}

	private function site_ops_priority_action_label( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'High priority', 'npcink-workflow-toolbox' );
		}
		if ( $score >= 75 ) {
			return __( 'Medium priority', 'npcink-workflow-toolbox' );
		}
		return __( 'Needs review', 'npcink-workflow-toolbox' );
	}

	private function site_ops_priority_bucket_label( string $bucket ): string {
		if ( 'high' === $bucket ) {
			return __( 'High', 'npcink-workflow-toolbox' );
		}
		if ( 'medium' === $bucket ) {
			return __( 'Medium', 'npcink-workflow-toolbox' );
		}
		return __( 'Review', 'npcink-workflow-toolbox' );
	}

	private function site_ops_boundary_label( string $boundary ): string {
		if ( 'core_handoff_candidate' === $boundary ) {
			return __( 'Needs review workflow', 'npcink-workflow-toolbox' );
		}
		if ( 'manual_review_only' === $boundary ) {
			return __( 'Manual check only', 'npcink-workflow-toolbox' );
		}
		return __( 'Advice only', 'npcink-workflow-toolbox' );
	}

	private function site_ops_boundary_guidance( string $boundary ): string {
		if ( 'core_handoff_candidate' === $boundary ) {
			return __( 'Changes must be reviewed before anything is written. This report only recommends.', 'npcink-workflow-toolbox' );
		}
		if ( 'manual_review_only' === $boundary ) {
			return __( 'Use this as a checklist. Nothing changes automatically.', 'npcink-workflow-toolbox' );
		}
		if ( 'blocked_until_cloud_ready' === $boundary ) {
			return __( 'Connect Cloud before expecting deeper AI ranking or trend detail.', 'npcink-workflow-toolbox' );
		}
		return __( 'Use this as decision support only. No WordPress data changes here.', 'npcink-workflow-toolbox' );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_id( array $finding ): string {
		$id = (string) ( $finding['id'] ?? $finding['finding_id'] ?? '' );
		return sanitize_key( $id );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_title( array $finding, string $fallback = '' ): string {
		$id     = $this->site_ops_finding_id( $finding );
		$titles = array(
			'stale_content_backlog'             => __( 'Old content refresh backlog', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'     => __( 'Content depth and internal-link gaps', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'           => __( 'Post metadata review backlog', 'npcink-workflow-toolbox' ),
			'comment_signal_review'             => __( 'Comment signal review', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'               => __( 'Media metadata review backlog', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'          => __( 'Taxonomy structure review', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'           => __( 'Site Context brief is incomplete', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable'  => __( 'Cloud Site Knowledge unavailable', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $titles[ $id ] ) ) {
			return $titles[ $id ];
		}

		$title = trim( (string) ( $finding['title'] ?? $finding['finding_id'] ?? $finding['id'] ?? '' ) );
		if ( '' !== $title ) {
			return $this->site_ops_dynamic_label( $title );
		}

		return '' !== $fallback ? $fallback : __( 'Site analysis finding', 'npcink-workflow-toolbox' );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_evidence_summary( array $finding ): string {
		$id      = $this->site_ops_finding_id( $finding );
		$summary = trim( (string) ( $finding['evidence_summary'] ?? '' ) );
		if ( '' === $summary ) {
			return '';
		}

		if ( 'stale_content_backlog' === $id && preg_match( '/(\d+)\s+sampled public posts or pages have not been modified for 180\+ days;\s+(\d+)\s+still have approved comments\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: stale item count, 2: stale item count with comments. */
				__( 'The current scan found %1$d posts/pages older than 180 days; %2$d still have approved comments.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}
		if ( 'content_depth_and_linking_gap' === $id && preg_match( '/(\d+)\s+sampled items are short;\s+(\d+)\s+have no recorded internal links\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: thin item count, 2: missing internal link count. */
				__( 'The current scan found %1$d short items; %2$d have no recorded internal links.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}
		if ( 'metadata_review_backlog' === $id && preg_match( '/(\d+)\s+sampled items need excerpt or meta-description review;\s+(\d+)\s+need category or tag review\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: missing meta count, 2: missing taxonomy count. */
				__( 'The current scan found %1$d items needing excerpt or meta-description review; %2$d need category or tag review.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}
		if ( 'comment_signal_review' === $id && preg_match( '/The approved comment sample includes\s+(\d+)\s+question-like comments and\s+(\d+)\s+longer comments;\s+(\d+)\s+comments are pending moderation\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: question-like comments, 2: long comments, 3: pending comments. */
				__( 'The approved comment sample includes %1$d question-like comments and %2$d longer comments; %3$d comments are pending moderation.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2],
				(int) $matches[3]
			);
		}
		if ( 'media_metadata_debt' === $id && preg_match( '/(\d+)\s+sampled attachments lack ALT text,\s+(\d+)\s+lack captions,\s+and sampled posts reference\s+(\d+)\s+image ALT gaps\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: missing attachment alt count, 2: missing caption count, 3: referenced image alt gaps. */
				__( 'The current scan found %1$d media items without ALT text, %2$d without captions, and %3$d referenced image ALT gaps.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2],
				(int) $matches[3]
			);
		}
		if ( 'taxonomy_structure_drift' === $id && preg_match( '/(\d+)\s+category\/tag terms are empty and\s+(\d+)\s+are used once in the sampled taxonomy summary\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: empty term count, 2: low-use term count. */
				__( 'The current scan found %1$d empty category/tag terms and %2$d terms used only once.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}

		return $this->site_ops_dynamic_label( $summary );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_impact( array $finding ): string {
		$id       = $this->site_ops_finding_id( $finding );
		$impacts  = array(
			'stale_content_backlog'             => __( 'Older but still active content can reduce reader trust and search freshness.', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'     => __( 'Thin pages and missing internal paths make it harder for readers and AI systems to understand the site map.', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'           => __( 'Weak metadata reduces snippet quality and makes suggestion workflows less grounded.', 'npcink-workflow-toolbox' ),
			'comment_signal_review'             => __( 'Comment patterns can reveal missing FAQ, troubleshooting, or follow-up content needs.', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'               => __( 'Image metadata affects accessibility, editorial reuse, and media search quality.', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'          => __( 'Sparse vocabulary can fragment content discovery and weaken recommendation quality.', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'           => __( 'Weak site context makes downstream SEO/AEO/GEO and content support suggestions less consistent.', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable'  => __( 'Without Cloud, recommendations stay local and cannot use semantic related-content evidence.', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $impacts[ $id ] ) ) {
			return $impacts[ $id ];
		}

		return $this->site_ops_dynamic_label( (string) ( $finding['impact'] ?? '' ) );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_recommended_action( array $finding ): string {
		$id      = $this->site_ops_finding_id( $finding );
		$actions = array(
			'stale_content_backlog'             => __( 'Open the oldest active items first, then write refresh notes before choosing any review workflow.', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'     => __( 'Prioritize internal-link review and content-depth review before creating new articles on the same topics.', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'           => __( 'Review one post at a time in the editor, then send accepted values through the review workflow.', 'npcink-workflow-toolbox' ),
			'comment_signal_review'             => __( 'Review high-signal public comments manually; convert repeated needs into FAQ or article-refresh notes.', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'               => __( 'Start with a media ALT/caption review set; do not update media metadata until a governed path is selected.', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'          => __( 'Review taxonomy consolidation separately; do not create, merge, or assign terms from this panel.', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'           => __( 'Fill the Site Context brief before relying on repeated AI recommendations.', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable'  => __( 'Connect or verify Cloud Addon before expecting deeper semantic analysis.', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $actions[ $id ] ) ) {
			return $actions[ $id ];
		}

		return $this->site_ops_dynamic_label( (string) ( $finding['recommended_action'] ?? '' ) );
	}

	private function site_ops_dynamic_label( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^Cloud runtime\/detail ranked (\d+) findings across (.+); review the first item before expanding work\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: finding count, 2: dimension list. */
				__( 'Cloud runtime/detail ranked %1$d findings across %2$s; review the first item before expanding work.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				$this->site_ops_dynamic_dimension_list_label( (string) $matches[2] )
			);
		}
		if ( preg_match( '/^([a-z_]+) has (\d+) ranked finding\(s\) in the current request\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: dimension label, 2: finding count. */
				__( '%1$s has %2$d ranked findings in the current request.', 'npcink-workflow-toolbox' ),
				$this->site_ops_dynamic_label( (string) $matches[1] ),
				(int) $matches[2]
			);
		}
		if ( preg_match( '/^([a-z_]+) has aggregate signals but no ranked local finding\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: dimension label. */
				__( '%s has aggregate signals but no ranked local finding.', 'npcink-workflow-toolbox' ),
				$this->site_ops_dynamic_label( (string) $matches[1] )
			);
		}
		if ( preg_match( '/^([a-z_]+) has no priority signal in the current aggregate sample\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: dimension label. */
				__( '%s has no priority signal in the current aggregate sample.', 'npcink-workflow-toolbox' ),
				$this->site_ops_dynamic_label( (string) $matches[1] )
			);
		}

		$labels = array(
			'stale_content_backlog'                                                                                 => __( 'Old content refresh backlog', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'                                                                         => __( 'Content depth and internal-link gaps', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'                                                                               => __( 'Post metadata review backlog', 'npcink-workflow-toolbox' ),
			'comment_signal_review'                                                                                 => __( 'Comment signal review', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'                                                                                   => __( 'Media metadata review backlog', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'                                                                              => __( 'Taxonomy structure review', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'                                                                               => __( 'Site Context brief is incomplete', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable'                                                                      => __( 'Cloud Site Knowledge unavailable', 'npcink-workflow-toolbox' ),
			'cloud_semantic_analysis'                                                                               => __( 'Cloud semantic analysis', 'npcink-workflow-toolbox' ),
			'cloud_runtime_unavailable'                                                                             => __( 'Cloud runtime is unavailable', 'npcink-workflow-toolbox' ),
			'connect_or_verify_cloud_addon'                                                                         => __( 'Connect or verify Cloud Addon', 'npcink-workflow-toolbox' ),
			'content'                                                                                               => __( 'Content', 'npcink-workflow-toolbox' ),
			'media'                                                                                                 => __( 'Media', 'npcink-workflow-toolbox' ),
			'comments'                                                                                              => __( 'Comments', 'npcink-workflow-toolbox' ),
			'structure'                                                                                             => __( 'Structure', 'npcink-workflow-toolbox' ),
			'high'                                                                                                  => __( 'High', 'npcink-workflow-toolbox' ),
			'medium'                                                                                                => __( 'Medium', 'npcink-workflow-toolbox' ),
			'low'                                                                                                   => __( 'Low', 'npcink-workflow-toolbox' ),
			'review'                                                                                                => __( 'Review', 'npcink-workflow-toolbox' ),
			'runtime_detail'                                                                                        => __( 'Runtime/detail', 'npcink-workflow-toolbox' ),
			'collect_stronger_site_context'                                                                         => __( 'Collect stronger Site Context', 'npcink-workflow-toolbox' ),
			'blocked_until_operator_review'                                                                         => __( 'Blocked until operator review', 'npcink-workflow-toolbox' ),
			'ready_for_operator_prioritization'                                                                     => __( 'Ready for operator prioritization', 'npcink-workflow-toolbox' ),
			'no_priority_findings'                                                                                  => __( 'No priority findings', 'npcink-workflow-toolbox' ),
			'clear_blocked_items_then_repeat_cloud_analysis'                                                        => __( 'Clear blocked items, then repeat Cloud analysis', 'npcink-workflow-toolbox' ),
			'review_top_ranked_finding_then_choose_manual_or_core_handoff'                                          => __( 'Review the top ranked finding, then choose manual review or Core handoff', 'npcink-workflow-toolbox' ),
			'keep_as_current_snapshot_or_refresh_after_site_changes'                                                => __( 'Keep this as the current snapshot, or refresh after site changes', 'npcink-workflow-toolbox' ),
			'content_quality_and_discoverability'                                                                   => __( 'Content quality and discoverability', 'npcink-workflow-toolbox' ),
			'media_accessibility_and_reuse'                                                                         => __( 'Media accessibility and reuse', 'npcink-workflow-toolbox' ),
			'audience_demand_signal'                                                                                => __( 'Audience demand signal', 'npcink-workflow-toolbox' ),
			'site_structure_and_context'                                                                            => __( 'Site structure and context', 'npcink-workflow-toolbox' ),
			'general_site_review'                                                                                   => __( 'General site review', 'npcink-workflow-toolbox' ),
			'content_refresh_trend'                                                                                 => __( 'Content refresh trend', 'npcink-workflow-toolbox' ),
			'comment_question_trend'                                                                                => __( 'Comment question trend', 'npcink-workflow-toolbox' ),
			'media_metadata_trend'                                                                                  => __( 'Media metadata trend', 'npcink-workflow-toolbox' ),
			'taxonomy_drift_trend'                                                                                  => __( 'Taxonomy drift trend', 'npcink-workflow-toolbox' ),
			'insufficient_signal'                                                                                   => __( 'Insufficient signal', 'npcink-workflow-toolbox' ),
			'compare_stale_items_with_recent_comment_activity'                                                      => __( 'Compare stale items with recent comment activity', 'npcink-workflow-toolbox' ),
			'group_repeated_comment_questions_without_raw_text'                                                     => __( 'Group repeated comment questions without raw text', 'npcink-workflow-toolbox' ),
			'sample_media_alt_and_caption_review_set'                                                               => __( 'Sample a media ALT and caption review set', 'npcink-workflow-toolbox' ),
			'review_empty_and_low_use_terms'                                                                        => __( 'Review empty and low-use terms', 'npcink-workflow-toolbox' ),
			'complete_site_context_and_repeat_local_preview'                                                        => __( 'Complete Site Context and repeat the local preview', 'npcink-workflow-toolbox' ),
			'repeat_cloud_detail_after_next_local_scan'                                                             => __( 'Repeat Cloud detail after the next local scan', 'npcink-workflow-toolbox' ),
			'Old content needs a refresh queue'                                                                     => __( 'Old content refresh backlog', 'npcink-workflow-toolbox' ),
			'Some content lacks depth or internal paths'                                                            => __( 'Content depth and internal-link gaps', 'npcink-workflow-toolbox' ),
			'Metadata review backlog is visible'                                                                    => __( 'Post metadata review backlog', 'npcink-workflow-toolbox' ),
			'Comments contain support and follow-up signals'                                                        => __( 'Comment signal review', 'npcink-workflow-toolbox' ),
			'Media metadata needs review'                                                                           => __( 'Media metadata review backlog', 'npcink-workflow-toolbox' ),
			'Taxonomy structure may need cleanup'                                                                   => __( 'Taxonomy structure review', 'npcink-workflow-toolbox' ),
			'Site Context needs a stronger brief'                                                                   => __( 'Site Context brief is incomplete', 'npcink-workflow-toolbox' ),
			'Cloud Site Knowledge is not available'                                                                 => __( 'Cloud Site Knowledge unavailable', 'npcink-workflow-toolbox' ),
			'Review blockers before turning findings into an action plan.'                                          => __( 'Review blockers before turning findings into an action plan.', 'npcink-workflow-toolbox' ),
			'Prioritize the strongest full-site signals before creating new work.'                                  => __( 'Prioritize the strongest full-site signals before creating new work.', 'npcink-workflow-toolbox' ),
			'No priority full-site findings were detected in the current aggregate sample.'                         => __( 'No priority full-site findings were detected in the current aggregate sample.', 'npcink-workflow-toolbox' ),
			'The analysis found prerequisites that should be cleared before repeated review.'                       => __( 'The analysis found prerequisites that should be cleared before repeated review.', 'npcink-workflow-toolbox' ),
			'The current aggregate sample is reviewable, but it did not produce a priority queue.'                  => __( 'The current aggregate sample is reviewable, but it did not produce a priority queue.', 'npcink-workflow-toolbox' ),
			'Media metadata affects accessibility, reuse, and evidence quality.'                                    => __( 'Media metadata affects accessibility, reuse, and evidence quality.', 'npcink-workflow-toolbox' ),
			'Approved comment signals can reveal unanswered audience needs.'                                        => __( 'Approved comment signals can reveal unanswered audience needs.', 'npcink-workflow-toolbox' ),
			'Older active content should be refreshed before expanding similar work.'                               => __( 'Older active content should be refreshed before expanding similar work.', 'npcink-workflow-toolbox' ),
			'This finding is ranked from aggregate local evidence and operator review value.'                       => __( 'This finding is ranked from aggregate local evidence and operator review value.', 'npcink-workflow-toolbox' ),
			'Refresh planning should start with active stale pages.'                                                => __( 'Refresh planning should start with active stale pages.', 'npcink-workflow-toolbox' ),
			'Repeated questions can become FAQ or article-refresh work.'                                            => __( 'Repeated questions can become FAQ or article-refresh work.', 'npcink-workflow-toolbox' ),
			'Accessibility and media search quality may be weaker.'                                                 => __( 'Accessibility and media search quality may be weaker.', 'npcink-workflow-toolbox' ),
			'Sparse vocabulary can fragment discovery and recommendations.'                                         => __( 'Sparse vocabulary can fragment discovery and recommendations.', 'npcink-workflow-toolbox' ),
			'Review the aggregate signal before creating new work.'                                                 => __( 'Review the aggregate signal before creating new work.', 'npcink-workflow-toolbox' ),
			'No aggregate signal was strong enough for trend explanation.'                                          => __( 'No aggregate signal was strong enough for trend explanation.', 'npcink-workflow-toolbox' ),
			'Run the local scan after more public content evidence is available.'                                   => __( 'Run the local scan after more public content evidence is available.', 'npcink-workflow-toolbox' ),
			'Review the oldest active items first, then prepare refresh notes or a Core-governed update plan.'       => __( 'Review the oldest active items first, then prepare refresh notes or a Core-governed update plan.', 'npcink-workflow-toolbox' ),
			'Start with a media ALT/caption review and make metadata visible before broader adoption.'              => __( 'Start with a media ALT/caption review set; do not update media metadata until a governed path is selected.', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $labels[ $value ] ) ) {
			return $labels[ $value ];
		}

		$key = sanitize_key( $value );
		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}

		return $value;
	}

	private function site_ops_dynamic_dimension_list_label( string $value ): string {
		$parts  = preg_split( '/\s*,\s*/', trim( $value ) );
		$labels = array();
		foreach ( is_array( $parts ) ? $parts : array() as $part ) {
			$label = $this->site_ops_dynamic_label( (string) $part );
			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}
		return implode( ' / ', $labels );
	}

	/**
	 * @param mixed $cloud_analysis Cloud analysis result or WP_Error.
	 */
	private function render_site_ops_cloud_analysis_result( $cloud_analysis ): void {
		if ( null === $cloud_analysis ) {
			return;
		}
		if ( is_wp_error( $cloud_analysis ) ) {
			?>
			<div class="npcink-toolbox__result-notice is-warning"><?php echo esc_html( $cloud_analysis->get_error_message() ); ?></div>
			<?php
			return;
		}
		if ( ! is_array( $cloud_analysis ) ) {
			return;
		}

		$result             = is_array( $cloud_analysis['result'] ?? null ) ? $cloud_analysis['result'] : array();
		$executive_summary  = is_array( $result['executive_summary'] ?? null ) ? $result['executive_summary'] : array();
		$priority_queue     = is_array( $result['priority_queue'] ?? null ) ? array_slice( $result['priority_queue'], 0, 5 ) : array();
		$dimension_summaries = is_array( $result['dimension_summaries'] ?? null ) ? array_slice( $result['dimension_summaries'], 0, 4 ) : array();
		$semantic_ranked_findings = is_array( $result['semantic_ranked_findings'] ?? null ) ? array_slice( $result['semantic_ranked_findings'], 0, 5 ) : array();
		$trend_notes        = is_array( $result['trend_notes'] ?? null ) ? array_slice( $result['trend_notes'], 0, 5 ) : array();
		$trend_explanations = is_array( $result['trend_explanations'] ?? null ) ? array_slice( $result['trend_explanations'], 0, 5 ) : array();
		$analysis_closure   = is_array( $result['analysis_closure'] ?? null ) ? $result['analysis_closure'] : array();
		$blocked_items      = is_array( $result['blocked_items'] ?? null ) ? array_slice( $result['blocked_items'], 0, 5 ) : array();
		$next_actions       = is_array( $result['operator_next_actions'] ?? null ) ? array_slice( $result['operator_next_actions'], 0, 5 ) : array();
		$handoff_candidates = is_array( $result['core_handoff_candidates'] ?? null ) ? array_slice( $result['core_handoff_candidates'], 0, 5 ) : array();
		$confidence         = is_array( $result['confidence'] ?? null ) ? $result['confidence'] : array();
		$cloud_run          = is_array( $cloud_analysis['cloud_run'] ?? null ) ? $cloud_analysis['cloud_run'] : array();
		$cloud_error        = is_array( $cloud_analysis['cloud_error'] ?? null ) ? $cloud_analysis['cloud_error'] : array();
		$status             = sanitize_key( (string) ( $cloud_run['status'] ?? $cloud_analysis['status'] ?? 'submitted' ) );
		$error_code         = sanitize_key( (string) ( $cloud_error['error_code'] ?? '' ) );
		$error_message      = (string) ( $cloud_error['error_message'] ?? '' );
		$confidence_level   = sanitize_key( (string) ( $confidence['level'] ?? '' ) );
		$is_failed          = in_array( $status, array( 'failed', 'error' ), true ) || '' !== $error_code;
		$cloud_focus        = array();
		foreach ( $priority_queue as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = $this->site_ops_finding_title( $item, '' );
			if ( '' !== $label ) {
				$cloud_focus[] = $label;
			}
			if ( count( $cloud_focus ) >= 3 ) {
				break;
			}
		}
		?>
		<section class="npcink-toolbox__card npcink-toolbox__insight-cloud-result">
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Cloud analysis result', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Cloud adds runtime/detail ranking only. Review results locally before any Core handoff.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
				<span class="npcink-toolbox__pill"><?php echo esc_html( '' !== $status ? $status : 'submitted' ); ?></span>
			</div>

			<div class="npcink-toolbox__ops-summary-bar">
				<div>
					<?php /* translators: %d: number of Cloud-ranked priority items. */ ?>
					<strong><?php printf( esc_html__( 'Cloud returned %d priority items', 'npcink-workflow-toolbox' ), (int) count( $priority_queue ) ); ?></strong>
					<span><?php esc_html_e( 'Runtime/detail output is review guidance only and does not create Core proposals or WordPress writes.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
				<div class="npcink-toolbox__ops-scope">
					<?php /* translators: %s: Cloud runtime run identifier, or not returned. */ ?>
					<span><?php printf( esc_html__( 'Run: %s', 'npcink-workflow-toolbox' ), esc_html( (string) ( $cloud_run['run_id'] ?? __( 'not returned', 'npcink-workflow-toolbox' ) ) ) ); ?></span>
					<?php /* translators: %s: Cloud runtime confidence level, or not reported. */ ?>
					<span><?php printf( esc_html__( 'Confidence: %s', 'npcink-workflow-toolbox' ), esc_html( '' !== $confidence_level ? $confidence_level : __( 'not reported', 'npcink-workflow-toolbox' ) ) ); ?></span>
					<span><?php esc_html_e( 'Review: local operator required', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			</div>
			<?php if ( array() !== $executive_summary ) : ?>
				<div class="npcink-toolbox__ops-summary-bar" aria-label="<?php esc_attr_e( 'Cloud executive summary', 'npcink-workflow-toolbox' ); ?>">
					<div>
						<strong><?php esc_html_e( 'Cloud executive summary', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $executive_summary['headline'] ?? __( 'Cloud analysis is ready for operator review.', 'npcink-workflow-toolbox' ) ) ) ); ?></span>
						<?php if ( '' !== (string) ( $executive_summary['summary'] ?? '' ) ) : ?>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) $executive_summary['summary'] ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="npcink-toolbox__ops-scope">
						<?php if ( '' !== (string) ( $executive_summary['primary_focus'] ?? '' ) ) : ?>
							<?php /* translators: %s: Cloud-reported primary focus label. */ ?>
							<span><?php printf( esc_html__( 'Focus: %s', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) $executive_summary['primary_focus'] ) ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== (string) ( $analysis_closure['loop_status'] ?? '' ) ) : ?>
							<?php /* translators: %s: Cloud-reported analysis loop status. */ ?>
							<span><?php printf( esc_html__( 'Loop: %s', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) $analysis_closure['loop_status'] ) ) ); ?></span>
						<?php endif; ?>
						<span><?php esc_html_e( 'Cloud role: runtime/detail', 'npcink-workflow-toolbox' ); ?></span>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $dimension_summaries ) : ?>
				<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud dimension summaries', 'npcink-workflow-toolbox' ); ?>">
					<?php foreach ( $dimension_summaries as $dimension ) : ?>
						<?php if ( ! is_array( $dimension ) ) { continue; } ?>
						<div>
							<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $dimension['dimension'] ?? __( 'Analysis area', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
							<?php /* translators: 1: Cloud-reported priority label, 2: number of findings in this analysis dimension. */ ?>
							<span><?php printf( esc_html__( '%1$s priority, %2$d findings', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) ( $dimension['priority'] ?? 'review' ) ) ), (int) ( $dimension['finding_count'] ?? 0 ) ); ?></span>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $dimension['summary'] ?? '' ) ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $is_failed ) : ?>
				<div class="npcink-toolbox__result-notice is-error">
					<strong><?php esc_html_e( 'Cloud analysis failed in runtime/detail.', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( '' !== $error_code || '' !== $error_message ) : ?>
						<span><?php echo esc_html( trim( $error_code . ( '' !== $error_message ? ': ' . $error_message : '' ) ) ); ?></span>
					<?php endif; ?>
					<span><?php esc_html_e( 'Toolbox did not retry locally, create a local run table, create Core proposals, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			<?php elseif ( 'low' === $confidence_level ) : ?>
				<div class="npcink-toolbox__result-notice is-warning">
					<strong><?php esc_html_e( 'Cloud returned low-confidence detail.', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Review the blockers and Site Context before treating the result as an operations priority list.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			<?php elseif ( array() === $priority_queue && array() === $trend_notes ) : ?>
				<div class="npcink-toolbox__result-notice">
					<?php esc_html_e( 'Cloud returned no priority queue or trend notes for this bounded request.', 'npcink-workflow-toolbox' ); ?>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $blocked_items || array() !== $next_actions || array() !== $handoff_candidates ) : ?>
				<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud follow-up summary', 'npcink-workflow-toolbox' ); ?>">
					<div>
						<strong><?php esc_html_e( 'Blockers', 'npcink-workflow-toolbox' ); ?></strong>
						<span>
							<?php
							if ( array() === $blocked_items ) {
								esc_html_e( 'No Cloud blockers reported.', 'npcink-workflow-toolbox' );
							} else {
								$first_blocker = is_array( $blocked_items[0] ?? null ) ? $blocked_items[0] : array();
								printf(
									/* translators: 1: number of Cloud blockers, 2: first blocker label. */
									esc_html__( '%1$d reported; first: %2$s.', 'npcink-workflow-toolbox' ),
									(int) count( $blocked_items ),
									esc_html( $this->site_ops_dynamic_label( (string) ( $first_blocker['reason'] ?? $first_blocker['id'] ?? '' ) ) )
								);
							}
							?>
						</span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Suggested next path', 'npcink-workflow-toolbox' ); ?></strong>
						<span>
							<?php
							if ( array() === $next_actions ) {
								esc_html_e( 'Review the top priority queue item locally.', 'npcink-workflow-toolbox' );
							} else {
								$first_action = is_array( $next_actions[0] ?? null ) ? $next_actions[0] : array();
								echo esc_html( $this->site_ops_dynamic_label( (string) ( $first_action['label'] ?? $first_action['target'] ?? $first_action['id'] ?? '' ) ) );
							}
							?>
						</span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Core handoff candidates', 'npcink-workflow-toolbox' ); ?></strong>
						<span>
							<?php
							printf(
								/* translators: %d: number of Core handoff planning hints. */
								esc_html__( '%d planning hints; proposal creation remains outside this report.', 'npcink-workflow-toolbox' ),
								(int) count( $handoff_candidates )
							);
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $cloud_focus ) : ?>
				<div class="npcink-toolbox__ops-focus">
					<strong><?php esc_html_e( 'Cloud focus', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php echo esc_html( implode( ' / ', $cloud_focus ) ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $priority_queue ) : ?>
				<div class="npcink-toolbox__ops-priority-list">
					<?php foreach ( $priority_queue as $item ) : ?>
						<?php if ( ! is_array( $item ) ) { continue; } ?>
						<?php
						$title   = $this->site_ops_finding_title( $item, __( 'Cloud priority', 'npcink-workflow-toolbox' ) );
						$summary = $this->site_ops_finding_evidence_summary( $item );
						$action  = $this->site_ops_finding_recommended_action( $item );
						?>
						<article class="npcink-toolbox__ops-priority-row">
							<div class="npcink-toolbox__ops-priority-main">
								<span class="npcink-toolbox__priority-label"><?php echo esc_html( $this->site_ops_priority_label( (int) ( $item['cloud_priority_score'] ?? 0 ) ) ); ?></span>
								<div>
									<h3><?php echo esc_html( $title ); ?></h3>
									<p><?php echo esc_html( $summary ); ?></p>
								</div>
								<span class="npcink-toolbox__priority-score"><?php echo esc_html( (string) (int) ( $item['cloud_priority_score'] ?? 0 ) ); ?></span>
							</div>
							<div class="npcink-toolbox__ops-action-line">
								<strong><?php esc_html_e( 'Next', 'npcink-workflow-toolbox' ); ?></strong>
								<span><?php echo esc_html( $action ); ?></span>
								<em><?php esc_html_e( 'Cloud-ranked suggestion', 'npcink-workflow-toolbox' ); ?></em>
								<span><?php echo esc_html( $this->site_ops_boundary_guidance( (string) ( $item['write_boundary'] ?? 'suggestion_only' ) ) ); ?></span>
							</div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<?php if ( array() !== $semantic_ranked_findings ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Semantic ranking detail', 'npcink-workflow-toolbox' ); ?></summary>
					<ul class="npcink-toolbox__usage-list">
						<?php foreach ( $semantic_ranked_findings as $item ) : ?>
							<?php if ( ! is_array( $item ) ) { continue; } ?>
							<li>
								<strong><?php echo esc_html( $this->site_ops_finding_title( $item, __( 'Semantic finding', 'npcink-workflow-toolbox' ) ) ); ?></strong>
								<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['semantic_cluster'] ?? '' ) ) ); ?></span>
								<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['reason'] ?? '' ) ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $trend_explanations ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Trend explanations', 'npcink-workflow-toolbox' ); ?></summary>
					<ul class="npcink-toolbox__usage-list">
							<?php foreach ( $trend_explanations as $item ) : ?>
								<?php if ( ! is_array( $item ) ) { continue; } ?>
								<li>
									<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['id'] ?? __( 'Trend explanation', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
									<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['operator_impact'] ?? $item['summary'] ?? '' ) ) ); ?></span>
									<?php if ( '' !== (string) ( $item['next_check'] ?? '' ) ) : ?>
										<?php /* translators: %s: suggested next trend check. */ ?>
										<span><?php printf( esc_html__( 'Next check: %s', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) $item['next_check'] ) ) ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $trend_notes ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Trend notes', 'npcink-workflow-toolbox' ); ?></summary>
					<ul class="npcink-toolbox__usage-list">
						<?php foreach ( $trend_notes as $note ) : ?>
							<?php if ( ! is_array( $note ) ) { continue; } ?>
							<li>
								<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $note['id'] ?? __( 'Trend note', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
								<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $note['summary'] ?? '' ) ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $blocked_items || array() !== $next_actions || array() !== $handoff_candidates ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Blocked items, next actions, and Core handoff candidates', 'npcink-workflow-toolbox' ); ?></summary>
					<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud analysis review detail', 'npcink-workflow-toolbox' ); ?>">
						<?php if ( array() !== $blocked_items ) : ?>
							<div>
								<strong><?php esc_html_e( 'Blocked items', 'npcink-workflow-toolbox' ); ?></strong>
								<ul class="npcink-toolbox__usage-list">
									<?php foreach ( $blocked_items as $item ) : ?>
										<?php if ( ! is_array( $item ) ) { continue; } ?>
										<li>
											<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['id'] ?? __( 'Blocked item', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
											<span>
												<?php
												$reason = $this->site_ops_dynamic_label( (string) ( $item['reason'] ?? '' ) );
												$next   = isset( $item['next'] ) ? $this->site_ops_dynamic_label( (string) $item['next'] ) : '';
												echo esc_html( '' !== $next ? $reason . ' - ' . $next : $reason );
												?>
											</span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						<?php if ( array() !== $next_actions ) : ?>
							<div>
								<strong><?php esc_html_e( 'Operator next actions', 'npcink-workflow-toolbox' ); ?></strong>
								<ul class="npcink-toolbox__usage-list">
									<?php foreach ( $next_actions as $action ) : ?>
										<?php if ( ! is_array( $action ) ) { continue; } ?>
										<li>
											<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $action['id'] ?? __( 'Review action', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
											<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $action['label'] ?? $action['target'] ?? '' ) ) ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						<?php if ( array() !== $handoff_candidates ) : ?>
							<div>
								<strong><?php esc_html_e( 'Core handoff candidates', 'npcink-workflow-toolbox' ); ?></strong>
								<ul class="npcink-toolbox__usage-list">
									<?php foreach ( $handoff_candidates as $candidate ) : ?>
										<?php if ( ! is_array( $candidate ) ) { continue; } ?>
										<li>
											<strong><?php echo esc_html( $this->site_ops_finding_title( $candidate, __( 'Handoff candidate', 'npcink-workflow-toolbox' ) ) ); ?></strong>
											<span><?php esc_html_e( 'Planning hint only; proposal_ready=false and Core still owns review.', 'npcink-workflow-toolbox' ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $analysis_closure ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Analysis closure', 'npcink-workflow-toolbox' ); ?></summary>
					<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud analysis closure detail', 'npcink-workflow-toolbox' ); ?>">
						<div>
							<strong><?php esc_html_e( 'Loop status', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $analysis_closure['loop_status'] ?? '' ) ) ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( 'Next step', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $analysis_closure['next_step'] ?? '' ) ) ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( 'Boundary', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Cloud detail only; Core and WordPress writes stay local-governed.', 'npcink-workflow-toolbox' ); ?></span>
						</div>
					</div>
				</details>
			<?php endif; ?>
			<details class="npcink-toolbox__result-details">
				<summary><?php esc_html_e( 'Advanced: Copy Cloud result JSON', 'npcink-workflow-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'This result is suggestion-only and does not create Core proposals or WordPress writes.', 'npcink-workflow-toolbox' ); ?></p>
				<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( (string) wp_json_encode( $cloud_analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
			</details>
		</section>
		<?php
	}

	private function nightly_inspection_preview_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                       => self::MENU_SLUG,
					'toolbox_tab'                => 'cloud-checks',
					'toolbox_cloud_check'        => 'nightly-inspection',
					'nightly_inspection_preview' => '1',
				),
				admin_url( 'admin.php' )
			),
			'npcink_toolbox_nightly_inspection_preview'
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function nightly_inspection_preview_from_request(): ?array {
		$requested = filter_input( INPUT_GET, 'nightly_inspection_preview', FILTER_UNSAFE_RAW );
		if ( '1' !== ( is_scalar( $requested ) ? (string) $requested : '' ) ) {
			return null;
		}

		$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		$nonce = is_scalar( $nonce ) ? (string) $nonce : '';
		if ( ! wp_verify_nonce( $nonce, 'npcink_toolbox_nightly_inspection_preview' ) ) {
			return array(
				'error' => __( 'The Morning Brief preview link expired. Reload the page and try again.', 'npcink-workflow-toolbox' ),
			);
		}

		try {
			$collector = new Snapshot_Collector();
			$planner   = new Manual_Dry_Run_Planner();
			$snapshot  = $collector->collect();
			$replay    = $planner->plan( $snapshot );

			return array(
				'snapshot' => $snapshot,
				'replay'   => $replay,
			);
		} catch ( \Throwable $throwable ) {
			return array(
				'error' => __( 'Could not build the local Morning Brief preview.', 'npcink-workflow-toolbox' ),
			);
		}
	}

	/**
	 * @param array<string,mixed>|null $preview Preview payload.
	 */
	private function render_nightly_inspection_preview( ?array $preview ): void {
		if ( null === $preview ) {
			return;
		}

		if ( isset( $preview['error'] ) ) {
			?>
			<section class="npcink-toolbox__card" data-toolbox-nightly-inspection-preview>
				<h3><?php esc_html_e( 'Morning Brief preview', 'npcink-workflow-toolbox' ); ?></h3>
				<div class="npcink-toolbox__result-notice is-warning"><?php echo esc_html( (string) $preview['error'] ); ?></div>
			</section>
			<?php
			return;
		}

		$replay   = isset( $preview['replay'] ) && is_array( $preview['replay'] ) ? $preview['replay'] : array();
		$json     = (string) wp_json_encode( $replay, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$download = 'data:application/json;charset=utf-8,' . rawurlencode( $json );
		$brief    = isset( $replay['preview']['morning_brief'] ) && is_array( $replay['preview']['morning_brief'] ) ? $replay['preview']['morning_brief'] : array();
		$summary  = isset( $brief['summary'] ) && is_array( $brief['summary'] ) ? $brief['summary'] : array();
		$priority = isset( $brief['priorities'] ) && is_array( $brief['priorities'] ) ? array_slice( $brief['priorities'], 0, 6 ) : array();
		?>
		<section class="npcink-toolbox__card" data-toolbox-nightly-inspection-preview>
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Morning Brief preview', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Manual dry-run only. The preview reads local content, produces review signals, and does not schedule, call Cloud, create Core proposals, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>"><?php esc_html_e( 'Refresh preview', 'npcink-workflow-toolbox' ); ?></a>
			</div>
			<div class="npcink-toolbox__readiness-strip" aria-label="<?php esc_attr_e( 'Morning Brief preview summary', 'npcink-workflow-toolbox' ); ?>">
				<?php
				$this->render_start_status_item( __( 'Scanned posts', 'npcink-workflow-toolbox' ), 'neutral', (string) (int) ( $summary['scanned_posts'] ?? 0 ), __( 'Oldest modified public posts and pages.', 'npcink-workflow-toolbox' ) );
				$this->render_start_status_item( __( 'Scanned media', 'npcink-workflow-toolbox' ), 'neutral', (string) (int) ( $summary['scanned_media'] ?? 0 ), __( 'Recent image attachments.', 'npcink-workflow-toolbox' ) );
				$this->render_start_status_item( __( 'Review items', 'npcink-workflow-toolbox' ), (int) ( $summary['actions_total'] ?? 0 ) > 0 ? 'warning' : 'ok', (string) (int) ( $summary['actions_total'] ?? 0 ), __( 'Preview-only action candidates.', 'npcink-workflow-toolbox' ) );
				$this->render_start_status_item( __( 'Execution', 'npcink-workflow-toolbox' ), 'ok', __( 'Disabled', 'npcink-workflow-toolbox' ), __( 'No cron, worker, Cloud call, Core proposal, or write.', 'npcink-workflow-toolbox' ) );
				?>
			</div>
			<?php if ( array() === $priority ) : ?>
				<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority review items were found in this bounded preview.', 'npcink-workflow-toolbox' ); ?></div>
			<?php else : ?>
				<ul class="npcink-toolbox__usage-list">
					<?php foreach ( $priority as $item ) : ?>
						<?php
						if ( ! is_array( $item ) ) {
							continue;
						}
						$reason_codes = isset( $item['reason_codes'] ) && is_array( $item['reason_codes'] ) ? implode( ', ', array_map( 'strval', $item['reason_codes'] ) ) : '';
						?>
						<li>
							<strong><?php echo esc_html( (string) ( $item['title'] ?? __( 'Untitled item', 'npcink-workflow-toolbox' ) ) ); ?></strong>
							<span>
								<?php
								printf(
									/* translators: 1: object type, 2: object id, 3: score, 4: reason codes. */
									esc_html__( '%1$s #%2$d, score %3$d. %4$s', 'npcink-workflow-toolbox' ),
									esc_html( (string) ( $item['object_type'] ?? 'post' ) ),
									(int) ( $item['object_id'] ?? 0 ),
									(int) ( $item['score'] ?? 0 ),
									esc_html( $reason_codes )
								);
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<details class="npcink-toolbox__result-details">
				<summary><?php esc_html_e( 'Copy or download dry-run JSON', 'npcink-workflow-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'This is the read-only replay payload produced by the manual preview. It is not saved automatically and does not create scheduled work.', 'npcink-workflow-toolbox' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $download, array( 'data' ) ); ?>" download="nightly-site-inspection-dry-run.json"><?php esc_html_e( 'Download dry-run JSON', 'npcink-workflow-toolbox' ); ?></a></p>
				<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( $json ); ?></textarea>
			</details>
		</section>
		<?php
	}

	private function render_nightly_inspection_basic_settings( array $settings ): void {
		$latest_preview = Basic_WP_Cron_Dry_Run::latest_preview();
		$brief          = isset( $latest_preview['preview']['morning_brief'] ) && is_array( $latest_preview['preview']['morning_brief'] ) ? $latest_preview['preview']['morning_brief'] : array();
		$summary        = isset( $brief['summary'] ) && is_array( $brief['summary'] ) ? $brief['summary'] : array();
		$cloud_ready    = $this->settings->cloud_runtime_available();
		$pro_enabled    = ! empty( $settings['nightly_inspection_pro_enabled'] );
		$cloud_disabled = ! $cloud_ready || ! $pro_enabled;
		if ( array() === $brief && $cloud_ready && $pro_enabled ) {
			try {
				$snapshot = ( new Snapshot_Collector() )->collect(
					(int) $settings['nightly_inspection_post_limit'],
					(int) $settings['nightly_inspection_media_limit']
				);
				$brief    = ( new Morning_Brief_Builder() )->build( $snapshot );
			} catch ( \Throwable $throwable ) {
				$brief = array();
			}
		}
		$brief_json = array() !== $brief ? wp_json_encode( $brief, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
		?>
		<section class="npcink-toolbox__card" data-toolbox-nightly-inspection-basic-settings>
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Local Fallback Preview', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'WP-Cron is the WordPress-side fallback for one latest dry-run Morning Brief preview. The Pro Cloud Runtime remains the primary execution path for reliable scoring, entitlement, status, and result retention.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<form class="npcink-toolbox__settings-form" method="post" action="options.php">
				<?php settings_fields( 'npcink_toolbox' ); ?>
				<?php if ( ! empty( $settings['include_raw_responses'] ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[include_raw_responses]" value="1" />
				<?php endif; ?>
				<?php if ( ! empty( $settings['enable_image_source'] ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[enable_image_source]" value="1" />
				<?php endif; ?>
				<label class="npcink-toolbox__check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_enabled]" value="1" <?php checked( ! empty( $settings['nightly_inspection_enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Enable local WP-Cron fallback preview', 'npcink-workflow-toolbox' ); ?></span>
				</label>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Run time', 'npcink-workflow-toolbox' ); ?></span>
						<input type="time" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_time]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_time'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Post/page scan limit', 'npcink-workflow-toolbox' ); ?></span>
						<input type="number" min="1" max="50" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_post_limit]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_post_limit'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Media scan limit', 'npcink-workflow-toolbox' ); ?></span>
						<input type="number" min="1" max="50" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_media_limit]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_media_limit'] ); ?>" />
					</label>
				</div>
				<hr />
				<label class="npcink-toolbox__check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_pro_enabled]" value="1" <?php checked( $pro_enabled ); ?> />
					<span><?php esc_html_e( 'Enable Pro Cloud Runtime controls', 'npcink-workflow-toolbox' ); ?></span>
				</label>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Cloud payload', 'npcink-workflow-toolbox' ); ?></span>
						<select name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_cloud_payload_mode]">
							<option value="metadata_only" <?php selected( (string) $settings['nightly_inspection_cloud_payload_mode'], 'metadata_only' ); ?>><?php esc_html_e( 'Metadata only', 'npcink-workflow-toolbox' ); ?></option>
							<option value="excerpt" <?php selected( (string) $settings['nightly_inspection_cloud_payload_mode'], 'excerpt' ); ?>><?php esc_html_e( 'Include short excerpts', 'npcink-workflow-toolbox' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Cloud result retention days', 'npcink-workflow-toolbox' ); ?></span>
						<input type="number" min="1" max="90" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_cloud_retention_days]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_cloud_retention_days'] ); ?>" />
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Pro Cloud Runtime is review-only: Cloud may score, meter entitlement, retain results, and return details, but WordPress writes and Core proposals stay local and operator reviewed.', 'npcink-workflow-toolbox' ); ?></p>
				<?php submit_button( __( 'Save fallback preview', 'npcink-workflow-toolbox' ) ); ?>
			</form>
			<?php if ( array() !== $latest_preview ) : ?>
				<div class="npcink-toolbox__result-notice is-success">
					<?php
					printf(
						/* translators: 1: generated time, 2: action count. */
						esc_html__( 'Latest cron dry-run preview: %1$s, %2$d review items.', 'npcink-workflow-toolbox' ),
						esc_html( (string) ( $latest_preview['generated_at'] ?? '' ) ),
						(int) ( $summary['actions_total'] ?? 0 )
					);
					?>
				</div>
			<?php else : ?>
				<div class="npcink-toolbox__result-notice is-neutral"><?php esc_html_e( 'No cron dry-run preview has been generated yet.', 'npcink-workflow-toolbox' ); ?></div>
			<?php endif; ?>
			<form class="npcink-toolbox__inline-form npcink-toolbox__batch-panel" data-toolbox-nightly-cloud-batch data-toolbox-nightly-cloud-ready="<?php echo esc_attr( $cloud_ready ? '1' : '0' ); ?>" data-toolbox-nightly-cloud-enabled="<?php echo esc_attr( $cloud_disabled ? '0' : '1' ); ?>" data-toolbox-nightly-local-brief="<?php echo esc_attr( $brief_json ); ?>">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Pro Cloud Runtime', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Run a Cloud-scored site inspection and merge review-only findings into the Morning Brief preview. Cloud owns entitlement, usage, queue, retry, and retention detail; no local job queue or write path is created.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
				</div>
				<?php if ( ! $cloud_ready ) : ?>
					<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Cloud runtime is not configured, so Pro Cloud Runtime controls are disabled.', 'npcink-workflow-toolbox' ); ?></div>
				<?php elseif ( ! $pro_enabled ) : ?>
					<div class="npcink-toolbox__result-notice is-neutral"><?php esc_html_e( 'Enable Pro Cloud Runtime controls and save settings before submitting a Cloud run.', 'npcink-workflow-toolbox' ); ?></div>
				<?php endif; ?>
				<div class="npcink-toolbox__inline-actions">
					<button type="submit" class="button button-primary" data-toolbox-nightly-cloud-submit <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Run Cloud inspection', 'npcink-workflow-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-nightly-cloud-entitlement <?php disabled( ! $cloud_ready ); ?>><?php esc_html_e( 'Refresh Cloud quota', 'npcink-workflow-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-nightly-cloud-recent <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Load Cloud recent', 'npcink-workflow-toolbox' ); ?></button>
				</div>
				<div class="npcink-toolbox__readiness-strip" data-toolbox-nightly-cloud-recent-run hidden></div>
				<div class="npcink-toolbox__readiness-strip" data-toolbox-nightly-cloud-run-summary hidden></div>
				<div class="npcink-toolbox__result is-empty" data-toolbox-nightly-cloud-result aria-live="polite" hidden></div>
				<details class="npcink-toolbox__result-details" data-toolbox-nightly-cloud-advanced>
					<summary><?php esc_html_e( 'Advanced details', 'npcink-workflow-toolbox' ); ?></summary>
					<label>
						<span><?php esc_html_e( 'Cloud run ID', 'npcink-workflow-toolbox' ); ?></span>
						<input type="text" data-toolbox-nightly-cloud-run-id placeholder="<?php esc_attr_e( 'Run ID from Cloud Batch', 'npcink-workflow-toolbox' ); ?>" autocomplete="off" />
					</label>
					<div class="npcink-toolbox__inline-actions">
						<button type="button" class="button" data-toolbox-nightly-cloud-status <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Check status', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="button" data-toolbox-nightly-cloud-result-read <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Read result', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="button" data-toolbox-nightly-cloud-retry <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Retry run', 'npcink-workflow-toolbox' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Use these controls only when recovering, retrying, or inspecting a known Cloud run ID. Cloud remains the run-state owner and retry processor.', 'npcink-workflow-toolbox' ); ?></p>
				</details>
			</form>
		</section>
		<?php
	}

	private function render_start_status_item( string $title, string $status, string $label, string $description ): void {
		?>
		<div class="npcink-toolbox__readiness-item is-<?php echo esc_attr( $status ); ?>">
			<span><?php echo esc_html( $title ); ?></span>
			<strong><?php echo esc_html( $label ); ?></strong>
			<small><?php echo esc_html( $description ); ?></small>
		</div>
		<?php
	}

	private function render_start_status_row( string $title, string $status, string $label, string $description ): void {
		?>
		<div class="npcink-toolbox__start-status-row is-<?php echo esc_attr( $status ); ?>">
			<div>
				<span><?php echo esc_html( $title ); ?></span>
				<strong><?php echo esc_html( $label ); ?></strong>
			</div>
			<small><?php echo esc_html( $description ); ?></small>
		</div>
		<?php
	}

	private function content_context_ready( array $content_context ): bool {
		$required_fields = array( 'site_positioning', 'target_audience', 'brand_voice', 'primary_keywords' );

		foreach ( $required_fields as $field ) {
			$value = $content_context[ $field ] ?? '';
			if ( is_array( $value ) ) {
				$parts = array();
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) {
						$parts[] = trim( (string) $item );
					}
				}
				$value = implode( ' ', array_filter( $parts ) );
			}
			if ( '' === trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	private function render_cloud_runtime_notice(): void {
		?>
		<div class="npcink-toolbox__result-notice is-warning">
			<strong><?php esc_html_e( 'AI service is not connected.', 'npcink-workflow-toolbox' ); ?></strong>
			<span>
				<?php esc_html_e( 'AI-powered search, image suggestions, content library search, and hosted checks stay unavailable until the service is connected. Basic site profile editing remains available.', 'npcink-workflow-toolbox' ); ?>
				<?php echo esc_html( $this->cloud_runtime_unavailable_reason_label() ); ?>
			</span>
		</div>
		<?php
	}

	private function cloud_runtime_unavailable_reason_label(): string {
		$reason = $this->settings->cloud_runtime_unavailable_reason();

		if ( 'cloud_addon_not_installed' === $reason ) {
			return __( 'Install and connect the Cloud Addon to enable hosted execution.', 'npcink-workflow-toolbox' );
		}

		if ( 'cloud_addon_not_connected' === $reason ) {
			return __( 'Save and verify Cloud Addon credentials to enable hosted execution.', 'npcink-workflow-toolbox' );
		}

		return __( 'Check Cloud Addon transport before running hosted execution.', 'npcink-workflow-toolbox' );
	}

	private function render_site_knowledge_panel( bool $cloud_ready ): void {
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Content Library Setup', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Let AI suggestions search your existing public posts and pages. Toolbox sends the public content list; Cloud owns index detail.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<div class="npcink-toolbox__site-knowledge" data-toolbox-site-knowledge>
				<section class="npcink-toolbox__card">
					<div class="npcink-toolbox__section-heading">
						<div>
							<h3><?php esc_html_e( 'Index status', 'npcink-workflow-toolbox' ); ?></h3>
							<p><?php esc_html_e( 'Read-only coverage summary for public content AI can use as suggestion context.', 'npcink-workflow-toolbox' ); ?></p>
						</div>
						<button type="button" class="button" data-toolbox-site-knowledge-status <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Refresh status', 'npcink-workflow-toolbox' ); ?></button>
					</div>
					<?php if ( ! $cloud_ready ) : ?>
						<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect the AI service before reading content library coverage.', 'npcink-workflow-toolbox' ); ?></div>
					<?php endif; ?>
					<div class="npcink-toolbox__knowledge-summary" data-toolbox-site-knowledge-summary>
						<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'Status has not been loaded yet.', 'npcink-workflow-toolbox' ); ?></div>
					</div>
					<div class="npcink-toolbox__knowledge-summary" data-toolbox-agent-feedback-summary>
						<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'Agent feedback summary has not been loaded yet.', 'npcink-workflow-toolbox' ); ?></div>
					</div>
				</section>

			<section class="npcink-toolbox__card">
				<h3><?php esc_html_e( 'Index actions', 'npcink-workflow-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'Start or refresh the public content library used by AI suggestions. Advanced cleanup stays outside this page.', 'npcink-workflow-toolbox' ); ?></p>
				<form data-toolbox-site-knowledge-sync>
					<input type="hidden" name="sync_mode" value="refresh" />
					<input type="hidden" name="max_posts" value="20" />
					<p class="description"><?php esc_html_e( 'Toolbox sends the latest public posts and pages. Approved comments are included only when the hosted service enables comment indexing.', 'npcink-workflow-toolbox' ); ?></p>
					<div class="npcink-toolbox__inline-actions">
							<button
								type="submit"
								class="button button-primary"
								data-toolbox-site-knowledge-sync-submit
								data-start-label="<?php esc_attr_e( 'Start indexing', 'npcink-workflow-toolbox' ); ?>"
								data-refresh-label="<?php esc_attr_e( 'Refresh index', 'npcink-workflow-toolbox' ); ?>"
								<?php echo disabled( ! $cloud_ready, true, false ); ?>
							><?php esc_html_e( 'Start indexing', 'npcink-workflow-toolbox' ); ?></button>
						</div>
						<?php if ( ! $cloud_ready ) : ?>
							<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Indexing is disabled until the AI service connection is available.', 'npcink-workflow-toolbox' ); ?></div>
						<?php endif; ?>
						<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
				</form>
			</section>

			<section class="npcink-toolbox__info-panel">
				<h3><?php esc_html_e( 'Review handoff', 'npcink-workflow-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'When AI returns evidence-backed review input, Toolbox can prepare one blocked review proposal. A person still completes and approves it.', 'npcink-workflow-toolbox' ); ?></p>
				<ul class="npcink-toolbox__usage-list">
					<li>
						<strong><?php esc_html_e( 'Evidence first', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'The handoff appears only after the content library returns proposal input with evidence references.', 'npcink-workflow-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Core review only', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Submit Core review proposal creates a blocked proposal that still needs a human title and content.', 'npcink-workflow-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'No direct write', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Approval, preflight, audit, and final WordPress writes stay in local Core governance.', 'npcink-workflow-toolbox' ); ?></span>
					</li>
				</ul>
			</section>

			<section class="npcink-toolbox__info-panel">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Where this helps', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'After the library is ready, article suggestions can find related public content without showing index settings to daily users.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
				</div>
				<ul class="npcink-toolbox__usage-list">
					<li>
						<strong><?php esc_html_e( 'Internal Link Candidates', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Find related public posts and pages for editor-reviewed links.', 'npcink-workflow-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Publish Preflight', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Check duplicate risk, source coverage, and missing site references before publishing.', 'npcink-workflow-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Discoverability Brief', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Ground SEO, AEO, and GEO suggestions in existing site context.', 'npcink-workflow-toolbox' ); ?></span>
					</li>
						<li>
							<strong><?php esc_html_e( 'External AI workflows', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Use the same Cloud-managed knowledge ability for API callers and natural-language requests.', 'npcink-workflow-toolbox' ); ?></span>
						</li>
				</ul>
				<p class="description"><?php esc_html_e( 'Final WordPress edits still require the normal Core proposal and editor approval path.', 'npcink-workflow-toolbox' ); ?></p>
			</section>
		</div>
		<?php
	}

	private function render_site_knowledge_search_check( bool $advanced = false, bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__inline-form" data-toolbox-site-knowledge-search>
			<h3><?php echo esc_html( $advanced ? __( 'Advanced search check', 'npcink-workflow-toolbox' ) : __( 'Search check', 'npcink-workflow-toolbox' ) ); ?></h3>
			<p><?php esc_html_e( 'Run a read-only query against Cloud-managed site knowledge.', 'npcink-workflow-toolbox' ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running Site Knowledge checks.', 'npcink-workflow-toolbox' ); ?></div>
			<?php endif; ?>
			<label>
				<span><?php esc_html_e( 'Query', 'npcink-workflow-toolbox' ); ?></span>
				<input type="text" name="query" placeholder="<?php esc_attr_e( 'Search public site knowledge', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<?php if ( $advanced ) : ?>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Intent', 'npcink-workflow-toolbox' ); ?></span>
						<select name="intent">
							<option value="site_search"><?php esc_html_e( 'Site search', 'npcink-workflow-toolbox' ); ?></option>
							<option value="faq_candidates"><?php esc_html_e( 'FAQ candidates', 'npcink-workflow-toolbox' ); ?></option>
							<option value="content_gap_analysis"><?php esc_html_e( 'Content gaps', 'npcink-workflow-toolbox' ); ?></option>
							<option value="duplicate_check"><?php esc_html_e( 'Duplicate check', 'npcink-workflow-toolbox' ); ?></option>
							<option value="internal_links"><?php esc_html_e( 'Internal links', 'npcink-workflow-toolbox' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Source types', 'npcink-workflow-toolbox' ); ?></span>
						<input type="text" name="source_types" value="post,page" />
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Current post ID', 'npcink-workflow-toolbox' ); ?></span>
						<input type="number" name="current_post_id" min="0" value="0" />
					</label>
					<label>
						<span><?php esc_html_e( 'Max results', 'npcink-workflow-toolbox' ); ?></span>
						<input type="number" name="max_results" min="1" max="20" value="8" />
					</label>
				</div>
				<?php else : ?>
					<input type="hidden" name="intent" value="site_search" />
					<input type="hidden" name="source_types" value="post,page" />
					<input type="hidden" name="current_post_id" value="0" />
					<input type="hidden" name="max_results" value="8" />
				<?php endif; ?>
				<button type="submit" class="button" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php echo esc_html( $advanced ? __( 'Search index', 'npcink-workflow-toolbox' ) : __( 'Run check', 'npcink-workflow-toolbox' ) ); ?></button>
				<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
			</form>
			<?php
	}

	private function render_cloud_checks_panel( array $settings, bool $cloud_ready, ?array $nightly_preview ): void {
		$image_ready = $this->settings->has_image_source_provider();
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'AI Service Checks', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Use these only when AI actions are unavailable or a support person asks for diagnostics.', 'npcink-workflow-toolbox' ); ?></p>
		</div>
		<?php
		if ( ! $cloud_ready ) {
			$this->render_cloud_runtime_notice();
		}
		?>

		<div class="npcink-toolbox__cloud-check-workspace" data-toolbox-cloud-checks>
			<section class="npcink-toolbox__card">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Basic AI service check', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Start here when AI tools seem unavailable. This sends one read-only test request.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
				</div>
				<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="web-search/test">
					<input type="hidden" name="query" value="WordPress AI content workflow status check" />
					<input type="hidden" name="intent" value="article_background" />
					<input type="hidden" name="max_results" value="1" />
					<input type="hidden" name="recency_days" value="30" />
					<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run basic AI service check', 'npcink-workflow-toolbox' ); ?></button>
					<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
				</form>
			</section>

			<details class="npcink-toolbox__start-advanced" data-toolbox-cloud-check-details>
				<summary>
					<span><?php esc_html_e( 'Detailed service checks', 'npcink-workflow-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Search, image suggestions, content library, and scheduled review checks for troubleshooting.', 'npcink-workflow-toolbox' ); ?></small>
				</summary>

				<nav class="npcink-toolbox__cloud-check-tabs" aria-label="<?php esc_attr_e( 'AI service check groups', 'npcink-workflow-toolbox' ); ?>">
					<button type="button" class="npcink-toolbox__cloud-check-tab is-active" data-toolbox-cloud-check-target="search" aria-selected="true">
						<span><?php esc_html_e( 'Search', 'npcink-workflow-toolbox' ); ?></span>
						<small><?php echo esc_html( $cloud_ready ? __( 'Connected', 'npcink-workflow-toolbox' ) : __( 'Needs connection', 'npcink-workflow-toolbox' ) ); ?></small>
					</button>
					<button type="button" class="npcink-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="image" aria-selected="false">
						<span><?php esc_html_e( 'Image suggestions', 'npcink-workflow-toolbox' ); ?></span>
						<small><?php echo esc_html( $image_ready ? __( 'Connected', 'npcink-workflow-toolbox' ) : __( 'Needs connection', 'npcink-workflow-toolbox' ) ); ?></small>
					</button>
					<button type="button" class="npcink-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="site-knowledge" aria-selected="false">
						<span><?php esc_html_e( 'Content library', 'npcink-workflow-toolbox' ); ?></span>
						<small><?php echo esc_html( $cloud_ready ? __( 'Connected', 'npcink-workflow-toolbox' ) : __( 'Needs connection', 'npcink-workflow-toolbox' ) ); ?></small>
					</button>
					<button type="button" class="npcink-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="nightly-inspection" aria-selected="false">
						<span><?php esc_html_e( 'Scheduled review', 'npcink-workflow-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Detailed check', 'npcink-workflow-toolbox' ); ?></small>
					</button>
				</nav>
				<div class="npcink-toolbox__result-notice is-pending">
					<?php esc_html_e( 'Detailed service monitoring lives in Cloud Addon Monitoring. Toolbox keeps only the checks needed to confirm this plugin can reach the service.', 'npcink-workflow-toolbox' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-cloud-addon&tab=monitoring' ) ); ?>"><?php esc_html_e( 'Open Cloud Addon Monitoring', 'npcink-workflow-toolbox' ); ?></a>
				</div>

				<div class="npcink-toolbox__cloud-check-panels">
				<section class="npcink-toolbox__card" data-toolbox-cloud-check-panel="search">
					<div class="npcink-toolbox__cloud-check-group-workspace" data-toolbox-cloud-check-groups>
							<nav class="npcink-toolbox__cloud-check-group-list" aria-label="<?php esc_attr_e( 'Search checks', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__cloud-check-group-button is-active" data-toolbox-cloud-check-group-target="search-test" aria-selected="true">
									<span><?php esc_html_e( 'Search test', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Read-only query', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="zhihu-capability" aria-selected="false">
									<span><?php esc_html_e( 'Zhihu source test', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Optional source', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="search-diagnostic" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion evidence', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Result evidence', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div>
								<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="search-test">
									<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="web-search/test">
										<h3><?php esc_html_e( 'Search service test', 'npcink-workflow-toolbox' ); ?></h3>
										<?php if ( ! $cloud_ready ) : ?>
											<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect the AI service before running search checks.', 'npcink-workflow-toolbox' ); ?></div>
										<?php endif; ?>
										<label>
											<span><?php esc_html_e( 'Query', 'npcink-workflow-toolbox' ); ?></span>
											<input type="text" name="query" value="latest WordPress AI search trends" />
										</label>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Use case', 'npcink-workflow-toolbox' ); ?></span>
												<select name="intent">
													<option value="article_background" data-toolbox-query="latest WordPress AI search trends" data-toolbox-recency="30"><?php esc_html_e( 'Article background', 'npcink-workflow-toolbox' ); ?></option>
													<option value="fact_check" data-toolbox-query="official WordPress 6.9 release WordPress.org AI Experiments plugin" data-toolbox-recency="0"><?php esc_html_e( 'Fact check', 'npcink-workflow-toolbox' ); ?></option>
													<option value="competitor_research" data-toolbox-query="Surfer SEO Clearscope MarketMuse content optimization competitors pricing features 2026" data-toolbox-recency="30"><?php esc_html_e( 'Competitor research', 'npcink-workflow-toolbox' ); ?></option>
													<option value="pricing_snapshot" data-toolbox-query="Tavily API pricing official pricing page" data-toolbox-recency="0"><?php esc_html_e( 'Pricing snapshot', 'npcink-workflow-toolbox' ); ?></option>
													<option value="product_comparison" data-toolbox-query="Surfer SEO Clearscope MarketMuse product comparison official features" data-toolbox-recency="0"><?php esc_html_e( 'Product comparison', 'npcink-workflow-toolbox' ); ?></option>
													<option value="writing_context" data-toolbox-query="WordPress AI content workflow current best practices" data-toolbox-recency="30"><?php esc_html_e( 'Writing context', 'npcink-workflow-toolbox' ); ?></option>
													<option value="news" data-toolbox-query="latest WordPress AI search news" data-toolbox-recency="7"><?php esc_html_e( 'News', 'npcink-workflow-toolbox' ); ?></option>
													<option value="source_discovery" data-toolbox-query="official WordPress AI plugin source references" data-toolbox-recency="0"><?php esc_html_e( 'Source discovery', 'npcink-workflow-toolbox' ); ?></option>
													<option value="external_links" data-toolbox-query="WordPress AI content workflow authoritative references" data-toolbox-recency="0"><?php esc_html_e( 'External links', 'npcink-workflow-toolbox' ); ?></option>
												</select>
											</label>
											<label>
												<span><?php esc_html_e( 'Max results', 'npcink-workflow-toolbox' ); ?></span>
												<input type="number" name="max_results" min="1" max="5" value="3" />
											</label>
										</div>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Recent days', 'npcink-workflow-toolbox' ); ?></span>
												<input type="number" name="recency_days" min="0" max="30" value="7" />
											</label>
										</div>
										<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run search check', 'npcink-workflow-toolbox' ); ?></button>
										<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
									</form>
								</div>
								<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="zhihu-capability" hidden>
									<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="web-search/test">
										<h3><?php esc_html_e( 'Zhihu source checks', 'npcink-workflow-toolbox' ); ?></h3>
										<p><?php esc_html_e( 'Use this only when Zhihu-based sources need troubleshooting. These checks are read-only and do not generate, rewrite, publish, or write WordPress content.', 'npcink-workflow-toolbox' ); ?></p>
										<?php if ( ! $cloud_ready ) : ?>
											<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect the AI service before running Zhihu source checks.', 'npcink-workflow-toolbox' ); ?></div>
										<?php endif; ?>
										<label>
											<span><?php esc_html_e( 'Query', 'npcink-workflow-toolbox' ); ?></span>
											<input type="text" name="query" value="AI 写作工具用户最关心什么" />
										</label>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Source mode', 'npcink-workflow-toolbox' ); ?></span>
												<select name="intent">
													<option value="zhihu_global_search" data-toolbox-managed-source="zhihu_global_search" data-toolbox-query="AI 写作工具有哪些可靠资料来源" data-toolbox-recency="30" data-toolbox-max-results="3"><?php esc_html_e( '全网搜', 'npcink-workflow-toolbox' ); ?></option>
													<option value="zhihu_research" data-toolbox-managed-source="zhihu_research" data-toolbox-query="AI 写作工具用户最关心什么" data-toolbox-recency="30" data-toolbox-max-results="5"><?php esc_html_e( '知乎搜索', 'npcink-workflow-toolbox' ); ?></option>
													<option value="zhihu_hot_topics" data-toolbox-managed-source="zhihu_hot_topics" data-toolbox-query="知乎热榜" data-toolbox-recency="1" data-toolbox-max-results="5"><?php esc_html_e( '热榜', 'npcink-workflow-toolbox' ); ?></option>
													<option value="zhida_simple" data-toolbox-managed-source="zhida_simple" data-toolbox-query="什么是 AI Agent" data-toolbox-recency="30" data-toolbox-max-results="3"><?php esc_html_e( '直答 Simple', 'npcink-workflow-toolbox' ); ?></option>
													<option value="zhida_deep" data-toolbox-managed-source="zhida_deep" data-toolbox-query="AI Agent 在内容生产中有哪些真实使用场景" data-toolbox-recency="30" data-toolbox-max-results="3"><?php esc_html_e( '直答 Deep', 'npcink-workflow-toolbox' ); ?></option>
													<option value="zhida_deepsearch" data-toolbox-managed-source="zhida_deepsearch" data-toolbox-query="2026 年 AI Agent 内容生产工具有哪些新趋势" data-toolbox-recency="7" data-toolbox-max-results="3"><?php esc_html_e( '直答 DeepSearch', 'npcink-workflow-toolbox' ); ?></option>
												</select>
											</label>
											<label>
												<span><?php esc_html_e( 'Max results', 'npcink-workflow-toolbox' ); ?></span>
												<input type="number" name="max_results" min="1" max="5" value="3" />
											</label>
										</div>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Recent days', 'npcink-workflow-toolbox' ); ?></span>
												<input type="number" name="recency_days" min="0" max="30" value="30" />
											</label>
										</div>
										<input type="hidden" name="managed_source" value="zhihu_global_search" />
										<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run Zhihu check', 'npcink-workflow-toolbox' ); ?></button>
										<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
									</form>
								</div>
								<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="search-diagnostic" hidden>
									<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="web-search/diagnostics">
										<h3><?php esc_html_e( 'Suggestion evidence check', 'npcink-workflow-toolbox' ); ?></h3>
										<p><?php esc_html_e( 'Run one read-only suggestion scenario and verify whether supporting web evidence is attached.', 'npcink-workflow-toolbox' ); ?></p>
										<?php if ( ! $cloud_ready ) : ?>
											<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect the AI service before running suggestion evidence checks.', 'npcink-workflow-toolbox' ); ?></div>
										<?php endif; ?>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Check type', 'npcink-workflow-toolbox' ); ?></span>
												<select name="scenario">
													<option value="discoverability"><?php esc_html_e( 'Discoverability', 'npcink-workflow-toolbox' ); ?></option>
													<option value="publish_preflight"><?php esc_html_e( 'Publish preflight', 'npcink-workflow-toolbox' ); ?></option>
												</select>
											</label>
											<label>
												<span><?php esc_html_e( 'Topic', 'npcink-workflow-toolbox' ); ?></span>
												<input type="text" name="topic" value="latest WordPress AI search trends" />
											</label>
										</div>
										<label>
											<span><?php esc_html_e( 'Working title', 'npcink-workflow-toolbox' ); ?></span>
											<input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional title override', 'npcink-workflow-toolbox' ); ?>" />
										</label>
										<button type="submit" class="button" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run evidence check', 'npcink-workflow-toolbox' ); ?></button>
										<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
									</form>
								</div>
							</div>
						</div>
				</section>

				<section class="npcink-toolbox__card" data-toolbox-cloud-check-panel="image" hidden>
					<div class="npcink-toolbox__cloud-check-group-workspace" data-toolbox-cloud-check-groups>
							<nav class="npcink-toolbox__cloud-check-group-list" aria-label="<?php esc_attr_e( 'Image checks', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__cloud-check-group-button is-active" data-toolbox-cloud-check-group-target="image-smoke" aria-selected="true">
									<span><?php esc_html_e( 'Candidate check', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Candidates', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="image-derivative-preview" aria-selected="false">
									<span><?php esc_html_e( 'Existing image preview', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Derivative', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="image-handoff" aria-selected="false">
									<span><?php esc_html_e( 'Handoff', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Core review', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div>
								<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="image-smoke">
									<?php $this->render_image_source_candidates_smoke_form( $cloud_ready ); ?>
									<?php $this->render_ai_image_generation_smoke_form( $cloud_ready ); ?>
								</div>
							<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="image-derivative-preview" hidden>
								<?php $this->render_image_derivative_preview_check(); ?>
							</div>
							<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="image-handoff" hidden>
								<div class="npcink-toolbox__example">
									<strong><?php esc_html_e( 'Reviewed changes stay in the image workflow', 'npcink-workflow-toolbox' ); ?></strong>
									<span><?php esc_html_e( 'Start single-image optimization from the media library attachment details panel. Use Batch Image Optimization Review when multiple images or URL repair actions are needed.', 'npcink-workflow-toolbox' ); ?></span>
								</div>
									<a class="button" href="<?php echo esc_url( $this->media_library_url() ); ?>"><?php esc_html_e( 'Open Media Library', 'npcink-workflow-toolbox' ); ?></a>
							</div>
						</div>
					</div>
				</section>

				<section class="npcink-toolbox__card" data-toolbox-cloud-check-panel="site-knowledge" hidden>
					<div class="npcink-toolbox__section-heading">
						<div>
							<h3><?php esc_html_e( 'Content library search check', 'npcink-workflow-toolbox' ); ?></h3>
							<p><?php esc_html_e( 'Use this only to check whether AI can search existing public posts and pages.', 'npcink-workflow-toolbox' ); ?></p>
						</div>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=site-knowledge' ) ); ?>"><?php esc_html_e( 'Open content library setup', 'npcink-workflow-toolbox' ); ?></a>
					</div>
					<?php $this->render_site_knowledge_search_check( false, $cloud_ready ); ?>
				</section>

				<section class="npcink-toolbox__cloud-check-panel-stack" data-toolbox-cloud-check-panel="nightly-inspection" hidden>
					<section class="npcink-toolbox__card">
						<div class="npcink-toolbox__section-heading">
							<div>
								<h3><?php esc_html_e( 'Scheduled review checks', 'npcink-workflow-toolbox' ); ?></h3>
								<p><?php esc_html_e( 'Use this only when scheduled site review needs preview, status, or recovery checks. Daily article work stays in the editor sidebar.', 'npcink-workflow-toolbox' ); ?></p>
							</div>
							<a class="button" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>"><?php esc_html_e( 'Preview Morning Brief', 'npcink-workflow-toolbox' ); ?></a>
						</div>
					</section>
					<?php $this->render_nightly_inspection_preview( $nightly_preview ); ?>
					<?php $this->render_nightly_inspection_basic_settings( $settings ); ?>
				</section>

			</div>
			</details>
		</div>
		<?php
	}

	private function render_content_context_form( array $context ): void {
		$proposal_fields = array(
			'seo_title'             => __( 'SEO title', 'npcink-workflow-toolbox' ),
			'seo_description'       => __( 'SEO description', 'npcink-workflow-toolbox' ),
			'slug'                  => __( 'Slug', 'npcink-workflow-toolbox' ),
			'excerpt'               => __( 'Excerpt', 'npcink-workflow-toolbox' ),
			'faq'                   => __( 'FAQ', 'npcink-workflow-toolbox' ),
			'answer_summary'        => __( 'Answer summary', 'npcink-workflow-toolbox' ),
			'geo_summary'           => __( 'GEO summary', 'npcink-workflow-toolbox' ),
			'structured_data_hints' => __( 'Structured data hints', 'npcink-workflow-toolbox' ),
		);
		$preview = wp_json_encode( $this->settings->get_content_context_for_ability(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Site Profile', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Fill the basic site brief AI needs for consistent suggestions. SEO, AEO, GEO, and claim rules stay folded under advanced guidance.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<form class="npcink-toolbox__settings-form" method="post" action="options.php" data-toolbox-context-form>
			<?php settings_fields( 'npcink_toolbox_content_context' ); ?>

			<div class="npcink-toolbox__draft-actions" aria-label="<?php esc_attr_e( 'Content context draft actions', 'npcink-workflow-toolbox' ); ?>">
				<button type="button" class="button" data-toolbox-context-draft="aiBlog"><?php esc_html_e( 'Use AI tech blog template', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-draft="site"><?php esc_html_e( 'Draft from current site content', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-clear><?php esc_html_e( 'Clear form', 'npcink-workflow-toolbox' ); ?></button>
				<span><?php esc_html_e( 'Drafts are editable suggestions and do not change posts, media, SEO meta, or provider settings.', 'npcink-workflow-toolbox' ); ?></span>
			</div>

			<div class="npcink-toolbox__context-workspace" data-toolbox-context-sections>
				<section class="npcink-toolbox__context-brief-panel">
					<div class="npcink-toolbox__section-heading">
						<div>
							<h3><?php esc_html_e( 'Site brief', 'npcink-workflow-toolbox' ); ?></h3>
							<p><?php esc_html_e( 'Maintain only the fields needed for consistent suggestion behavior. Advanced SEO, AEO, GEO, and claim rules stay folded below.', 'npcink-workflow-toolbox' ); ?></p>
						</div>
					</div>
					<div class="npcink-toolbox__context-brief-grid">
						<?php $this->render_context_textarea( 'site_positioning', __( 'Site positioning', 'npcink-workflow-toolbox' ), $context ); ?>
						<?php $this->render_context_list_field( 'target_audience', __( 'Target audience', 'npcink-workflow-toolbox' ), $context ); ?>
						<?php $this->render_context_textarea( 'brand_voice', __( 'Brand voice', 'npcink-workflow-toolbox' ), $context ); ?>
						<?php $this->render_context_list_field( 'primary_keywords', __( 'Primary keywords', 'npcink-workflow-toolbox' ), $context ); ?>
					</div>
				</section>

					<details class="npcink-toolbox__context-advanced">
						<summary>
							<span><?php esc_html_e( 'Advanced guidance rules', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'SEO, AEO, GEO, claim boundaries, and technical preview.', 'npcink-workflow-toolbox' ); ?></small>
						</summary>
					<nav class="npcink-toolbox__context-tabs" aria-label="<?php esc_attr_e( 'Advanced content context sections', 'npcink-workflow-toolbox' ); ?>">
						<button type="button" class="npcink-toolbox__context-tab is-active" data-toolbox-context-target="seo" aria-selected="true">
							<span><?php esc_html_e( 'SEO', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Search snippets', 'npcink-workflow-toolbox' ); ?></small>
						</button>
						<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="aeo" aria-selected="false">
							<span><?php esc_html_e( 'AEO', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Answer shape', 'npcink-workflow-toolbox' ); ?></small>
						</button>
						<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="geo" aria-selected="false">
							<span><?php esc_html_e( 'GEO', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'AI citation signals', 'npcink-workflow-toolbox' ); ?></small>
						</button>
						<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="boundaries" aria-selected="false">
							<span><?php esc_html_e( 'Boundaries', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Claims and preview', 'npcink-workflow-toolbox' ); ?></small>
						</button>
					</nav>

					<div class="npcink-toolbox__context-panels">
					<section class="npcink-toolbox__card" data-toolbox-context-panel="seo">
						<h2><?php esc_html_e( 'SEO', 'npcink-workflow-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Control search-oriented metadata, keyword coverage, and which SEO fields AI may suggest.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'SEO fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="seo-keywords" aria-selected="true">
									<span><?php esc_html_e( 'Keywords', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Long-tail terms', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="seo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Search guidance', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="seo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-keywords">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'SEO keywords', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Add supporting long-tail phrases here. Primary keywords stay in the Brief section so the first setup path remains obvious.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'long_tail_keywords', __( 'Long-tail keywords', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-rules" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'SEO rules', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Describe title, description, slug, excerpt, and internal-link preferences for proposal-ready suggestions.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'seo_rules', __( 'SEO rules', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'SEO fields AI may suggest', 'npcink-workflow-toolbox' ); ?></legend>
										<?php foreach ( array( 'seo_title', 'seo_description', 'slug', 'excerpt' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="aeo" hidden>
						<h2><?php esc_html_e( 'AEO', 'npcink-workflow-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Shape answer-engine output: direct answers, FAQs, definitions, and step-style responses.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'AEO fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="aeo-rules" aria-selected="true">
									<span><?php esc_html_e( 'Rules', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Answer guidance', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="aeo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'FAQ and summary', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="aeo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-rules">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'AEO rules', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Start with a direct answer, then add conditions, steps, limits, and short followups.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'aeo_rules', __( 'AEO rules', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_faq_generation', __( 'Allow FAQ suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_aeo_summary', __( 'Allow AEO answer summary suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'AEO fields AI may suggest', 'npcink-workflow-toolbox' ); ?></legend>
										<?php foreach ( array( 'faq', 'answer_summary' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="geo" hidden>
						<h2><?php esc_html_e( 'GEO', 'npcink-workflow-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Guide AI-readable entity signals, standalone conclusions, and citation-friendly summaries.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'GEO fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="geo-entities" aria-selected="true">
									<span><?php esc_html_e( 'Entities', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Signals', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Summary guidance', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'GEO and schema', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-entities">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'Entities', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'List people, products, standards, projects, and concepts AI should recognize as important context.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'entity_keywords', __( 'Entity keywords', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-rules" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'GEO rules', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Keep key conclusions standalone, define important entities, and separate implemented facts from plans.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'geo_rules', __( 'GEO rules', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_geo_summary', __( 'Allow GEO summary suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_structured_data_suggestions', __( 'Allow structured data suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'GEO fields AI may suggest', 'npcink-workflow-toolbox' ); ?></legend>
										<?php foreach ( array( 'geo_summary', 'structured_data_hints' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

						<section class="npcink-toolbox__card" data-toolbox-context-panel="boundaries" hidden>
							<h2><?php esc_html_e( 'Boundaries', 'npcink-workflow-toolbox' ); ?></h2>
							<p><?php esc_html_e( 'Limit what AI can claim and inspect the read-only technical preview exposed to callers.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'Boundary fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="boundaries-allowed" aria-selected="true">
									<span><?php esc_html_e( 'Allowed claims', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Can say', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-forbidden" aria-selected="false">
									<span><?php esc_html_e( 'Forbidden claims', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Must not say', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-exceptions" aria-selected="false">
									<span><?php esc_html_e( 'Exceptions', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Special cases', 'npcink-workflow-toolbox' ); ?></small>
									</button>
									<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-preview" aria-selected="false">
										<span><?php esc_html_e( 'Technical preview', 'npcink-workflow-toolbox' ); ?></span>
										<small><?php esc_html_e( 'Read-only data', 'npcink-workflow-toolbox' ); ?></small>
									</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-allowed">
									<?php $this->render_context_list_field( 'allowed_claims', __( 'Allowed claims', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-forbidden" hidden>
									<?php $this->render_context_list_field( 'forbidden_claims', __( 'Forbidden claims', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-exceptions" hidden>
									<?php $this->render_context_list_field( 'disallowed_topics', __( 'Disallowed topics', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'cautious_topics', __( 'Cautious topics', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'no_structured_output_topics', __( 'No structured output topics', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'human_confirmation_required', __( 'Human confirmation required', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-preview" hidden>
									<pre class="npcink-toolbox__result"><?php echo esc_html( (string) $preview ); ?></pre>
								</section>
							</div>
						</div>
					</section>
				</div>
				</details>
			</div>

			<p class="description"><?php esc_html_e( 'Final WordPress writes still require review approval; AI receives this profile as suggestion-only guidance.', 'npcink-workflow-toolbox' ); ?></p>
			<?php submit_button( __( 'Save site profile', 'npcink-workflow-toolbox' ) ); ?>
		</form>

		<?php
	}

	private function render_tool_cards( bool $cloud_ready, string $surface = 'image' ): void {
		$tools = array(
			array(
				'surface'     => 'image',
				'group'       => __( 'Media', 'npcink-workflow-toolbox' ),
				'group_id'    => 'media',
				'id'          => 'media-batch-optimize',
				'endpoint'    => 'media-derivative-handoff',
				'title'       => __( 'Batch Image Optimization Review', 'npcink-workflow-toolbox' ),
				'description' => __( 'Build a small review list, generate previews, then submit only approved rows. Original images are not replaced here.', 'npcink-workflow-toolbox' ),
				'custom'      => 'media_derivative_batch',
			),
			array(
				'surface'     => 'image',
				'group'       => __( 'Batch ALT Completion', 'npcink-workflow-toolbox' ),
				'group_id'    => 'image-text-review',
				'id'          => 'media-alt-caption-review',
				'endpoint'    => 'ai/site-helpers',
				'title'       => __( 'Batch Fill Missing ALT', 'npcink-workflow-toolbox' ),
				'description' => __( 'Find images that need ALT text, review suggestions, then submit selected rows for review.', 'npcink-workflow-toolbox' ),
				'intent'      => 'media_alt_suggestions',
				'button'      => __( 'Scan and generate suggestions', 'npcink-workflow-toolbox' ),
				'custom'      => 'media_alt_caption_review',
			),
		);

		$tools = array_values(
			array_filter(
				$tools,
				static function ( array $tool ) use ( $surface ): bool {
					if ( $surface !== (string) ( $tool['surface'] ?? 'image' ) ) {
						return false;
					}
					return true;
				}
			)
		);

		$tool_groups = array(
			'media'             => array(
				'title'       => __( 'Image Optimization Review', 'npcink-workflow-toolbox' ),
				'description' => __( 'Preview selected images before submitting an optimization request.', 'npcink-workflow-toolbox' ),
			),
			'image-text-review' => array(
				'title'       => __( 'Batch Image ALT', 'npcink-workflow-toolbox' ),
				'description' => __( 'Review selected image ALT suggestions before submitting them for review.', 'npcink-workflow-toolbox' ),
			),
		);
		$group_counts = array();
		foreach ( $tools as $tool ) {
			$group_id = (string) ( $tool['group_id'] ?? '' );
			if ( '' === $group_id ) {
				continue;
			}
			$group_counts[ $group_id ] = (int) ( $group_counts[ $group_id ] ?? 0 ) + 1;
		}

		$surface_header = array(
			'title'             => __( 'Image Handling', 'npcink-workflow-toolbox' ),
			'description'       => __( 'Use the media library for one image, or use this page for selected image batches. Full-site content opportunities start from Overview.', 'npcink-workflow-toolbox' ),
			'scope_title'       => __( 'Image tasks', 'npcink-workflow-toolbox' ),
			'scope_description' => __( 'Batch work starts from selected media-library images or a bounded sample. Nothing is written automatically.', 'npcink-workflow-toolbox' ),
		);
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php echo esc_html( (string) $surface_header['title'] ); ?></h2>
			<p><?php echo esc_html( (string) $surface_header['description'] ); ?></p>
		</div>
		<div class="npcink-toolbox__tool-workspace" data-toolbox-tools>
			<div class="npcink-toolbox__workflow-scope">
				<strong><?php echo esc_html( (string) $surface_header['scope_title'] ); ?></strong>
				<span><?php echo esc_html( (string) $surface_header['scope_description'] ); ?></span>
			</div>
			<div class="npcink-toolbox__tool-group-tabs" aria-label="<?php esc_attr_e( 'Tool groups', 'npcink-workflow-toolbox' ); ?>">
				<?php
				$rendered_groups = array();
				foreach ( $tools as $index => $tool ) :
					$group_id = (string) ( $tool['group_id'] ?? '' );
					if ( '' === $group_id || isset( $rendered_groups[ $group_id ] ) ) {
						continue;
					}
					$rendered_groups[ $group_id ] = true;
					$group_meta                   = $tool_groups[ $group_id ] ?? array(
						'title'       => (string) ( $tool['group'] ?? '' ),
						'description' => '',
					);
					$group_classes                = array( 'npcink-toolbox__tool-group-tab' );
					if ( 0 === $index ) {
						$group_classes[] = 'is-active';
					}
					if ( ! empty( $group_meta['secondary'] ) ) {
						$group_classes[] = 'is-secondary';
					}
					?>
					<button type="button" class="<?php echo esc_attr( implode( ' ', $group_classes ) ); ?>" data-toolbox-tool-group-target="<?php echo esc_attr( $group_id ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>">
						<span><?php echo esc_html( (string) $group_meta['title'] ); ?></span>
						<small><?php echo esc_html( (string) $group_meta['description'] ); ?></small>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="npcink-toolbox__tool-list" aria-label="<?php esc_attr_e( 'Tool actions', 'npcink-workflow-toolbox' ); ?>">
				<?php
				$rendered_groups = array();
				foreach ( $tools as $index => $tool ) :
					$group_id = (string) ( $tool['group_id'] ?? '' );
					if ( '' === $group_id ) {
						continue;
					}
					if ( ! isset( $rendered_groups[ $group_id ] ) ) :
						$rendered_groups[ $group_id ] = true;
						$group_meta                   = $tool_groups[ $group_id ] ?? array(
							'title'       => (string) ( $tool['group'] ?? '' ),
							'description' => '',
						);
						?>
						<div class="npcink-toolbox__tool-group-panel <?php echo 1 === (int) ( $group_counts[ $group_id ] ?? 0 ) ? 'is-single-tool' : ''; ?>" data-toolbox-tool-group-panel="<?php echo esc_attr( $group_id ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
							<div class="npcink-toolbox__tool-group-label">
								<span><?php echo esc_html( (string) $group_meta['title'] ); ?></span>
								<small><?php echo esc_html( (string) $group_meta['description'] ); ?></small>
							</div>
					<?php endif; ?>
						<button type="button" class="npcink-toolbox__tool-button <?php echo 0 === $index ? 'is-active' : ''; ?>" data-toolbox-tool-target="<?php echo esc_attr( (string) $tool['id'] ); ?>" data-toolbox-tool-group="<?php echo esc_attr( $group_id ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>">
							<span><?php echo esc_html( (string) $tool['title'] ); ?></span>
							<small><?php echo esc_html( (string) $tool['description'] ); ?></small>
						</button>
					<?php
					$next_tool     = $tools[ $index + 1 ] ?? null;
					$next_group_id = is_array( $next_tool ) ? (string) ( $next_tool['group_id'] ?? '' ) : '';
					if ( $next_group_id !== $group_id ) :
						?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<div class="npcink-toolbox__tool-panels">
				<?php
				foreach ( $tools as $index => $tool ) {
						if ( 'content_support_flow' === (string) ( $tool['custom'] ?? '' ) ) {
							$this->render_content_support_flow_tool(
								(string) $tool['endpoint'],
								(string) $tool['title'],
								(string) $tool['description'],
								(string) $tool['id'],
								(string) $tool['intent'],
								(string) $tool['button'],
								'hosted_ai' === (string) ( $tool['powered_by'] ?? '' ),
								0 === $index,
								$cloud_ready
							);
							continue;
						}
						if ( 'media_alt_caption_review' === (string) ( $tool['custom'] ?? '' ) ) {
							$this->render_media_alt_caption_review_tool(
								(string) $tool['endpoint'],
								(string) $tool['title'],
								(string) $tool['description'],
								(string) $tool['id'],
								(string) $tool['button'],
								0 === $index,
								$cloud_ready
							);
							continue;
						}
						if ( 'media_derivative_batch' === (string) ( $tool['custom'] ?? '' ) ) {
							$this->render_media_derivative_batch_tool(
								(string) $tool['endpoint'],
								(string) $tool['title'],
								(string) $tool['description'],
								(string) $tool['id'],
								0 === $index
							);
							continue;
						}
					$this->render_text_tool(
						(string) $tool['endpoint'],
						(string) $tool['title'],
						(string) $tool['description'],
						(string) $tool['field'],
						(string) $tool['placeholder'],
						(string) $tool['button'],
						$tool['extra_fields'] ?? array(),
						(string) $tool['id'],
						0 === $index
					);
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_media_alt_caption_review_tool( string $endpoint, string $title, string $description, string $tool_id, string $button, bool $active = false, bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--alt-review" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" data-toolbox-media-alt-caption-review <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before building image text review sets.', 'npcink-workflow-toolbox' ); ?></div>
			<?php endif; ?>
			<input type="hidden" name="intent" value="media_alt_suggestions" />
				<div class="npcink-toolbox__example is-ai">
					<strong><?php esc_html_e( 'Review first', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Toolbox only prepares ALT suggestions. Media ALT text changes only after selected rows pass review.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			<label>
				<span><?php esc_html_e( 'Selected attachment IDs', 'npcink-workflow-toolbox' ); ?></span>
				<input type="text" name="attachment_ids" data-toolbox-selected-attachment-ids placeholder="<?php esc_attr_e( 'Optional: 12, 34, 56', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<p class="description" data-toolbox-selected-attachment-summary><?php esc_html_e( 'Use the media library bulk action to prefill this field, or leave it empty to scan by range.', 'npcink-workflow-toolbox' ); ?></p>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Scan range', 'npcink-workflow-toolbox' ); ?></span>
					<select name="media_scope" data-toolbox-media-alt-scope>
						<option value="media_library_sample"><?php esc_html_e( 'Recent media library images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="current_article_used_images"><?php esc_html_e( 'Images used by one article', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label data-toolbox-media-alt-post-field hidden>
					<span><?php esc_html_e( 'Article ID', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" name="post_id" min="1" step="1" placeholder="<?php esc_attr_e( 'Required for article images', 'npcink-workflow-toolbox' ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Scan count', 'npcink-workflow-toolbox' ); ?></span>
					<select name="sample_size">
						<option value="10"><?php esc_html_e( '10 images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="20"><?php esc_html_e( '20 images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="30"><?php esc_html_e( '30 images', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Rows to review', 'npcink-workflow-toolbox' ); ?></span>
					<select name="review_set_limit">
						<option value="5"><?php esc_html_e( '5 images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="10"><?php esc_html_e( '10 images', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Problem type', 'npcink-workflow-toolbox' ); ?></span>
					<select name="media_filter">
						<option value="missing_or_weak_alt"><?php esc_html_e( 'Missing or weak ALT', 'npcink-workflow-toolbox' ); ?></option>
						<option value="missing_alt"><?php esc_html_e( 'Missing ALT only', 'npcink-workflow-toolbox' ); ?></option>
						<option value="all_recent"><?php esc_html_e( 'All recent images', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Focus note', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="focus" placeholder="<?php esc_attr_e( 'Optional: product screenshots, diagrams, or missing captions', 'npcink-workflow-toolbox' ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'No media ALT is changed here. Review the rows, then submit selected items for review.', 'npcink-workflow-toolbox' ); ?></div>
			<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php echo esc_html( $button ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_content_support_flow_tool( string $endpoint, string $title, string $description, string $tool_id, string $intent, string $button, bool $hosted_ai = false, bool $active = false, bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
				<?php if ( $hosted_ai ) : ?>
					<div class="npcink-toolbox__example is-ai">
						<strong><?php esc_html_e( 'Hosted AI route', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Toolbox sends one lightweight draft-support request through the Cloud hosted runtime when the site is connected. The result is a reviewable suggestion, not a finished article.', 'npcink-workflow-toolbox' ); ?></span>
					</div>
					<?php if ( ! $cloud_ready ) : ?>
						<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running hosted AI support.', 'npcink-workflow-toolbox' ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
			<input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>" />
			<input type="hidden" name="post_type" value="post" />
			<input type="hidden" name="post_status" value="draft" />
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Fixed support flow', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'This runs one bounded suggestion flow from the supplied article, selected text, topic, or brief. It does not write posts, assign terms, insert links, import media, or publish.', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Input scope', 'npcink-workflow-toolbox' ); ?></span>
				<select name="context_scope">
					<option value="auto"><?php esc_html_e( 'Auto: selected text when present, otherwise full article', 'npcink-workflow-toolbox' ); ?></option>
					<option value="full_article"><?php esc_html_e( 'Full article context', 'npcink-workflow-toolbox' ); ?></option>
					<option value="selected_text"><?php esc_html_e( 'Selected text or supplied snippet', 'npcink-workflow-toolbox' ); ?></option>
					<option value="topic_only"><?php esc_html_e( 'Topic or short brief only', 'npcink-workflow-toolbox' ); ?></option>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Post ID (optional)', 'npcink-workflow-toolbox' ); ?></span>
				<input type="number" min="0" step="1" name="post_id" placeholder="<?php esc_attr_e( 'Use 0 for topic-only runs', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Title or topic', 'npcink-workflow-toolbox' ); ?></span>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Working title or article topic', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Excerpt or short brief', 'npcink-workflow-toolbox' ); ?></span>
				<textarea name="excerpt" rows="3" placeholder="<?php esc_attr_e( 'Optional summary, angle, audience, or constraints', 'npcink-workflow-toolbox' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Draft text or notes', 'npcink-workflow-toolbox' ); ?></span>
				<textarea name="content" rows="5" placeholder="<?php esc_attr_e( 'Optional draft body, notes, or source outline', 'npcink-workflow-toolbox' ); ?>"></textarea>
			</label>
				<button type="submit" class="button button-primary" <?php echo disabled( $hosted_ai && ! $cloud_ready, true, false ); ?>><?php echo esc_html( $button ); ?></button>
				<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
			</form>
		<?php
	}

	private function render_image_source_candidates_smoke_form( bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="image-candidates">
			<h3><?php esc_html_e( 'Image source check', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Test Cloud-managed Unsplash/Pixabay/Pexels image-source candidates and preserve attribution metadata.', 'npcink-workflow-toolbox' ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before testing Cloud image-source candidates.', 'npcink-workflow-toolbox' ); ?></div>
			<?php endif; ?>
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Cloud smoke test', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'A successful result shows Cloud runtime, provider mode, candidate count, preview image, suggested filename, license review status, and any reviewed AI image generation handoff. This does not import media or write WordPress.', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Image search query', 'npcink-workflow-toolbox' ); ?></span>
				<input type="text" name="query" value="<?php esc_attr_e( 'wordpress article hero image', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Provider', 'npcink-workflow-toolbox' ); ?></span>
					<select name="provider">
						<option value="auto"><?php esc_html_e( 'Cloud auto', 'npcink-workflow-toolbox' ); ?></option>
						<option value="unsplash"><?php esc_html_e( 'Unsplash', 'npcink-workflow-toolbox' ); ?></option>
						<option value="pixabay"><?php esc_html_e( 'Pixabay', 'npcink-workflow-toolbox' ); ?></option>
						<option value="pexels"><?php esc_html_e( 'Pexels', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Orientation', 'npcink-workflow-toolbox' ); ?></span>
					<select name="orientation">
						<option value="landscape"><?php esc_html_e( 'Landscape', 'npcink-workflow-toolbox' ); ?></option>
						<option value="portrait"><?php esc_html_e( 'Portrait', 'npcink-workflow-toolbox' ); ?></option>
						<option value="squarish"><?php esc_html_e( 'Squarish', 'npcink-workflow-toolbox' ); ?></option>
						<option value=""><?php esc_html_e( 'Any', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Candidate count', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="1" max="8" step="1" name="per_page" value="3" />
				</label>
				<label>
					<span><?php esc_html_e( 'Color filter', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="color" placeholder="<?php esc_attr_e( 'Optional Unsplash color filter', 'npcink-workflow-toolbox' ); ?>" />
				</label>
			</div>
			<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run image source check', 'npcink-workflow-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_ai_image_generation_smoke_form( bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="ai/image-generation">
			<h3><?php esc_html_e( 'AI image generation check', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Generate one reviewed-prompt AI image candidate through hosted Cloud runtime. The result stays candidate-only and does not import media or write WordPress.', 'npcink-workflow-toolbox' ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before testing hosted AI image generation.', 'npcink-workflow-toolbox' ); ?></div>
			<?php endif; ?>
			<label>
				<span><?php esc_html_e( 'Reviewed prompt', 'npcink-workflow-toolbox' ); ?></span>
				<textarea name="prompt" rows="4"><?php echo esc_textarea( __( 'Create an original editorial header image for a WordPress article about AI image generation governance. Composition: 16:9 image suitable for a WordPress article. Style: clean editorial photo illustration, natural light, high quality. Avoid visible text, brand logos, watermarks, distorted hands or faces, and copyrighted characters.', 'npcink-workflow-toolbox' ) ); ?></textarea>
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Aspect ratio', 'npcink-workflow-toolbox' ); ?></span>
					<select name="aspect_ratio">
						<option value="16:9"><?php esc_html_e( '16:9', 'npcink-workflow-toolbox' ); ?></option>
						<option value="1:1"><?php esc_html_e( '1:1', 'npcink-workflow-toolbox' ); ?></option>
						<option value="4:3"><?php esc_html_e( '4:3', 'npcink-workflow-toolbox' ); ?></option>
						<option value="3:4"><?php esc_html_e( '3:4', 'npcink-workflow-toolbox' ); ?></option>
						<option value="9:16"><?php esc_html_e( '9:16', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Candidate count', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="1" max="4" step="1" name="n" value="1" />
				</label>
			</div>
			<input type="hidden" name="resolution" value="high" />
			<input type="hidden" name="response_format" value="url" />
			<input type="hidden" name="purpose" value="cloud_check_ai_image_generation" />
			<input type="hidden" name="prompt_reviewed_by_operator" value="1" />
			<input type="hidden" name="media_title" value="<?php esc_attr_e( 'AI image generation governance', 'npcink-workflow-toolbox' ); ?>" />
			<input type="hidden" name="media_alt" value="<?php esc_attr_e( 'Original editorial image for AI image generation governance.', 'npcink-workflow-toolbox' ); ?>" />
			<input type="hidden" name="media_description" value="<?php esc_attr_e( 'AI-generated image candidate for the hosted image generation smoke test. Review it before importing or setting it as featured media.', 'npcink-workflow-toolbox' ); ?>" />
			<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run AI image check', 'npcink-workflow-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function get_media_derivative_toolbox_policy(): array {
		return $this->settings->media_optimization_policy_summary();
	}

	private function get_media_derivative_watermark_details( array $toolbox_policy ): string {
		if ( 'text' === (string) ( $toolbox_policy['watermark_type'] ?? '' ) ) {
			return sprintf(
				/* translators: 1: text, 2: position, 3: opacity, 4: font size, 5: margin. */
				__( 'text "%1$s", %2$s, %3$d%% opacity, %4$dpx font, %5$dpx margin', 'npcink-workflow-toolbox' ),
				(string) ( $toolbox_policy['watermark_text'] ?? 'AI' ),
				ucwords( str_replace( '_', ' ', (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ) ) ),
				(int) ( $toolbox_policy['watermark_opacity'] ?? 80 ),
				(int) ( $toolbox_policy['watermark_font_size'] ?? 48 ),
				(int) ( $toolbox_policy['watermark_margin'] ?? 24 )
			);
		}

		if ( empty( $toolbox_policy['watermark_configured'] ) ) {
			return __( 'off or incomplete', 'npcink-workflow-toolbox' );
		}

		return sprintf(
			/* translators: 1: position, 2: opacity, 3: scale, 4: margin. */
			__( '%1$s, %2$d%% opacity, %3$d%% scale, %4$dpx margin', 'npcink-workflow-toolbox' ),
			ucwords( str_replace( '_', ' ', (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ) ) ),
			(int) ( $toolbox_policy['watermark_opacity'] ?? 80 ),
			(int) ( $toolbox_policy['watermark_scale'] ?? 20 ),
			(int) ( $toolbox_policy['watermark_margin'] ?? 24 )
		);
	}

	private function render_media_derivative_toolbox_defaults( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__example">
			<strong><?php esc_html_e( 'Toolbox defaults', 'npcink-workflow-toolbox' ); ?></strong>
			<span>
				<?php
				printf(
					/* translators: 1: format, 2: max width, 3: quality. */
					esc_html__( '%1$s, %2$dpx, quality %3$d. Watermark: %4$s.', 'npcink-workflow-toolbox' ),
					esc_html( strtoupper( (string) $toolbox_policy['target_format'] ) ),
					(int) $toolbox_policy['max_width'],
					(int) $toolbox_policy['quality'],
					esc_html( $this->get_media_derivative_watermark_details( $toolbox_policy ) )
				);
				?>
			</span>
		</div>
		<?php
	}

	private function render_media_derivative_picker_controls(): void {
		?>
		<div class="npcink-toolbox__media-picker">
			<div class="npcink-toolbox__media-preview" data-toolbox-media-preview>
				<span><?php esc_html_e( 'No image selected', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<div>
				<label>
					<span><?php esc_html_e( 'Attachment ID', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="1" step="1" name="attachment_id" placeholder="<?php esc_attr_e( 'Attachment ID', 'npcink-workflow-toolbox' ); ?>" data-toolbox-media-attachment />
				</label>
				<label>
					<span><?php esc_html_e( 'Image URL', 'npcink-workflow-toolbox' ); ?></span>
					<input type="url" name="attachment_url" placeholder="<?php esc_attr_e( 'Paste a local uploads URL', 'npcink-workflow-toolbox' ); ?>" data-toolbox-media-url />
				</label>
				<div class="npcink-toolbox__inline-actions">
					<button type="button" class="button" data-toolbox-select-media><?php esc_html_e( 'Select from media library', 'npcink-workflow-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-resolve-media-url><?php esc_html_e( 'Resolve URL', 'npcink-workflow-toolbox' ); ?></button>
					<span data-toolbox-media-name><?php esc_html_e( 'Choose one local image attachment.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
				<div class="npcink-toolbox__url-resolution" data-toolbox-media-url-resolution hidden></div>
			</div>
		</div>
		<?php
	}

	private function render_media_derivative_format_controls( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__split">
			<label>
				<span><?php esc_html_e( 'Format override', 'npcink-workflow-toolbox' ); ?></span>
				<select name="target_format">
					<option value=""><?php esc_html_e( 'Use Toolbox default', 'npcink-workflow-toolbox' ); ?></option>
					<?php foreach ( array( 'webp', 'avif', 'jpeg', 'png', 'original' ) as $format ) : ?>
						<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( strtoupper( $format ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Max width override', 'npcink-workflow-toolbox' ); ?></span>
				<input type="number" min="320" max="7680" step="1" name="max_width" placeholder="<?php echo esc_attr( (string) $toolbox_policy['max_width'] ); ?>" />
			</label>
		</div>
		<label>
			<span><?php esc_html_e( 'Quality override', 'npcink-workflow-toolbox' ); ?></span>
			<input type="number" min="1" max="100" step="1" name="quality" placeholder="<?php echo esc_attr( (string) $toolbox_policy['quality'] ); ?>" />
		</label>
		<?php
	}

	private function render_media_derivative_watermark_controls( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Watermark override', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Use Toolbox watermark defaults unless this run needs a specific override. Text watermark overrides do not need a logo attachment; image/logo overrides use the configured Toolbox logo source for this run.', 'npcink-workflow-toolbox' ); ?></p>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Watermark mode', 'npcink-workflow-toolbox' ); ?></span>
					<select name="watermark_mode">
						<option value="default"><?php esc_html_e( 'Use Toolbox default', 'npcink-workflow-toolbox' ); ?></option>
						<option value="off"><?php esc_html_e( 'No watermark', 'npcink-workflow-toolbox' ); ?></option>
						<option value="text"><?php esc_html_e( 'Text watermark', 'npcink-workflow-toolbox' ); ?></option>
						<option value="image"><?php esc_html_e( 'Image/logo watermark', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Position', 'npcink-workflow-toolbox' ); ?></span>
					<select name="watermark_position">
						<?php foreach ( array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ) as $position ) : ?>
							<option value="<?php echo esc_attr( $position ); ?>" <?php selected( (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ), $position ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $position ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Text', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" maxlength="64" name="watermark_text" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_text'] ?? 'AI' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Font size', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="8" max="256" step="1" name="watermark_font_size" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_font_size'] ?? 48 ) ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Text color', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="watermark_color" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_color'] ?? '#FFFFFF' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Background', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="watermark_background" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_background'] ?? 'rgba(0,0,0,0.35)' ) ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Opacity', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="0" max="100" step="1" name="watermark_opacity" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_opacity'] ?? 80 ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Image scale', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="1" max="100" step="1" name="watermark_scale" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_scale'] ?? 20 ) ); ?>" />
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Margin', 'npcink-workflow-toolbox' ); ?></span>
				<input type="number" min="0" max="1000" step="1" name="watermark_margin" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_margin'] ?? 24 ) ); ?>" />
			</label>
		</div>
		<?php
	}

	private function render_media_derivative_crop_controls(): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Crop override', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Optional one-run crop for common publishing ratios. Cloud returns only a preview; final adoption still requires review.', 'npcink-workflow-toolbox' ); ?></p>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Crop ratio', 'npcink-workflow-toolbox' ); ?></span>
					<select name="crop_aspect_ratio">
						<option value=""><?php esc_html_e( 'No crop', 'npcink-workflow-toolbox' ); ?></option>
						<option value="16:9"><?php esc_html_e( '16:9 landscape', 'npcink-workflow-toolbox' ); ?></option>
						<option value="4:3"><?php esc_html_e( '4:3 landscape', 'npcink-workflow-toolbox' ); ?></option>
						<option value="1:1"><?php esc_html_e( '1:1 square', 'npcink-workflow-toolbox' ); ?></option>
						<option value="3:4"><?php esc_html_e( '3:4 portrait', 'npcink-workflow-toolbox' ); ?></option>
						<option value="9:16"><?php esc_html_e( '9:16 portrait', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Crop anchor', 'npcink-workflow-toolbox' ); ?></span>
					<select name="crop_position">
						<?php foreach ( array( 'center', 'top', 'bottom', 'left', 'right', 'top_left', 'top_right', 'bottom_left', 'bottom_right' ) as $position ) : ?>
							<option value="<?php echo esc_attr( $position ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $position ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_image_derivative_preview_check(): void {
		$toolbox_policy = $this->get_media_derivative_toolbox_policy();
		?>
		<form class="npcink-toolbox__inline-form" data-toolbox-media-derivative data-toolbox-media-derivative-preview-only>
			<h3><?php esc_html_e( 'Existing image preview', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Generate a short-lived Cloud preview for one existing media-library image. This check does not submit a review request or write media.', 'npcink-workflow-toolbox' ); ?></p>
			<?php $this->render_media_derivative_toolbox_defaults( $toolbox_policy ); ?>
			<?php $this->render_media_derivative_picker_controls(); ?>
			<?php $this->render_media_derivative_format_controls( $toolbox_policy ); ?>
			<?php $this->render_media_derivative_crop_controls(); ?>
			<?php $this->render_media_derivative_watermark_controls( $toolbox_policy ); ?>
			<button type="button" class="button button-primary" data-toolbox-run-media-derivative><?php esc_html_e( 'Generate preview', 'npcink-workflow-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_media_derivative_batch_controls( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Batch image optimization review', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Use this for multiple existing media-library images. Build a review list first; nothing replaces the original files from this page.', 'npcink-workflow-toolbox' ); ?></p>
			<ol class="npcink-toolbox__flow-steps" aria-label="<?php esc_attr_e( 'Batch optimization steps', 'npcink-workflow-toolbox' ); ?>">
				<li><?php esc_html_e( 'Choose image source', 'npcink-workflow-toolbox' ); ?></li>
				<li><?php esc_html_e( 'Build review list', 'npcink-workflow-toolbox' ); ?></li>
				<li><?php esc_html_e( 'Generate previews', 'npcink-workflow-toolbox' ); ?></li>
				<li><?php esc_html_e( 'Submit optimization request', 'npcink-workflow-toolbox' ); ?></li>
			</ol>
			<p class="description" data-toolbox-selected-attachment-summary><?php esc_html_e( 'Use Media Library bulk actions to prefill selected image IDs, or use the source below to build a small sample.', 'npcink-workflow-toolbox' ); ?></p>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Image source', 'npcink-workflow-toolbox' ); ?></span>
					<select name="batch_scope_preset">
						<option value="current_month"><?php esc_html_e( 'This month\'s images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="previous_month"><?php esc_html_e( 'Previous month\'s images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom date range', 'npcink-workflow-toolbox' ); ?></option>
						<option value="all"><?php esc_html_e( 'Small eligible sample', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Optimization goal', 'npcink-workflow-toolbox' ); ?></span>
					<select name="batch_recipe">
						<option value="smart_optimize"><?php esc_html_e( 'Recommended optimization', 'npcink-workflow-toolbox' ); ?></option>
						<option value="convert_format"><?php esc_html_e( 'Change file format', 'npcink-workflow-toolbox' ); ?></option>
						<option value="resize_only"><?php esc_html_e( 'Resize large images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="watermark"><?php esc_html_e( 'Add watermark', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Images to review', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="1" max="10" step="1" name="batch_max_items" value="5" />
				</label>
			</div>
			<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'No original image is replaced here. Review previews first, then submit selected rows. Core may execute automatically only when site policy allows.', 'npcink-workflow-toolbox' ); ?></div>
			<details class="npcink-toolbox__result-details npcink-toolbox__advanced-filters">
				<summary><?php esc_html_e( 'Advanced filters and processing options', 'npcink-workflow-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Use these only when the review list needs exact media IDs, dates, formats, dimensions, crop, or watermark changes.', 'npcink-workflow-toolbox' ); ?></p>
				<label>
					<span><?php esc_html_e( 'Selected image IDs', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="attachment_ids" data-toolbox-selected-attachment-ids placeholder="<?php esc_attr_e( 'Optional: 12, 34, 56', 'npcink-workflow-toolbox' ); ?>" />
				</label>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Date from', 'npcink-workflow-toolbox' ); ?></span>
						<input type="date" name="batch_date_from" />
					</label>
					<label>
						<span><?php esc_html_e( 'Date to', 'npcink-workflow-toolbox' ); ?></span>
						<input type="date" name="batch_date_to" />
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Exclude formats', 'npcink-workflow-toolbox' ); ?></span>
						<input type="text" name="batch_exclude_formats" value="webp,gif,svg" />
					</label>
					<label>
						<span><?php esc_html_e( 'Min dimensions', 'npcink-workflow-toolbox' ); ?></span>
						<input type="text" name="batch_min_dimensions" value="800x800" />
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Output format', 'npcink-workflow-toolbox' ); ?></span>
					<select name="batch_target_format">
						<?php foreach ( array( 'webp', 'avif', 'jpeg', 'png', 'original' ) as $format ) : ?>
							<option value="<?php echo esc_attr( $format ); ?>" <?php selected( 'webp', $format ); ?>><?php echo esc_html( strtoupper( $format ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php $this->render_media_derivative_format_controls( $toolbox_policy ); ?>
				<?php $this->render_media_derivative_crop_controls(); ?>
				<?php $this->render_media_derivative_watermark_controls( $toolbox_policy ); ?>
			</details>
			<div class="npcink-toolbox__inline-actions">
				<button type="button" class="button button-primary" data-toolbox-build-media-batch-plan><?php esc_html_e( 'Build review list', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-run-media-batch-previews disabled><?php esc_html_e( 'Generate selected previews', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-submit-media-batch-proposals disabled><?php esc_html_e( 'Submit and execute', 'npcink-workflow-toolbox' ); ?></button>
			</div>
			<div class="npcink-toolbox__batch-plan" data-toolbox-media-batch-plan hidden></div>
		</div>
		<?php
	}

	private function render_media_derivative_batch_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		$toolbox_policy = $this->get_media_derivative_toolbox_policy();
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--media-batch" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" data-toolbox-media-derivative <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="npcink-toolbox__example is-ai">
				<strong><?php esc_html_e( 'Review before replacing', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'This page prepares previews and review requests only. Final media replacement happens later through Core approval and Adapter execution.', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<?php $this->render_media_derivative_toolbox_defaults( $toolbox_policy ); ?>
			<?php $this->render_media_derivative_batch_controls( $toolbox_policy ); ?>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_text_tool( string $endpoint, string $title, string $description, string $field, string $placeholder, string $button, array $extra_fields = array(), string $tool_id = '', bool $active = false ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<label>
				<span><?php echo esc_html( $placeholder ); ?></span>
				<textarea name="<?php echo esc_attr( $field ); ?>" rows="4"></textarea>
			</label>
			<?php foreach ( $extra_fields as $extra ) : ?>
				<label>
					<span><?php echo esc_html( (string) $extra['label'] ); ?></span>
					<input type="text" name="<?php echo esc_attr( (string) $extra['name'] ); ?>" placeholder="<?php echo esc_attr( (string) $extra['placeholder'] ); ?>" />
				</label>
			<?php endforeach; ?>
			<button type="submit" class="button button-primary"><?php echo esc_html( $button ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_checkbox( string $key, string $label, array $settings ): void {
		?>
		<label class="npcink-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	private function render_context_textarea( string $key, string $label, array $context ): void {
		?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( (string) ( $context[ $key ] ?? '' ) ); ?></textarea>
		</label>
		<?php
	}

	private function render_context_list_field( string $key, string $label, array $context ): void {
		$value = implode( "\n", (array) ( $context[ $key ] ?? array() ) );
		?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
		</label>
		<?php
	}

	private function render_context_checkbox( string $key, string $label, array $context ): void {
		?>
		<label class="npcink-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $context[ $key ] ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	private function render_proposal_field_checkbox( string $field, string $label, array $context ): void {
		?>
		<label class="npcink-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[proposal_allowed_fields][]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, (array) $context['proposal_allowed_fields'], true ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}
}
