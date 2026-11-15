# World Flora Online (WFO) in TaxaImporter

TaxaImporter can validate plant names against [World Flora Online](https://www.worldfloraonline.org/) in two ways:

1. **GNVerifier + WFO checklist** (GNames data source `196`) — same fuzzy matching stack as GBIF pipelines, different reference dataset.
2. **WFO Match REST API** — direct HTTP calls to `matching_rest.php` (equivalent to the GraphQL `taxonNameMatch` operation).

This document is independent of any single migration project (e.g. Biodiversidad Virtual).

## Processors

| Processor | Path | Backend |
|-----------|------|---------|
| GN WFO | `GNames\GNWFOSpeciesProcessor` | `vendor/bin/gnverifier -s 196` |
| GBIF (default GN) | `GNames\GNSpeciesProcessor` | `gnverifier -s 11` (override with `source` option) |
| WFO REST | `WFO\WFOSpeciesProcessor` | `https://list.worldfloraonline.org/matching_rest.php` |

Constants: `GnVerifierCli::DATASOURCE_WFO` (`196`), `GnVerifierCli::DATASOURCE_GBIF` (`11`).

### GNVerifier (WFO)

```yaml
remote:
  processor: GNames\GNWFOSpeciesProcessor
```

Equivalent to `GNames\GNSpeciesProcessor` with `options.source: '196'`. Output columns include `gn_species_id`, `gn_species_name`, plus `wfo_id` and `wfo_data_source`.

Requires the `gnames/gnverifier` binary from Composer (`vendor/bin/gnverifier`).

### WFO Match API

```yaml
remote:
  processor: WFO\WFOSpeciesProcessor
  options:
    fuzzy_names: 2
    fuzzy_authors: 1
    accept_single_candidate: true
    check_homonyms: false
    check_rank: false
    rate_limit_seconds: 0.1
```

See [WFO name matching REST documentation](https://list.worldfloraonline.org/matching_rest.php) for parameter semantics.

### TLS / Guzzle

`WfoMatchClient` uses `WfoHttpConfig` for TLS:

| Variable | Effect |
|----------|--------|
| `TAXAIMPORTER_WFO_CA_BUNDLE` | Path to a CA bundle (recommended on WSL/Linux) |
| `TAXAIMPORTER_WFO_INSECURE_SSL=1` | Disable certificate verification (development only) |

If neither is set, common system CA paths are tried before falling back to Guzzle defaults. See `.env.example`.

Local gnverifier access is centralized in `GnVerifierCli`; `LocalBinGNSpeciesVerifier` delegates to it.

### Bare-name / missing `classificationRanks` (GNVerifier)

Some WFO matches return several candidates. The first row can be a **bare-name** stub (`recordId` starting with `bare-name-`) that has a binomial `matchedCanonicalFull` but **no** `classificationRanks`. Older logic always took `results[0]`, so `GNSpeciesParser` treated the name as non-species and appended ` spec.` (e.g. *Eryngium tenue* → *Eryngium tenue spec.*).

TaxaImporter now:

1. **`GnVerifierCli::selectBestResult()`** — prefers a non-bare-name row that includes `classificationRanks`.
2. **`GNSpeciesParser`** — if ranks are still missing, uses `matchedCardinality` (≥ 2 → species level; 1 → ` indet.`).

Unit coverage: `tests/GNames/GnVerifierResultSelectionTest.php` with fixture `tests/fixtures/gnverifier_eryngium_tenue_wfo.json`.

Example pipeline files:

- `config/pipelines/WFO-GNVerifier-example.yml`
- `config/pipelines/WFO-Match-CSV-example.yml`

## Testing

### Unit tests (offline, default in CI)

```bash
composer install
composer test
```

### Live validation (Andalusia taxon list)

Fixture: `tests/fixtures/wfo_andalusia_taxa.txt` (one scientific name per line; `#` comments allowed).

```bash
export TAXAIMPORTER_LIVE_WFO=1
export TAXAIMPORTER_WFO_CA_BUNDLE=/etc/ssl/certs/ca-certificates.crt
# Or, dev-only: export TAXAIMPORTER_WFO_INSECURE_SSL=1
export TAXAIMPORTER_WFO_MAX_TAXA=100
composer test:live
```

GitHub Actions: push/PR runs **unit** only; enable live via repository variable `TAXAIMPORTER_RUN_LIVE_WFO=1` or **workflow_dispatch** with “Run live WFO tests…”.

### GN vs REST discrepancy CSV

```bash
php bin/wfo-gn-rest-discrepancy-report.php tests/fixtures/wfo_andalusia_taxa.txt data/output/wfo_gn_rest_discrepancies.csv
```

Columns include `status`: `agree_id`, `agree_name`, `disagree`, `gn_only`, `rest_only`, `neither`.

Benchmark both backends:

```bash
php bin/wfo-validate-taxa.php tests/fixtures/wfo_andalusia_taxa.txt --backend=both
```

Live tests expect at least **85%** match rate on the fixture (tunable in `WfoLiveValidationTest`).

## Choosing a backend

| Criterion | GNVerifier + 196 | WFO REST |
|-----------|------------------|----------|
| Dependencies | Local binary | HTTP only |
| Fuzzy / typos | gnverifier | `fuzzy_names`, `fuzzy_authors` |
| Debug detail | Compact JSON | `narrative`, `candidates` in API |
| Rate limits | Local CPU | Be polite; use `rate_limit_seconds` |

For large batches, prefer GNVerifier if the binary and data source index are already installed; use REST when you need WFO-native matching flags without gnverifier.
