import React from "react";
import "../css/app.css";
import "./bootstrap";

import { createRoot } from "react-dom/client";
import { createInertiaApp, router } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ConfigProvider, theme as antdTheme } from "antd";
import { ThemeProvider, ThemeContext } from "../js/Components/ThemeContext";
import { NotificationProvider } from "./Context/NotificationContext";
// import { Toaster } from "sonner";
import axios from "axios";

// Force full page redirect on auth errors — prevents login modal overlay
router.on("invalid", (event) => {
    event.preventDefault();
    window.location.href = event.detail.response.url ?? "/";
});

// Auto reload on CSRF expiry
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            window.location.reload();
        }
        return Promise.reject(error);
    },
);

const rawAppName = import.meta.env.VITE_APP_NAME || "Laravel";
const appName = rawAppName
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (char) => char.toUpperCase());

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx"),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Get userId from auth.emp_data (JWT session) only
        // REMOVED: localStorage token storage — JWT lives in cookie/session
        const emp_data = props.initialPage?.props?.emp_data;
        const userId = emp_data?.emp_id;

        root.render(
            <React.StrictMode>
                <ThemeProvider>
                    <ThemeContext.Consumer>
                        {({ theme }) => (
                            <>
                                {/* <Toaster
                                    richColors
                                    position="top-center"
                                    theme={theme}
                                /> */}
                                <ConfigProvider
                                    theme={{
                                        algorithm:
                                            theme === "dark"
                                                ? antdTheme.darkAlgorithm
                                                : antdTheme.defaultAlgorithm,
                                    }}
                                >
                                    <NotificationProvider userId={userId}>
                                        <div style={{ position: "relative" }}>
                                            <App {...props} />
                                        </div>
                                    </NotificationProvider>
                                </ConfigProvider>
                            </>
                        )}
                    </ThemeContext.Consumer>
                </ThemeProvider>
            </React.StrictMode>,
        );
    },
});
