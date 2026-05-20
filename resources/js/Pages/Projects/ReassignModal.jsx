import React, { useState, useEffect } from "react";
import { Modal, Select, Avatar, Tooltip, Spin, message } from "antd";
import { UserSwitchOutlined } from "@ant-design/icons";
import axios from "axios";

const { Option } = Select;

export default function ReassignModal({ isOpen, onClose, project, onSuccess }) {
    const [programmers, setProgrammers] = useState([]);
    const [loadingProgrammers, setLoadingProgrammers] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (isOpen) {
            fetchProgrammers();
            setSelectedIds(
                project?.assigned_to?.map((p) => p.emp_id) ?? []
            );
        }
    }, [isOpen, project]);

    const fetchProgrammers = async () => {
        setLoadingProgrammers(true);
        try {
            const res = await axios.get("/api/projects/programmers");
            if (res.data.success) {
                setProgrammers(res.data.programmers ?? []);
            }
        } catch (err) {
            message.error("Failed to load programmers");
        } finally {
            setLoadingProgrammers(false);
        }
    };

    const handleSave = async () => {
        if (selectedIds.length === 0) {
            message.warning("Please select at least one programmer");
            return;
        }

        setSaving(true);
        try {
            await axios.patch(`/api/projects/${project.id}/assigned-to`, {
                assigned_ids: selectedIds,
            });
            message.success("Assigned programmers updated successfully");
            onClose();
            if (onSuccess) onSuccess();
        } catch (err) {
            const msg =
                err.response?.data?.error ||
                err.response?.data?.message ||
                "Failed to update assigned programmers";
            message.error(msg);
        } finally {
            setSaving(false);
        }
    };

    const handleClose = () => {
        setSelectedIds([]);
        onClose();
    };

    return (
        <Modal
            title={
                <div className="flex items-center gap-2">
                    <UserSwitchOutlined className="text-blue-500" />
                    <span>Reassign Programmers</span>
                </div>
            }
            open={isOpen}
            onCancel={handleClose}
            onOk={handleSave}
            okText="Save"
            confirmLoading={saving}
            width={480}
        >
            {project && (
                <p className="text-sm text-gray-500 mb-4">
                    Project:{" "}
                    <span className="font-semibold text-gray-700">
                        {project.name}
                    </span>
                </p>
            )}

            {loadingProgrammers ? (
                <div className="flex justify-center py-6">
                    <Spin />
                </div>
            ) : (
                <Select
                    mode="multiple"
                    placeholder="Select programmers to assign"
                    value={selectedIds}
                    onChange={setSelectedIds}
                    optionFilterProp="label"
                    showSearch
                    allowClear
                    size="large"
                    className="w-full"
                    options={programmers.map((p) => ({
                        label: `${p.EMPLOYID} - ${p.EMPNAME}`,
                        value: p.EMPLOYID,
                    }))}
                />
            )}
        </Modal>
    );
}
