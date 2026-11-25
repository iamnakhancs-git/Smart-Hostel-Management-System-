/* ============================================================
   Custom JavaScript — Smart Hostel Management System
   ============================================================ */

$(function () {

  /* ---- Search every dropdown, including dropdowns inside a modal ---- */
  if ($.fn.select2) {
    $('select').not('[data-search="off"]').each(function () {
      var select = $(this);
      var modal = select.closest('.modal');
      select.select2({
        width: select.closest('.form-inline').length ? 'resolve' : '100%',
        minimumResultsForSearch: 0,
        dropdownParent: modal.length ? modal : $(document.body)
      });
      // Existing page scripts sometimes listen through addEventListener.
      select.on('select2:select select2:unselect', function () {
        this.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    $(document).on('select2:open', function () {
      var search = document.querySelector('.select2-container--open .select2-search__field');
      if (search) search.focus();
    });
  }

  /* ---- ID format follows the selected document type ---- */
  var rulesElement = document.getElementById('identity-rules');
  if (rulesElement) {
    var identityRules = JSON.parse(rulesElement.textContent);
    var idType = document.getElementById('id_proof_type');
    var idNumber = document.getElementById('id_proof_number');
    var help = document.getElementById('identity-help');
    function updateIdentityRule() {
      var rule = identityRules[idType.value];
      idNumber.required = !!rule;
      idNumber.maxLength = rule ? rule.maxLength : 20;
      idNumber.setAttribute('inputmode', rule ? rule.inputmode : 'text');
      if (rule) idNumber.setAttribute('pattern', rule.pattern);
      else idNumber.removeAttribute('pattern');
      if (help) help.textContent = rule ? rule.hint : 'Choose an ID type to enter its number.';
      idNumber.setCustomValidity(!rule && idNumber.value ? 'Select the ID proof type first.' : '');
    }
    $(idType).on('change', updateIdentityRule);
    idNumber.addEventListener('input', updateIdentityRule);
    updateIdentityRule();
  }

  /* ---- Current password is required only when setting a new password ---- */
  $('form').each(function () {
    var form = $(this), old = form.find('[name="old_password"]');
    if (!old.length) return;
    var next = form.find('[name="password"]'), confirm = form.find('[name="password_confirm"]');
    function updatePasswordFields() {
      var changing = next.val() !== '' || confirm.val() !== '';
      old.prop('required', changing);
      next.prop('required', changing);
      confirm.prop('required', changing);
    }
    next.add(confirm).on('input', updatePasswordFields);
    updatePasswordFields();
  });

  /* ---- Admin confirms their own password before resetting a resident password ---- */
  $('form').each(function () {
    var form = $(this), current = form.find('[name="admin_current_password"]');
    if (!current.length) return;
    var next = form.find('[name="password"]'), confirm = form.find('[name="password_confirm"]');
    function updateAdminResetFields() {
      var changing = next.val() !== '' || confirm.val() !== '';
      current.prop('required', changing); next.prop('required', changing); confirm.prop('required', changing);
    }
    next.add(confirm).on('input', updateAdminResetFields); updateAdminResetFields();
  });

  /* ---- Initialise DataTables ---- */
  if ($.fn.DataTable) {
    $('.data-table').each(function () {
      var table = $(this);
      var emptyMessage = 'No records found.';
      var rows = table.children('tbody').children('tr');
      // Legacy pages render one spanning placeholder when their result is empty.
      // Remove only that placeholder before DataTables parses real data rows.
      if (rows.length === 1 && rows.eq(0).children('td').length === 1 && rows.eq(0).children('td').is('[colspan]')) {
        emptyMessage = rows.eq(0).text().trim();
        rows.remove();
      }
      table.DataTable({
        language: { search: 'Search:', lengthMenu: 'Show _MENU_ entries', emptyTable: emptyMessage },
        pageLength: 10,
        // Preserve the page's server-side order. List pages already request
        // newest records first; sorting the display-only # column reversed it.
        order: []
      });
    });
  }

  /* ---- Confirm delete ---- */
  $(document).on('click', '.btn-delete', function (e) {
    if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
      e.preventDefault();
    }
  });

  /* ---- Auto-dismiss alerts ---- */
  setTimeout(function () {
    $('.alert-dismissible').fadeOut('slow');
  }, 4000);

  /* ---- Print bill ---- */
  $(document).on('click', '#printBill', function () {
    window.print();
  });

  /* ---- Toggle password visibility ---- */
  $(document).on('click', '.toggle-password', function () {
    var input = $($(this).data('target'));
    var icon  = $(this).find('i');
    if (input.attr('type') === 'password') {
      input.attr('type', 'text');
      icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
      input.attr('type', 'password');
      icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
  });

  /* ---- Select-all checkbox ---- */
  $(document).on('change', '#selectAll', function () {
    $('.row-checkbox').prop('checked', $(this).is(':checked'));
  });

});
