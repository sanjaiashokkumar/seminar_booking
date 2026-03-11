/**
 * app.js — SeminarBook Frontend Logic
 */
if (typeof BASE_URL === 'undefined') { var BASE_URL = ''; }

/* Flash auto-dismiss */
(function() {
  var flash = document.getElementById('flashMsg');
  if (flash) {
    setTimeout(function() {
      flash.style.transition = 'opacity .5s ease, transform .5s ease';
      flash.style.opacity = '0';
      flash.style.transform = 'translateX(30px)';
      setTimeout(function() { if(flash.parentNode) flash.remove(); }, 500);
    }, 4500);
  }
})();

function escHtml(str) {
  var d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

function showAlert(msg) {
  var existing = document.getElementById('inlineAlert');
  if (existing) existing.remove();
  var el = document.createElement('div');
  el.id = 'inlineAlert';
  el.className = 'flash flash-error';
  el.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999';
  el.innerHTML = '<span>' + escHtml(msg) + '</span><button onclick="this.parentElement.remove()">×</button>';
  document.body.appendChild(el);
  setTimeout(function() { if(el.parentNode) el.remove(); }, 4000);
}

function initCheckboxPills() {
  document.querySelectorAll('.checkbox-pill').forEach(function(pill) {
    var cb = pill.querySelector('input[type="checkbox"]');
    if (!cb) return;
    if (cb.checked) pill.classList.add('checked');
    pill.addEventListener('click', function() {
      cb.checked = !cb.checked;
      pill.classList.toggle('checked', cb.checked);
    });
  });
}

function fetchHallAvailability() {
  var availabilityBox = document.getElementById('hall-availability');
  if (!availabilityBox) return;
  var date = document.getElementById('booking_date') ? document.getElementById('booking_date').value : '';
  if (!date) {
    availabilityBox.innerHTML = '<p class="availability-message loading">⏳ Please select a date first</p>';
    return;
  }
  var checked = document.querySelectorAll('.period-item input[type="checkbox"]:checked');
  var selected = [];
  checked.forEach(function(cb) { selected.push(cb.value); });
  if (selected.length === 0) {
    availabilityBox.innerHTML = '';
    clearHallCards();
    return;
  }
  availabilityBox.innerHTML = '<p class="availability-message loading">⏳ Checking availability…</p>';
  var params = 'date=' + encodeURIComponent(date) + '&periods=' + encodeURIComponent(selected.join(','));
  var excludeEl = document.getElementById('exclude_booking_id');
  var excludeId = excludeEl ? excludeEl.value : 0;
  if (excludeId) params += '&exclude=' + excludeId;

  fetch(BASE_URL + '/dept/ajax_halls.php?' + params)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.halls || data.halls.length === 0) {
        availabilityBox.innerHTML = '<p class="availability-message no-halls">⚠️ No halls available for all selected periods on this date.</p><div class="mt-2"><p class="text-muted mb-1" style="font-size:.85rem">Submit a help request for admin consideration.</p><a href="' + BASE_URL + '/dept/help_request.php" class="btn btn-accent btn-sm">📋 Submit Help Request</a></div>';
        clearHallCards();
        return;
      }
      renderHallCards(data.halls);
      availabilityBox.innerHTML = '<p class="availability-message has-halls">✅ ' + data.halls.length + ' hall(s) available for all selected periods</p>';
    })
    .catch(function() {
      availabilityBox.innerHTML = '<p class="availability-message no-halls">❌ Error checking availability.</p>';
    });
}

function renderHallCards(halls) {
  var container = document.getElementById('hall-cards');
  if (!container) return;
  var currentVal = '';
  var cur = document.querySelector('input[name="hall_id"]:checked');
  if (cur) currentVal = cur.value;
  container.innerHTML = halls.map(function(h) {
    return '<label class="hall-card ' + (String(h.id) === String(currentVal) ? 'hall-selected' : '') + '"><input type="radio" name="hall_id" value="' + h.id + '" ' + (String(h.id) === String(currentVal) ? 'checked' : '') + '><div class="hall-name">' + escHtml(h.name) + '</div><div class="hall-meta">📍 ' + escHtml(h.location || '') + '</div><div class="hall-cap">👥 Capacity: ' + h.capacity + '</div></label>';
  }).join('');
  container.querySelectorAll('.hall-card').forEach(function(card) {
    card.addEventListener('click', function() {
      container.querySelectorAll('.hall-card').forEach(function(c) { c.classList.remove('hall-selected'); });
      card.classList.add('hall-selected');
    });
  });
}

function clearHallCards() {
  var container = document.getElementById('hall-cards');
  if (container) container.innerHTML = '<p class="text-muted" style="font-size:.88rem">No halls available for selected criteria.</p>';
}

function initPeriodSelector() {
  document.querySelectorAll('.period-item:not(.period-booked)').forEach(function(item) {
    item.addEventListener('click', function() {
      var cb = item.querySelector('input[type="checkbox"]');
      if (!cb) return;
      cb.checked = !cb.checked;
      item.classList.toggle('period-selected', cb.checked);
      fetchHallAvailability();
    });
  });
  var dateEl = document.getElementById('booking_date');
  if (dateEl) dateEl.addEventListener('change', fetchHallAvailability);
}

function initBookingSteps() {
  var steps = document.querySelectorAll('.booking-step');
  if (steps.length === 0) return;
  var current = 0;

  function showStep(index) {
    steps.forEach(function(s, i) { s.style.display = i === index ? 'block' : 'none'; });
    document.querySelectorAll('[id^="step-ind-"]').forEach(function(el, i) {
      el.classList.toggle('active', i === index);
      el.classList.toggle('done', i < index);
    });
    current = index;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function validateStep(i) {
    if (i === 0) {
      var date = document.getElementById('booking_date');
      var periods = document.querySelectorAll('.period-item input[type="checkbox"]:checked');
      var hall = document.querySelector('input[name="hall_id"]:checked');
      if (!date || !date.value) { showAlert('Please select a booking date.'); return false; }
      if (!periods.length) { showAlert('Please select at least one period.'); return false; }
      if (!hall) { showAlert('Please select an available seminar hall.'); return false; }
    }
    if (i === 1) {
      var name = document.getElementById('event_name');
      if (!name || !name.value.trim()) { showAlert('Please enter the event name.'); return false; }
      var type = document.getElementById('event_type');
      if (!type || !type.value) { showAlert('Please select an event type.'); return false; }
    }
    return true;
  }

  document.querySelectorAll('[data-next-step]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (validateStep(current)) showStep(current + 1);
    });
  });
  document.querySelectorAll('[data-prev-step]').forEach(function(btn) {
    btn.addEventListener('click', function() { showStep(current - 1); });
  });

  showStep(0);
}

function initConfirmLinks() {
  document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });
}

document.addEventListener('DOMContentLoaded', function() {
  initCheckboxPills();
  initPeriodSelector();
  initBookingSteps();
  initConfirmLinks();
});
