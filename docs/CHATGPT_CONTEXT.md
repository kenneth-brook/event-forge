# CHATGPT_CONTEXT.md

## Project
Event Forge

## One-Line Summary
Event Forge is a reusable PHP/MySQL event and calendar system for client websites, designed to be lightweight, installer-based, deployable on standard PHP hosting, and capable of growing into a productized tool.

## Primary Goal
Replace heavier or less flexible event/calendar solutions with a practical, fast-to-deploy system that supports both immediate client needs and future commercial reuse.

## Core Stack
- PHP
- MySQL
- Vanilla JavaScript / ES6
- FullCalendar
- Standard shared/VPS PHP hosting compatibility

## Product Direction
This is not a one-off build. Event Forge is intended to become a reusable utility product under Kennetic Concepts.

Planned or possible expansion areas:
- upcoming events scroller
- announcement bar
- mapped event locations
- event feeds / API endpoints
- tourism and travel-app integration
- modular add-ons

## Development Rules
- Keep solutions practical and compatible with standard PHP hosting
- Avoid unnecessary dependencies
- Avoid modern JS framework requirements unless absolutely justified
- Prefer maintainable, production-minded architecture
- Build for reuse, not one-off hacks
- Keep admin workflows simple for non-technical users
- Prefer full file updates over partial snippets when making code changes
- Respect current structure unless there is a strong reason to refactor

## Functional Requirements
- public calendar/event display
- month, list, week, and day views
- event modal/popup
- image uploads
- PDF uploads
- external links
- category support
- recurring event support
- admin management interface
- settings/branding controls
- installer-based setup

## Recurring Event Model
Recurring events use a parent/child relationship model.

Requirements:
- parent events can generate child instances
- child events may inherit updates from parent
- child events can be marked independent
- independent children stop inheriting parent changes
- cancellations must be handled cleanly
- exceptions should be manageable without breaking the recurring chain

## Architecture Priorities
1. Simplicity
2. Deployability
3. Maintainability
4. Reusability
5. Productization readiness

## Hosting Priorities
Event Forge should work on:
- normal shared hosting
- standard VPS PHP hosting
- environments without Node.js
- environments where simplicity of deployment matters

## Business Constraints
- built by a solo developer
- needs to be fast to deploy
- needs to be easy to support
- must be robust enough to eventually sell
- should avoid overly complex infrastructure

## Preferred Response Style for AI Assistance
When helping with this project:
- prioritize practical implementation
- prefer incremental, shippable work
- return full files when requested
- explain architectural tradeoffs briefly and clearly
- do not recommend large-framework rewrites unless specifically asked
- preserve compatibility with current PHP/MySQL approach

## Current Focus
Event Forge v0.4 stabilization and feature completion.

## Repo
https://github.com/kenneth-brook/event-forge