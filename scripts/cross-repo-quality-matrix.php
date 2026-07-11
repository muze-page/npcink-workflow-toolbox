<?php
/**
 * Cross-repository quality and drift matrix for the Npcink/Magick AI stack.
 *
 * This script is intentionally read-only except for an optional report file.
 * It does not stage files, reset branches, fetch remotes, or mutate WordPress.
 *
 * @package Npcink_Toolbox
 */

$root        = dirname( __DIR__ );
$family_root = getenv( 'NPCINK_REPO_FAMILY_ROOT' ) ?: dirname( $root );

$repos = array(
	array(
		'name'       => 'npcink-abilities-toolkit',
		'paths'      => array( 'npcink-abilities-toolkit' ),
		'gate'       => 'composer test:all',
		'gate_notes' => 'Reusable WordPress ability contracts and callbacks.',
	),
	array(
		'name'       => 'npcink-governance-core',
		'paths'      => array( 'npcink-governance-core' ),
		'gate'       => 'composer test:all',
		'gate_notes' => 'Proposal, approval, preflight, audit, and fail-closed contracts.',
	),
	array(
		'name'       => 'npcink-ai-client-adapter',
		'paths'      => array( 'npcink-ai-client-adapter' ),
		'gate'       => 'composer test:all',
		'gate_notes' => 'Adapter route, recipe, and execution-profile contracts.',
	),
	array(
		'name'       => 'npcink-workflow-toolbox',
		'paths'      => array( 'npcink-workflow-toolbox' ),
		'gate'       => 'composer test:all',
		'gate_notes' => 'Toolbox product-surface static contracts and local smoke gates.',
	),
	array(
		'name'       => 'npcink-cloud-addon',
		'paths'      => array( 'npcink-cloud-addon' ),
		'gate'       => 'composer test:all',
		'gate_notes' => 'Cloud Addon transport, settings, and WordPress plugin contracts.',
	),
	array(
		'name'       => 'npcink-ai-cloud',
		'paths'      => array( 'npcink-ai-cloud' ),
		'gate'       => 'npm run check:fast',
		'gate_notes' => 'Cloud runtime fast contract, seam, perimeter, and anti-drift checks.',
	),
);

$options = array(
	'run_gates'     => false,
	'json'          => false,
	'fail_on_dirty' => false,
	'output'        => '',
	'repos'         => array(),
);

foreach ( array_slice( $_SERVER['argv'] ?? array(), 1 ) as $arg ) {
	if ( '--run-gates' === $arg ) {
		$options['run_gates'] = true;
		continue;
	}

	if ( '--json' === $arg ) {
		$options['json'] = true;
		continue;
	}

	if ( '--fail-on-dirty' === $arg ) {
		$options['fail_on_dirty'] = true;
		continue;
	}

	if ( 0 === strpos( $arg, '--output=' ) ) {
		$options['output'] = substr( $arg, strlen( '--output=' ) );
		continue;
	}

	if ( 0 === strpos( $arg, '--repo=' ) ) {
		$options['repos'][] = substr( $arg, strlen( '--repo=' ) );
		continue;
	}

	fwrite( STDERR, "Unknown option: {$arg}\n" );
	fwrite( STDERR, "Usage: php scripts/cross-repo-quality-matrix.php [--run-gates] [--fail-on-dirty] [--json] [--output=PATH] [--repo=NAME]\n" );
	exit( 2 );
}

if ( ! empty( $options['repos'] ) ) {
	$repo_filter = array_flip( $options['repos'] );
	$repos       = array_values(
		array_filter(
			$repos,
			static function ( array $repo ) use ( $repo_filter ): bool {
				return isset( $repo_filter[ $repo['name'] ] );
			}
		)
	);
}

/**
 * Run a shell command and capture bounded output.
 *
 * @param string $command Command to run.
 * @param string $cwd Working directory.
 * @return array{exit_code:int,output:string,duration_ms:float}
 */
function npcink_quality_matrix_run( string $command, string $cwd ): array {
	$start   = microtime( true );
	$process = proc_open(
		'bash -lc ' . escapeshellarg( $command ),
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes,
		$cwd
	);

	if ( ! is_resource( $process ) ) {
		return array(
			'exit_code'   => 127,
			'output'      => 'Failed to start process.',
			'duration_ms' => 0.0,
		);
	}

	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	$exit_code = proc_close( $process );

	return array(
		'exit_code'   => (int) $exit_code,
		'output'      => trim( (string) $output . ( '' !== trim( (string) $error ) ? "\n" . (string) $error : '' ) ),
		'duration_ms' => round( ( microtime( true ) - $start ) * 1000, 1 ),
	);
}

/**
 * Return the last non-empty lines from command output.
 *
 * @param string $output Command output.
 * @param int    $limit Number of lines.
 * @return string
 */
function npcink_quality_matrix_tail( string $output, int $limit = 8 ): string {
	$lines = array_values(
		array_filter(
			preg_split( '/\R/', $output ) ?: array(),
			static function ( string $line ): bool {
				return '' !== trim( $line );
			}
		)
	);

	return implode( "\n", array_slice( $lines, -1 * $limit ) );
}

/**
 * Resolve the first existing candidate repository path.
 *
 * @param string $family_root Repository family root.
 * @param array  $paths Candidate directory names.
 * @return string
 */
function npcink_quality_matrix_resolve_path( string $family_root, array $paths ): string {
	foreach ( $paths as $path ) {
		$candidate = $family_root . DIRECTORY_SEPARATOR . $path;
		if ( is_dir( $candidate ) ) {
			return $candidate;
		}
	}

	return $family_root . DIRECTORY_SEPARATOR . (string) ( $paths[0] ?? '' );
}

/**
 * Parse git status first line for ahead/behind and branch detail.
 *
 * @param string $branch_line Status branch line.
 * @return array{branch:string,ahead:int,behind:int}
 */
function npcink_quality_matrix_parse_branch( string $branch_line ): array {
	$branch = preg_replace( '/^##\s+/', '', trim( $branch_line ) );
	$ahead  = 0;
	$behind = 0;

	if ( preg_match( '/ahead\s+(\d+)/', $branch_line, $matches ) ) {
		$ahead = (int) $matches[1];
	}

	if ( preg_match( '/behind\s+(\d+)/', $branch_line, $matches ) ) {
		$behind = (int) $matches[1];
	}

	return array(
		'branch' => (string) $branch,
		'ahead'  => $ahead,
		'behind' => $behind,
	);
}

$results  = array();
$failures = 0;

foreach ( $repos as $repo ) {
	$path   = npcink_quality_matrix_resolve_path( $family_root, $repo['paths'] );
	$result = array(
		'name'          => $repo['name'],
		'path'          => $path,
		'exists'        => is_dir( $path ),
		'is_git'        => false,
		'branch'        => 'missing',
		'ahead'         => null,
		'behind'        => null,
		'dirty_count'   => null,
		'dirty_files'   => array(),
		'stash_count'   => null,
		'worktrees'     => null,
		'gate'          => $repo['gate'],
		'gate_notes'    => $repo['gate_notes'],
		'gate_ran'      => false,
		'gate_status'   => 'not_run',
		'gate_exit'     => null,
		'gate_duration' => null,
		'gate_tail'     => '',
	);

	if ( ! $result['exists'] ) {
		++$failures;
		$results[] = $result;
		continue;
	}

	$git_root = npcink_quality_matrix_run( 'git rev-parse --show-toplevel', $path );
	if ( 0 !== $git_root['exit_code'] ) {
		++$failures;
		$result['branch'] = 'not a git repository';
		$results[]        = $result;
		continue;
	}

	$result['is_git'] = true;
	$status           = npcink_quality_matrix_run( 'git status --short --branch', $path );
	$status_lines     = preg_split( '/\R/', trim( $status['output'] ) ) ?: array();
	$branch_info      = npcink_quality_matrix_parse_branch( (string) ( $status_lines[0] ?? '' ) );
	$dirty_files      = array_values(
		array_filter(
			array_slice( $status_lines, 1 ),
			static function ( string $line ): bool {
				return '' !== trim( $line );
			}
		)
	);

	$result['branch']      = $branch_info['branch'];
	$result['ahead']       = $branch_info['ahead'];
	$result['behind']      = $branch_info['behind'];
	$result['dirty_count'] = count( $dirty_files );
	$result['dirty_files'] = $dirty_files;
	$stash_output          = npcink_quality_matrix_run( 'git stash list', $path )['output'];
	$worktree_output       = npcink_quality_matrix_run( 'git worktree list --porcelain', $path )['output'];
	$result['stash_count'] = max( 0, substr_count( $stash_output, "\n" ) + ( '' === trim( $stash_output ) ? 0 : 1 ) );
	$result['worktrees']   = max( 0, substr_count( $worktree_output, "\nworktree " ) + 1 );

	if ( $options['fail_on_dirty'] && $result['dirty_count'] > 0 ) {
		++$failures;
	}

	if ( $options['run_gates'] ) {
		$result['gate_ran']      = true;
		$gate                   = npcink_quality_matrix_run( $repo['gate'], $path );
		$result['gate_exit']     = $gate['exit_code'];
		$result['gate_duration'] = $gate['duration_ms'];
		$result['gate_tail']     = npcink_quality_matrix_tail( $gate['output'] );
		$result['gate_status']   = 0 === $gate['exit_code'] ? 'passed' : 'failed';

		if ( 0 !== $gate['exit_code'] ) {
			++$failures;
		}
	}

	$results[] = $result;
}

$report = array(
	'generated_at' => gmdate( 'c' ),
	'family_root'  => $family_root,
	'run_gates'    => $options['run_gates'],
	'results'      => $results,
	'failures'     => $failures,
);

if ( $options['json'] ) {
	$output = json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
	if ( ! is_string( $output ) ) {
		fwrite( STDERR, "Failed to encode quality matrix report.\n" );
		exit( 1 );
	}
} else {
	$lines   = array();
	$lines[] = '# Cross-Repo Quality Matrix';
	$lines[] = '';
	$lines[] = '- Generated: `' . $report['generated_at'] . '`';
	$lines[] = '- Family root: `' . $family_root . '`';
	$lines[] = '- Gates: `' . ( $options['run_gates'] ? 'run' : 'not_run' ) . '`';
	$lines[] = '';
	$lines[] = '| Repo | Branch | Dirty | Ahead | Behind | Gate | Result |';
	$lines[] = '| --- | --- | ---: | ---: | ---: | --- | --- |';

	foreach ( $results as $result ) {
		$lines[] = sprintf(
			'| `%s` | `%s` | %s | %s | %s | `%s` | `%s` |',
			$result['name'],
			$result['branch'],
			null === $result['dirty_count'] ? 'n/a' : (string) $result['dirty_count'],
			null === $result['ahead'] ? 'n/a' : (string) $result['ahead'],
			null === $result['behind'] ? 'n/a' : (string) $result['behind'],
			$result['gate'],
			$result['gate_status']
		);
	}

	$lines[] = '';
	$lines[] = '## Details';
	foreach ( $results as $result ) {
		$lines[] = '';
		$lines[] = '### ' . $result['name'];
		$lines[] = '';
		$lines[] = '- Path: `' . $result['path'] . '`';
		$lines[] = '- Exists: `' . ( $result['exists'] ? 'yes' : 'no' ) . '`';
		$lines[] = '- Git repo: `' . ( $result['is_git'] ? 'yes' : 'no' ) . '`';
		$lines[] = '- Gate notes: ' . $result['gate_notes'];
		if ( ! empty( $result['dirty_files'] ) ) {
			$lines[] = '- Dirty files:';
			foreach ( $result['dirty_files'] as $dirty_file ) {
				$lines[] = '  - `' . $dirty_file . '`';
			}
		}
		if ( $result['gate_ran'] ) {
			$lines[] = '- Gate duration: `' . (string) $result['gate_duration'] . 'ms`';
			$lines[] = '- Gate tail:';
			$lines[] = '```text';
			$lines[] = $result['gate_tail'];
			$lines[] = '```';
		}
	}

	$output = implode( "\n", $lines ) . "\n";
}

if ( '' !== $options['output'] ) {
	$target = $options['output'];
	if ( ! str_starts_with( $target, DIRECTORY_SEPARATOR ) ) {
		$target = $root . DIRECTORY_SEPARATOR . $target;
	}
	$dir = dirname( $target );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0775, true );
	}
	file_put_contents( $target, $output );
}

echo $output;

exit( $failures > 0 ? 1 : 0 );
