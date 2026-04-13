document.addEventListener('DOMContentLoaded', () => {
  const calendarEl = document.getElementById('calendar');

  if (!calendarEl || !window.FullCalendar) {
    console.error('Calendar container or FullCalendar missing.');
    return;
  }

  const modal = ensureEventModal();
  const modalTitle = document.getElementById('modal-title');
  const modalDate = document.getElementById('modal-datetime');

  const modalLocationWrap = document.getElementById('modal-location-wrap');
  const modalLocation = document.getElementById('modal-location');

  const modalImageWrap = document.getElementById('modal-image-wrap');
  const modalImage = document.getElementById('modal-image');

  const modalDescriptionWrap = document.getElementById('modal-description-wrap');
  const modalDescription = document.getElementById('modal-description');

  const modalPdfWrap = document.getElementById('modal-pdf-wrap');
  const modalPdf = document.getElementById('modal-pdf');

  const modalExternalWrap = document.getElementById('modal-external-wrap');
  const modalExternal = document.getElementById('modal-external');

  const modalShareWrap = document.getElementById('modal-share-wrap');
  const modalShareButton = document.getElementById('modal-share-button');
  const modalShareStatus = document.getElementById('modal-share-status');

  const closeBtn = document.querySelector('.event-modal-close');
  const backdrop = document.querySelector('.event-modal-backdrop');

  let activeEventForShare = null;
  let shareStatusTimeout = null;

  const clearShareStatus = () => {
    if (shareStatusTimeout) {
      window.clearTimeout(shareStatusTimeout);
      shareStatusTimeout = null;
    }

    if (modalShareStatus) {
      modalShareStatus.textContent = '';
      modalShareStatus.hidden = true;
      modalShareStatus.classList.remove('is-error');
      modalShareStatus.classList.remove('is-success');
    }
  };

  const setShareStatus = (message, isError = false) => {
    if (!modalShareStatus) {
      return;
    }

    if (shareStatusTimeout) {
      window.clearTimeout(shareStatusTimeout);
    }

    modalShareStatus.textContent = message;
    modalShareStatus.hidden = false;
    modalShareStatus.classList.toggle('is-error', isError);
    modalShareStatus.classList.toggle('is-success', !isError);

    shareStatusTimeout = window.setTimeout(() => {
      clearShareStatus();
    }, 3000);
  };

  const closeModal = () => {
    if (modal) modal.hidden = true;
    if (modalImage) modalImage.removeAttribute('src');
    if (modalPdf) modalPdf.removeAttribute('href');
    if (modalExternal) modalExternal.removeAttribute('href');

    activeEventForShare = null;

    if (modalShareButton) {
      modalShareButton.disabled = false;
    }

    clearShareStatus();
  };

  function buildEventShareUrl(event) {
    if (!event || typeof event.url !== 'string') {
      return '';
    }

    const rawUrl = event.url.trim();

    if (rawUrl === '') {
      return '';
    }

    try {
      const parsed = new URL(rawUrl, window.location.origin);

      if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
        return '';
      }

      return parsed.href;
    } catch (error) {
      return '';
    }
  }

  async function copyTextToClipboard(text) {
    if (typeof text !== 'string' || text.trim() === '') {
      return false;
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch (error) {
        // Fall through to legacy copy.
      }
    }

    const helper = document.createElement('textarea');
    helper.value = text;
    helper.setAttribute('readonly', 'readonly');
    helper.style.position = 'fixed';
    helper.style.top = '-9999px';
    helper.style.left = '-9999px';

    document.body.appendChild(helper);
    helper.focus();
    helper.select();

    let copied = false;

    try {
      copied = document.execCommand('copy');
    } catch (error) {
      copied = false;
    }

    document.body.removeChild(helper);

    return copied;
  }

  async function handleShareClick() {
    if (!activeEventForShare) {
      setShareStatus('Share link unavailable for this event.', true);
      return;
    }

    const shareUrl = buildEventShareUrl(activeEventForShare);

    if (shareUrl === '') {
      setShareStatus('Share link unavailable for this event.', true);
      return;
    }

    const props = activeEventForShare.extendedProps || {};
    const shareData = {
      title: activeEventForShare.title || 'Event',
      text: typeof props.summary === 'string' && props.summary.trim() !== ''
        ? props.summary.trim()
        : activeEventForShare.title || 'Event',
      url: shareUrl
    };

    if (modalShareButton) {
      modalShareButton.disabled = true;
    }

    clearShareStatus();

    if (navigator.share && typeof navigator.share === 'function') {
      try {
        await navigator.share(shareData);

        if (modalShareButton) {
          modalShareButton.disabled = false;
        }

        return;
      } catch (error) {
        if (modalShareButton) {
          modalShareButton.disabled = false;
        }

        if (error && error.name === 'AbortError') {
          return;
        }

        const copiedAfterShareFailure = await copyTextToClipboard(shareUrl);

        if (copiedAfterShareFailure) {
          setShareStatus('Share not available there. Link copied instead.');
        } else {
          setShareStatus('Unable to share automatically. Please copy the event page URL manually.', true);
        }

        return;
      }
    }

    const copied = await copyTextToClipboard(shareUrl);

    if (modalShareButton) {
      modalShareButton.disabled = false;
    }

    if (copied) {
      setShareStatus('Event link copied to clipboard.');
    } else {
      setShareStatus('Unable to copy link automatically. Please copy the event page URL manually.', true);
    }
  }

  function openEventModal(event) {
    const props = event.extendedProps || {};
    const isCanceled = !!props.isCanceled;
    const safeExternalUrl = getSafeExternalUrl(props.externalUrl);
    const safeShareUrl = buildEventShareUrl(event);

    activeEventForShare = event;
    clearShareStatus();

    if (modalTitle) {
      modalTitle.textContent = isCanceled
        ? `${event.title} (CANCELED)`
        : event.title;
    }

    if (modalDate) {
      if (event.start) {
        const hasEnd = !!event.end;
        const isAllDay = !!event.allDay;

        const dateOptions = {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        };

        const timeOptions = {
          hour: 'numeric',
          minute: '2-digit'
        };

        const startDate = event.start.toLocaleDateString(undefined, dateOptions);

        if (isAllDay) {
          modalDate.textContent = `${startDate} • All Day`;
        } else {
          const startTime = event.start.toLocaleTimeString(undefined, timeOptions);

          if (hasEnd) {
            const sameDay = event.start.toDateString() === event.end.toDateString();

            if (sameDay) {
              const endTime = event.end.toLocaleTimeString(undefined, timeOptions);
              modalDate.textContent = `${startDate} • ${startTime} - ${endTime}`;
            } else {
              const endDate = event.end.toLocaleDateString(undefined, dateOptions);
              const endTime = event.end.toLocaleTimeString(undefined, timeOptions);
              modalDate.textContent = `${startDate} • ${startTime} through ${endDate} • ${endTime}`;
            }
          } else {
            modalDate.textContent = `${startDate} • ${startTime}`;
          }
        }
      } else {
        modalDate.textContent = '';
      }
    }

    if (modalLocationWrap && modalLocation) {
      if (props.location) {
        modalLocation.textContent = props.location;
        modalLocationWrap.hidden = false;
      } else {
        modalLocation.textContent = '';
        modalLocationWrap.hidden = true;
      }
    }

    if (modalDescriptionWrap && modalDescription) {
      if (props.description) {
        modalDescription.textContent = props.description;
        modalDescriptionWrap.hidden = false;
      } else {
        modalDescription.textContent = '';
        modalDescriptionWrap.hidden = true;
      }
    }

    if (modalImageWrap && modalImage) {
      if (props.image) {
        modalImage.src = props.image;
        modalImage.alt = event.title;
        modalImageWrap.hidden = false;
      } else {
        modalImage.removeAttribute('src');
        modalImage.alt = '';
        modalImageWrap.hidden = true;
      }
    }

    if (modalPdfWrap && modalPdf) {
      if (props.pdf) {
        modalPdf.href = props.pdf;
        modalPdfWrap.hidden = false;
      } else {
        modalPdf.removeAttribute('href');
        modalPdfWrap.hidden = true;
      }
    }

    if (modalExternalWrap && modalExternal) {
      if (safeExternalUrl) {
        modalExternal.href = safeExternalUrl;
        modalExternalWrap.hidden = false;
      } else {
        modalExternal.removeAttribute('href');
        modalExternalWrap.hidden = true;
      }
    }

    if (modalShareWrap && modalShareButton) {
      if (safeShareUrl) {
        modalShareWrap.hidden = false;
        modalShareButton.disabled = false;
      } else {
        modalShareWrap.hidden = true;
        modalShareButton.disabled = true;
      }
    }

    if (modal) modal.hidden = false;
  }

  function applyCalendarThemeVariables(themeVars, scope = document.documentElement) {
    if (!themeVars || typeof themeVars !== 'object' || !scope) {
      return;
    }

    Object.entries(themeVars).forEach(([key, value]) => {
      if (typeof value === 'string' && value.trim() !== '') {
        scope.style.setProperty(key, value);
      }
    });
  }

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (backdrop) backdrop.addEventListener('click', closeModal);

  if (modalShareButton) {
    modalShareButton.addEventListener('click', handleShareClick);
  }

  const params = new URLSearchParams(window.location.search);
  const linkedEventId = params.get('event_id');
  let linkedEventOpened = false;

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    fixedWeekCount: false,
    showNonCurrentDates: false,
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
    },
    buttonText: {
      dayGridMonth: 'Month',
      timeGridWeek: 'Week',
      timeGridDay: 'Day',
      listMonth: 'List'
    },
    events(fetchInfo, successCallback, failureCallback) {
      fetch('/event-forge/events/api.php')
        .then((response) => response.json())
        .then((data) => {
          if (data.meta && data.meta.calendar_theme_css_variables) {
            applyCalendarThemeVariables(data.meta.calendar_theme_css_variables);
          }

          if (data.meta && data.meta.app_version) {
            injectPoweredBy(data.meta.app_version);
          }

          successCallback(data.events || []);
        })
        .catch((err) => {
          console.error(err);
          failureCallback(err);
        });
    },
    eventTimeFormat: {
      hour: 'numeric',
      minute: '2-digit',
      meridiem: 'short'
    },
    dayMaxEvents: true,
    eventDisplay: 'block',

    eventDidMount(info) {
      const props = info.event.extendedProps || {};
      const isCanceled = !!props.isCanceled;
      const categoryColor = props.categoryColor || '';
      const categoryFontColor = props.categoryFontColor || '';

      if (isCanceled) {
        info.el.classList.add('event-canceled');
      }

      if (categoryColor) {
        info.el.style.backgroundColor = categoryColor;
        info.el.style.borderColor = categoryColor;
      }

      if (categoryFontColor) {
        info.el.style.color = categoryFontColor;

        const textNodes = info.el.querySelectorAll(
          '.fc-event-title, .fc-event-time, .fc-list-event-title a, .fc-list-event-time'
        );

        textNodes.forEach((node) => {
          node.style.color = categoryFontColor;
        });
      }
    },

    eventClick(info) {
      info.jsEvent.preventDefault();
      openEventModal(info.event);
    },

    eventsSet() {
      if (linkedEventOpened || !linkedEventId) return;

      const event = calendar.getEventById(String(linkedEventId));
      if (!event) return;

      if (event.start) {
        calendar.gotoDate(event.start);
      }

      calendarEl.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });

      setTimeout(() => {
        openEventModal(event);
      }, 350);

      linkedEventOpened = true;
    }
  });

  calendar.render();

  ensureCalendarFooter(calendarEl);

  const keyEl = document.getElementById('calendar-category-key');

  if (keyEl) {
    fetch('/event-forge/events/categories.php')
      .then((response) => response.json())
      .then((items) => {
        if (!Array.isArray(items) || items.length === 0) {
          keyEl.innerHTML = '';
          return;
        }

        keyEl.innerHTML = items.map((item) => {
          const color = item.color || '#cccccc';
          const name = item.name || 'Uncategorized';

          return `
            <span class="calendar-category-key__item">
              <span class="calendar-category-key__swatch" style="background:${color};"></span>
              <span>${name}</span>
            </span>
          `;
        }).join('');
      })
      .catch((err) => {
        console.error('Category key failed to load.', err);
        keyEl.innerHTML = '';
      });
  }
});

function ensureCalendarFooter(calendarEl) {
  let footer = document.getElementById('calendar-footer');

  if (footer) return footer;

  footer = document.createElement('div');
  footer.id = 'calendar-footer';
  footer.className = 'calendar-footer';

  const key = document.createElement('div');
  key.id = 'calendar-category-key';
  key.className = 'calendar-category-key';

  const powered = document.createElement('div');
  powered.id = 'calendar-powered-by';
  powered.className = 'calendar-powered-by';

  footer.appendChild(key);
  footer.appendChild(powered);

  calendarEl.insertAdjacentElement('afterend', footer);

  return footer;
}

function injectPoweredBy(version) {
  const el = document.getElementById('calendar-powered-by');
  if (!el) return;

  el.textContent = `Powered by Event Forge v${version}`;
}

function getSafeExternalUrl(value) {
  if (typeof value !== 'string') {
    return '';
  }

  const trimmed = value.trim();
  if (trimmed === '') {
    return '';
  }

  try {
    const parsed = new URL(trimmed, window.location.origin);

    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
      return '';
    }

    return parsed.href;
  } catch (error) {
    return '';
  }
}

function ensureEventModal() {
  let modal = document.getElementById('event-modal');

  if (modal) return modal;

  const wrapper = document.createElement('div');
  wrapper.innerHTML = `
    <div id="event-modal" class="event-modal" hidden>
      <div class="event-modal-backdrop"></div>

      <div class="event-modal-panel">
        <button class="event-modal-close" type="button" aria-label="Close event details">×</button>

        <h3 id="modal-title"></h3>

        <p id="modal-datetime"></p>

        <p id="modal-location-wrap" hidden>
          <strong>Location:</strong>
          <span id="modal-location"></span>
        </p>

        <div id="modal-image-wrap" hidden>
          <img id="modal-image" alt="" style="max-width:100%;">
        </div>

        <div id="modal-description-wrap" hidden>
          <div id="modal-description"></div>
        </div>

        <div class="event-modal-actions">
          <p id="modal-pdf-wrap" hidden>
            <a id="modal-pdf" class="event-modal-action-link event-modal-action-tile" href="#" target="_blank" rel="noopener">
              <svg
                class="event-modal-action-icon"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                aria-hidden="true"
                focusable="false"
              >
                <path
                  d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Zm0 1.5L17.5 7H14ZM7 3.5h5.5V8a.5.5 0 0 0 .5.5h4.5V20a.5.5 0 0 1-.5.5H7a.5.5 0 0 1-.5-.5V4A.5.5 0 0 1 7 3.5Zm2.25 8.75h5.5a.75.75 0 0 1 0 1.5h-5.5a.75.75 0 0 1 0-1.5Zm0 3h5.5a.75.75 0 0 1 0 1.5h-5.5a.75.75 0 0 1 0-1.5Z"
                  fill="currentColor"
                />
              </svg>
              <span class="event-modal-action-label">View Event Flyer</span>
            </a>
          </p>

          <p id="modal-external-wrap" hidden>
            <a id="modal-external" class="event-modal-action-link event-modal-action-tile" href="#" target="_blank" rel="noopener">
              <svg
                class="event-modal-action-icon"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                aria-hidden="true"
                focusable="false"
              >
                <path
                  d="M10 4.75A.75.75 0 0 1 10.75 4h8.5A.75.75 0 0 1 20 4.75v8.5a.75.75 0 0 1-1.5 0V6.56l-8.97 8.97a.75.75 0 1 1-1.06-1.06l8.97-8.97h-6.69A.75.75 0 0 1 10 4.75ZM5.75 7A1.75 1.75 0 0 0 4 8.75v9.5C4 19.22 4.78 20 5.75 20h9.5A1.75 1.75 0 0 0 17 18.25V11.5a.75.75 0 0 0-1.5 0v6.75a.25.25 0 0 1-.25.25h-9.5a.25.25 0 0 1-.25-.25v-9.5a.25.25 0 0 1 .25-.25H12.5A.75.75 0 0 0 12.5 7Z"
                  fill="currentColor"
                />
              </svg>
              <span class="event-modal-action-label">More Info</span>
            </a>
          </p>

          <p id="modal-share-wrap" hidden>
            <button id="modal-share-button" class="event-modal-action-button event-modal-action-tile" type="button">
              <svg
                class="event-modal-action-icon"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                aria-hidden="true"
                focusable="false"
              >
                <path
                  d="M18 16a3 3 0 0 0-2.39 1.19l-6.77-3.38a3.12 3.12 0 0 0 0-1.62l6.77-3.38A3 3 0 1 0 15 7a3.12 3.12 0 0 0 .06.59L8.29 10.97a3 3 0 1 0 0 2.06l6.77 3.38A3.12 3.12 0 0 0 15 17a3 3 0 1 0 3-1Z"
                  fill="currentColor"
                />
              </svg>
              <span class="event-modal-action-label">Share Event</span>
            </button>
          </p>
        </div>

        <p id="modal-share-status" class="event-modal-share-status" hidden aria-live="polite"></p>
      </div>
    </div>
  `;

  document.body.appendChild(wrapper.firstElementChild);

  return document.getElementById('event-modal');
}