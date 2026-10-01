## ADDED Requirements

### Requirement: Table state model

Tables SHALL carry a `state` of `free`, `seated`, `ordered`, or `needs_bill`, separate from the `active`/`archived` row lifecycle. Allowed transitions SHALL be: `free → seated → ordered → needs_bill → free`, any state reset to `free`, and `needs_bill → ordered`. The domain SHALL expose each state's valid next states, and no caller SHALL re-implement the transition rules.

#### Scenario: Forward transition is allowed

- **WHEN** a table moves from `seated` to `ordered`
- **THEN** the transition is accepted

#### Scenario: Backward transition outside the graph is rejected

- **WHEN** a table moves from `ordered` directly to `seated`
- **THEN** the transition is rejected as invalid

#### Scenario: Any state resets to free

- **WHEN** a table in `ordered` is reset
- **THEN** it moves to `free`

#### Scenario: needs_bill returns to ordered

- **WHEN** a table in `needs_bill` is moved back to `ordered`
- **THEN** the transition is accepted

#### Scenario: Next states are domain-derived

- **WHEN** the UI requests the next states for a table in `needs_bill`
- **THEN** the API returns `free` and `ordered` from the domain model, not from UI code

### Requirement: Table management REST API

The plugin SHALL expose `GET /smooth/v1/tables`, `POST /smooth/v1/tables` (create `{label, seats}`), `POST /smooth/v1/tables/<id>/state`, and `DELETE /smooth/v1/tables/<id>` (archive). Every route SHALL require the `manage_options` capability. Each table in a response SHALL include its id, label, seats, state, `next_states`, and `qr_url`.

#### Scenario: List returns tables with next states and QR URLs

- **WHEN** an authorized user requests `GET /tables`
- **THEN** each returned table includes its `next_states` and `qr_url`

#### Scenario: Create validates input

- **WHEN** `POST /tables` receives a missing or empty `label`, or invalid `seats`
- **THEN** the API responds `400`

#### Scenario: Duplicate label is a conflict

- **WHEN** `POST /tables` receives a label that already exists on an active table
- **THEN** the API responds `409`

#### Scenario: Unknown table is not found

- **WHEN** a state change or delete targets an id with no active table
- **THEN** the API responds `404`

#### Scenario: Invalid state transition is rejected

- **WHEN** `POST /tables/<id>/state` requests a transition not in the state graph
- **THEN** the API responds `400`

#### Scenario: Delete archives, does not destroy

- **WHEN** `DELETE /tables/<id>` runs
- **THEN** the table's `status` becomes `archived` and it no longer appears in the active list

#### Scenario: Unauthorized access is denied

- **WHEN** a user without `manage_options` calls any tables route
- **THEN** the request is denied

### Requirement: Free QR cards and no sessions

Free SHALL provide printable QR cards linking to the menu with an optional display-only table label. Scanning a Free QR SHALL open the menu only: it SHALL NOT create a table session or tag any order as a session order. `smooth_table_sessions` and the Pro session path SHALL remain untouched.

#### Scenario: Free QR opens the menu without a session

- **WHEN** a diner opens a Free table's QR link
- **THEN** the menu is requested and no `smooth_table_sessions` row is created

#### Scenario: Free orders are not session orders

- **WHEN** a diner places an order after a Free QR scan
- **THEN** the order carries no session id

#### Scenario: Table cards print readably

- **WHEN** an owner selects tables and prints from the admin screen
- **THEN** the output shows two cards per A4 row with a large, legible table label and a QR code

### Requirement: QR menu URL filter

The QR link target SHALL default to `home_url('/menu/')` and SHALL be overridable through the `smooth_qr_menu_url` filter. The `?table=` value SHALL be a display-only label; Free SHALL NOT add a rewrite rule or read the parameter on the menu.

#### Scenario: Default URL

- **WHEN** no filter is registered
- **THEN** `qr_url` targets `home_url('/menu/')` with the table label appended for display

#### Scenario: Filter overrides the target

- **WHEN** a site registers `smooth_qr_menu_url` returning a custom URL
- **THEN** every table's `qr_url` uses that URL

### Requirement: Table storage and migration

`smooth_tables` SHALL be created by a version-guarded, idempotent, additive migration with a `status` lifecycle column and a `state` column defaulting to `free`. Activation SHALL NOT seed any tables. `smooth_table_sessions` SHALL NOT be created or modified by this change.

#### Scenario: Migration creates the table once

- **WHEN** activation runs the migration
- **THEN** `smooth_tables` exists with `status` and `state`, and a rerun at the same version is a no-op

#### Scenario: No session table is created

- **WHEN** the migration runs
- **THEN** `smooth_table_sessions` is not created or altered

#### Scenario: No seeding

- **WHEN** the plugin is activated on a fresh site
- **THEN** the tables list is empty until the owner creates a table
