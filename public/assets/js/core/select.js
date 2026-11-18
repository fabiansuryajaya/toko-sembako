// Dropdown dengan pencarian (Select2). jQuery & Select2 dimuat global oleh app.php.

const $j = window.jQuery;

/**
 * Isi <select> lalu aktifkan Select2.
 * @param {HTMLSelectElement} el
 * @param {Array} items
 * @param {{value?: (x)=>any, label: (x)=>string, placeholder?: string, dropdownParent?: Element}} opt
 */
export function fillSelect(el, items, { value = (x) => x.id, label, placeholder = 'Pilih', dropdownParent } = {}) {
  const current = $j(el).val();
  el.innerHTML = '';
  if (!el.multiple) el.add(new Option('', ''));
  for (const item of items) el.add(new Option(label(item), value(item)));
  if ($j(el).data('select2')) $j(el).select2('destroy');
  $j(el).select2({
    placeholder,
    allowClear: true,
    width: '100%',
    dropdownParent: dropdownParent ? $j(dropdownParent) : undefined,
  });
  if (current) setSelect(el, current);
}

export function setSelect(el, value) {
  $j(el).val(value).trigger('change');
}

export function getSelect(el) {
  return $j(el).val();
}

export function onSelect(el, handler) {
  $j(el).on('select2:select', handler);
}

// Fokus langsung ke kotak pencarian saat dropdown dibuka
$j(document).on('select2:open', () => {
  setTimeout(() => document.querySelector('.select2-container--open .select2-search__field')?.focus(), 0);
});
