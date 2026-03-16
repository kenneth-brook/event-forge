# README.md

# Event Forge

![License](https://img.shields.io/badge/license-Proprietary-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![Database](https://img.shields.io/badge/database-MySQL%20%7C%20MariaDB-orange)
![Version](https://img.shields.io/badge/version-0.1.0-green)
![Status](https://img.shields.io/badge/status-active-success)
![Architecture](https://img.shields.io/badge/architecture-portable-informational)

**Event Forge** is a portable event management and display utility built for static and legacy hosting environments.

It provides a self-contained event system with an administrative interface, event API, recurring event support, cancellations, child-instance independence, and embeddable calendar views without requiring Node.js, a framework build chain, or a modern CMS stack.

---

## Purpose

Event Forge was built to solve a practical deployment problem:

- static or lightly dynamic sites still need a real event system
- older hosting environments often cannot support modern application stacks
- clients still expect calendars, recurring events, attachments, and admin controls
- agencies need something repeatable, fast to deploy, and easy to maintain

Event Forge is designed to fill that gap.

---

## Features

### Event Management

- Create, edit, and delete events
- Publish and unpublish events
- Cancel and uncancel events
- Optional image uploads
- Optional PDF attachments
- Event summaries and descriptions

### Recurring Events

- Weekly recurrence
- Monthly nth-weekday recurrence
- Generated child event instances
- Independent child support for one-off overrides
- Parent/child grouping in the admin interface

### Event Display

- Month view
- Week view
- Day view
- List view
- Event detail modal
- Canceled event display styling

### Admin Controls

- Login-protected admin area
- User roles: `admin` and `staff`
- User creation and management
- Admin-only settings area
- Series controls for recurring events

### API

Event data is exposed through:

`/events/api.php`

This allows the system to power:

- calendars
- list views
- scrollers
- event widgets
- future modules

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
```

---

## Installation

### 1. Upload the `events` directory

Deploy the `events` folder to your site root.

Example target path:

`/events`

### 2. Create the database

Create a MySQL or MariaDB database for the installation.

### 3. Import the schema

Import:

`/install/install.sql`

### 4. Configure database access

Edit:

`/events/includes/db.php`

and set the correct database connection values.

### 5. Create the first admin account

Insert an admin user into `event_admin_users` using a password hash generated with PHP:

```php
password_hash('yourpassword', PASSWORD_DEFAULT);
```

### 6. Log in

Access the admin area:

`/events/admin/login.php`

---

## Embedding the Calendar

Add a calendar container to the page consuming the module:

```html
<div id="calendar"></div>
```

Load the assets:

```html
<link rel="stylesheet" href="/events/assets/css/calendar.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script type="module" src="/events/assets/js/calendar.js"></script>
```

The calendar will load event data from:

`/events/api.php`

---

## Upload Locations

Uploaded assets are stored in:

- `/events/uploads/images`
- `/events/uploads/pdfs`

These directories should be writable by the web server.

---

## Roles

Event Forge currently supports two user roles:

- `admin`
- `staff`

### Admin
Can access settings and manage users.

### Staff
Can use the event system without admin-only controls.

---

## Notes

- Recurring parent events generate child instances into the main `events` table.
- Independent children remain associated with the series, but are no longer overwritten by parent regeneration.
- Canceled events remain visible for communication purposes instead of being deleted.

---

## Current Status

Event Forge is currently in active internal development and deployment use.

It is already suitable for real-world client deployment in environments that need:

- rapid event calendar rollout
- recurring event support
- minimal hosting requirements
- a maintainable admin layer

---

## License

This project is currently maintained for internal and client use.

License terms will be finalized as the project evolves.