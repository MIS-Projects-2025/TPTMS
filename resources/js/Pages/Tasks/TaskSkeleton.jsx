// TaskSkeleton.jsx
import React from "react";
import { Skeleton } from "antd";

const TaskSkeleton = ({ isCardView = false }) => {
    if (isCardView) {
        // Grid card skeleton
        return (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {Array.from({ length: 6 }).map((_, i) => (
                    <div
                        key={i}
                        className="bg-base-100 rounded-xl shadow-md p-4"
                    >
                        <Skeleton.Avatar active size="large" shape="circle" />
                        <Skeleton active title paragraph={{ rows: 3 }} />
                    </div>
                ))}
            </div>
        );
    }

    // Table-style skeleton
    return (
        <div className="bg-base-100 rounded-xl shadow-md p-6">
            <div className="grid grid-cols-7 gap-4 mb-4">
                {Array.from({ length: 7 }).map((_, i) => (
                    <Skeleton.Input
                        key={i}
                        active
                        style={{ width: "100%", height: 20 }}
                    />
                ))}
            </div>

            {Array.from({ length: 6 }).map((_, rowIndex) => (
                <div key={rowIndex} className="grid grid-cols-7 gap-4 mb-3">
                    {Array.from({ length: 7 }).map((_, colIndex) => (
                        <Skeleton.Button
                            key={colIndex}
                            active
                            style={{ width: "100%", height: 20 }}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
};

export default TaskSkeleton;
