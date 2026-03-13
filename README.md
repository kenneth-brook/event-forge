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
- Publish/unpublish events
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

```text
/events/api.php