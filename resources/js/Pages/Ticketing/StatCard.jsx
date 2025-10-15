import React from "react";

export default function StatCard({
    title,
    value,
    color,
    icon: Icon,
    onClick,
    isActive,
    filterType,
}) {
    return (
        <div
            className={`card cursor-pointer transition-all duration-300 border shadow-md hover:shadow-lg
                ${
                    isActive
                        ? "bg-base-100 border-primary"
                        : "bg-base-200 border-base-300 hover:bg-base-100"
                }`}
            onClick={() => onClick(filterType)}
        >
            <div className="card-body p-4 flex flex-row items-center justify-between">
                <div>
                    <p className={`text-sm font-medium text-${color}`}>
                        {title}
                    </p>
                    <p className={`text-2xl font-bold text-${color}`}>
                        {value}
                    </p>
                </div>
                <Icon className={`text-${color} text-3xl`} />
            </div>
        </div>
    );
}
