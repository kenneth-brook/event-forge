# Event Forge

![License](https://img.shields.io/badge/license-Proprietary-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![Database](https://img.shields.io/badge/database-MySQL%20%7C%20MariaDB-orange)
![Version](https://img.shields.io/badge/version-0.1.0-green)
![Status](https://img.shields.io/badge/status-active-success)
![Architecture](https://img.shields.io/badge/architecture-modular-informational)

**Event Forge** is a portable event management and display engine designed for static and legacy hosting environments.

It provides a modular system for managing and displaying events without requiring modern JavaScript frameworks, build tools, or complex infrastructure.

Event Forge was created to run in environments where Node-based applications or large CMS platforms are impractical, such as shared hosting, tourism portals, municipal websites, retirement communities, kiosk systems, and static marketing sites.

---

## Philosophy

Event Forge follows a few guiding principles:

- **Portable** - deployable on standard PHP hosting
- **Modular** - new functionality added through modules
- **API-first** - event data is accessible via JSON endpoints
- **Framework-free** - no Node.js or build pipeline required
- **Replaceable UI** - frontend components can be swapped without altering the backend

---

## Features

### Event Management

- Create, edit, and delete events
- Publish and unpublish events
- Optional image uploads
- Optional PDF attachments
- Event descriptions and summaries

### Event Display

- Interactive calendar
- Month view
- Week view
- Day view
- List view
- Event detail modal
- Optional attachment display

### API

Event Forge exposes event data through a JSON API:

`/events/api.php`

This API allows events to be consumed by:

- calendars
- event lists
- scrollers
- kiosk displays
- external systems

---

## System Requirements

Minimum requirements:

- PHP **7.4+**
- MySQL or MariaDB
- Standard shared hosting
- JavaScript enabled in the browser

No Node.js, npm, or build tools are required.

---

## Installation

### 1. Upload Event Forge

Upload the events module to your site.

Example structure:

`/events`

Directory layout:

```text
events
├── admin
├── assets
├── includes
├── uploads
└── api.php
```

### 2. Create Database Tables

Import the provided SQL schema or create the required tables manually.

Required tables:

- `events`
- `event_admin_users`

### 3. Configure Database

Edit the database configuration file:

`/events/includes/db.php`

Update it with your database credentials.

### 4. Create an Admin User

Generate a password hash using PHP:

```php
password_hash('yourpassword', PASSWORD_DEFAULT);
```

Insert the user into the `event_admin_users` table.

### 5. Access the Admin Interface

`/events/admin/login.php`

---

## Embedding the Calendar

Add a calendar container to the page that will consume the module:

```html
<div id="calendar"></div>
```

Load the required assets:

```html
<link rel="stylesheet" href="/events/assets/css/calendar.css">

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script type="module" src="/events/assets/js/calendar.js"></script>
```

The calendar will load data from:

`/events/api.php`

---

## Event Attachments

Events may optionally include:

- images
- PDF documents

These files are uploaded through the admin interface and stored in:

- `/events/uploads/images`
- `/events/uploads/pdfs`

Uploads are optional and events function normally without them.

---

## Project Structure

```text
event-forge
├── modules
│   └── calendar
│       ├── admin
│       ├── assets
│       ├── includes
│       ├── uploads
│       └── api.php
├── README.md
├── ROADMAP.md
└── LICENSE
```

---

## Roadmap

### v0.1

- FullCalendar integration
- Event API
- Admin CRUD
- Image and PDF uploads
- Modal event viewer

### v0.2

- Event categories
- Filtering
- Admin interface improvements
- Account-level settings

### v0.3

- Upcoming events scroller module
- Announcement bar module
- Mapped locations module

### v0.4

- Module auto-loader
- Shared settings panel
- Expanded embed options

### v1.0

- Native Event Forge calendar engine
- Removal of third-party calendar dependency
- Full modular utility platform support

---

## Planned Modules

Event Forge is being designed as a multi-module utility platform.

Planned modules include:

- Calendar
- Upcoming Events Scroller
- Announcement Bar
- Mapped Locations

Additional modules can be added over time while continuing to use the same data and configuration architecture.

---

## Development Notes

The current implementation uses FullCalendar as the presentation layer for rapid deployment and compatibility with static and legacy-hosted environments.

The long-term goal is to replace third-party calendar rendering with a native Event Forge calendar engine while preserving the existing data layer, administrative tooling, and module architecture.

---

## License

This project is currently maintained for internal and client use.

License terms will be finalized as the project evolves.

---

## Contributing

When contributing to Event Forge, prefer changes that preserve:

- portability
- modularity
- framework independence
- compatibility with standard PHP hosting

Avoid introducing infrastructure requirements that would prevent deployment on legacy or shared hosting environments.