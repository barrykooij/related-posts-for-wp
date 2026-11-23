#!/usr/bin/env bash
#
# Term frequency saturation (the 3.1 plan, phase 5): runs the quality suite once per value of k, without the scale
# test, and compares each with the baseline in artifacts/quality/results/tf-saturation.md.
#
# Usage: bash scripts/quality/tf-variants.sh [plugin folder in wp-env] [algorithm] [plugin root]
# The defaults are the free plugin. RP4WP_TF_KS chooses the values (0 is none); RP4WP_QUALITY_ONLY the corpora.

set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
folder="${1:-related-posts-for-wp}"
algorithm="${2:-free}"
root="${3:-$(cd "$here/../.." && pwd)}"
ks="${RP4WP_TF_KS:-0 5 10 20}"
only="${RP4WP_QUALITY_ONLY:-}"

cd "$root"
for k in $ks; do
	echo "== tf-${k}"
	npx wp-env --config=.wp-env.test.json run cli --env-cwd="wp-content/plugins/${folder}" bash -c "RP4WP_QUALITY_VARIANT=tf-${k} RP4WP_QUALITY_SCALE=0 RP4WP_QUALITY_ONLY=${only} vendor/bin/phpunit -c phpunit-quality.xml.dist --filter QualityTest" > /dev/null
done

php "$here/compare-variants.php" "$root" "$algorithm" > "$root/artifacts/quality/results/tf-saturation.md"
cat "$root/artifacts/quality/results/tf-saturation.md"
