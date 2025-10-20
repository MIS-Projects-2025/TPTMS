import "../css/app.css";
import "./bootstrap";

import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ConfigProvider, theme as antdTheme } from "antd";
import { ThemeProvider, ThemeContext } from "../js/Components/ThemeContext";
import { NotificationProvider } from "./Context/NotificationContext";

const rawAppName = import.meta.env.VITE_APP_NAME || "Laravel";
const appName = rawAppName
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (char) => char.toUpperCase());
createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx")
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <ThemeProvider>
                <ThemeContext.Consumer>
                    {({ theme }) => (
                        <ConfigProvider
                            theme={{
                                algorithm:
                                    theme === "dark"
                                        ? antdTheme.darkAlgorithm
                                        : antdTheme.defaultAlgorithm,
                            }}
                        >
                            <NotificationProvider
                                userId={props.emp_data?.emp_id}
                            >
                                <App {...props} />
                            </NotificationProvider>
                        </ConfigProvider>
                    )}
                </ThemeContext.Consumer>
            </ThemeProvider>
        );
    },
});
