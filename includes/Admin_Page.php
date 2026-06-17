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
	private const LEGACY_MENU_SLUG = 'magick-ai-toolbox';

	private Settings $settings;
	private string $hook_suffix = '';

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_menu(): void {
		if ( $this->has_magick_parent_menu() ) {
			$this->hook_suffix = add_submenu_page(
				self::PARENT_MENU_SLUG,
				__( 'Npcink Toolbox', 'npcink-toolbox' ),
				__( 'Toolbox', 'npcink-toolbox' ),
				'manage_options',
				self::MENU_SLUG,
				array( $this, 'render' ),
				45
			);
			$this->register_legacy_redirect_page();
			return;
		}

		$this->hook_suffix = add_management_page(
			__( 'Npcink Toolbox', 'npcink-toolbox' ),
			__( 'Npcink Toolbox', 'npcink-toolbox' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);

		$this->register_legacy_redirect_page();
	}

	private function register_legacy_redirect_page(): void {
		$hook_suffix = add_menu_page(
			__( 'Npcink Toolbox', 'npcink-toolbox' ),
			__( 'Npcink Toolbox', 'npcink-toolbox' ),
			'manage_options',
			self::LEGACY_MENU_SLUG,
			array( $this, 'redirect_legacy_menu_slug' )
		);
		remove_menu_page( self::LEGACY_MENU_SLUG );

		if ( is_string( $hook_suffix ) && '' !== $hook_suffix ) {
			global $_registered_pages, $_parent_pages;

			$_registered_pages[ $hook_suffix ]       = true;
			$_parent_pages[ self::LEGACY_MENU_SLUG ] = false;

			add_action( 'load-' . $hook_suffix, array( $this, 'redirect_legacy_menu_slug' ) );
		}
	}

	public function redirect_legacy_menu_slug(): void {
		$page_param = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$page       = is_scalar( $page_param ) ? sanitize_key( (string) $page_param ) : '';
		if ( self::LEGACY_MENU_SLUG !== $page ) {
			return;
		}

		$query = array(
			'page' => self::MENU_SLUG,
		);
		foreach ( array( 'toolbox_tab', 'toolbox_tool', 'toolbox_cloud_check', 'toolbox_cloud_check_group' ) as $key ) {
			$value = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
			$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
			if ( '' !== $value ) {
				$query[ $key ] = $value;
			}
		}

		wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
		exit;
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
			'npcink-toolbox',
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
					'running' => __( 'Running...', 'npcink-toolbox' ),
					'error'   => __( 'Request failed.', 'npcink-toolbox' ),
				)
			)
		);
	}

	private function asset_version( string $relative_path ): string {
		$path     = NPCINK_TOOLBOX_DIR . ltrim( $relative_path, '/' );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;
		return NPCINK_TOOLBOX_VERSION . ( $modified ? '-' . (string) $modified : '' );
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
			'site_positioning'                  => __( 'A practical AI technology blog for developers, product teams, and AI tool builders. It focuses on large language model applications, agent workflows, WordPress AI integration, vector search, content automation, and AI product engineering.', 'npcink-toolbox' ),
			'target_audience'                   => array(
				__( 'AI application developers', 'npcink-toolbox' ),
				__( 'WordPress plugin developers', 'npcink-toolbox' ),
				__( 'Technical content operators', 'npcink-toolbox' ),
				__( 'AI product managers', 'npcink-toolbox' ),
				__( 'Independent developers', 'npcink-toolbox' ),
				__( 'Internal tools teams', 'npcink-toolbox' ),
			),
			'brand_voice'                       => __( 'Professional, pragmatic, clear, and restrained. Explain real use cases, engineering tradeoffs, and boundary risks. Avoid inflated marketing claims. Give direct recommendations with conditions and limits.', 'npcink-toolbox' ),
			'primary_keywords'                  => array(
				__( 'AI technology blog', 'npcink-toolbox' ),
				__( 'large language model applications', 'npcink-toolbox' ),
				__( 'AI Agent', 'npcink-toolbox' ),
				__( 'WordPress AI', 'npcink-toolbox' ),
				__( 'vector search', 'npcink-toolbox' ),
				__( 'RAG', 'npcink-toolbox' ),
				__( 'AI workflow', 'npcink-toolbox' ),
				__( 'content automation', 'npcink-toolbox' ),
			),
			'long_tail_keywords'                => array(
				__( 'how to integrate AI capabilities into WordPress', 'npcink-toolbox' ),
				__( 'AI Agent workflow design', 'npcink-toolbox' ),
				__( 'WordPress plugin development with AI tools', 'npcink-toolbox' ),
				__( 'vector search for content websites', 'npcink-toolbox' ),
				__( 'RAG and content retrieval practice', 'npcink-toolbox' ),
				__( 'AI content suggestion workflow', 'npcink-toolbox' ),
				__( 'large language model application engineering', 'npcink-toolbox' ),
			),
			'entity_keywords'                   => array( 'OpenAI', 'WordPress', 'Cloud Search', 'Site Knowledge', 'Unsplash', 'REST API', 'WordPress Abilities API', 'Npcink' ),
			'allowed_claims'                    => array(
				__( 'AI tools can assist research, generate suggestions, plan content, and improve editorial efficiency.', 'npcink-toolbox' ),
				__( 'Vector search, external search, and content context can improve retrieval and suggestion quality.', 'npcink-toolbox' ),
				__( 'Architecture advice and implementation ideas are suitable for development and testing contexts.', 'npcink-toolbox' ),
				__( 'Final publishing, SEO writes, and media changes should go through human review or governance.', 'npcink-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not claim AI output is always correct.', 'npcink-toolbox' ),
				__( 'Do not claim automatic SEO ranking improvements.', 'npcink-toolbox' ),
				__( 'Do not claim AI replaces human review, legal review, or expert judgment.', 'npcink-toolbox' ),
				__( 'Do not imply WordPress permissions, approval, or governance can be bypassed.', 'npcink-toolbox' ),
				__( 'Do not describe image-source search as AI image generation.', 'npcink-toolbox' ),
				__( 'Do not describe vector search as a complete knowledge base or automatic indexing system.', 'npcink-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported customer stories, rankings, benchmark results, or legal/medical/financial advice.', 'npcink-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Model comparisons, provider pricing, product roadmap, security posture, and production-readiness claims require current verification.', 'npcink-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions when the source does not clearly support every answer or step.', 'npcink-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Claims about implemented features, integrations, customer usage, benchmark quality, ranking impact, or availability must be confirmed by the operator.', 'npcink-toolbox' ),
			),
			'seo_rules'                         => __( "Titles should include the main topic keyword and avoid clickbait.\nDescriptions should state the problem, audience, and core conclusion.\nUse clear headings, steps, caveats, and engineering boundary notes.\nPrefer internal links to related tutorials, architecture notes, and tool reviews.", 'npcink-toolbox' ),
			'aeo_rules'                         => __( "Start with a direct answer, then add conditions, steps, and limits.\nPrefer FAQ, short definitions, comparison tables, and actionable checklists.\nAvoid abstract-only answers; include practical guidance.", 'npcink-toolbox' ),
			'geo_rules'                         => __( "Make key conclusions clear, standalone, and easy for AI systems to summarize.\nDefine important terms when they first appear.\nDistinguish implemented features, development-stage behavior, and future plans.\nAvoid inflated claims; state boundaries, inputs, outputs, and limits.", 'npcink-toolbox' ),
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
				__( 'Practical guide: %s', 'npcink-toolbox' ),
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
				__( '%s. Use recent public posts, categories, and tags as non-secret guidance for content suggestions. Keep the site brief editable and verify recommendations before saving.', 'npcink-toolbox' ),
				implode( ' - ', $position_parts )
			)
			: __( 'A WordPress site with public content available for operator-reviewed AI content suggestions. Keep recommendations editable and verify them before saving.', 'npcink-toolbox' );

		return array(
			'site_positioning'                  => $positioning,
			'target_audience'                   => array( __( 'Current site readers', 'npcink-toolbox' ), __( 'Editors', 'npcink-toolbox' ), __( 'Site operators', 'npcink-toolbox' ) ),
			'brand_voice'                       => __( 'Use the tone implied by existing public posts. Prefer clear, accurate, and reviewable suggestions over promotional claims.', 'npcink-toolbox' ),
			'primary_keywords'                  => $primary_keywords,
			'long_tail_keywords'                => $this->unique_non_empty( $long_tail_keywords ),
			'entity_keywords'                   => $entity_keywords,
			'allowed_claims'                    => array(
				__( 'Suggestions may use public post titles, public categories, and public tags as context.', 'npcink-toolbox' ),
				__( 'Suggestions should be treated as drafts for operator review.', 'npcink-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not infer private business facts from public content.', 'npcink-toolbox' ),
				__( 'Do not claim the generated suggestions have been verified unless an operator verifies them.', 'npcink-toolbox' ),
				__( 'Do not bypass WordPress permissions, approval, or governance.', 'npcink-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported private facts, unverified business claims, and claims outside current public site content.', 'npcink-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Product status, pricing, customer examples, legal/medical/financial claims, and time-sensitive facts require operator confirmation.', 'npcink-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions unless the sampled source clearly supports them.', 'npcink-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Any claim not visible in public post titles, categories, tags, or supplied source content must be confirmed by the operator.', 'npcink-toolbox' ),
			),
			'seo_rules'                         => __( "Use public categories, tags, and recent article themes as keyword candidates.\nTitles should stay specific to the article topic and avoid clickbait.\nDescriptions should summarize the reader problem and expected value.\nSuggest internal links only when the target content is clearly related.", 'npcink-toolbox' ),
			'aeo_rules'                         => __( "Answer likely reader questions directly before giving details.\nPrefer concise definitions, steps, checklists, and FAQ suggestions.\nMark assumptions clearly when the site content does not provide enough evidence.", 'npcink-toolbox' ),
			'geo_rules'                         => __( "Use public entity names from categories, tags, and recent titles as entity hints.\nKeep conclusions standalone and easy to quote.\nDistinguish observed site content from generated recommendations.", 'npcink-toolbox' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'npcink-toolbox' ) );
		}

		$settings        = $this->settings->get_all();
		$content_context = $this->settings->get_content_context();
		$cloud_ready     = $this->settings->cloud_runtime_available();
		$nightly_preview = $this->nightly_inspection_preview_from_request();
		?>
		<div class="wrap npcink-toolbox">
			<h1><?php esc_html_e( 'Npcink Toolbox', 'npcink-toolbox' ); ?></h1>
			<p class="npcink-toolbox__scope"><?php esc_html_e( 'Use hosted AI for reviewable content-support suggestions, then hand final WordPress writes to Core proposal approval.', 'npcink-toolbox' ); ?></p>
			<?php
			if ( ! $cloud_ready ) {
				$this->render_cloud_runtime_notice();
			}
			?>

			<nav class="npcink-toolbox__tabs" data-toolbox-tabs aria-label="<?php esc_attr_e( 'Toolbox sections', 'npcink-toolbox' ); ?>">
				<button type="button" class="npcink-toolbox__tab is-active" data-toolbox-tab-target="start" aria-selected="true"><?php esc_html_e( 'Start', 'npcink-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__tab" data-toolbox-tab-target="context" aria-selected="false"><?php esc_html_e( 'Site Context', 'npcink-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__tab" data-toolbox-tab-target="site-knowledge" aria-selected="false"><?php esc_html_e( 'Site Knowledge', 'npcink-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__tab" data-toolbox-tab-target="tools" aria-selected="false"><?php esc_html_e( 'Workflows', 'npcink-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__tab" data-toolbox-tab-target="cloud-checks" aria-selected="false"><?php esc_html_e( 'Advanced Checks', 'npcink-toolbox' ); ?></button>
			</nav>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="start" aria-label="<?php esc_attr_e( 'Toolbox start', 'npcink-toolbox' ); ?>">
				<?php $this->render_start_panel( $settings, $content_context, $cloud_ready, $nightly_preview ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="context" aria-label="<?php esc_attr_e( 'Site context', 'npcink-toolbox' ); ?>" hidden>
				<?php $this->render_content_context_form( $content_context ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="site-knowledge" aria-label="<?php esc_attr_e( 'Site knowledge', 'npcink-toolbox' ); ?>" hidden>
				<?php $this->render_site_knowledge_panel( $cloud_ready ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="tools" aria-label="<?php esc_attr_e( 'Try Toolbox actions', 'npcink-toolbox' ); ?>" hidden>
				<?php $this->render_tool_cards( $cloud_ready ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="cloud-checks" aria-label="<?php esc_attr_e( 'Cloud checks', 'npcink-toolbox' ); ?>" hidden>
				<?php $this->render_cloud_checks_panel( $settings, $cloud_ready ); ?>
			</section>
		</div>
		<?php
	}

	private function render_start_panel( array $settings, array $content_context, bool $cloud_ready, ?array $nightly_preview ): void {
		$context_ready = $this->content_context_ready( $content_context );
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Start', 'npcink-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Check readiness, then open the right operator surface for the current task.', 'npcink-toolbox' ); ?></p>
		</div>

		<div class="npcink-toolbox__start" data-toolbox-start>
			<section class="npcink-toolbox__readiness-strip" aria-label="<?php esc_attr_e( 'Toolbox readiness', 'npcink-toolbox' ); ?>">
				<?php
				$this->render_start_status_item(
					__( 'Cloud runtime', 'npcink-toolbox' ),
					$cloud_ready ? 'ok' : 'warning',
					$cloud_ready ? __( 'Connected', 'npcink-toolbox' ) : __( 'Needs Cloud Addon', 'npcink-toolbox' ),
					$cloud_ready ? __( 'Hosted checks and suggestions are available.', 'npcink-toolbox' ) : __( 'Hosted checks stay disabled until Cloud transport is verified.', 'npcink-toolbox' )
				);
				$this->render_start_status_item(
					__( 'Site context', 'npcink-toolbox' ),
					$context_ready ? 'ok' : 'neutral',
					$context_ready ? __( 'Ready', 'npcink-toolbox' ) : __( 'Needs brief', 'npcink-toolbox' ),
					$context_ready ? __( 'Site positioning, audience, voice, and keywords are present.', 'npcink-toolbox' ) : __( 'Add the compact site brief before relying on repeatable suggestions.', 'npcink-toolbox' )
				);
				$this->render_start_status_item(
					__( 'Site Knowledge', 'npcink-toolbox' ),
					'neutral',
					__( 'Cloud managed', 'npcink-toolbox' ),
					__( 'Toolbox starts or refreshes the manifest; Cloud owns embeddings and index state.', 'npcink-toolbox' )
				);
				$this->render_start_status_item(
					__( 'Final writes', 'npcink-toolbox' ),
					'neutral',
					__( 'Core governed', 'npcink-toolbox' ),
					__( 'Suggestions and plans do not publish, mutate media, or write SEO fields directly.', 'npcink-toolbox' )
				);
				?>
			</section>

			<section class="npcink-toolbox__start-main">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Current article work', 'npcink-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'High-frequency writing preparation, metadata suggestions, links, image candidates, and publish checks live in the post editor sidebar.', 'npcink-toolbox' ); ?></p>
					</div>
					<div class="npcink-toolbox__inline-actions">
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'New post', 'npcink-toolbox' ); ?></a>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Open posts', 'npcink-toolbox' ); ?></a>
					</div>
				</div>
				<ul class="npcink-toolbox__usage-list">
					<li>
						<strong><?php esc_html_e( 'Use editor support for article-specific decisions.', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'The admin surface stays focused on setup, reusable site operations, fallback bundles, governed handoffs, and diagnostics.', 'npcink-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Review before handoff.', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Toolbox may prepare a plan, but final WordPress writes stay in Core approval or a separately defined local-consent proof.', 'npcink-toolbox' ); ?></span>
					</li>
				</ul>
			</section>

			<section class="npcink-toolbox__start-actions" aria-label="<?php esc_attr_e( 'Next actions', 'npcink-toolbox' ); ?>">
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=site-knowledge' ) ); ?>">
					<strong><?php esc_html_e( 'Manage Site Knowledge', 'npcink-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Start or refresh the Cloud-managed public content index.', 'npcink-toolbox' ); ?></span>
				</a>
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>">
					<strong><?php esc_html_e( 'Preview Morning Brief', 'npcink-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Read bounded local content, score quality signals, and show a dry-run replay without cron, Cloud, Core proposals, or writes.', 'npcink-toolbox' ); ?></span>
				</a>
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=tools&toolbox_tool=media-derivative' ) ); ?>">
					<strong><?php esc_html_e( 'Optimize Existing Image', 'npcink-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Review a media-library image preview before creating one Core proposal.', 'npcink-toolbox' ); ?></span>
				</a>
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=context' ) ); ?>">
					<strong><?php esc_html_e( 'Edit Site Context', 'npcink-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Maintain the site brief used as suggestion-only guidance.', 'npcink-toolbox' ); ?></span>
				</a>
				<a class="npcink-toolbox__action-row" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=cloud-checks' ) ); ?>">
					<strong><?php esc_html_e( 'Open Advanced Checks', 'npcink-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Use search, image-source, and workflow checks only when troubleshooting.', 'npcink-toolbox' ); ?></span>
				</a>
			</section>

			<?php $this->render_nightly_inspection_preview( $nightly_preview ); ?>
			<?php $this->render_nightly_inspection_basic_settings( $settings ); ?>
		</div>
		<?php
	}

	private function nightly_inspection_preview_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                       => self::MENU_SLUG,
					'toolbox_tab'                => 'start',
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
				'error' => __( 'The Morning Brief preview link expired. Reload the page and try again.', 'npcink-toolbox' ),
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
				'error' => __( 'Could not build the local Morning Brief preview.', 'npcink-toolbox' ),
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
				<h3><?php esc_html_e( 'Morning Brief preview', 'npcink-toolbox' ); ?></h3>
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
					<h3><?php esc_html_e( 'Morning Brief preview', 'npcink-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Manual dry-run only. The preview reads local content, produces review signals, and does not schedule, call Cloud, create Core proposals, or write WordPress data.', 'npcink-toolbox' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>"><?php esc_html_e( 'Refresh preview', 'npcink-toolbox' ); ?></a>
			</div>
			<div class="npcink-toolbox__readiness-strip" aria-label="<?php esc_attr_e( 'Morning Brief preview summary', 'npcink-toolbox' ); ?>">
				<?php
				$this->render_start_status_item( __( 'Scanned posts', 'npcink-toolbox' ), 'neutral', (string) (int) ( $summary['scanned_posts'] ?? 0 ), __( 'Oldest modified public posts and pages.', 'npcink-toolbox' ) );
				$this->render_start_status_item( __( 'Scanned media', 'npcink-toolbox' ), 'neutral', (string) (int) ( $summary['scanned_media'] ?? 0 ), __( 'Recent image attachments.', 'npcink-toolbox' ) );
				$this->render_start_status_item( __( 'Review items', 'npcink-toolbox' ), (int) ( $summary['actions_total'] ?? 0 ) > 0 ? 'warning' : 'ok', (string) (int) ( $summary['actions_total'] ?? 0 ), __( 'Preview-only action candidates.', 'npcink-toolbox' ) );
				$this->render_start_status_item( __( 'Execution', 'npcink-toolbox' ), 'ok', __( 'Disabled', 'npcink-toolbox' ), __( 'No cron, worker, Cloud call, Core proposal, or write.', 'npcink-toolbox' ) );
				?>
			</div>
			<?php if ( array() === $priority ) : ?>
				<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority review items were found in this bounded preview.', 'npcink-toolbox' ); ?></div>
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
							<strong><?php echo esc_html( (string) ( $item['title'] ?? __( 'Untitled item', 'npcink-toolbox' ) ) ); ?></strong>
							<span>
								<?php
								printf(
									/* translators: 1: object type, 2: object id, 3: score, 4: reason codes. */
									esc_html__( '%1$s #%2$d, score %3$d. %4$s', 'npcink-toolbox' ),
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
				<summary><?php esc_html_e( 'Copy or download dry-run JSON', 'npcink-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'This is the read-only replay payload produced by the manual preview. It is not saved automatically and does not create scheduled work.', 'npcink-toolbox' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $download, array( 'data' ) ); ?>" download="nightly-site-inspection-dry-run.json"><?php esc_html_e( 'Download dry-run JSON', 'npcink-toolbox' ); ?></a></p>
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
					<h3><?php esc_html_e( 'Local Fallback Preview', 'npcink-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'WP-Cron is the WordPress-side fallback for one latest dry-run Morning Brief preview. The Pro Cloud Runtime remains the primary execution path for reliable scoring, entitlement, status, and result retention.', 'npcink-toolbox' ); ?></p>
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
					<span><?php esc_html_e( 'Enable local WP-Cron fallback preview', 'npcink-toolbox' ); ?></span>
				</label>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Run time', 'npcink-toolbox' ); ?></span>
						<input type="time" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_time]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_time'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Post/page scan limit', 'npcink-toolbox' ); ?></span>
						<input type="number" min="1" max="50" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_post_limit]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_post_limit'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Media scan limit', 'npcink-toolbox' ); ?></span>
						<input type="number" min="1" max="50" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_media_limit]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_media_limit'] ); ?>" />
					</label>
				</div>
				<hr />
				<label class="npcink-toolbox__check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_pro_enabled]" value="1" <?php checked( $pro_enabled ); ?> />
					<span><?php esc_html_e( 'Enable Pro Cloud Runtime controls', 'npcink-toolbox' ); ?></span>
				</label>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Cloud payload', 'npcink-toolbox' ); ?></span>
						<select name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_cloud_payload_mode]">
							<option value="metadata_only" <?php selected( (string) $settings['nightly_inspection_cloud_payload_mode'], 'metadata_only' ); ?>><?php esc_html_e( 'Metadata only', 'npcink-toolbox' ); ?></option>
							<option value="excerpt" <?php selected( (string) $settings['nightly_inspection_cloud_payload_mode'], 'excerpt' ); ?>><?php esc_html_e( 'Include short excerpts', 'npcink-toolbox' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Cloud result retention days', 'npcink-toolbox' ); ?></span>
						<input type="number" min="1" max="90" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_cloud_retention_days]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_cloud_retention_days'] ); ?>" />
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Pro Cloud Runtime is review-only: Cloud may score, meter entitlement, retain results, and return details, but WordPress writes and Core proposals stay local and operator reviewed.', 'npcink-toolbox' ); ?></p>
				<?php submit_button( __( 'Save fallback preview', 'npcink-toolbox' ) ); ?>
			</form>
			<?php if ( array() !== $latest_preview ) : ?>
				<div class="npcink-toolbox__result-notice is-success">
					<?php
					printf(
						/* translators: 1: generated time, 2: action count. */
						esc_html__( 'Latest cron dry-run preview: %1$s, %2$d review items.', 'npcink-toolbox' ),
						esc_html( (string) ( $latest_preview['generated_at'] ?? '' ) ),
						(int) ( $summary['actions_total'] ?? 0 )
					);
					?>
				</div>
			<?php else : ?>
				<div class="npcink-toolbox__result-notice is-neutral"><?php esc_html_e( 'No cron dry-run preview has been generated yet.', 'npcink-toolbox' ); ?></div>
			<?php endif; ?>
			<form class="npcink-toolbox__inline-form npcink-toolbox__batch-panel" data-toolbox-nightly-cloud-batch data-toolbox-nightly-cloud-ready="<?php echo esc_attr( $cloud_ready ? '1' : '0' ); ?>" data-toolbox-nightly-cloud-enabled="<?php echo esc_attr( $cloud_disabled ? '0' : '1' ); ?>">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Pro Cloud Runtime', 'npcink-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Run a Cloud-scored site inspection and merge review-only findings into the Morning Brief preview. Cloud owns entitlement, usage, queue, retry, and retention detail; no local job queue or write path is created.', 'npcink-toolbox' ); ?></p>
					</div>
				</div>
				<?php if ( ! $cloud_ready ) : ?>
					<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Cloud runtime is not configured, so Pro Cloud Runtime controls are disabled.', 'npcink-toolbox' ); ?></div>
				<?php elseif ( ! $pro_enabled ) : ?>
					<div class="npcink-toolbox__result-notice is-neutral"><?php esc_html_e( 'Enable Pro Cloud Runtime controls and save settings before submitting a Cloud run.', 'npcink-toolbox' ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $brief_json ) : ?>
					<script type="application/json" data-toolbox-nightly-local-brief><?php echo $brief_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
				<?php endif; ?>
				<div class="npcink-toolbox__inline-actions">
					<button type="submit" class="button button-primary" data-toolbox-nightly-cloud-submit <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Run Cloud inspection', 'npcink-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-nightly-cloud-entitlement <?php disabled( ! $cloud_ready ); ?>><?php esc_html_e( 'Refresh Cloud quota', 'npcink-toolbox' ); ?></button>
				</div>
				<div class="npcink-toolbox__readiness-strip" data-toolbox-nightly-cloud-recent-run hidden></div>
				<div class="npcink-toolbox__readiness-strip" data-toolbox-nightly-cloud-run-summary hidden></div>
				<div class="npcink-toolbox__result is-empty" data-toolbox-nightly-cloud-result aria-live="polite" hidden></div>
				<details class="npcink-toolbox__result-details" data-toolbox-nightly-cloud-advanced>
					<summary><?php esc_html_e( 'Advanced details', 'npcink-toolbox' ); ?></summary>
					<label>
						<span><?php esc_html_e( 'Cloud run ID', 'npcink-toolbox' ); ?></span>
						<input type="text" data-toolbox-nightly-cloud-run-id placeholder="<?php esc_attr_e( 'Run ID from Cloud Batch', 'npcink-toolbox' ); ?>" autocomplete="off" />
					</label>
					<div class="npcink-toolbox__inline-actions">
						<button type="button" class="button" data-toolbox-nightly-cloud-status <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Check status', 'npcink-toolbox' ); ?></button>
						<button type="button" class="button" data-toolbox-nightly-cloud-result-read <?php disabled( $cloud_disabled ); ?>><?php esc_html_e( 'Read result', 'npcink-toolbox' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Use these controls only when recovering or inspecting a known Cloud run ID. Cloud remains the run-state owner.', 'npcink-toolbox' ); ?></p>
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
			<strong><?php esc_html_e( 'Cloud runtime is not connected.', 'npcink-toolbox' ); ?></strong>
			<span>
				<?php esc_html_e( 'Cloud-managed search, image-source, Site Knowledge, and hosted AI actions stay unavailable until the Cloud Addon is installed and verified. Site Context and governed handoff planning remain available.', 'npcink-toolbox' ); ?>
				<?php echo esc_html( $this->cloud_runtime_unavailable_reason_label() ); ?>
			</span>
		</div>
		<?php
	}

	private function cloud_runtime_unavailable_reason_label(): string {
		$reason = $this->settings->cloud_runtime_unavailable_reason();

		if ( 'cloud_addon_not_installed' === $reason ) {
			return __( 'Install and connect the Cloud Addon to enable hosted execution.', 'npcink-toolbox' );
		}

		if ( 'cloud_addon_not_connected' === $reason ) {
			return __( 'Save and verify Cloud Addon credentials to enable hosted execution.', 'npcink-toolbox' );
		}

		return __( 'Check Cloud Addon transport before running hosted execution.', 'npcink-toolbox' );
	}

	private function render_site_knowledge_panel( bool $cloud_ready ): void {
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Site Knowledge', 'npcink-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Start or refresh Cloud-managed indexing for public site content. Toolbox sends the manifest; Cloud owns embeddings, vector storage, rerank, and run detail.', 'npcink-toolbox' ); ?></p>
		</div>

		<div class="npcink-toolbox__site-knowledge" data-toolbox-site-knowledge>
				<section class="npcink-toolbox__card">
					<div class="npcink-toolbox__section-heading">
						<div>
							<h3><?php esc_html_e( 'Index status', 'npcink-toolbox' ); ?></h3>
							<p><?php esc_html_e( 'Read-only Cloud coverage summary for this WordPress site.', 'npcink-toolbox' ); ?></p>
						</div>
						<button type="button" class="button" data-toolbox-site-knowledge-status <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Refresh status', 'npcink-toolbox' ); ?></button>
					</div>
					<?php if ( ! $cloud_ready ) : ?>
						<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before reading Site Knowledge coverage.', 'npcink-toolbox' ); ?></div>
					<?php endif; ?>
					<div class="npcink-toolbox__knowledge-summary" data-toolbox-site-knowledge-summary>
						<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'Status has not been loaded yet.', 'npcink-toolbox' ); ?></div>
					</div>
					<div class="npcink-toolbox__knowledge-summary" data-toolbox-agent-feedback-summary>
						<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'Agent feedback summary has not been loaded yet.', 'npcink-toolbox' ); ?></div>
					</div>
				</section>

			<section class="npcink-toolbox__card">
				<h3><?php esc_html_e( 'Index actions', 'npcink-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'Create or refresh the Cloud index for current public site content. Advanced cleanup stays in Cloud operations.', 'npcink-toolbox' ); ?></p>
				<form data-toolbox-site-knowledge-sync>
					<input type="hidden" name="sync_mode" value="refresh" />
					<input type="hidden" name="max_posts" value="20" />
					<p class="description"><?php esc_html_e( 'Toolbox sends the latest public posts and pages. Approved comments are included only when Cloud comments indexing is enabled.', 'npcink-toolbox' ); ?></p>
					<div class="npcink-toolbox__inline-actions">
							<button
								type="submit"
								class="button button-primary"
								data-toolbox-site-knowledge-sync-submit
								data-start-label="<?php esc_attr_e( 'Start indexing', 'npcink-toolbox' ); ?>"
								data-refresh-label="<?php esc_attr_e( 'Refresh index', 'npcink-toolbox' ); ?>"
								<?php echo disabled( ! $cloud_ready, true, false ); ?>
							><?php esc_html_e( 'Start indexing', 'npcink-toolbox' ); ?></button>
						</div>
						<?php if ( ! $cloud_ready ) : ?>
							<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Indexing is disabled until Cloud Addon transport is available.', 'npcink-toolbox' ); ?></div>
						<?php endif; ?>
						<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
				</form>
			</section>

			<section class="npcink-toolbox__card">
				<h3><?php esc_html_e( 'Agent next step', 'npcink-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'When Cloud returns an evidence-backed handoff, Toolbox can turn it into one blocked Core review proposal.', 'npcink-toolbox' ); ?></p>
				<ul class="npcink-toolbox__usage-list">
					<li>
						<strong><?php esc_html_e( 'Evidence first', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'The handoff appears only after Site Knowledge returns proposal input with evidence references.', 'npcink-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Core review only', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Submit Core review proposal creates a blocked proposal that still needs a human title and content.', 'npcink-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'No direct write', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Approval, preflight, audit, and final WordPress writes stay in local Core governance.', 'npcink-toolbox' ); ?></span>
					</li>
				</ul>
			</section>

			<section class="npcink-toolbox__card">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Used by editor support and Workflows', 'npcink-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'After the index is ready, article-specific suggestions use the editor sidebar and lower-frequency planning stays in Workflows.', 'npcink-toolbox' ); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Open editor support', 'npcink-toolbox' ); ?></a>
				</div>
				<ul class="npcink-toolbox__usage-list">
					<li>
						<strong><?php esc_html_e( 'Internal Link Candidates', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Find related public posts and pages for editor-reviewed links.', 'npcink-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Publish Preflight', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Check duplicate risk, source coverage, and missing site references before publishing.', 'npcink-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Discoverability Brief', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Ground SEO, AEO, and GEO suggestions in existing site context.', 'npcink-toolbox' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Article Planning Bundle and Workflows', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Use the same Cloud-managed knowledge ability for planning bundles and natural-language requests.', 'npcink-toolbox' ); ?></span>
					</li>
				</ul>
				<p class="description"><?php esc_html_e( 'Final WordPress edits still require the normal Core proposal and editor approval path.', 'npcink-toolbox' ); ?></p>
			</section>
		</div>
		<?php
	}

	private function render_site_knowledge_search_check( bool $advanced = false, bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__inline-form" data-toolbox-site-knowledge-search>
			<h3><?php echo esc_html( $advanced ? __( 'Advanced search check', 'npcink-toolbox' ) : __( 'Search check', 'npcink-toolbox' ) ); ?></h3>
			<p><?php esc_html_e( 'Run a read-only query against Cloud-managed site knowledge.', 'npcink-toolbox' ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running Site Knowledge checks.', 'npcink-toolbox' ); ?></div>
			<?php endif; ?>
			<label>
				<span><?php esc_html_e( 'Query', 'npcink-toolbox' ); ?></span>
				<input type="text" name="query" placeholder="<?php esc_attr_e( 'Search public site knowledge', 'npcink-toolbox' ); ?>" />
			</label>
			<?php if ( $advanced ) : ?>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Intent', 'npcink-toolbox' ); ?></span>
						<select name="intent">
							<option value="site_search"><?php esc_html_e( 'Site search', 'npcink-toolbox' ); ?></option>
							<option value="faq_candidates"><?php esc_html_e( 'FAQ candidates', 'npcink-toolbox' ); ?></option>
							<option value="content_gap_analysis"><?php esc_html_e( 'Content gaps', 'npcink-toolbox' ); ?></option>
							<option value="duplicate_check"><?php esc_html_e( 'Duplicate check', 'npcink-toolbox' ); ?></option>
							<option value="internal_links"><?php esc_html_e( 'Internal links', 'npcink-toolbox' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Source types', 'npcink-toolbox' ); ?></span>
						<input type="text" name="source_types" value="post,page" />
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Current post ID', 'npcink-toolbox' ); ?></span>
						<input type="number" name="current_post_id" min="0" value="0" />
					</label>
					<label>
						<span><?php esc_html_e( 'Max results', 'npcink-toolbox' ); ?></span>
						<input type="number" name="max_results" min="1" max="20" value="8" />
					</label>
				</div>
				<?php else : ?>
					<input type="hidden" name="intent" value="site_search" />
					<input type="hidden" name="source_types" value="post,page" />
					<input type="hidden" name="current_post_id" value="0" />
					<input type="hidden" name="max_results" value="8" />
				<?php endif; ?>
				<button type="submit" class="button" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php echo esc_html( $advanced ? __( 'Search index', 'npcink-toolbox' ) : __( 'Run check', 'npcink-toolbox' ) ); ?></button>
				<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
			</form>
			<?php
	}

	private function render_cloud_checks_panel( array $settings, bool $cloud_ready ): void {
		$image_ready = $this->settings->has_image_source_provider();
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Advanced Checks', 'npcink-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Verify Cloud-managed search, image-source, Site Knowledge, and workflow evidence when an operator flow needs troubleshooting.', 'npcink-toolbox' ); ?></p>
		</div>
		<?php
		if ( ! $cloud_ready ) {
			$this->render_cloud_runtime_notice();
		}
		?>

		<div class="npcink-toolbox__cloud-check-workspace" data-toolbox-cloud-checks>
			<nav class="npcink-toolbox__cloud-check-tabs" aria-label="<?php esc_attr_e( 'Cloud check groups', 'npcink-toolbox' ); ?>">
				<button type="button" class="npcink-toolbox__cloud-check-tab is-active" data-toolbox-cloud-check-target="search" aria-selected="true">
					<span><?php esc_html_e( 'Search', 'npcink-toolbox' ); ?></span>
					<small><?php echo esc_html( $cloud_ready ? __( 'Connected', 'npcink-toolbox' ) : __( 'Cloud connection needed', 'npcink-toolbox' ) ); ?></small>
				</button>
				<button type="button" class="npcink-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="image" aria-selected="false">
					<span><?php esc_html_e( 'Image', 'npcink-toolbox' ); ?></span>
					<small><?php echo esc_html( $image_ready ? __( 'Connected', 'npcink-toolbox' ) : __( 'Cloud connection needed', 'npcink-toolbox' ) ); ?></small>
				</button>
				<button type="button" class="npcink-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="site-knowledge" aria-selected="false">
					<span><?php esc_html_e( 'Site Knowledge', 'npcink-toolbox' ); ?></span>
					<small><?php echo esc_html( $cloud_ready ? __( 'Connected', 'npcink-toolbox' ) : __( 'Cloud connection needed', 'npcink-toolbox' ) ); ?></small>
				</button>
				<button type="button" class="npcink-toolbox__cloud-check-tab" data-toolbox-cloud-check-target="agent-quality" aria-selected="false">
					<span><?php esc_html_e( 'Agent Quality', 'npcink-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Quality summary', 'npcink-toolbox' ); ?></small>
				</button>
			</nav>

			<div class="npcink-toolbox__cloud-check-panels">
				<section class="npcink-toolbox__card" data-toolbox-cloud-check-panel="search">
					<div class="npcink-toolbox__cloud-check-group-workspace" data-toolbox-cloud-check-groups>
							<nav class="npcink-toolbox__cloud-check-group-list" aria-label="<?php esc_attr_e( 'Search checks', 'npcink-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__cloud-check-group-button is-active" data-toolbox-cloud-check-group-target="search-test" aria-selected="true">
									<span><?php esc_html_e( 'Search reachability', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Read-only query', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="search-diagnostic" aria-selected="false">
									<span><?php esc_html_e( 'Evidence check', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Workflow evidence', 'npcink-toolbox' ); ?></small>
								</button>
							</nav>
							<div>
								<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="search-test">
									<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="web-search/test">
										<h3><?php esc_html_e( 'Cloud search reachability', 'npcink-toolbox' ); ?></h3>
										<?php if ( ! $cloud_ready ) : ?>
											<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running Cloud search checks.', 'npcink-toolbox' ); ?></div>
										<?php endif; ?>
										<label>
											<span><?php esc_html_e( 'Query', 'npcink-toolbox' ); ?></span>
											<input type="text" name="query" value="latest WordPress AI search trends" />
										</label>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Intent', 'npcink-toolbox' ); ?></span>
												<select name="intent">
													<option value="article_background" data-toolbox-query="latest WordPress AI search trends" data-toolbox-recency="30"><?php esc_html_e( 'Article background', 'npcink-toolbox' ); ?></option>
													<option value="fact_check" data-toolbox-query="official WordPress 6.9 release WordPress.org AI Experiments plugin" data-toolbox-recency="0"><?php esc_html_e( 'Fact check', 'npcink-toolbox' ); ?></option>
													<option value="competitor_research" data-toolbox-query="Surfer SEO Clearscope MarketMuse content optimization competitors pricing features 2026" data-toolbox-recency="30"><?php esc_html_e( 'Competitor research', 'npcink-toolbox' ); ?></option>
													<option value="pricing_snapshot" data-toolbox-query="Tavily API pricing official pricing page" data-toolbox-recency="0"><?php esc_html_e( 'Pricing snapshot', 'npcink-toolbox' ); ?></option>
													<option value="product_comparison" data-toolbox-query="Surfer SEO Clearscope MarketMuse product comparison official features" data-toolbox-recency="0"><?php esc_html_e( 'Product comparison', 'npcink-toolbox' ); ?></option>
													<option value="writing_context" data-toolbox-query="WordPress AI content workflow current best practices" data-toolbox-recency="30"><?php esc_html_e( 'Writing context', 'npcink-toolbox' ); ?></option>
													<option value="news" data-toolbox-query="latest WordPress AI search news" data-toolbox-recency="7"><?php esc_html_e( 'News', 'npcink-toolbox' ); ?></option>
													<option value="source_discovery" data-toolbox-query="official WordPress AI plugin source references" data-toolbox-recency="0"><?php esc_html_e( 'Source discovery', 'npcink-toolbox' ); ?></option>
													<option value="external_links" data-toolbox-query="WordPress AI content workflow authoritative references" data-toolbox-recency="0"><?php esc_html_e( 'External links', 'npcink-toolbox' ); ?></option>
												</select>
											</label>
											<label>
												<span><?php esc_html_e( 'Max results', 'npcink-toolbox' ); ?></span>
												<input type="number" name="max_results" min="1" max="5" value="3" />
											</label>
										</div>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Recency days', 'npcink-toolbox' ); ?></span>
												<input type="number" name="recency_days" min="0" max="30" value="7" />
											</label>
										</div>
										<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run search check', 'npcink-toolbox' ); ?></button>
										<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
									</form>
								</div>
								<div class="npcink-toolbox__cloud-check-group-panel" data-toolbox-cloud-check-group-panel="search-diagnostic" hidden>
									<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="web-search/diagnostics">
										<h3><?php esc_html_e( 'Workflow evidence check', 'npcink-toolbox' ); ?></h3>
										<p><?php esc_html_e( 'Run a Toolbox content workflow and verify whether it attached Cloud web search evidence.', 'npcink-toolbox' ); ?></p>
										<?php if ( ! $cloud_ready ) : ?>
											<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running Cloud workflow diagnostics.', 'npcink-toolbox' ); ?></div>
										<?php endif; ?>
										<div class="npcink-toolbox__split">
											<label>
												<span><?php esc_html_e( 'Scenario', 'npcink-toolbox' ); ?></span>
												<select name="scenario">
													<option value="article_assistant"><?php esc_html_e( 'Article Assistant', 'npcink-toolbox' ); ?></option>
													<option value="discoverability"><?php esc_html_e( 'Discoverability', 'npcink-toolbox' ); ?></option>
													<option value="publish_preflight"><?php esc_html_e( 'Publish preflight', 'npcink-toolbox' ); ?></option>
												</select>
											</label>
											<label>
												<span><?php esc_html_e( 'Topic', 'npcink-toolbox' ); ?></span>
												<input type="text" name="topic" value="latest WordPress AI search trends" />
											</label>
										</div>
										<label>
											<span><?php esc_html_e( 'Working title', 'npcink-toolbox' ); ?></span>
											<input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional title override', 'npcink-toolbox' ); ?>" />
										</label>
										<button type="submit" class="button" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run evidence check', 'npcink-toolbox' ); ?></button>
										<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
									</form>
								</div>
							</div>
						</div>
				</section>

				<section class="npcink-toolbox__card" data-toolbox-cloud-check-panel="image" hidden>
					<div class="npcink-toolbox__cloud-check-group-workspace" data-toolbox-cloud-check-groups>
							<nav class="npcink-toolbox__cloud-check-group-list" aria-label="<?php esc_attr_e( 'Image checks', 'npcink-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__cloud-check-group-button is-active" data-toolbox-cloud-check-group-target="image-smoke" aria-selected="true">
									<span><?php esc_html_e( 'Candidate check', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Candidates', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="image-derivative-preview" aria-selected="false">
									<span><?php esc_html_e( 'Existing image preview', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Derivative', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__cloud-check-group-button" data-toolbox-cloud-check-group-target="image-handoff" aria-selected="false">
									<span><?php esc_html_e( 'Handoff', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Core review', 'npcink-toolbox' ); ?></small>
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
									<strong><?php esc_html_e( 'Core handoff stays in Workflows', 'npcink-toolbox' ); ?></strong>
									<span><?php esc_html_e( 'Use the full Optimize Existing Image flow when a reviewed preview should become one Core proposal, or when batch and URL repair actions are needed.', 'npcink-toolbox' ); ?></span>
								</div>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=tools&toolbox_tool=media-derivative' ) ); ?>"><?php esc_html_e( 'Open Optimize Existing Image', 'npcink-toolbox' ); ?></a>
							</div>
						</div>
					</div>
				</section>

				<section class="npcink-toolbox__card" data-toolbox-cloud-check-panel="site-knowledge" hidden>
					<div class="npcink-toolbox__section-heading">
						<div>
							<h3><?php esc_html_e( 'Site Knowledge search check', 'npcink-toolbox' ); ?></h3>
							<p><?php esc_html_e( 'Use this only to verify read-only Cloud index retrieval. Index status and refresh controls stay in Site Knowledge.', 'npcink-toolbox' ); ?></p>
						</div>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=site-knowledge' ) ); ?>"><?php esc_html_e( 'Manage index', 'npcink-toolbox' ); ?></a>
					</div>
					<?php $this->render_site_knowledge_search_check( false, $cloud_ready ); ?>
				</section>

				<section class="npcink-toolbox__card" data-toolbox-cloud-check-panel="agent-quality" hidden>
					<div class="npcink-toolbox__section-heading">
						<div>
							<h3><?php esc_html_e( 'Agent feedback quality', 'npcink-toolbox' ); ?></h3>
							<p><?php esc_html_e( 'Read-only Cloud eval summary across Site Knowledge, image candidates, AI image generation, and editor image suggestions.', 'npcink-toolbox' ); ?></p>
						</div>
						<button type="button" class="button" data-toolbox-agent-feedback-quality-refresh <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Refresh quality', 'npcink-toolbox' ); ?></button>
					</div>
					<?php if ( ! $cloud_ready ) : ?>
						<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before loading Agent feedback quality.', 'npcink-toolbox' ); ?></div>
					<?php endif; ?>
					<div class="npcink-toolbox__knowledge-summary" data-toolbox-agent-feedback-quality>
						<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'Agent feedback quality has not been loaded yet.', 'npcink-toolbox' ); ?></div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	private function render_content_context_form( array $context ): void {
		$proposal_fields = array(
			'seo_title'             => __( 'SEO title', 'npcink-toolbox' ),
			'seo_description'       => __( 'SEO description', 'npcink-toolbox' ),
			'slug'                  => __( 'Slug', 'npcink-toolbox' ),
			'excerpt'               => __( 'Excerpt', 'npcink-toolbox' ),
			'faq'                   => __( 'FAQ', 'npcink-toolbox' ),
			'answer_summary'        => __( 'Answer summary', 'npcink-toolbox' ),
			'geo_summary'           => __( 'GEO summary', 'npcink-toolbox' ),
			'structured_data_hints' => __( 'Structured data hints', 'npcink-toolbox' ),
		);
		$preview = wp_json_encode( $this->settings->get_content_context_for_ability(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Site Context', 'npcink-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Fill a compact site brief, then tune SEO, AEO, and GEO guidance. Draft buttons only prefill this form; nothing is saved until you click Save content context. Article-specific checks belong in the editor sidebar.', 'npcink-toolbox' ); ?></p>
		</div>

		<form class="npcink-toolbox__settings-form" method="post" action="options.php" data-toolbox-context-form>
			<?php settings_fields( 'npcink_toolbox_content_context' ); ?>

			<div class="npcink-toolbox__draft-actions" aria-label="<?php esc_attr_e( 'Content context draft actions', 'npcink-toolbox' ); ?>">
				<button type="button" class="button" data-toolbox-context-draft="aiBlog"><?php esc_html_e( 'Use AI tech blog template', 'npcink-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-draft="site"><?php esc_html_e( 'Draft from current site content', 'npcink-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-clear><?php esc_html_e( 'Clear form', 'npcink-toolbox' ); ?></button>
				<span><?php esc_html_e( 'Drafts are editable suggestions and do not change posts, media, SEO meta, or provider settings.', 'npcink-toolbox' ); ?></span>
			</div>

			<div class="npcink-toolbox__context-workspace" data-toolbox-context-sections>
				<nav class="npcink-toolbox__context-tabs" aria-label="<?php esc_attr_e( 'Content context sections', 'npcink-toolbox' ); ?>">
					<button type="button" class="npcink-toolbox__context-tab is-active" data-toolbox-context-target="brief" aria-selected="true">
						<span><?php esc_html_e( 'Brief', 'npcink-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Start here', 'npcink-toolbox' ); ?></small>
					</button>
					<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="seo" aria-selected="false">
						<span><?php esc_html_e( 'SEO', 'npcink-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Search snippets', 'npcink-toolbox' ); ?></small>
					</button>
					<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="aeo" aria-selected="false">
						<span><?php esc_html_e( 'AEO', 'npcink-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Answer shape', 'npcink-toolbox' ); ?></small>
					</button>
					<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="geo" aria-selected="false">
						<span><?php esc_html_e( 'GEO', 'npcink-toolbox' ); ?></span>
						<small><?php esc_html_e( 'AI citation signals', 'npcink-toolbox' ); ?></small>
					</button>
					<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="boundaries" aria-selected="false">
						<span><?php esc_html_e( 'Boundaries', 'npcink-toolbox' ); ?></span>
						<small><?php esc_html_e( 'Claims and preview', 'npcink-toolbox' ); ?></small>
					</button>
				</nav>

				<div class="npcink-toolbox__context-panels">
					<section class="npcink-toolbox__card" data-toolbox-context-panel="brief">
						<h2><?php esc_html_e( 'Brief', 'npcink-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Define who the site is for and how suggestions should sound. This is the minimum useful setup.', 'npcink-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'Brief fields', 'npcink-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="brief-profile" aria-selected="true">
									<span><?php esc_html_e( 'Site profile', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Positioning', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="brief-audience" aria-selected="false">
									<span><?php esc_html_e( 'Audience', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Readers', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="brief-voice" aria-selected="false">
									<span><?php esc_html_e( 'Voice', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Tone', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="brief-keywords" aria-selected="false">
									<span><?php esc_html_e( 'Keywords', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Primary terms', 'npcink-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-profile">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'Site profile', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Tell AI what this site is, who it helps, and what kind of suggestion it should produce first.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'site_positioning', __( 'Site positioning', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-audience" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'Audience', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'List the reader groups AI should optimize explanations, examples, and terminology for.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'target_audience', __( 'Target audience', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-voice" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'Voice', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Set the writing posture, level of detail, and phrases AI should favor or avoid.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'brand_voice', __( 'Brand voice', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="brief-keywords" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'Keywords', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Use this short list as the main topic vocabulary before adding SEO-specific long-tail terms.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'primary_keywords', __( 'Primary keywords', 'npcink-toolbox' ), $context ); ?>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="seo" hidden>
						<h2><?php esc_html_e( 'SEO', 'npcink-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Control search-oriented metadata, keyword coverage, and which SEO fields AI may suggest.', 'npcink-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'SEO fields', 'npcink-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="seo-keywords" aria-selected="true">
									<span><?php esc_html_e( 'Keywords', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Long-tail terms', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="seo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Search guidance', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="seo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-keywords">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'SEO keywords', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Add supporting long-tail phrases here. Primary keywords stay in the Brief section so the first setup path remains obvious.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'long_tail_keywords', __( 'Long-tail keywords', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-rules" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'SEO rules', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Describe title, description, slug, excerpt, and internal-link preferences for proposal-ready suggestions.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'seo_rules', __( 'SEO rules', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'SEO fields AI may suggest', 'npcink-toolbox' ); ?></legend>
										<?php foreach ( array( 'seo_title', 'seo_description', 'slug', 'excerpt' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="aeo" hidden>
						<h2><?php esc_html_e( 'AEO', 'npcink-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Shape answer-engine output: direct answers, FAQs, definitions, and step-style responses.', 'npcink-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'AEO fields', 'npcink-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="aeo-rules" aria-selected="true">
									<span><?php esc_html_e( 'Rules', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Answer guidance', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="aeo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'FAQ and summary', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="aeo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-rules">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'AEO rules', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Start with a direct answer, then add conditions, steps, limits, and short followups.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'aeo_rules', __( 'AEO rules', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_faq_generation', __( 'Allow FAQ suggestions', 'npcink-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_aeo_summary', __( 'Allow AEO answer summary suggestions', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'AEO fields AI may suggest', 'npcink-toolbox' ); ?></legend>
										<?php foreach ( array( 'faq', 'answer_summary' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="geo" hidden>
						<h2><?php esc_html_e( 'GEO', 'npcink-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Guide AI-readable entity signals, standalone conclusions, and citation-friendly summaries.', 'npcink-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'GEO fields', 'npcink-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="geo-entities" aria-selected="true">
									<span><?php esc_html_e( 'Entities', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Signals', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Summary guidance', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'GEO and schema', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-entities">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'Entities', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'List people, products, standards, projects, and concepts AI should recognize as important context.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'entity_keywords', __( 'Entity keywords', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-rules" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'GEO rules', 'npcink-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Keep key conclusions standalone, define important entities, and separate implemented facts from plans.', 'npcink-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'geo_rules', __( 'GEO rules', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_geo_summary', __( 'Allow GEO summary suggestions', 'npcink-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_structured_data_suggestions', __( 'Allow structured data suggestions', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'GEO fields AI may suggest', 'npcink-toolbox' ); ?></legend>
										<?php foreach ( array( 'geo_summary', 'structured_data_hints' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="boundaries" hidden>
						<h2><?php esc_html_e( 'Boundaries', 'npcink-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Limit what AI can claim and inspect the read-only payload exposed to callers.', 'npcink-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'Boundary fields', 'npcink-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="boundaries-allowed" aria-selected="true">
									<span><?php esc_html_e( 'Allowed claims', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Can say', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-forbidden" aria-selected="false">
									<span><?php esc_html_e( 'Forbidden claims', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Must not say', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-exceptions" aria-selected="false">
									<span><?php esc_html_e( 'Exceptions', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Special cases', 'npcink-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-preview" aria-selected="false">
									<span><?php esc_html_e( 'Ability preview', 'npcink-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Read-only payload', 'npcink-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-allowed">
									<?php $this->render_context_list_field( 'allowed_claims', __( 'Allowed claims', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-forbidden" hidden>
									<?php $this->render_context_list_field( 'forbidden_claims', __( 'Forbidden claims', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-exceptions" hidden>
									<?php $this->render_context_list_field( 'disallowed_topics', __( 'Disallowed topics', 'npcink-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'cautious_topics', __( 'Cautious topics', 'npcink-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'no_structured_output_topics', __( 'No structured output topics', 'npcink-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'human_confirmation_required', __( 'Human confirmation required', 'npcink-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-preview" hidden>
									<pre class="npcink-toolbox__result"><?php echo esc_html( (string) $preview ); ?></pre>
								</section>
							</div>
						</div>
					</section>
				</div>
			</div>

			<p class="description"><?php esc_html_e( 'Final WordPress writes still require Core proposal approval; third-party AI receives this context as suggestion-only guidance.', 'npcink-toolbox' ); ?></p>
			<?php submit_button( __( 'Save content context', 'npcink-toolbox' ) ); ?>
		</form>

		<?php
	}

	private function render_tool_cards( bool $cloud_ready ): void {
		$tools = array(
			array(
				'group'       => __( 'Media', 'npcink-toolbox' ),
				'group_id'    => 'media',
				'id'          => 'media-derivative',
				'endpoint'    => 'media-derivative-handoff',
				'title'       => __( 'Optimize Existing Image', 'npcink-toolbox' ),
				'description' => __( 'Choose a media-library image, review metadata, generate a preview, then submit one Core optimization proposal.', 'npcink-toolbox' ),
				'custom'      => 'media_derivative',
			),
			array(
				'group'       => __( 'Media', 'npcink-toolbox' ),
				'group_id'    => 'media',
				'id'          => 'media-brief',
				'endpoint'    => 'flows/media-brief',
				'title'       => __( 'Media Brief', 'npcink-toolbox' ),
				'description' => __( 'Use an existing post id to plan image prompts and media SEO actions.', 'npcink-toolbox' ),
				'field'       => 'post_id',
				'placeholder' => __( 'Post ID', 'npcink-toolbox' ),
				'button'      => __( 'Plan media', 'npcink-toolbox' ),
			),
			array(
				'group'       => __( 'AI Site Helpers', 'npcink-toolbox' ),
				'group_id'    => 'site-helpers',
				'id'          => 'ai-media-alt-suggestions',
				'endpoint'    => 'ai/site-helpers',
				'title'       => __( 'Media ALT Suggestions', 'npcink-toolbox' ),
				'description' => __( 'Sample recent image metadata with missing or weak ALT and return reviewable ALT/caption ideas.', 'npcink-toolbox' ),
				'intent'      => 'media_alt_suggestions',
				'button'      => __( 'Suggest media ALT', 'npcink-toolbox' ),
				'custom'      => 'hosted_ai_site_helper',
			),
			array(
				'group'       => __( 'AI Site Helpers', 'npcink-toolbox' ),
				'group_id'    => 'site-helpers',
				'id'          => 'ai-content-snapshot-suggestions',
				'endpoint'    => 'ai/site-helpers',
				'title'       => __( 'Content Snapshot Suggestions', 'npcink-toolbox' ),
				'description' => __( 'Review a bounded public content snapshot and return a few content opportunities, not a full site audit.', 'npcink-toolbox' ),
				'intent'      => 'content_snapshot_suggestions',
				'button'      => __( 'Suggest content opportunities', 'npcink-toolbox' ),
				'custom'      => 'hosted_ai_site_helper',
			),
			array(
				'group'       => __( 'Governed Handoffs', 'npcink-toolbox' ),
				'group_id'    => 'governed-handoffs',
				'id'          => 'article-plan',
				'endpoint'    => 'flows/article-plan',
				'title'       => __( 'Reviewed Draft Handoff', 'npcink-toolbox' ),
				'description' => __( 'Use only after a human-reviewed draft exists; prepare a Core-ready article_write_plan without submitting or approving it.', 'npcink-toolbox' ),
				'custom'      => 'article_plan',
			),
			array(
				'group'       => __( 'Governed Handoffs', 'npcink-toolbox' ),
				'group_id'    => 'governed-handoffs',
				'id'          => 'image-candidate-adoption',
				'endpoint'    => 'flows/image-candidate-adoption-plan',
				'title'       => __( 'Adopt New Image', 'npcink-toolbox' ),
				'description' => __( 'Use only after a reviewed stock, generated, owned, or external image candidate exists.', 'npcink-toolbox' ),
				'custom'      => 'image_candidate_adoption',
			),
			array(
				'group'       => __( 'Fallback Bundles', 'npcink-toolbox' ),
				'group_id'    => 'fallback-bundles',
				'id'          => 'article-brief',
				'endpoint'    => 'flows/article-brief',
				'title'       => __( 'Article Planning Bundle', 'npcink-toolbox' ),
				'description' => __( 'Advanced fallback package for combined research, image, knowledge, outline, and handoff context when the editor path is not enough.', 'npcink-toolbox' ),
				'field'       => 'topic',
				'placeholder' => __( 'Article topic', 'npcink-toolbox' ),
				'button'      => __( 'Build bundle', 'npcink-toolbox' ),
			),
			array(
				'group'       => __( 'Fallback Bundles', 'npcink-toolbox' ),
				'group_id'    => 'fallback-bundles',
				'id'          => 'article-assistant',
				'endpoint'    => 'flows/article-assistant',
				'title'       => __( 'Article Assistant Fallback', 'npcink-toolbox' ),
				'description' => __( 'Advanced fallback workbench for existing support artifacts and an optional reviewed draft; it does not write the article body.', 'npcink-toolbox' ),
				'custom'      => 'article_assistant',
			),
		);
		$tool_groups = array(
			'media'             => array(
				'title'       => __( 'Media', 'npcink-toolbox' ),
				'description' => __( 'Start here for existing-image optimization and bounded media planning.', 'npcink-toolbox' ),
			),
			'site-helpers'      => array(
				'title'       => __( 'Site Helpers', 'npcink-toolbox' ),
				'description' => __( 'Small hosted checks for media ALT and public content opportunities.', 'npcink-toolbox' ),
			),
			'governed-handoffs' => array(
				'title'       => __( 'Governed Handoffs', 'npcink-toolbox' ),
				'description' => __( 'Use after reviewed draft, selected image, or editor choices exist.', 'npcink-toolbox' ),
			),
			'fallback-bundles'  => array(
				'title'       => __( 'Fallback Bundles', 'npcink-toolbox' ),
				'description' => __( 'Advanced fallback packages; not the daily writing path.', 'npcink-toolbox' ),
			),
		);
		$advanced_tool_groups = array(
			'governed-handoffs' => true,
			'fallback-bundles'  => true,
		);
		?>
		<div class="npcink-toolbox__tool-workspace" data-toolbox-tools>
			<div class="npcink-toolbox__workflow-scope">
				<strong><?php esc_html_e( 'Workflow scope', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Use this workbench for media operations, small site helpers, reviewed handoffs, and advanced fallback packages. Article-specific writing support stays in the editor sidebar.', 'npcink-toolbox' ); ?></span>
			</div>
			<div class="npcink-toolbox__tool-group-tabs" aria-label="<?php esc_attr_e( 'Workflow groups', 'npcink-toolbox' ); ?>">
				<?php
				$rendered_groups = array();
				$advanced_groups = array();
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
					if ( isset( $advanced_tool_groups[ $group_id ] ) ) {
						$advanced_groups[] = array(
							'group_id'    => $group_id,
							'group_meta'  => $group_meta,
							'active'      => 0 === $index,
						);
						continue;
					}
					?>
					<button type="button" class="npcink-toolbox__tool-group-tab <?php echo 0 === $index ? 'is-active' : ''; ?>" data-toolbox-tool-group-target="<?php echo esc_attr( $group_id ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>">
						<span><?php echo esc_html( (string) $group_meta['title'] ); ?></span>
						<small><?php echo esc_html( (string) $group_meta['description'] ); ?></small>
					</button>
				<?php endforeach; ?>
				<?php if ( ! empty( $advanced_groups ) ) : ?>
					<details class="npcink-toolbox__advanced-workflow-groups" data-toolbox-advanced-workflows>
						<summary>
							<span><?php esc_html_e( 'Advanced and fallback', 'npcink-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Use only after reviewed inputs exist or the editor path is not enough.', 'npcink-toolbox' ); ?></small>
						</summary>
						<div class="npcink-toolbox__advanced-workflow-buttons">
							<?php foreach ( $advanced_groups as $advanced_group ) : ?>
								<button type="button" class="npcink-toolbox__tool-group-tab is-secondary <?php echo $advanced_group['active'] ? 'is-active' : ''; ?>" data-toolbox-tool-group-target="<?php echo esc_attr( (string) $advanced_group['group_id'] ); ?>" aria-selected="<?php echo $advanced_group['active'] ? 'true' : 'false'; ?>">
									<span><?php echo esc_html( (string) $advanced_group['group_meta']['title'] ); ?></span>
									<small><?php echo esc_html( (string) $advanced_group['group_meta']['description'] ); ?></small>
								</button>
							<?php endforeach; ?>
						</div>
					</details>
				<?php endif; ?>
			</div>

			<div class="npcink-toolbox__tool-list" aria-label="<?php esc_attr_e( 'Tool actions', 'npcink-toolbox' ); ?>">
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
						<div class="npcink-toolbox__tool-group-panel" data-toolbox-tool-group-panel="<?php echo esc_attr( $group_id ); ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
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
					if ( 'hosted_ai_site_helper' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_hosted_ai_site_helper_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							(string) $tool['intent'],
							(string) $tool['button'],
							0 === $index,
							$cloud_ready
						);
						continue;
					}
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

	private function render_hosted_ai_site_helper_tool( string $endpoint, string $title, string $description, string $tool_id, string $intent, string $button, bool $active = false, bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="npcink-toolbox__example is-ai">
				<strong><?php esc_html_e( 'Hosted AI site helper', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Toolbox sends only a small public-site or media metadata sample to Cloud. Results are reviewable suggestions only; no media, content, SEO, or proposal data is changed.', 'npcink-toolbox' ); ?></span>
			</div>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running AI site helpers.', 'npcink-toolbox' ); ?></div>
			<?php endif; ?>
			<input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>" />
			<label>
				<span><?php esc_html_e( 'Optional focus', 'npcink-toolbox' ); ?></span>
				<textarea name="focus" rows="3" placeholder="<?php esc_attr_e( 'Optional priority, audience, image type, or content area to review', 'npcink-toolbox' ); ?>"></textarea>
			</label>
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
						<strong><?php esc_html_e( 'Hosted AI route', 'npcink-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Toolbox sends one lightweight draft-support request through the Cloud hosted runtime when the site is connected. The result is a reviewable suggestion, not a finished article.', 'npcink-toolbox' ); ?></span>
					</div>
					<?php if ( ! $cloud_ready ) : ?>
						<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running hosted AI support.', 'npcink-toolbox' ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
			<input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>" />
			<input type="hidden" name="post_type" value="post" />
			<input type="hidden" name="post_status" value="draft" />
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Fixed support flow', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'This runs one bounded suggestion flow from the supplied article, selected text, topic, or brief. It does not write posts, assign terms, insert links, import media, or publish.', 'npcink-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Input scope', 'npcink-toolbox' ); ?></span>
				<select name="context_scope">
					<option value="auto"><?php esc_html_e( 'Auto: selected text when present, otherwise full article', 'npcink-toolbox' ); ?></option>
					<option value="full_article"><?php esc_html_e( 'Full article context', 'npcink-toolbox' ); ?></option>
					<option value="selected_text"><?php esc_html_e( 'Selected text or supplied snippet', 'npcink-toolbox' ); ?></option>
					<option value="topic_only"><?php esc_html_e( 'Topic or short brief only', 'npcink-toolbox' ); ?></option>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Post ID (optional)', 'npcink-toolbox' ); ?></span>
				<input type="number" min="0" step="1" name="post_id" placeholder="<?php esc_attr_e( 'Use 0 for topic-only runs', 'npcink-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Title or topic', 'npcink-toolbox' ); ?></span>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Working title or article topic', 'npcink-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Excerpt or short brief', 'npcink-toolbox' ); ?></span>
				<textarea name="excerpt" rows="3" placeholder="<?php esc_attr_e( 'Optional summary, angle, audience, or constraints', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Draft text or notes', 'npcink-toolbox' ); ?></span>
				<textarea name="content" rows="5" placeholder="<?php esc_attr_e( 'Optional draft body, notes, or source outline', 'npcink-toolbox' ); ?>"></textarea>
			</label>
				<button type="submit" class="button button-primary" <?php echo disabled( $hosted_ai && ! $cloud_ready, true, false ); ?>><?php echo esc_html( $button ); ?></button>
				<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
			</form>
		<?php
	}

	private function render_image_source_candidates_smoke_form( bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="image-candidates">
			<h3><?php esc_html_e( 'Image source check', 'npcink-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Test Cloud-managed Unsplash/Pixabay/Pexels image-source candidates and preserve attribution metadata.', 'npcink-toolbox' ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before testing Cloud image-source candidates.', 'npcink-toolbox' ); ?></div>
			<?php endif; ?>
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Cloud smoke test', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'A successful result shows Cloud runtime, provider mode, candidate count, preview image, suggested filename, license review status, and any reviewed AI image generation handoff. This does not import media or write WordPress.', 'npcink-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Image search query', 'npcink-toolbox' ); ?></span>
				<input type="text" name="query" value="<?php esc_attr_e( 'wordpress article hero image', 'npcink-toolbox' ); ?>" />
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Provider', 'npcink-toolbox' ); ?></span>
					<select name="provider">
						<option value="auto"><?php esc_html_e( 'Cloud auto', 'npcink-toolbox' ); ?></option>
						<option value="unsplash"><?php esc_html_e( 'Unsplash', 'npcink-toolbox' ); ?></option>
						<option value="pixabay"><?php esc_html_e( 'Pixabay', 'npcink-toolbox' ); ?></option>
						<option value="pexels"><?php esc_html_e( 'Pexels', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Orientation', 'npcink-toolbox' ); ?></span>
					<select name="orientation">
						<option value="landscape"><?php esc_html_e( 'Landscape', 'npcink-toolbox' ); ?></option>
						<option value="portrait"><?php esc_html_e( 'Portrait', 'npcink-toolbox' ); ?></option>
						<option value="squarish"><?php esc_html_e( 'Squarish', 'npcink-toolbox' ); ?></option>
						<option value=""><?php esc_html_e( 'Any', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Candidate count', 'npcink-toolbox' ); ?></span>
					<input type="number" min="1" max="8" step="1" name="per_page" value="3" />
				</label>
				<label>
					<span><?php esc_html_e( 'Color filter', 'npcink-toolbox' ); ?></span>
					<input type="text" name="color" placeholder="<?php esc_attr_e( 'Optional Unsplash color filter', 'npcink-toolbox' ); ?>" />
				</label>
			</div>
			<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run image source check', 'npcink-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_ai_image_generation_smoke_form( bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__inline-form" data-toolbox-endpoint="ai/image-generation">
			<h3><?php esc_html_e( 'AI image generation check', 'npcink-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Generate one reviewed-prompt AI image candidate through hosted Cloud runtime. The result stays candidate-only and does not import media or write WordPress.', 'npcink-toolbox' ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before testing hosted AI image generation.', 'npcink-toolbox' ); ?></div>
			<?php endif; ?>
			<label>
				<span><?php esc_html_e( 'Reviewed prompt', 'npcink-toolbox' ); ?></span>
				<textarea name="prompt" rows="4"><?php echo esc_textarea( __( 'Create an original editorial header image for a WordPress article about AI image generation governance. Composition: 16:9 image suitable for a WordPress article. Style: clean editorial photo illustration, natural light, high quality. Avoid visible text, brand logos, watermarks, distorted hands or faces, and copyrighted characters.', 'npcink-toolbox' ) ); ?></textarea>
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Aspect ratio', 'npcink-toolbox' ); ?></span>
					<select name="aspect_ratio">
						<option value="16:9"><?php esc_html_e( '16:9', 'npcink-toolbox' ); ?></option>
						<option value="1:1"><?php esc_html_e( '1:1', 'npcink-toolbox' ); ?></option>
						<option value="4:3"><?php esc_html_e( '4:3', 'npcink-toolbox' ); ?></option>
						<option value="3:4"><?php esc_html_e( '3:4', 'npcink-toolbox' ); ?></option>
						<option value="9:16"><?php esc_html_e( '9:16', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Candidate count', 'npcink-toolbox' ); ?></span>
					<input type="number" min="1" max="4" step="1" name="n" value="1" />
				</label>
			</div>
			<input type="hidden" name="resolution" value="high" />
			<input type="hidden" name="response_format" value="url" />
			<input type="hidden" name="purpose" value="cloud_check_ai_image_generation" />
			<input type="hidden" name="prompt_reviewed_by_operator" value="1" />
			<input type="hidden" name="media_title" value="<?php esc_attr_e( 'AI image generation governance', 'npcink-toolbox' ); ?>" />
			<input type="hidden" name="media_alt" value="<?php esc_attr_e( 'Original editorial image for AI image generation governance.', 'npcink-toolbox' ); ?>" />
			<input type="hidden" name="media_description" value="<?php esc_attr_e( 'AI-generated image candidate for the hosted image generation smoke test. Review it before importing or setting it as featured media.', 'npcink-toolbox' ); ?>" />
			<button type="submit" class="button button-primary" <?php echo disabled( ! $cloud_ready, true, false ); ?>><?php esc_html_e( 'Run AI image check', 'npcink-toolbox' ); ?></button>
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
				__( 'text "%1$s", %2$s, %3$d%% opacity, %4$dpx font, %5$dpx margin', 'npcink-toolbox' ),
				(string) ( $toolbox_policy['watermark_text'] ?? 'AI' ),
				ucwords( str_replace( '_', ' ', (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ) ) ),
				(int) ( $toolbox_policy['watermark_opacity'] ?? 80 ),
				(int) ( $toolbox_policy['watermark_font_size'] ?? 48 ),
				(int) ( $toolbox_policy['watermark_margin'] ?? 24 )
			);
		}

		if ( empty( $toolbox_policy['watermark_configured'] ) ) {
			return __( 'off or incomplete', 'npcink-toolbox' );
		}

		return sprintf(
			/* translators: 1: position, 2: opacity, 3: scale, 4: margin. */
			__( '%1$s, %2$d%% opacity, %3$d%% scale, %4$dpx margin', 'npcink-toolbox' ),
			ucwords( str_replace( '_', ' ', (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ) ) ),
			(int) ( $toolbox_policy['watermark_opacity'] ?? 80 ),
			(int) ( $toolbox_policy['watermark_scale'] ?? 20 ),
			(int) ( $toolbox_policy['watermark_margin'] ?? 24 )
		);
	}

	private function render_media_derivative_toolbox_defaults( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__example">
			<strong><?php esc_html_e( 'Toolbox defaults', 'npcink-toolbox' ); ?></strong>
			<span>
				<?php
				printf(
					/* translators: 1: format, 2: max width, 3: quality. */
					esc_html__( '%1$s, %2$dpx, quality %3$d. Watermark: %4$s.', 'npcink-toolbox' ),
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

	private function render_media_derivative_policy_settings( array $toolbox_policy ): void {
		?>
		<details class="npcink-toolbox__card npcink-toolbox__result-details">
			<summary><?php esc_html_e( 'Media optimization defaults', 'npcink-toolbox' ); ?></summary>
			<p class="description"><?php esc_html_e( 'Toolbox stores the operator defaults for media derivative previews and Core proposal handoffs. Core still owns proposal review, preflight, and audit.', 'npcink-toolbox' ); ?></p>
			<form class="npcink-toolbox__settings-form" method="post" action="options.php">
				<?php settings_fields( 'npcink_toolbox_media_optimization' ); ?>
				<label class="npcink-toolbox__check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[enabled]" value="1" <?php checked( ! empty( $toolbox_policy['enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Enable media optimization defaults', 'npcink-toolbox' ); ?></span>
				</label>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Default format', 'npcink-toolbox' ); ?></span>
						<select name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[target_format]">
							<?php foreach ( $this->settings->allowed_media_derivative_formats() as $format ) : ?>
								<option value="<?php echo esc_attr( $format ); ?>" <?php selected( (string) $toolbox_policy['target_format'], $format ); ?>><?php echo esc_html( strtoupper( $format ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Default max width', 'npcink-toolbox' ); ?></span>
						<input type="number" min="320" max="7680" step="1" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[max_width]" value="<?php echo esc_attr( (string) $toolbox_policy['max_width'] ); ?>" />
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Default quality', 'npcink-toolbox' ); ?></span>
					<input type="number" min="1" max="100" step="1" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[quality]" value="<?php echo esc_attr( (string) $toolbox_policy['quality'] ); ?>" />
				</label>
				<div class="npcink-toolbox__split">
					<label class="npcink-toolbox__check">
						<input type="checkbox" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_enabled]" value="1" <?php checked( ! empty( $toolbox_policy['watermark_enabled'] ) ); ?> />
						<span><?php esc_html_e( 'Use default watermark', 'npcink-toolbox' ); ?></span>
					</label>
					<label>
						<span><?php esc_html_e( 'Default watermark type', 'npcink-toolbox' ); ?></span>
						<select name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_type]">
							<option value="image" <?php selected( (string) $toolbox_policy['watermark_type'], 'image' ); ?>><?php esc_html_e( 'Image/logo', 'npcink-toolbox' ); ?></option>
							<option value="text" <?php selected( (string) $toolbox_policy['watermark_type'], 'text' ); ?>><?php esc_html_e( 'Text', 'npcink-toolbox' ); ?></option>
						</select>
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Logo attachment ID', 'npcink-toolbox' ); ?></span>
						<input type="number" min="0" step="1" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_attachment_id]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_attachment_id'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Position', 'npcink-toolbox' ); ?></span>
						<select name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_position]">
							<?php foreach ( $this->settings->allowed_media_watermark_positions() as $position ) : ?>
								<option value="<?php echo esc_attr( $position ); ?>" <?php selected( (string) $toolbox_policy['watermark_position'], $position ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $position ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Text', 'npcink-toolbox' ); ?></span>
						<input type="text" maxlength="64" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_text]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_text'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Font size', 'npcink-toolbox' ); ?></span>
						<input type="number" min="8" max="256" step="1" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_font_size]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_font_size'] ); ?>" />
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Opacity', 'npcink-toolbox' ); ?></span>
						<input type="number" min="0" max="100" step="1" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_opacity]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_opacity'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Image scale', 'npcink-toolbox' ); ?></span>
						<input type="number" min="1" max="100" step="1" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_scale]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_scale'] ); ?>" />
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Text color', 'npcink-toolbox' ); ?></span>
						<input type="text" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_color]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_color'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Background', 'npcink-toolbox' ); ?></span>
						<input type="text" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_background]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_background'] ); ?>" />
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Margin', 'npcink-toolbox' ); ?></span>
						<input type="number" min="0" max="1000" step="1" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[watermark_margin]" value="<?php echo esc_attr( (string) $toolbox_policy['watermark_margin'] ); ?>" />
					</label>
					<label class="npcink-toolbox__check">
						<input type="checkbox" name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[use_cloud_when_available]" value="1" <?php checked( ! empty( $toolbox_policy['use_cloud_when_available'] ) ); ?> />
						<span><?php esc_html_e( 'Use Cloud execution when available', 'npcink-toolbox' ); ?></span>
					</label>
				</div>
				<?php submit_button( __( 'Save media optimization defaults', 'npcink-toolbox' ) ); ?>
			</form>
		</details>
		<?php
	}

	private function render_media_derivative_picker_controls(): void {
		?>
		<div class="npcink-toolbox__media-picker">
			<div class="npcink-toolbox__media-preview" data-toolbox-media-preview>
				<span><?php esc_html_e( 'No image selected', 'npcink-toolbox' ); ?></span>
			</div>
			<div>
				<label>
					<span><?php esc_html_e( 'Attachment ID', 'npcink-toolbox' ); ?></span>
					<input type="number" min="1" step="1" name="attachment_id" placeholder="<?php esc_attr_e( 'Attachment ID', 'npcink-toolbox' ); ?>" data-toolbox-media-attachment />
				</label>
				<label>
					<span><?php esc_html_e( 'Image URL', 'npcink-toolbox' ); ?></span>
					<input type="url" name="attachment_url" placeholder="<?php esc_attr_e( 'Paste a local uploads URL', 'npcink-toolbox' ); ?>" data-toolbox-media-url />
				</label>
				<div class="npcink-toolbox__inline-actions">
					<button type="button" class="button" data-toolbox-select-media><?php esc_html_e( 'Select from media library', 'npcink-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-resolve-media-url><?php esc_html_e( 'Resolve URL', 'npcink-toolbox' ); ?></button>
					<span data-toolbox-media-name><?php esc_html_e( 'Choose one local image attachment.', 'npcink-toolbox' ); ?></span>
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
				<span><?php esc_html_e( 'Format override', 'npcink-toolbox' ); ?></span>
				<select name="target_format">
					<option value=""><?php esc_html_e( 'Use Toolbox default', 'npcink-toolbox' ); ?></option>
					<?php foreach ( array( 'webp', 'avif', 'jpeg', 'png', 'original' ) as $format ) : ?>
						<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( strtoupper( $format ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Max width override', 'npcink-toolbox' ); ?></span>
				<input type="number" min="320" max="7680" step="1" name="max_width" placeholder="<?php echo esc_attr( (string) $toolbox_policy['max_width'] ); ?>" />
			</label>
		</div>
		<label>
			<span><?php esc_html_e( 'Quality override', 'npcink-toolbox' ); ?></span>
			<input type="number" min="1" max="100" step="1" name="quality" placeholder="<?php echo esc_attr( (string) $toolbox_policy['quality'] ); ?>" />
		</label>
		<?php
	}

	private function render_media_derivative_watermark_controls( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Watermark override', 'npcink-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Use Toolbox watermark defaults unless this run needs a specific override. Text watermark overrides do not need a logo attachment; image/logo overrides use the configured Toolbox logo source for this run.', 'npcink-toolbox' ); ?></p>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Watermark mode', 'npcink-toolbox' ); ?></span>
					<select name="watermark_mode">
						<option value="default"><?php esc_html_e( 'Use Toolbox default', 'npcink-toolbox' ); ?></option>
						<option value="off"><?php esc_html_e( 'No watermark', 'npcink-toolbox' ); ?></option>
						<option value="text"><?php esc_html_e( 'Text watermark', 'npcink-toolbox' ); ?></option>
						<option value="image"><?php esc_html_e( 'Image/logo watermark', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Position', 'npcink-toolbox' ); ?></span>
					<select name="watermark_position">
						<?php foreach ( array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ) as $position ) : ?>
							<option value="<?php echo esc_attr( $position ); ?>" <?php selected( (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ), $position ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $position ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Text', 'npcink-toolbox' ); ?></span>
					<input type="text" maxlength="64" name="watermark_text" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_text'] ?? 'AI' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Font size', 'npcink-toolbox' ); ?></span>
					<input type="number" min="8" max="256" step="1" name="watermark_font_size" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_font_size'] ?? 48 ) ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Text color', 'npcink-toolbox' ); ?></span>
					<input type="text" name="watermark_color" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_color'] ?? '#FFFFFF' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Background', 'npcink-toolbox' ); ?></span>
					<input type="text" name="watermark_background" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_background'] ?? 'rgba(0,0,0,0.35)' ) ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Opacity', 'npcink-toolbox' ); ?></span>
					<input type="number" min="0" max="100" step="1" name="watermark_opacity" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_opacity'] ?? 80 ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Image scale', 'npcink-toolbox' ); ?></span>
					<input type="number" min="1" max="100" step="1" name="watermark_scale" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_scale'] ?? 20 ) ); ?>" />
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Margin', 'npcink-toolbox' ); ?></span>
				<input type="number" min="0" max="1000" step="1" name="watermark_margin" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_margin'] ?? 24 ) ); ?>" />
			</label>
		</div>
		<?php
	}

	private function render_media_derivative_crop_controls(): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Crop override', 'npcink-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Optional one-run crop for common publishing ratios. Cloud returns only a derivative preview; Core still governs adoption.', 'npcink-toolbox' ); ?></p>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Crop ratio', 'npcink-toolbox' ); ?></span>
					<select name="crop_aspect_ratio">
						<option value=""><?php esc_html_e( 'No crop', 'npcink-toolbox' ); ?></option>
						<option value="16:9"><?php esc_html_e( '16:9 landscape', 'npcink-toolbox' ); ?></option>
						<option value="4:3"><?php esc_html_e( '4:3 landscape', 'npcink-toolbox' ); ?></option>
						<option value="1:1"><?php esc_html_e( '1:1 square', 'npcink-toolbox' ); ?></option>
						<option value="3:4"><?php esc_html_e( '3:4 portrait', 'npcink-toolbox' ); ?></option>
						<option value="9:16"><?php esc_html_e( '9:16 portrait', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Crop anchor', 'npcink-toolbox' ); ?></span>
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
			<h3><?php esc_html_e( 'Existing image preview', 'npcink-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Generate a short-lived Cloud derivative preview for one existing media-library image. This check does not submit a Core proposal or write media.', 'npcink-toolbox' ); ?></p>
			<?php $this->render_media_derivative_toolbox_defaults( $toolbox_policy ); ?>
			<?php $this->render_media_derivative_picker_controls(); ?>
			<?php $this->render_media_derivative_format_controls( $toolbox_policy ); ?>
			<?php $this->render_media_derivative_crop_controls(); ?>
			<?php $this->render_media_derivative_watermark_controls( $toolbox_policy ); ?>
			<button type="button" class="button button-primary" data-toolbox-run-media-derivative><?php esc_html_e( 'Generate preview', 'npcink-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_image_candidate_adoption_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Button flow', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Pick or paste one reviewed image, add basic media details, then submit the returned plan to Core for approval. Toolbox does not import media directly.', 'npcink-toolbox' ); ?></span>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Selected image URL', 'npcink-toolbox' ); ?></span>
					<input type="url" name="download_url" placeholder="<?php esc_attr_e( 'https://example.com/image.jpg', 'npcink-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Source type', 'npcink-toolbox' ); ?></span>
					<select name="source_type">
						<option value="stock"><?php esc_html_e( 'Stock image', 'npcink-toolbox' ); ?></option>
						<option value="ai_generated"><?php esc_html_e( 'AI generated', 'npcink-toolbox' ); ?></option>
						<option value="owned"><?php esc_html_e( 'Owned image', 'npcink-toolbox' ); ?></option>
						<option value="external"><?php esc_html_e( 'External image', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Source page', 'npcink-toolbox' ); ?></span>
					<input type="url" name="source_url" placeholder="<?php esc_attr_e( 'License or source page URL', 'npcink-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Provider', 'npcink-toolbox' ); ?></span>
					<input type="text" name="provider" placeholder="<?php esc_attr_e( 'unsplash, pixabay, openai, manual', 'npcink-toolbox' ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Media title', 'npcink-toolbox' ); ?></span>
					<input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional media title', 'npcink-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Alt text', 'npcink-toolbox' ); ?></span>
					<input type="text" name="alt" placeholder="<?php esc_attr_e( 'Describe the image for accessibility', 'npcink-toolbox' ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Attach to post ID', 'npcink-toolbox' ); ?></span>
					<input type="number" min="1" step="1" name="post_id" placeholder="<?php esc_attr_e( 'Optional post ID', 'npcink-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Approved file name', 'npcink-toolbox' ); ?></span>
					<input type="text" name="file_name" placeholder="<?php esc_attr_e( 'Optional approved filename', 'npcink-toolbox' ); ?>" />
				</label>
			</div>
			<label class="npcink-toolbox__check">
				<input type="checkbox" name="set_featured_image" value="1" />
				<span><?php esc_html_e( 'Set as featured image after approval', 'npcink-toolbox' ); ?></span>
			</label>
			<label>
				<span><?php esc_html_e( 'Attribution', 'npcink-toolbox' ); ?></span>
				<textarea name="attribution_text" rows="2" placeholder="<?php esc_attr_e( 'Photographer, license, prompt disclosure, or required credit.', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<details class="npcink-toolbox__result-details">
				<summary><?php esc_html_e( 'Advanced candidate details', 'npcink-toolbox' ); ?></summary>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Thumbnail URL', 'npcink-toolbox' ); ?></span>
						<input type="url" name="thumbnail_url" placeholder="<?php esc_attr_e( 'Optional preview URL', 'npcink-toolbox' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'License review', 'npcink-toolbox' ); ?></span>
						<select name="license_review_status">
							<option value="required"><?php esc_html_e( 'Review required', 'npcink-toolbox' ); ?></option>
							<option value="reviewed"><?php esc_html_e( 'Reviewed', 'npcink-toolbox' ); ?></option>
							<option value="not_required"><?php esc_html_e( 'Not required', 'npcink-toolbox' ); ?></option>
						</select>
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Prompt', 'npcink-toolbox' ); ?></span>
						<input type="text" name="prompt" placeholder="<?php esc_attr_e( 'Optional generation prompt', 'npcink-toolbox' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Model', 'npcink-toolbox' ); ?></span>
						<input type="text" name="model" placeholder="<?php esc_attr_e( 'Optional generation model', 'npcink-toolbox' ); ?>" />
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Candidate JSON', 'npcink-toolbox' ); ?></span>
					<textarea name="image_candidate" rows="6" placeholder="<?php esc_attr_e( 'Optional. Paste one image_candidate.v1 object to override the fields above.', 'npcink-toolbox' ); ?>"></textarea>
				</label>
			</details>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Build import proposal plan', 'npcink-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_article_assistant_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Local workbench', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'This composes a planning artifact only. It does not run a cloud writer, submit a proposal, approve a proposal, or write WordPress content.', 'npcink-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Topic', 'npcink-toolbox' ); ?></span>
				<input type="text" name="topic" placeholder="<?php esc_attr_e( 'Article topic', 'npcink-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Working title', 'npcink-toolbox' ); ?></span>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Optional title override', 'npcink-toolbox' ); ?>" />
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Audience', 'npcink-toolbox' ); ?></span>
					<input type="text" name="target_audience" placeholder="<?php esc_attr_e( 'Target reader', 'npcink-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Angle', 'npcink-toolbox' ); ?></span>
					<input type="text" name="angle" placeholder="<?php esc_attr_e( 'Point of view or structure', 'npcink-toolbox' ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Language', 'npcink-toolbox' ); ?></span>
					<input type="text" name="language" value="zh-CN" />
				</label>
				<label>
					<span><?php esc_html_e( 'Target words', 'npcink-toolbox' ); ?></span>
					<input type="number" min="500" max="5000" step="50" name="target_word_count" value="1200" />
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Article goal', 'npcink-toolbox' ); ?></span>
				<textarea name="article_goal" rows="2" placeholder="<?php esc_attr_e( 'What should the article help the reader do?', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Must include', 'npcink-toolbox' ); ?></span>
					<textarea name="must_include" rows="3" placeholder="<?php esc_attr_e( 'One required point per line', 'npcink-toolbox' ); ?>"></textarea>
				</label>
				<label>
					<span><?php esc_html_e( 'Must avoid', 'npcink-toolbox' ); ?></span>
					<textarea name="must_avoid" rows="3" placeholder="<?php esc_attr_e( 'One forbidden or sensitive point per line', 'npcink-toolbox' ); ?>"></textarea>
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Reference URLs', 'npcink-toolbox' ); ?></span>
				<textarea name="reference_urls" rows="3" placeholder="<?php esc_attr_e( 'One source URL per line', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Draft notes', 'npcink-toolbox' ); ?></span>
				<textarea name="draft_notes" rows="4" placeholder="<?php esc_attr_e( 'Operator notes, facts, outline fragments, or constraints.', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Reviewed draft body', 'npcink-toolbox' ); ?></span>
				<textarea name="reviewed_draft_markdown" rows="7" placeholder="<?php esc_attr_e( 'Optional. When present and risk checks pass, Toolbox also returns an article_write_plan for Core intake.', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Source policy', 'npcink-toolbox' ); ?></span>
					<select name="source_policy">
						<option value="strict_sources"><?php esc_html_e( 'Strict sources', 'npcink-toolbox' ); ?></option>
						<option value="review_required"><?php esc_html_e( 'Review required', 'npcink-toolbox' ); ?></option>
						<option value="operator_notes_only"><?php esc_html_e( 'Operator notes only', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Tone', 'npcink-toolbox' ); ?></span>
					<input type="text" name="tone" placeholder="<?php esc_attr_e( 'Optional tone hint', 'npcink-toolbox' ); ?>" />
				</label>
			</div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Build assistant artifact', 'npcink-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_media_derivative_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		$toolbox_policy = $this->get_media_derivative_toolbox_policy();
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" data-toolbox-media-derivative <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<?php $this->render_media_derivative_toolbox_defaults( $toolbox_policy ); ?>
			<?php $this->render_media_derivative_picker_controls(); ?>
			<?php $this->render_media_derivative_format_controls( $toolbox_policy ); ?>
			<?php $this->render_media_derivative_crop_controls(); ?>
			<div class="npcink-toolbox__batch-panel">
				<h3><?php esc_html_e( 'Reviewed media details', 'npcink-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'These fields are submitted with the derivative artifact as one Core media optimization proposal.', 'npcink-toolbox' ); ?></p>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Media title', 'npcink-toolbox' ); ?></span>
						<input type="text" name="media_title" placeholder="<?php esc_attr_e( 'Reviewed media title', 'npcink-toolbox' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Alt text', 'npcink-toolbox' ); ?></span>
						<input type="text" name="media_alt" placeholder="<?php esc_attr_e( 'Describe the image for accessibility', 'npcink-toolbox' ); ?>" />
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Caption', 'npcink-toolbox' ); ?></span>
					<input type="text" name="media_caption" placeholder="<?php esc_attr_e( 'Optional reviewed caption', 'npcink-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Description', 'npcink-toolbox' ); ?></span>
					<textarea name="media_description" rows="2" placeholder="<?php esc_attr_e( 'Optional reviewed attachment description', 'npcink-toolbox' ); ?>"></textarea>
				</label>
				<label>
					<span><?php esc_html_e( 'Source type', 'npcink-toolbox' ); ?></span>
					<select name="media_source_type">
						<option value="ai_generated"><?php esc_html_e( 'AI generated', 'npcink-toolbox' ); ?></option>
						<option value="owned"><?php esc_html_e( 'Owned image', 'npcink-toolbox' ); ?></option>
						<option value="stock"><?php esc_html_e( 'Stock image', 'npcink-toolbox' ); ?></option>
						<option value="external"><?php esc_html_e( 'External image', 'npcink-toolbox' ); ?></option>
						<option value="test"><?php esc_html_e( 'Test media', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<?php $this->render_media_derivative_watermark_controls( $toolbox_policy ); ?>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Exclude formats from setting repair', 'npcink-toolbox' ); ?></span>
					<input type="text" name="settings_excluded_formats" value="svg,gif,ico,pdf" />
				</label>
				<label>
					<span><?php esc_html_e( 'Minimum setting image size', 'npcink-toolbox' ); ?></span>
					<input type="text" name="settings_min_dimensions" value="64x64" />
				</label>
			</div>
			<div class="npcink-toolbox__batch-panel">
				<h3><?php esc_html_e( 'Batch conversion plan', 'npcink-toolbox' ); ?></h3>
				<p><?php esc_html_e( 'Fixed batch flow: choose a bounded review set and goal, build a plan, generate selected previews, then submit only selected Core reviews. This is not a one-click whole-site replacement.', 'npcink-toolbox' ); ?></p>
				<ol class="npcink-toolbox__flow-steps" aria-label="<?php esc_attr_e( 'Batch optimization steps', 'npcink-toolbox' ); ?>">
					<li><?php esc_html_e( 'Scope', 'npcink-toolbox' ); ?></li>
					<li><?php esc_html_e( 'Plan', 'npcink-toolbox' ); ?></li>
					<li><?php esc_html_e( 'Preview', 'npcink-toolbox' ); ?></li>
					<li><?php esc_html_e( 'Core review', 'npcink-toolbox' ); ?></li>
				</ol>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Media range', 'npcink-toolbox' ); ?></span>
						<select name="batch_scope_preset">
							<option value="current_month"><?php esc_html_e( 'This month', 'npcink-toolbox' ); ?></option>
							<option value="previous_month"><?php esc_html_e( 'Previous month', 'npcink-toolbox' ); ?></option>
							<option value="custom"><?php esc_html_e( 'Custom range', 'npcink-toolbox' ); ?></option>
							<option value="all"><?php esc_html_e( 'Eligible media sample', 'npcink-toolbox' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Processing goal', 'npcink-toolbox' ); ?></span>
						<select name="batch_recipe">
							<option value="smart_optimize"><?php esc_html_e( 'Smart optimize', 'npcink-toolbox' ); ?></option>
							<option value="convert_format"><?php esc_html_e( 'Convert format', 'npcink-toolbox' ); ?></option>
							<option value="resize_only"><?php esc_html_e( 'Resize only', 'npcink-toolbox' ); ?></option>
							<option value="watermark"><?php esc_html_e( 'Apply watermark', 'npcink-toolbox' ); ?></option>
						</select>
					</label>
				</div>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Output format', 'npcink-toolbox' ); ?></span>
						<select name="batch_target_format">
							<?php foreach ( array( 'webp', 'avif', 'jpeg', 'png', 'original' ) as $format ) : ?>
								<option value="<?php echo esc_attr( $format ); ?>" <?php selected( 'webp', $format ); ?>><?php echo esc_html( strtoupper( $format ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Review set size', 'npcink-toolbox' ); ?></span>
						<input type="number" min="1" max="25" step="1" name="batch_max_items" value="10" />
					</label>
				</div>
				<details class="npcink-toolbox__result-details npcink-toolbox__advanced-filters">
					<summary><?php esc_html_e( 'Advanced filters', 'npcink-toolbox' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Use these only when the fixed review set needs a tighter date, format, or dimension filter. The plan remains bounded by review set size.', 'npcink-toolbox' ); ?></p>
					<div class="npcink-toolbox__split">
						<label>
							<span><?php esc_html_e( 'Date from', 'npcink-toolbox' ); ?></span>
							<input type="date" name="batch_date_from" />
						</label>
						<label>
							<span><?php esc_html_e( 'Date to', 'npcink-toolbox' ); ?></span>
							<input type="date" name="batch_date_to" />
						</label>
					</div>
					<div class="npcink-toolbox__split">
						<label>
							<span><?php esc_html_e( 'Exclude formats', 'npcink-toolbox' ); ?></span>
							<input type="text" name="batch_exclude_formats" value="webp,gif,svg" />
						</label>
						<label>
							<span><?php esc_html_e( 'Min dimensions', 'npcink-toolbox' ); ?></span>
							<input type="text" name="batch_min_dimensions" value="800x800" />
						</label>
					</div>
				</details>
				<div class="npcink-toolbox__inline-actions">
					<button type="button" class="button" data-toolbox-build-media-batch-plan><?php esc_html_e( 'Build review plan', 'npcink-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-run-media-batch-previews disabled><?php esc_html_e( 'Generate selected previews', 'npcink-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-submit-media-batch-proposals disabled><?php esc_html_e( 'Submit selected Core reviews', 'npcink-toolbox' ); ?></button>
					<button type="button" class="button button-primary" data-toolbox-execute-media-batch-replacements disabled><?php esc_html_e( 'Approve and execute replacements', 'npcink-toolbox' ); ?></button>
				</div>
				<div class="npcink-toolbox__batch-plan" data-toolbox-media-batch-plan hidden></div>
			</div>
			<p class="description"><?php esc_html_e( 'Cloud returns a short-lived derivative artifact. Toolbox owns media optimization defaults and reviewed handoff fields; Core creates one proposal for the metadata update and derivative adoption together.', 'npcink-toolbox' ); ?></p>
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Operator flow', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Generate preview first, review the derivative and adoption preflight, then submit the Core optimization review only when the result is acceptable.', 'npcink-toolbox' ); ?></span>
			</div>
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Replacement boundary', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Adoption changes the media attachment through Core approval. Hard-coded post URLs and URLs stored in settings, themes, or other plugin options require the separate repair actions.', 'npcink-toolbox' ); ?></span>
			</div>
			<p class="description"><?php esc_html_e( 'If old image URLs are stored in theme settings or other plugin options, run the settings URL repair action after preview; the derivative adoption proposal does not scan settings automatically.', 'npcink-toolbox' ); ?></p>
			<div class="npcink-toolbox__inline-actions">
				<button type="button" class="button button-primary" data-toolbox-run-media-derivative><?php esc_html_e( 'Generate preview', 'npcink-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-submit-media-proposal disabled><?php esc_html_e( 'Submit optimization review', 'npcink-toolbox' ); ?></button>
			</div>
			<details class="npcink-toolbox__result-details">
				<summary><?php esc_html_e( 'Repair and handoff actions', 'npcink-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Use these after preview when old URLs appear in post content, settings, themes, or when another client needs the raw handoff.', 'npcink-toolbox' ); ?></p>
				<div class="npcink-toolbox__inline-actions">
					<button type="button" class="button" data-toolbox-submit-reference-repair disabled><?php esc_html_e( 'Submit content URL repair', 'npcink-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-submit-settings-repair disabled><?php esc_html_e( 'Submit settings URL repair', 'npcink-toolbox' ); ?></button>
					<button type="submit" class="button"><?php esc_html_e( 'Build handoff only', 'npcink-toolbox' ); ?></button>
				</div>
			</details>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<div data-toolbox-tool-panel-extra="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<?php $this->render_media_derivative_policy_settings( $toolbox_policy ); ?>
		</div>
		<?php
	}

	private function render_article_plan_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Handoff', 'npcink-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'Review the returned artifact, then send it to Core through Adapter or Core from-plan intake. Final execution remains npcink-abilities-toolkit/create-draft after Core approval.', 'npcink-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Reviewed draft title', 'npcink-toolbox' ); ?></span>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Working article title', 'npcink-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Reviewed draft body', 'npcink-toolbox' ); ?></span>
				<textarea name="content_markdown" rows="8" placeholder="<?php esc_attr_e( 'Paste the reviewed draft body. This creates a plan only, not a post.', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Topic', 'npcink-toolbox' ); ?></span>
					<input type="text" name="topic" placeholder="<?php esc_attr_e( 'Optional topic label', 'npcink-toolbox' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Risk level', 'npcink-toolbox' ); ?></span>
					<select name="risk_level">
						<option value="low"><?php esc_html_e( 'Low', 'npcink-toolbox' ); ?></option>
						<option value="medium"><?php esc_html_e( 'Medium', 'npcink-toolbox' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'npcink-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'SEO title', 'npcink-toolbox' ); ?></span>
				<input type="text" name="seo_title" placeholder="<?php esc_attr_e( 'Optional proposal SEO title', 'npcink-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'SEO description', 'npcink-toolbox' ); ?></span>
				<textarea name="seo_description" rows="2" placeholder="<?php esc_attr_e( 'Optional proposal SEO description', 'npcink-toolbox' ); ?>"></textarea>
			</label>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Build plan', 'npcink-toolbox' ); ?></button>
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
