import React, { useState, useRef, useEffect } from "react";
import { Form, Select, Input, Button, Alert, message, DatePicker } from "antd";
import { Ticket, HelpCircle } from "lucide-react";
import { useForm, usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AttachmentUpload from "./AttachmentUpload";
import TicketFormTour from "./TicketFormTour";
import TicketFormSkeleton from "./TicketFormSkeleton";
import { useTicketForm } from "./../../Hooks/useTicketForm";
import dayjs from "dayjs";

const { Option } = Select;

const Create = () => {
    const selectDropdownStyle = { borderRadius: "0.5rem" };

    // Loading state
    const [isLoading, setIsLoading] = useState(true);

    // Tour state
    const [tourOpen, setTourOpen] = useState(false);

    // Refs for tour targets
    const requestTypeRef = useRef(null);
    const projectRef = useRef(null);
    const parentTicketRef = useRef(null);
    const testerRef = useRef(null);
    const targetDateRef = useRef(null);
    const detailsRef = useRef(null);
    const attachmentsRef = useRef(null);
    const submitRef = useRef(null);

    const {
        emp_data,
        requestTypes = [],
        ticketOptions = [],
        ticketProjects = {},
        projectOptions = [],
        employeeOptions = [],
    } = usePage().props;

    // Parse URL query parameters
    const params = new URLSearchParams(window.location.search);
    const parentParam = params.get("parent");
    const projectParam = params.get("project");
    const userParam = params.get("user");
    const actionParam = params.get("action");

    const parentFromUrl = parentParam ? atob(parentParam) : null;
    const projectFromUrl = projectParam ? atob(projectParam) : null;
    const userFromUrl = userParam ? atob(userParam) : null;
    const isNewTicketFromProj = Boolean(projectParam && !parentParam);
    const isNewProj = Boolean(actionParam);

    // Initialize form data
    const { data, setData, post, processing, errors, reset } = useForm({
        request_type: isNewProj ? 1 : null,
        project: projectFromUrl || null,
        project_name: projectFromUrl || null,
        parent_ticket: parentFromUrl || null,
        testers: [],
        target_date: null,
        details: "",
        attachments: [],
    });

    // Handle custom logic for filtering and selections
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

    const isChildTicket = Boolean(parentFromUrl && projectFromUrl);

    // Simulate data loading effect
    useEffect(() => {
        // Simulate loading delay for data initialization
        const timer = setTimeout(() => {
            setIsLoading(false);
        }, 800);

        return () => clearTimeout(timer);
    }, []);

    // Disable past dates in DatePicker
    const disabledDate = (current) => {
        const today = dayjs().startOf("day");

        if (data.request_type === 5) {
            // Allow only 1 to 7 days ahead (including today)
            const maxDate = today.add(7, "day");
            return current < today || current > maxDate;
        }

        if (data.request_type === 6) {
            // Allow up to 31 days ahead (including today)
            const maxDate = today.add(31, "day");
            return current < today || current > maxDate;
        }

        // Default: no restriction
        return false;
    };

    const handleSubmit = (e) => {
        const formData = new FormData();

        formData.append("request_type", data.request_type);

        if (isChildTicket) {
            formData.append("project", projectFromUrl);
            formData.append("parent_ticket", parentFromUrl);
        } else if (isNewSystem) {
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

        if (isTesting && data.target_date) {
            const dateStr =
                typeof data.target_date === "string"
                    ? data.target_date
                    : dayjs(data.target_date).format("YYYY-MM-DD");
            formData.append("target_date", dateStr);
        }

        formData.append("details", data.details);

        if (data.attachments && Array.isArray(data.attachments)) {
            const validFiles = data.attachments.filter(
                (file) => file instanceof File
            );
            validFiles.forEach((file, index) => {
                formData.append(`attachments[${index}]`, file);
            });
        }

        post(route("tickets.store"), {
            data: formData,
            forceFormData: true,
            onSuccess: () => {
                message.success("Ticket created successfully!");
                reset();
            },
            onError: () => {
                message.error("Please check the form for errors.");
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <div className="text-center px-6 mb-6">
                <div className="flex items-center justify-center gap-3 mb-1">
                    <h1 className="text-3xl font-bold">Ticketing System</h1>
                    {!isLoading && (
                        <Button
                            type="text"
                            icon={<HelpCircle size={20} />}
                            onClick={() => setTourOpen(true)}
                            className="flex items-center"
                            title="Start guided tour"
                        />
                    )}
                </div>
                <p className="text-base-content/60 text-sm">
                    Generate a new ticket by filling out the form below.
                </p>
            </div>

            {isLoading ? (
                <TicketFormSkeleton />
            ) : (
                <>
                    <div className="flex justify-center">
                        <div className="card w-full max-w-4xl shadow-xl bg-base-200">
                            <div className="card-body p-6 space-y-5">
                                <Alert
                                    message="Please fill out all required fields."
                                    type="info"
                                    showIcon
                                    className="rounded-lg"
                                />

                                {/* Employee Info */}
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
                                        <Form.Item
                                            label="Type of Request"
                                            required
                                            validateStatus={
                                                errors.request_type
                                                    ? "error"
                                                    : ""
                                            }
                                            help={errors.request_type}
                                        >
                                            <div ref={requestTypeRef}>
                                                <Select
                                                    placeholder="Choose request type"
                                                    value={data.request_type}
                                                    onChange={(value) => {
                                                        setData(
                                                            "request_type",
                                                            value
                                                        );
                                                        handleRequestTypeChange(
                                                            value
                                                        );
                                                    }}
                                                    className="w-full rounded-lg text-sm h-10"
                                                    showSearch
                                                    optionFilterProp="children"
                                                    disabled={isNewProj}
                                                >
                                                    {requestTypes
                                                        .filter((rt) => {
                                                            if (
                                                                isNewTicketFromProj
                                                            )
                                                                return (
                                                                    rt.value !==
                                                                    1
                                                                );
                                                            if (isNewProj)
                                                                return (
                                                                    rt.value ===
                                                                    1
                                                                );
                                                            if (isChildTicket) {
                                                                if (
                                                                    userFromUrl !=
                                                                    emp_data.emp_id
                                                                )
                                                                    return [
                                                                        5, 6,
                                                                    ].includes(
                                                                        rt.value
                                                                    );
                                                                return ![
                                                                    1, 5, 6,
                                                                ].includes(
                                                                    rt.value
                                                                );
                                                            }
                                                            return true;
                                                        })
                                                        .map((rt) => (
                                                            <Option
                                                                key={rt.value}
                                                                value={rt.value}
                                                            >
                                                                {rt.label}
                                                            </Option>
                                                        ))}
                                                </Select>
                                            </div>
                                        </Form.Item>

                                        {/* Project & Parent Ticket */}
                                        {isChildTicket ? (
                                            <>
                                                <Form.Item
                                                    label="Project"
                                                    required
                                                >
                                                    <div ref={projectRef}>
                                                        <Input
                                                            value={
                                                                projectFromUrl
                                                            }
                                                            readOnly
                                                            className="input input-bordered w-full rounded-lg text-sm h-10 bg-base-300"
                                                        />
                                                    </div>
                                                </Form.Item>

                                                <Form.Item
                                                    label="Parent Ticket"
                                                    required
                                                >
                                                    <Input
                                                        value={parentFromUrl}
                                                        readOnly
                                                        className="input input-bordered w-full rounded-lg text-sm h-10 bg-base-300"
                                                    />
                                                </Form.Item>
                                            </>
                                        ) : isNewTicketFromProj ? (
                                            <>
                                                <Form.Item
                                                    label="Project"
                                                    required
                                                >
                                                    <div ref={projectRef}>
                                                        <Input
                                                            value={
                                                                projectFromUrl
                                                            }
                                                            readOnly
                                                            className="input input-bordered w-full rounded-lg text-sm h-10 bg-base-300"
                                                        />
                                                    </div>
                                                </Form.Item>
                                            </>
                                        ) : isNewSystem ? (
                                            <>
                                                <Form.Item
                                                    label="Project Name"
                                                    required
                                                    className="col-span-1 md:col-span-2"
                                                    validateStatus={
                                                        errors.project_name
                                                            ? "error"
                                                            : ""
                                                    }
                                                    help={errors.project_name}
                                                >
                                                    <div ref={projectRef}>
                                                        <Input
                                                            placeholder="Enter project name"
                                                            value={
                                                                data.project_name
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    "project_name",
                                                                    e.target
                                                                        .value
                                                                )
                                                            }
                                                            className="input input-bordered w-full rounded-lg text-sm h-10"
                                                        />
                                                    </div>
                                                </Form.Item>
                                            </>
                                        ) : (
                                            <>
                                                <Form.Item
                                                    label="Project"
                                                    required
                                                    validateStatus={
                                                        errors.project
                                                            ? "error"
                                                            : ""
                                                    }
                                                    help={errors.project}
                                                >
                                                    <div ref={projectRef}>
                                                        <Select
                                                            placeholder="Select project"
                                                            value={data.project}
                                                            onChange={(
                                                                value
                                                            ) => {
                                                                setData(
                                                                    "project",
                                                                    value
                                                                );
                                                                handleProjectChange(
                                                                    value
                                                                );
                                                            }}
                                                            showSearch
                                                            optionFilterProp="children"
                                                            className="w-full rounded-lg text-sm h-10"
                                                        >
                                                            {projectOptions.map(
                                                                (p) => (
                                                                    <Option
                                                                        key={
                                                                            p.value
                                                                        }
                                                                        value={
                                                                            p.value
                                                                        }
                                                                    >
                                                                        {
                                                                            p.label
                                                                        }
                                                                    </Option>
                                                                )
                                                            )}
                                                        </Select>
                                                    </div>
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
                                                    <div ref={parentTicketRef}>
                                                        <Select
                                                            placeholder="Select parent ticket"
                                                            value={
                                                                data.parent_ticket
                                                            }
                                                            onChange={(
                                                                value
                                                            ) => {
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
                                                            className="w-full rounded-lg text-sm h-10"
                                                        >
                                                            {filteredParentTickets.map(
                                                                (t) => (
                                                                    <Option
                                                                        key={
                                                                            t.value
                                                                        }
                                                                        value={
                                                                            t.value
                                                                        }
                                                                    >
                                                                        {
                                                                            t.label
                                                                        }
                                                                    </Option>
                                                                )
                                                            )}
                                                        </Select>
                                                    </div>
                                                </Form.Item>
                                            </>
                                        )}
                                    </div>

                                    {/* Assign Tester and Target Date */}
                                    {isTesting && (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <Form.Item
                                                label="Assign Tester"
                                                required
                                                validateStatus={
                                                    errors.testers
                                                        ? "error"
                                                        : ""
                                                }
                                                help={errors.testers}
                                            >
                                                <div ref={testerRef}>
                                                    <Select
                                                        mode="multiple"
                                                        placeholder="Select tester(s)"
                                                        value={data.testers}
                                                        onChange={(value) =>
                                                            setData(
                                                                "testers",
                                                                value
                                                            )
                                                        }
                                                        className="w-full rounded-lg text-sm h-10"
                                                        showSearch
                                                        optionFilterProp="children"
                                                    >
                                                        {employeeOptions.map(
                                                            (emp) => (
                                                                <Option
                                                                    key={
                                                                        emp.value
                                                                    }
                                                                    value={
                                                                        emp.value
                                                                    }
                                                                >
                                                                    {emp.label}
                                                                </Option>
                                                            )
                                                        )}
                                                    </Select>
                                                </div>
                                            </Form.Item>

                                            <Form.Item
                                                label="Target Date"
                                                required
                                                validateStatus={
                                                    errors.target_date
                                                        ? "error"
                                                        : ""
                                                }
                                                help={errors.target_date}
                                            >
                                                <div ref={targetDateRef}>
                                                    <DatePicker
                                                        placeholder="Select target date"
                                                        value={
                                                            data.target_date
                                                                ? dayjs(
                                                                      data.target_date
                                                                  )
                                                                : null
                                                        }
                                                        onChange={(date) => {
                                                            const formattedDate =
                                                                date
                                                                    ? date.format(
                                                                          "YYYY-MM-DD"
                                                                      )
                                                                    : null;
                                                            setData(
                                                                "target_date",
                                                                formattedDate
                                                            );
                                                        }}
                                                        disabledDate={
                                                            disabledDate
                                                        }
                                                        className="w-full rounded-lg text-sm h-10"
                                                        format="YYYY-MM-DD"
                                                    />
                                                </div>
                                            </Form.Item>
                                        </div>
                                    )}

                                    {/* Request Details */}
                                    <Form.Item
                                        label="Request Details"
                                        required
                                        validateStatus={
                                            errors.details ? "error" : ""
                                        }
                                        help={errors.details}
                                    >
                                        <div ref={detailsRef}>
                                            <textarea
                                                placeholder="Provide detailed information about your request..."
                                                rows={4}
                                                value={data.details}
                                                onChange={(e) =>
                                                    setData(
                                                        "details",
                                                        e.target.value
                                                    )
                                                }
                                                className="textarea textarea-bordered w-full rounded-lg text-sm resize-y"
                                            />
                                        </div>
                                    </Form.Item>

                                    {/* Attachments */}
                                    <Form.Item
                                        label="Attachments"
                                        validateStatus={
                                            errors.attachments ? "error" : ""
                                        }
                                        help={errors.attachments}
                                    >
                                        <div ref={attachmentsRef}>
                                            <AttachmentUpload
                                                onFilesChange={(files) =>
                                                    setData(
                                                        "attachments",
                                                        files
                                                    )
                                                }
                                            />
                                        </div>
                                    </Form.Item>

                                    {/* Submit */}
                                    <Form.Item className="mb-0">
                                        <div ref={submitRef}>
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
                                                    ? isChildTicket
                                                        ? "Creating Child Ticket..."
                                                        : "Creating..."
                                                    : isChildTicket
                                                    ? "Create Child Ticket"
                                                    : "Create Ticket"}
                                            </Button>
                                        </div>
                                    </Form.Item>
                                </Form>
                            </div>
                        </div>
                    </div>

                    {/* Tour Component */}
                    <TicketFormTour
                        open={tourOpen}
                        onClose={() => setTourOpen(false)}
                        refs={{
                            requestTypeRef,
                            projectRef,
                            parentTicketRef,
                            testerRef,
                            targetDateRef,
                            detailsRef,
                            attachmentsRef,
                            submitRef,
                        }}
                        isChildTicket={isChildTicket}
                        isNewSystem={isNewSystem}
                        isTesting={isTesting}
                    />
                </>
            )}
        </AuthenticatedLayout>
    );
};

export default Create;
