# Repair and intervention ledger

This is a maintainer-authored record, not complete model tool telemetry. Command logs are retained where named; shorter error excerpts below were copied from the observed tool output.

## Baseline authoring

- Initial test source had an incorrectly escaped quote in SQL-looking test data; PHP parse error, exit 255, retained `01-test-authoring-error.log`. Changed test fixture construction to json_encode; no application behavior or acceptance change.
- `01-baseline-initial-failure.log`: test support/source did not exist yet, expected nonzero before implementation.
- First complete gate: static analysis and all PHP behavior tests passed; real HTTP module failed with `ModuleNotFoundError: No module named 'http.client'; 'http' is not a package`. Renamed the application test script from http.py to front_controller.py to stop shadowing Python's standard module. No test removed.
- Composer lock refresh initially could not reach Packagist under the network sandbox; retried through the normal tool permission mechanism. Lock metadata changed for ext-pdo_sqlite; package versions/references remained unchanged.
- Application-owned context was deliberately completed for the synthetic scenario; optional concerns remain absent. Shared transport-error cache headers became private no-store and the corresponding starter assertions were updated to enforce that stronger declared policy. No framework-owned code or installed checker changed.

## Search and source upgrade

- The frozen search test first failed with expected 200 / actual 400; retained log and `search-red` tag precede implementation. Repair adds one typed query field and an explicit literal substring predicate; package lock unchanged.
- The source upgrade changes only the framework package. The initial complete gate passes static checks and pre-existing behavior, then fails Contract 18's real bootstrap-failure test: expected fixed generic JSON, observed empty body. `upgrade-red` remains a failed checkpoint.
- Added the explicit outer catch and narrowly reconciled application context; no skeleton regeneration or suppression. First repair gate proved missing-fixture generic behavior but the effective-log-path assertion failed: macOS `/var` versus `/private/var` aliases. Canonicalized the test's owned temporary root with Path.resolve; retained `07-upgrade-instrumentation-failure` as failed evidence. Required settings/assertions remain unchanged.
- Fresh review's first attempt reported an account usage limit; the user requested continuation and the same reviewer resumed. No substantive source hint or answer was supplied. Its elapsed wall time includes this interruption, so it is not active labor time.
