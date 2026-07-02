(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }

    callback();
  }

  function appendQuery(url, params) {
    var parsed = new URL(url, window.location.origin);

    Object.keys(params).forEach(function (key) {
      var value = params[key];

      if (value !== null && value !== undefined && String(value).trim() !== '') {
        parsed.searchParams.set(key, String(value));
      }
    });

    return parsed.href;
  }

  function buildSiblingEndpointUrl(sourceUrl, filename) {
    try {
      var parsed = new URL(sourceUrl || window.location.href, window.location.origin);
      var parts = parsed.pathname.split('/');

      parts.pop();
      parts.push(filename);

      parsed.pathname = parts.join('/');
      parsed.search = '';

      return parsed.href;
    } catch (error) {
      return filename;
    }
  }

  function getSafeHexColor(value, fallback) {
    if (typeof value !== 'string') {
      return fallback;
    }

    var trimmed = value.trim();

    return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(trimmed)
      ? trimmed
      : fallback;
  }

  function getReleaseChannelLabel(value) {
    return value === 'test-candidate'
      ? ' Test Candidate'
      : '';
  }

  function ensureDisplayFooter(container) {
    var footer = container.querySelector('[data-display-footer]');
    var key = container.querySelector('[data-display-category-key]');
    var powered = container.querySelector('[data-display-powered-by]');

    if (!footer) {
      footer = document.createElement('footer');
      footer.className = 'eventforge-display-footer';
      footer.setAttribute('data-display-footer', '');
      footer.hidden = true;
      container.appendChild(footer);
    }

    if (!key) {
      key = document.createElement('div');
      key.className = 'eventforge-display-category-key';
      key.setAttribute('data-display-category-key', '');
      key.hidden = true;
      footer.appendChild(key);
    }

    if (!powered) {
      powered = document.createElement('div');
      powered.className = 'eventforge-display-powered-by';
      powered.setAttribute('data-display-powered-by', '');
      powered.hidden = true;
      footer.appendChild(powered);
    }

    return {
      footer: footer,
      key: key,
      powered: powered
    };
  }

  function renderPoweredBy(container, meta) {
    var nodes = ensureDisplayFooter(container);
    var version = meta && typeof meta.app_version === 'string'
      ? meta.app_version.trim()
      : '';

    var releaseChannel = meta && typeof meta.release_channel === 'string'
      ? meta.release_channel.trim()
      : '';

    if (version === '') {
      nodes.powered.hidden = true;
      nodes.powered.textContent = '';
      nodes.footer.hidden = nodes.key.hidden;
      return;
    }

    nodes.powered.textContent = 'Powered by Event Forge v' + version + getReleaseChannelLabel(releaseChannel);
    nodes.powered.hidden = false;
    nodes.footer.hidden = false;
  }

  function renderCategoryKey(container, categories) {
    var nodes = ensureDisplayFooter(container);

    nodes.key.replaceChildren();

    if (!Array.isArray(categories) || categories.length === 0) {
      nodes.key.hidden = true;
      nodes.footer.hidden = nodes.powered.hidden;
      return;
    }

    categories.forEach(function (category) {
      var name = typeof category.name === 'string' && category.name.trim() !== ''
        ? category.name.trim()
        : 'Uncategorized';

      var color = getSafeHexColor(category.color, '#cccccc');

      var item = document.createElement('span');
      var swatch = document.createElement('span');
      var label = document.createElement('span');

      item.className = 'eventforge-display-category-key__item';
      swatch.className = 'eventforge-display-category-key__swatch';
      swatch.style.backgroundColor = color;
      label.textContent = name;

      item.appendChild(swatch);
      item.appendChild(label);
      nodes.key.appendChild(item);
    });

    nodes.key.hidden = false;
    nodes.footer.hidden = false;
  }

  function loadCategoryKey(container, source) {
    if (container._eventforgeCategoryKeyLoaded) {
      return;
    }

    container._eventforgeCategoryKeyLoaded = true;

    fetch(buildSiblingEndpointUrl(source, 'categories.php'), {
      headers: {
        Accept: 'application/json'
      }
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Category key request failed.');
        }

        return response.json();
      })
      .then(function (categories) {
        renderCategoryKey(container, categories);
      })
      .catch(function (error) {
        console.error('Event Forge category key failed to load.', error);
      });
  }

  function getSafeUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return '';
    }

    try {
      var parsed = new URL(value, window.location.origin);

      if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return '';
      }

      return parsed.href;
    } catch (error) {
      return '';
    }
  }

  function applyThemeVariables(themeVars, scope) {
    if (!themeVars || typeof themeVars !== 'object' || !scope) {
      return;
    }

    Object.keys(themeVars).forEach(function (key) {
      var value = themeVars[key];

      if (typeof value === 'string' && value.trim() !== '') {
        scope.style.setProperty(key, value);
      }
    });
  }

  function parseEventDate(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return null;
    }

    var date = new Date(value.replace(' ', 'T'));

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
    var start = parseEventDate(event.start);
    var end = parseEventDate(event.end);

    if (!start) {
      return '';
    }

    var dateOptions = {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    };

    var timeOptions = {
      hour: 'numeric',
      minute: '2-digit'
    };

    var startDate = start.toLocaleDateString(undefined, dateOptions);

    if (event.allDay) {
      return startDate + ' • All day';
    }

    var startTime = start.toLocaleTimeString(undefined, timeOptions);

    if (!end || end.getTime() <= start.getTime()) {
      return startDate + ' • ' + startTime;
    }

    var endTime = end.toLocaleTimeString(undefined, timeOptions);

    if (sameDate(start, end)) {
      return startDate + ' • ' + startTime + ' - ' + endTime;
    }

    var endDate = end.toLocaleDateString(undefined, dateOptions);

    return startDate + ' • ' + startTime + ' through ' + endDate + ' • ' + endTime;
  }

  function createEventItem(event) {
    var props = event.extendedProps || {};
    var isCanceled = !!props.isCanceled;
    var viewUrl = getSafeUrl(event.viewUrl || props.viewUrl || event.url || '');

    var item = document.createElement('article');
    item.className = 'eventforge-upcoming-events__item';

    if (isCanceled) {
      item.classList.add('is-canceled');
    }

    if (props.categoryColor) {
      item.style.setProperty('--ef-upcoming-category-color', props.categoryColor);
    }

    var title = document.createElement('h3');
    title.className = 'eventforge-upcoming-events__title';
    title.textContent = event.title || 'Untitled Event';
    item.appendChild(title);

    var date = document.createElement('div');
    date.className = 'eventforge-upcoming-events__date';
    date.textContent = formatEventDateTime(event);

    if (date.textContent !== '') {
      item.appendChild(date);
    }

    if (props.location) {
      var location = document.createElement('div');
      location.className = 'eventforge-upcoming-events__location';
      location.textContent = props.location;
      item.appendChild(location);
    }

    if (isCanceled) {
      var canceled = document.createElement('div');
      canceled.className = 'eventforge-upcoming-events__canceled';
      canceled.textContent = 'Canceled';
      item.appendChild(canceled);
    }

    if (viewUrl !== '') {
      var link = document.createElement('a');
      link.className = 'eventforge-upcoming-events__link';
      link.href = viewUrl;
      link.textContent = 'View Details';
      item.appendChild(link);
    }

    return item;
  }

  function getBooleanAttribute(element, name, defaultValue) {
    var value = element.getAttribute(name);

    if (value === null) {
      return defaultValue;
    }

    return ['1', 'true', 'yes', 'on'].indexOf(value.toLowerCase()) !== -1;
  }

  function getNumberAttribute(element, name, defaultValue, min, max) {
    var value = Number.parseFloat(element.getAttribute(name));

    if (!Number.isFinite(value)) {
      return defaultValue;
    }

    return Math.min(max, Math.max(min, value));
  }

  function getIntegerAttribute(element, name, defaultValue, min, max) {
    var value = Number.parseInt(element.getAttribute(name), 10);

    if (!Number.isFinite(value)) {
      return defaultValue;
    }

    return Math.min(max, Math.max(min, value));
  }

  function clearScroller(listEl) {
    if (!listEl || !listEl._eventforgeScrollerTimer) {
      return;
    }

    window.clearInterval(listEl._eventforgeScrollerTimer);
    listEl._eventforgeScrollerTimer = null;
  }

  function startUpcomingScroller(container, listEl, eventCount) {
    var autoScroll = getBooleanAttribute(container, 'data-auto-scroll', true);
    var minScrollItems = getIntegerAttribute(container, 'data-min-scroll-items', 3, 2, 25);
    var pauseOnHover = getBooleanAttribute(container, 'data-pause-on-hover', true);
    var scrollSpeed = getNumberAttribute(container, 'data-scroll-speed', 1, 0.25, 10);

    clearScroller(listEl);

    if (!autoScroll || eventCount < minScrollItems) {
      return;
    }

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    listEl.classList.add('is-auto-scrolling');
    container.classList.add('is-auto-scrolling');

    listEl._eventforgePaused = false;

    function setPaused(nextPaused) {
      listEl._eventforgePaused = nextPaused;
      listEl.classList.toggle('is-scroll-paused', nextPaused);
      container.classList.toggle('is-scroll-paused', nextPaused);
    }

    if (!listEl._eventforgeHoverBound) {
      [listEl, container].forEach(function (target) {
        target.addEventListener('mouseenter', function () {
          setPaused(true);
        });

        target.addEventListener('mouseover', function () {
          setPaused(true);
        });

        target.addEventListener('mouseleave', function () {
          setPaused(false);
        });

        target.addEventListener('focusin', function () {
          setPaused(true);
        });

        target.addEventListener('focusout', function () {
          setPaused(false);
        });
      });

      listEl._eventforgeHoverBound = true;
    }

    if (pauseOnHover && (listEl.matches(':hover') || container.matches(':hover'))) {
      setPaused(true);
    }

    listEl._eventforgeScrollerTimer = window.setInterval(function () {
      var canScroll = listEl.scrollHeight > listEl.clientHeight + 2;
      var hoverPaused = pauseOnHover
        && (listEl._eventforgePaused || listEl.matches(':hover') || container.matches(':hover'));

      if (hoverPaused || !canScroll) {
        return;
      }

      listEl.scrollTop = listEl.scrollTop + scrollSpeed;

      if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 2) {
        listEl.scrollTop = 0;
      }
    }, 24);
  }

  function refreshUpcomingScroller(container) {
    var listEl = container.querySelector('[data-upcoming-list]');
    var eventCount = Number.parseInt(container.getAttribute('data-rendered-event-count') || '0', 10);

    if (!listEl || listEl.hidden || !eventCount) {
      return;
    }

    window.setTimeout(function () {
      startUpcomingScroller(container, listEl, eventCount);
    }, 80);
  }

  function refreshAllUpcomingScrollers() {
    document.querySelectorAll('[data-eventforge-upcoming]').forEach(function (container) {
      refreshUpcomingScroller(container);
    });
  }

  function renderEmpty(container, statusEl, listEl, message) {
    if (listEl) {
      clearScroller(listEl);
      listEl.hidden = true;
      listEl.innerHTML = '';
    }

    container.setAttribute('data-rendered-event-count', '0');

    if (statusEl) {
      statusEl.hidden = false;
      statusEl.textContent = message || 'No upcoming events are currently scheduled.';
      statusEl.classList.remove('is-error');
    }
  }

  function renderError(container, statusEl) {
    var listEl = container ? container.querySelector('[data-upcoming-list]') : null;

    if (listEl) {
      clearScroller(listEl);
      listEl.hidden = true;
      listEl.innerHTML = '';
    }

    if (container) {
      container.setAttribute('data-rendered-event-count', '0');
    }

    if (!statusEl) {
      return;
    }

    statusEl.hidden = false;
    statusEl.textContent = 'Upcoming events could not be loaded.';
    statusEl.classList.add('is-error');
  }

  function renderEvents(container, data) {
    var statusEl = container.querySelector('[data-upcoming-status]');
    var listEl = container.querySelector('[data-upcoming-list]');
    var emptyMessage = container.getAttribute('data-empty-message') || 'No upcoming events are currently scheduled.';
    var events = data && Array.isArray(data.events) ? data.events : [];

    if (data && data.meta && data.meta.calendar_theme_css_variables) {
      applyThemeVariables(data.meta.calendar_theme_css_variables, container);
    }

    if (data && data.meta) {
      renderPoweredBy(container, data.meta);
    }

    if (!listEl) {
      return;
    }

    clearScroller(listEl);
    listEl.innerHTML = '';
    container.setAttribute('data-rendered-event-count', String(events.length));

    if (events.length === 0) {
      renderEmpty(container, statusEl, listEl, emptyMessage);
      return;
    }

    var fragment = document.createDocumentFragment();

    events.forEach(function (event) {
      fragment.appendChild(createEventItem(event));
    });

    listEl.appendChild(fragment);
    listEl.hidden = false;

    if (statusEl) {
      statusEl.hidden = true;
      statusEl.textContent = '';
      statusEl.classList.remove('is-error');
    }

    startUpcomingScroller(container, listEl, events.length);
  }

  function initUpcomingEvents(container) {
    var source = container.getAttribute('data-source') || '/event-forge/events/api.php';
    var display = container.getAttribute('data-display') || 'upcoming';
    var limit = container.getAttribute('data-limit') || '10';
    var includeCanceled = container.getAttribute('data-include-canceled') === 'true';
    var statusEl = container.querySelector('[data-upcoming-status]');

    if (statusEl) {
      statusEl.hidden = false;
      statusEl.textContent = 'Loading upcoming events...';
      statusEl.classList.remove('is-error');
    }

    loadCategoryKey(container, source);

    fetch(appendQuery(source, {
      display: display,
      limit: limit,
      include_canceled: includeCanceled ? '1' : ''
    }), {
      headers: {
        Accept: 'application/json'
      }
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Upcoming events request failed.');
        }

        return response.json();
      })
      .then(function (data) {
        renderEvents(container, data);
      })
      .catch(function (error) {
        console.error(error);
        renderError(container, statusEl);
      });
  }

  ready(function () {
    document.querySelectorAll('[data-eventforge-upcoming]').forEach(function (container) {
      initUpcomingEvents(container);
    });

    window.addEventListener('resize', refreshAllUpcomingScrollers);
    window.addEventListener('eventforge:display-refresh', refreshAllUpcomingScrollers);
  });
})();
