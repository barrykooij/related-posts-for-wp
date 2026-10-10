# Quality harness

Measures how good the related posts are, so every change to the way they are found can be compared with the algorithm before it. It is Phase 0 of the related posts quality plan in the project repository. The free plugin holds the harness; premium runs the same harness with its own algorithm.

## Run it

```bash
npm run quality:prepare
npm run env:test:start
npm run test:quality
```

- `quality:prepare` downloads the public corpora once into `artifacts/quality/raw/`, builds them into `artifacts/quality/corpora/`, and copies the private corpora from the project repository when it sits next to this plugin.
- `test:quality` runs the suite inside wp-env and prints `artifacts/quality/results/summary.md`, with the difference to the recorded baseline in brackets. It takes about a minute.
- `test:quality:record` records the results as the new baseline in `tests/Quality/baseline/`. Record only when the algorithm is meant to change, and commit the baseline with that change.
- `test:quality:gate` fails when a corpus is not better than its baseline: precision at 3 must go up, coverage must not drop by more than a point, and under 1 percent of the stored words may be stop words.
- Premium runs the same commands from its own folder, on the corpora the free plugin built. Premium also has `test:quality:extras`, which measures its extras in about ten minutes and writes `artifacts/quality/results/extras.md`: freshness on publish (the posts published one by one, with precision over the oldest third), the fallback of force fill against random picks, and a maximum of links to a post.

Environment variables, passed with `bash -c` inside wp-env:

| Variable | What it does |
|---|---|
| `RP4WP_QUALITY_ONLY` | Comma-separated corpora to run, for example `golden,blog` |
| `RP4WP_QUALITY_SCALE` | Posts in the scale test; `0` skips it. Default 50,000 |
| `RP4WP_QUALITY_CORPORA` | Another corpora folder |
| `RP4WP_QUALITY_NO_INTL` | `1` runs the tokenizer as on a server without intl: no NFKC, and pairs of characters for Chinese and Japanese |

The GitHub workflow `quality.yml` runs the suite on pushes that change the algorithm and on demand, and nightly once it is on the default branch. The summary is on the run's summary page.

## Corpora

| Name | Posts | Related means | Locale | Source and licence |
|---|---|---|---|---|
| `golden` | 40 | Same topic | `en_US` | The golden master corpus of the integration tests |
| `bbc` | 2,225 | Same topic, 5 topics | `en_US` | BBC News, Greene and Cunningham (ICML 2006); BBC copyright, for research |
| `gnad` | 1,998 | Same topic, 9 topics, 222 each | `de_DE` | 10kGNAD German news (Block 2019); CC BY-NC-SA 4.0 |
| `livedoor` | 999 | Same topic, 9 topics, 111 each | `ja` | livedoor news corpus (RONDHUIT); CC BY-ND 2.1 JP |
| `blog` | 86 | Picked by hand | `en_US` | The author's blog; private, kept in the project repository |
| `blog-nl` | 86 | Picked by hand | `nl_NL` | The same posts on a site with a Dutch locale: content and locale differ |

- The topic of a post is never given to the plugin: posts are imported without categories, so the test measures what the content says.
- 10kGNAD has no titles, so the first sentence of an article becomes its title.
- Samples are deterministic: per topic, posts are taken in the order of the CRC32 of their ID.
- The public data is downloaded for testing, checked against pinned checksums, and never committed or shipped.
- The stop-word leak is measured against stopwords-iso (MIT), for the language of the corpus.

## Metrics

All metrics look at the first 3 related posts, the default amount, unless named otherwise.

| Metric | Meaning |
|---|---|
| P@3, P@5 | Related posts that share the topic, or that were picked by hand, divided by 3 (5), or by the number of picked posts when fewer were picked. A missing related post counts as a miss |
| Random P@3 | What a random pick of 3 posts scores, for comparison |
| Success@3 | Posts with at least one good related post in the first 3 |
| Full lists | Posts that get 3 related posts |
| Coverage | Posts that are the related post of at least one other post |
| Hub share, max inbound | The share of all links that point to the 1 percent most linked posts, and the most links one post gets |
| Symmetry | Links from A to B where B also links to A |
| Stop-word leak | Stored words that are stop words of the corpus language |
| Title share | Stored words that also appear in the title |
| Index and related ms per post | Time to cache the words of a post, and to find its related posts. Statistics of the tables are not refreshed inside the test's transaction, so these are rough; the scale test is the reference for speed |
| Scale | The related posts of 25 random posts, timed, on 50,000 synthetic posts whose words follow Zipf's law, with analyzed tables |

## Adding an algorithm or a corpus

- An algorithm is an adapter that implements `Algorithm`; when the code that finds related posts changes, its adapter changes in the same commit.
- A corpus is a `<name>.json` and a JSON lines file in the corpora folder; see `scripts/quality/build-corpora.php`, which also turns a WordPress export into a corpus.
- Hand labels of a corpus live in `labels/<label set>.json` next to the corpora folder, as `{ "posts": { "<post slug>": { "good": [ "<slug>", ... ] } } }`.
