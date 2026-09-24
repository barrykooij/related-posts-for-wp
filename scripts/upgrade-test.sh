#!/usr/bin/env bash
#
# Upgrade test: builds a site with a 2.x release, then replaces the plugin files with this checkout the way a plugin
# update does. The site must store and show the same, load its admin screens, and log no notices from the plugin.
#
# Usage: npm run test:upgrade -- <2.x tag>, for example 2.3.1. Needs Docker, like the other wp-env suites, and a built
# checkout (composer install, npm run build). The site runs on http://localhost:8712; the snapshots and a diff of any
# change end up in artifacts/upgrade/.

set -euo pipefail

from="${1:-}"
if [ -z "$from" ]; then
	echo 'Usage: npm run test:upgrade -- <2.x tag>' >&2
	exit 2
fi

root="$(cd "$(dirname "$0")/.." && pwd)"
work="$root/artifacts/upgrade"
plugin="$work/related-posts-for-wp"
scripts='/var/www/html/wp-content/rp4wp-upgrade'
url='http://localhost:8712'

wp_env() {
	"$root/node_modules/.bin/wp-env" --config="$root/.wp-env.upgrade.json" "$@"
}

# WP-CLI on the test site, with only what WP-CLI printed.
wp() {
	wp_env run cli wp "$@" 2>/dev/null | tr -d '\r'
}

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

# What the site stores and shows: the snapshot script, and the front end of a few posts.
snapshot() {
	local dir="$work/$1"
	mkdir -p "$dir"

	wp eval-file "$scripts/snapshot.php" > "$dir/site.txt"

	for slug in espresso-at-home latte-art growing-tomatoes mountain-huts; do
		curl -sfL "$url/?name=$slug" > "$dir/front-$slug.html" || fail "the front end of $slug did not load"
		grep -q "class='rp4wp-related-posts'" "$dir/front-$slug.html" || fail "the front end of $slug shows no related posts"
	done
}

# An admin screen must load for an administrator and, when given, show a text.
check_admin() {
	local response status
	response="$(curl -s -w '\n%{http_code}' -H "Cookie: $cookie" "$url$1")"
	status="${response##*$'\n'}"

	[ "$status" = 200 ] || fail "$1 answered $status"
	if [ -n "${2:-}" ] && ! grep -qF -- "$2" <<< "$response"; then
		fail "$1 does not show: $2"
	fi
}

echo "== Building a site with ${from}"
# Empty the plugin folder but keep it: a running site mounts this folder, and a new one would not be seen.
mkdir -p "$plugin"
find "$plugin" -mindepth 1 -delete
rm -rf "$work/before" "$work/after" "$work/changes.diff"
git -C "$root" archive "$from" | tar -x -C "$plugin"

wp_env start
wp_env reset all

wp eval-file "$scripts/content.php"
wp plugin activate related-posts-for-wp
wp eval-file "$scripts/seed.php"
before="$(wp plugin get related-posts-for-wp --field=version)"
snapshot before

echo "== Updating ${before} to this checkout"
# Only the notices of the new code count.
wp_env run cli bash -c ': > wp-content/debug.log' > /dev/null 2>&1
rsync -a --delete --delete-excluded --exclude-from="$root/.distignore" "$root/" "$plugin/"
after="$(wp plugin get related-posts-for-wp --field=version)"
snapshot after

echo "== Comparing the site before and after"
if ! diff -ru "$work/before" "$work/after" > "$work/changes.diff"; then
	cat "$work/changes.diff"
	fail "the site changed after the update from ${before} to ${after}; see artifacts/upgrade/changes.diff"
fi

echo "== Checking the admin"
cookie="$(wp eval 'echo "wordpress_" . COOKIEHASH . "=" . rawurlencode( wp_generate_auth_cookie( 1, time() + HOUR_IN_SECONDS, "auth" ) ) . "; wordpress_logged_in_" . COOKIEHASH . "=" . rawurlencode( wp_generate_auth_cookie( 1, time() + HOUR_IN_SECONDS, "logged_in" ) );')"
latte="$(wp post list --post_type=post --name=latte-art --field=ID)"

# No redirect to the installation wizard: the update must not start it again.
check_admin '/wp-admin/'
check_admin '/wp-admin/plugins.php' 'related-posts-for-wp'
check_admin '/wp-admin/options-general.php?page=rp4wp' 'value="You might also like"'
check_admin "/wp-admin/post.php?post=${latte}&action=edit" 'rp4wp_metabox_related_posts'

echo "== Checking the debug log"
log="$(wp_env run cli bash -c 'cat wp-content/debug.log 2> /dev/null || true' 2> /dev/null | tr -d '\r')"
if grep -F 'related-posts-for-wp' <<< "$log"; then
	fail 'the plugin logged the errors or notices above'
fi

wp_env stop
echo "PASS: the update from ${before} to ${after} keeps the site as it was."
