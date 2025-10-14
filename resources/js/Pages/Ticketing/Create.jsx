import React, { useState, useEffect } from "react";
import { Form, Select, Input, Button, Alert } from "antd";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { usePage } from "@inertiajs/react";

const { Option } = Select;

const Create = () => {
    const selectStyle = { height: "2.5rem" };
    const selectDropdownStyle = { borderRadius: "0.5rem" };

    const {
        emp_data,
        requestTypes = [],
        ticketOptions = [],
        ticketProjects = {}, // {ticketId: projectName}
        projectOptions = [],
        employeeOptions = [],
    } = usePage().props;
    console.log(usePage().props);

    const [requestType, setRequestType] = useState(null);
    const [selectedProject, setSelectedProject] = useState(null);
    const [selectedParentTicket, setSelectedParentTicket] = useState(null);
    const [filteredParentTickets, setFilteredParentTickets] =
        useState(ticketOptions);

    const isNewSystem = requestType === 1;
    const isTesting = requestType === 5 || requestType === 6; // REQUEST_TESTING

    // Update filtered parent tickets if project changes
    useEffect(() => {
        if (selectedProject) {
            setFilteredParentTickets(
                ticketOptions.filter(
                    (t) => ticketProjects[t.value] === selectedProject
                )
            );
            // If parent ticket doesn't belong to selected project, clear it
            if (
                selectedParentTicket &&
                ticketProjects[selectedParentTicket] !== selectedProject
            ) {
                setSelectedParentTicket(null);
            }
        } else {
            setFilteredParentTickets(ticketOptions);
        }
    }, [selectedProject, ticketOptions, ticketProjects, selectedParentTicket]);

    // Auto-fill project when parent ticket changes
    useEffect(() => {
        if (selectedParentTicket) {
            setSelectedProject(ticketProjects[selectedParentTicket]);
        }
    }, [selectedParentTicket, ticketProjects]);

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

                        <Form layout="vertical" className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {/* Request Type */}
                                <Form.Item label="Type of Request" required>
                                    <Select
                                        placeholder="Choose request type"
                                        value={requestType}
                                        onChange={setRequestType}
                                        style={selectStyle}
                                        dropdownStyle={selectDropdownStyle}
                                        className="rounded-lg border border-base-300 text-sm"
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
                                    >
                                        <Input
                                            placeholder="Enter project name"
                                            className="input input-bordered w-full rounded-lg text-sm h-10"
                                        />
                                    </Form.Item>
                                ) : (
                                    <>
                                        <Form.Item label="Project" required>
                                            <Select
                                                placeholder="Select project"
                                                value={selectedProject}
                                                onChange={setSelectedProject}
                                                showSearch
                                                optionFilterProp="children"
                                                style={selectStyle}
                                                dropdownStyle={
                                                    selectDropdownStyle
                                                }
                                                className="rounded-lg border border-base-300 text-sm"
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

                                        <Form.Item label="Parent Ticket">
                                            <Select
                                                placeholder="Select parent ticket"
                                                value={selectedParentTicket}
                                                onChange={
                                                    setSelectedParentTicket
                                                }
                                                allowClear
                                                showSearch
                                                optionFilterProp="children"
                                                style={selectStyle}
                                                dropdownStyle={
                                                    selectDropdownStyle
                                                }
                                                className="rounded-lg border border-base-300 text-sm"
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
                                <Form.Item label="Assign Tester">
                                    <Select
                                        mode="multiple"
                                        placeholder="Select tester(s)"
                                        style={selectStyle}
                                        dropdownStyle={selectDropdownStyle}
                                        className="rounded-lg border border-base-300 text-sm"
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

                            <Form.Item label="Request Details">
                                <textarea
                                    placeholder="Provide detailed information about your request..."
                                    rows={4}
                                    className="textarea textarea-bordered w-full rounded-lg text-sm resize-y"
                                />
                            </Form.Item>

                            <Form.Item className="mb-0">
                                <Button
                                    type="primary"
                                    htmlType="submit"
                                    className="btn btn-primary w-full rounded-lg"
                                    size="large"
                                >
                                    Create Ticket
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
