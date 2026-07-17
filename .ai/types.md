# Type boundary contract

`mixed` is permitted only where external data enters the application. Parse it once into a concrete final readonly value, then use native types.

Canonical factories:

- `Projection::fromDatabaseRow(array<string, mixed>)` for a selected database row.
- `Command::fromJson(string)` for a JSON request body.

Every factory must:

1. Reject missing and unknown fields.
2. Check the runtime type before conversion.
3. Accept only documented canonical representations and ranges.
4. Throw without including the rejected value.
5. Use a private constructor so invalid instances cannot be created.

Do not use scalar casts, `intval`, `floatval`, `boolval`, `strval`, `settype`, inline `@var`, or `assert` as validation. Do not add reflection hydration, a generic collection, or a second parsing style.

`PHT001` enforces this for scalar casts and conversion functions while PHPStan still resolves the operand as `mixed`. Narrowing through an explicit runtime check makes a documented conversion eligible; it does not make an invalid representation valid.

PHPStan `list<T>`, array shapes, and `@template` are static contracts only. They supplement boundary parsing; they never replace it.
