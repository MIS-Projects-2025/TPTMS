// Create.jsx
import React, { useState } from "react";
import { Form, Select, Input, Button, Alert, message } from "antd";
import { Ticket } from "lucide-react";
import { useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { usePage } from "@inertiajs/react";
import AttachmentUpload from "./AttachmentUpload";
import { useTicketForm } from "./../../Hooks/useTicketForm";

const { Option } = Select;

const Create = () => {
    const selectStyle = { height: "4.5rem" };
    const selectDropdownStyle = { borderRadius: "0.5rem" };

    const {
        emp_data,
        requestTypes = [],
        ticketOptions = [],
        ticketProjects = {},
        projectOptions = [],
        employeeOptions = [],
    } = usePage().props;

    // Use Inertia's useForm hook
    const { data, setData, post, processing, errors, reset } = useForm({
        request_type: null,
        project: null,
        project_name: null,
        parent_ticket: null,
        testers: [],
        details: "",
        attachments: [],
    });

    // Custom hook for form logic
    const {
        filteredParentTickets,
        isNewSystem,
        isTesting,
        handleRequestTypeChange,
        handleProjectChange,
        handleParentTicketChange,
    } = useTicketForm({
        requestType: data.request_type,
        selectedProject: data.project,
        selectedParentTicket: data.parent_ticket,
        ticketOptions,
        ticketProjects,
        onProjectChange: (value) => setData("project", value),
        onParentTicketChange: (value) => setData("parent_ticket", value),
    });

    const handleSubmit = (e) => {
        // Create FormData for file upload
        const formData = new FormData();

        // Append all form fields
        formData.append("request_type", data.request_type);

        if (isNewSystem) {
            formData.append("project_name", data.project_name);
        } else {
            formData.append("project", data.project);
            if (data.parent_ticket) {
                formData.append("parent_ticket", data.parent_ticket);
            }
        }

        if (isTesting && data.testers.length > 0) {
            data.testers.forEach((tester, index) => {
                formData.append(`testers[${index}]`, tester);
            });
        }

        formData.append("details", data.details);

        // Append attachments
        data.attachments.forEach((file, index) => {
            if (file instanceof File) {
                formData.append(`attachments[${index}]`, file);
            }
        });
        // Submit using Inertia
        post(route("tickets.store"), {
            data: formData,
            forceFormData: true,
            onSuccess: () => {
                message.success("Ticket created successfully!");
                reset();
            },
            onError: (errors) => {
                message.error("Please check the form for errors.");
                console.error(errors);
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <div className="text-center px-6 mb-6">
                <h1 className="text-3xl font-bold mb-1">Ticketing System</h1>
                <p className="text-base-content/60 text-sm">
                    Generate a new ticket by filling out the form below.
                </p>
            </div>

            <div className="flex justify-center">
                <div className="card w-full max-w-4xl shadow-xl bg-base-200">
                    <div className="card-body p-6 space-y-5">
                        <Alert
                            message="Please fill out all required fields."
                            type="info"
                            showIcon
                            className="rounded-lg"
                        />

                        <div className="bg-base-100 border border-base-300 rounded-lg p-4 flex justify-between text-sm mb-4">
                            <div className="flex flex-col">
                                <span className="text-xs text-base-content/50 uppercase">
                                    Employee ID
                                </span>
                                <span className="font-semibold">
                                    {emp_data.emp_id}
                                </span>
                            </div>
                            <div className="flex flex-col">
                                <span className="text-xs text-base-content/50 uppercase">
                                    Name
                                </span>
                                <span className="font-semibold">
                                    {emp_data.emp_name}
                                </span>
                            </div>
                            <div className="flex flex-col">
                                <span className="text-xs text-base-content/50 uppercase">
                                    Department
                                </span>
                                <span className="font-semibold">
                                    {emp_data.emp_dept}
                                </span>
                            </div>
                        </div>

                        <Form
                            layout="vertical"
                            onFinish={handleSubmit}
                            className="space-y-4"
                        >
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {/* Request Type */}
                                <Form.Item
                                    label="Type of Request"
                                    required
                                    validateStatus={
                                        errors.request_type ? "error" : ""
                                    }
                                    help={errors.request_type}
                                >
                                    <Select
                                        placeholder="Choose request type"
                                        value={data.request_type}
                                        onChange={(value) => {
                                            setData("request_type", value);
                                            handleRequestTypeChange(value);
                                        }}
                                        styles={{
                                            popup: {
                                                root: selectDropdownStyle,
                                            },
                                        }}
                                        className="w-full rounded-lg text-sm h-10 "
                                        showSearch
                                        optionFilterProp="children"
                                    >
                                        {requestTypes.map((rt) => (
                                            <Option
                                                key={rt.value}
                                                value={rt.value}
                                            >
                                                {rt.label}
                                            </Option>
                                        ))}
                                    </Select>
                                </Form.Item>

                                {/* Project Input / Select */}
                                {isNewSystem ? (
                                    <Form.Item
                                        label="Project Name"
                                        required
                                        className="col-span-1 md:col-span-2"
                                        validateStatus={
                                            errors.project_name ? "error" : ""
                                        }
                                        help={errors.project_name}
                                    >
                                        <Input
                                            placeholder="Enter project name"
                                            value={data.project_name}
                                            onChange={(e) =>
                                                setData(
                                                    "project_name",
                                                    e.target.value
                                                )
                                            }
                                            className="input input-bordered w-full rounded-lg text-sm h-10"
                                        />
                                    </Form.Item>
                                ) : (
                                    <>
                                        <Form.Item
                                            label="Project"
                                            required
                                            validateStatus={
                                                errors.project ? "error" : ""
                                            }
                                            help={errors.project}
                                        >
                                            <Select
                                                placeholder="Select project"
                                                value={data.project}
                                                onChange={(value) => {
                                                    setData("project", value);
                                                    handleProjectChange(value);
                                                }}
                                                showSearch
                                                optionFilterProp="children"
                                                styles={{
                                                    popup: {
                                                        root: selectDropdownStyle,
                                                    },
                                                }}
                                                className="w-full rounded-lg text-sm h-10"
                                            >
                                                {projectOptions.map((p) => (
                                                    <Option
                                                        key={p.value}
                                                        value={p.value}
                                                    >
                                                        {p.label}
                                                    </Option>
                                                ))}
                                            </Select>
                                        </Form.Item>

                                        <Form.Item
                                            label="Parent Ticket"
                                            validateStatus={
                                                errors.parent_ticket
                                                    ? "error"
                                                    : ""
                                            }
                                            help={errors.parent_ticket}
                                        >
                                            <Select
                                                placeholder="Select parent ticket"
                                                value={data.parent_ticket}
                                                onChange={(value) => {
                                                    setData(
                                                        "parent_ticket",
                                                        value
                                                    );
                                                    handleParentTicketChange(
                                                        value
                                                    );
                                                }}
                                                allowClear
                                                showSearch
                                                optionFilterProp="children"
                                                styles={{
                                                    popup: {
                                                        root: selectDropdownStyle,
                                                    },
                                                }}
                                                className="w-full rounded-lg text-sm h-10"
                                            >
                                                {filteredParentTickets.map(
                                                    (t) => (
                                                        <Option
                                                            key={t.value}
                                                            value={t.value}
                                                        >
                                                            {t.label}
                                                        </Option>
                                                    )
                                                )}
                                            </Select>
                                        </Form.Item>
                                    </>
                                )}
                            </div>

                            {/* Assign Tester only for Testing requests */}
                            {isTesting && (
                                <Form.Item
                                    label="Assign Tester"
                                    validateStatus={
                                        errors.testers ? "error" : ""
                                    }
                                    help={errors.testers}
                                >
                                    <Select
                                        mode="multiple"
                                        placeholder="Select tester(s)"
                                        value={data.testers}
                                        onChange={(value) =>
                                            setData("testers", value)
                                        }
                                        styles={{
                                            popup: {
                                                root: selectDropdownStyle,
                                            },
                                        }}
                                        className="w-full rounded-lg text-sm h-10"
                                        showSearch
                                        optionFilterProp="children"
                                    >
                                        {employeeOptions.map((emp) => (
                                            <Option
                                                key={emp.value}
                                                value={emp.value}
                                            >
                                                {emp.label}
                                            </Option>
                                        ))}
                                    </Select>
                                </Form.Item>
                            )}

                            <Form.Item
                                label="Request Details"
                                validateStatus={errors.details ? "error" : ""}
                                help={errors.details}
                            >
                                <textarea
                                    placeholder="Provide detailed information about your request..."
                                    rows={4}
                                    value={data.details}
                                    onChange={(e) =>
                                        setData("details", e.target.value)
                                    }
                                    className="textarea textarea-bordered w-full rounded-lg text-sm resize-y"
                                />
                            </Form.Item>

                            <Form.Item
                                label="Attachments"
                                validateStatus={
                                    errors.attachments ? "error" : ""
                                }
                                help={errors.attachments}
                            >
                                <AttachmentUpload
                                    onFilesChange={(files) =>
                                        setData("attachments", files)
                                    }
                                />
                            </Form.Item>
                            <Form.Item className="mb-0">
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    loading={processing}
                                    disabled={processing}
                                    className="btn btn-primary w-full rounded-lg flex items-center justify-center gap-2"
                                    size="large"
                                >
                                    {!processing && <Ticket />}
                                    {processing
                                        ? "Creating..."
                                        : "Create Ticket"}
                                </Button>
                            </Form.Item>
                        </Form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
};

export default Create;
