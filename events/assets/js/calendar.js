document.addEventListener('DOMContentLoaded', () => {
  const calendarEl = document.getElementById('calendar');

  const modal = document.getElementById('event-modal');
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

  const closeModal = () => {
    modal.hidden = true;
    modalImage.removeAttribute('src');
    modalPdf.removeAttribute('href');
  };

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (backdrop) backdrop.addEventListener('click', closeModal);

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

  eventDidMount(info) {
    const props = info.event.extendedProps || {};
    const isCanceled = !!props.isCanceled;
    const categoryColor = props.categoryColor || '';

    if (isCanceled) {
      info.el.classList.add('event-canceled');
    }

    if (categoryColor) {
      info.el.style.backgroundColor = categoryColor;
      info.el.style.borderColor = categoryColor;
    }
  },

  eventClick(info) {
    info.jsEvent.preventDefault();

    const event = info.event;
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
});

  calendar.render();
});