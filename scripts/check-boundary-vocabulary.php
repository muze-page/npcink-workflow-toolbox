<?php
/**
 * Checks boundary vocabulary that is easy to regress during documentation work.
 *
 * @package NpcinkToolbox
 */

$root     = dirname( __DIR__ );
$failures = array();

function npcink_boundary_fail( $message ) {
	global $failures;
	$failures[] = (string) $message;
}

function npcink_boundary_read( $relative ) {
	global $root;

	$path = $root . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		npcink_boundary_fail( 'Missing readable file: ' . $relative );
		return '';
	}

	$contents = file_get_contents( $path );
	return is_string( $contents ) ? $contents : '';
}

function npcink_boundary_project_files() {
	global $root;

	$include_roots = array( 'README.md', 'readme.txt', 'composer.json', 'docs', 'includes', 'assets', 'scripts' );
	$files         = array();

	foreach ( $include_roots as $include_root ) {
		$path = $root . '/' . $include_root;
		if ( is_file( $path ) ) {
			$files[] = $path;
			continue;
		}

		if ( ! is_dir( $path ) ) {
			continue;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$path,
				FilesystemIterator::SKIP_DOTS
			)
		);
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$relative = npcink_boundary_relative_path( $file->getPathname() );
			if ( 'scripts/check-boundary-vocabulary.php' === $relative ) {
				continue;
			}

			$extension = strtolower( $file->getExtension() );
			if ( in_array( $extension, array( 'css', 'js', 'json', 'md', 'mjs', 'php', 'po', 'txt' ), true ) ) {
				$files[] = $file->getPathname();
			}
		}
	}

	sort( $files );
	return $files;
}

function npcink_boundary_relative_path( $path ) {
	global $root;
	return str_replace( $root . '/', '', $path );
}

function npcink_boundary_assert_contains( $relative, $needle, $message ) {
	$contents = npcink_boundary_read( $relative );
	if ( false === strpos( $contents, $needle ) ) {
		npcink_boundary_fail( $relative . ': ' . $message . ' Missing: ' . $needle );
	}
}

function npcink_boundary_assert_forbidden( $needle, $message, $allowed_files = array() ) {
	foreach ( npcink_boundary_project_files() as $file ) {
		$relative = npcink_boundary_relative_path( $file );
		if ( in_array( $relative, $allowed_files, true ) ) {
			continue;
		}

		$contents = file_get_contents( $file );
		if ( is_string( $contents ) && false !== strpos( $contents, $needle ) ) {
			npcink_boundary_fail( $relative . ': ' . $message . ' Found: ' . $needle );
		}
	}
}

$required_evidence = array(
	array(
		'file'    => 'README.md',
		'needle'  => 'compatibility allowlist, not an ownership claim',
		'message' => 'README must keep route lists from becoming ownership claims.',
	),
	array(
		'file'    => 'README.md',
		'needle'  => '### Boundary Exceptions Only',
		'message' => 'README must keep accepted exceptions separate from default runtime ownership.',
	),
	array(
		'file'    => 'README.md',
		'needle'  => 'refresh-only bounded',
		'message' => 'README must constrain Site Knowledge sync to bounded refresh.',
	),
	array(
		'file'    => 'docs/boundary.md',
		'needle'  => 'Toolbox must never implement `confirm_token`, `write_confirmed`',
		'message' => 'Boundary doc must block legacy write-confirmation contracts.',
	),
	array(
		'file'    => 'docs/boundary.md',
		'needle'  => 'local vector database, embeddings, RAG runtime',
		'message' => 'Boundary doc must block local vector/RAG ownership.',
	),
	array(
		'file'    => 'docs/product-positioning.md',
		'needle'  => 'Host-generated image candidates are a separate',
		'message' => 'Product positioning must distinguish hosted candidates from image-source connectors.',
	),
	array(
		'file'    => 'docs/product-positioning.md',
		'needle'  => 'The disabled Local Fallback WP-Cron dry-run preview is an accepted boundary',
		'message' => 'Product positioning must constrain Local Fallback WP-Cron as an exception.',
	),
	array(
		'file'    => 'docs/connector-ability-exposure.md',
		'needle'  => 'Toolbox-registered wrapper',
		'message' => 'Connector docs must distinguish Toolbox wrapper abilities.',
	),
	array(
		'file'    => 'docs/connector-ability-exposure.md',
		'needle'  => 'External Toolkit target abilities may appear',
		'message' => 'Connector docs must distinguish external Toolkit target abilities.',
	),
	array(
		'file'    => 'docs/connector-ability-exposure.md',
		'needle'  => 'Cloud-owned bridge metadata only',
		'message' => 'Connector docs must keep cloud-web-search as a Cloud bridge.',
	),
	array(
		'file'    => 'docs/adversarial-boundary-findings-triage.md',
		'needle'  => 'test:boundary-vocabulary',
		'message' => 'Triage must point follow-up vocabulary work at the dedicated composer target.',
	),
);

foreach ( $required_evidence as $evidence ) {
	npcink_boundary_assert_contains( $evidence['file'], $evidence['needle'], $evidence['message'] );
}

$forbidden_phrases = array(
	array(
		'needle'  => 'local vector context',
		'message' => 'Do not reintroduce local vector/RAG ownership wording outside the recorded finding.',
		'allowed' => array( 'docs/adversarial-boundary-findings-triage.md' ),
	),
	array(
		'needle'  => 'Npcink -> Toolbox',
		'message' => 'Use the current menu label Npcink -> Workflow Toolbox.',
	),
	array(
		'needle'  => 'Tools -> Npcink Toolbox',
		'message' => 'Use the current fallback menu label Tools -> Npcink Workflow Toolbox.',
	),
	array(
		'needle'  => 'single `web-research` ability',
		'message' => 'Do not imply Toolbox owns a local web-research ability.',
	),
	array(
		'needle'  => 'Tavily and Bocha',
		'message' => 'Do not reintroduce local web-search provider ownership wording.',
	),
	array(
		'needle'  => 'bounded post-search enhancement',
		'message' => 'Do not reintroduce local post-search enhancement ownership wording.',
	),
	array(
		'needle'  => 'AI image generation connectors',
		'message' => 'Use image-source connectors or host-generated image candidates, not AI generation connector ownership.',
	),
	array(
		'needle'  => 'Jina Reader/Reranker candidate button',
		'message' => 'Do not present Jina Reader/Reranker as an active Toolbox runtime feature outside the recorded finding.',
		'allowed' => array( 'docs/adversarial-boundary-review.md' ),
	),
);

foreach ( $forbidden_phrases as $rule ) {
	npcink_boundary_assert_forbidden( $rule['needle'], $rule['message'], $rule['allowed'] ?? array() );
}

if ( ! empty( $failures ) ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '[fail] ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo "Boundary vocabulary guard: ok\n";
