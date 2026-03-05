export function buildClientHeaderInjectionScript(headerName, headerValue) {
  const safeName = JSON.stringify(headerName);
  const safeValue = JSON.stringify(headerValue);

  return `(() => {
    const HEADER_NAME = ${safeName};
    const HEADER_VALUE = ${safeValue};

    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, init = {}) => {
      const headers = new Headers(init.headers || {});
      headers.set(HEADER_NAME, HEADER_VALUE);
      return originalFetch(input, { ...init, headers });
    };

    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (...args) {
      this.__archiveMasterHeaders = this.__archiveMasterHeaders || [];
      this.__archiveMasterHeaders.push([HEADER_NAME, HEADER_VALUE]);
      return originalOpen.apply(this, args);
    };

    XMLHttpRequest.prototype.send = function (...args) {
      if (Array.isArray(this.__archiveMasterHeaders)) {
        for (const [name, value] of this.__archiveMasterHeaders) {
          try {
            this.setRequestHeader(name, value);
          } catch {
            // Ignore header errors for non-http targets.
          }
        }
      }

      return originalSend.apply(this, args);
    };
  })();`;
}
