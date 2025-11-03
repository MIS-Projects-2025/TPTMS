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

    const fetchProjects = async () => {
        try {
            const res = await axios.get(`/api/projects/assigned/${empId}`);
            setProjects(res.data || []);
        } catch (err) {
            console.error("Failed to load projects", err);
        }
    };

    const fetchTickets = async () => {
        try {
            const res = await axios.get(
                `/api/tickets/assigned/${empId}?status=active`
            );
            setTickets(res.data || []);
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
            width={800}
            className="new-task-modal"
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={handleSubmit}
                className="grid grid-cols-2 gap-x-6 gap-y-2 dark:text-gray-200"
            >
                {/* Common Fields */}
                <Form.Item
                    label="Source Type"
                    name="SOURCE_TYPE"
                    rules={[
                        { required: true, message: "Select a source type" },
                    ]}
                >
                    <Select
                        placeholder="Select Source Type"
                        onChange={(v) => setSourceType(v)}
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
                        rules={[{ required: true, message: "Select a ticket" }]}
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
                            { required: true, message: "Select a project" },
                        ]}
                    >
                        <Select showSearch placeholder="Search project...">
                            {projects.map((p) => (
                                <Option key={p.value} value={p.value}>
                                    {p.PROJ_NAME}
                                </Option>
                            ))}
                        </Select>
                    </Form.Item>
                )}

                <Form.Item label="Status" name="STATUS" initialValue={1}>
                    <Select>
                        <Option value={1}>Pending</Option>
                        <Option value={2}>In Progress</Option>
                        <Option value={3}>Completed</Option>
                        <Option value={4}>On Hold</Option>
                        <Option value={5}>Cancelled</Option>
                    </Select>
                </Form.Item>

                <Form.Item label="Priority" name="PRIORITY" initialValue={3}>
                    <Select>
                        <Option value={1}>Urgent</Option>
                        <Option value={2}>High</Option>
                        <Option value={3}>Medium</Option>
                        <Option value={4}>Low</Option>
                        <Option value={5}>N/A</Option>
                    </Select>
                </Form.Item>

                <div className="col-span-2">
                    <Divider>Task Details</Divider>
                </div>

                <div className="col-span-2">
                    <Form.List
                        name="TASKS"
                        rules={[
                            {
                                validator: async (_, tasks) => {
                                    if (!tasks || tasks.length < 1)
                                        return Promise.reject(
                                            new Error("Add at least one task")
                                        );
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
                                        className="block p-3 mb-2 border rounded-lg "
                                    >
                                        <Form.Item
                                            {...restField}
                                            label="Task Title"
                                            name={[name, "TASK_TITLE"]}
                                            rules={[{ required: true }]}
                                        >
                                            <Input
                                                placeholder="Enter title..."
                                                className="input input-bordered w-full rounded-lg text-sm h-10"
                                            />
                                        </Form.Item>

                                        <Form.Item
                                            {...restField}
                                            label="Description"
                                            name={[name, "TASK_DESCRIPTION"]}
                                            rules={[{ required: true }]}
                                        >
                                            <TextArea
                                                rows={3}
                                                placeholder="Enter description..."
                                                className="textarea textarea-bordered w-full rounded-lg text-sm resize-y"
                                            />
                                        </Form.Item>

                                        <div className="text-right">
                                            <Button
                                                type="dashed"
                                                danger
                                                icon={<MinusCircleOutlined />}
                                                onClick={() => remove(name)}
                                            >
                                                Remove
                                            </Button>
                                        </div>
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
                </div>

                <div className="col-span-2 flex justify-end gap-2 mt-4">
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
