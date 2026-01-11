<!DOCTYPE html>
<html>
<head>
  <!-- ...existing code... -->
</head>
<body>
  <!-- ...existing code... -->
  <div id="backend-status" class="alert hidden">Backend unreachable. Running in offline mode.</div>
  <!-- ...existing code... -->
  <script>
    // ...existing code...
    const backendStatusEl = document.getElementById('backend-status');
    const showBackendError = (msg) => {
      backendStatusEl.textContent = msg;
      backendStatusEl.classList.remove('hidden');
    };
    const hideBackendError = () => backendStatusEl.classList.add('hidden');

    async function safeFetchJson(url, options = {}) {
      const res = await fetch(url, options);
      if (!res.ok) {
        const text = await res.text();
        console.error(`[backend] ${url} failed (${res.status})`, text);
        throw new Error(`Backend ${res.status}`);
      }
      return res.json();
    }

    async function fetchMe() {
      try {
        const me = await safeFetchJson('/backend/me');
        hideBackendError();
        // ...existing code that handles the user/me payload...
      } catch (err) {
        showBackendError('Unable to reach backend. Using guest mode.');
        // ...existing code to fall back to guest/offline flow...
      }
    }

    // ...existing code...

    loginButton.addEventListener('click', async (ev) => {
      ev.preventDefault();
      // ...existing code that gathers login form data...
      try {
        const payload = new FormData(loginForm);
        const result = await safeFetchJson('/backend/login', { method: 'POST', body: payload });
        hideBackendError();
        // ...existing code that handles successful login...
      } catch (err) {
        showBackendError('Login failed. Please try again later.');
        console.error('Login error:', err);
      }
    });

    // ...existing code...
  </script>
</body>
</html>