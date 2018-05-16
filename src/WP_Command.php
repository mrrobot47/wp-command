<?php

/**
 * Executes wp-cli command on a site.
 *
 * ## EXAMPLES
 *
 *     # Create simple WordPress site
 *     $ ee wp test.local plugin list
 *
 * @package ee-cli
 */

use EE\Utils;

class WP_Command extends EE_Command {

	private $db;

	/**
	 * Executes wp-cli command on a site.
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of website.
	 */
	public function __invoke( $args, $assoc_args ) {

		$this->db              = EE::db();
		$site_name             = $args[0];
		$import_export_command = false;

		if ( $this->db::site_in_db( $site_name ) ) {
			if ( ! $this->db::select( array( 'is_enabled' ), array( 'sitename' => $site_name ) )[0]['is_enabled'] ) {
				EE::error( "Please enable $site_name using `ee site enable $site_name` command to run wp commands." );
			}
			$arguments = '';
			if ( ! empty( $assoc_args ) ) {
				foreach ( $assoc_args as $key => $value ) {
					$arguments .= ' --' . $key . '=' . $value;
				}
			}

			if ( \EE::get_runner()->config['debug'] ) {
				$arguments .= ' --debug';
			}

			$site_dir     = EE::get_runner()->config['sites_path'] . '/' . $site_name;
			$site_src_dir = $site_dir . '/app/src';

			// Check if user is running `wp db export or import`
			if ( ! empty( $args[1] ) && $args[1] === 'db' && ! empty( $args[2] ) && ( $args[2] === 'export' || $args[2] === 'import' ) ) {
				$import_export_command = true;
				if ( isset( $args[3] ) && ! @is_dir( $args[3] ) ) {
					$file_name = $args[3];
				} else {
					$hash          = substr( md5( mt_rand() ), 0, 7 );
					$gen_file_name = sprintf( '%s-%s-%s.sql', implode( '_', explode( '.', $site_name ) ), date( 'Y-m-d' ), $hash );
					$file_name     = @is_dir( $args[3] ) ? \EE\Utils\trailingslashit( $args[3] ) . $gen_file_name : $gen_file_name;
				}
				$path_info = pathinfo( $file_name );
				$args[3]   = $path_info['basename'];
				if ( $site_src_dir !== $path_info['dirname'] && 'import' === $args[2] ) {
					if ( file_exists( $file_name ) ) {
						copy( $file_name, $site_src_dir . '/' . $path_info['basename'] );
					} else {
						\EE::error( "$file_name does not exist." );
					}
				}
				if ( $site_src_dir !== $path_info['dirname'] && 'export' === $args[2] ) {
					if ( is_dir( $path_info['dirname'] ) ) {
						$file_name = \EE\Utils\trailingslashit( realpath( $path_info['dirname'] ) ) . $path_info['basename'];
					} else {
						\EE::error( $path_info['dirname'] . ' is not a directory.' );
					}
				}
			}

			$wp_command             = 'wp ' . implode( ' ', array_slice( $args, 1 ) ) . $arguments;
			$docker_compose_command = 'docker-compose exec --user=www-data php ' . $wp_command;

			chdir( $site_dir );

			passthru( $docker_compose_command, $return );
			if ( $import_export_command ) {
				if ( 'import' === $args[2] ) {
					unlink( $site_src_dir . '/' . $path_info['basename'] );
				} else {
					rename( 'app/src/' . $path_info['basename'], $file_name );
					EE::success( "Path: $file_name" );
				}
			}

		} else {
			EE::error( "No site with name `$site_name` found." );
		}
	}
}
