<?php
/**
 * Stands in for WP-CLI, so static analysis and tests know the plugin's commands outside WP-CLI.
 *
 * The WP_CLI constant is left undefined: the plugin only checks it to print progress bars.
 *
 * @package RelatedPostsForWP
 */

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * WP-CLI's base class for commands.
	 */
	abstract class WP_CLI_Command {
	}
}

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * WP-CLI's static API, keeping what the commands register and print.
	 */
	class WP_CLI {

		/**
		 * The registered commands: the callable by command name.
		 *
		 * @var array<string, mixed>
		 */
		public static $commands = [];

		/**
		 * What the commands printed: the kind of message and the message.
		 *
		 * @var list<array{string, string}>
		 */
		public static $output = [];

		/**
		 * Forget the registered commands and the output.
		 *
		 * @return void
		 */
		public static function reset() {
			self::$commands = [];
			self::$output   = [];
		}

		/**
		 * Register a command.
		 *
		 * @param string $name     The command name.
		 * @param mixed  $callable The class or callable.
		 *
		 * @return bool
		 */
		public static function add_command( $name, $callable ) {
			self::$commands[ $name ] = $callable;

			return true;
		}

		/**
		 * Print a line.
		 *
		 * @param string $message The message.
		 *
		 * @return void
		 */
		public static function line( $message = '' ) {
			self::$output[] = [ 'line', $message ];
		}

		/**
		 * Print a success message.
		 *
		 * @param string $message The message.
		 *
		 * @return void
		 */
		public static function success( $message ) {
			self::$output[] = [ 'success', $message ];
		}

		/**
		 * Print a warning.
		 *
		 * @param string $message The message.
		 *
		 * @return void
		 */
		public static function warning( $message ) {
			self::$output[] = [ 'warning', $message ];
		}

		/**
		 * Print an error and stop, the way WP-CLI ends the command.
		 *
		 * @param string $message The message.
		 *
		 * @return never
		 *
		 * @throws RuntimeException Always.
		 */
		public static function error( $message ) {
			self::$output[] = [ 'error', $message ];

			throw new RuntimeException( $message );
		}
	}
}
