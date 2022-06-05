# Plugin Check

Proof of concept for a WordPress plugin check tool.

For now, this is limited to WP-CLI usage. Eventually, the real plugin checker should probably have a UI as well that allows invoking the checks for a plugin.

## Usage examples

```
wp plugin-check check-plugin hello
```

```
wp plugin-check check-plugin akismet
```

## Architecture

* All checks are implemented as classes that implement the `WordPress\Plugin_Check\Checker\Check` interface.
* Check classes can optionally implement the `WordPress\Plugin_Check\Checker\Preparation` interface if they require any environment preparation before running the check.
* There can also be general environment preparation steps which should be implemented as standalone classes implementing the `WordPress\Plugin_Check\Checker\Preparation` interface only.

## Currently included checks

* `Enqueued_Scripts_Check`
* `PHP_CodeSniffer_Check`
    * currently just checks for `WordPress-Core` ruleset
    * should eventually use a custom ruleset for the plugin checker
