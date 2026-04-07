# Event Forge

![License](https://img.shields.io/badge/license-Proprietary-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![Database](https://img.shields.io/badge/database-MySQL%20%7C%20MariaDB-orange)
![Version](https://img.shields.io/badge/version-0.4.1-green)
![Status](https://img.shields.io/badge/status-active-success)
![Architecture](https://img.shields.io/badge/architecture-portable-informational)

**Event Forge** is a portable event management and display utility built for static and legacy hosting environments.

It provides a self-contained event system with an administrative interface, event API, recurring event support, cancellation handling, category-based styling, modal event display, and deployable calendar views without requiring Node.js, a framework build chain, or a modern CMS stack.

---

## Purpose

Event Forge was built to solve a practical deployment problem:

- static or lightly dynamic sites still need a real event system
- older hosting environments often cannot support modern application stacks
- clients still expect calendars, recurring events, attachments, categories, and admin controls
- agencies need something repeatable, fast to deploy, and easy to maintain
- installs need to be updatable without rebuilding the whole deployment by hand

Event Forge is designed to fill that gap.

---

## Current Version

**Current release:** `0.4.1`

Event Forge now includes:

- automated installer flow
- database schema version tracking
- migration-based upgrades for dropped-in file updates
- recurring event management
- cancellation handling
- independent child workflow
- category system with colors
- modal deep-link support
- admin-side event URL tools

---

## Features

### Event Management

- Create, edit, and delete events
- Publish and unpublish events
- Cancel and uncancel events
- Optional image uploads
- Optional PDF attachments
- Event summaries and descriptions
- Event category assignment
- Event slug generation
- Admin event detail view

### Recurring Events

- Weekly recurrence
- Monthly nth-weekday recurrence
- Generated child event instances
- Independent child support for one-off overrides
- Parent/child grouping in the admin interface
- Child inheritance of parent content and category
- Series-aware cancellation handling

### Event Display

- Month view
- Week view
- Day view
- List view
- Event detail modal
- Category color styling
- Category key / legend
- Deep-link event modal support from shared URLs
- Powered-by Event Forge footer with version display
- Canceled event display styling

### Category System

- Create, edit, and delete categories
- Background color selection
- Font color selection
- Active / inactive categories
- Category-based display styling in the calendar
- Category legend output below calendar

### Administrative Controls

- Login-protected admin area
- User roles:
  - `staff`
  - `staff_manager`
  - `admin`
- Staff manager account controls for client-side delegated management
- Admin-only system configuration
- User suspension support
- Role-aware settings display
- Public calendar URL setting for shareable event links

### Deployment / Upgrade Model

- Install-aware bootstrap flow
- Database configuration setup
- Schema version tracking through `eventforge_system`
- App version tracking through `eventforge_system`
- Migration runner for dropped-in file updates
- Legacy install adoption path for older deployments

### API

Event data is exposed through:

`/events/api.php`

This powers:

- calendars
- list views
- scrollers
- event widgets
- future modules

The API also returns install metadata needed by the display layer, including app version.

---

## System Requirements

Minimum requirements:

- PHP **7.4+**
- MySQL or MariaDB
- Standard shared hosting
- JavaScript enabled in the browser

No Node.js, npm, or build pipeline is required.

---

## Project Structure

```text
event-forge/
├── README.md
├── ROADMAP.md
├── LICENSE
├── .gitignore
├── events/
│   ├── api.php
│   ├── categories.php
│   ├── event.php
│   ├── admin/
│   ├── assets/
│   ├── includes/
│   ├── uploads/
│   └── config/
├── install/
│   ├── install.sql
│   ├── seed.sample.sql
│   └── notes.md
├── deploy/
│   └── deployment-checklist.md
└── examples/
    ├── consumer-page-example.php
    └── embed-snippets.md