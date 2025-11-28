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
    const MAKE_IMAGES = (window.SFXEstimator && window.SFXEstimator.makeImages) ? window.SFXEstimator.makeImages : {};
    const TILE_IMAGES = (window.SFXEstimator && window.SFXEstimator.tiles && window.SFXEstimator.tiles.images) ? window.SFXEstimator.tiles.images : {};
    const BOOTSTRAP = (window.SFXEstimator && window.SFXEstimator.bootstrap) ? window.SFXEstimator.bootstrap : null;

    function applyCatalogData(data) {
      state.catalog = data.catalog || {};
      state.series_map = data.series_map || {};
      state.issues_all = data.issues || [];
      state.issues_by_type = data.issues_by_type || {};
      state.issues_by_make = data.issues_by_make || {};
    }

    function brandImageFor(type, make) {
      if (!type || !make) return null;
      const perType = MAKE_IMAGES[type];
      if (!perType) return null;
      return perType[make] || null;
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
        state.model = 'Any Model';
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
        state.model = null;
        state.modelPage = 0;
        state.modelQuery = '';
        state.step = 3;
        render();
        return;
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
          tile.appendChild(el('div', { class: 'sfx-main-icon', 'aria-hidden': 'true' }, [FALLBACK_ICONS[type] || 'Device']));
        }
        tile.appendChild(el('div', { class: 'sfx-tile-label' }, [type]));
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
      if (!makes.length) {
        rootEl.appendChild(el('div', { class: 'sfx-empty' }, ['No brands found for this category. Try going back and selecting a different type.']));
        rootEl.appendChild(actions());
        return;
      }
      const grid = el('div', { class: 'sfx-grid' });
      makes.forEach(make => {
        const tile = el('div', { class: 'sfx-tile', tabIndex: '0' });
        const image = brandImageFor(state.type, make);
        if (image) {
          tile.appendChild(el('img', { src: image, alt: make }));
        } else {
          const initials = (make && make.trim()) ? make.trim().charAt(0).toUpperCase() : '?';
          tile.appendChild(el('div', { class: 'sfx-avatar', 'aria-hidden': 'true' }, [initials]));
        }
        tile.appendChild(el('div', { class: 'sfx-tile-label' }, [make]));
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
      const seriesList = sortSeries(seriesListFor(state.type, state.make));
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
        if (image) {
          tile.appendChild(el('img', { src: image, alt: seriesName }));
        } else {
          const initials = (seriesName && seriesName.trim()) ? seriesName.trim().charAt(0).toUpperCase() : 'S';
          tile.appendChild(el('div', { class: 'sfx-avatar sfx-avatar-muted', 'aria-hidden': 'true' }, [initials]));
        }
        tile.appendChild(el('div', { class: 'sfx-tile-label' }, [seriesName]));
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
        const anyBtn = el('button', { class: 'sfx-btn', type: 'button' }, ['Use Any Model']);
        anyBtn.addEventListener('click', () => {
          state.model = 'Any Model';
          next();
        });
        rootEl.appendChild(el('div', { style: 'margin:12px 0;' }, [anyBtn]));
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
        if (imgSrc) {
          tile.appendChild(el('img', { src: imgSrc, alt: modelName }));
        } else {
          const initials = (modelName && modelName.trim()) ? modelName.trim().charAt(0).toUpperCase() : '?';
          tile.appendChild(el('div', { class: 'sfx-avatar sfx-avatar-muted', 'aria-hidden': 'true' }, [initials]));
        }
        tile.appendChild(el('div', { class: 'sfx-tile-label' }, [modelName]));
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
      if (!state.type) missing.push('device type');
      if (!state.make) missing.push('brand');
      if (!state.model && !shouldSkipModelSelection(state.type, state.make)) missing.push('model');
      if (!state.issues || state.issues.length === 0) missing.push('issue(s)');
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
        model: state.model || 'Any Model',
        issues: state.issues
      };

      console.log('[sfx-estimator] submitting payload', payload);

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

    function fallbackSeriesFor(type, make) {
      const t = String(type || '').toLowerCase();
      const m = String(make || '').toLowerCase();
      if (t === 'gaming console' && m === 'playstation') {
        return ['PS5', 'PS4', 'PS3'];
      }
      return [];
    }

    function seriesHasModelOptions(type, make, series) {
      if (!type || !make || !series) return false;
      const map = (((state.series_map || {})[type] || {})[make] || {});
      const list = map[series];
      if (Array.isArray(list) && list.length > 0) {
        return true;
      }
      if (typeof fallbackModelsForSeries === 'function') {
        const fallback = fallbackModelsForSeries(type, make, series);
        if (Array.isArray(fallback) && fallback.length > 0) {
          return true;
        }
      }
      return false;
    }

    function fallbackModelsForSeries(type, make, series) {
      const t = String(type || '').toLowerCase();
      const m = String(make || '').toLowerCase();
      const s = String(series || '').toLowerCase();

      // iPhone families
      const iphone = {
        '17 series': ['iPhone 17 Pro Max', 'iPhone 17 Pro', 'iPhone 17 Air', 'iPhone 17'],
        '16 series': ['iPhone 16 Pro Max', 'iPhone 16 Pro', 'iPhone 16 Plus', 'iPhone 16'],
        '15 series': ['iPhone 15 Pro Max', 'iPhone 15 Pro', 'iPhone 15 Plus', 'iPhone 15'],
        '14 series': ['iPhone 14 Pro Max', 'iPhone 14 Pro', 'iPhone 14 Plus', 'iPhone 14'],
        '13 series': ['iPhone 13 Pro Max', 'iPhone 13 Pro', 'iPhone 13', 'iPhone 13 Mini'],
        '12 series': ['iPhone 12 Pro Max', 'iPhone 12 Pro', 'iPhone 12', 'iPhone 12 Mini'],
        '11 series': ['iPhone 11 Pro Max', 'iPhone 11 Pro', 'iPhone 11'],
        'x / xs / xr series': ['iPhone XS Max', 'iPhone XS', 'iPhone XR', 'iPhone X'],
        'se series': ['iPhone SE (2022)', 'iPhone SE (2020)', 'iPhone SE (2016)'],
        '8 series': ['iPhone 8 Plus', 'iPhone 8'],
        '7 series': ['iPhone 7 Plus', 'iPhone 7'],
        '6s series': ['iPhone 6s Plus', 'iPhone 6s'],
        '6 series': ['iPhone 6 Plus', 'iPhone 6']
      };

      // Samsung phones & tablets
      const samsungS = {
        's25 series': ['Galaxy S25 Ultra', 'Galaxy S25 Plus', 'Galaxy S25'],
        's24 series': ['Galaxy S24 Ultra', 'Galaxy S24 Plus', 'Galaxy S24', 'Galaxy S24 FE'],
        's23 series': ['Galaxy S23 Ultra', 'Galaxy S23 Plus', 'Galaxy S23', 'Galaxy S23 FE'],
        's22 series': ['Galaxy S22 Ultra', 'Galaxy S22 Plus', 'Galaxy S22'],
        's21 series': ['Galaxy S21 Ultra', 'Galaxy S21 Plus', 'Galaxy S21', 'Galaxy S21 FE'],
        's20 series': ['Galaxy S20 Ultra', 'Galaxy S20 Plus', 'Galaxy S20', 'Galaxy S20 FE'],
        's10 series': ['Galaxy S10 5G', 'Galaxy S10 Plus', 'Galaxy S10e', 'Galaxy S10'],
        's9 series': ['Galaxy S9 Plus', 'Galaxy S9'],
        's8 series': ['Galaxy S8 Plus', 'Galaxy S8'],
        's7 series': ['Galaxy S7 Edge', 'Galaxy S7']
      };

      const samsungNote = {
        'note series': ['Galaxy Note 20 Ultra', 'Galaxy Note 20', 'Galaxy Note 10 Plus', 'Galaxy Note 10 Lite', 'Galaxy Note 10', 'Galaxy Note 9', 'Galaxy Note 8', 'Galaxy Note 7', 'Galaxy Note 5', 'Galaxy Note 4']
      };

      const samsungFold = {
        'fold series': ['Galaxy Z Fold 7 5G', 'Galaxy Z Fold 6 5G', 'Galaxy Z Fold 5 5G', 'Galaxy Z Fold 4 5G', 'Galaxy Z Fold 3 5G', 'Galaxy Z Fold 2 5G', 'Galaxy Fold 5G', 'Galaxy Fold 4G']
      };

      const samsungFlip = {
        'flip series': ['Galaxy Z Flip 7 FE 5G', 'Galaxy Z Flip 7 5G', 'Galaxy Z Flip 6 5G', 'Galaxy Z Flip 5 5G', 'Galaxy Z Flip 4 5G', 'Galaxy Z Flip 3 5G', 'Galaxy Z Flip 3', 'Galaxy Z Flip 2', 'Galaxy Z Flip 4G']
      };

      const samsungA = {
        'a series': ['Galaxy A90 5G', 'Galaxy A73 5G', 'Galaxy A73', 'Galaxy A72', 'Galaxy A71', 'Galaxy A70', 'Galaxy A54', 'Galaxy A53', 'Galaxy A52', 'Galaxy A51', 'Galaxy A50']
      };

      const samsungTabS = {
        'tab s series': ['Tab S10 FE 13.1\"', 'Tab S10 FE 10.9\"', 'Tab S10 Ultra 14.6\"', 'Tab S10 Plus 12.4\"', 'Tab S9 FE Plus 12.4\"', 'Tab S9 FE 10.9\"', 'Tab S9 Ultra 14.6\"', 'Tab S9 Plus 12.4\"', 'Tab S9 11\"', 'Tab S8 Ultra 14.6\"', 'Tab S8 Plus 12.4\"', 'Tab S8 11\"']
      };

      const samsungTabA = {
        'tab a series': ['Tab A9 Plus 11.0\"', 'Tab A9 8.7\"', 'Tab A8 10.5\"', 'Tab A7 Lite 8.7\"', 'Tab A7 10.4\"', 'Tab A 10.1\"', 'Tab A 8.0\"']
      };

      // Motorola phones
      const motoEdge = {
        'edge series': ['Edge 60 Pro', 'Edge 60', 'Edge 60 Stylus', 'Edge 50 Ultra', 'Edge 50 Pro', 'Edge 50', 'Edge 30 Pro', 'Edge 30 Fusion', 'Edge 30 Neo', 'Edge 20 Pro', 'Edge 20 Lite']
      };

      const motoG = {
        'g series': ['G96', 'G Stylus 5G', 'G15 Power', 'G15', 'G05', 'G Power', 'G24 Power', 'G24', 'G04', 'G Stylus', 'G5', 'G5 Plus', 'G5 Play']
      };

      // iPad families (Apple tablets)
      const ipad = {
        'ipad pro 12.9': [
          'iPad Pro 12.9\" 1st Gen (2015)',
          'iPad Pro 12.9\" 2nd Gen (2017)',
          'iPad Pro 12.9\" 3rd Gen (2018)',
          'iPad Pro 12.9\" 4th Gen (2020)',
          'iPad Pro 12.9\" 5th Gen (2021)',
          'iPad Pro 12.9\" 6th Gen (2022)',
          'iPad Pro 12.9\" 7th Gen (2024)',
          'iPad Pro 12.9\" 8th Gen (2025)',
          'iPad Pro 9.7\" (2016)'
        ],
        'ipad pro 11': [
          'iPad Pro 10.5\" (2017)',
          'iPad Pro 11\" 1st Gen (2018)',
          'iPad Pro 11\" 2nd Gen (2020)',
          'iPad Pro 11\" 3rd Gen (2021)',
          'iPad Pro 11\" 4th Gen (2022)',
          'iPad Pro 11\" 5th Gen (2021)',
          'iPad Pro 11\" 6th Gen (2022)',
          'iPad Pro 11\" 7th Gen (2024)',
          'iPad Pro 11\" 8th Gen (2025)'
        ]
        // If you later add "iPad Air", "iPad", "iPad Mini" series to your Sheet,
        // they will automatically be exposed from the CSV without needing a fallback.
      };

      // PlayStation families (Gaming Console)
      const playstation = {
        'ps5': ['PlayStation 5', 'PlayStation 5 Slim'],
        'ps5 series': ['PlayStation 5', 'PlayStation 5 Slim'],
        'playstation 5': ['PlayStation 5', 'PlayStation 5 Slim'],
        'ps4': ['PlayStation 4 Pro', 'PlayStation 4 Slim', 'PlayStation 4'],
        'ps4 series': ['PlayStation 4 Pro', 'PlayStation 4 Slim', 'PlayStation 4'],
        'playstation 4': ['PlayStation 4 Pro', 'PlayStation 4 Slim', 'PlayStation 4'],
        'ps3': ['PlayStation 3'],
        'playstation 3': ['PlayStation 3']
      };

      const table = [];
      if (t === 'cell phone' && m === 'iphone') table.push(iphone);
      if (t === 'cell phone' && m === 'samsung') table.push(samsungS, samsungNote, samsungFold, samsungFlip, samsungA);
      if (t === 'tablet' && m === 'samsung tablets') table.push(samsungTabS, samsungTabA);
      if (t === 'cell phone' && m === 'motorola') table.push(motoEdge, motoG);
      if (t === 'tablet' && m === 'apple') table.push(ipad);
      if (t === 'gaming console' && m === 'playstation') table.push(playstation);

      for (const group of table) {
        for (const key of Object.keys(group)) {
          if (key === s) {
            const list = Array.isArray(group[key]) ? group[key] : [];
            // Ensure we never return "Any Model" from fallbacks – the caller decides about that.
            return list.filter(function (name) { return name && name !== 'Any Model'; });
          }
        }
      }
      return null;
    }

    function sortSeries(list) {
      const items = (list || []).slice();

      // Custom priority for iPhone series to force newest → oldest ordering.
      const isIphone = state.type === 'Cell Phone' && String(state.make || '').toLowerCase() === 'iphone';
      const iphonePriority = [
        '17 Series', '17',
        '16 Series', '16',
        '15 Series', '15',
        '14 Series', '14',
        '13 Series', '13',
        '12 Series', '12',
        '11 Series', '11',
        '10 Series', '10',
        '9 Series',  '9',
        '8 Series',  '8',
        '7 Series',  '7',
        '6s Series', '6s',
        '6 Series',  '6',
        'SE Series', 'SE',
        'X / XS / XR Series', 'X / XS / XR', 'X', 'XS', 'XR'
      ].map(label => label.toLowerCase());

      const priorityIndex = label => {
        const cleaned = String(label || '').trim().toLowerCase();
        const idx = iphonePriority.indexOf(cleaned);
        return idx === -1 ? null : idx;
      };

      return items.sort((a, b) => {
        if (isIphone) {
          const pa = priorityIndex(a);
          const pb = priorityIndex(b);
          if (pa !== null && pb !== null && pa !== pb) return pa - pb;
          if (pa !== null && pb === null) return -1;
          if (pa === null && pb !== null) return 1;
        }

        const num = s => {
          const m = String(s).match(/(\d+)/);
          return m ? parseInt(m[1], 10) : null;
        };
        const na = num(a);
        const nb = num(b);
        if (na !== null && nb !== null && na !== nb) return nb - na; // numeric desc
        if (na !== null && nb === null) return -1;
        if (na === null && nb !== null) return 1;
        return String(a).localeCompare(String(b));
      });
    }

    function seriesListFor(type, make) {
      const map = (state.series_map && state.series_map[type] && state.series_map[type][make]) ? state.series_map[type][make] : null;
      const banned = ['general', 'any model'];
      const fromCatalog = map ? Object.keys(map).filter(function (name) {
        const key = String(name || '').trim().toLowerCase();
        return key && !banned.includes(key);
      }) : [];
      const existing = fromCatalog.map(function (name) {
        return String(name || '').trim().toLowerCase();
      });
      const fallback = fallbackSeriesFor(type, make).filter(function (name) {
        const key = String(name || '').trim().toLowerCase();
        return key && !banned.includes(key) && !existing.includes(key);
      });
      return fromCatalog.concat(fallback);
    }

    function modelsForCurrentSelection() {
      if (!state.type || !state.make) return [];
      const seriesList = seriesListFor(state.type, state.make);

      // CASE 1: series-based selection (phones, iPads, some consoles, etc.)
      if (seriesList.length && state.series) {
        const seriesMap = (((state.series_map || {})[state.type] || {})[state.make] || {});
        const bySeries = seriesMap[state.series] || [];
        const cleaned = bySeries.filter(Boolean);
        const nonAny = cleaned.filter(function (name) { return name !== 'Any Model'; });

        // Start with explicit models from the sheet, if any.
        let models = nonAny.slice();

        // Merge in any fallback models we know about for this series (iPhone, Samsung, iPad, PS, etc.).
        if (typeof fallbackModelsForSeries === 'function') {
          const fallback = fallbackModelsForSeries(state.type, state.make, state.series) || [];
          const fallbackNonAny = fallback.filter(function (name) { return name && name !== 'Any Model'; });
          models = Array.from(new Set(models.concat(fallbackNonAny)));
        }

        // If we still don't have any named models, last resort: allow "Any Model" so flow still works.
        if (!models.length) {
          return ['Any Model'];
        }

        return models;
      }

      // CASE 2: no series step for this brand (models directly under make, e.g. some consoles / computers).
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
