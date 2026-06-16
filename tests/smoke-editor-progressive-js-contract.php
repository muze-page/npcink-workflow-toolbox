<?php
/**
 * Source-only JavaScript lifecycle contract for editor progressive recommendations.
 *
 * @package Npcink_Toolbox
 */

$root      = dirname( __DIR__ );
$editor_js = file_get_contents( $root . '/assets/editor-content-support.js' );

function toolbox_editor_progressive_js_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_editor_progressive_js_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_editor_progressive_js_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_editor_progressive_js_fail( $message );
	}

	toolbox_editor_progressive_js_pass( $message );
}

function toolbox_editor_progressive_js_block( string $source, string $pattern, string $message ): string {
	if ( 1 !== preg_match( $pattern, $source, $matches ) ) {
		toolbox_editor_progressive_js_fail( $message );
	}

	return (string) $matches[0];
}

toolbox_editor_progressive_js_assert( false !== $editor_js, 'Editor Content Support JavaScript is readable.' );

$payload_block = toolbox_editor_progressive_js_block(
	$editor_js,
	'/function progressiveRecommendationPayload\\(postContext\\) \\{.*?\\n\\t\\}/s',
	'Progressive payload builder is present.'
);
toolbox_editor_progressive_js_assert( false !== strpos( $payload_block, "intent: 'progressive_recommendations'" ), 'Automatic progressive prefetch sends only the progressive recommendations intent.' );
toolbox_editor_progressive_js_assert( false === strpos( $payload_block, 'writing_support' ) && false === strpos( $payload_block, 'proposal' ) && false === strpos( $payload_block, 'adapterRestUrl' ), 'Automatic progressive prefetch payload does not trigger writing support or proposal handoff.' );

$prefetch_block = toolbox_editor_progressive_js_block(
	$editor_js,
	'/async function runProgressivePrefetch\\(keyOverride, force\\) \\{.*?\\n\\t\\t\\t\\}/s',
	'Progressive prefetch runner is present.'
);
toolbox_editor_progressive_js_assert( false !== strpos( $prefetch_block, 'if (!force && progressiveLoadedKey === key)' ), 'Same fingerprint uses the loaded-key cache and skips duplicate automatic requests.' );
toolbox_editor_progressive_js_assert( false !== strpos( $prefetch_block, 'postJsonWithTimeout' ) && false !== strpos( $prefetch_block, 'PROGRESSIVE_RECOMMENDATION_TIMEOUT_MS' ), 'Progressive prefetch uses the 2.5 second timeout wrapper.' );
toolbox_editor_progressive_js_assert( false !== strpos( $prefetch_block, 'progressiveRequestSeqRef.current = requestSeq' ) && false !== strpos( $prefetch_block, 'shouldApplyProgressiveResult' ), 'Progressive prefetch tracks request sequence before applying results.' );
toolbox_editor_progressive_js_assert( false !== strpos( $prefetch_block, 'progressiveCurrentKeyRef.current === key' ), 'Progressive prefetch rejects old results after the editor fingerprint changes.' );
toolbox_editor_progressive_js_assert( false !== strpos( $prefetch_block, 'progressiveMountedRef.current' ), 'Progressive prefetch checks mounted state before setting UI state.' );
toolbox_editor_progressive_js_assert( strpos( $prefetch_block, 'if (!shouldApplyProgressiveResult())' ) < strpos( $prefetch_block, 'setProgressiveResult(flowResult)' ), 'Late progressive responses cannot overwrite newer results.' );
toolbox_editor_progressive_js_assert( false !== strpos( $prefetch_block, "status: requestError && requestError.code === 'npcink_toolbox_progressive_timeout' ? 'warning' : 'error'" ), 'Progressive timeout renders a stable warning fallback instead of throwing into the editor.' );

toolbox_editor_progressive_js_assert( false !== strpos( $editor_js, 'progressiveMountedRef.current = false' ) && false !== strpos( $editor_js, 'progressiveRequestSeqRef.current += 1' ), 'Progressive unmount cleanup invalidates in-flight requests before they can set state.' );
toolbox_editor_progressive_js_assert( false !== strpos( $editor_js, 'runProgressivePrefetch(progressiveRecommendationKey(postContext), true)' ), 'Refresh forces only the local progressive recommendation set.' );
toolbox_editor_progressive_js_assert( false !== strpos( $editor_js, "runFlow('progressive_recommendations', { timeoutMs: PROGRESSIVE_RECOMMENDATION_TIMEOUT_MS })" ), 'Manual progressive review fallback keeps the same local timeout budget.' );
toolbox_editor_progressive_js_assert( false !== strpos( $editor_js, 'recommendationCandidateSourceLabel' ) && false !== strpos( $editor_js, 'recommendationCandidateActionClassLabel' ), 'Progressive result rows expose source and action class labels.' );
