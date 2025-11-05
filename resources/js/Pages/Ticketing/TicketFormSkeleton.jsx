import React from "react";
import { Skeleton, Card } from "antd";

const TicketFormSkeleton = () => {
    return (
        <div className="flex justify-center">
            <div className="card w-full max-w-4xl shadow-xl bg-base-200">
                <div className="card-body p-6 space-y-5">
                    {/* Info Alert Skeleton */}
                    <Skeleton.Button
                        active
                        size="large"
                        block
                        style={{ height: 48 }}
                    />

                    {/* Employee Info Skeleton */}
                    <Card className="bg-base-100 border border-base-300">
                        <div className="flex justify-between gap-4">
                            <div className="flex-1">
                                <Skeleton
                                    active
                                    paragraph={{ rows: 1, width: "60%" }}
                                    title={false}
                                />
                            </div>
                            <div className="flex-1">
                                <Skeleton
                                    active
                                    paragraph={{ rows: 1, width: "70%" }}
                                    title={false}
                                />
                            </div>
                            <div className="flex-1">
                                <Skeleton
                                    active
                                    paragraph={{ rows: 1, width: "65%" }}
                                    title={false}
                                />
                            </div>
                        </div>
                    </Card>

                    {/* Form Fields Skeleton */}
                    <div className="space-y-4">
                        {/* First Row - 3 Columns */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <Skeleton.Input
                                    active
                                    size="small"
                                    block
                                    style={{ marginBottom: 8 }}
                                />
                                <Skeleton.Input active size="large" block />
                            </div>
                            <div>
                                <Skeleton.Input
                                    active
                                    size="small"
                                    block
                                    style={{ marginBottom: 8 }}
                                />
                                <Skeleton.Input active size="large" block />
                            </div>
                            <div>
                                <Skeleton.Input
                                    active
                                    size="small"
                                    block
                                    style={{ marginBottom: 8 }}
                                />
                                <Skeleton.Input active size="large" block />
                            </div>
                        </div>

                        {/* Request Details */}
                        <div>
                            <Skeleton.Input
                                active
                                size="small"
                                block
                                style={{ marginBottom: 8 }}
                            />
                            <Skeleton.Input
                                active
                                size="large"
                                block
                                style={{ height: 120 }}
                            />
                        </div>

                        {/* Attachments */}
                        <div>
                            <Skeleton.Input
                                active
                                size="small"
                                block
                                style={{ marginBottom: 8 }}
                            />
                            <Skeleton.Button
                                active
                                size="large"
                                block
                                style={{ height: 100 }}
                            />
                        </div>

                        {/* Submit Button */}
                        <Skeleton.Button
                            active
                            size="large"
                            block
                            style={{ height: 48, marginTop: 16 }}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
};

export default TicketFormSkeleton;
