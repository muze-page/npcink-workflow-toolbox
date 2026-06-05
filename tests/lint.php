<?php
/**
 * PHP syntax lint runner.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );
$files = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ): bool {
			if ( $file->isDir() ) {
				return ! in_array( $file->getBasename(), array( '.git', 'vendor', 'node_modules' ), true );
			}

			return 'php' === $file->getExtension();
		}
	)
);

$failed = false;
foreach ( $files as $file ) {
	$path = $file->getPathname();
	$output = array();
	$status = 0;
	exec( 'php -l ' . escapeshellarg( $path ) . ' 2>&1', $output, $status );
	if ( 0 !== $status ) {
		$failed = true;
		echo implode( PHP_EOL, $output ) . PHP_EOL;
	}
}

if ( $failed ) {
	exit( 1 );
}

echo "PHP lint passed.\n";
