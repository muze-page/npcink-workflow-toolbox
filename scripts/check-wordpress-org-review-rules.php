<?php
/**
 * Guards against WordPress.org review patterns that should block release.
 *
 * @package NpcinkToolbox
 */

$root     = dirname( __DIR__ );
$failures = array();

function npcink_wporg_fail( $message ) {
	global $failures;
	$failures[] = (string) $message;
}

function npcink_wporg_read( $path ) {
	if ( ! is_readable( $path ) ) {
		npcink_wporg_fail( 'Missing readable file: ' . $path );
		return '';
	}

	$contents = file_get_contents( $path );
	return is_string( $contents ) ? $contents : '';
}

function npcink_wporg_php_files() {
	global $root;

	$files = glob( $root . '/*.php' );
	$files = is_array( $files ) ? $files : array();

	foreach ( array( 'includes' ) as $directory ) {
		$path = $root . '/' . $directory;
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
			if ( 'php' === strtolower( $file->getExtension() ) ) {
				$files[] = $file->getPathname();
			}
		}
	}

	sort( $files );
	return $files;
}

$rules = array(
	'Do not build paths into wp-admin/includes except the dbDelta upgrade.php activation helper.' => '/wp-admin\/includes\/(?!upgrade\.php)/',
	'Do not ship inline admin styles through wp_add_inline_style(); use assets/*.css.' => '/wp_add_inline_style\s*\(/',
	'Do not ship inline admin scripts through wp_add_inline_script(); use assets/*.js or data attributes.' => '/wp_add_inline_script\s*\(/',
	'Do not output raw script tags from PHP admin views; enqueue scripts.' => '/<\s*\/?\s*script\b/i',
	'Do not output raw style tags from PHP admin views; enqueue styles.' => '/<\s*\/?\s*style\b/i',
	'Do not read $_GET directly in plugin views; route reads through nonce-verified helpers.' => '/\$_GET\s*\[/',
);

foreach ( npcink_wporg_php_files() as $file ) {
	$relative = str_replace( $root . '/', '', $file );
	$contents = npcink_wporg_read( $file );

	foreach ( $rules as $message => $pattern ) {
		if ( preg_match( $pattern, $contents ) ) {
			npcink_wporg_fail( $relative . ': ' . $message );
		}
	}
}

if ( ! empty( $failures ) ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '[fail] ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo "WordPress.org review guard: ok\n";
