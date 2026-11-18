// Modal sederhana: <div class="modal" id="x"><div class="modal-content">…</div></div>
// Tutup dengan tombol [data-close], klik latar, atau tombol Esc.

export function openModal(modal) {
  modal.hidden = false;
  modal.querySelector('input:not([type=hidden]):not([disabled]), select, textarea')?.focus();
}

export function closeModal(modal) {
  modal.hidden = true;
}

let wired = false;
export function wireModals() {
  if (wired) return;
  wired = true;
  document.addEventListener('click', (e) => {
    const closer = e.target.closest('[data-close]');
    if (closer) closeModal(closer.closest('.modal'));
    else if (e.target.classList.contains('modal')) closeModal(e.target);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = [...document.querySelectorAll('.modal:not([hidden])')].pop();
    if (open) closeModal(open);
  });
}

export async function confirmAction(message) {
  return window.confirm(message);
}
