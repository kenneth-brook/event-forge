# ARCHITECTURE.md

## Overview
Event Forge is a modular PHP/MySQL event management system intended for rapid deployment on standard PHP hosting. It provides public event/calendar views, an admin management interface, recurring event support, uploads, categories, and a first-run installer.

The project is designed around practical deployment constraints:
- standard PHP hosting
- minimal dependencies
- straightforward setup
- maintainable server-rendered/admin-driven structure

## System Goals
- easy installation
- simple administration
- reusable across multiple client sites
- scalable enough for productization
- flexible enough for future modules

## High-Level Components

### 1. Installer
Purpose:
- detect whether Event Forge is installed
- collect DB credentials
- write or validate configuration
- create database tables
- bootstrap first admin user

Responsibilities:
- first-run detection
- environment readiness checks
- DB setup
- initial system bootstrap

### 2. Database Layer
Purpose:
- provide centralized database connectivity
- support consistent query execution and schema evolution

Responsibilities:
- connection management
- query safety
- migration/version support
- shared access for admin/public systems

### 3. Public Event Delivery
Purpose:
- provide public-facing event/calendar data and UI rendering

Responsibilities:
- fetch published events
- filter hidden/unpublished/canceled states as needed
- support FullCalendar data consumption
- support branded presentation

Likely surfaces:
- JSON/API-style endpoints
- public pages/templates
- modal data support

### 4. Admin Interface
Purpose:
- provide event creation, editing, management, and settings control

Responsibilities:
- CRUD for events
- category management
- upload management
- recurring event controls
- site/system settings
- display/visibility settings

### 5. Recurring Event Engine
Purpose:
- support recurring events through parent/child relationships

Responsibilities:
- define recurring series rules
- generate child instances
- update inherited children
- preserve independent children
- support cancellations and exceptions

Design note:
This is one of the most sensitive parts of the system. Changes here should be minimal, deliberate, and well-documented.

### 6. Assets / Uploads
Purpose:
- support event-related media and documents

Responsibilities:
- image uploads
- PDF uploads
- path/reference storage
- basic validation and safe usage

### 7. Category / Branding Layer
Purpose:
- give admins organizational and visual control over event display

Responsibilities:
- category definitions
- category colors/font colors
- display grouping or filtering support
- branding flexibility

### 8. Settings / Configuration
Purpose:
- allow lightweight system customization without code edits

Responsibilities:
- feature toggles
- branding controls
- date/time display preferences
- past-event visibility behavior

## Architectural Style
Event Forge follows a practical modular-PHP architecture:
- separated by responsibility
- no heavy framework dependency
- procedural or lightweight structured PHP where appropriate
- JavaScript used only where it adds direct frontend value

The project is intentionally biased toward:
- easier deployment
- simpler maintenance
- predictable behavior

## Design Principles
- build for real hosting, not perfect hosting
- favor boring reliability over clever complexity
- support incremental growth
- document sensitive logic
- keep public/admin boundaries clear
- isolate recurring-event logic carefully
- treat installer and migrations as first-class parts of the product

## Recommended Directory Intent
This section should be updated to match the actual repo structure.

Example pattern:

- `/admin/`  
  admin UI, CRUD flows, settings, auth-related pages

- `/api/`  
  public or internal endpoints for event/calendar data

- `/includes/`  
  shared PHP includes such as DB access, utilities, auth helpers, recurring logic, installer helpers

- `/assets/`  
  CSS, JS, images, static resources

- `/uploads/`  
  event-uploaded files like images and PDFs

- `/docs/`  
  project documentation for architecture, schema, and AI context

## Data Flow Overview

### Public Flow
1. public page loads
2. frontend calendar requests event data
3. backend filters published/eligible events
4. frontend renders views and event modal details

### Admin Flow
1. admin authenticates
2. admin creates/edits event
3. backend validates and stores event data
4. recurring logic runs if event belongs to a series
5. public-facing output reflects allowed/published changes

### Installer Flow
1. system checks for installation state
2. installer gathers DB/config data
3. schema is created
4. first admin is created
5. normal app flow becomes available

## Sensitive Areas
These parts of the system should be changed carefully:
- recurring event inheritance logic
- cancellation/exception handling
- installer bootstrap logic
- schema migrations
- file upload handling
- public filtering of published/canceled/past events

## Future Growth Areas
- API standardization for external integrations
- travel/tourism feed support
- map integration
- widgets/modules
- import/export tooling
- role/permission expansion
- analytics or event usage insights
- multi-site packaging improvements

## Documentation Rule
Whenever a major structural decision changes, update this file so the repo does not become a haunted house with moving walls.