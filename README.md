# run-as-root/german-compound-splitter

Pure-PHP splitter for German compound words (`Autobahnraststätte` → `Autobahn` + `Raststätte`).

It is a port of [charsplit-fst](https://pypi.org/project/charsplit-fst/) (Rust port of
[CharSplit](https://github.com/dtuggener/CharSplit) by Don Tuggener). Only the compound splitting API is
implemented. The ngram probability data is read directly from the original `.fst` files, so results
(including scores) are identical to the original library.

## Requirements

- PHP >= 8.2, `ext-mbstring`
- No native extensions besides mbstring. About 20 MB of RAM for the data, loaded once per `Splitter` instance.

## Usage

```php
use RunAsRoot\GermanCompoundSplitter\Splitter;

$splitter = new Splitter(); // reuse this instance, e.g. for a whole reindex run

foreach ($splitter->split('Autobahnraststätte') as $candidate) {
    echo $candidate->score, ' ', $candidate->part1, ' | ', $candidate->part2, PHP_EOL;
}
// 0.79458724506313 Autobahn | Raststätte
// -0.71432908878768 Auto | Bahnraststätte
// ...

$best = $splitter->splitBest('Autobahnraststätte', minScore: 0.5);
$best?->part1; // "Autobahn"
$best?->part2; // "Raststätte"
```

### `Splitter::split(string $word): list<SplitResult>`

Same behaviour as `Splitter().split_compound(word)` of the original, a list of candidates sorted by score
(highest first), every part title-cased:

- a word containing `-` is split at the last hyphen, one result with score `1.0`;
- a word that cannot be split (shorter than 6 characters) returns `[(0.0, Word, Word)]`;
- there is no recursion: only a two-part split is produced.

### `Splitter::splitBest(string $word, float $minScore = 0.0): ?SplitResult`

Convenience wrapper: the top candidate, or `null` if the word cannot be split or the top score is below
`$minScore`.

## How it works

`score = prefixProb(part2) - minInfixProb(part2...) + suffixProb(part1)` for every split position
(Fugen-S removed from both parts before the prefix/suffix lookups). The three probability tables are
`data/{suffix,prefix,infix}.fst`, maps in the format of the Rust [`fst`](https://crates.io/crates/fst) crate
(version 3), decoded by `Fst\FstMap`.

## Tests

```bash
vendor/bin/phpunit -c src/run-as-root/german-compound-splitter/phpunit.xml.dist
```

`Test/Unit/_files/parity.json` holds reference output generated with the original Python package;
`fst_sample.json` holds keys/values dumped by the Rust `fst` crate.

## License and attribution

MIT. The `data/*.fst` files and the algorithm come from charsplit-fst 0.1.4 (MIT OR Apache-2.0, see
`LICENSE-charsplit-fst-*`), which is based on CharSplit by Don Tuggener.
