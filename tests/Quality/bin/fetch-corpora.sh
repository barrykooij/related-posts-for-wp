#!/usr/bin/env bash
#
# Download the public corpora of the quality harness into artifacts/quality/raw/<corpus>/, once.
#
# The files are the standard datasets of the field; they are downloaded for testing and never committed or shipped.
# Each download is checked against a pinned SHA-256, so a changed upstream file is noticed instead of silently changing
# the numbers. Usage: tests/Quality/bin/fetch-corpora.sh [raw directory]

set -euo pipefail

root="$(cd "$(dirname "$0")/../../.." && pwd)"
raw="${1:-$root/artifacts/quality/raw}"

sha256() {
	if command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$1" | cut -d' ' -f1
	else
		shasum -a 256 "$1" | cut -d' ' -f1
	fi
}

# fetch <corpus> <url> <file> <sha256> <extract: none|zip|tgz>
fetch() {
	local name="$1" url="$2" file="$3" expected="$4" extract="$5"
	local dir="$raw/$name"

	if [ -f "$dir/.done" ]; then
		echo "$name: already downloaded"
		return
	fi

	rm -rf "$dir"
	mkdir -p "$dir"
	echo "$name: downloading $url"
	curl -fsSL --retry 3 --max-time 300 -o "$dir/$file" "$url"

	local actual
	actual="$(sha256 "$dir/$file")"
	if [ "$expected" != "-" ] && [ "$actual" != "$expected" ]; then
		echo "$name: checksum mismatch, expected $expected, got $actual" >&2
		exit 1
	fi

	case "$extract" in
		zip) unzip -q "$dir/$file" -d "$dir" ;;
		tgz) tar -xzf "$dir/$file" -C "$dir" ;;
		none) ;;
	esac

	echo "$actual" > "$dir/.done"
	echo "$name: done ($actual)"
}

# BBC News (Greene and Cunningham, ICML 2006): 2,225 articles in 5 topics. The BBC holds the copyright of the articles.
fetch bbc "http://mlg.ucd.ie/files/datasets/bbc-fulltext.zip" bbc-fulltext.zip "${RP4WP_SHA_BBC:--}" zip

# Ten Thousand German News Articles (10kGNAD, Block 2019): 10,273 articles in 9 topics. CC BY-NC-SA 4.0.
fetch gnad "https://raw.githubusercontent.com/tblock/10kGNAD/master/articles.csv" articles.csv "${RP4WP_SHA_GNAD:--}" none

# livedoor news corpus (RONDHUIT): 7,367 Japanese articles in 9 topics. CC BY-ND 2.1 JP.
fetch livedoor "https://www.rondhuit.com/download/ldcc-20140209.tar.gz" ldcc-20140209.tar.gz "${RP4WP_SHA_LIVEDOOR:--}" tgz
