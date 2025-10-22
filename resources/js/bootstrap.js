import Echo from "laravel-echo";
import Pusher from "pusher-js";
import axios from "axios";

// Axios setup
window.axios = axios;
window.axios.defaults.withCredentials = true; // ✅ important
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// CSRF token
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
console.log("CSRF token:", csrfToken);

// Laravel Echo with Reverb
window.Pusher = Pusher;
// Echo config
window.echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "http") === "https",
    enabledTransports: ["ws", "wss"],
    disableStats: true,
    authEndpoint: "/TPTMS/broadcasting/auth",
    auth: {
        headers: {
            "X-CSRF-TOKEN": csrfToken,
            Accept: "application/json",
        },
        withCredentials: true,
    },
});
