const TRUE_VALUES = new Set(['1', 'true', 'yes', 'on']);

export function parseBoolean(value, fallback = false) {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }

  return TRUE_VALUES.has(String(value).trim().toLowerCase());
}

export function parseAllowedHosts(value) {
  if (!value) {
    return [];
  }

  if (Array.isArray(value)) {
    return value.map((host) => String(host).trim().toLowerCase()).filter(Boolean);
  }

  const normalized = String(value).trim();

  try {
    if (normalized.startsWith('[')) {
      const parsed = JSON.parse(normalized);
      if (Array.isArray(parsed)) {
        return parsed.map((host) => String(host).trim().toLowerCase()).filter(Boolean);
      }
    }
  } catch {
    // Fallback to CSV parsing.
  }

  return normalized
    .split(',')
    .map((host) => host.trim().toLowerCase())
    .filter(Boolean);
}

export function normalizeBaseUrl(value) {
  if (!value) {
    throw new Error('ARCHIVE_BASE_URL is required for desktop runtime');
  }

  const url = new URL(value);

  if (!['http:', 'https:'].includes(url.protocol)) {
    throw new Error(`ARCHIVE_BASE_URL must be http/https. Received: ${url.protocol}`);
  }

  url.pathname = '/';
  url.search = '';
  url.hash = '';

  return url.toString().replace(/\/$/, '');
}

export function loadDesktopRuntimeConfig(env = process.env) {
  const baseUrl = normalizeBaseUrl(env.ARCHIVE_BASE_URL);
  const baseHost = new URL(baseUrl).host.toLowerCase();
  const configuredAllowlist = parseAllowedHosts(env.ARCHIVE_ALLOWED_HOSTS);
  const allowedHosts = Array.from(new Set([baseHost, ...configuredAllowlist]));

  return {
    appName: env.ARCHIVE_DESKTOP_APP_NAME || 'ArchiveMaster Desktop',
    appVersion: env.ARCHIVE_DESKTOP_APP_VERSION || '0.1.0',
    instanceName: env.ARCHIVE_INSTANCE_NAME || 'ArchiveMaster',
    environmentLabel: env.ARCHIVE_ENV_LABEL || 'production',
    baseUrl,
    allowedHosts,
    enableInstanceSwitch: parseBoolean(env.ARCHIVE_ENABLE_INSTANCE_SWITCH, false),
    itModeEnabled: parseBoolean(env.ARCHIVE_IT_MODE_ENABLED, false),
    itSupportPinHash: env.ARCHIVE_IT_SUPPORT_PIN_HASH || null,
    clientHeaderName: env.ARCHIVE_CLIENT_HEADER_NAME || 'X-ArchiveMaster-Client',
    clientHeaderValue:
      env.ARCHIVE_CLIENT_HEADER_VALUE
      || `desktop/${env.ARCHIVE_DESKTOP_APP_VERSION || '0.1.0'} (${env.ARCHIVE_INSTANCE_NAME || 'ArchiveMaster'})`,
  };
}
