<?php
/**
 * No-write smoke checks for the Site Knowledge review handoff UI.
 *
 * This script intentionally reads source files only. It must not call REST
 * routes, Adapter endpoints, Core proposal intake, or WordPress write paths.
 *
 * @package NpcinkToolbox
 */

$root = dirname( __DIR__ );

/**
 * Assertion helper.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function npcink_toolbox_sk_review_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, '[fail] ' . $message . "\n" );
		exit( 1 );
	}

	echo '[ok] ' . $message . "\n";
}

$admin_js = file_get_contents( $root . '/assets/admin.js' );
$client   = file_get_contents( $root . '/includes/Provider_Client.php' );
$rest     = file_get_contents( $root . '/includes/Rest_Controller.php' );
$abilities = file_get_contents( $root . '/includes/Abilities.php' );

npcink_toolbox_sk_review_smoke_assert(
	false !== $admin_js && false !== $client && false !== $rest && false !== $abilities,
	'Site Knowledge review smoke can read the required source files.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, 'function submitSiteKnowledgeReviewProposal' )
	&& false !== strpos( $admin_js, "postJson(config.restUrl, 'flows/site-knowledge-review-plan'" )
	&& false !== strpos( $admin_js, "postJson(config.adapterRestUrl, 'proposals/from-plan'" )
	&& false !== strpos( $admin_js, "plan_ability_id: 'npcink-toolbox/build-site-knowledge-review-plan'" ),
	'Admin UI wires Site Knowledge review submission through Toolbox plan build and Adapter from-plan intake.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, "submitButton.setAttribute('data-toolbox-site-knowledge-review-submit', 'true')" )
	&& false !== strpos( $admin_js, "submitButton.addEventListener('click', () => submitSiteKnowledgeReviewProposal(container, handoff, submitButton))" ),
	'Admin UI submits Site Knowledge review only from the explicit operator button.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, 'Site Knowledge review proposal submitted' )
	&& false !== strpos( $admin_js, 'Human title and content input are required before approval, preflight, or execution can proceed.' )
	&& false !== strpos( $admin_js, 'Could not submit the Site Knowledge review proposal.' ),
	'Admin UI exposes success and failure copy for the blocked Core review proposal.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $client, 'npcink_toolbox_site_knowledge_review_evidence_required' )
	&& false !== strpos( $client, "'artifact_type'          => 'site_knowledge_review_plan'" )
	&& false !== strpos( $client, "'target_ability_id' => 'npcink-abilities-toolkit/create-draft'" )
	&& false !== strpos( $client, "'proposal_ready'    => false" )
	&& false !== strpos( $client, "'requires_input'    => array( 'title', 'content' )" ),
	'Provider client builds an evidence-gated blocked review plan that still requires human draft input.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $client, "'site_knowledge_evidence_refs' => \$this->sanitize_payload( \$evidence_refs )" )
	&& false !== strpos( $client, "'dry_run'         => true" )
	&& false !== strpos( $client, "'commit'          => false" )
	&& false !== strpos( $client, "'direct_wordpress_write' => false" ),
	'Provider client preserves evidence and keeps the generated plan no-write.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $rest, "\$this->post( '/flows/site-knowledge-review-plan', 'site_knowledge_review_plan' );" )
	&& false !== strpos( $rest, 'public function site_knowledge_review_plan' )
	&& false !== strpos( $abilities, "'npcink-toolbox/build-site-knowledge-review-plan'" ),
	'Toolbox exposes the narrow REST route and ability used by the Site Knowledge review UI.'
);

echo "Site Knowledge review UI smoke: ok\n";
