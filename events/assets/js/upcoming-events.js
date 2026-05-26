(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }

    callback();
  }

  function getSafeUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return '';
    }

    try {
      const parsed = new URL(value, window.location.origin);

      if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return '';
      }

      return parsed.href;
    } catch (error) {
      return '';
    }
  }

  function appendQuery(url, params) {
    const parsed = new URL(url, window.location.origin);

    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined && String(value).trim() !== '') {
        parsed.searchParams.set(key, String(value));
      }
    });

    return parsed.href;
  }

  function applyThemeVariables(themeVars, scope) {
    if (!themeVars || typeof themeVars !== 'object' || !scope) {
      return;
    }

    Object.entries(themeVars).forEach(([key, value]) => {
      if (typeof value === 'string' && value.trim() !== '') {
        scope.style.setProperty(key, value);
      }
    });
  }

  function parseEventDate(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return null;
    }

    const normalized = value.replace(' ', 'T');
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
      return null;
    }

    return date;
  }

  function sameDate(first, second) {
    return first.getFullYear() === second.getFullYear()
      && first.getMonth() === second.getMonth()
      && first.getDate() === second.getDate();
  }

  function formatEventDateTime(event) {
    const start = parseEventDate(event.start);
    const end = parseEventDate(event.end);
    const allDay = !!event.allDay;

    if (!start) {
      return '';
    }

    const dateOptions = {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    };

    const timeOptions = {
      hour: 'numeric',
      minute: '2-digit'
    };

    const startDate = start.toLocaleDateString(undefined, dateOptions);

    if (allDay) {
      return `${startDate} • All day`;
    }

    const startTime = start.toLocaleTimeString(undefined, timeOptions);

    if (!end || end.getTime() <= start.getTime()) {
      return `${startDate} • ${startTime}`;
    }

    const endTime = end.toLocaleTimeString(undefined, timeOptions);

    if (sameDate(start, end)) {
      return `${startDate} • ${startTime} - ${endTime}`;
    }

    const endDate = end.toLocaleDateString(undefined, dateOptions);

    return `${startDate} • ${startTime} through ${endDate} • ${endTime}`;
  }

  function createEventItem(event) {
    const props = event.extendedProps || {};
    const isCanceled = !!props.isCanceled;
    const viewUrl = getSafeUrl(event.viewUrl || props.viewUrl || event.url || '');

    const item = document.createElement('article');
    item.className = 'eventforge-upcoming-events__item';

    if (isCanceled) {
      item.classList.add('is-canceled');
    }

    if (props.categoryColor) {
      item.style.setProperty('--ef-upcoming-category-color', props.categoryColor);
    }

    if (props.categoryFontColor) {
      item.style.setProperty('--ef-upcoming-category-font-color', props.categoryFontColor);
    }

    const title = document.createElement('h3');
    title.className = 'eventforge-upcoming-events__title';
    title.textContent = event.title || 'Untitled Event';

    const date = document.createElement('div');
    date.className = 'eventforge-upcoming-events__date';
    date.textContent = formatEventDateTime(event);

    item.appendChild(title);

    if (date.textContent !== '') {
      item.appendChild(date);
    }

    if (props.location) {
      const location = document.createElement('div');
      location.className = 'eventforge-upcoming-events__location';
      location.textContent = props.location;
      item.appendChild(location);
    }

    if (isCanceled) {
      const canceled = document.createElement('div');
      canceled.className = 'eventforge-upcoming-events__canceled';
      canceled.textContent = 'Canceled';
      item.appendChild(canceled);
    }

    if (viewUrl !== '') {
      const link = document.createElement('a');
      link.className = 'eventforge-upcoming-events__link';
      link.href = viewUrl;
      link.textContent = 'View Details';
      item.appendChild(link);
    }

    return item;
  }

  function renderEmpty(container, statusEl, listEl, message) {
    if (listEl) {
      listEl.hidden = true;
      listEl.innerHTML = '';
    }

    if (statusEl) {
      statusEl.hidden = false;
      statusEl.textContent = message || 'No upcoming events are currently scheduled.';
      statusEl.classList.remove('is-error');
    }
  }

  function renderError(statusEl) {
    if (!statusEl) {
      return;
    }

    statusEl.hidden = false;
    statusEl.textContent = 'Upcoming events could not be loaded.';
    statusEl.classList.add('is-error');
  }

  function renderEvents(container, data) {
    const statusEl = container.querySelector('[data-upcoming-status]');
    const listEl = container.querySelector('[data-upcoming-list]');
    const emptyMessage = container.getAttribute('data-empty-message') || 'No upcoming events are currently scheduled.';

    const events = data && Array.isArray(data.events) ? data.events : [];

    if (data && data.meta && data.meta.calendar_theme_css_variables) {
      applyThemeVariables(data.meta.calendar_theme_css_variables, container);
    }

    if (!listEl) {
      return;
    }

    listEl.innerHTML = '';

    if (events.length === 0) {
      renderEmpty(container, statusEl, listEl, emptyMessage);
      return;
    }

    const fragment = document.createDocumentFragment();

    events.forEach((event) => {
      fragment.appendChild(createEventItem(event));
    });

    listEl.appendChild(fragment);
    listEl.hidden = false;

    if (statusEl) {
      statusEl.hidden = true;
      statusEl.textContent = '';
      statusEl.classList.remove('is-error');
    }
  }

  function initUpcomingEvents(container) {
    const source = container.getAttribute('data-source') || '/event-forge/events/upcoming.php';
    const limit = container.getAttribute('data-limit') || '10';
    const includeCanceled = container.getAttribute('data-include-canceled') === 'true';

    const statusEl = container.querySelector('[data-upcoming-status]');

    if (statusEl) {
      statusEl.hidden = false;
      statusEl.textContent = 'Loading upcoming events...';
      statusEl.classList.remove('is-error');
    }

    const requestUrl = appendQuery(source, {
      limit,
      include_canceled: includeCanceled ? '1' : ''
    });

    fetch(requestUrl, {
      headers: {
        Accept: 'application/json'
      }
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Upcoming events request failed.');
        }

        return response.json();
      })
      .then((data) => {
        renderEvents(container, data);
      })
      .catch((error) => {
        console.error(error);
        renderError(statusEl);
      });
  }

  ready(() => {
    const containers = document.querySelectorAll('[data-eventforge-upcoming]');

    containers.forEach((container) => {
      initUpcomingEvents(container);
    });
  });
})();