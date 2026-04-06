document.addEventListener('DOMContentLoaded', () => {
  const calendarEl = document.getElementById('calendar');

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

  const closeBtn = document.querySelector('.event-modal-close');
  const backdrop = document.querySelector('.event-modal-backdrop');

  if (!calendarEl || !window.FullCalendar) {
    console.error('Calendar container or FullCalendar missing.');
    return;
  }

  function ensureCategoryKey(calendarEl) {
    let keyEl = document.getElementById('calendar-category-key');

    if (keyEl) return keyEl;

    keyEl = document.createElement('div');
    keyEl.id = 'calendar-category-key';
    keyEl.className = 'calendar-category-key';

    calendarEl.insertAdjacentElement('afterend', keyEl);

    return keyEl;
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

          <p id="modal-pdf-wrap" hidden>
            <a id="modal-pdf" href="#" target="_blank" rel="noopener">View Event Flyer</a>
          </p>
        </div>
      </div>
    `;

    document.body.appendChild(wrapper.firstElementChild);

    return document.getElementById('event-modal');
  }

  const closeModal = () => {
    if (modal) modal.hidden = true;
    if (modalImage) modalImage.removeAttribute('src');
    if (modalPdf) modalPdf.removeAttribute('href');
  };

  function openEventModal(event) {
    const props = event.extendedProps || {};
    const isCanceled = !!props.isCanceled;

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

    if (modal) modal.hidden = false;
  }

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (backdrop) backdrop.addEventListener('click', closeModal);

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
    events: '/event-forge/events/api.php',
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

  const keyEl = ensureCategoryKey(calendarEl);

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

function ensureCategoryKey(calendarEl) {
    let keyEl = document.getElementById('calendar-category-key');

    if (keyEl) return keyEl;

    keyEl = document.createElement('div');
    keyEl.id = 'calendar-category-key';
    keyEl.className = 'calendar-category-key';

    calendarEl.insertAdjacentElement('afterend', keyEl);

    return keyEl;
  }