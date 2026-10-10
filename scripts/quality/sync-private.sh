#!/usr/bin/env bash
#
# Copy the private corpora and labels of the quality harness into artifacts/quality/, when they are there.
#
# The hand-labelled blog corpus is the site owner's own posts, so it is kept in the private project repository
# (quality/corpora and quality/labels), next to this plugin in the workspace. RP4WP_QUALITY_PRIVATE points elsewhere.
# Without it, the harness runs on the public corpora only.

set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
private="${RP4WP_QUALITY_PRIVATE:-$root/../project/quality}"

if [ ! -d "$private/corpora" ]; then
	echo "No private corpora in $private; the public corpora only."
	exit 0
fi

mkdir -p "$root/artifacts/quality/corpora" "$root/artifacts/quality/labels"
cp "$private"/corpora/*.json "$private"/corpora/*.jsonl "$root/artifacts/quality/corpora/"

if [ -d "$private/labels" ] && compgen -G "$private/labels/*.json" >/dev/null; then
	cp "$private"/labels/*.json "$root/artifacts/quality/labels/"
fi

echo "Private corpora copied from $private."
