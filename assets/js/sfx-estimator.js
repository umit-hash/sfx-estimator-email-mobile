(function () {
  function startEstimator() {
    const rootEl = document.getElementById('sfx-estimator');
    if (!rootEl) return;
    rootEl.style.display = 'block';

    const PAGE_SIZE = 18; // requirement: fewer than 20 models per page

    const state = {
      step: 0,
      type: null,
      make: null,
      series: null,
      model: null,
      issues: [],
      first_name: '',
      last_name: '',
      phone: '',
      email: '',
      notes: '',
    notify: 'both',
      catalog: null,
      series_map: null,
      issues_all: [],
      issues_by_type: {},
      issues_by_make: {},
      modelPage: 0,
      modelQuery: '',
      loadError: '',
      isLoading: true
    };

    const FALLBACK_ICONS = {
      'Cell Phone': 'Phone',
      Smartphone: 'Phone',
      Tablet: 'Tablet',
      Computer: 'Computer',
      'Gaming Console': 'Console',
      Others: 'Device'
    };
    const PREFERRED_MAKE_ORDER = {
      'Cell Phone': ['iPhone', 'Samsung', 'Motorola', 'Google Pixel', 'LG', 'Other Cell Phones'],
      Tablet: ['Apple', 'Samsung Tablets', 'Other Tablets'],
      Computer: ['MacBook', 'iMac', 'Laptop (Windows)', 'Desktop (Windows)'],
      'Gaming Console': ['PlayStation', 'Xbox', 'Nintendo'],
      Others: ['Apple Watch', 'iPod', 'Other Devices']
    };
    const SERIES_IMAGES = (window.SFXEstimator && window.SFXEstimator.seriesImages) ? window.SFXEstimator.seriesImages : {};
    const MODEL_IMAGES = (window.SFXEstimator && window.SFXEstimator.modelImages) ? window.SFXEstimator.modelImages : {};
    const TILE_IMAGES = (window.SFXEstimator && window.SFXEstimator.tiles && window.SFXEstimator.tiles.images) ? window.SFXEstimator.tiles.images : {};
    const BOOTSTRAP = (window.SFXEstimator && window.SFXEstimator.bootstrap) ? window.SFXEstimator.bootstrap : null;

    function applyCatalogData(data) {
      state.catalog = data.catalog || {};
      state.series_map = data.series_map || {};
      state.issues_all = data.issues || [];
      state.issues_by_type = data.issues_by_type || {};
      state.issues_by_make = data.issues_by_make || {};
    }

    if (BOOTSTRAP && Object.keys(BOOTSTRAP.catalog || {}).length) {
      applyCatalogData(BOOTSTRAP);
      state.isLoading = false;
    }

    function el(tag, attrs = {}, children = []) {
      const element = document.createElement(tag);
      Object.entries(attrs).forEach(([key, value]) => {
        if (key === 'class') {
          element.className = value;
        } else if (key === 'html') {
          element.innerHTML = value;
        } else if ((key === 'disabled' || key === 'checked') && !value) {
          return;
        } else if (key === 'disabled' && value) {
          element.setAttribute('disabled', 'disabled');
        } else if (key === 'checked' && value) {
          element.setAttribute('checked', 'checked');
        } else if (value !== undefined && value !== null) {
          element.setAttribute(key, value);
        }
      });
      (Array.isArray(children) ? children : [children]).forEach(child => {
        if (!child && child !== 0) return;
        if (typeof child === 'string' || typeof child === 'number') {
          element.appendChild(document.createTextNode(String(child)));
        } else {
          element.appendChild(child);
        }
      });
      return element;
    }

    function render() {
      rootEl.innerHTML = '';
      const stepsLabels = ['Main Category', 'Subcategory', 'Series', 'Model', 'Problem', 'Customer Info'];
      if ((window.SFXEstimator && window.SFXEstimator.ui && window.SFXEstimator.ui.showBreadcrumbs) === true) {
        const activeTrail = stepsLabels
          .map((label, index) => (index <= state.step ? label : null))
          .filter(Boolean)
          .join(' > ');
        rootEl.appendChild(el('div', { class: 'sfx-steps' }, [activeTrail]));
      }
      rootEl.appendChild(el('h2', { class: 'sfx-head' }, [stepTitle()]));

      if (state.isLoading) {
        rootEl.appendChild(el('div', { class: 'sfx-empty' }, ['Loading catalog...']));
        return;
      }
      if (state.loadError) {
        rootEl.appendChild(el('div', { class: 'sfx-error-box' }, [state.loadError]));
      }

      if (state.step === 0) renderMainCategory();
      else if (state.step === 1) renderSubcategory();
      else if (state.step === 2) renderSeries();
      else if (state.step === 3) renderModel();
      else if (state.step === 4) renderIssues();
      else renderContact();
    }

    function stepTitle() {
      switch (state.step) {
        case 0:
          return 'What kind of device do you have?';
        case 1:
          return 'Pick a subcategory (brand)';
        case 2:
          return 'Select the device series';
        case 3:
          return 'Select your exact model';
        case 4:
          return 'What problems are you seeing?';
        default:
          return 'How can we contact you?';
      }
    }

    function next() {
      const hasSeries = seriesListFor(state.type, state.make).length > 0;
      const skipModel = shouldSkipModelSelection(state.type, state.make);
      if (state.step === 0 && !state.type) return;
      if (state.step === 1 && !state.make) return;
      if (state.step === 1 && skipModel) {
        state.series = '';
        state.model = '';
        state.step = 4;
        render();
        return;
      }
      if (state.step === 2) {
        if (!hasSeries) {
          state.series = '';
          state.step = 3;
          render();
          return;
        }
        if (!state.series) return;
        if (!seriesHasModelOptions(state.type, state.make, state.series)) {
          state.model = '';
          state.modelPage = 0;
          state.modelQuery = '';
          state.step = 4;
          render();
          return;
        }
      }
      if (state.step === 3 && !state.model) return;
      if (state.step === 4 && (!state.issues || state.issues.length === 0)) {
        alert('Please select at least one problem so we can prepare your estimate.');
        return;
      }
      state.step = Math.min(5, state.step + 1);
      render();
    }

    function back() {
      const hasSeries = seriesListFor(state.type, state.make).length > 0;
      const skipModel = shouldSkipModelSelection(state.type, state.make);
      if (state.step === 0) return;
      if (state.step === 1) {
        state.make = null;
        state.step = 0;
      } else if (state.step === 2) {
        state.series = null;
        state.model = null;
        state.step = 1;
      } else if (state.step === 3) {
        state.model = null;
        state.step = hasSeries ? 2 : 1;
      } else if (state.step === 4) {
        if (skipModel) {
          state.series = '';
          state.model = '';
          state.issues = [];
          state.step = 1;
        } else if (state.series && !seriesHasModelOptions(state.type, state.make, state.series)) {
          state.issues = [];
          state.step = 2;
        } else {
          state.step = 3;
        }
      } else if (state.step === 5) {
        state.step = 4;
      }
      render();
    }

    function actions() {
      const wrapper = el('div', { class: 'sfx-actions' });
      if (state.step > 0) {
        wrapper.appendChild(el('button', { class: 'sfx-btn secondary', type: 'button' }, ['Back']));
      }
      wrapper.appendChild(el('button', { class: 'sfx-btn', type: 'button' }, [state.step === 5 ? 'Submit' : 'Next']));
      wrapper.addEventListener('click', evt => {
        if (evt.target.tagName !== 'BUTTON') return;
        if (evt.target.textContent === 'Back') back();
        else if (state.step < 5) next();
        else submit();
      });
      return wrapper;
    }

    function renderMainCategory() {
      const types = Object.keys(state.catalog || {});
      if (!types.length) {
        rootEl.appendChild(el('div', { class: 'sfx-empty' }, [
          'No devices found. Add pricing data in the Estimator settings to get started.'
        ]));
        return;
      }
      const grid = el('div', { class: 'sfx-grid' });
      types.forEach(type => {
        const tile = el('div', { class: 'sfx-tile', tabIndex: '0' });
        if (TILE_IMAGES[type]) {
          tile.appendChild(el('img', { src: TILE_IMAGES[type], alt: type }));
        } else {
          tile.appendChild(el('div', { style: 'font-size:28px;line-height:1;' }, [FALLBACK_ICONS[type] || 'Device']));
        }
        tile.appendChild(el('div', { style: 'margin-top:6px;' }, [type]));
        if (state.type === type) tile.classList.add('selected');
        tile.addEventListener('click', () => {
          if (state.type !== type) {
            state.type = type;
            resetAfterTypeChange();
          }
          next();
        });
        grid.appendChild(tile);
      });
      rootEl.appendChild(grid);
      rootEl.appendChild(actions());
    }

    function renderSubcategory() {
      const preferredOrder = PREFERRED_MAKE_ORDER[state.type] || [];
      const makes = Object.keys((state.catalog && state.catalog[state.type]) || {}).sort((a, b) => {
        const aIndex = preferredOrder.indexOf(a);
        const bIndex = preferredOrder.indexOf(b);
        if (aIndex !== -1 || bIndex !== -1) {
          if (aIndex === -1) return 1;
          if (bIndex === -1) return -1;
          if (aIndex !== bIndex) return aIndex - bIndex;
        }
        return a.localeCompare(b);
      });
      const grid = el('div', { class: 'sfx-grid' });
      makes.forEach(make => {
        const tile = el('div', { class: 'sfx-tile', tabIndex: '0' });
        tile.appendChild(el('div', { style: 'font-size:24px;line-height:1;' }, ['Brand']));
        tile.appendChild(el('div', { style: 'margin-top:6px;' }, [make]));
        if (state.make === make) tile.classList.add('selected');
        tile.addEventListener('click', () => {
          if (state.make !== make) {
            state.make = make;
            resetAfterMakeChange();
          }
          next();
        });
        grid.appendChild(tile);
      });
      rootEl.appendChild(grid);
      rootEl.appendChild(actions());
    }

    function renderSeries() {
      if (!state.type || !state.make) return;
      const seriesList = seriesListFor(state.type, state.make);
      if (!seriesList.length) {
        state.series = '';
        state.step = 3;
        render();
        return;
      }
      if (state.series && !seriesList.includes(state.series)) state.series = null;

      const grid = el('div', { class: 'sfx-grid' });
      seriesList.forEach(seriesName => {
        const tile = el('div', { class: 'sfx-tile', tabIndex: '0' });
        const image = SERIES_IMAGES[seriesName];
        if (image) tile.appendChild(el('img', { src: image, alt: seriesName }));
        else tile.appendChild(el('div', { style: 'font-size:24px;line-height:1;' }, ['Series']));
        tile.appendChild(el('div', { style: 'margin-top:6px;' }, [seriesName]));
        if (state.series === seriesName) tile.classList.add('selected');
        tile.addEventListener('click', () => {
          if (state.series !== seriesName) {
            state.series = seriesName;
            resetAfterSeriesChange();
          }
          next();
        });
        grid.appendChild(tile);
      });
      rootEl.appendChild(el('div', { class: 'sfx-tip' }, ['Pick the series first, then you\'ll choose the exact model.']));
      rootEl.appendChild(grid);
      rootEl.appendChild(actions());
    }

    function renderModel() {
      if (!state.type || !state.make) return;
      const models = modelsForCurrentSelection();
      if (state.model && !models.includes(state.model)) state.model = null;

      const query = (state.modelQuery || '').toLowerCase();
      const filtered = models.filter(model => model.toLowerCase().includes(query));
      const total = filtered.length;

      const searchBox = el('input', {
        class: 'sfx-search',
        placeholder: 'Search model...',
        value: state.modelQuery
      });
      searchBox.addEventListener('input', () => {
        state.modelQuery = searchBox.value.trim();
        state.modelPage = 0;
        render();
      });

      if (!total) {
        rootEl.appendChild(el('div', { class: 'sfx-tip' }, ['No models found. Try a different search or go back to pick another series.']));
        rootEl.appendChild(searchBox);
        rootEl.appendChild(actions());
        return;
      }

      const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));
      if (state.modelPage >= pageCount) state.modelPage = pageCount - 1;
      if (state.modelPage < 0) state.modelPage = 0;

      const start = state.modelPage * PAGE_SIZE;
      const end = Math.min(start + PAGE_SIZE, total);
      const pageItems = filtered.slice(start, end);

      const grid = el('div', { class: 'sfx-grid' });
      pageItems.forEach(modelName => {
        const tile = el('div', { class: 'sfx-tile', tabIndex: '0' });
        const imgSrc = MODEL_IMAGES[modelName] || (state.series ? SERIES_IMAGES[state.series] : null);
        if (imgSrc) tile.appendChild(el('img', { src: imgSrc, alt: modelName }));
        tile.appendChild(el('div', { style: 'margin-top:6px;' }, [modelName]));
        if (state.model === modelName) tile.classList.add('selected');
        tile.addEventListener('click', () => {
          state.model = modelName;
          next();
        });
        grid.appendChild(tile);
      });

      rootEl.appendChild(el('div', { class: 'sfx-tip' }, [`Showing ${start + 1}-${end} of ${total} models`]));
      rootEl.appendChild(searchBox);
      rootEl.appendChild(grid);

      if (pageCount > 1) {
        const pagination = el('div', { class: 'sfx-pagination' });
        const prevBtn = el('button', {
          class: 'sfx-page-btn',
          type: 'button',
          disabled: state.modelPage === 0
        }, ['Previous']);
        const nextBtn = el('button', {
          class: 'sfx-page-btn',
          type: 'button',
          disabled: state.modelPage >= pageCount - 1
        }, ['Next']);
        prevBtn.addEventListener('click', () => {
          if (state.modelPage === 0) return;
          state.modelPage -= 1;
          render();
        });
        nextBtn.addEventListener('click', () => {
          if (state.modelPage >= pageCount - 1) return;
          state.modelPage += 1;
          render();
        });
        pagination.appendChild(prevBtn);
        pagination.appendChild(el('span', { class: 'sfx-page-info' }, [`Page ${state.modelPage + 1} of ${pageCount}`]));
        pagination.appendChild(nextBtn);
        rootEl.appendChild(pagination);
      }

      rootEl.appendChild(actions());
    }

    function renderIssues() {
      const issuesList = issuesFor(state.type, state.make);
      const wrapper = el('div');
      const boxWrap = el('div');

      issuesList.forEach(issueName => {
        const label = el('label', { class: 'sfx-checkbox' });
        const checkbox = el('input', { type: 'checkbox' });
        checkbox.checked = state.issues.includes(issueName);
        checkbox.addEventListener('change', () => {
          if (checkbox.checked && !state.issues.includes(issueName)) {
            state.issues.push(issueName);
          } else if (!checkbox.checked) {
            state.issues = state.issues.filter(item => item !== issueName);
          }
        });
        label.appendChild(checkbox);
        label.appendChild(el('span', {}, [issueName]));
        boxWrap.appendChild(label);
      });

      wrapper.appendChild(el('div', { class: 'sfx-tip' }, ['Select all that apply.']));
      wrapper.appendChild(boxWrap);
      rootEl.appendChild(wrapper);
      rootEl.appendChild(actions());
    }

    function issuesFor(type, make) {
      const byMake = (((state.issues_by_make || {})[type] || {})[make]) || null;
      if (byMake && byMake.length) return byMake;
      const fallback = fallbackIssues(type, make);
      if (fallback.length) return fallback;
      const byType = (state.issues_by_type || {})[type] || null;
      if (byType && byType.length) return byType;
      return state.issues_all || [];
    }

    function fallbackIssues(type, make) {
      const t = String(type || '').toLowerCase();
      const m = String(make || '').toLowerCase();
      if (t === 'gaming console' && m === 'playstation') {
        return [
          'HDMI Port Replacement',
          'Disc Drive Repair',
          'No Power (Diagnostic)',
          'Overheating / Thermal Service',
          'Power Supply Replacement',
          'Fan / Loud Noise Service',
          'Software / Firmware Reinstall',
          'SSD / Storage Upgrade'
        ];
      }
      if (t === 'computer' && m === 'macbook') {
        return [
          'Screen Replacement',
          'Battery Replacement',
          'Keyboard Replacement',
          'Trackpad Replacement',
          'Charging Port / I/O Board Repair',
          'Fan / Thermal Service',
          'SSD / Storage Upgrade',
          'Data Recovery',
          'Liquid Damage (Diagnostic)',
          'No Power (Diagnostic)',
          'Logic Board Repair'
        ];
      }
      return [];
    }

    function generalSeriesModels(type, make) {
      const banned = ['general', 'any model'];
      const map = (((state.series_map || {})[type] || {})[make]) || {};
      const models = [];
      Object.keys(map).forEach(function (key) {
        const cleaned = String(key || '').trim().toLowerCase();
        if (!banned.includes(cleaned)) return;
        const arr = Array.isArray(map[key]) ? map[key] : [];
        arr.forEach(function (name) {
          if (!name || name === 'Any Model') return;
          if (!models.includes(name)) models.push(name);
        });
      });
      return models;
    }

    function renderContact() {
      const form = el('div', { class: 'sfx-contact' });

      const hero = el('div', { class: 'sfx-contact-hero' }, [
        el('span', { class: 'sfx-contact-chip' }, ['Instant estimate delivery']),
        el('div', { class: 'sfx-contact-hero-text' }, [
          el('div', { class: 'sfx-contact-title' }, ['Where should we send your estimate?']),
          el('p', { class: 'sfx-contact-copy' }, ['We only use your info to share the quote and quick status updates.'])
        ])
      ]);
      form.appendChild(hero);

      const body = el('div', { class: 'sfx-contact-body' });
      const fields = el('div', { class: 'sfx-contact-fields' });

      function buildField(labelText, placeholder, value, required, type = 'text') {
        const wrapper = el('label', { class: 'sfx-field' });
        wrapper.appendChild(el('span', { class: 'sfx-field-label' }, [labelText + (required ? ' *' : '')]));
        const input = el('input', { class: 'sfx-input', type, placeholder, value: value || '' });
        wrapper.appendChild(input);
        return { wrapper, input };
      }

      function buildTextarea(labelText, placeholder, value) {
        const wrapper = el('label', { class: 'sfx-field' });
        wrapper.appendChild(el('span', { class: 'sfx-field-label' }, [labelText]));
        const textarea = el('textarea', { class: 'sfx-textarea', rows: '4', placeholder, html: value || '' });
        wrapper.appendChild(textarea);
        return { wrapper, textarea };
      }

      const namesRow = el('div', { class: 'sfx-contact-grid' });
      const first = buildField('First name', 'First name', state.first_name, true);
      const last = buildField('Last name', 'Last name', state.last_name, false);
      namesRow.appendChild(first.wrapper);
      namesRow.appendChild(last.wrapper);

      const contactRow = el('div', { class: 'sfx-contact-grid' });
      const phone = buildField('Phone', 'Best number for text updates', state.phone, false, 'tel');
      const email = buildField('Email', 'Where should we email the estimate?', state.email, false, 'email');
      contactRow.appendChild(phone.wrapper);
      contactRow.appendChild(email.wrapper);

      const notifyWrap = el('div', { class: 'sfx-contact-delivery' });
      notifyWrap.appendChild(el('div', { class: 'sfx-contact-legend' }, ['Send my estimate via']));
      const notifyOptions = el('div', { class: 'sfx-contact-options' });
      const notifyValue = state.notify || 'both';

      function makeNotifyOption(value, title, hint) {
        const label = el('label', { class: 'sfx-pill' + (notifyValue === value ? ' is-active' : '') });
        const radio = el('input', { type: 'radio', name: 'notify', value, checked: notifyValue === value });
        const body = el('div', { class: 'sfx-pill-body' }, [
          el('strong', {}, [title]),
          el('small', {}, [hint])
        ]);
        label.appendChild(radio);
        label.appendChild(body);
        return label;
      }

      const emailOpt = makeNotifyOption('email', 'Email', 'Detailed breakdown of your quote.');
      const smsOpt = makeNotifyOption('sms', 'Text', 'Quick status pings and directions.');
      const bothOpt = makeNotifyOption('both', 'Email + text', 'Best if you want both confirmations.');
      notifyOptions.appendChild(emailOpt);
      notifyOptions.appendChild(smsOpt);
      notifyOptions.appendChild(bothOpt);
      notifyWrap.appendChild(notifyOptions);

      const notes = buildTextarea('Notes (optional)', 'Anything we should know about your device or schedule?', state.notes);

      fields.appendChild(namesRow);
      fields.appendChild(contactRow);
      fields.appendChild(notifyWrap);
      fields.appendChild(notes.wrapper);

      body.appendChild(fields);

      const summary = el('div', { class: 'sfx-contact-summary' });
      summary.appendChild(el('div', { class: 'sfx-contact-summary-title' }, ['You’re getting help for']));
      const summaryList = el('ul', { class: 'sfx-summary-list' });
      const modelText = state.model || (shouldSkipModelSelection(state.type, state.make) ? 'Any Model' : '');
      const deviceLine = [state.type, state.make, modelText].filter(Boolean).join(' • ');
      summaryList.appendChild(el('li', {}, [
        el('span', { class: 'sfx-summary-label' }, ['Device']),
        el('span', { class: 'sfx-summary-value' }, [deviceLine || 'Device not selected yet'])
      ]));
      const issuesText = (state.issues && state.issues.length) ? state.issues.join(', ') : 'Issue list will appear here';
      summaryList.appendChild(el('li', {}, [
        el('span', { class: 'sfx-summary-label' }, ['Issues']),
        el('span', { class: 'sfx-summary-value' }, [issuesText])
      ]));
      const notifyCopy = { email: 'Email delivery', sms: 'Text delivery', both: 'Email + text' }[notifyValue] || 'Email + text';
      summaryList.appendChild(el('li', {}, [
        el('span', { class: 'sfx-summary-label' }, ['Delivery']),
        el('span', { class: 'sfx-summary-value' }, [notifyCopy])
      ]));
      summary.appendChild(summaryList);
      summary.appendChild(el('p', { class: 'sfx-summary-footnote' }, ['Same-day turnaround on most repairs. Walk-ins welcome.']));

      body.appendChild(summary);

      form.appendChild(body);
      rootEl.appendChild(form);
      rootEl.appendChild(actions());

      function syncFormState() {
        state.first_name = first.input.value.trim();
        state.last_name = last.input.value.trim();
        state.phone = phone.input.value.trim();
        state.email = email.input.value.trim();
        state.notes = notes.textarea.value.trim();
        const selected = form.querySelector('input[name=notify]:checked');
        state.notify = selected ? selected.value : 'both';
        [emailOpt, smsOpt, bothOpt].forEach(label => {
          const input = label.querySelector('input');
          label.classList.toggle('is-active', input && input.checked);
        });
      }

      form.addEventListener('input', syncFormState);
      form.addEventListener('change', syncFormState);
      syncFormState();
    }

    async function submit() {
      const notify = state.notify || 'both';
      const needPhone = notify === 'sms' || notify === 'both';
      const needEmail = notify === 'email' || notify === 'both';
      const missing = [];
      if (!state.first_name) missing.push('first name');
      if (needPhone && !state.phone) missing.push('phone');
      if (needEmail && !state.email) missing.push('email');
      if (missing.length) {
        alert('Please enter: ' + missing.join(', '));
        return;
      }

      const payload = {
        first_name: state.first_name,
        last_name: state.last_name,
        phone: state.phone,
        email: state.email,
        notes: state.notes || '',
        notify,
        device_type: state.type,
        make: state.make,
        series: state.series || '',
        model: state.model,
        issues: state.issues
      };

      try {
        const restRoot = (window.SFXEstimator && window.SFXEstimator.rest && window.SFXEstimator.rest.root)
          ? window.SFXEstimator.rest.root
          : '/wp-json/sfx/v1/';
        const nonce = (window.SFXEstimator && window.SFXEstimator.rest) ? window.SFXEstimator.rest.nonce : '';
        const headers = { 'Content-Type': 'application/json' };
        if (nonce) headers['X-WP-Nonce'] = nonce;
        const response = await fetch(restRoot + 'quote', {
          method: 'POST',
          headers,
          body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!data.ok) throw new Error(data.message || 'Failed');
        rootEl.innerHTML = '';
        rootEl.appendChild(el('h2', { class: 'sfx-head' }, ['Thanks! We received your request.']));
        const estimateData = data.estimate || {};
        const estimateRef = data.estimate_ref ? String(data.estimate_ref) : '';
        const estimateId = estimateData.estimate_id ? String(estimateData.estimate_id) : '';
        const fallbackId = typeof data.quote_id !== 'undefined' ? String(data.quote_id) : '';
        const ticketMeta = (window.SFXEstimator && window.SFXEstimator.ticket) || null;
        let computedRef = '';
        if (!estimateRef && ticketMeta && fallbackId) {
          const rawId = parseInt(fallbackId, 10);
          if (!Number.isNaN(rawId)) {
            const offset = typeof ticketMeta.offset !== 'undefined' ? parseInt(ticketMeta.offset, 10) : NaN;
            const prefix = typeof ticketMeta.prefix === 'string' ? ticketMeta.prefix : '';
            let displayNumber = rawId;
            if (!Number.isNaN(offset)) {
              displayNumber = rawId + offset;
            }
            if (displayNumber <= 0) {
              displayNumber = rawId;
            }
            computedRef = prefix + String(displayNumber);
          }
        }
        const ticketDisplay = estimateId || estimateRef || computedRef || fallbackId || 'processing';
        rootEl.appendChild(el('p', {}, ['Your ticket number is ', ticketDisplay, '. A confirmation has been sent.']));
      } catch (err) {
        alert('Could not submit your request. Please call us or try again.\n\n' + err.message);
      }
    }

    function shouldSkipModelSelection(type, make) {
      if (!type || !make) return false;
      const series = seriesListFor(type, make);
      if (series.length) return false;
      const catalogMakes = ((state.catalog || {})[type] || {})[make] || [];
      const hasModels = catalogMakes.some(name => {
        if (!name) return false;
        const cleaned = String(name).trim().toLowerCase();
        return cleaned && cleaned !== 'any model' && cleaned !== 'general';
      });
      if (generalSeriesModels(type, make).length) return false;
      if (hasModels) return false;
      const issues = (((state.issues_by_make || {})[type] || {})[make]) || [];
      return issues.length > 0;
    }

    function seriesHasModelOptions(type, make, series) {
      if (!type || !make || !series) return false;
      const list = (((state.series_map || {})[type] || {})[make] || {})[series];
      return Array.isArray(list) && list.length > 0;
    }

    function seriesListFor(type, make) {
      const map = (state.series_map && state.series_map[type] && state.series_map[type][make]) ? state.series_map[type][make] : null;
      if (!map) return [];
      const banned = ['general', 'any model'];
      return Object.keys(map).filter(function (name) {
        const key = String(name || '').trim().toLowerCase();
        return key && !banned.includes(key);
      });
    }

    function modelsForCurrentSelection() {
      if (!state.type || !state.make) return [];
      const seriesList = seriesListFor(state.type, state.make);
      if (seriesList.length && state.series) {
        const bySeries = (((state.series_map || {})[state.type] || {})[state.make] || {})[state.series] || [];
        return bySeries.filter(Boolean);
      }
      if (seriesList.length && !state.series) return [];
      const direct = ((state.catalog || {})[state.type] || {})[state.make] || [];
      const directFiltered = direct.filter(function (name) {
        if (!name) return false;
        const cleaned = String(name).trim().toLowerCase();
        return cleaned && cleaned !== 'any model' && cleaned !== 'general';
      });
      if (directFiltered.length) return directFiltered;
      return generalSeriesModels(state.type, state.make);
    }

    function resetAfterTypeChange() {
      state.make = null;
      state.series = null;
      state.model = null;
      state.issues = [];
      state.modelPage = 0;
      state.modelQuery = '';
    }

    function resetAfterMakeChange() {
      state.series = null;
      state.model = null;
      state.issues = [];
      state.modelPage = 0;
      state.modelQuery = '';
    }

    function resetAfterSeriesChange() {
      state.model = null;
      state.issues = [];
      state.modelPage = 0;
      state.modelQuery = '';
    }

    async function boot() {
      render();
      try {
        const restRoot = (window.SFXEstimator && window.SFXEstimator.rest && window.SFXEstimator.rest.root)
          ? window.SFXEstimator.rest.root
          : '/wp-json/sfx/v1/';
        const nonce = (window.SFXEstimator && window.SFXEstimator.rest) ? window.SFXEstimator.rest.nonce : '';
        const versionToken = (window.SFXEstimator && window.SFXEstimator.version) ? window.SFXEstimator.version : Date.now();
        const headers = {};
        if (nonce) headers['X-WP-Nonce'] = nonce;
        const response = await fetch(restRoot + 'catalog?v=' + versionToken, { headers });
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        const data = await response.json();
        if (!data || data.ok === false) {
          throw new Error((data && data.message) ? data.message : 'Catalog request failed.');
        }
        applyCatalogData(data);
        state.loadError = '';
      } catch (err) {
        if (!Object.keys(state.catalog || {}).length) {
          state.catalog = state.catalog || {};
          state.series_map = state.series_map || {};
          state.issues_all = state.issues_all || [];
          state.issues_by_type = state.issues_by_type || {};
          state.issues_by_make = state.issues_by_make || {};
          state.loadError = 'We could not load your catalog. Check your estimator settings and try again. (' + err.message + ')';
        }
      }
      state.isLoading = false;
      render();
    }

    boot();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startEstimator);
  } else {
    startEstimator();
  }
})();
