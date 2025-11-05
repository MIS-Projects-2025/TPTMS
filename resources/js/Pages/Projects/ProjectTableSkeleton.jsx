import React from "react";
import { Skeleton } from "antd";

const ProjectTableSkeleton = () => {
    return (
        <div className="bg-base-100 rounded-xl shadow-md p-6">
            {/* Table header skeleton */}
            <div className="grid grid-cols-7 gap-4 mb-4">
                {Array.from({ length: 7 }).map((_, i) => (
                    <Skeleton.Input
                        key={i}
                        active
                        style={{ width: "100%", height: 20 }}
                    />
                ))}
            </div>

            {/* Table rows skeleton */}
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

export default ProjectTableSkeleton;
