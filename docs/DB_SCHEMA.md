# DB_SCHEMA.md

## Overview
This document describes the database structure for Event Forge.

Goals of the schema:
- support practical event management
- support recurring events
- support categories and branding
- support uploads and public display needs
- remain simple enough for standard hosting and maintenance
- support future migrations/versioned upgrades

## Schema Design Principles
- keep tables focused
- avoid unnecessary normalization where it adds complexity without value
- preserve clarity around recurring relationships
- support easy admin CRUD
- make future migrations manageable

## Core Entities

### 1. Events
Purpose:
Stores event records for both standalone and recurring child events.

Typical responsibilities:
- title and content
- start/end date and time
- location
- summary/description
- media/document references
- published state
- canceled state
- recurrence relationships
- category association

Suggested important columns:
- `id`
- `title`
- `start_datetime`
- `end_datetime`
- `all_day`
- `location`
- `summary`
- `description`
- `image_path`
- `pdf_path`
- `external_url`
- `is_published`
- `is_canceled`
- `category_id`
- `is_recurring_parent`
- `parent_event_id`
- `is_independent`
- `created_at`
- `updated_at`

Notes:
- standalone events have no parent
- recurring parent events define a series
- recurring child events link back to a parent event
- independent children are exceptions that stop inheriting parent updates

### 2. Event Categories
Purpose:
Organize events and provide display styling.

Suggested important columns:
- `id`
- `name`
- `slug` if used
- `color`
- `font_color`
- `created_at`
- `updated_at`

Notes:
- categories may be optional per event
- color/font-color support frontend branding and readability

### 3. Users / Admin Users
Purpose:
Store administrative access accounts for managing the system.

Suggested important columns:
- `id`
- `username`
- `email`
- `password_hash`
- `role` if used
- `is_active`
- `created_at`
- `updated_at`
- `last_login_at` if used

Notes:
- keep auth simple and secure
- if roles are minimal now, design with future expansion in mind

### 4. Settings
Purpose:
Store system-wide configuration without requiring code edits.

Suggested important columns:
- `id` or `setting_key`
- `setting_key`
- `setting_value`
- `autoload` if used
- `updated_at`

Possible uses:
- branding options
- feature toggles
- public display preferences
- hide/show past event behavior
- installer or version-related settings

### 5. Schema Version / Migrations
Purpose:
Track database version so upgrades can be applied safely.

Suggested important columns:
- `id`
- `version`
- `applied_at`

Alternative:
A single-row version table can work if migration tracking is intentionally simple.

Notes:
This table is strongly recommended. It keeps future upgrades from turning into archaeology with SQL dust on it.

## Relationship Model

### Event to Category
- many events can belong to one category
- category is optional unless business rules say otherwise

### Parent Event to Child Events
- one recurring parent event can have many child event instances
- child instances reference parent via `parent_event_id`

### Event Independence
- child events marked `is_independent = 1` no longer fully inherit parent changes
- this supports exceptions without destroying the full recurring model

## Recurring Event Logic Notes
The schema must support:
- recurring parent definition
- generated child instances
- selective cancellation
- selective overrides
- inherited changes where appropriate
- preserved exceptions where needed

Key flags/fields usually involved:
- `is_recurring_parent`
- `parent_event_id`
- `is_independent`
- `is_canceled`

This model should remain clearly documented because recurring-event bugs breed fast and hide well.

## Recommended Indexing
Indexing should reflect real query patterns.

Likely useful indexes:
- `start_datetime`
- `is_published`
- `category_id`
- `parent_event_id`
- combinations used in public event queries

Examples:
- events by published date range
- events by category
- child events by parent
- future public events

Exact indexes should be confirmed against actual query usage.

## Public Query Concerns
Public-facing queries usually need to account for:
- published state
- current/future visibility rules
- canceled status
- recurring child handling
- category joins
- performance on date-based lookups

## Migration Strategy
Recommended approach:
- keep schema creation in installer/bootstrap
- add a version table
- create migration scripts for future upgrades
- never rely on “just manually run this SQL” as the main upgrade plan

## Documentation Rule
Whenever a table or important column changes:
1. update the SQL/schema
2. update this document
3. update architecture notes if behavior changed

If those three things do not happen together, future debugging gets stupid fast.

## Current Effective Schema

This section reflects the current database structure after applying the initial schema plus migrations 1 through 6.

---

## Tables

### 1. `event_admin_users`

Stores admin users for the Event Forge management interface.

#### Columns
| Column | Type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary key |
| `username` | VARCHAR(100) | No |  | Unique username |
| `password_hash` | VARCHAR(255) | No |  | Hashed password |
| `role` | VARCHAR(50) | No | `'staff'` | Admin role |
| `created_at` | DATETIME | No | `CURRENT_TIMESTAMP` | Creation timestamp |
| `is_suspended` | TINYINT(1) | No | `0` | Added in migration 2; allows admin suspension without deletion |

#### Indexes / Constraints
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_event_admin_users_username (username)`

#### Notes
- `is_suspended` was introduced after the initial schema to support disabling admin access while keeping the account record intact.

---

### 2. `events`

Stores all events, including:
- standalone events
- recurring parent events
- recurring child instances
- child exceptions / independent children

#### Columns
| Column | Type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary key |
| `parent_event_id` | INT UNSIGNED | Yes | `NULL` | Self-reference to recurring parent event |
| `title` | VARCHAR(255) | No |  | Event title |
| `slug` | VARCHAR(255) | Yes | `NULL` | Added by migration 5; backfilled in migration 6 |
| `start_datetime` | DATETIME | No |  | Event start |
| `end_datetime` | DATETIME | Yes | `NULL` | Event end |
| `all_day` | TINYINT(1) | No | `0` | All-day flag |
| `location` | VARCHAR(255) | Yes | `NULL` | Event location |
| `summary` | TEXT | Yes | `NULL` | Short summary |
| `description` | MEDIUMTEXT | Yes | `NULL` | Full event description |
| `image_path` | VARCHAR(255) | Yes | `NULL` | Event image asset path |
| `pdf_path` | VARCHAR(255) | Yes | `NULL` | Event PDF asset path |
| `external_url` | VARCHAR(255) | Yes | `NULL` | External link |
| `is_published` | TINYINT(1) | No | `1` | Public visibility flag |
| `is_canceled` | TINYINT(1) | No | `0` | Canceled flag |
| `is_recurring_parent` | TINYINT(1) | No | `0` | Marks a recurring series parent |
| `is_independent_child` | TINYINT(1) | No | `0` | Marks a child event as detached from inherited updates |
| `recurrence_type` | VARCHAR(50) | Yes | `NULL` | Recurrence mode/type |
| `recurrence_interval` | INT UNSIGNED | Yes | `NULL` | Repeat interval |
| `recurrence_days` | VARCHAR(50) | Yes | `NULL` | Days list for recurrence |
| `recurrence_week_of_month` | VARCHAR(20) | Yes | `NULL` | Week-of-month recurrence rule |
| `recurrence_day_of_week` | VARCHAR(20) | Yes | `NULL` | Day-of-week recurrence rule |
| `recurrence_end_date` | DATE | Yes | `NULL` | Date recurrence ends |
| `recurrence_count` | INT UNSIGNED | Yes | `NULL` | Max number of recurrence instances |
| `recurrence_instance_date` | DATE | Yes | `NULL` | Specific generated instance date for child events |
| `created_at` | DATETIME | No | `CURRENT_TIMESTAMP` | Creation timestamp |
| `updated_at` | DATETIME | No | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last update timestamp |
| `category_id` | INT UNSIGNED | Yes | `NULL` | Added in migration 3; links event to category |

#### Indexes
- `PRIMARY KEY (id)`
- `KEY idx_events_parent_event_id (parent_event_id)`
- `KEY idx_events_start_datetime (start_datetime)`
- `KEY idx_events_is_published (is_published)`
- `KEY idx_events_is_canceled (is_canceled)`
- `KEY idx_events_is_recurring_parent (is_recurring_parent)`
- `KEY idx_events_is_independent_child (is_independent_child)`
- `KEY idx_events_recurrence_instance_date (recurrence_instance_date)`
- `KEY idx_events_slug (slug)`
- `KEY idx_events_parent_instance (parent_event_id, recurrence_instance_date)`
- `KEY idx_events_calendar_filter (is_published, is_recurring_parent, start_datetime)`
- `KEY idx_events_category_id (category_id)`

#### Foreign Keys
- `fk_events_parent_event`
  - `parent_event_id` → `events(id)`
  - `ON DELETE CASCADE`
- `fk_events_category`
  - `category_id` → `event_categories(id)`
  - `ON DELETE SET NULL`

#### Notes
- This table is the center of gravity for the whole system, and also the likely source of future gray hair.
- `parent_event_id` is used for recurring child instances.
- `is_recurring_parent` identifies a series-defining event.
- `is_independent_child` allows a generated child event to stop inheriting parent changes.
- `slug` was added after initial rollout and then backfilled for existing events.
- `recurrence_instance_date` is important for identifying specific child instances in a recurring series.
- `category_id` was added later, so older installs depend on migration 3 to reach current shape.

---

### 3. `eventforge_system`

Stores system-wide key/value pairs for application and schema metadata.

#### Columns
| Column | Type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary key |
| `system_key` | VARCHAR(100) | No |  | Unique key name |
| `system_value` | TEXT | Yes | `NULL` | Stored value |
| `updated_at` | DATETIME | No | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last update timestamp |

#### Indexes / Constraints
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_eventforge_system_key (system_key)`

#### Seeded Values
Initial schema seeding inserts:
- `schema_version`
- `app_version`

#### Notes
- This table acts as both lightweight settings storage and migration/version tracking.
- Current migration execution updates `schema_version` after each successful migration and refreshes `app_version` afterward.

---

### 4. `event_categories`

Stores event categories used for organization and frontend display styling.

#### Columns
| Column | Type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | INT UNSIGNED | No | AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(100) | No |  | Category name |
| `slug` | VARCHAR(120) | Yes | `NULL` | Optional slug |
| `color` | VARCHAR(20) | Yes | `NULL` | Category display color |
| `font_color` | VARCHAR(20) | Yes | `NULL` | Added in migration 4 for text/readability control |
| `is_active` | TINYINT(1) | No | `1` | Active/inactive flag |
| `created_at` | DATETIME | No | `CURRENT_TIMESTAMP` | Creation timestamp |
| `updated_at` | DATETIME | No | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Last update timestamp |

#### Indexes / Constraints
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_event_categories_name (name)`

#### Notes
- Created in migration 3.
- `font_color` was added in migration 4 to support better visual contrast and branding control.

---

## Relationship Summary

### `events` → `events`
- Self-referencing relationship through `parent_event_id`
- Used for recurring series parent/child modeling

### `events` → `event_categories`
- Optional many-to-one relationship through `category_id`
- If a category is deleted, event records are preserved and `category_id` is set to `NULL`

---

## Migration Summary

| Version | Change |
|---|---|
| `1` | Initial schema baseline |
| `2` | Added `event_admin_users.is_suspended` |
| `3` | Added `event_categories`; added `events.category_id`, index, and category foreign key |
| `4` | Added `event_categories.font_color` |
| `5` | Added `events.slug` and slug index |
| `6` | Backfilled slug values for existing events |

---

## Current Schema SQL Snapshot

```sql
CREATE TABLE event_admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'staff',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_suspended TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) DEFAULT NULL,
  color VARCHAR(20) DEFAULT NULL,
  font_color VARCHAR(20) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_event_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) DEFAULT NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME DEFAULT NULL,
  all_day TINYINT(1) NOT NULL DEFAULT 0,
  location VARCHAR(255) DEFAULT NULL,
  summary TEXT DEFAULT NULL,
  description MEDIUMTEXT DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  pdf_path VARCHAR(255) DEFAULT NULL,
  external_url VARCHAR(255) DEFAULT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  is_canceled TINYINT(1) NOT NULL DEFAULT 0,
  is_recurring_parent TINYINT(1) NOT NULL DEFAULT 0,
  is_independent_child TINYINT(1) NOT NULL DEFAULT 0,
  recurrence_type VARCHAR(50) DEFAULT NULL,
  recurrence_interval INT UNSIGNED DEFAULT NULL,
  recurrence_days VARCHAR(50) DEFAULT NULL,
  recurrence_week_of_month VARCHAR(20) DEFAULT NULL,
  recurrence_day_of_week VARCHAR(20) DEFAULT NULL,
  recurrence_end_date DATE DEFAULT NULL,
  recurrence_count INT UNSIGNED DEFAULT NULL,
  recurrence_instance_date DATE DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  category_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_events_parent_event_id (parent_event_id),
  KEY idx_events_start_datetime (start_datetime),
  KEY idx_events_is_published (is_published),
  KEY idx_events_is_canceled (is_canceled),
  KEY idx_events_is_recurring_parent (is_recurring_parent),
  KEY idx_events_is_independent_child (is_independent_child),
  KEY idx_events_recurrence_instance_date (recurrence_instance_date),
  KEY idx_events_slug (slug),
  KEY idx_events_parent_instance (parent_event_id, recurrence_instance_date),
  KEY idx_events_calendar_filter (is_published, is_recurring_parent, start_datetime),
  KEY idx_events_category_id (category_id),
  CONSTRAINT fk_events_parent_event
    FOREIGN KEY (parent_event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_events_category
    FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE eventforge_system (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  system_key VARCHAR(100) NOT NULL,
  system_value TEXT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eventforge_system_key (system_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


```md
## Schema Design Notes

- `eventforge_system` currently acts as both a lightweight system settings store and the schema/app version tracker.
- Recurring events are modeled directly in the `events` table using a self-referencing parent/child relationship.
- Child event exceptions are handled through `is_independent_child`.
- Category support was added after the initial schema, so migrations are required for older installs.
- Slug support was also added after initial release and requires backfill for existing records.