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