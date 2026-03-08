#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

use serde::Deserialize;
use tauri::plugin::{Builder as PluginBuilder, TauriPlugin};
use tauri::webview::PageLoadEvent;
use tauri::{Manager, Runtime, Url, Webview};
use tauri_plugin_opener::OpenerExt;

const RUNTIME_CONFIG_JSON: &str = include_str!("../../dist/runtime-config.json");

#[derive(Debug, Clone, Deserialize)]
#[serde(rename_all = "camelCase")]
struct RuntimeConfig {
    app_name: String,
    app_version: String,
    instance_name: String,
    environment_label: String,
    base_url: String,
    allowed_hosts: Vec<String>,
    enable_instance_switch: bool,
    it_mode_enabled: bool,
    it_support_pin_hash: Option<String>,
    client_header_name: String,
    client_header_value: String,
}

impl RuntimeConfig {
    fn load() -> Self {
        serde_json::from_str(RUNTIME_CONFIG_JSON)
            .expect("desktop runtime config must be rendered before starting Tauri")
    }

    fn is_internal_url(&self, url: &Url) -> bool {
        if !matches!(url.scheme(), "http" | "https") {
            return false;
        }

        url.host_str()
            .map(|host| {
                let normalized_host = host.to_lowercase();

                self.allowed_hosts
                    .iter()
                    .any(|allowed_host| allowed_host.eq_ignore_ascii_case(&normalized_host))
            })
            .unwrap_or(false)
    }

    fn allows_embedded_navigation(&self, url: &Url) -> bool {
        if matches!(url.scheme(), "about" | "data" | "blob") {
            return true;
        }

        self.is_internal_url(url)
    }
}

fn build_client_header_injection_script(header_name: &str, header_value: &str) -> String {
    let safe_name = serde_json::to_string(header_name).expect("header name must be serializable");
    let safe_value = serde_json::to_string(header_value).expect("header value must be serializable");

    format!(
        r#"(() => {{
    const HEADER_NAME = {safe_name};
    const HEADER_VALUE = {safe_value};

    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, init = {{}}) => {{
      const headers = new Headers(init.headers || {{}});
      headers.set(HEADER_NAME, HEADER_VALUE);
      return originalFetch(input, {{ ...init, headers }});
    }};

    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (...args) {{
      this.__archiveMasterHeaders = this.__archiveMasterHeaders || [];
      this.__archiveMasterHeaders.push([HEADER_NAME, HEADER_VALUE]);
      return originalOpen.apply(this, args);
    }};

    XMLHttpRequest.prototype.send = function (...args) {{
      if (Array.isArray(this.__archiveMasterHeaders)) {{
        for (const [name, value] of this.__archiveMasterHeaders) {{
          try {{
            this.setRequestHeader(name, value);
          }} catch {{
            // Ignore header errors for unsupported targets.
          }}
        }}
      }}

      return originalSend.apply(this, args);
    }};
  }})();"#
    )
}

fn build_runtime_bridge_script(config: &RuntimeConfig) -> String {
    let payload = serde_json::json!({
        "appName": config.app_name,
        "appVersion": config.app_version,
        "instanceName": config.instance_name,
        "environmentLabel": config.environment_label,
        "baseUrl": config.base_url,
        "allowedHosts": config.allowed_hosts,
        "enableInstanceSwitch": config.enable_instance_switch,
        "itModeEnabled": config.it_mode_enabled,
        "hasItSupportPin": config.it_support_pin_hash.is_some(),
        "clientHeaderName": config.client_header_name,
        "clientHeaderValue": config.client_header_value,
        "desktopShell": true
    });

    format!(
        r#"(() => {{
    window.__ARCHIVE_MASTER_DESKTOP__ = Object.freeze({payload});
    window.dispatchEvent(new CustomEvent('archivemaster:desktop-ready', {{
      detail: window.__ARCHIVE_MASTER_DESKTOP__,
    }}));
  }})();"#,
        payload = payload
    )
}

fn desktop_shell_plugin<R: Runtime>(runtime_config: RuntimeConfig) -> TauriPlugin<R> {
    let navigation_config = runtime_config.clone();
    let page_load_config = runtime_config.clone();

    PluginBuilder::new("desktop-shell")
        .on_navigation(move |webview: &Webview<R>, url: &Url| {
            if navigation_config.allows_embedded_navigation(url) {
                return true;
            }

            let _ = webview
                .app_handle()
                .opener()
                .open_url(url.to_string(), None::<String>);

            false
        })
        .on_page_load(move |webview: &Webview<R>, payload| {
            if payload.event() != PageLoadEvent::Finished {
                return;
            }

            if !page_load_config.is_internal_url(payload.url()) {
                return;
            }

            let _ = webview.eval(&build_client_header_injection_script(
                &page_load_config.client_header_name,
                &page_load_config.client_header_value,
            ));

            let _ = webview.eval(&build_runtime_bridge_script(&page_load_config));
        })
        .build()
}

fn main() {
    let runtime_config = RuntimeConfig::load();

    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_opener::init())
        .plugin(desktop_shell_plugin(runtime_config))
        .run(tauri::generate_context!())
        .expect("error while running ArchiveMaster Desktop");
}

#[cfg(test)]
mod tests {
    use super::{
        build_client_header_injection_script, build_runtime_bridge_script, RuntimeConfig,
    };
    use tauri::Url;

    fn sample_config() -> RuntimeConfig {
        RuntimeConfig {
            app_name: "ArchiveMaster Desktop".into(),
            app_version: "0.1.0".into(),
            instance_name: "ArchiveMaster Producción".into(),
            environment_label: "production".into(),
            base_url: "https://archive.kronnos.dev".into(),
            allowed_hosts: vec!["archive.kronnos.dev".into()],
            enable_instance_switch: false,
            it_mode_enabled: false,
            it_support_pin_hash: None,
            client_header_name: "X-ArchiveMaster-Client".into(),
            client_header_value: "desktop/0.1.0 (ArchiveMaster Producción)".into(),
        }
    }

    #[test]
    fn runtime_config_allows_internal_hosts_only() {
        let config = sample_config();

        let allowed_url = Url::parse("https://archive.kronnos.dev/documents").unwrap();
        let blocked_url = Url::parse("https://google.com").unwrap();
        let data_url = Url::parse("data:text/plain,hello").unwrap();

        assert!(config.is_internal_url(&allowed_url));
        assert!(!config.is_internal_url(&blocked_url));
        assert!(config.allows_embedded_navigation(&data_url));
        assert!(!config.allows_embedded_navigation(&blocked_url));
    }

    #[test]
    fn client_header_script_contains_runtime_header_contract() {
        let script =
            build_client_header_injection_script("X-ArchiveMaster-Client", "desktop/0.1.0");

        assert!(script.contains("X-ArchiveMaster-Client"));
        assert!(script.contains("desktop/0.1.0"));
        assert!(script.contains("window.fetch"));
        assert!(script.contains("XMLHttpRequest.prototype.send"));
    }

    #[test]
    fn runtime_bridge_script_exposes_desktop_metadata() {
        let script = build_runtime_bridge_script(&sample_config());

        assert!(script.contains("__ARCHIVE_MASTER_DESKTOP__"));
        assert!(script.contains("ArchiveMaster Producción"));
        assert!(script.contains("archivemaster:desktop-ready"));
        assert!(script.contains("\"desktopShell\":true"));
    }
}
