import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const cookieToken = document.cookie
	.split('; ')
	.find((row) => row.startsWith('XSRF-TOKEN='))
	?.split('=')[1];
const resolvedToken = csrfToken ?? (cookieToken ? decodeURIComponent(cookieToken) : undefined);

if (resolvedToken) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = resolvedToken;
}

function shouldEnableEcho() {
	return document.querySelector('meta[name="enable-echo"]')?.getAttribute('content') === 'true';
}

function resolveWebSocketConfig() {
	const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
	const configuredHost = import.meta.env.VITE_REVERB_HOST;
	const fallbackHost = window.location.hostname;
	const host = configuredHost && configuredHost !== '127.0.0.1' ? configuredHost : fallbackHost;
	const configuredPort = import.meta.env.VITE_REVERB_PORT;
	const defaultPort = scheme === 'https' ? 443 : 80;
	const locationPort = window.location.port ? Number(window.location.port) : undefined;
	const port = configuredPort ? Number(configuredPort) : locationPort ?? defaultPort;

	return {
		scheme,
		host,
		port,
		forceTLS: scheme === 'https',
	};
}

export function initializeEcho(force = false) {
	if (window.Echo) {
		return window.Echo;
	}

	if (!force && !shouldEnableEcho()) {
		return null;
	}

	const websocket = resolveWebSocketConfig();

	window.Echo = new Echo({
		broadcaster: 'reverb',
		key: import.meta.env.VITE_REVERB_APP_KEY,
		wsHost: websocket.host,
		wsPort: websocket.port,
		wssPort: websocket.port,
		forceTLS: websocket.forceTLS,
		enabledTransports: ['ws', 'wss'],
		authEndpoint: '/broadcasting/auth',
		auth: resolvedToken
			? {
				  headers: {
					  'X-CSRF-TOKEN': resolvedToken,
					  'X-Requested-With': 'XMLHttpRequest',
				  },
				  withCredentials: true,
			  }
			: undefined,
	});

	return window.Echo;
}

initializeEcho();
