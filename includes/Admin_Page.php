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

		wp_add_inline_script(
			'magick-ai-toolbox-admin',
			'window.MagickAIToolbox = ' . wp_json_encode(
				array(
					'restUrl'       => esc_url_raw( rest_url( Plugin::REST_NAMESPACE ) ),
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'contextOption' => Plugin::CONTEXT_OPTION_NAME,
					'contextDrafts' => array(
						'aiBlog' => $this->get_ai_blog_context_template(),
						'site'   => $this->get_site_content_context_suggestion(),
					),
					'labels'        => array(
						'running' => __( 'Running...', 'magick-ai-toolbox' ),
						'error'   => __( 'Request failed.', 'magick-ai-toolbox' ),
					),
				)
			) . ';',
			'before'
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
			'entity_keywords'                   => array( 'OpenAI', 'WordPress', 'Qdrant', 'Tavily', 'Unsplash', 'Jina AI', 'SiliconFlow', 'REST API', 'WordPress Abilities API', 'Magick AI' ),
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
				__( 'Do not describe Unsplash image search as AI image generation.', 'magick-ai-toolbox' ),
				__( 'Do not describe vector search as a complete knowledge base or automatic indexing system.', 'magick-ai-toolbox' ),
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
			<p class="magick-ai-toolbox__scope"><?php esc_html_e( 'Generate research, image-source candidates, vector matches, and planning handoffs. Final WordPress writes still require Core proposal approval.', 'magick-ai-toolbox' ); ?></p>

			<?php $this->render_status_strip( $settings, $content_context ); ?>

			<nav class="magick-ai-toolbox__tabs" data-toolbox-tabs aria-label="<?php esc_attr_e( 'Toolbox sections', 'magick-ai-toolbox' ); ?>">
				<button type="button" class="magick-ai-toolbox__tab is-active" data-toolbox-tab-target="context" aria-selected="true"><?php esc_html_e( 'Content Context', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="magick-ai-toolbox__tab" data-toolbox-tab-target="tools" aria-selected="false"><?php esc_html_e( 'Try Tools', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="magick-ai-toolbox__tab" data-toolbox-tab-target="connectors" aria-selected="false"><?php esc_html_e( 'Connectors', 'magick-ai-toolbox' ); ?></button>
			</nav>

			<section class="magick-ai-toolbox__panel" data-toolbox-tab-panel="context" aria-label="<?php esc_attr_e( 'Content context', 'magick-ai-toolbox' ); ?>">
				<?php $this->render_content_context_form( $content_context ); ?>
			</section>

			<section class="magick-ai-toolbox__panel" data-toolbox-tab-panel="tools" aria-label="<?php esc_attr_e( 'Try Toolbox actions', 'magick-ai-toolbox' ); ?>" hidden>
				<?php $this->render_tool_cards(); ?>
			</section>

			<section class="magick-ai-toolbox__panel" data-toolbox-tab-panel="connectors" aria-label="<?php esc_attr_e( 'Connector settings', 'magick-ai-toolbox' ); ?>" hidden>
				<?php $this->render_connector_settings_form( $settings ); ?>
			</section>
		</div>
		<?php
	}

	private function render_status_strip( array $settings, array $context ): void {
		$embedding_provider = (string) $settings['embedding_provider'];
		$embedding_ready    = 'jina' === $embedding_provider ? $this->settings->has_jina_api_key() : $this->settings->has_siliconflow_api_key();
		$vector_ready       = ! empty( $settings['enable_vector_search'] ) && $this->settings->has_qdrant_connection() && $embedding_ready;
		$context_count      = $this->count_content_context_fields( $context );
		?>
		<div class="magick-ai-toolbox__status-strip" aria-label="<?php esc_attr_e( 'Toolbox status', 'magick-ai-toolbox' ); ?>">
			<?php $this->render_status_pill( __( 'Tavily', 'magick-ai-toolbox' ), $this->settings->has_tavily_api_key() && ! empty( $settings['enable_web_research'] ) ? 'ok' : 'warning', $this->settings->has_tavily_api_key() ? __( 'Configured', 'magick-ai-toolbox' ) : __( 'Missing key', 'magick-ai-toolbox' ) ); ?>
			<?php $this->render_status_pill( __( 'Unsplash', 'magick-ai-toolbox' ), $this->settings->has_unsplash_access_key() && ! empty( $settings['enable_image_source'] ) ? 'ok' : 'warning', $this->settings->has_unsplash_access_key() ? __( 'Configured', 'magick-ai-toolbox' ) : __( 'Missing key', 'magick-ai-toolbox' ) ); ?>
			<?php $this->render_status_pill( __( 'Vector', 'magick-ai-toolbox' ), $vector_ready ? 'ok' : 'warning', $vector_ready ? __( 'Ready', 'magick-ai-toolbox' ) : __( 'Needs Qdrant and embedding', 'magick-ai-toolbox' ) ); ?>
			<?php $this->render_status_pill( __( 'Context', 'magick-ai-toolbox' ), $context_count > 0 ? 'ok' : 'inactive', sprintf( /* translators: %d: number of filled content context fields. */ _n( '%d field filled', '%d fields filled', $context_count, 'magick-ai-toolbox' ), $context_count ) ); ?>
		</div>
		<?php
	}

	private function render_status_pill( string $label, string $status, string $detail ): void {
		?>
		<div class="magick-ai-toolbox__status-pill is-<?php echo esc_attr( $status ); ?>">
			<span class="magick-ai-toolbox__status-label"><?php echo esc_html( $label ); ?></span>
			<span class="magick-ai-toolbox__status-detail"><?php echo esc_html( $detail ); ?></span>
		</div>
		<?php
	}

	private function count_content_context_fields( array $context ): int {
		$counted_keys = array(
			'site_positioning',
			'target_audience',
			'brand_voice',
			'primary_keywords',
			'long_tail_keywords',
			'entity_keywords',
			'allowed_claims',
			'forbidden_claims',
			'seo_rules',
			'aeo_rules',
			'geo_rules',
		);
		$count        = 0;

		foreach ( $counted_keys as $key ) {
			$value = $context[ $key ] ?? '';
			if ( is_array( $value ) ? ! empty( $value ) : '' !== trim( (string) $value ) ) {
				++$count;
			}
		}

		return $count;
	}

	private function render_connector_settings_form( array $settings ): void {
		$embedding_provider = (string) $settings['embedding_provider'];
		$embedding_ready    = 'jina' === $embedding_provider ? $this->settings->has_jina_api_key() : $this->settings->has_siliconflow_api_key();
		$vector_ready       = $this->settings->has_qdrant_connection() && $embedding_ready;
		?>
		<div class="magick-ai-toolbox__panel-header">
			<h2><?php esc_html_e( 'Connectors', 'magick-ai-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Configure provider credentials by capability. Secrets are stored separately from content context and are never exposed through abilities.', 'magick-ai-toolbox' ); ?></p>
		</div>

		<form class="magick-ai-toolbox__settings-form" method="post" action="options.php">
			<?php settings_fields( 'magick_ai_toolbox' ); ?>

			<div class="magick-ai-toolbox__tool-workspace magick-ai-toolbox__connector-workspace" data-toolbox-connectors>
				<div class="magick-ai-toolbox__tool-list" aria-label="<?php esc_attr_e( 'Connector groups', 'magick-ai-toolbox' ); ?>">
					<button type="button" class="magick-ai-toolbox__tool-button is-active" data-toolbox-connector-target="search" aria-selected="true">
						<span><?php esc_html_e( 'Search', 'magick-ai-toolbox' ); ?></span>
						<small><?php echo esc_html( $this->settings->has_tavily_api_key() ? __( 'Tavily configured', 'magick-ai-toolbox' ) : __( 'Tavily key needed', 'magick-ai-toolbox' ) ); ?></small>
					</button>
					<button type="button" class="magick-ai-toolbox__tool-button" data-toolbox-connector-target="image" aria-selected="false">
						<span><?php esc_html_e( 'Image', 'magick-ai-toolbox' ); ?></span>
						<small><?php echo esc_html( $this->settings->has_unsplash_access_key() ? __( 'Unsplash configured', 'magick-ai-toolbox' ) : __( 'Unsplash key needed', 'magick-ai-toolbox' ) ); ?></small>
					</button>
					<button type="button" class="magick-ai-toolbox__tool-button" data-toolbox-connector-target="vector" aria-selected="false">
						<span><?php esc_html_e( 'Vector', 'magick-ai-toolbox' ); ?></span>
						<small><?php echo esc_html( $vector_ready ? __( 'Qdrant and embedding ready', 'magick-ai-toolbox' ) : __( 'Needs Qdrant and embedding', 'magick-ai-toolbox' ) ); ?></small>
					</button>
				</div>

				<div class="magick-ai-toolbox__connector-panels">
					<section class="magick-ai-toolbox__card" data-toolbox-connector-panel="search">
						<h2><?php esc_html_e( 'Search', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Tavily powers external web research suggestions. Results are source candidates for operator review, not verified truth.', 'magick-ai-toolbox' ); ?></p>

						<?php
						$this->render_connector_status_catalog(
							array(
								array(
									'label'  => __( 'Tavily', 'magick-ai-toolbox' ),
									'state'  => $this->settings->has_tavily_api_key() ? 'ok' : 'warning',
									'status' => $this->settings->has_tavily_api_key() ? __( 'Configured', 'magick-ai-toolbox' ) : __( 'Missing key', 'magick-ai-toolbox' ),
									'owner'  => __( 'Local MVP config', 'magick-ai-toolbox' ),
									'note'   => __( 'Server-side search connector; provider secrets are not exposed through REST or abilities.', 'magick-ai-toolbox' ),
								),
								array(
									'label'  => __( 'Additional search providers', 'magick-ai-toolbox' ),
									'state'  => 'inactive',
									'status' => __( 'Reserved', 'magick-ai-toolbox' ),
									'owner'  => __( 'Future connector owner', 'magick-ai-toolbox' ),
									'note'   => __( 'New search providers need a later contract before runtime support.', 'magick-ai-toolbox' ),
								),
							)
						);
						?>

						<label>
							<span><?php esc_html_e( 'Tavily API key', 'magick-ai-toolbox' ); ?></span>
							<input type="password" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[tavily_api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $this->settings->has_tavily_api_key() ? __( 'Stored or provided by environment', 'magick-ai-toolbox' ) : __( 'tvly-...', 'magick-ai-toolbox' ) ); ?>" />
						</label>

						<label>
							<span><?php esc_html_e( 'Tavily search depth', 'magick-ai-toolbox' ); ?></span>
							<select name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[tavily_search_depth]">
								<option value="basic" <?php selected( (string) $settings['tavily_search_depth'], 'basic' ); ?>><?php esc_html_e( 'Basic', 'magick-ai-toolbox' ); ?></option>
								<option value="advanced" <?php selected( (string) $settings['tavily_search_depth'], 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'magick-ai-toolbox' ); ?></option>
							</select>
						</label>

						<?php $this->render_checkbox( 'tavily_include_answer', __( 'Tavily answer summary', 'magick-ai-toolbox' ), $settings ); ?>
						<?php $this->render_checkbox( 'tavily_include_raw', __( 'Tavily raw content', 'magick-ai-toolbox' ), $settings ); ?>
						<?php $this->render_checkbox( 'tavily_include_images', __( 'Tavily image URLs', 'magick-ai-toolbox' ), $settings ); ?>
					</section>

					<section class="magick-ai-toolbox__card" data-toolbox-connector-panel="image" hidden>
						<h2><?php esc_html_e( 'Image', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Unsplash returns image-source candidates with attribution and download tracking metadata. This is not AI image generation.', 'magick-ai-toolbox' ); ?></p>

						<?php
						$this->render_connector_status_catalog(
							array(
								array(
									'label'  => __( 'Unsplash', 'magick-ai-toolbox' ),
									'state'  => $this->settings->has_unsplash_access_key() ? 'ok' : 'warning',
									'status' => $this->settings->has_unsplash_access_key() ? __( 'Configured', 'magick-ai-toolbox' ) : __( 'Missing key', 'magick-ai-toolbox' ),
									'owner'  => __( 'Local MVP config', 'magick-ai-toolbox' ),
									'note'   => __( 'Image-source candidates preserve attribution and download tracking metadata.', 'magick-ai-toolbox' ),
								),
								array(
									'label'  => __( 'Pixabay / Pexels', 'magick-ai-toolbox' ),
									'state'  => 'inactive',
									'status' => __( 'Reserved', 'magick-ai-toolbox' ),
									'owner'  => __( 'Future connector owner', 'magick-ai-toolbox' ),
									'note'   => __( 'Reserved as public image-source connectors, not AI image generation providers.', 'magick-ai-toolbox' ),
								),
							)
						);
						?>

						<label>
							<span><?php esc_html_e( 'Unsplash access key', 'magick-ai-toolbox' ); ?></span>
							<input type="password" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[unsplash_access_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $this->settings->has_unsplash_access_key() ? __( 'Stored or provided by environment', 'magick-ai-toolbox' ) : __( 'Unsplash access key', 'magick-ai-toolbox' ) ); ?>" />
						</label>

						<label>
							<span><?php esc_html_e( 'Unsplash app name', 'magick-ai-toolbox' ); ?></span>
							<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[unsplash_utm_source]" value="<?php echo esc_attr( (string) $settings['unsplash_utm_source'] ); ?>" />
						</label>
					</section>

					<section class="magick-ai-toolbox__card" data-toolbox-connector-panel="vector" hidden>
						<h2><?php esc_html_e( 'Vector', 'magick-ai-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Qdrant stores and queries vectors. SiliconFlow or Jina AI creates a synchronous query embedding for vector search; Toolbox does not own indexing or collection lifecycle.', 'magick-ai-toolbox' ); ?></p>

						<?php
						$this->render_connector_status_catalog(
							array(
								array(
									'label'  => __( 'Qdrant', 'magick-ai-toolbox' ),
									'state'  => $this->settings->has_qdrant_connection() ? 'ok' : 'warning',
									'status' => $this->settings->has_qdrant_connection() ? __( 'Endpoint and collection selected', 'magick-ai-toolbox' ) : __( 'Needs endpoint and collection', 'magick-ai-toolbox' ),
									'owner'  => __( 'Local MVP config', 'magick-ai-toolbox' ),
									'note'   => __( 'Queries an existing collection only; indexing and collection lifecycle stay out of Toolbox.', 'magick-ai-toolbox' ),
								),
								array(
									'label'  => __( 'SiliconFlow / Jina AI', 'magick-ai-toolbox' ),
									'state'  => $embedding_ready ? 'ok' : 'warning',
									'status' => $embedding_ready ? __( 'Selected embedding ready', 'magick-ai-toolbox' ) : __( 'Selected embedding key needed', 'magick-ai-toolbox' ),
									'owner'  => __( 'Local MVP config', 'magick-ai-toolbox' ),
									'note'   => __( 'Creates one synchronous query embedding for vector search; it does not index WordPress content.', 'magick-ai-toolbox' ),
								),
								array(
									'label'  => __( 'Pinecone / Weaviate', 'magick-ai-toolbox' ),
									'state'  => 'inactive',
									'status' => __( 'Reserved', 'magick-ai-toolbox' ),
									'owner'  => __( 'Future connector owner', 'magick-ai-toolbox' ),
									'note'   => __( 'Reserved vector database slots; runtime support needs a later contract.', 'magick-ai-toolbox' ),
								),
							)
						);
						?>

						<div class="magick-ai-toolbox__example">
							<strong><?php esc_html_e( 'Jina test setup', 'magick-ai-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Choose Jina AI as the embedding provider, use https://api.jina.ai/v1 as the base URL, keep jina-embeddings-v3 for the model, then fill Qdrant endpoint and collection before trying Vector Search.', 'magick-ai-toolbox' ); ?></span>
						</div>

						<label>
							<span><?php esc_html_e( 'Qdrant endpoint', 'magick-ai-toolbox' ); ?></span>
							<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[qdrant_endpoint]" value="<?php echo esc_attr( (string) $settings['qdrant_endpoint'] ); ?>" placeholder="https://example.qdrant.io" />
						</label>

						<label>
							<span><?php esc_html_e( 'Qdrant collection', 'magick-ai-toolbox' ); ?></span>
							<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[qdrant_collection]" value="<?php echo esc_attr( (string) $settings['qdrant_collection'] ); ?>" />
						</label>

						<label>
							<span><?php esc_html_e( 'Qdrant vector name', 'magick-ai-toolbox' ); ?></span>
							<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[qdrant_vector_name]" value="<?php echo esc_attr( (string) $settings['qdrant_vector_name'] ); ?>" />
						</label>

						<label>
							<span><?php esc_html_e( 'Qdrant API key', 'magick-ai-toolbox' ); ?></span>
							<input type="password" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[qdrant_api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== $this->settings->get_qdrant_api_key() ? __( 'Stored or provided by environment', 'magick-ai-toolbox' ) : __( 'Optional for local Qdrant', 'magick-ai-toolbox' ) ); ?>" />
						</label>

						<label>
							<span><?php esc_html_e( 'Embedding provider', 'magick-ai-toolbox' ); ?></span>
							<select name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[embedding_provider]">
								<option value="siliconflow" <?php selected( (string) $settings['embedding_provider'], 'siliconflow' ); ?>><?php esc_html_e( 'SiliconFlow', 'magick-ai-toolbox' ); ?></option>
								<option value="jina" <?php selected( (string) $settings['embedding_provider'], 'jina' ); ?>><?php esc_html_e( 'Jina AI', 'magick-ai-toolbox' ); ?></option>
							</select>
						</label>

						<label>
							<span><?php esc_html_e( 'Embedding dimensions', 'magick-ai-toolbox' ); ?></span>
							<input type="number" min="1" max="4096" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[embedding_dimensions]" value="<?php echo esc_attr( (string) $settings['embedding_dimensions'] ); ?>" />
						</label>

						<div class="magick-ai-toolbox__split">
							<div>
								<h3><?php esc_html_e( 'SiliconFlow', 'magick-ai-toolbox' ); ?></h3>
								<label>
									<span><?php esc_html_e( 'SiliconFlow API key', 'magick-ai-toolbox' ); ?></span>
									<input type="password" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[siliconflow_api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $this->settings->has_siliconflow_api_key() ? __( 'Stored or provided by environment', 'magick-ai-toolbox' ) : __( 'SiliconFlow API key', 'magick-ai-toolbox' ) ); ?>" />
								</label>
								<label>
									<span><?php esc_html_e( 'SiliconFlow base URL', 'magick-ai-toolbox' ); ?></span>
									<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[siliconflow_base_url]" value="<?php echo esc_attr( (string) $settings['siliconflow_base_url'] ); ?>" placeholder="https://api.siliconflow.com/v1" />
								</label>
								<label>
									<span><?php esc_html_e( 'SiliconFlow embedding model', 'magick-ai-toolbox' ); ?></span>
									<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[siliconflow_model]" value="<?php echo esc_attr( (string) $settings['siliconflow_model'] ); ?>" placeholder="BAAI/bge-m3" />
								</label>
							</div>

							<div>
								<h3><?php esc_html_e( 'Jina AI', 'magick-ai-toolbox' ); ?></h3>
								<label>
									<span><?php esc_html_e( 'Jina AI API key', 'magick-ai-toolbox' ); ?></span>
									<input type="password" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[jina_api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $this->settings->has_jina_api_key() ? __( 'Stored or provided by environment', 'magick-ai-toolbox' ) : __( 'Jina AI API key', 'magick-ai-toolbox' ) ); ?>" />
								</label>
								<label>
									<span><?php esc_html_e( 'Jina AI base URL', 'magick-ai-toolbox' ); ?></span>
									<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[jina_base_url]" value="<?php echo esc_attr( (string) $settings['jina_base_url'] ); ?>" placeholder="https://api.jina.ai/v1" />
								</label>
								<label>
									<span><?php esc_html_e( 'Jina AI embedding model', 'magick-ai-toolbox' ); ?></span>
									<input type="text" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[jina_model]" value="<?php echo esc_attr( (string) $settings['jina_model'] ); ?>" placeholder="jina-embeddings-v3" />
								</label>
							</div>
						</div>
					</section>
				</div>
			</div>

			<details class="magick-ai-toolbox__disclosure">
				<summary>
					<span><?php esc_html_e( 'Advanced / Debug', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Feature toggles, raw response output, and key clearing', 'magick-ai-toolbox' ); ?></small>
				</summary>
				<div class="magick-ai-toolbox__disclosure-body">
					<?php $this->render_checkbox( 'enable_web_research', __( 'Web research', 'magick-ai-toolbox' ), $settings ); ?>
					<?php $this->render_checkbox( 'enable_image_source', __( 'Image source search', 'magick-ai-toolbox' ), $settings ); ?>
					<?php $this->render_checkbox( 'enable_vector_search', __( 'Vector search', 'magick-ai-toolbox' ), $settings ); ?>
					<?php $this->render_checkbox( 'include_raw_responses', __( 'Include provider raw responses', 'magick-ai-toolbox' ), $settings ); ?>
					<label class="magick-ai-toolbox__check">
						<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[clear_tavily_api_key]" value="1" />
						<span><?php esc_html_e( 'Clear stored Tavily key', 'magick-ai-toolbox' ); ?></span>
					</label>
					<label class="magick-ai-toolbox__check">
						<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[clear_unsplash_access_key]" value="1" />
						<span><?php esc_html_e( 'Clear stored Unsplash key', 'magick-ai-toolbox' ); ?></span>
					</label>
					<label class="magick-ai-toolbox__check">
						<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[clear_qdrant_api_key]" value="1" />
						<span><?php esc_html_e( 'Clear stored Qdrant key', 'magick-ai-toolbox' ); ?></span>
					</label>
					<label class="magick-ai-toolbox__check">
						<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[clear_siliconflow_api_key]" value="1" />
						<span><?php esc_html_e( 'Clear stored SiliconFlow key', 'magick-ai-toolbox' ); ?></span>
					</label>
					<label class="magick-ai-toolbox__check">
						<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[clear_jina_api_key]" value="1" />
						<span><?php esc_html_e( 'Clear stored Jina AI key', 'magick-ai-toolbox' ); ?></span>
					</label>
				</div>
			</details>

			<?php submit_button( __( 'Save settings', 'magick-ai-toolbox' ) ); ?>
		</form>
		<?php
	}

	private function render_connector_status_catalog( array $rows ): void {
		?>
		<dl class="magick-ai-toolbox__connector-status" aria-label="<?php esc_attr_e( 'Connector status catalog', 'magick-ai-toolbox' ); ?>">
			<?php foreach ( $rows as $row ) : ?>
				<div class="magick-ai-toolbox__connector-status-row is-<?php echo esc_attr( (string) $row['state'] ); ?>">
					<dt>
						<span><?php echo esc_html( (string) $row['label'] ); ?></span>
						<small><?php echo esc_html( (string) $row['owner'] ); ?></small>
					</dt>
					<dd>
						<strong><?php echo esc_html( (string) $row['status'] ); ?></strong>
						<span><?php echo esc_html( (string) $row['note'] ); ?></span>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
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
			<p><?php esc_html_e( 'Fill a compact site brief, then tune SEO, AEO, and GEO guidance. Draft buttons only prefill this form; nothing is saved until you click Save content context. After saving, use Try Tools to test generated briefs and search outputs.', 'magick-ai-toolbox' ); ?></p>
		</div>

		<form class="magick-ai-toolbox__settings-form" method="post" action="options.php" data-toolbox-context-form>
			<?php settings_fields( 'magick_ai_toolbox_content_context' ); ?>

			<div class="magick-ai-toolbox__draft-actions" aria-label="<?php esc_attr_e( 'Content context draft actions', 'magick-ai-toolbox' ); ?>">
				<button type="button" class="button" data-toolbox-context-draft="aiBlog"><?php esc_html_e( 'Use AI tech blog template', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-draft="site"><?php esc_html_e( 'Draft from current site content', 'magick-ai-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-clear><?php esc_html_e( 'Clear form', 'magick-ai-toolbox' ); ?></button>
				<span><?php esc_html_e( 'Drafts are editable suggestions and do not change posts, media, SEO meta, or provider settings.', 'magick-ai-toolbox' ); ?></span>
			</div>

			<details class="magick-ai-toolbox__disclosure" open>
				<summary>
					<span><?php esc_html_e( 'Basic brief', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Site type, audience, and voice', 'magick-ai-toolbox' ); ?></small>
				</summary>
				<div class="magick-ai-toolbox__disclosure-body">
					<div class="magick-ai-toolbox__example">
						<strong><?php esc_html_e( 'Example', 'magick-ai-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'A practical AI technology blog for developers and product teams, written in a clear and restrained engineering voice.', 'magick-ai-toolbox' ); ?></span>
					</div>
					<?php $this->render_context_textarea( 'site_positioning', __( 'Site positioning', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_list_field( 'target_audience', __( 'Target audience', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_textarea( 'brand_voice', __( 'Brand voice', 'magick-ai-toolbox' ), $context ); ?>
				</div>
			</details>

			<details class="magick-ai-toolbox__disclosure" open>
				<summary>
					<span><?php esc_html_e( 'SEO', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Keywords, titles, descriptions, and internal links', 'magick-ai-toolbox' ); ?></small>
				</summary>
				<div class="magick-ai-toolbox__disclosure-body">
					<div class="magick-ai-toolbox__example">
						<strong><?php esc_html_e( 'Example', 'magick-ai-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Use clear topic keywords, avoid clickbait, and explain the problem, audience, and conclusion in the excerpt.', 'magick-ai-toolbox' ); ?></span>
					</div>
					<?php $this->render_context_list_field( 'primary_keywords', __( 'Primary keywords', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_list_field( 'long_tail_keywords', __( 'Long-tail keywords', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_textarea( 'seo_rules', __( 'SEO rules', 'magick-ai-toolbox' ), $context ); ?>
					<fieldset class="magick-ai-toolbox__check-grid">
						<legend><?php esc_html_e( 'SEO fields AI may suggest', 'magick-ai-toolbox' ); ?></legend>
						<?php foreach ( array( 'seo_title', 'seo_description', 'slug', 'excerpt' ) as $field ) : ?>
							<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
						<?php endforeach; ?>
					</fieldset>
				</div>
			</details>

			<details class="magick-ai-toolbox__disclosure">
				<summary>
					<span><?php esc_html_e( 'AEO', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Direct answers, FAQs, definitions, and steps', 'magick-ai-toolbox' ); ?></small>
				</summary>
				<div class="magick-ai-toolbox__disclosure-body">
					<div class="magick-ai-toolbox__example">
						<strong><?php esc_html_e( 'Example', 'magick-ai-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Start with a direct answer, then add conditions, steps, limits, and short FAQ-style followups.', 'magick-ai-toolbox' ); ?></span>
					</div>
					<?php $this->render_context_textarea( 'aeo_rules', __( 'AEO rules', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_checkbox( 'allow_faq_generation', __( 'Allow FAQ suggestions', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_checkbox( 'allow_aeo_summary', __( 'Allow AEO answer summary suggestions', 'magick-ai-toolbox' ), $context ); ?>
					<fieldset class="magick-ai-toolbox__check-grid">
						<legend><?php esc_html_e( 'AEO fields AI may suggest', 'magick-ai-toolbox' ); ?></legend>
						<?php foreach ( array( 'faq', 'answer_summary' ) as $field ) : ?>
							<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
						<?php endforeach; ?>
					</fieldset>
				</div>
			</details>

			<details class="magick-ai-toolbox__disclosure">
				<summary>
					<span><?php esc_html_e( 'GEO', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Entity signals, AI summaries, and citation-friendly boundaries', 'magick-ai-toolbox' ); ?></small>
				</summary>
				<div class="magick-ai-toolbox__disclosure-body">
					<div class="magick-ai-toolbox__example">
						<strong><?php esc_html_e( 'Example', 'magick-ai-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Keep key conclusions standalone, define important entities, and distinguish implemented features from plans.', 'magick-ai-toolbox' ); ?></span>
					</div>
					<?php $this->render_context_list_field( 'entity_keywords', __( 'Entity keywords', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_textarea( 'geo_rules', __( 'GEO rules', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_checkbox( 'allow_geo_summary', __( 'Allow GEO summary suggestions', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_checkbox( 'allow_structured_data_suggestions', __( 'Allow structured data suggestions', 'magick-ai-toolbox' ), $context ); ?>
					<fieldset class="magick-ai-toolbox__check-grid">
						<legend><?php esc_html_e( 'GEO fields AI may suggest', 'magick-ai-toolbox' ); ?></legend>
						<?php foreach ( array( 'geo_summary', 'structured_data_hints' ) as $field ) : ?>
							<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
						<?php endforeach; ?>
					</fieldset>
				</div>
			</details>

			<details class="magick-ai-toolbox__disclosure">
				<summary>
					<span><?php esc_html_e( 'Advanced boundaries', 'magick-ai-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Allowed claims and forbidden claims', 'magick-ai-toolbox' ); ?></small>
				</summary>
				<div class="magick-ai-toolbox__disclosure-body">
					<?php $this->render_context_list_field( 'allowed_claims', __( 'Allowed claims', 'magick-ai-toolbox' ), $context ); ?>
					<?php $this->render_context_list_field( 'forbidden_claims', __( 'Forbidden claims', 'magick-ai-toolbox' ), $context ); ?>
				</div>
			</details>

			<p class="description"><?php esc_html_e( 'Final WordPress writes still require Core proposal approval; third-party AI receives this context as suggestion-only guidance.', 'magick-ai-toolbox' ); ?></p>
			<?php submit_button( __( 'Save content context', 'magick-ai-toolbox' ) ); ?>
		</form>

		<details class="magick-ai-toolbox__disclosure magick-ai-toolbox__preview">
			<summary>
				<span><?php esc_html_e( 'Ability preview', 'magick-ai-toolbox' ); ?></span>
				<small><?php esc_html_e( 'Read-only payload exposed to callers', 'magick-ai-toolbox' ); ?></small>
			</summary>
			<pre class="magick-ai-toolbox__result"><?php echo esc_html( (string) $preview ); ?></pre>
		</details>
		<?php
	}

	private function render_tool_cards(): void {
		$tools = array(
			array(
				'id'          => 'article-brief',
				'endpoint'    => 'flows/article-brief',
				'title'       => __( 'Article Brief', 'magick-ai-toolbox' ),
				'description' => __( 'Build a research-backed outline, source notes, image prompt, and governance handoff.', 'magick-ai-toolbox' ),
				'field'       => 'topic',
				'placeholder' => __( 'Article topic', 'magick-ai-toolbox' ),
				'button'      => __( 'Build brief', 'magick-ai-toolbox' ),
			),
			array(
				'id'          => 'article-assistant',
				'endpoint'    => 'flows/article-assistant',
				'title'       => __( 'Article Assistant', 'magick-ai-toolbox' ),
				'description' => __( 'Compose one local article_draft_v1 workbench from abilities, evidence, context, notes, and an optional reviewed draft.', 'magick-ai-toolbox' ),
				'custom'      => 'article_assistant',
			),
			array(
				'id'          => 'article-plan',
				'endpoint'    => 'flows/article-plan',
				'title'       => __( 'Article Write Plan', 'magick-ai-toolbox' ),
				'description' => __( 'Prepare a Core-ready article_write_plan for one reviewed draft. Toolbox does not submit or approve the proposal.', 'magick-ai-toolbox' ),
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
				'title'       => __( 'Media Derivative Handoff', 'magick-ai-toolbox' ),
				'description' => __( 'Prepare a one-run optimized derivative handoff from Core media policy. This does not call Cloud, create a proposal, or write media.', 'magick-ai-toolbox' ),
				'custom'      => 'media_derivative',
			),
			array(
				'id'           => 'web-research',
				'endpoint'     => 'web-research',
				'title'        => __( 'Web Research', 'magick-ai-toolbox' ),
				'description'  => __( 'Search Tavily and return source-aware research notes.', 'magick-ai-toolbox' ),
				'field'        => 'query',
				'placeholder'  => __( 'What should be researched?', 'magick-ai-toolbox' ),
				'button'       => __( 'Research', 'magick-ai-toolbox' ),
				'extra_fields' => array(
					array(
						'name'        => 'include_domains',
						'label'       => __( 'Include domains', 'magick-ai-toolbox' ),
						'placeholder' => 'example.com, wordpress.org',
					),
					array(
						'name'        => 'exclude_domains',
						'label'       => __( 'Exclude domains', 'magick-ai-toolbox' ),
						'placeholder' => 'example.com',
					),
				),
			),
			array(
				'id'           => 'image-candidates',
				'endpoint'     => 'image-candidates',
				'title'        => __( 'Image Source Candidates', 'magick-ai-toolbox' ),
				'description'  => __( 'Search image-source candidates and preserve attribution metadata.', 'magick-ai-toolbox' ),
				'field'        => 'query',
				'placeholder'  => __( 'Image search query', 'magick-ai-toolbox' ),
				'button'       => __( 'Search images', 'magick-ai-toolbox' ),
				'extra_fields' => array(
					array(
						'name'        => 'orientation',
						'label'       => __( 'Orientation', 'magick-ai-toolbox' ),
						'placeholder' => 'landscape',
					),
				),
			),
			array(
				'id'           => 'vector-search',
				'endpoint'     => 'knowledge-search',
				'title'        => __( 'Vector Search', 'magick-ai-toolbox' ),
				'description'  => __( 'Query the configured vector collection with text embedding or vector JSON.', 'magick-ai-toolbox' ),
				'field'        => 'query',
				'placeholder'  => __( 'Search query or vector JSON', 'magick-ai-toolbox' ),
				'button'       => __( 'Search vectors', 'magick-ai-toolbox' ),
				'extra_fields' => array(
					array(
						'name'        => 'max_results',
						'label'       => __( 'Max results', 'magick-ai-toolbox' ),
						'placeholder' => '4',
					),
				),
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
				'use_cloud_when_available' => true,
			);
		?>
		<form class="magick-ai-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
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
						! empty( $core_policy['watermark_configured'] ) ? esc_html__( 'configured', 'magick-ai-toolbox' ) : esc_html__( 'off or incomplete', 'magick-ai-toolbox' )
					);
					?>
				</span>
			</div>
			<label>
				<span><?php esc_html_e( 'Attachment ID', 'magick-ai-toolbox' ); ?></span>
				<input type="number" min="1" step="1" name="attachment_id" placeholder="<?php esc_attr_e( 'Attachment ID', 'magick-ai-toolbox' ); ?>" />
			</label>
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
			<p class="description"><?php esc_html_e( 'The returned handoff contains ability input for magick-ai/build-media-derivative-cloud-request. Core remains the policy and final write owner.', 'magick-ai-toolbox' ); ?></p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Build handoff', 'magick-ai-toolbox' ); ?></button>
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
