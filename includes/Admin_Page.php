<?php
/**
 * WordPress admin page for Toolbox actions.
 *
 * @package Magick_AI_Toolbox
 */

namespace Magick_AI_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Admin_Page {
	private const PARENT_MENU_SLUG = 'magick-ai';
	private const MENU_SLUG        = 'magick-ai-toolbox';

	private Settings $settings;
	private string $hook_suffix = '';

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_menu(): void {
		if ( $this->has_magick_parent_menu() ) {
			$this->hook_suffix = add_submenu_page(
				self::PARENT_MENU_SLUG,
				__( 'Magick AI Toolbox', 'magick-ai-toolbox' ),
				__( 'Toolbox', 'magick-ai-toolbox' ),
				'manage_options',
				self::MENU_SLUG,
				array( $this, 'render' ),
				45
			);
			return;
		}

		$this->hook_suffix = add_management_page(
			__( 'Magick AI Toolbox', 'magick-ai-toolbox' ),
			__( 'Magick AI Toolbox', 'magick-ai-toolbox' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	private function has_magick_parent_menu(): bool {
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

		wp_enqueue_style(
			'magick-ai-toolbox-admin',
			MAGICK_AI_TOOLBOX_URL . 'assets/admin.css',
			array(),
			MAGICK_AI_TOOLBOX_VERSION
		);

		wp_enqueue_script(
			'magick-ai-toolbox-admin',
			MAGICK_AI_TOOLBOX_URL . 'assets/admin.js',
			array(),
			MAGICK_AI_TOOLBOX_VERSION,
			true
		);
		wp_enqueue_media();

		wp_localize_script(
			'magick-ai-toolbox-admin',
			'MagickAIToolbox',
			array(
				'restUrl'       => esc_url_raw( rest_url( Plugin::REST_NAMESPACE ) ),
				'adapterRestUrl' => esc_url_raw( rest_url( 'magick-ai-adapter/v1' ) ),
				'coreAdminUrl'  => esc_url_raw( admin_url( 'admin.php?page=magick-ai-core' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'contextOption' => Plugin::CONTEXT_OPTION_NAME,
				'contextDrafts' => array(
					'aiBlog' => $this->get_ai_blog_context_template(),
					'site'   => $this->get_site_content_context_suggestion(),
				),
				'labels'        => array(
					'running' => __( 'Running...', 'magick-ai-toolbox' ),
					'error'   => __( 'Request failed.', 'magick-ai-toolbox' ),
				)
			)
		);
	}

	private function get_ai_blog_context_template(): array {
		return array(
			'site_positioning'                  => __( 'A practical AI technology blog for developers, product teams, and AI tool builders. It focuses on large language model applications, agent workflows, WordPress AI integration, vector search, content automation, and AI product engineering.', 'magick-ai-toolbox' ),
			'target_audience'                   => array(
				__( 'AI application developers', 'magick-ai-toolbox' ),
				__( 'WordPress plugin developers', 'magick-ai-toolbox' ),
				__( 'Technical content operators', 'magick-ai-toolbox' ),
				__( 'AI product managers', 'magick-ai-toolbox' ),
				__( 'Independent developers', 'magick-ai-toolbox' ),
				__( 'Internal tools teams', 'magick-ai-toolbox' ),
			),
			'brand_voice'                       => __( 'Professional, pragmatic, clear, and restrained. Explain real use cases, engineering tradeoffs, and boundary risks. Avoid inflated marketing claims. Give direct recommendations with conditions and limits.', 'magick-ai-toolbox' ),
			'primary_keywords'                  => array(
				__( 'AI technology blog', 'magick-ai-toolbox' ),
				__( 'large language model applications', 'magick-ai-toolbox' ),
				__( 'AI Agent', 'magick-ai-toolbox' ),
				__( 'WordPress AI', 'magick-ai-toolbox' ),
				__( 'vector search', 'magick-ai-toolbox' ),
				__( 'RAG', 'magick-ai-toolbox' ),
				__( 'AI workflow', 'magick-ai-toolbox' ),
				__( 'content automation', 'magick-ai-toolbox' ),
			),
			'long_tail_keywords'                => array(
				__( 'how to integrate AI capabilities into WordPress', 'magick-ai-toolbox' ),
				__( 'AI Agent workflow design', 'magick-ai-toolbox' ),
				__( 'WordPress plugin development with AI tools', 'magick-ai-toolbox' ),
				__( 'vector search for content websites', 'magick-ai-toolbox' ),
				__( 'RAG and content retrieval practice', 'magick-ai-toolbox' ),
				__( 'AI content suggestion workflow', 'magick-ai-toolbox' ),
				__( 'large language model application engineering', 'magick-ai-toolbox' ),
			),
			'entity_keywords'                   => array( 'OpenAI', 'WordPress', 'Cloud Search', 'Site Knowledge', 'Unsplash', 'REST API', 'WordPress Abilities API', 'Magick AI' ),
			'allowed_claims'                    => array(
				__( 'AI tools can assist research, generate suggestions, plan content, and improve editorial efficiency.', 'magick-ai-toolbox' ),
				__( 'Vector search, external search, and content context can improve retrieval and suggestion quality.', 'magick-ai-toolbox' ),
				__( 'Architecture advice and implementation ideas are suitable for development and testing contexts.', 'magick-ai-toolbox' ),
				__( 'Final publishing, SEO writes, and media changes should go through human review or governance.', 'magick-ai-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not claim AI output is always correct.', 'magick-ai-toolbox' ),
				__( 'Do not claim automatic SEO ranking improvements.', 'magick-ai-toolbox' ),
				__( 'Do not claim AI replaces human review, legal review, or expert judgment.', 'magick-ai-toolbox' ),
				__( 'Do not imply WordPress permissions, approval, or governance can be bypassed.', 'magick-ai-toolbox' ),
				__( 'Do not describe image-source search as AI image generation.', 'magick-ai-toolbox' ),
				__( 'Do not describe vector search as a complete knowledge base or automatic indexing system.', 'magick-ai-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported customer stories, rankings, benchmark results, or legal/medical/financial advice.', 'magick-ai-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Model comparisons, provider pricing, product roadmap, security posture, and production-readiness claims require current verification.', 'magick-ai-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions when the source does not clearly support every answer or step.', 'magick-ai-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Claims about implemented features, integrations, customer usage, benchmark quality, ranking impact, or availability must be confirmed by the operator.', 'magick-ai-toolbox' ),
			),
			'seo_rules'                         => __( "Titles should include the main topic keyword and avoid clickbait.\nDescriptions should state the problem, audience, and core conclusion.\nUse clear headings, steps, caveats, and engineering boundary notes.\nPrefer internal links to related tutorials, architecture notes, and tool reviews.", 'magick-ai-toolbox' ),
			'aeo_rules'                         => __( "Start with a direct answer, then add conditions, steps, and limits.\nPrefer FAQ, short definitions, comparison tables, and actionable checklists.\nAvoid abstract-only answers; include practical guidance.", 'magick-ai-toolbox' ),
			'geo_rules'                         => __( "Make key conclusions clear, standalone, and easy for AI systems to summarize.\nDefine important terms when they first appear.\nDistinguish implemented features, development-stage behavior, and future plans.\nAvoid inflated claims; state boundaries, inputs, outputs, and limits.", 'magick-ai-toolbox' ),
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
				__( 'Practical guide: %s', 'magick-ai-toolbox' ),
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
				__( '%s. Use recent public posts, categories, and tags as non-secret guidance for content suggestions. Keep the site brief editable and verify recommendations before saving.', 'magick-ai-toolbox' ),
				implode( ' - ', $position_parts )
			)
			: __( 'A WordPress site with public content available for operator-reviewed AI content suggestions. Keep recommendations editable and verify them before saving.', 'magick-ai-toolbox' );

		return array(
			'site_positioning'                  => $positioning,
			'target_audience'                   => array( __( 'Current site readers', 'magick-ai-toolbox' ), __( 'Editors', 'magick-ai-toolbox' ), __( 'Site operators', 'magick-ai-toolbox' ) ),
			'brand_voice'                       => __( 'Use the tone implied by existing public posts. Prefer clear, accurate, and reviewable suggestions over promotional claims.', 'magick-ai-toolbox' ),
			'primary_keywords'                  => $primary_keywords,
			'long_tail_keywords'                => $this->unique_non_empty( $long_tail_keywords ),
			'entity_keywords'                   => $entity_keywords,
			'allowed_claims'                    => array(
				__( 'Suggestions may use public post titles, public categories, and public tags as context.', 'magick-ai-toolbox' ),
				__( 'Suggestions should be treated as drafts for operator review.', 'magick-ai-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not infer private business facts from public content.', 'magick-ai-toolbox' ),
				__( 'Do not claim the generated suggestions have been verified unless an operator verifies them.', 'magick-ai-toolbox' ),
				__( 'Do not bypass WordPress permissions, approval, or governance.', 'magick-ai-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported private facts, unverified business claims, and claims outside current public site content.', 'magick-ai-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Product status, pricing, customer examples, legal/medical/financial claims, and time-sensitive facts require operator confirmation.', 'magick-ai-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions unless the sampled source clearly supports them.', 'magick-ai-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Any claim not visible in public post titles, categories, tags, or supplied source content must be confirmed by the operator.', 'magick-ai-toolbox' ),
			),
			'seo_rules'                         => __( "Use public categories, tags, and recent article themes as keyword candidates.\nTitles should stay specific to the article topic and avoid clickbait.\nDescriptions should summarize the reader problem and expected value.\nSuggest internal links only when the target content is clearly related.", 'magick-ai-toolbox' ),
			'aeo_rules'                         => __( "Answer likely reader questions directly before giving details.\nPrefer concise definitions, steps, checklists, and FAQ suggestions.\nMark assumptions clearly when the site content does not provide enough evidence.", 'magick-ai-toolbox' ),
			'geo_rules'                         => __( "Use public entity names from categories, tags, and recent titles as entity hints.\nKeep conclusions standalone and easy to quote.\nDistinguish observed site content from generated recommendations.", 'magick-ai-toolbox' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'magick-ai-toolbox' ) );
		}

		$settings        = $this->settings->get_all();
		$content_context = $this->settings->get_content_context();
		?>
		<div class="wrap magick-ai-toolbox">
			<h1><?php esc_html_e( 'Magick AI Toolbox', 'magick-ai-toolbox' ); ?></h1>
			<p class="magick-ai-toolbox__scope"><?php esc_html_e( 'Review content context, site knowledge, image candidates, and governed handoffs. Human editors own article text; final WordPress writes still require Core proposal approval.', 'magick-ai-toolbox' ); ?></p>

			<nav class="magick-ai-toolbox__tabs" data-toolbox-tabs aria-label="<?php esc_attr_e( 'Toolbox sections', 'magick-ai-toolbox' ); ?>">
				<button type="button" class="magick-ai-toolbox__tab is-active" data-toolbox-tab-target="context" aria-selected="true"><?php esc_html_e( 'Content Context', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="magick-ai-toolbox__tab" data-toolbox-tab-target="site-knowledge" aria-selected="false"><?php esc_html_e( 'Site Knowledge', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="magick-ai-toolbox__tab" data-toolbox-tab-target="tools" aria-selected="false"><?php esc_html_e( 'Content Support', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="magick-ai-toolbox__tab" data-toolbox-tab-target="cloud-checks" aria-selected="false"><?php esc_html_e( 'Cloud Checks', 'magick-ai-toolbox' ); ?></button>
			</nav>

			<section class="magick-ai-toolbox__panel" data-toolbox-tab-panel="context" aria-label="<?php esc_attr_e( 'Content context', 'magick-ai-toolbox' ); ?>">
				<?php $this->render_content_context_form( $content_context ); ?>
			</section>

			<section class="magick-ai-toolbox__panel" data-toolbox-tab-panel="site-knowledge" aria-label="<?php esc_attr_e( 'Site knowledge', 'magick-ai-toolbox' ); ?>" hidden>
				<?php $this->render_site_knowledge_panel(); ?>
			</section>

			<section class="magick-ai-toolbox__panel" data-toolbox-tab-panel="tools" aria-label="<?php esc_attr_e( 'Try Toolbox actions', 'magick-ai-toolbox' ); ?>" hidden>
				<?php $this->render_tool_cards(); ?>
			</section>

			<section class="magick-ai-toolbox__panel" data-toolbox-tab-panel="cloud-checks" aria-label="<?php esc_attr_e( 'Cloud checks', 'magick-ai-toolbox' ); ?>" hidden>
				<?php $this->render_cloud_checks_panel( $settings ); ?>
			</section>
		</div>
		<?php
	}

	private function render_site_knowledge_panel(): void {
		?>
		<div class="magick-ai-toolbox__panel-header">
			<h2><?php esc_html_e( 'Site Knowledge', 'magick-ai-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Start Cloud-managed indexing for public posts, pages, and approved comments. Toolbox collects a bounded manifest; Cloud owns embeddings, vector storage, and run detail.', 'magick-ai-toolbox' ); ?></p>
		</div>

		<div class="magick-ai-toolbox__site-knowledge" data-toolbox-site-knowledge>
			<section class="magick-ai-toolbox__card">
				<div class="magick-ai-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Index status', 'magick-ai-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Read-only Cloud coverage summary for this WordPress site.', 'magick-ai-toolbox' ); ?></p>
					</div>
					<button type="button" class="button" data-toolbox-site-knowledge-status><?php esc_html_e( 'Refresh status', 'magick-ai-toolbox' ); ?></button>
				</div>
				<div class="magick-ai-toolbox__knowledge-summary" data-toolbox-site-knowledge-summary>
					<div class="magick-ai-toolbox__result-notice is-pending"><?php esc_html_e( 'Status has not been loaded yet.', 'magick-ai-toolbox' ); ?></div>
				</div>
			</section>

			<section class="magick-ai-toolbox__card">
				<h3><?php esc_html_e( 'Index actions', 'magick-ai-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'Create or refresh the Cloud index for current public site content. Advanced cleanup stays in Cloud operations.', 'magick-ai-toolbox' ); ?></p>
				<form data-toolbox-site-knowledge-sync>
					<input type="hidden" name="sync_mode" value="refresh" />
					<input type="hidden" name="max_posts" value="20" />
					<p class="description"><?php esc_html_e( 'Toolbox sends the latest public posts and pages. Approved comments are included only when Cloud comments indexing is enabled.', 'magick-ai-toolbox' ); ?></p>
					<div class="magick-ai-toolbox__inline-actions">
						<button
							type="submit"
							class="button button-primary"
							data-toolbox-site-knowledge-sync-submit
							data-start-label="<?php esc_attr_e( 'Start indexing', 'magick-ai-toolbox' ); ?>"
							data-refresh-label="<?php esc_attr_e( 'Refresh index', 'magick-ai-toolbox' ); ?>"
						><?php esc_html_e( 'Start indexing', 'magick-ai-toolbox' ); ?></button>
					</div>
					<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
				</form>
			</section>
		</div>
		<?php
	}

	private function render_site_knowledge_search_check( bool $advanced = false ): void {
		?>
		<form class="magick-ai-toolbox__inline-form" data-toolbox-site-knowledge-search>
			<h3><?php echo esc_html( $advanced ? __( 'Advanced search check', 'magick-ai-toolbox' ) : __( 'Search check', 'magick-ai-toolbox' ) ); ?></h3>
			<p><?php esc_html_e( 'Run a read-only query against Cloud-managed site knowledge.', 'magick-ai-toolbox' ); ?></p>
			<label>
				<span><?php esc_html_e( 'Query', 'magick-ai-toolbox' ); ?></span>
				<input type="text" name="query" placeholder="<?php esc_attr_e( 'Search public site knowledge', 'magick-ai-toolbox' ); ?>" />
			</label>
			<?php if ( $advanced ) : ?>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Intent', 'magick-ai-toolbox' ); ?></span>
						<select name="intent">
							<option value="site_search"><?php esc_html_e( 'Site search', 'magick-ai-toolbox' ); ?></option>
							<option value="faq_candidates"><?php esc_html_e( 'FAQ candidates', 'magick-ai-toolbox' ); ?></option>
							<option value="content_gap_analysis"><?php esc_html_e( 'Content gaps', 'magick-ai-toolbox' ); ?></option>
							<option value="duplicate_check"><?php esc_html_e( 'Duplicate check', 'magick-ai-toolbox' ); ?></option>
							<option value="internal_links"><?php esc_html_e( 'Internal links', 'magick-ai-toolbox' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Source types', 'magick-ai-toolbox' ); ?></span>
						<input type="text" name="source_types" value="post,page" />
					</label>
				</div>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Current post ID', 'magick-ai-toolbox' ); ?></span>
						<input type="number" name="current_post_id" min="0" value="0" />
					</label>
					<label>
						<span><?php esc_html_e( 'Max results', 'magick-ai-toolbox' ); ?></span>
						<input type="number" name="max_results" min="1" max="20" value="8" />
					</label>
				</div>
			<?php else : ?>
				<input type="hidden" name="intent" value="site_search" />
				<input type="hidden" name="source_types" value="post,page" />
				<input type="hidden" name="current_post_id" value="0" />
				<input type="hidden" name="max_results" value="8" />
			<?php endif; ?>
			<button type="submit" class="button"><?php echo esc_html( $advanced ? __( 'Search index', 'magick-ai-toolbox' ) : __( 'Run check', 'magick-ai-toolbox' ) ); ?></button>
			<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_cloud_checks_panel( array $settings ): void {
		$image_ready = $this->settings->has_image_source_provider();
		?>
		<div class="magick-ai-toolbox__panel-header">
			<h2><?php esc_html_e( 'Cloud Checks', 'magick-ai-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Run Cloud-managed search, image-source, and site-knowledge checks from Toolbox.', 'magick-ai-toolbox' ); ?></p>
		</div>

		<div class="magick-ai-toolbox__cloud-check-workspace" data-toolbox-cloud-checks>
			<nav class="magick-ai-toolbox__cloud-check-tabs" aria-label="<?php esc_attr_e( 'Cloud check groups', 'magick-ai-toolbox' ); ?>">
				<button type="button" class="magick-ai-toolbox__cloud-check-tab is-active" data-toolbox-cloud-check-target="search" aria-selected="true">
					<span><?php esc_html_e( 'Search', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Cloud managed', 'magick-ai-toolbox' ); ?></small>
				</button>
				<button type="button" class="magick-ai-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="image" aria-selected="false">
					<span><?php esc_html_e( 'Image', 'magick-ai-toolbox' ); ?></span>
					<small><?php echo esc_html( $image_ready ? __( 'Cloud managed', 'magick-ai-toolbox' ) : __( 'Cloud connection needed', 'magick-ai-toolbox' ) ); ?></small>
				</button>
				<button type="button" class="magick-ai-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="vector" aria-selected="false">
					<span><?php esc_html_e( 'Vector', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Cloud managed', 'magick-ai-toolbox' ); ?></small>
				</button>
			</nav>

			<div class="magick-ai-toolbox__cloud-check-panels">
				<section class="magick-ai-toolbox__card" data-toolbox-cloud-check-panel="search">
					<div class="magick-ai-toolbox__cloud-check-group-workspace" data-toolbox-cloud-check-groups>
						<nav class="magick-ai-toolbox__cloud-check-group-list" aria-label="<?php esc_attr_e( 'Search checks', 'magick-ai-toolbox' ); ?>">
							<button type="button" class="magick-ai-toolbox__cloud-check-group-button is-active" data-toolbox-cloud-check-group-target="search-test" aria-selected="true">
								<span><?php esc_html_e( 'Search test', 'magick-ai-toolbox' ); ?></span>
								<small><?php esc_html_e( 'Read-only query', 'magick-ai-toolbox' ); ?></small>
							</button>
							<button type="button" class="magick-ai-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="search-diagnostic" aria-selected="false">
								<span><?php esc_html_e( 'Diagnostic', 'magick-ai-toolbox' ); ?></span>
								<small><?php esc_html_e( 'Workflow evidence', 'magick-ai-toolbox' ); ?></small>
							</button>
						</nav>
						<div>
							<div class="magick-ai-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="search-test">
								<form class="magick-ai-toolbox__inline-form" data-toolbox-endpoint="web-search/test">
									<h3><?php esc_html_e( 'Cloud search test', 'magick-ai-toolbox' ); ?></h3>
									<label>
										<span><?php esc_html_e( 'Query', 'magick-ai-toolbox' ); ?></span>
										<input type="text" name="query" value="latest WordPress AI search trends" />
									</label>
									<div class="magick-ai-toolbox__split">
										<label>
											<span><?php esc_html_e( 'Intent', 'magick-ai-toolbox' ); ?></span>
											<select name="intent">
												<option value="news"><?php esc_html_e( 'News', 'magick-ai-toolbox' ); ?></option>
												<option value="fact_check"><?php esc_html_e( 'Fact check', 'magick-ai-toolbox' ); ?></option>
												<option value="writing_context"><?php esc_html_e( 'Writing context', 'magick-ai-toolbox' ); ?></option>
												<option value="competitor_research"><?php esc_html_e( 'Competitor research', 'magick-ai-toolbox' ); ?></option>
												<option value="source_discovery"><?php esc_html_e( 'Source discovery', 'magick-ai-toolbox' ); ?></option>
												<option value="external_links"><?php esc_html_e( 'External links', 'magick-ai-toolbox' ); ?></option>
											</select>
										</label>
										<label>
											<span><?php esc_html_e( 'Provider', 'magick-ai-toolbox' ); ?></span>
											<select name="provider">
												<option value="auto"><?php esc_html_e( 'Auto', 'magick-ai-toolbox' ); ?></option>
												<option value="tavily"><?php esc_html_e( 'Tavily', 'magick-ai-toolbox' ); ?></option>
												<option value="bocha"><?php esc_html_e( 'Bocha', 'magick-ai-toolbox' ); ?></option>
												<option value="apify"><?php esc_html_e( 'Apify', 'magick-ai-toolbox' ); ?></option>
											</select>
										</label>
									</div>
									<div class="magick-ai-toolbox__split">
										<label>
											<span><?php esc_html_e( 'Max results', 'magick-ai-toolbox' ); ?></span>
											<input type="number" name="max_results" min="1" max="5" value="3" />
										</label>
										<label>
											<span><?php esc_html_e( 'Recency days', 'magick-ai-toolbox' ); ?></span>
											<input type="number" name="recency_days" min="0" max="30" value="7" />
										</label>
									</div>
									<label class="magick-ai-toolbox__check">
										<input type="checkbox" name="enhance_with_reader" value="1" />
										<span><?php esc_html_e( 'Enhance returned pages with Jina Reader when Cloud enables it', 'magick-ai-toolbox' ); ?></span>
									</label>
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Run search test', 'magick-ai-toolbox' ); ?></button>
									<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
								</form>
							</div>
							<div class="magick-ai-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="search-diagnostic" hidden>
								<form class="magick-ai-toolbox__inline-form" data-toolbox-endpoint="web-search/diagnostics">
									<h3><?php esc_html_e( 'Workflow diagnostic', 'magick-ai-toolbox' ); ?></h3>
									<p><?php esc_html_e( 'Run a Toolbox content workflow and verify whether it attached Cloud web search evidence.', 'magick-ai-toolbox' ); ?></p>
									<div class="magick-ai-toolbox__split">
										<label>
											<span><?php esc_html_e( 'Scenario', 'magick-ai-toolbox' ); ?></span>
											<select name="scenario">
												<option value="article_assistant"><?php esc_html_e( 'Article Assistant', 'magick-ai-toolbox' ); ?></option>
												<option value="discoverability"><?php esc_html_e( 'Discoverability', 'magick-ai-toolbox' ); ?></option>
												<option value="publish_preflight"><?php esc_html_e( 'Publish preflight', 'magick-ai-toolbox' ); ?></option>
											</select>
										</label>
										<label>
											<span><?php esc_html_e( 'Topic', 'magick-ai-toolbox' ); ?></span>
											<input type="text" name="topic" value="latest WordPress AI search trends" />
										</label>
									</div>
									<label>
										<span><?php esc_html_e( 'Working title', 'magick-ai-toolbox' ); ?></span>
										<input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional title override', 'magick-ai-toolbox' ); ?>" />
									</label>
									<button type="submit" class="button"><?php esc_html_e( 'Run workflow diagnostic', 'magick-ai-toolbox' ); ?></button>
									<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
								</form>
							</div>
						</div>
					</div>
				</section>

				<section class="magick-ai-toolbox__card" data-toolbox-cloud-check-panel="image" hidden>
					<div class="magick-ai-toolbox__cloud-check-group-workspace" data-toolbox-cloud-check-groups>
						<nav class="magick-ai-toolbox__cloud-check-group-list" aria-label="<?php esc_attr_e( 'Image checks', 'magick-ai-toolbox' ); ?>">
							<button type="button" class="magick-ai-toolbox__cloud-check-group-button is-active" data-toolbox-cloud-check-group-target="image-smoke" aria-selected="true">
								<span><?php esc_html_e( 'Smoke test', 'magick-ai-toolbox' ); ?></span>
								<small><?php esc_html_e( 'Candidates', 'magick-ai-toolbox' ); ?></small>
							</button>
						</nav>
						<div>
							<div class="magick-ai-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="image-smoke">
								<?php $this->render_image_source_candidates_smoke_form(); ?>
							</div>
						</div>
					</div>
				</section>

				<section class="magick-ai-toolbox__card" data-toolbox-cloud-check-panel="vector" hidden>
					<div class="magick-ai-toolbox__cloud-check-group-workspace" data-toolbox-cloud-check-groups>
						<nav class="magick-ai-toolbox__cloud-check-group-list" aria-label="<?php esc_attr_e( 'Vector checks', 'magick-ai-toolbox' ); ?>">
							<button type="button" class="magick-ai-toolbox__cloud-check-group-button is-active" data-toolbox-cloud-check-group-target="vector-status" aria-selected="true">
								<span><?php esc_html_e( 'Status', 'magick-ai-toolbox' ); ?></span>
								<small><?php esc_html_e( 'Coverage', 'magick-ai-toolbox' ); ?></small>
							</button>
							<button type="button" class="magick-ai-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="vector-search" aria-selected="false">
								<span><?php esc_html_e( 'Search check', 'magick-ai-toolbox' ); ?></span>
								<small><?php esc_html_e( 'Read-only query', 'magick-ai-toolbox' ); ?></small>
							</button>
						</nav>
						<div data-toolbox-site-knowledge>
							<div class="magick-ai-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="vector-status">
								<div class="magick-ai-toolbox__section-heading">
									<div>
										<h3><?php esc_html_e( 'Status', 'magick-ai-toolbox' ); ?></h3>
										<p><?php esc_html_e( 'Cloud coverage summary for this WordPress site.', 'magick-ai-toolbox' ); ?></p>
									</div>
									<div class="magick-ai-toolbox__inline-actions">
										<button type="button" class="button" data-toolbox-site-knowledge-status><?php esc_html_e( 'Refresh', 'magick-ai-toolbox' ); ?></button>
										<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=magick-ai-toolbox&toolbox_tab=site-knowledge' ) ); ?>"><?php esc_html_e( 'Manage index', 'magick-ai-toolbox' ); ?></a>
									</div>
								</div>
								<div class="magick-ai-toolbox__knowledge-summary" data-toolbox-site-knowledge-summary>
									<div class="magick-ai-toolbox__result-notice is-pending"><?php esc_html_e( 'Status has not been loaded yet.', 'magick-ai-toolbox' ); ?></div>
								</div>
							</div>
							<div class="magick-ai-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="vector-search" hidden>
								<?php $this->render_site_knowledge_search_check(); ?>
							</div>
						</div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	private function render_content_context_form( array $context ): void {
		$proposal_fields = array(
			'seo_title'             => __( 'SEO title', 'magick-ai-toolbox' ),
			'seo_description'       => __( 'SEO description', 'magick-ai-toolbox' ),
			'slug'                  => __( 'Slug', 'magick-ai-toolbox' ),
			'excerpt'               => __( 'Excerpt', 'magick-ai-toolbox' ),
			'faq'                   => __( 'FAQ', 'magick-ai-toolbox' ),
			'answer_summary'        => __( 'Answer summary', 'magick-ai-toolbox' ),
			'geo_summary'           => __( 'GEO summary', 'magick-ai-toolbox' ),
			'structured_data_hints' => __( 'Structured data hints', 'magick-ai-toolbox' ),
		);
		$preview = wp_json_encode( $this->settings->get_content_context_for_ability(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		?>
		<div class="magick-ai-toolbox__panel-header">
			<h2><?php esc_html_e( 'Content Context', 'magick-ai-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Fill a compact site brief, then tune SEO, AEO, and GEO guidance. Draft buttons only prefill this form; nothing is saved until you click Save content context. After saving, use Content Support to test briefs, site knowledge, and image candidates.', 'magick-ai-toolbox' ); ?></p>
		</div>

		<form class="magick-ai-toolbox__settings-form" method="post" action="options.php" data-toolbox-context-form>
			<?php settings_fields( 'magick_ai_toolbox_content_context' ); ?>

			<div class="magick-ai-toolbox__draft-actions" aria-label="<?php esc_attr_e( 'Content context draft actions', 'magick-ai-toolbox' ); ?>">
				<button type="button" class="button" data-toolbox-context-draft="aiBlog"><?php esc_html_e( 'Use AI tech blog template', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-draft="site"><?php esc_html_e( 'Draft from current site content', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-clear><?php esc_html_e( 'Clear form', 'magick-ai-toolbox' ); ?></button>
				<span><?php esc_html_e( 'Drafts are editable suggestions and do not change posts, media, SEO meta, or provider settings.', 'magick-ai-toolbox' ); ?></span>
			</div>

			<div class="magick-ai-toolbox__context-workspace" data-toolbox-context-sections>
				<nav class="magick-ai-toolbox__context-tabs" aria-label="<?php esc_attr_e( 'Content context sections', 'magick-ai-toolbox' ); ?>">
					<button type="button" class="magick-ai-toolbox__context-tab is-active" data-toolbox-context-target="brief" aria-selected="true">
						<span><?php esc_html_e( 'Brief', 'magick-ai-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Start here', 'magick-ai-toolbox' ); ?></small>
					</button>
					<button type="button" class="magick-ai-toolbox__context-tab" data-toolbox-context-target="seo" aria-selected="false">
						<span><?php esc_html_e( 'SEO', 'magick-ai-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Search snippets', 'magick-ai-toolbox' ); ?></small>
					</button>
					<button type="button" class="magick-ai-toolbox__context-tab" data-toolbox-context-target="aeo" aria-selected="false">
						<span><?php esc_html_e( 'AEO', 'magick-ai-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Answer shape', 'magick-ai-toolbox' ); ?></small>
					</button>
					<button type="button" class="magick-ai-toolbox__context-tab" data-toolbox-context-target="geo" aria-selected="false">
						<span><?php esc_html_e( 'GEO', 'magick-ai-toolbox' ); ?></span>
						<small><?php esc_html_e( 'AI citation signals', 'magick-ai-toolbox' ); ?></small>
					</button>
					<button type="button" class="magick-ai-toolbox__context-tab" data-toolbox-context-target="boundaries" aria-selected="false">
						<span><?php esc_html_e( 'Boundaries', 'magick-ai-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Claims and preview', 'magick-ai-toolbox' ); ?></small>
					</button>
				</nav>

				<div class="magick-ai-toolbox__context-panels">
					<section class="magick-ai-toolbox__card" data-toolbox-context-panel="brief">
						<h2><?php esc_html_e( 'Brief', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Define who the site is for and how suggestions should sound. This is the minimum useful setup.', 'magick-ai-toolbox' ); ?></p>
						<div class="magick-ai-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="magick-ai-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'Brief fields', 'magick-ai-toolbox' ); ?>">
								<button type="button" class="magick-ai-toolbox__context-group-button is-active" data-toolbox-context-group-target="brief-profile" aria-selected="true">
									<span><?php esc_html_e( 'Site profile', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Positioning', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="brief-audience" aria-selected="false">
									<span><?php esc_html_e( 'Audience', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Readers', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="brief-voice" aria-selected="false">
									<span><?php esc_html_e( 'Voice', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Tone', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="brief-keywords" aria-selected="false">
									<span><?php esc_html_e( 'Keywords', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Primary terms', 'magick-ai-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="magick-ai-toolbox__context-group-panels">
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-profile">
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'Site profile', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Tell AI what this site is, who it helps, and what kind of suggestion it should produce first.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'site_positioning', __( 'Site positioning', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-audience" hidden>
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'Audience', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'List the reader groups AI should optimize explanations, examples, and terminology for.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'target_audience', __( 'Target audience', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-voice" hidden>
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'Voice', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Set the writing posture, level of detail, and phrases AI should favor or avoid.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'brand_voice', __( 'Brand voice', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-keywords" hidden>
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'Keywords', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Use this short list as the main topic vocabulary before adding SEO-specific long-tail terms.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'primary_keywords', __( 'Primary keywords', 'magick-ai-toolbox' ), $context ); ?>
								</section>
							</div>
						</div>
					</section>

					<section class="magick-ai-toolbox__card" data-toolbox-context-panel="seo" hidden>
						<h2><?php esc_html_e( 'SEO', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Control search-oriented metadata, keyword coverage, and which SEO fields AI may suggest.', 'magick-ai-toolbox' ); ?></p>
						<div class="magick-ai-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="magick-ai-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'SEO fields', 'magick-ai-toolbox' ); ?>">
								<button type="button" class="magick-ai-toolbox__context-group-button is-active" data-toolbox-context-group-target="seo-keywords" aria-selected="true">
									<span><?php esc_html_e( 'Keywords', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Long-tail terms', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="seo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Search guidance', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="seo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'magick-ai-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="magick-ai-toolbox__context-group-panels">
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-keywords">
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'SEO keywords', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Add supporting long-tail phrases here. Primary keywords stay in the Brief section so the first setup path remains obvious.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'long_tail_keywords', __( 'Long-tail keywords', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-rules" hidden>
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'SEO rules', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Describe title, description, slug, excerpt, and internal-link preferences for proposal-ready suggestions.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'seo_rules', __( 'SEO rules', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-fields" hidden>
									<fieldset class="magick-ai-toolbox__check-grid">
										<legend><?php esc_html_e( 'SEO fields AI may suggest', 'magick-ai-toolbox' ); ?></legend>
										<?php foreach ( array( 'seo_title', 'seo_description', 'slug', 'excerpt' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="magick-ai-toolbox__card" data-toolbox-context-panel="aeo" hidden>
						<h2><?php esc_html_e( 'AEO', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Shape answer-engine output: direct answers, FAQs, definitions, and step-style responses.', 'magick-ai-toolbox' ); ?></p>
						<div class="magick-ai-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="magick-ai-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'AEO fields', 'magick-ai-toolbox' ); ?>">
								<button type="button" class="magick-ai-toolbox__context-group-button is-active" data-toolbox-context-group-target="aeo-rules" aria-selected="true">
									<span><?php esc_html_e( 'Rules', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Answer guidance', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="aeo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'FAQ and summary', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="aeo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'magick-ai-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="magick-ai-toolbox__context-group-panels">
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-rules">
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'AEO rules', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Start with a direct answer, then add conditions, steps, limits, and short followups.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'aeo_rules', __( 'AEO rules', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_faq_generation', __( 'Allow FAQ suggestions', 'magick-ai-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_aeo_summary', __( 'Allow AEO answer summary suggestions', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-fields" hidden>
									<fieldset class="magick-ai-toolbox__check-grid">
										<legend><?php esc_html_e( 'AEO fields AI may suggest', 'magick-ai-toolbox' ); ?></legend>
										<?php foreach ( array( 'faq', 'answer_summary' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="magick-ai-toolbox__card" data-toolbox-context-panel="geo" hidden>
						<h2><?php esc_html_e( 'GEO', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Guide AI-readable entity signals, standalone conclusions, and citation-friendly summaries.', 'magick-ai-toolbox' ); ?></p>
						<div class="magick-ai-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="magick-ai-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'GEO fields', 'magick-ai-toolbox' ); ?>">
								<button type="button" class="magick-ai-toolbox__context-group-button is-active" data-toolbox-context-group-target="geo-entities" aria-selected="true">
									<span><?php esc_html_e( 'Entities', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Signals', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="geo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Summary guidance', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="geo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'GEO and schema', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="geo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'magick-ai-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="magick-ai-toolbox__context-group-panels">
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-entities">
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'Entities', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'List people, products, standards, projects, and concepts AI should recognize as important context.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'entity_keywords', __( 'Entity keywords', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-rules" hidden>
									<div class="magick-ai-toolbox__example">
										<strong><?php esc_html_e( 'GEO rules', 'magick-ai-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Keep key conclusions standalone, define important entities, and separate implemented facts from plans.', 'magick-ai-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'geo_rules', __( 'GEO rules', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_geo_summary', __( 'Allow GEO summary suggestions', 'magick-ai-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_structured_data_suggestions', __( 'Allow structured data suggestions', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-fields" hidden>
									<fieldset class="magick-ai-toolbox__check-grid">
										<legend><?php esc_html_e( 'GEO fields AI may suggest', 'magick-ai-toolbox' ); ?></legend>
										<?php foreach ( array( 'geo_summary', 'structured_data_hints' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="magick-ai-toolbox__card" data-toolbox-context-panel="boundaries" hidden>
						<h2><?php esc_html_e( 'Boundaries', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Limit what AI can claim and inspect the read-only payload exposed to callers.', 'magick-ai-toolbox' ); ?></p>
						<div class="magick-ai-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="magick-ai-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'Boundary fields', 'magick-ai-toolbox' ); ?>">
								<button type="button" class="magick-ai-toolbox__context-group-button is-active" data-toolbox-context-group-target="boundaries-allowed" aria-selected="true">
									<span><?php esc_html_e( 'Allowed claims', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Can say', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-forbidden" aria-selected="false">
									<span><?php esc_html_e( 'Forbidden claims', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Must not say', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-exceptions" aria-selected="false">
									<span><?php esc_html_e( 'Exceptions', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Special cases', 'magick-ai-toolbox' ); ?></small>
								</button>
								<button type="button" class="magick-ai-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-preview" aria-selected="false">
									<span><?php esc_html_e( 'Ability preview', 'magick-ai-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Read-only payload', 'magick-ai-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="magick-ai-toolbox__context-group-panels">
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-allowed">
									<?php $this->render_context_list_field( 'allowed_claims', __( 'Allowed claims', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-forbidden" hidden>
									<?php $this->render_context_list_field( 'forbidden_claims', __( 'Forbidden claims', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-exceptions" hidden>
									<?php $this->render_context_list_field( 'disallowed_topics', __( 'Disallowed topics', 'magick-ai-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'cautious_topics', __( 'Cautious topics', 'magick-ai-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'no_structured_output_topics', __( 'No structured output topics', 'magick-ai-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'human_confirmation_required', __( 'Human confirmation required', 'magick-ai-toolbox' ), $context ); ?>
								</section>
								<section class="magick-ai-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-preview" hidden>
									<pre class="magick-ai-toolbox__result"><?php echo esc_html( (string) $preview ); ?></pre>
								</section>
							</div>
						</div>
					</section>
				</div>
			</div>

			<p class="description"><?php esc_html_e( 'Final WordPress writes still require Core proposal approval; third-party AI receives this context as suggestion-only guidance.', 'magick-ai-toolbox' ); ?></p>
			<?php submit_button( __( 'Save content context', 'magick-ai-toolbox' ) ); ?>
		</form>

		<?php
	}

	private function render_tool_cards(): void {
		$tools = array(
			array(
				'id'          => 'article-brief',
				'endpoint'    => 'flows/article-brief',
				'title'       => __( 'Content Support Brief', 'magick-ai-toolbox' ),
				'description' => __( 'Build source notes, outline guidance, image candidates, and governance handoff notes around a human-written article.', 'magick-ai-toolbox' ),
				'field'       => 'topic',
				'placeholder' => __( 'Article topic', 'magick-ai-toolbox' ),
				'button'      => __( 'Build brief', 'magick-ai-toolbox' ),
			),
			array(
				'id'          => 'article-assistant',
				'endpoint'    => 'flows/article-assistant',
				'title'       => __( 'Article Assistant Fallback', 'magick-ai-toolbox' ),
				'description' => __( 'Assemble one local workbench artifact from support abilities and an optional reviewed draft; it does not write the article body.', 'magick-ai-toolbox' ),
				'custom'      => 'article_assistant',
			),
			array(
				'id'          => 'article-plan',
				'endpoint'    => 'flows/article-plan',
				'title'       => __( 'Reviewed Draft Handoff', 'magick-ai-toolbox' ),
				'description' => __( 'Prepare a Core-ready article_write_plan only after a human-reviewed draft exists. Toolbox does not submit or approve the proposal.', 'magick-ai-toolbox' ),
				'custom'      => 'article_plan',
			),
			array(
				'id'          => 'media-brief',
				'endpoint'    => 'flows/media-brief',
				'title'       => __( 'Media Brief', 'magick-ai-toolbox' ),
				'description' => __( 'Use an existing post id to plan image prompts and media SEO actions.', 'magick-ai-toolbox' ),
				'field'       => 'post_id',
				'placeholder' => __( 'Post ID', 'magick-ai-toolbox' ),
				'button'      => __( 'Plan media', 'magick-ai-toolbox' ),
			),
			array(
				'id'          => 'media-derivative',
				'endpoint'    => 'media-derivative-handoff',
				'title'       => __( 'Optimize Existing Image', 'magick-ai-toolbox' ),
				'description' => __( 'Choose a media-library image, review metadata, generate a preview, then submit one Core optimization proposal.', 'magick-ai-toolbox' ),
				'custom'      => 'media_derivative',
			),
			array(
				'id'          => 'image-candidate-adoption',
				'endpoint'    => 'flows/image-candidate-adoption-plan',
				'title'       => __( 'Adopt New Image', 'magick-ai-toolbox' ),
				'description' => __( 'Use a reviewed stock, generated, owned, or external image as a media import proposal.', 'magick-ai-toolbox' ),
				'custom'      => 'image_candidate_adoption',
			),
		);
		?>
		<div class="magick-ai-toolbox__tool-workspace" data-toolbox-tools>
			<div class="magick-ai-toolbox__tool-list" aria-label="<?php esc_attr_e( 'Tool actions', 'magick-ai-toolbox' ); ?>">
				<?php foreach ( $tools as $index => $tool ) : ?>
					<button type="button" class="magick-ai-toolbox__tool-button <?php echo 0 === $index ? 'is-active' : ''; ?>" data-toolbox-tool-target="<?php echo esc_attr( (string) $tool['id'] ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>">
						<span><?php echo esc_html( (string) $tool['title'] ); ?></span>
						<small><?php echo esc_html( (string) $tool['description'] ); ?></small>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="magick-ai-toolbox__tool-panels">
				<?php
				foreach ( $tools as $index => $tool ) {
					if ( 'article_assistant' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_article_assistant_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							0 === $index
						);
						continue;
					}
					if ( 'article_plan' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_article_plan_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							0 === $index
						);
						continue;
					}
					if ( 'media_derivative' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_media_derivative_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							0 === $index
						);
						continue;
					}
					if ( 'image_candidate_adoption' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_image_candidate_adoption_tool(
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

	private function render_image_source_candidates_smoke_form(): void {
		?>
		<form class="magick-ai-toolbox__inline-form" data-toolbox-endpoint="image-candidates">
			<h3><?php esc_html_e( 'Image source smoke test', 'magick-ai-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Test Cloud-managed Unsplash/Pixabay/Pexels image-source candidates and preserve attribution metadata.', 'magick-ai-toolbox' ); ?></p>
			<div class="magick-ai-toolbox__example">
				<strong><?php esc_html_e( 'Cloud smoke test', 'magick-ai-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'A successful result shows Cloud runtime, provider mode, candidate count, preview image, suggested filename, and license review status. This does not import media or write WordPress.', 'magick-ai-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Image search query', 'magick-ai-toolbox' ); ?></span>
				<input type="text" name="query" value="<?php esc_attr_e( 'wordpress article hero image', 'magick-ai-toolbox' ); ?>" />
			</label>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Provider', 'magick-ai-toolbox' ); ?></span>
					<select name="provider">
						<option value="auto"><?php esc_html_e( 'Cloud auto', 'magick-ai-toolbox' ); ?></option>
						<option value="unsplash"><?php esc_html_e( 'Unsplash', 'magick-ai-toolbox' ); ?></option>
						<option value="pixabay"><?php esc_html_e( 'Pixabay', 'magick-ai-toolbox' ); ?></option>
						<option value="pexels"><?php esc_html_e( 'Pexels', 'magick-ai-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Orientation', 'magick-ai-toolbox' ); ?></span>
					<select name="orientation">
						<option value="landscape"><?php esc_html_e( 'Landscape', 'magick-ai-toolbox' ); ?></option>
						<option value="portrait"><?php esc_html_e( 'Portrait', 'magick-ai-toolbox' ); ?></option>
						<option value="squarish"><?php esc_html_e( 'Squarish', 'magick-ai-toolbox' ); ?></option>
						<option value=""><?php esc_html_e( 'Any', 'magick-ai-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Candidate count', 'magick-ai-toolbox' ); ?></span>
					<input type="number" min="1" max="8" step="1" name="per_page" value="3" />
				</label>
				<label>
					<span><?php esc_html_e( 'Color filter', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="color" placeholder="<?php esc_attr_e( 'Optional Unsplash color filter', 'magick-ai-toolbox' ); ?>" />
				</label>
			</div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Test Cloud image source', 'magick-ai-toolbox' ); ?></button>
			<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_image_candidate_adoption_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		?>
		<form class="magick-ai-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="magick-ai-toolbox__example">
				<strong><?php esc_html_e( 'Button flow', 'magick-ai-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Pick or paste one reviewed image, add basic media details, then submit the returned plan to Core for approval. Toolbox does not import media directly.', 'magick-ai-toolbox' ); ?></span>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Selected image URL', 'magick-ai-toolbox' ); ?></span>
					<input type="url" name="download_url" placeholder="<?php esc_attr_e( 'https://example.com/image.jpg', 'magick-ai-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Source type', 'magick-ai-toolbox' ); ?></span>
					<select name="source_type">
						<option value="stock"><?php esc_html_e( 'Stock image', 'magick-ai-toolbox' ); ?></option>
						<option value="ai_generated"><?php esc_html_e( 'AI generated', 'magick-ai-toolbox' ); ?></option>
						<option value="owned"><?php esc_html_e( 'Owned image', 'magick-ai-toolbox' ); ?></option>
						<option value="external"><?php esc_html_e( 'External image', 'magick-ai-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Source page', 'magick-ai-toolbox' ); ?></span>
					<input type="url" name="source_url" placeholder="<?php esc_attr_e( 'License or source page URL', 'magick-ai-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Provider', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="provider" placeholder="<?php esc_attr_e( 'unsplash, pixabay, openai, manual', 'magick-ai-toolbox' ); ?>" />
				</label>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Media title', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional media title', 'magick-ai-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Alt text', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="alt" placeholder="<?php esc_attr_e( 'Describe the image for accessibility', 'magick-ai-toolbox' ); ?>" />
				</label>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Attach to post ID', 'magick-ai-toolbox' ); ?></span>
					<input type="number" min="1" step="1" name="post_id" placeholder="<?php esc_attr_e( 'Optional post ID', 'magick-ai-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Approved file name', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="file_name" placeholder="<?php esc_attr_e( 'Optional approved filename', 'magick-ai-toolbox' ); ?>" />
				</label>
			</div>
			<label class="magick-ai-toolbox__check">
				<input type="checkbox" name="set_featured_image" value="1" />
				<span><?php esc_html_e( 'Set as featured image after approval', 'magick-ai-toolbox' ); ?></span>
			</label>
			<label>
				<span><?php esc_html_e( 'Attribution', 'magick-ai-toolbox' ); ?></span>
				<textarea name="attribution_text" rows="2" placeholder="<?php esc_attr_e( 'Photographer, license, prompt disclosure, or required credit.', 'magick-ai-toolbox' ); ?>"></textarea>
			</label>
			<details class="magick-ai-toolbox__result-details">
				<summary><?php esc_html_e( 'Advanced candidate details', 'magick-ai-toolbox' ); ?></summary>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Thumbnail URL', 'magick-ai-toolbox' ); ?></span>
						<input type="url" name="thumbnail_url" placeholder="<?php esc_attr_e( 'Optional preview URL', 'magick-ai-toolbox' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'License review', 'magick-ai-toolbox' ); ?></span>
						<select name="license_review_status">
							<option value="required"><?php esc_html_e( 'Review required', 'magick-ai-toolbox' ); ?></option>
							<option value="reviewed"><?php esc_html_e( 'Reviewed', 'magick-ai-toolbox' ); ?></option>
							<option value="not_required"><?php esc_html_e( 'Not required', 'magick-ai-toolbox' ); ?></option>
						</select>
					</label>
				</div>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Prompt', 'magick-ai-toolbox' ); ?></span>
						<input type="text" name="prompt" placeholder="<?php esc_attr_e( 'Optional generation prompt', 'magick-ai-toolbox' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Model', 'magick-ai-toolbox' ); ?></span>
						<input type="text" name="model" placeholder="<?php esc_attr_e( 'Optional generation model', 'magick-ai-toolbox' ); ?>" />
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Candidate JSON', 'magick-ai-toolbox' ); ?></span>
					<textarea name="image_candidate" rows="6" placeholder="<?php esc_attr_e( 'Optional. Paste one image_candidate.v1 object to override the fields above.', 'magick-ai-toolbox' ); ?>"></textarea>
				</label>
			</details>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Build import proposal plan', 'magick-ai-toolbox' ); ?></button>
			<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_article_assistant_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		?>
		<form class="magick-ai-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="magick-ai-toolbox__example">
				<strong><?php esc_html_e( 'Local workbench', 'magick-ai-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'This composes a planning artifact only. It does not run a cloud writer, submit a proposal, approve a proposal, or write WordPress content.', 'magick-ai-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Topic', 'magick-ai-toolbox' ); ?></span>
				<input type="text" name="topic" placeholder="<?php esc_attr_e( 'Article topic', 'magick-ai-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Working title', 'magick-ai-toolbox' ); ?></span>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional title override', 'magick-ai-toolbox' ); ?>" />
			</label>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Audience', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="target_audience" placeholder="<?php esc_attr_e( 'Target reader', 'magick-ai-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Angle', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="angle" placeholder="<?php esc_attr_e( 'Point of view or structure', 'magick-ai-toolbox' ); ?>" />
				</label>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Language', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="language" value="zh-CN" />
				</label>
				<label>
					<span><?php esc_html_e( 'Target words', 'magick-ai-toolbox' ); ?></span>
					<input type="number" min="500" max="5000" step="50" name="target_word_count" value="1200" />
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Article goal', 'magick-ai-toolbox' ); ?></span>
				<textarea name="article_goal" rows="2" placeholder="<?php esc_attr_e( 'What should the article help the reader do?', 'magick-ai-toolbox' ); ?>"></textarea>
			</label>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Must include', 'magick-ai-toolbox' ); ?></span>
					<textarea name="must_include" rows="3" placeholder="<?php esc_attr_e( 'One required point per line', 'magick-ai-toolbox' ); ?>"></textarea>
				</label>
				<label>
					<span><?php esc_html_e( 'Must avoid', 'magick-ai-toolbox' ); ?></span>
					<textarea name="must_avoid" rows="3" placeholder="<?php esc_attr_e( 'One forbidden or sensitive point per line', 'magick-ai-toolbox' ); ?>"></textarea>
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Reference URLs', 'magick-ai-toolbox' ); ?></span>
				<textarea name="reference_urls" rows="3" placeholder="<?php esc_attr_e( 'One source URL per line', 'magick-ai-toolbox' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Draft notes', 'magick-ai-toolbox' ); ?></span>
				<textarea name="draft_notes" rows="4" placeholder="<?php esc_attr_e( 'Operator notes, facts, outline fragments, or constraints.', 'magick-ai-toolbox' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Reviewed draft body', 'magick-ai-toolbox' ); ?></span>
				<textarea name="reviewed_draft_markdown" rows="7" placeholder="<?php esc_attr_e( 'Optional. When present and risk checks pass, Toolbox also returns an article_write_plan for Core intake.', 'magick-ai-toolbox' ); ?>"></textarea>
			</label>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Source policy', 'magick-ai-toolbox' ); ?></span>
					<select name="source_policy">
						<option value="strict_sources"><?php esc_html_e( 'Strict sources', 'magick-ai-toolbox' ); ?></option>
						<option value="review_required"><?php esc_html_e( 'Review required', 'magick-ai-toolbox' ); ?></option>
						<option value="operator_notes_only"><?php esc_html_e( 'Operator notes only', 'magick-ai-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Tone', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="tone" placeholder="<?php esc_attr_e( 'Optional tone hint', 'magick-ai-toolbox' ); ?>" />
				</label>
			</div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Build assistant artifact', 'magick-ai-toolbox' ); ?></button>
			<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_media_derivative_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		$core_policy = function_exists( 'magick_ai_core_get_media_derivative_settings' )
			? magick_ai_core_get_media_derivative_settings()
			: array(
				'target_format'            => 'webp',
				'max_width'                => 1600,
				'quality'                  => 82,
				'watermark_enabled'        => false,
				'watermark_configured'     => false,
				'watermark_position'       => 'bottom_right',
				'watermark_opacity'        => 80,
				'watermark_scale'          => 20,
				'watermark_margin'         => 24,
				'use_cloud_when_available' => true,
			);
		$watermark_details = ! empty( $core_policy['watermark_configured'] )
			? sprintf(
				/* translators: 1: position, 2: opacity, 3: scale, 4: margin. */
				__( '%1$s, %2$d%% opacity, %3$d%% scale, %4$dpx margin', 'magick-ai-toolbox' ),
				ucwords( str_replace( '_', ' ', (string) ( $core_policy['watermark_position'] ?? 'bottom_right' ) ) ),
				(int) ( $core_policy['watermark_opacity'] ?? 80 ),
				(int) ( $core_policy['watermark_scale'] ?? 20 ),
				(int) ( $core_policy['watermark_margin'] ?? 24 )
			)
			: __( 'off or incomplete', 'magick-ai-toolbox' );
		?>
		<form class="magick-ai-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" data-toolbox-media-derivative <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="magick-ai-toolbox__example">
				<strong><?php esc_html_e( 'Core defaults', 'magick-ai-toolbox' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: format, 2: max width, 3: quality. */
						esc_html__( '%1$s, %2$dpx, quality %3$d. Watermark: %4$s.', 'magick-ai-toolbox' ),
						esc_html( strtoupper( (string) $core_policy['target_format'] ) ),
						(int) $core_policy['max_width'],
						(int) $core_policy['quality'],
						esc_html( $watermark_details )
					);
					?>
				</span>
			</div>
			<div class="magick-ai-toolbox__media-picker">
				<div class="magick-ai-toolbox__media-preview" data-toolbox-media-preview>
					<span><?php esc_html_e( 'No image selected', 'magick-ai-toolbox' ); ?></span>
				</div>
				<div>
					<label>
						<span><?php esc_html_e( 'Attachment ID', 'magick-ai-toolbox' ); ?></span>
						<input type="number" min="1" step="1" name="attachment_id" placeholder="<?php esc_attr_e( 'Attachment ID', 'magick-ai-toolbox' ); ?>" data-toolbox-media-attachment />
					</label>
					<label>
						<span><?php esc_html_e( 'Image URL', 'magick-ai-toolbox' ); ?></span>
						<input type="url" name="attachment_url" placeholder="<?php esc_attr_e( 'Paste a local uploads URL', 'magick-ai-toolbox' ); ?>" data-toolbox-media-url />
					</label>
					<div class="magick-ai-toolbox__inline-actions">
						<button type="button" class="button" data-toolbox-select-media><?php esc_html_e( 'Select from media library', 'magick-ai-toolbox' ); ?></button>
						<button type="button" class="button" data-toolbox-resolve-media-url><?php esc_html_e( 'Resolve URL', 'magick-ai-toolbox' ); ?></button>
						<span data-toolbox-media-name><?php esc_html_e( 'Choose one local image attachment.', 'magick-ai-toolbox' ); ?></span>
					</div>
					<div class="magick-ai-toolbox__url-resolution" data-toolbox-media-url-resolution hidden></div>
				</div>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Format override', 'magick-ai-toolbox' ); ?></span>
					<select name="target_format">
						<option value=""><?php esc_html_e( 'Use Core default', 'magick-ai-toolbox' ); ?></option>
						<?php foreach ( array( 'webp', 'avif', 'jpeg', 'png', 'original' ) as $format ) : ?>
							<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( strtoupper( $format ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Max width override', 'magick-ai-toolbox' ); ?></span>
					<input type="number" min="320" max="7680" step="1" name="max_width" placeholder="<?php echo esc_attr( (string) $core_policy['max_width'] ); ?>" />
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Quality override', 'magick-ai-toolbox' ); ?></span>
				<input type="number" min="1" max="100" step="1" name="quality" placeholder="<?php echo esc_attr( (string) $core_policy['quality'] ); ?>" />
			</label>
			<div class="magick-ai-toolbox__batch-panel">
				<h3><?php esc_html_e( 'Reviewed media details', 'magick-ai-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'These fields are submitted with the derivative artifact as one Core media optimization proposal.', 'magick-ai-toolbox' ); ?></p>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Media title', 'magick-ai-toolbox' ); ?></span>
						<input type="text" name="media_title" placeholder="<?php esc_attr_e( 'Reviewed media title', 'magick-ai-toolbox' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Alt text', 'magick-ai-toolbox' ); ?></span>
						<input type="text" name="media_alt" placeholder="<?php esc_attr_e( 'Describe the image for accessibility', 'magick-ai-toolbox' ); ?>" />
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Caption', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="media_caption" placeholder="<?php esc_attr_e( 'Optional reviewed caption', 'magick-ai-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Description', 'magick-ai-toolbox' ); ?></span>
					<textarea name="media_description" rows="2" placeholder="<?php esc_attr_e( 'Optional reviewed attachment description', 'magick-ai-toolbox' ); ?>"></textarea>
				</label>
				<label>
					<span><?php esc_html_e( 'Source type', 'magick-ai-toolbox' ); ?></span>
					<select name="media_source_type">
						<option value="ai_generated"><?php esc_html_e( 'AI generated', 'magick-ai-toolbox' ); ?></option>
						<option value="owned"><?php esc_html_e( 'Owned image', 'magick-ai-toolbox' ); ?></option>
						<option value="stock"><?php esc_html_e( 'Stock image', 'magick-ai-toolbox' ); ?></option>
						<option value="external"><?php esc_html_e( 'External image', 'magick-ai-toolbox' ); ?></option>
						<option value="test"><?php esc_html_e( 'Test media', 'magick-ai-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="magick-ai-toolbox__batch-panel">
				<h3><?php esc_html_e( 'Watermark override', 'magick-ai-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'Use Core watermark policy by default. Override only changes this preview or batch run and still requires a configured Core logo attachment.', 'magick-ai-toolbox' ); ?></p>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Watermark mode', 'magick-ai-toolbox' ); ?></span>
						<select name="watermark_mode">
							<option value="core"><?php esc_html_e( 'Use Core default', 'magick-ai-toolbox' ); ?></option>
							<option value="off"><?php esc_html_e( 'Disable for this run', 'magick-ai-toolbox' ); ?></option>
							<option value="override"><?php esc_html_e( 'Override placement', 'magick-ai-toolbox' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Position', 'magick-ai-toolbox' ); ?></span>
						<select name="watermark_position">
							<?php foreach ( array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ) as $position ) : ?>
								<option value="<?php echo esc_attr( $position ); ?>" <?php selected( (string) ( $core_policy['watermark_position'] ?? 'bottom_right' ), $position ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $position ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Opacity', 'magick-ai-toolbox' ); ?></span>
						<input type="number" min="0" max="100" step="1" name="watermark_opacity" value="<?php echo esc_attr( (string) ( $core_policy['watermark_opacity'] ?? 80 ) ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Scale', 'magick-ai-toolbox' ); ?></span>
						<input type="number" min="1" max="100" step="1" name="watermark_scale" value="<?php echo esc_attr( (string) ( $core_policy['watermark_scale'] ?? 20 ) ); ?>" />
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Margin', 'magick-ai-toolbox' ); ?></span>
					<input type="number" min="0" max="1000" step="1" name="watermark_margin" value="<?php echo esc_attr( (string) ( $core_policy['watermark_margin'] ?? 24 ) ); ?>" />
				</label>
			</div>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Exclude formats from setting repair', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="settings_excluded_formats" value="svg,gif,ico,pdf" />
				</label>
				<label>
					<span><?php esc_html_e( 'Minimum setting image size', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="settings_min_dimensions" value="64x64" />
				</label>
			</div>
			<div class="magick-ai-toolbox__batch-panel">
				<h3><?php esc_html_e( 'Batch conversion plan', 'magick-ai-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'Build a bounded local candidate plan first, then generate previews and Core proposals only for reviewed selections.', 'magick-ai-toolbox' ); ?></p>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Date from', 'magick-ai-toolbox' ); ?></span>
						<input type="date" name="batch_date_from" />
					</label>
					<label>
						<span><?php esc_html_e( 'Date to', 'magick-ai-toolbox' ); ?></span>
						<input type="date" name="batch_date_to" />
					</label>
				</div>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Batch target format', 'magick-ai-toolbox' ); ?></span>
						<select name="batch_target_format">
							<?php foreach ( array( 'webp', 'avif', 'jpeg', 'png', 'original' ) as $format ) : ?>
								<option value="<?php echo esc_attr( $format ); ?>" <?php selected( 'webp', $format ); ?>><?php echo esc_html( strtoupper( $format ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Exclude formats', 'magick-ai-toolbox' ); ?></span>
						<input type="text" name="batch_exclude_formats" value="webp" />
					</label>
				</div>
				<div class="magick-ai-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Min dimensions', 'magick-ai-toolbox' ); ?></span>
						<input type="text" name="batch_min_dimensions" value="0x0" />
					</label>
					<label>
						<span><?php esc_html_e( 'Max candidates', 'magick-ai-toolbox' ); ?></span>
						<input type="number" min="1" max="50" step="1" name="batch_max_items" value="20" />
					</label>
				</div>
				<div class="magick-ai-toolbox__inline-actions">
					<button type="button" class="button" data-toolbox-build-media-batch-plan><?php esc_html_e( 'Build batch plan', 'magick-ai-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-run-media-batch-previews disabled><?php esc_html_e( 'Generate selected previews', 'magick-ai-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-submit-media-batch-proposals disabled><?php esc_html_e( 'Submit selected proposals', 'magick-ai-toolbox' ); ?></button>
				</div>
				<div class="magick-ai-toolbox__batch-plan" data-toolbox-media-batch-plan hidden></div>
			</div>
			<p class="description"><?php esc_html_e( 'Cloud returns a short-lived derivative artifact. Core remains the policy owner and final WordPress write owner, and creates one proposal for the metadata update and derivative adoption together.', 'magick-ai-toolbox' ); ?></p>
			<div class="magick-ai-toolbox__inline-actions">
				<button type="button" class="button button-primary" data-toolbox-run-media-derivative><?php esc_html_e( 'Generate preview', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-submit-media-proposal disabled><?php esc_html_e( 'Submit optimization review', 'magick-ai-toolbox' ); ?></button>
			</div>
			<details class="magick-ai-toolbox__result-details">
				<summary><?php esc_html_e( 'Repair and handoff actions', 'magick-ai-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Use these after preview when old URLs appear in post content, settings, themes, or when another client needs the raw handoff.', 'magick-ai-toolbox' ); ?></p>
				<div class="magick-ai-toolbox__inline-actions">
					<button type="button" class="button" data-toolbox-submit-reference-repair disabled><?php esc_html_e( 'Submit content URL repair', 'magick-ai-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-submit-settings-repair disabled><?php esc_html_e( 'Submit settings URL repair', 'magick-ai-toolbox' ); ?></button>
					<button type="submit" class="button"><?php esc_html_e( 'Build handoff only', 'magick-ai-toolbox' ); ?></button>
				</div>
			</details>
			<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_article_plan_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		?>
		<form class="magick-ai-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="magick-ai-toolbox__example">
				<strong><?php esc_html_e( 'Handoff', 'magick-ai-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Review the returned artifact, then send it to Core through Adapter or Core from-plan intake. Final execution remains magick-ai/create-draft after Core approval.', 'magick-ai-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Reviewed draft title', 'magick-ai-toolbox' ); ?></span>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Working article title', 'magick-ai-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Reviewed draft body', 'magick-ai-toolbox' ); ?></span>
				<textarea name="content_markdown" rows="8" placeholder="<?php esc_attr_e( 'Paste the reviewed draft body. This creates a plan only, not a post.', 'magick-ai-toolbox' ); ?>"></textarea>
			</label>
			<div class="magick-ai-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Topic', 'magick-ai-toolbox' ); ?></span>
					<input type="text" name="topic" placeholder="<?php esc_attr_e( 'Optional topic label', 'magick-ai-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Risk level', 'magick-ai-toolbox' ); ?></span>
					<select name="risk_level">
						<option value="low"><?php esc_html_e( 'Low', 'magick-ai-toolbox' ); ?></option>
						<option value="medium"><?php esc_html_e( 'Medium', 'magick-ai-toolbox' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'magick-ai-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'SEO title', 'magick-ai-toolbox' ); ?></span>
				<input type="text" name="seo_title" placeholder="<?php esc_attr_e( 'Optional proposal SEO title', 'magick-ai-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'SEO description', 'magick-ai-toolbox' ); ?></span>
				<textarea name="seo_description" rows="2" placeholder="<?php esc_attr_e( 'Optional proposal SEO description', 'magick-ai-toolbox' ); ?>"></textarea>
			</label>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Build plan', 'magick-ai-toolbox' ); ?></button>
			<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_text_tool( string $endpoint, string $title, string $description, string $field, string $placeholder, string $button, array $extra_fields = array(), string $tool_id = '', bool $active = false ): void {
		?>
		<form class="magick-ai-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
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
			<div class="magick-ai-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_checkbox( string $key, string $label, array $settings ): void {
		?>
		<label class="magick-ai-toolbox__check">
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
		<label class="magick-ai-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $context[ $key ] ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	private function render_proposal_field_checkbox( string $field, string $label, array $context ): void {
		?>
		<label class="magick-ai-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[proposal_allowed_fields][]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, (array) $context['proposal_allowed_fields'], true ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}
}
