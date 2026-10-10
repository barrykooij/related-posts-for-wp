<?php
/**
 * Build the corpora of the quality harness from the downloaded data (see fetch-corpora.sh) and from WordPress exports.
 *
 * Each corpus becomes two files in the output folder: `<name>.jsonl`, one post per line, and `<name>.json`, what the
 * corpus is (language, locale, labels, source, licence). The harness in tests/Quality/ imports them.
 *
 * Usage:
 *   php scripts/quality/build-corpora.php [--raw=artifacts/quality/raw] [--out=artifacts/quality/corpora]
 *   php scripts/quality/build-corpora.php --wxr=<export.xml> --name=blog --out=<folder> [--locale=en_US]
 *       [--variant=blog-nl:nl_NL]
 *
 * Plain PHP 8.0, no WordPress. Samples are deterministic: within each label, posts are taken in the order of the
 * CRC32 of their ID.
 *
 * @package RelatedPostsForWP
 */

// phpcs:disable -- A command line tool outside WordPress; not linted with the plugin.

$root    = dirname( __DIR__, 2 );
$options = getopt( '', [ 'raw:', 'out:', 'wxr:', 'name:', 'locale:', 'variant:', 'title:' ] );
$raw     = $options['raw'] ?? $root . '/artifacts/quality/raw';
$out     = $options['out'] ?? $root . '/artifacts/quality/corpora';

if ( ! is_dir( $out ) && ! mkdir( $out, 0777, true ) ) {
	fwrite( STDERR, "Cannot create {$out}\n" );
	exit( 1 );
}

if ( isset( $options['wxr'] ) ) {
	build_wxr( (string) $options['wxr'], $out, (string) ( $options['name'] ?? 'blog' ), (string) ( $options['locale'] ?? 'en_US' ), isset( $options['variant'] ) ? (array) $options['variant'] : [], (string) ( $options['title'] ?? '' ) );
	exit( 0 );
}

build_golden( $root, $out );

if ( is_dir( $raw . '/bbc/bbc' ) ) {
	build_bbc( $raw . '/bbc/bbc', $out );
}

if ( is_file( $raw . '/gnad/articles.csv' ) ) {
	build_gnad( $raw . '/gnad/articles.csv', $out, 222 );
}

if ( is_dir( $raw . '/livedoor/text' ) ) {
	build_livedoor( $raw . '/livedoor/text', $out, 111 );
}

if ( is_file( $raw . '/stopwords/stopwords-iso.json' ) ) {
	copy( $raw . '/stopwords/stopwords-iso.json', $out . '/stopwords-iso.json' );
	echo "stopwords-iso.json: copied\n";
}

/**
 * The golden master corpus of the integration tests: 40 posts in 4 categories. The category is the label, so it is
 * not given to the plugin; the tags are.
 */
function build_golden( string $root, string $out ): void {
	require_once $root . '/tests/Integration/GoldenMaster/Corpus.php';

	$posts = ( new ReflectionClassConstant( 'LV2\WordPress\RelatedPostsForWP\Tests\Integration\GoldenMaster\Corpus', 'POSTS' ) )->getValue();
	$rows  = [];

	foreach ( $posts as $index => [ $slug, $title, $content, $category, $tags ] ) {
		$rows[] = row( $slug, $slug, $title, $content, gmdate( 'Y-m-d H:i:s', gmmktime( 9, 0, 0, 1, 1 + $index, 2024 ) ), $category, [], $tags );
	}

	write_corpus(
		$out,
		[
			'name'     => 'golden',
			'title'    => 'Golden master corpus (40 posts, 4 topics)',
			'language' => 'en',
			'locale'   => 'en_US',
			'labels'   => 'topic',
			'held_out' => true,
			'source'   => 'tests/Integration/GoldenMaster/Corpus.php',
			'licence'  => 'Part of the plugin (GPL-2.0-or-later)',
			'sample'   => 'all',
		],
		$rows
	);
}

/**
 * BBC News: 2,225 articles in 5 topics. The first line of each file is the title.
 */
function build_bbc( string $dir, string $out ): void {
	$rows = [];

	foreach ( glob( $dir . '/*', GLOB_ONLYDIR ) as $topic_dir ) {
		$topic = basename( $topic_dir );

		foreach ( glob( $topic_dir . '/*.txt' ) as $file ) {
			$text = utf8( (string) file_get_contents( $file ) );
			$text = str_replace( "\r\n", "\n", $text );
			$nl   = strpos( $text, "\n" );

			$title   = trim( false === $nl ? $text : substr( $text, 0, $nl ) );
			$content = trim( false === $nl ? '' : substr( $text, $nl + 1 ) );
			$id      = 'bbc-' . $topic . '-' . basename( $file, '.txt' );

			$rows[] = row( $id, $id, $title, paragraphs( $content ), '', $topic );
		}
	}

	usort( $rows, static fn( $a, $b ) => strcmp( $a['id'], $b['id'] ) );
	dated( $rows, '2005-01-01 08:00:00' );

	write_corpus(
		$out,
		[
			'name'     => 'bbc',
			'title'    => 'BBC News (2,225 articles, 5 topics)',
			'language' => 'en',
			'locale'   => 'en_US',
			'labels'   => 'topic',
			'held_out' => true,
			'source'   => 'http://mlg.ucd.ie/datasets/bbc.html',
			'citation' => 'D. Greene and P. Cunningham. Practical Solutions to the Problem of Diagonal Dominance in Kernel Document Clustering. ICML 2006.',
			'licence'  => 'The articles are copyright of the BBC; the dataset is offered for research. Downloaded for testing, never redistributed.',
			'sample'   => 'all',
		],
		$rows
	);
}

/**
 * 10kGNAD: German news, `label;text` per line, without titles. The first sentence becomes the title.
 */
function build_gnad( string $file, string $out, int $per_label ): void {
	$handle = fopen( $file, 'r' );
	$rows   = [];
	$line   = 0;

	while ( false !== ( $fields = fgetcsv( $handle, 0, ';', "'" ) ) ) {
		++$line;
		if ( count( $fields ) < 2 ) {
			continue;
		}

		[ $title, $content ] = first_sentence( utf8( (string) $fields[1] ) );
		$id                  = sprintf( 'gnad-%05d', $line );

		$rows[] = row( $id, $id, $title, $content, '', trim( (string) $fields[0] ) );
	}

	fclose( $handle );

	$rows = balanced( $rows, $per_label );
	dated( $rows, '2016-01-01 08:00:00' );

	write_corpus(
		$out,
		[
			'name'     => 'gnad',
			'title'    => sprintf( '10kGNAD German news (%d per topic, 9 topics)', $per_label ),
			'language' => 'de',
			'locale'   => 'de_DE',
			'labels'   => 'topic',
			'held_out' => true,
			'source'   => 'https://tblock.github.io/10kGNAD/',
			'citation' => 'T. Block. Ten Thousand German News Articles Dataset, 2019.',
			'licence'  => 'CC BY-NC-SA 4.0. Downloaded for testing, never redistributed.',
			'sample'   => "{$per_label} per topic, by CRC32 of the ID; the first sentence of the article is the title",
		],
		$rows
	);
}

/**
 * livedoor news: one file per article: URL, date, title, then the body.
 */
function build_livedoor( string $dir, string $out, int $per_label ): void {
	$rows = [];

	foreach ( glob( $dir . '/*', GLOB_ONLYDIR ) as $category_dir ) {
		$category = basename( $category_dir );

		foreach ( glob( $category_dir . '/' . $category . '-*.txt' ) as $file ) {
			$lines = preg_split( '/\R/u', utf8( (string) file_get_contents( $file ) ) );
			if ( count( $lines ) < 4 ) {
				continue;
			}

			$date   = date_create( trim( $lines[1] ) );
			$id     = 'livedoor-' . basename( $file, '.txt' );
			$rows[] = row( $id, $id, trim( $lines[2] ), paragraphs( implode( "\n", array_slice( $lines, 3 ) ) ), false === $date ? '' : $date->format( 'Y-m-d H:i:s' ), $category );
		}
	}

	usort( $rows, static fn( $a, $b ) => strcmp( $a['id'], $b['id'] ) );
	$rows = balanced( $rows, $per_label );

	write_corpus(
		$out,
		[
			'name'     => 'livedoor',
			'title'    => sprintf( 'livedoor news, Japanese (%d per topic, 9 topics)', $per_label ),
			'language' => 'ja',
			'locale'   => 'ja',
			'labels'   => 'topic',
			'held_out' => true,
			'source'   => 'https://www.rondhuit.com/download.html#ldcc',
			'citation' => 'livedoor news corpus, RONDHUIT Co., Ltd.',
			'licence'  => 'CC BY-ND 2.1 JP. Downloaded for testing, never redistributed.',
			'sample'   => "{$per_label} per topic, by CRC32 of the ID",
		],
		$rows
	);
}

/**
 * A WordPress export (WXR): its published posts with their categories and tags. Nothing else is kept: no authors, no
 * comments, no meta. The labels come from a separate file (`labels/<name>.json`).
 *
 * @param string[] $variants More corpora on the same posts with another site locale, as `name:locale`.
 */
function build_wxr( string $file, string $out, string $name, string $locale, array $variants, string $title ): void {
	$xml = simplexml_load_file( $file, 'SimpleXMLElement', LIBXML_NOCDATA );
	if ( false === $xml ) {
		fwrite( STDERR, "Cannot read {$file}\n" );
		exit( 1 );
	}

	$ns       = $xml->getNamespaces( true );
	$channel  = $xml->channel;
	$site_url = rtrim( (string) $channel->children( $ns['wp'] )->base_blog_url, '/' );
	$rows     = [];
	$pretty   = 0;

	foreach ( $channel->item as $item ) {
		$wp = $item->children( $ns['wp'] );
		if ( 'post' !== (string) $wp->post_type || 'publish' !== (string) $wp->status ) {
			continue;
		}

		$categories = [];
		$tags       = [];
		foreach ( $item->category as $term ) {
			if ( 'category' === (string) $term['domain'] ) {
				$categories[] = (string) $term;
			} elseif ( 'post_tag' === (string) $term['domain'] ) {
				$tags[] = (string) $term;
			}
		}

		$slug = (string) $wp->post_name;
		if ( rtrim( (string) $item->link, '/' ) === $site_url . '/' . $slug ) {
			++$pretty;
		}

		$rows[] = row(
			$slug,
			$slug,
			(string) $item->title,
			(string) $item->children( $ns['content'] )->encoded,
			(string) $wp->post_date,
			null,
			$categories,
			$tags,
			(string) $item->children( $ns['excerpt'] )->encoded
		);
	}

	usort( $rows, static fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) ?: strcmp( $a['id'], $b['id'] ) );

	$meta = [
		'name'                => $name,
		'title'               => '' !== $title ? $title : sprintf( 'WordPress export of %s (%d posts)', $site_url, count( $rows ) ),
		'language'            => strtolower( substr( (string) $channel->language, 0, 2 ) ),
		'locale'              => $locale,
		'labels'              => 'hand',
		'label_set'           => $name,
		'held_out'            => false,
		'source'              => basename( $file ),
		'licence'             => 'The site owner\'s own posts',
		'sample'              => 'all published posts',
		'site_url'            => $site_url,
		'permalink_structure' => $pretty === count( $rows ) ? '/%postname%/' : null,
	];

	write_corpus( $out, $meta, $rows );

	foreach ( $variants as $variant ) {
		[ $variant_name, $variant_locale ] = array_pad( explode( ':', (string) $variant, 2 ), 2, $locale );

		$variant_meta           = $meta;
		$variant_meta['name']   = $variant_name;
		$variant_meta['title']  = $meta['title'] . ', with the site locale ' . $variant_locale;
		$variant_meta['locale'] = $variant_locale;
		$variant_meta['data']   = $name . '.jsonl';

		write_meta( $out, $variant_meta, $rows );
	}
}

/**
 * One post of a corpus.
 *
 * @return array<string, mixed>
 */
function row( string $id, string $slug, string $title, string $content, string $date, ?string $label, array $categories = [], array $tags = [], string $excerpt = '' ): array {
	return [
		'id'         => $id,
		'slug'       => $slug,
		'title'      => $title,
		'content'    => $content,
		'excerpt'    => $excerpt,
		'date'       => $date,
		'label'      => $label,
		'categories' => array_values( $categories ),
		'tags'       => array_values( $tags ),
	];
}

/**
 * Text as UTF-8; files that are not are read as Windows-1252.
 */
function utf8( string $text ): string {
	return mb_check_encoding( $text, 'UTF-8' ) ? $text : mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
}

/**
 * Paragraphs separated by blank lines become `<p>` elements, as the block editor stores them.
 */
function paragraphs( string $text ): string {
	$parts = array_filter( array_map( 'trim', preg_split( '/\n\s*\n|\n/u', $text ) ) );

	return implode( "\n\n", array_map( static fn( $part ) => '<p>' . htmlspecialchars( $part, ENT_NOQUOTES, 'UTF-8' ) . '</p>', $parts ) );
}

/**
 * The first sentence of a text as the title, and the rest as the content.
 *
 * @return array{string, string}
 */
function first_sentence( string $text ): array {
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

	if ( 1 === preg_match( '/^(.{20,180}?[.!?])\s+(\p{Lu}.*)$/su', $text, $matches ) ) {
		return [ $matches[1], paragraphs( $matches[2] ) ];
	}

	$words = explode( ' ', $text );

	return [ implode( ' ', array_slice( $words, 0, 10 ) ), paragraphs( implode( ' ', array_slice( $words, 10 ) ) ) ];
}

/**
 * Give posts without a date one, an hour apart, in their order.
 */
function dated( array &$rows, string $start ): void {
	$time = strtotime( $start . ' UTC' );

	foreach ( $rows as $index => $row ) {
		if ( '' === $row['date'] ) {
			$rows[ $index ]['date'] = gmdate( 'Y-m-d H:i:s', $time + $index * 3600 );
		}
	}
}

/**
 * The same number of posts per label, picked by the CRC32 of their ID, then sorted by ID.
 */
function balanced( array $rows, int $per_label ): array {
	$by_label = [];
	foreach ( $rows as $row ) {
		$by_label[ $row['label'] ][] = $row;
	}

	$picked = [];
	foreach ( $by_label as $label_rows ) {
		usort( $label_rows, static fn( $a, $b ) => ( crc32( $a['id'] ) <=> crc32( $b['id'] ) ) ?: strcmp( $a['id'], $b['id'] ) );
		array_push( $picked, ...array_slice( $label_rows, 0, $per_label ) );
	}

	usort( $picked, static fn( $a, $b ) => strcmp( $a['id'], $b['id'] ) );

	return $picked;
}

/**
 * Write a corpus: its posts and what it is.
 */
function write_corpus( string $out, array $meta, array $rows ): void {
	$lines = '';
	foreach ( $rows as $row ) {
		$lines .= json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) . "\n";
	}

	file_put_contents( $out . '/' . $meta['name'] . '.jsonl', $lines );
	write_meta( $out, $meta, $rows );
}

/**
 * Write what a corpus is, with its counts.
 */
function write_meta( string $out, array $meta, array $rows ): void {
	$counts = [];
	foreach ( $rows as $row ) {
		if ( null !== $row['label'] ) {
			$counts[ $row['label'] ] = ( $counts[ $row['label'] ] ?? 0 ) + 1;
		}
	}
	ksort( $counts );

	$meta += [
		'data'                => $meta['name'] . '.jsonl',
		'site_url'            => null,
		'permalink_structure' => null,
	];
	$meta['posts']        = count( $rows );
	$meta['label_counts'] = [] === $counts ? new stdClass() : $counts;

	file_put_contents( $out . '/' . $meta['name'] . '.json', json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );

	echo "{$meta['name']}: {$meta['posts']} posts" . ( $counts ? ', ' . count( $counts ) . ' labels' : '' ) . "\n";
}
