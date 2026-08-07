# ADR-004: Compute account-lockout state in SQL, not PHP

**Status:** Accepted
**Recorded:** 2026-08-07 (retrospectively, from the implementation)

## Context

Five consecutive failed logins lock a staff account for fifteen minutes. That
requires answering two questions on every login attempt:

- Is this account locked _right now_?
- If so, until when?

Both are comparisons between a stored timestamp and the current time and there
are two current times available. PHP has one clock, configured by
`date.timezone`. PostgreSQL has another, configured by the server and the
session `TimeZone`. Nothing guarantees they agree.

They routinely don't. On Windows, PHP frequently defaults `date.timezone` to UTC
while PostgreSQL runs in the machine's local zone. During British Summer Time
that is a one-hour disagreement. If PHP compares `time()` against a
`locked_until` value that PostgreSQL wrote in local time, a fifteen-minute
lockout either expires an hour early, silently disabling the throttle, the
security control this code exists to provide or lasts seventy-five minutes,
locking out a legitimate clinician mid-shift.

The failure is also invisible in development if both happen to be configured
alike, and appears only on a differently-configured deployment host.

## Decision

Never compare timestamps across the two systems. Compute all lockout state
inside the query that reads the user, so both sides of every comparison come
from PostgreSQL's clock:

```sql
(locked_until IS NOT NULL AND locked_until >  CURRENT_TIMESTAMP) AS is_locked,
(locked_until IS NOT NULL AND locked_until <= CURRENT_TIMESTAMP) AS lockout_expired,
EXTRACT(EPOCH FROM locked_until)::bigint            AS locked_until_epoch
```

Write the lock the same way `CURRENT_TIMESTAMP + interval` and return the
unlock time as a Unix epoch via `RETURNING`, so PHP receives an unambiguous
instant to format rather than a local-time string it would have to reinterpret.

## Consequences

**Positive**

- **Timezone configuration cannot produce a wrong lockout.** Every comparison
  happens in one clock. The security control behaves identically regardless of
  how the host is configured.
- **The epoch hand-off is timezone-neutral.** An integer instant carries no zone
  assumption, so the display layer formats it in whatever zone it likes without
  risk of double-conversion.
- **Locking and reading back the unlock time is one statement.** `UPDATE ...
RETURNING` avoids a second round trip and the window that would open between
  them.

**Negative**

- **Business logic lives in SQL.** A developer reading `validateLogin()` in PHP
  sees three boolean columns arriving pre-computed; the rule that defines them
  is in the query string, easy to miss and easy to break by editing the SELECT.
- **It is not unit-testable without a database.** The lockout rule can only be
  exercised against real PostgreSQL, which puts the project's most
  security-sensitive logic outside the reach of fast isolated tests.
- **Tied to PostgreSQL.** `EXTRACT(EPOCH FROM ...)`, interval casting from a
  bound parameter, and `RETURNING` are all Postgres-specific. Porting to another
  engine means rewriting this logic, not just the connection string.

## Related decisions

Two adjacent choices in the same function, recorded here rather than as separate
ADRs:

- **The lockout is checked before `password_verify()`.** bcrypt verification is
  deliberately expensive; there is no reason to spend it on an account that
  cannot log in regardless of the answer. This also removes a timing signal.
- **Unknown and inactive accounts return the same generic failure as a wrong
  password**, so the endpoint cannot be used to enumerate valid usernames. The
  failure is still audit-logged, with the attempted username recorded.

## Evidence

- [`code/includes/auth.php:87-97`](../../code/includes/auth.php#L87-L97) - lockout state computed in the SELECT
- [`code/includes/auth.php:99-114`](../../code/includes/auth.php#L99-L114) - generic failure for unknown accounts; lockout checked before bcrypt
- [`code/includes/auth.php:116-119`](../../code/includes/auth.php#L116-L119) - expired lockout resets the attempt budget rather than carrying it over
- [`code/includes/auth.php:124-149`](../../code/includes/auth.php#L124-L149) - lock written and epoch read back in one statement
- [`login.php:39-46`](../../login.php#L39-L46) - epoch formatted for display at the edge
