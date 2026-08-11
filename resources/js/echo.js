import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = document.querySelector('meta[name="reverb-key"]')?.getAttribute('content') || import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = document.querySelector('meta[name="reverb-host"]')?.getAttribute('content') || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const reverbScheme = document.querySelector('meta[name="reverb-scheme"]')?.getAttribute('content') || import.meta.env.VITE_REVERB_SCHEME || 'https';
const reverbPort = document.querySelector('meta[name="reverb-port"]')?.getAttribute('content') || import.meta.env.VITE_REVERB_PORT || '443';

const wsPort = Number(reverbPort);
const wssPort = Number(reverbPort);

if (reverbKey && reverbKey !== '${REVERB_APP_KEY}' && reverbKey !== 'undefined') {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort,
        wssPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
