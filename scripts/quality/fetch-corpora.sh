#!/usr/bin/env bash
#
# Download the public data of the quality harness into artifacts/quality/raw/<name>/, once.
#
# The corpora are the standard datasets of the field; they are downloaded for testing and never committed or shipped.
# Each download is checked against a pinned SHA-256, so a changed upstream file is noticed instead of silently changing
# the numbers. Build the corpora from them with scripts/quality/build-corpora.php.
#
# Usage: scripts/quality/fetch-corpora.sh [raw directory]

set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
raw="${1:-$root/artifacts/quality/raw}"

sha256() {
	if command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$1" | cut -d' ' -f1
	else
		shasum -a 256 "$1" | cut -d' ' -f1
	fi
}

# fetch <name> <url> <file> <sha256> <extract: none|zip|tgz>
fetch() {
	local name="$1" url="$2" file="$3" expected="$4" extract="$5"
	local dir="$raw/$name"

	if [ -f "$dir/.done" ] && [ "$(cat "$dir/.done")" = "$expected" ]; then
		echo "$name: already downloaded"
		return
	fi

	rm -rf "$dir"
	mkdir -p "$dir"
	echo "$name: downloading $url"
	curl -fsSL --retry 3 --max-time 300 -o "$dir/$file" "$url"

	local actual
	actual="$(sha256 "$dir/$file")"
	if [ "$actual" != "$expected" ]; then
		echo "$name: checksum mismatch, expected $expected, got $actual" >&2
		exit 1
	fi

	case "$extract" in
		zip) unzip -q "$dir/$file" -d "$dir" ;;
		tgz) tar -xzf "$dir/$file" -C "$dir" ;;
		none) ;;
	esac

	echo "$actual" > "$dir/.done"
	echo "$name: done"
}

# BBC News (Greene and Cunningham, ICML 2006): 2,225 English articles in 5 topics. The BBC holds the copyright of the
# articles; the dataset is offered for research.
fetch bbc "http://mlg.ucd.ie/files/datasets/bbc-fulltext.zip" bbc-fulltext.zip \
	a351031a62325b3d7ff13146eeb035c22feca8290913b5183d1ff3be35109495 zip

# Ten Thousand German News Articles Dataset (10kGNAD, Block 2019): 10,273 articles in 9 topics. CC BY-NC-SA 4.0.
fetch gnad "https://raw.githubusercontent.com/tblock/10kGNAD/master/articles.csv" articles.csv \
	6db4629272e2064f7ce8cc099052cb7fb3a7bfc7e77971791cd951cef496f141 none

# livedoor news corpus (RONDHUIT): 7,367 Japanese articles in 9 topics. CC BY-ND 2.1 JP.
fetch livedoor "https://www.rondhuit.com/download/ldcc-20140209.tar.gz" ldcc-20140209.tar.gz \
	b17606ed8c670013a3809100a9e6104701baab62cc019abc262111bd2acf1063 tgz

# stopwords-iso (MIT): the reference stop word lists the harness measures the stop-word leak against.
fetch stopwords "https://raw.githubusercontent.com/stopwords-iso/stopwords-iso/master/stopwords-iso.json" stopwords-iso.json \
	337af4d57d5fa1fecc2ffcae532e9b9f05db51c74ff18d3dde9572185a3cdae4 none
