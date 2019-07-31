<?php

$latest = '7.3';

$php_versions = array(
	'5.2' => array(
		'base_name'      => 'devilbox/php-fpm-5.2:latest',
		'gd'              => false,
		'extensions'      => array(),
		'pecl_extensions' => array(),
	),
	'5.3' => array(
		'base_name'      => 'devilbox/php-fpm-5.3:latest',
		'gd'              => false,
		'extensions'      => array(),
		'pecl_extensions' => array(),
	),
	'5.4' => array(
		'base_name'      => 'php:5.4-fpm',
		'gd'              => true,
		'extensions'      => array( 'gd', 'mysql', 'mysqli', 'zip' ),
		'pecl_extensions' => array( 'xdebug-2.4.1' ),
	),
	'5.5' => array(
		'base_name'      => 'php:5.5-fpm',
		'gd'              => true,
		'extensions'      => array( 'gd', 'mysql', 'mysqli', 'zip' ),
		'pecl_extensions' => array( 'xdebug-2.5.5' ),
	),
	'5.6' => array(
		'base_name'      => 'php:5.6-fpm',
		'gd'              => true,
		'extensions'      => array( 'gd', 'mysql', 'mysqli', 'zip' ),
		'pecl_extensions' => array( 'xdebug-2.5.5' ),
	),
	'7.0' => array(
		'base_name'      => 'php:7.0-fpm',
		'gd'              => true,
		'extensions'      => array( 'gd', 'opcache', 'mysqli', 'zip' ),
		'pecl_extensions' => array( 'xdebug-2.7.2' ),
	),
	'7.1' => array(
		'base_name'      => 'php:7.1-fpm',
		'gd'              => true,
		'extensions'      => array( 'gd', 'opcache', 'mysqli', 'zip' ),
		'pecl_extensions' => array( 'xdebug-2.7.2' ),
	),
	'7.2' => array(
		'base_name'      => 'php:7.2-fpm',
		'gd'              => true,
		'extensions'      => array( 'gd', 'opcache', 'mysqli', 'zip' ),
		'pecl_extensions' => array( 'xdebug-2.7.2' ),
	),
	'7.3' => array(
		'base_name'      => 'php:7.3-fpm',
		'gd'              => true,
		'extensions'      => array( 'gd', 'opcache', 'mysqli', 'zip' ),
		'pecl_extensions' => array( 'xdebug-2.7.2' ),
	),
);

$php_versions['latest'] = $php_versions[ $latest ];

$generated_warning = <<<EOT
##########################################################################
#
# WARNING: This file was generated by update.php. Do not edit it directly.
#
#
EOT;

$install_extensions = <<<EOT
# install the PHP extensions we need
RUN set -ex; \
	\
%%INSTALL_GD%%
	%%EXTENSIONS%% \
	\
%%PECL_EXTENSIONS%%
EOT;

$install_gd = <<<EOT
	apt-get update; \
	\
	apt-get install -y --no-install-recommends \
		libjpeg-dev \
		libpng-dev \
		libzip-dev \
	; \
	\
	docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr; \
EOT;

$template   = file_get_contents( 'Dockerfile.template' );
$entrypoint = file_get_contents( 'entrypoint.sh' );

foreach ( $php_versions as $version => $config ) {
	echo "-----\n$version\n-----\n";
	if ( 'latest' === $version ) {
		echo "Checkout master\n";
		echo shell_exec( 'git checkout master' );
	} else {
		echo "Check for remote branch\n";
		$branch_exists = shell_exec( "git ls-remote --heads git@github.com:pento/wordpress-develop-php.git $version-fpm" );
		if ( ! $branch_exists ) {
			echo "Remote branch doesn't exist. Checkout master.\n";
			echo shell_exec( 'git checkout master' );
			echo "Create branch\n";
			echo shell_exec( "git checkout -b $version-fpm" );
			echo "Remove unnecessary files\n";
			echo shell_exec( "git rm Dockerfile.template update.php" );
			echo "Commit file removal\n";
			echo shell_exec( "git commit -m 'Creating a new branch for PHP $version.'" );
		} else {
			echo "Remote branch exists. Check it out.\n";
			echo shell_exec( "git checkout $version-fpm" );
		}
	}

	$dockerfile = $template;

	$dockerfile = str_replace( '%%BASE_NAME%%', $config['base_name'], $dockerfile );

	$dockerfile = str_replace( '%%GENERATED_WARNING%%', $generated_warning, $dockerfile );

	if ( $config['gd'] || $config['extensions'] || $config['pecl_extensions'] ) {
		$dockerfile = str_replace( '%%INSTALL_EXTENSIONS%%', $install_extensions, $dockerfile );
	}

	if ( $config['gd'] ) {
		$dockerfile = str_replace( '%%INSTALL_GD%%', $install_gd, $dockerfile );
	}

	if ( $config['extensions'] ) {
		$extensions = 'docker-php-ext-install ' . implode( $config['extensions'], ' ' ) . ";";
		$dockerfile = str_replace( '%%EXTENSIONS%%', $extensions, $dockerfile );
	}

	if ( $config['pecl_extensions'] ) {
		$pecl_extensions = array_reduce( $config['pecl_extensions'], function ( $command, $extension ) {
			if ( $command ) {
				$command .= "\\\n";
			}

			return "$command\tpecl install $extension;";
		}, '' );

		$dockerfile = str_replace( '%%PECL_EXTENSIONS%%', $pecl_extensions, $dockerfile );
	}

	$dockerfile = preg_replace( '/%%[^%]+%%/', '', $dockerfile );

	$fh = fopen( 'Dockerfile', 'w' );
	fwrite( $fh, $dockerfile );
	fclose( $fh );

	$fh = fopen( 'entrypoint.sh', 'w' );
	fwrite( $fh, $entrypoint );
	fclose( $fh );

	echo "Add changed files\n";
	echo shell_exec( 'git add -A' );
	echo "Commit changed files\n";
	echo shell_exec( "git commit -m 'Update the image for PHP $version.'" );
	echo "Push changes\n";
	echo shell_exec( 'git push' );
}
