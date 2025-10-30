import React, { useState, useEffect } from "react";
import {
    Modal,
    Form,
    Input,
    Select,
    Button,
    Space,
    Divider,
    message,
} from "antd";
import { PlusOutlined, MinusCircleOutlined } from "@ant-design/icons";
import axios from "axios";

const { Option } = Select;
const { TextArea } = Input;

const NewTaskModal = ({ open, onClose, onCreate, empId }) => {
    const [form] = Form.useForm();
    const [loading, setLoading] = useState(false);
    const [sourceType, setSourceType] = useState(null);
    const [projects, setProjects] = useState([]);
    const [tickets, setTickets] = useState([]);

    // 🔹 Fetch projects assigned to programmer
    const fetchProjects = async () => {
        try {
            const res = await axios.get(`/api/projects/assigned/${empId}`);
            setProjects(res.data || []);
        } catch (err) {
            console.error("Failed to load projects", err);
        }
    };

    // 🔹 Fetch tickets assigned to programmer (not complete)
    const fetchTickets = async () => {
        try {
            const res = await axios.get(
                `/api/tickets/assigned/${empId}?status=active`
            );
            setTickets(res.data || []);
            console.log(res.data);
        } catch (err) {
            console.error("Failed to load tickets", err);
        }
    };

    useEffect(() => {
        if (sourceType === "project") fetchProjects();
        if (sourceType === "ticket") fetchTickets();
    }, [sourceType]);

    const handleSubmit = async () => {
        try {
            const values = await form.validateFields();
            setLoading(true);
            await onCreate(values);
            message.success("Tasks created successfully!");
            form.resetFields();
            onClose();
        } catch (error) {
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            title="Create New Task(s)"
            open={open}
            onCancel={onClose}
            footer={null}
            centered
            width={700}
        >
            <Form form={form} layout="vertical" onFinish={handleSubmit}>
                {/* 🔸 Common Fields */}
                <Form.Item
                    label="Source Type"
                    name="SOURCE_TYPE"
                    rules={[
                        {
                            required: true,
                            message: "Please select a source type",
                        },
                    ]}
                >
                    <Select
                        placeholder="Select Source Type"
                        onChange={(value) => setSourceType(value)}
                    >
                        <Option value="ticket">From Active Ticket</Option>
                        <Option value="project">From Project</Option>
                        <Option value="manual">Manual</Option>
                    </Select>
                </Form.Item>

                {sourceType === "ticket" && (
                    <Form.Item
                        label="Select Ticket"
                        name="SOURCE_ID"
                        rules={[
                            {
                                required: true,
                                message: "Please select a ticket",
                            },
                        ]}
                    >
                        <Select showSearch placeholder="Search ticket...">
                            {tickets.map((t) => (
                                <Option key={t.value} value={t.value}>
                                    {t.label}
                                </Option>
                            ))}
                        </Select>
                    </Form.Item>
                )}

                {sourceType === "project" && (
                    <Form.Item
                        label="Select Project"
                        name="SOURCE_ID"
                        rules={[
                            {
                                required: true,
                                message: "Please select a project",
                            },
                        ]}
                    >
                        <Select showSearch placeholder="Search project...">
                            {projects.map((p) => (
                                <Option key={p.PROJ_ID} value={p.PROJ_ID}>
                                    {p.PROJ_NAME}
                                </Option>
                            ))}
                        </Select>
                    </Form.Item>
                )}

                <Form.Item
                    label="Status"
                    name="STATUS"
                    initialValue={1}
                    rules={[{ required: true }]}
                >
                    <Select>
                        <Option value={1}>Pending</Option>
                        <Option value={2}>In Progress</Option>
                        <Option value={3}>Completed</Option>
                        <Option value={4}>On Hold</Option>
                        <Option value={5}>Cancelled</Option>
                    </Select>
                </Form.Item>

                <Form.Item
                    label="Priority"
                    name="PRIORITY"
                    initialValue={3}
                    rules={[{ required: true }]}
                >
                    <Select>
                        <Option value={1}>Urgent</Option>
                        <Option value={2}>High</Option>
                        <Option value={3}>Medium</Option>
                        <Option value={4}>Low</Option>
                        <Option value={5}>N/A</Option>
                    </Select>
                </Form.Item>

                <Divider>Task Details</Divider>

                {/* 🔸 Multiple Task Titles + Descriptions */}
                <Form.List
                    name="TASKS"
                    rules={[
                        {
                            validator: async (_, tasks) => {
                                if (!tasks || tasks.length < 1) {
                                    return Promise.reject(
                                        new Error("Add at least one task")
                                    );
                                }
                            },
                        },
                    ]}
                >
                    {(fields, { add, remove }) => (
                        <>
                            {fields.map(({ key, name, ...restField }) => (
                                <Space
                                    key={key}
                                    direction="vertical"
                                    style={{
                                        display: "flex",
                                        marginBottom: 12,
                                        border: "1px solid #f0f0f0",
                                        padding: 12,
                                        borderRadius: 6,
                                    }}
                                >
                                    <Form.Item
                                        {...restField}
                                        label="Task Title"
                                        name={[name, "TASK_TITLE"]}
                                        rules={[
                                            {
                                                required: true,
                                                message: "Enter task title",
                                            },
                                        ]}
                                    >
                                        <Input placeholder="Enter title..." />
                                    </Form.Item>

                                    <Form.Item
                                        {...restField}
                                        label="Task Description"
                                        name={[name, "TASK_DESCRIPTION"]}
                                        rules={[
                                            {
                                                required: true,
                                                message:
                                                    "Enter task description",
                                            },
                                        ]}
                                    >
                                        <TextArea
                                            placeholder="Enter description..."
                                            rows={3}
                                        />
                                    </Form.Item>

                                    <Button
                                        type="dashed"
                                        danger
                                        icon={<MinusCircleOutlined />}
                                        onClick={() => remove(name)}
                                    >
                                        Remove
                                    </Button>
                                </Space>
                            ))}

                            <Form.Item>
                                <Button
                                    type="dashed"
                                    onClick={() => add()}
                                    block
                                    icon={<PlusOutlined />}
                                >
                                    Add Another Task
                                </Button>
                            </Form.Item>
                        </>
                    )}
                </Form.List>

                <div className="flex justify-end gap-2 mt-4">
                    <Button onClick={onClose}>Cancel</Button>
                    <Button type="primary" htmlType="submit" loading={loading}>
                        Create Task(s)
                    </Button>
                </div>
            </Form>
        </Modal>
    );
};

export default NewTaskModal;
