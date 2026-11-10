/* Gestion Crédits — interactions (thème, menu, modales, vente, ajout rapide) */
(function () {
  'use strict';

  var BASE = document.body.getAttribute('data-base') || '';
  var root = document.documentElement;

  /* ---------------- Thème ---------------- */
  var saved = localStorage.getItem('theme');
  if (saved === 'light' || saved === 'dark') {
    root.setAttribute('data-theme', saved);
  }

  /* ---------------- Modales ---------------- */
  function openModal(id) {
    var el = document.getElementById(id);
    if (el) {
      el.classList.add('is-open');
      var first = el.querySelector('input, select, textarea');
      if (first) setTimeout(function () { first.focus(); }, 60);
    }
  }

  function closeModal(el) {
    if (el) el.classList.remove('is-open');
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-action]');

    if (trigger) {
      var action = trigger.getAttribute('data-action');

      if (action === 'theme') {
        var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
            return;
      }

      if (action === 'menu') {
        var sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.toggle('is-open');
        return;
      }

      if (action === 'modal-open') {
        event.preventDefault();
        openModal(trigger.getAttribute('data-target'));
        return;
      }

      if (action === 'modal-close') {
        event.preventDefault();
        closeModal(trigger.closest('.modal'));
        return;
      }

      if (action === 'confirm') {
        if (!confirm(trigger.getAttribute('data-message') || 'Confirmer ?')) {
          event.preventDefault();
        }
        return;
      }
    }

    if (event.target.classList.contains('modal-backdrop')) {
      closeModal(event.target.closest('.modal'));
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      document.querySelectorAll('.modal.is-open').forEach(closeModal);
    }
  });

  /* ---------------- Formulaire de vente ---------------- */
  function money(value) {
    return (Math.round(value * 100) / 100).toFixed(2);
  }

  function display(value) {
    return money(value).replace('.', ',');
  }

  function initSaleForm() {
    var form = document.getElementById('sale-form');
    if (!form) return;

    var lines = document.getElementById('sale-lines');
    var template = document.getElementById('sale-line-template');
    var cartProduct = document.getElementById('cart-product');
    var cartQty = document.getElementById('cart-qty');
    var cartPrice = document.getElementById('cart-price');
    var cartAdd = document.getElementById('cart-add');
    var cartEmpty = document.getElementById('sale-cart-empty');

    function refresh() {
      var subtotal = 0;
      var rowCount = 0;

      lines.querySelectorAll('.sale-line').forEach(function (row) {
        var qty = parseFloat(row.querySelector('.line-qty').value) || 0;
        var price = parseFloat(row.querySelector('.line-price').value) || 0;
        var total = qty * price;

        row.querySelector('.line-total').textContent = display(total);
        subtotal += total;
        rowCount += 1;
      });

      if (cartEmpty) {
        cartEmpty.style.display = rowCount === 0 ? '' : 'none';
      }

      var discount = parseFloat(document.getElementById('sale-discount').value) || 0;
      var total = Math.max(0, subtotal - discount);

      document.getElementById('sale-subtotal').textContent = display(subtotal);
      document.getElementById('sale-total').textContent = display(total);
      document.getElementById('sale-total-input').value = money(total);

      var paid = document.getElementById('sale-paid');

      if (paid && paid.getAttribute('data-auto') === '1') {
        paid.value = money(total);
      }

      var remaining = Math.max(0, total - (parseFloat(paid.value) || 0));
      document.getElementById('sale-remaining').textContent = display(remaining);
    }

    function addLine() {
      var node = template.content.firstElementChild.cloneNode(true);
      lines.appendChild(node);
      initSearchableSelects(node);
      refresh();
    }

    function addCartLine(productId, qty, price) {
      var node = template.content.firstElementChild.cloneNode(true);

      node.querySelector('.line-product').value = String(productId);
      node.querySelector('.line-qty').value = String(qty);
      node.querySelector('.line-price').value = Number(price).toFixed(2);
      lines.appendChild(node);
      initSearchableSelects(node);
      refresh();
    }

    // Remplit la marque et le prix à partir du produit sélectionné.
    function syncProduct(select) {
      var option = select.selectedOptions[0];
      var row = select.closest('.sale-line');

      if (!option || !row) {
        return;
      }

      var price = option.getAttribute('data-price');

      if (price !== null && price !== '') {
        row.querySelector('.line-price').value = price;
      } else if (option.value === '') {
        row.querySelector('.line-price').value = '0';
      }

      var brandInput = row.querySelector('.line-brand');
      var brand = option.getAttribute('data-brand');

      if (brandInput) {
        if (brand !== null) {
          brandInput.value = brand;
        } else if (option.value === '') {
          brandInput.value = '';
        }
      }
    }

    form.addEventListener('click', function (event) {
      if (event.target.closest('[data-add-line]')) {
        event.preventDefault();
        addLine();
      }

      var remove = event.target.closest('[data-remove-line]');
      if (remove) {
        event.preventDefault();
        remove.closest('.sale-line').remove();
        refresh();
      }
    });

    form.addEventListener('input', function (event) {
      if (event.target.matches('.line-qty, .line-price, #sale-discount, #sale-paid')) {
        if (event.target.id === 'sale-paid') {
          event.target.setAttribute('data-auto', '0');
        }
        refresh();
      }

      if (event.target.matches('.line-product')) {
        syncProduct(event.target);
        refresh();
      }
    });

    form.addEventListener('change', function (event) {
      if (event.target.matches('.line-product')) {
        syncProduct(event.target);
        refresh();
      }
    });

    form.addEventListener('submit', function (event) {
      var rows = lines.querySelectorAll('.sale-line');

      if (!rows.length) {
        event.preventDefault();
        alert('Ajoutez au moins un produit au panier.');
        return;
      }

      // Formulaire avec saisie libre (aucune liste de produits) : pas d'autre contrôle.
      var selects = lines.querySelectorAll('.line-product');

      if (!selects.length) {
        return;
      }

      var hasProduct = false;

      selects.forEach(function (select) {
        if (select.value) hasProduct = true;
      });

      if (!hasProduct) {
        event.preventDefault();
        alert('Ajoutez au moins un produit au panier.');
      }
    });

    if (cartProduct) {
      cartProduct.addEventListener('change', function () {
        var option = cartProduct.selectedOptions[0];

        if (!option || option.value === '') {
          cartPrice.value = '0';
          return;
        }

        var productPrice = option.getAttribute('data-price');

        if (productPrice !== null && productPrice !== '') {
          cartPrice.value = parseFloat(productPrice).toFixed(2);
        }

        if (!cartQty.value || parseFloat(cartQty.value) <= 0) {
          cartQty.value = '1';
        }
      });

      function addFromCart() {
        if (!cartProduct.value) {
          alert('Choisissez un produit.');
          cartProduct.focus();
          return;
        }

        var qty = parseFloat(cartQty.value) || 0;

        if (qty <= 0) {
          alert('Quantité invalide.');
          cartQty.focus();
          return;
        }

        var price = Math.max(0, parseFloat(cartPrice.value) || 0);

        addCartLine(cartProduct.value, qty, price);

        cartProduct.value = '';
        cartQty.value = '1';
        cartPrice.value = '0';
        cartProduct.focus();
      }

      cartAdd.addEventListener('click', addFromCart);

      [cartQty, cartPrice].forEach(function (input) {
        input.addEventListener('keydown', function (event) {
          if (event.key === 'Enter') {
            event.preventDefault();
            addFromCart();
          }
        });
      });
    }

    refresh();
  }

  /* ---------------- Formulaire d'un article de vente ---------------- */
  function initItemForm() {
    var form = document.getElementById('item-form');

    if (!form) {
      return;
    }

    var product = document.getElementById('item-product');
    var qty = document.getElementById('item-qty');
    var price = document.getElementById('item-price');
    var free = document.getElementById('item-free-fields');
    var totalBox = document.getElementById('item-total');
    var initial = true;

    function sync() {
      var option = product.selectedOptions[0];

      if (free) {
        free.hidden = !(option && option.value === '0');
      }

      if (!initial && option && option.value !== '0') {
        var productPrice = option.getAttribute('data-price');

        if (productPrice !== null && productPrice !== '') {
          price.value = parseFloat(productPrice).toFixed(2);
        }
      }

      initial = false;

      var lineTotal = (parseFloat(qty.value) || 0) * (parseFloat(price.value) || 0);

      if (totalBox) {
        totalBox.textContent = display(lineTotal);
      }
    }

    product.addEventListener('change', sync);
    qty.addEventListener('input', sync);
    price.addEventListener('input', sync);
    sync();
  }

  /* ---------------- Ajout rapide (produit / client) ---------------- */
  function initQuickAdd() {
    document.querySelectorAll('[data-quick-form]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();

        var button = form.querySelector('button[type="submit"]');
        button.disabled = true;

        fetch(BASE + '/index.php?p=' + form.getAttribute('data-quick-form'), {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'fetch' },
          credentials: 'same-origin',
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            button.disabled = false;

            if (!data || data.error) {
              alert((data && data.error) || 'Erreur');
              return;
            }

            var select = document.getElementById(form.getAttribute('data-target'));

            if (!select && form.getAttribute('data-target') === '__line-product__') {
              var selects = document.querySelectorAll('#sale-lines .line-product');
              select = selects[selects.length - 1];
            }

            if (select) {
              var valueKey = form.getAttribute('data-value');
              var value = valueKey && data[valueKey] !== undefined ? data[valueKey] : data.id;

              var option = document.createElement('option');
              option.value = String(value);
              option.textContent = data.name;
              if (data.price !== undefined) option.setAttribute('data-price', data.price);
              if (data.brand !== undefined) option.setAttribute('data-brand', data.brand);
              select.appendChild(option);
              select.value = String(value);
              select.dispatchEvent(new Event('change', { bubbles: true }));
            }

            closeModal(form.closest('.modal'));
            form.reset();
          })
          .catch(function () {
            button.disabled = false;
            alert('Erreur réseau');
          });
      });
    });
  }

  /* ---------------- Tableaux : recherche, tri, pagination ---------------- */
  var tableText = document.documentElement.lang === 'ar'
    ? {
        sort: 'ترتيب',
        rows: 'صفوف',
        all: 'الكل',
        none: 'لا توجد نتائج',
        page: 'صفحة',
        prev: 'السابق',
        next: 'التالي',
        filterSelect: 'تصفية…',
        noMatch: 'لا توجد صفوف مطابقة.',
      }
    : {
        sort: 'Trier',
        rows: 'Lignes',
        all: 'Toutes',
        none: 'Aucun résultat',
        page: 'Page',
        prev: 'Précédent',
        next: 'Suivant',
        filterSelect: 'Filtrer…',
        noMatch: 'Aucune ligne ne correspond au filtre.',
      };

  var tablePagers = [];

  function isTableEligible(table) {
    return table.classList.contains('table')
      && !table.classList.contains('pos-table')
      && !table.querySelector('#sale-lines')
      && !table.querySelector('.sale-line')
      && !table.hasAttribute('data-nopage')
      && !table.hasAttribute('data-nosort');
  }

  function tableRows(table) {
    var tbody = table.tBodies[0];

    if (!tbody) {
      return [];
    }

    return Array.prototype.slice.call(tbody.rows).filter(function (row) {
      return !row.classList.contains('empty') && row.cells.length > 1;
    });
  }

  function defaultPageSize() {
    var stored = parseInt(localStorage.getItem('tablePageSize') || '', 10);

    if ([0, 25, 50, 100, 250].indexOf(stored) !== -1) {
      return stored;
    }

    return 50;
  }

  /* Filtre rapide (input[data-search="#table-id"]) */
  function initTableSearch() {
    document.querySelectorAll('[data-search]').forEach(function (input) {
      input.addEventListener('input', function () {
        var table = document.querySelector(input.getAttribute('data-search'));
        if (!table) return;

        table.__tableTerm = input.value.trim().toLowerCase();

        if (typeof table.__refreshPager === 'function') {
          table.__refreshPager(true);
        }
      });
    });
  }

  /* Pagination : 50 lignes par défaut, choix 25 / 50 / 100 / 250 / tout */
  function initTablePaging() {
    var size = defaultPageSize();

    document.querySelectorAll('table.table').forEach(function (table) {
      if (!isTableEligible(table)) {
        return;
      }

      var tbody = table.tBodies[0];
      var rows = tableRows(table);

      if (!tbody || rows.length === 0) {
        return;
      }

      table.__tableRows = rows;
      table.__tableTerm = table.__tableTerm || '';
      table.__tableColumn = typeof table.__tableColumn === 'number' ? table.__tableColumn : -1;

      var state = { page: 1, size: size };
      var anchor = table.closest('.table-wrap') || table;
      var pager = document.createElement('div');
      pager.className = 'table-pager no-print';

      var info = document.createElement('div');
      info.className = 'pager-info';

      var sizeWrap = document.createElement('label');
      sizeWrap.className = 'pager-size';
      sizeWrap.innerHTML = '<span>' + tableText.rows + '</span>';

      var select = document.createElement('select');
      [25, 50, 100, 250, 0].forEach(function (value) {
        var option = document.createElement('option');
        option.value = String(value);
        option.textContent = value === 0 ? tableText.all : String(value);
        if (value === state.size) option.selected = true;
        select.appendChild(option);
      });
      sizeWrap.appendChild(select);

      var nav = document.createElement('div');
      nav.className = 'pager-nav';

      var prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'pager-btn';
      prev.setAttribute('aria-label', tableText.prev);
      prev.innerHTML = '&lsaquo;';

      var label = document.createElement('span');
      label.className = 'pager-page';

      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'pager-btn';
      next.setAttribute('aria-label', tableText.next);
      next.innerHTML = '&rsaquo;';

      nav.appendChild(prev);
      nav.appendChild(label);
      nav.appendChild(next);
      pager.appendChild(info);
      pager.appendChild(sizeWrap);
      pager.appendChild(nav);

      // Le sélecteur de pagination n'apparaît que s'il y a plus d'une page.
      if (rows.length > 10) {
        anchor.parentNode.insertBefore(pager, anchor.nextSibling);
      }

      function refresh(resetPage) {
        if (resetPage) state.page = 1;

        var term = (table.__tableTerm || '').trim();
        var compactTerm = term.replace(/\s+/g, '');
        var column = parseInt(table.__tableColumn, 10);
        var visible = term === ''
          ? rows
          : rows.filter(function (row) {
              var target = column >= 0 && row.cells[column] ? row.cells[column] : row;
              var haystack = target.textContent.toLowerCase();

              return haystack.indexOf(term) !== -1
                || (compactTerm !== '' && haystack.replace(/\s+/g, '').indexOf(compactTerm) !== -1);
            });

        var total = visible.length;

        if (table.__filterCount) {
          table.__filterCount.textContent = term === ''
            ? String(rows.length)
            : total + ' / ' + rows.length;
        }

        if (table.__filterEmpty) {
          table.__filterEmpty.style.display = total === 0 ? '' : 'none';
        }
        var perPage = state.size > 0 ? state.size : Math.max(total, 1);
        var pages = Math.max(1, Math.ceil(total / perPage));

        if (state.page > pages) state.page = pages;
        if (state.page < 1) state.page = 1;

        var start = (state.page - 1) * perPage;
        var end = Math.min(start + perPage, total);

        rows.forEach(function (row) {
          row.style.display = 'none';
        });

        visible.slice(start, end).forEach(function (row) {
          row.style.display = '';
        });

        info.textContent = total === 0
          ? tableText.none
          : (start + 1) + '–' + end + ' / ' + total;

        label.textContent = tableText.page + ' ' + state.page + ' / ' + pages;
        prev.disabled = state.page <= 1;
        next.disabled = state.page >= pages;
        nav.style.visibility = pages > 1 ? '' : 'hidden';
      }

      select.addEventListener('change', function () {
        state.size = parseInt(select.value, 10) || 0;
        localStorage.setItem('tablePageSize', String(state.size));
        refresh(true);
      });

      prev.addEventListener('click', function () {
        if (state.page > 1) {
          state.page -= 1;
          refresh(false);
        }
      });

      next.addEventListener('click', function () {
        state.page += 1;
        refresh(false);
      });

      table.__refreshPager = refresh;
      tablePagers.push(refresh);
      refresh(false);
    });
  }

  /* Listes déroulantes filtrables : recherche intégrée au menu */
  var activeSelectPanel = null;

  function closeSelectPanel() {
    if (!activeSelectPanel) {
      return;
    }

    activeSelectPanel.panel.remove();
    activeSelectPanel.select.setAttribute('aria-expanded', 'false');
    activeSelectPanel = null;
  }

  function openSelectPanel(select) {
    closeSelectPanel();

    var panel = document.createElement('div');
    panel.className = 'ss-panel';

    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'ss-search';
    search.placeholder = tableText.filterSelect;
    search.setAttribute('aria-label', tableText.filterSelect);
    search.autocomplete = 'off';

    var list = document.createElement('div');
    list.className = 'ss-list';
    list.setAttribute('role', 'listbox');

    var empty = document.createElement('div');
    empty.className = 'ss-empty';
    empty.textContent = tableText.noMatch;

    panel.appendChild(search);
    panel.appendChild(list);
    panel.appendChild(empty);
    document.body.appendChild(panel);

    var rect = select.getBoundingClientRect();
    var width = Math.max(rect.width, 220);
    width = Math.min(width, window.innerWidth - 16);
    var left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
    var below = window.innerHeight - rect.bottom - 8;
    var above = rect.top - 8;
    var placeBelow = below >= 220 || below >= above;
    var available = Math.max(160, Math.min(360, placeBelow ? below : above));

    panel.style.left = left + 'px';
    panel.style.width = width + 'px';
    panel.style.maxHeight = available + 'px';

    if (placeBelow) {
      panel.style.top = (rect.bottom + 4) + 'px';
    } else {
      panel.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
    }

    var activeIndex = -1;
    var optionButtons = [];

    function choose(value) {
      closeSelectPanel();
      select.value = value;
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
      select.focus();
    }

    function matches(label, term) {
      var text = label.toLowerCase();
      var compact = term.replace(/\s+/g, '');

      return text.indexOf(term) !== -1
        || (compact !== '' && text.replace(/\s+/g, '').indexOf(compact) !== -1);
    }

    function highlight(index) {
      optionButtons.forEach(function (button, i) {
        button.classList.toggle('is-active', i === index);
      });

      activeIndex = index;

      var current = optionButtons[index];

      if (current) {
        var listRect = list.getBoundingClientRect();
        var itemRect = current.getBoundingClientRect();

        if (itemRect.top < listRect.top) {
          list.scrollTop -= listRect.top - itemRect.top + 6;
        } else if (itemRect.bottom > listRect.bottom) {
          list.scrollTop += itemRect.bottom - listRect.bottom + 6;
        }
      }
    }

    function build(term) {
      list.innerHTML = '';
      optionButtons = [];
      activeIndex = -1;

      var query = term.trim().toLowerCase();
      var shown = 0;

      Array.prototype.forEach.call(select.options, function (option) {
        if (option.disabled) {
          return;
        }

        var label = option.textContent.trim();

        if (label === '' || (query !== '' && !matches(label, query))) {
          return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'ss-option';
        button.setAttribute('role', 'option');
        button.dataset.value = option.value;
        button.textContent = label;

        if (option.selected) {
          button.classList.add('is-selected');
          button.setAttribute('aria-selected', 'true');
        }

        button.addEventListener('click', function () {
          choose(option.value);
        });

        list.appendChild(button);
        optionButtons.push(button);
        shown += 1;
      });

      panel.classList.toggle('has-empty', shown === 0);

      var selected = list.querySelector('.is-selected');

      if (selected) {
        list.scrollTop = Math.max(0, selected.offsetTop - list.clientHeight / 2);
      }
    }

    search.addEventListener('input', function () {
      build(search.value);
    });

    search.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeSelectPanel();
        select.focus();
      } else if (event.key === 'ArrowDown') {
        event.preventDefault();

        if (optionButtons.length) {
          highlight(Math.min(optionButtons.length - 1, activeIndex + 1));
        }
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();

        if (optionButtons.length) {
          highlight(Math.max(0, activeIndex - 1));
        }
      } else if (event.key === 'Enter') {
        event.preventDefault();

        if (activeIndex >= 0 && optionButtons[activeIndex]) {
          optionButtons[activeIndex].click();
        } else if (optionButtons.length === 1) {
          optionButtons[0].click();
        }
      }
    });

    build('');
    select.setAttribute('aria-expanded', 'true');
    activeSelectPanel = { panel: panel, select: select };
    search.focus({ preventScroll: true });
  }

  function initSearchableSelects(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var selects = root && root.tagName === 'SELECT'
      ? [root]
      : Array.prototype.slice.call(scope.querySelectorAll('select'));

    selects.forEach(function (select) {
      if (select.dataset.searchable === '1'
        || select.multiple
        || select.disabled
        || select.hasAttribute('data-nosearch')) {
        return;
      }

      if (select.options.length <= 2) {
        return;
      }

      select.dataset.searchable = '1';
      select.setAttribute('aria-haspopup', 'listbox');
      select.setAttribute('aria-expanded', 'false');

      function togglePanel() {
        if (activeSelectPanel && activeSelectPanel.select === select) {
          closeSelectPanel();
        } else {
          openSelectPanel(select);
        }
      }

      select.addEventListener('pointerdown', function (event) {
        event.preventDefault();
        select.__pointerHandled = Date.now();
        togglePanel();
      });

      select.addEventListener('mousedown', function (event) {
        if (Date.now() - (select.__pointerHandled || 0) < 600) {
          return;
        }

        event.preventDefault();
        togglePanel();
      });

      select.addEventListener('click', function (event) {
        event.preventDefault();
      });

      select.addEventListener('keydown', function (event) {
        if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].indexOf(event.key) !== -1) {
          event.preventDefault();
          openSelectPanel(select);
        } else if (event.key === 'Escape') {
          closeSelectPanel();
        }
      });

      // Cliquer sur le libellé du champ ouvre aussi la liste.
      var field = select.closest('.field, label');

      if (field && field !== select) {
        field.addEventListener('click', function (event) {
          if (event.target.closest('select, button, a, input, textarea')) {
            return;
          }

          event.preventDefault();
          event.stopPropagation();
          togglePanel();
        });
      }
    });
  }

  document.addEventListener('click', function (event) {
    if (!activeSelectPanel) {
      return;
    }

    if (activeSelectPanel.panel.contains(event.target) || activeSelectPanel.select === event.target) {
      return;
    }

    closeSelectPanel();
  });

  window.addEventListener('resize', closeSelectPanel);

  window.addEventListener('scroll', function (event) {
    if (activeSelectPanel && !activeSelectPanel.panel.contains(event.target)) {
      closeSelectPanel();
    }
  }, true);

  /* Tri générique : dates, nombres, textes (accents / arabe) */
  function parseSortValue(text) {
    var t = (text || '').trim();

    if (t === '' || t === '—' || t === '-' || t === '–') {
      return null;
    }

    // Date jj/mm/aaaa ou jj/mm/aaaa hh:mm
    var date = t.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);

    if (date) {
      return {
        type: 'number',
        value: Date.UTC(+date[3], +date[2] - 1, +date[1], +(date[4] || 0), +(date[5] || 0)),
      };
    }

    // Nombre : espaces, devise, % et virgule décimale tolérés
    var number = t.replace(/\s/g, '').replace(/[^\d,.\-]/g, '').replace(',', '.');

    if (number !== '' && /^-?\d+(\.\d+)?$/.test(number)) {
      return { type: 'number', value: parseFloat(number) };
    }

    return { type: 'text', value: t.toLocaleLowerCase() };
  }

  function sortTable(table, index, th) {
    var tbody = table.tBodies[0];

    if (!tbody) {
      return;
    }

    var rows = table.__tableRows || tableRows(table);
    var direction = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';

    table.querySelectorAll('th').forEach(function (header) {
      header.removeAttribute('data-sort-dir');
      header.classList.remove('sort-asc', 'sort-desc');
    });

    th.setAttribute('data-sort-dir', direction);
    th.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');

    rows.sort(function (a, b) {
      var va = parseSortValue(a.cells[index] ? a.cells[index].textContent : '');
      var vb = parseSortValue(b.cells[index] ? b.cells[index].textContent : '');

      if (va === null && vb === null) return 0;
      if (va === null) return 1;
      if (vb === null) return -1;

      var result;

      if (va.type === 'number' && vb.type === 'number') {
        result = va.value - vb.value;
      } else {
        result = String(va.value).localeCompare(String(vb.value), undefined, {
          numeric: true,
          sensitivity: 'base',
        });
      }

      return direction === 'asc' ? result : -result;
    });

    rows.forEach(function (row) {
      tbody.appendChild(row);
    });

    if (typeof table.__refreshPager === 'function') {
      table.__refreshPager(true);
    }
  }

  function initTableSort() {
    document.querySelectorAll('table.table').forEach(function (table) {
      if (!isTableEligible(table)) {
        return;
      }

      var thead = table.tHead;

      if (!thead || !thead.rows.length) {
        return;
      }

      Array.prototype.forEach.call(thead.rows[0].cells, function (th, index) {
        if (th.classList.contains('actions') || th.hasAttribute('data-nosort') || !th.textContent.trim()) {
          return;
        }

        th.classList.add('sortable');
        th.setAttribute('tabindex', '0');
        th.setAttribute('role', 'button');
        th.setAttribute('title', tableText.sort);

        th.addEventListener('click', function () {
          sortTable(table, index, th);
        });

        th.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            sortTable(table, index, th);
          }
        });
      });
    });
  }

  /* Impression : toutes les lignes, quelle que soit la page affichée */
  window.addEventListener('beforeprint', function () {
    document.querySelectorAll('table.table tbody tr').forEach(function (row) {
      row.style.display = '';
    });
  });

  window.addEventListener('afterprint', function () {
    tablePagers.forEach(function (refresh) {
      refresh(false);
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    initSaleForm();
    initItemForm();
    initQuickAdd();
    initTableSort();
    initTablePaging();
    initSearchableSelects(document);
    initTableSearch();
  });
})();
