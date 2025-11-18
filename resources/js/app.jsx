import React from "react";
import "../css/app.css";
import "./bootstrap";

import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ConfigProvider, theme as antdTheme } from "antd";
import { ThemeProvider, ThemeContext } from "../js/Components/ThemeContext";
import { NotificationProvider } from "./Context/NotificationContext";
import Snowfall from "react-snowfall";

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

        const userId =
            props.initialPage?.props?.emp_data?.emp_id ||
            props.initialPage?.props?.auth?.emp_data?.emp_id;

        root.render(
            <React.StrictMode>
                <ThemeProvider>
                    <ThemeContext.Consumer>
                        {({ theme }) => {
                            // ❄️ Color depends on theme
                            const snowColor =
                                theme === "dark" ? "#FFFFFF" : "#a2d5f2"; // white for dark, icy blue for light

                            return (
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
                                            {/* ❄️ Realistic snowfall overlay */}
                                            <Snowfall
                                                color={snowColor}
                                                snowflakeCount={150}
                                                radius={[1.0, 5.0]} // random flake sizes
                                                speed={[0.5, 2.5]} // random fall speeds
                                                wind={[-1.0, 1.0]} // gentle side drift
                                                style={{
                                                    position: "fixed",
                                                    top: 0,
                                                    left: 0,
                                                    width: "100vw",
                                                    height: "100vh",
                                                    zIndex: 9999,
                                                    pointerEvents: "none",
                                                }}
                                            />
                                            <App {...props} />
                                        </div>
                                    </NotificationProvider>
                                </ConfigProvider>
                            );
                        }}
                    </ThemeContext.Consumer>
                </ThemeProvider>
            </React.StrictMode>
        );
    },
});
