<?php
/**
 * Fast cross-repository observation brief for the Npcink/Magick AI stack.
 *
 * This script is read-only. It reuses the quality matrix JSON output and turns
 * repository state into a small decision queue for the next development move.
 *
 * @package Npcink_Toolbox
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$options = array(
	'run_gates' => false,
);

foreach ( array_slice( $_SERVER['argv'] ?? array(), 1 ) as $arg ) {
	if ( '--run-gates' === $arg ) {
		$options['run_gates'] = true;
		continue;
	}

	fwrite( STDERR, "Unknown option: {$arg}\n" );
	fwrite( STDERR, "Usage: php scripts/cross-repo-observation-brief.php [--run-gates]\n" );
	exit( 2 );
}

/**
 * Run a command and capture output.
 *
 * @param string $command Command to run.
 * @param string $cwd Working directory.
 * @return array{exit_code:int,output:string}
 */
function npcink_observation_run( string $command, string $cwd ): array {
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
			'exit_code' => 127,
			'output'    => 'Failed to start process.',
		);
	}

	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return array(
		'exit_code' => (int) proc_close( $process ),
		'output'    => trim( (string) $output . ( '' !== trim( (string) $error ) ? "\n" . (string) $error : '' ) ),
	);
}

/**
 * Describe one repository's next action.
 *
 * @param array{name:string,exists:bool,is_git:bool,dirty_count:?int,ahead:?int,behind:?int,gate_status:string,gate:string,dirty_files:array<int,string>} $repo Repository row.
 * @return array{priority:int,status:string,next_action:string,evidence:string}
 */
function npcink_observation_classify_repo( array $repo ): array {
	if ( empty( $repo['exists'] ) ) {
		return array(
			'priority'    => 10,
			'status'      => 'missing',
			'next_action' => 'Locate or restore this repository before cross-repo work.',
			'evidence'    => 'Path is missing.',
		);
	}

	if ( empty( $repo['is_git'] ) ) {
		return array(
			'priority'    => 20,
			'status'      => 'not_git',
			'next_action' => 'Check workspace setup before using this repo in a milestone.',
			'evidence'    => 'Path is not a Git repository.',
		);
	}

	if ( 'failed' === (string) $repo['gate_status'] ) {
		return array(
			'priority'    => 30,
			'status'      => 'gate_failed',
			'next_action' => 'Fix this gate before adding new feature work.',
			'evidence'    => 'Gate failed: `' . (string) $repo['gate'] . '`.',
		);
	}

	if ( (int) ( $repo['dirty_count'] ?? 0 ) > 0 ) {
		$first_dirty = (string) ( $repo['dirty_files'][0] ?? 'dirty worktree' );
		return array(
			'priority'    => 40,
			'status'      => 'dirty_observation',
			'next_action' => 'Inspect the dirty files and decide whether they are in-scope, user work, or a separate pass.',
			'evidence'    => (string) $repo['dirty_count'] . ' dirty file(s); first: `' . $first_dirty . '`.',
		);
	}

	if ( (int) ( $repo['behind'] ?? 0 ) > 0 ) {
		return array(
			'priority'    => 50,
			'status'      => 'behind_remote',
			'next_action' => 'Sync or inspect upstream before building on this branch.',
			'evidence'    => 'Behind upstream by ' . (string) $repo['behind'] . ' commit(s).',
		);
	}

	if ( (int) ( $repo['ahead'] ?? 0 ) > 0 ) {
		return array(
			'priority'    => 60,
			'status'      => 'publish_or_pr',
			'next_action' => 'Publish, open/update a PR, or explicitly keep the commits local.',
			'evidence'    => 'Ahead of upstream by ' . (string) $repo['ahead'] . ' commit(s).',
		);
	}

	if ( 'passed' === (string) $repo['gate_status'] ) {
		return array(
			'priority'    => 80,
			'status'      => 'clean_gate_passed',
			'next_action' => 'Ready for narrow follow-up work if the product goal is clear.',
			'evidence'    => 'Clean worktree and gate passed.',
		);
	}

	return array(
		'priority'    => 90,
		'status'      => 'clean_status_only',
		'next_action' => 'Run the repo gate before milestone closeout or release decisions.',
		'evidence'    => 'Clean worktree; gate not run.',
	);
}

$matrix_script = $root . '/scripts/cross-repo-quality-matrix.php';
$matrix_cmd    = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $matrix_script ) . ' --json';
if ( $options['run_gates'] ) {
	$matrix_cmd .= ' --run-gates';
}

$matrix = npcink_observation_run( $matrix_cmd, $root );
if ( 0 !== $matrix['exit_code'] ) {
	fwrite( STDERR, $matrix['output'] . "\n" );
	exit( $matrix['exit_code'] );
}

$report = json_decode( $matrix['output'], true );
if ( ! is_array( $report ) || ! isset( $report['results'] ) || ! is_array( $report['results'] ) ) {
	fwrite( STDERR, "Failed to decode quality matrix JSON.\n" );
	exit( 1 );
}

$roles = array(
	'npcink-abilities-toolkit' => 'ability_contracts',
	'npcink-governance-core'   => 'proposal_handoff',
	'npcink-ai-client-adapter' => 'execution_profiles',
	'npcink-workflow-toolbox'  => 'product_surface',
	'npcink-cloud-addon'       => 'signed_transport',
	'npcink-ai-cloud'          => 'runtime_detail',
	'magick-ai-toolbox'        => 'legacy_current_toolbox',
);

$rows = array();
foreach ( $report['results'] as $repo ) {
	if ( ! is_array( $repo ) ) {
		continue;
	}

	$row           = npcink_observation_classify_repo( $repo );
	$row['repo']   = (string) ( $repo['name'] ?? 'unknown' );
	$row['role']   = (string) ( $roles[ $row['repo'] ] ?? 'unmapped' );
	$row['branch'] = (string) ( $repo['branch'] ?? 'unknown' );
	$rows[]        = $row;
}

usort(
	$rows,
	static function ( array $a, array $b ): int {
		return ( $a['priority'] <=> $b['priority'] ) ?: strcmp( $a['repo'], $b['repo'] );
	}
);

$dirty_count  = count( array_filter( $rows, static fn( array $row ): bool => 'dirty_observation' === $row['status'] ) );
$failed_count = count( array_filter( $rows, static fn( array $row ): bool => 'gate_failed' === $row['status'] ) );
$ahead_count  = count( array_filter( $rows, static fn( array $row ): bool => 'publish_or_pr' === $row['status'] ) );

$lines   = array();
$lines[] = '# Cross-Repo Observation Brief';
$lines[] = '';
$lines[] = '- Generated: `' . (string) ( $report['generated_at'] ?? gmdate( 'c' ) ) . '`';
$lines[] = '- Family root: `' . (string) ( $report['family_root'] ?? dirname( $root ) ) . '`';
$lines[] = '- Gates: `' . ( $options['run_gates'] ? 'run' : 'not_run' ) . '`';
$lines[] = '';
$lines[] = '## Fast Decision';
$lines[] = '';
if ( $failed_count > 0 ) {
	$lines[] = 'Fix failed gates first. Do not add product work while a required gate is red.';
} elseif ( $dirty_count > 0 ) {
	$lines[] = 'Inspect dirty worktrees first. Treat dirty files as a separate observation pass unless the user confirms they are current-scope work.';
} elseif ( $ahead_count > 0 ) {
	$lines[] = 'Publish or PR completed local commits before starting a new broad implementation slice.';
} else {
	$lines[] = 'The repo family is clean for a narrow follow-up. Pick one product goal and keep the existing role split.';
}

$lines[] = '';
$lines[] = '## Decision Queue';
$lines[] = '';
$lines[] = '| Repo | Role | Status | Evidence | Next action |';
$lines[] = '| --- | --- | --- | --- | --- |';
foreach ( $rows as $row ) {
	$lines[] = sprintf(
		'| `%s` | `%s` | `%s` | %s | %s |',
		$row['repo'],
		$row['role'],
		$row['status'],
		$row['evidence'],
		$row['next_action']
	);
}

$lines[] = '';
$lines[] = '## Efficient Development Rule';
$lines[] = '';
$lines[] = 'Use this brief before planning a new slice. If it reports dirty worktrees, ahead commits, or failed gates, resolve that queue before adding scope. Keep `ability_contracts`, `proposal_handoff`, `execution_profiles`, `product_surface`, `signed_transport`, and `runtime_detail` separate.';

echo implode( "\n", $lines ) . "\n";
exit( $failed_count > 0 ? 1 : 0 );
