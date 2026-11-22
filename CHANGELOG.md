# Changelog

All notable changes to this package are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0]

### Added

- A client for every operation of the Agilis Advertising REST API: categories, interests, Offers, Events, event categories, currencies and config, webhook endpoints and webhook events.
- The mode (Test or Live) from the API key, with `environment` filled in from it.
- Multipart uploads from a file path, bytes or a stream.
- Auto-paging for the Offer, Event and webhook event lists.
- Idempotency keys for creating Offers and publishing Events, kept across retries.
- Dry runs, retries with backoff and `Retry-After`, and typed exceptions.
- `webhooks->constructEvent()` to verify webhook signatures.
