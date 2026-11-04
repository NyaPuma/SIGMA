# app/Domain/Ticket/ValueObjects

Domain-specific value objects for ticket-related calculations.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- these are "Precision Timers" that measure exactly how long a ticket sat waiting for budget approval.

## The Big Picture

A **Value Object** is like a **measuring tool** -- it wraps a specific calculation into a self-contained, unchangeable package. You create it with some inputs, ask it for a result, and it gives you one. You never modify it after creation; you just use it or throw it away.

In this system, when a ticket needs budget approval, it pauses while someone decides whether to approve the spending. The `BudgetPauseMinutes` Value Object calculates exactly how long that pause lasted -- down to the minute. It's a small, precise tool that encapsulates one calculation so the rest of the code doesn't have to think about edge cases (like "what if the decision came before the request?").

---

## `BudgetPauseMinutes.php`

**File:** `app/Domain/Ticket/ValueObjects/BudgetPauseMinutes.php`
**What it is:** Calculates the elapsed time, in minutes and hours, between when a budget approval was requested and when a decision was made.

**Dependencies (constructor -- all public properties):**
- `?CarbonInterface $requestedAt` -- when the budget request was submitted (nullable)
- `?CarbonInterface $decidedAt` -- when the approval or rejection decision was made (nullable)

**Public methods:**

### `static make(?CarbonInterface $requestedAt, ?CarbonInterface $decidedAt): self`

Named constructor for fluent syntax. Equivalent to `new self(...)`.

### `value(): int`

Returns the total minutes elapsed during the pause.

1. If `$this->requestedAt === null || $this->decidedAt === null`, returns `0` (incomplete data).
2. If `$this->decidedAt->isBefore($this->requestedAt)`, returns `0` (invalid: decision before request).
3. Otherwise returns `(int) $this->requestedAt->diffInMinutes($this->decidedAt)`.

### `toHours(): float`

Converts the value to hours, rounded to 2 decimal places. Calls `$this->value() / 60`.

### `isPending(): bool`

Returns `true` if a request exists but no decision has been made yet (`$requestedAt !== null && $decidedAt === null`).

### `isEmpty(): bool`

Returns `true` if no pause time has been accumulated (`$this->value() === 0`).

### `__toString(): string`

Returns the string representation of `$this->value()`. Implements `Stringable` so it can be used in string contexts like `"Pause: " . $pause`.

### `jsonSerialize(): int`

Returns `$this->value()`. Implements `JsonSerializable` so the object can be directly passed to `json_encode()` or `response()->json()`.

**Who calls it and when:**
- `Ticket::budgetPauseMinutes()` (`app/Models/Ticket.php:215`) delegates to `BudgetPauseMinutes::make($this->budget_requested_at, $this->budget_decided_at)->value()`, so the `budget_pause_minutes` attribute uses the same negative-duration guard as every other caller instead of duplicating the arithmetic.
- Used in tests: `tests/Unit/ValueObjects/BudgetPauseMinutesTest.php`.

---

## Notes for developers / AI

- Immutable (`final readonly`) value object -- once created, it never changes.
- Returns 0 for incomplete or invalid pause data (missing timestamps or decision before request).
- Used to track how long a ticket was paused waiting for budget approval.
- The `make()` static method provides a fluent alternative to `new BudgetPauseMinutes(...)`.
- Implements `Stringable` and `JsonSerializable` for seamless integration with string contexts and JSON responses.
