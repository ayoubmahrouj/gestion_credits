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

    function refresh() {
      var subtotal = 0;

      lines.querySelectorAll('.sale-line').forEach(function (row) {
        var qty = parseFloat(row.querySelector('.line-qty').value) || 0;
        var price = parseFloat(row.querySelector('.line-price').value) || 0;
        var total = qty * price;

        row.querySelector('.line-total').textContent = display(total);
        subtotal += total;
      });

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
      refresh();
    }

    form.addEventListener('click', function (event) {
      if (event.target.closest('[data-add-line]')) {
        event.preventDefault();
        addLine();
      }

      var remove = event.target.closest('[data-remove-line]');
      if (remove) {
        event.preventDefault();
        var rows = lines.querySelectorAll('.sale-line');
        if (rows.length > 1) {
          remove.closest('.sale-line').remove();
          refresh();
        }
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
        var option = event.target.selectedOptions[0];
        var price = option ? option.getAttribute('data-price') : null;
        if (price) {
          event.target.closest('.sale-line').querySelector('.line-price').value = price;
        }
        refresh();
      }
    });

    form.addEventListener('change', function (event) {
      if (event.target.matches('.line-product')) {
        var option = event.target.selectedOptions[0];
        var price = option ? option.getAttribute('data-price') : null;
        if (price) {
          event.target.closest('.sale-line').querySelector('.line-price').value = price;
        }
        refresh();
      }
    });

    form.addEventListener('submit', function (event) {
      var selects = lines.querySelectorAll('.line-product');

      // Formulaire avec saisie libre (aucune liste de produits) : pas de contrôle.
      if (!selects.length) {
        return;
      }

      var hasProduct = false;

      selects.forEach(function (select) {
        if (select.value) hasProduct = true;
      });

      if (!hasProduct) {
        event.preventDefault();
        alert('Ajoutez au moins un produit.');
      }
    });

    if (lines.children.length === 0) addLine();
    refresh();
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

  /* ---------------- Filtre rapide des tableaux ---------------- */
  function initTableSearch() {
    document.querySelectorAll('[data-search]').forEach(function (input) {
      input.addEventListener('input', function () {
        var table = document.querySelector(input.getAttribute('data-search'));
        if (!table) return;

        var term = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
          row.style.display = row.textContent.toLowerCase().indexOf(term) === -1 ? 'none' : '';
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSaleForm();
    initQuickAdd();
    initTableSearch();
  });
})();
