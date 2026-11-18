// Klien API: api.php?r=<resource>. Selalu mengirim token CSRF; error dilempar sebagai ApiError berisi pesan dari server.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

function url(resource, params = {}) {
  const q = new URLSearchParams({ r: resource });
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') q.set(k, Array.isArray(v) ? v.join(',') : v);
  }
  return `api.php?${q}`;
}

async function request(method, resource, { params, body, form } = {}) {
  const headers = { 'X-CSRF-Token': csrf(), Accept: 'application/json' };
  let payload;
  if (form) {
    payload = form;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(url(resource, params), { method, headers, body: payload, credentials: 'same-origin' });
  } catch {
    throw new ApiError('Tidak bisa terhubung ke server', 0);
  }

  let json = null;
  try {
    json = await res.json();
  } catch {
    // respons bukan JSON (mis. error PHP mentah)
  }
  if (res.status === 401 && resource !== 'auth/login') {
    window.location.href = 'index.php';
  }
  if (!res.ok || !json || json.ok !== true) {
    throw new ApiError(json?.error || `Terjadi kesalahan (HTTP ${res.status})`, res.status);
  }
  return json.data;
}

export const api = {
  get: (resource, params) => request('GET', resource, { params }),
  post: (resource, body, params) => request('POST', resource, { body, params }),
  put: (resource, body, params) => request('PUT', resource, { body, params }),
  del: (resource, params, body) => request('DELETE', resource, { params, body }),
  upload: (resource, form, params) => request('POST', resource, { form, params }),
  url,
};
