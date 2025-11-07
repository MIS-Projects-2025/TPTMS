import React, { useEffect, useState } from "react";

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
    return (
        <div className="flex items-center justify-center min-h-screen px-6 bg-gray-100 dark:bg-gray-900">
            <div className="text-center">
                <h1 className="text-[60pt] font-bold text-gray-800 dark:text-gray-100 mb-0">
                    Unauthorized
                </h1>

                <p className="text-lg text-gray-500 dark:text-gray-400">
                    {message ||
                        "You do not have the necessary authorization to access this system."}
                </p>
                <p className="text-lg text-gray-500 dark:text-gray-400">
                    Redirecting to the login page in{" "}
                    <span className="font-semibold">{seconds}</span> seconds...
                </p>
                <button
                    className="btn btn-soft btn-primary mt-4"
                    onClick={() => (window.location.href = logoutUrl)}
                >
                    Go Back
                </button>
            </div>
        </div>
    );
}
