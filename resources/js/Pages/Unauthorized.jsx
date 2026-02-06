import React, { useEffect, useState } from "react";
import { Result, Button, Progress } from "antd";
import { LockOutlined } from "@ant-design/icons";

export default function Unauthorized({ message, logoutUrl }) {
    const [seconds, setSeconds] = useState(8);

    useEffect(() => {
        if (seconds <= 0) {
            window.location.href = logoutUrl;
            return;
        }
        const timer = setTimeout(() => setSeconds((s) => s - 1), 1000);
        return () => clearTimeout(timer);
    }, [seconds, logoutUrl]);

    const percent = ((8 - seconds) / 8) * 100; // Progress circle percentage

    return (
        <div className="flex items-center justify-center min-h-screen px-6 bg-gray-100 dark:bg-gray-900">
            <div className="text-center">
                <div className="inline-block mb-4">
                    <Progress
                        type="circle"
                        percent={percent}
                        width={120}
                        strokeColor="#1890ff"
                        format={() => (
                            <LockOutlined
                                style={{ fontSize: 48, color: "#1890ff" }}
                            />
                        )}
                    />
                </div>
                <h1 className="text-5xl font-bold text-gray-800 dark:text-gray-100 mb-2">
                    Unauthorized
                </h1>
                <p className="text-lg text-gray-500 dark:text-gray-400 mb-2">
                    {message ||
                        "You do not have the necessary authorization to access this system."}
                </p>
                <p className="text-lg text-gray-500 dark:text-gray-400 mb-4">
                    Redirecting to login in{" "}
                    <span className="font-semibold">{seconds}</span> seconds...
                </p>
                <Button
                    type="primary"
                    size="large"
                    onClick={() => (window.location.href = logoutUrl)}
                >
                    Go Back
                </Button>
            </div>
        </div>
    );
}
