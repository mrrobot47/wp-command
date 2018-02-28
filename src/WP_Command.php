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

class WP_Command extends EE_Command {

	private $sites;
	/**
	 * Executes wp-cli command on a site.
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of website.
	 *
	 */
	public function __invoke( $args, $assoc_args ) {

		$this->load_sites_from_db();

		$site_name = $args[0];

		if ( in_array( $site_name, $this->sites ) ) {
			$wp_command = 'wp ' . implode( ' ', array_slice( $args, 1 ) ) ;
			$docker_compose_command = 'docker-compose exec --user=www-data php ' . $wp_command;
			$site_dir = EE::get_runner()->config['sites_path'] . '/' . $site_name ;
			$site_src_dir = $site_dir . '/app/src' ;

			chdir( $site_dir );

			$process = \EE::launch( $docker_compose_command, false, true );

			if( !empty( $args[1] ) && $args[1] === 'db' && !empty( $args[2] ) && $args[2] === 'export' ) {
				\EE::log( "You can find your exported file in $site_src_dir" );
			}

			\EE::log( $process->stdout );
			\EE::log( $process->stderr );
		}

		else {
			EE::error( "No site with name $site_name not found." );
		}
	}

	/**
	 * Stub method which will return all sites from DB in future.
	 */
	private function load_sites_from_db() {

		$this->sites = [];
		$runner = EE::get_runner();

		$dir = dir($runner->config['sites_path']);

		while (false !== ($entry = $dir->read())) {
			if( $entry !== '.' && $entry !== '..' )
			array_push( $this->sites, $entry );
		}
	}
}