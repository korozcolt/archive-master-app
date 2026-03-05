export function isUrlAllowed(targetUrl, allowedHosts) {
  const parsed = new URL(targetUrl);

  if (!['http:', 'https:'].includes(parsed.protocol)) {
    return false;
  }

  return allowedHosts.includes(parsed.host.toLowerCase());
}

export function classifyNavigation(targetUrl, allowedHosts) {
  if (isUrlAllowed(targetUrl, allowedHosts)) {
    return 'internal';
  }

  return 'external';
}

export function assertInternalNavigation(targetUrl, allowedHosts) {
  if (!isUrlAllowed(targetUrl, allowedHosts)) {
    throw new Error(`Blocked navigation to non-allowed host: ${targetUrl}`);
  }
}
