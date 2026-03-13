import { useState, useEffect } from "react";
import { Sun, Moon } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";

export default function ThemeToggler({ toggleTheme, theme, isCollapsed }) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => setMounted(true), []);
    if (!mounted) return null;

    const isDark = theme === "dark";

    if (isCollapsed) {
        return (
            <button
                onClick={toggleTheme}
                className="w-8 h-8 rounded-full bg-gray-800 hover:bg-gray-700 flex items-center justify-center text-gray-300 hover:text-white transition-colors mx-auto"
                title={isDark ? "Switch to Light Mode" : "Switch to Dark Mode"}
            >
                {isDark ? (
                    <Moon className="w-4 h-4" />
                ) : (
                    <Sun className="w-4 h-4" />
                )}
            </button>
        );
    }

    return (
        <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-gray-400">Theme</span>
            <motion.button
                onClick={toggleTheme}
                className="relative w-14 h-7 rounded-full cursor-pointer select-none overflow-hidden"
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                title={isDark ? "Switch to Light Mode" : "Switch to Dark Mode"}
            >
                {/* Background with smooth day/night gradient */}
                <motion.div
                    className="absolute inset-0 rounded-full"
                    animate={{
                        background: isDark
                            ? "linear-gradient(135deg, #1e3a8a, #4f46e5)" // night
                            : "linear-gradient(135deg, #facc15, #f97316)", // day
                    }}
                    transition={{ duration: 0.6 }}
                />

                {/* Sliding ball */}
                <motion.div
                    className="absolute top-[2px] z-10 w-6 h-6 bg-white rounded-full flex items-center justify-center shadow-md"
                    animate={{ left: isDark ? 28 : 2 }}
                    transition={{ type: "spring", stiffness: 300, damping: 20 }}
                >
                    {/* Icon */}
                    <AnimatePresence mode="wait">
                        <motion.div
                            key={isDark ? "dark" : "light"}
                            initial={{ rotate: -90, opacity: 0 }}
                            animate={{ rotate: 0, opacity: 1 }}
                            exit={{ rotate: 90, opacity: 0 }}
                            transition={{ duration: 0.2 }}
                        >
                            {isDark ? (
                                <Moon className="w-3 h-3 text-purple-600" />
                            ) : (
                                <Sun className="w-3 h-3 text-yellow-500" />
                            )}
                        </motion.div>
                    </AnimatePresence>
                </motion.div>
            </motion.button>
        </div>
    );
}
