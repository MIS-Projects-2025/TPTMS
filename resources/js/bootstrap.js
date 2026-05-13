import Echo from "laravel-echo";
import Pusher from "pusher-js";
import axios from "axios";

// Axios setup
window.axios = axios;
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Pusher setup with FULL debug logging
window.Pusher = Pusher;
Pusher.logToConsole = true;

// Echo configuration for SSL
window.echo = new Echo({
    broadcaster: "pusher",
    key: "34bt7ihktudxw8thfeuy",
    cluster: "mt1",
    wsHost: "192.168.2.221",
    wsPort: 6001, // Apache SSL port
    wssPort: 6001, // Same as above
    forceTLS: false,
    disableStats: true,
    enabledTransports: ["ws"],
    authEndpoint: "http://192.168.2.221:8192/TPTMS/broadcasting/auth",
    auth: {
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                ?.content,
            Accept: "application/json",
        },
        withCredentials: true,
    },
});

// Test connection
window.echo.connector.pusher.connection.bind("connected", () => {
    console.log("✅ Connected to Soketi WebSocket server!");
});

window.echo.connector.pusher.connection.bind("error", (err) => {
    console.error("❌ WebSocket connection error:", err);
});
