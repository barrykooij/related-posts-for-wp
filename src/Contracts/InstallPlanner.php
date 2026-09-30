<?php
/**
 * The install planner contract file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Contracts;

/**
 * Turns what the admin asked for (the number of related posts, whether to rebuild, and in premium the post types) into
 * the tasks of an installation. The premium add-on replaces it through `Main::set_install_planner()`.
 *
 * The background installer plans again in every request it runs in, so the same request must give the same tasks, in
 * the same order.
 */
interface InstallPlanner {

	/**
	 * What a request may hold, as the `args` of a REST route: a JSON schema per key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function args(): array;

	/**
	 * Whether the site was installed: the admin app shows its first-run card when it was not.
	 *
	 * @return bool
	 */
	public function is_installed(): bool;

	/**
	 * The tasks of an installation.
	 *
	 * @param array<string, mixed> $request What the admin asked for, validated against args().
	 *
	 * @return InstallTask[]
	 */
	public function plan( array $request ): array;
}
