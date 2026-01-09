import React from "react";
import "../css/app.css";
import "./bootstrap";

import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ConfigProvider, theme as antdTheme } from "antd";
import { ThemeProvider, ThemeContext } from "../js/Components/ThemeContext";
import { NotificationProvider } from "./Context/NotificationContext";
// import Snowfall from "react-snowfall";

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

const emp_data =
    props.initialPage?.props?.emp_data ||
    props.initialPage?.props?.auth?.emp_data;

const userId = emp_data?.emp_id;

// Always clear old token first
localStorage.removeItem("authify-token");

// Then set new token if valid credentials exist
if (emp_data?.token && emp_data?.emp_id) {
    // Small delay to ensure old token is cleared
    setTimeout(() => {
        localStorage.setItem('authify-token', emp_data.token);
    }, 0);
}
        // Check if today is between Nov 5 and Dec 28
        const today = new Date();
        const month = today.getMonth() + 1; // getMonth() is 0-based
        const day = today.getDate();
        const isSnowSeason =
            (month === 11 && day >= 5) || (month === 12 && day <= 28);

        root.render(
            <React.StrictMode>
                <ThemeProvider>
                    <ThemeContext.Consumer>
                        {({ theme }) => {
                            const snowColor =
                                theme === "dark" ? "#FFFFFF" : "#a2d5f2";

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
                                            {/* {isSnowSeason && (
                                                <Snowfall
                                                    color={snowColor}
                                                    snowflakeCount={150}
                                                    radius={[1.0, 5.0]}
                                                    speed={[0.5, 2.5]}
                                                    wind={[-1.0, 1.0]}
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
                                            )} */}
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
