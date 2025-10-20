import axios from "axios";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;
Pusher.logToConsole = true;

// Axios default
window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Echo setup
window.Echo = new Echo({
    broadcaster: "pusher",
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true, // set to true if using HTTPS
});
