import React from "react";
import { Skeleton, Card } from "antd";

const TicketFormSkeleton = () => {
    return (
        <>
            {/* Stat Cards Skeleton */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                {[1, 2, 3, 4, 5].map((item) => (
                    <Card
                        key={item}
                        className="shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                    >
                        <div className="flex items-center justify-between">
                            <div className="flex-1">
                                <Skeleton.Input
                                    active
                                    size="small"
                                    style={{ width: 80, marginBottom: 8 }}
                                />
                                <Skeleton.Input
                                    active
                                    size="large"
                                    style={{ width: 50 }}
                                />
                            </div>
                            <Skeleton.Avatar
                                active
                                size="default"
                                shape="circle"
                            />
                        </div>
                    </Card>
                ))}
            </div>

            {/* Main Content Area */}
            <div className="p-6 bg-base-200 min-h-screen transition-all duration-300 border border-base-300 rounded-xl shadow-sm">
                {/* Filters Skeleton */}
                <div className="flex flex-wrap justify-between items-center mb-4 gap-3">
                    <div className="flex items-center gap-2">
                        <Skeleton.Avatar
                            active
                            size="small"
                            shape="circle"
                            style={{ width: 16, height: 16 }}
                        />
                        <Skeleton.Input
                            active
                            size="small"
                            style={{ width: 256 }}
                        />
                    </div>

                    <div className="flex gap-2 flex-wrap">
                        <Skeleton.Input
                            active
                            size="small"
                            style={{ width: 180 }}
                        />
                        <Skeleton.Button
                            active
                            size="small"
                            style={{ width: 100 }}
                        />
                    </div>
                </div>

                {/* Table Skeleton */}
                <div className="bg-base-100 rounded-xl shadow-md p-4">
                    {/* Table Header */}
                    <div className="grid grid-cols-6 gap-4 mb-4 pb-4 border-b border-base-300">
                        <Skeleton.Input active size="small" />
                        <Skeleton.Input active size="small" />
                        <Skeleton.Input active size="small" />
                        <Skeleton.Input active size="small" />
                        <Skeleton.Input active size="small" />
                        <Skeleton.Input active size="small" />
                    </div>

                    {/* Table Rows */}
                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((row) => (
                        <div
                            key={row}
                            className="grid grid-cols-6 gap-4 mb-3 pb-3 border-b border-base-200"
                        >
                            <div>
                                <Skeleton.Input
                                    active
                                    size="small"
                                    style={{ width: "100%" }}
                                />
                            </div>
                            <div>
                                <Skeleton.Input
                                    active
                                    size="small"
                                    style={{ width: "100%" }}
                                />
                            </div>
                            <div>
                                <Skeleton.Input
                                    active
                                    size="small"
                                    style={{ width: "90%" }}
                                />
                            </div>
                            <div>
                                <Skeleton.Button
                                    active
                                    size="small"
                                    shape="round"
                                    style={{ width: 80 }}
                                />
                            </div>
                            <div>
                                <Skeleton.Button
                                    active
                                    size="small"
                                    shape="round"
                                    style={{ width: 90 }}
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <Skeleton.Avatar
                                    active
                                    size="small"
                                    shape="circle"
                                />
                                <Skeleton.Button
                                    active
                                    size="small"
                                    style={{ width: 30 }}
                                />
                            </div>
                        </div>
                    ))}

                    {/* Pagination Skeleton */}
                    <div className="flex justify-between items-center mt-4 pt-4 border-t border-base-300">
                        <Skeleton.Input
                            active
                            size="small"
                            style={{ width: 150 }}
                        />
                        <div className="flex gap-2">
                            <Skeleton.Button
                                active
                                size="small"
                                style={{ width: 32, height: 32 }}
                            />
                            <Skeleton.Button
                                active
                                size="small"
                                style={{ width: 32, height: 32 }}
                            />
                            <Skeleton.Button
                                active
                                size="small"
                                style={{ width: 32, height: 32 }}
                            />
                            <Skeleton.Button
                                active
                                size="small"
                                style={{ width: 32, height: 32 }}
                            />
                            <Skeleton.Button
                                active
                                size="small"
                                style={{ width: 32, height: 32 }}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default TicketFormSkeleton;
