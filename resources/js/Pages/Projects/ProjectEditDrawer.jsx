import React from "react";
import {
    Drawer,
    Form,
    Input,
    Select,
    DatePicker,
    Button,
    message,
    Avatar,
    Tag,
    Empty,
    Spin,
    Row,
    Col,
    Alert,
} from "antd";
import {
    UserOutlined,
    CalendarOutlined,
    SaveOutlined,
    EditOutlined,
    TagOutlined,
    InfoCircleOutlined,
} from "@ant-design/icons";
import { router } from "@inertiajs/react";
import dayjs from "dayjs";
import axios from "axios";
import useProjectEdit from "@/Hooks/useProjectEdit";

const { TextArea } = Input;
const { Option } = Select;

export default function ProjectEditDrawer({ isOpen, onClose, project }) {
    const [form] = Form.useForm();

    const {
        loading,
        handlerOptions,
        loadingHandlers,
        departmentOptions,
        projectStatuses,
        constantsLoading,
        constantsError,
        getStatusLabel,
        getStatusColor,
        handleSubmit,
        handleClose,
        handleDepartmentChange,
        getColorFromString,
    } = useProjectEdit(isOpen, project, form, onClose);

    // const currentStatus = projectStatuses?.find(
    //     (status) => status.label === project.status
    // );

    return (
        <Drawer
            title={
                <div className="flex items-center gap-2">
                    <EditOutlined className="text-blue-500" />
                    <span>Edit Project</span>
                </div>
            }
            placement="right"
            onClose={handleClose}
            open={isOpen}
            width={600}
            footer={
                <div className="flex justify-end gap-2">
                    <Button onClick={handleClose}>Cancel</Button>
                    <Button
                        type="primary"
                        icon={<SaveOutlined />}
                        onClick={() => form.submit()}
                        loading={loading}
                    >
                        Update Project
                    </Button>
                </div>
            }
        >
            {constantsError && (
                <Alert
                    message="Warning"
                    description="Failed to load status options. Using cached values."
                    type="warning"
                    showIcon
                    closable
                    className="mb-4"
                />
            )}

            {project && (
                <div className="mb-6 p-4 bg-gray-50 rounded-lg border">
                    <h3 className="font-semibold text-lg mb-2 text-gray-800">
                        Project Details
                    </h3>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span className="text-gray-600">Created:</span>
                            <div className="font-medium">
                                {project.created_at
                                    ? dayjs(project.created_at).format(
                                          "MMM DD, YYYY"
                                      )
                                    : "N/A"}
                            </div>
                        </div>
                        <div>
                            <span className="text-gray-600">Last Updated:</span>
                            <div className="font-medium">
                                {project.updated_at
                                    ? dayjs(project.updated_at).format(
                                          "MMM DD, YYYY"
                                      )
                                    : "N/A"}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <Form
                form={form}
                layout="vertical"
                onFinish={handleSubmit}
                className="mt-4"
            >
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item
                            name="name"
                            label="Project Name"
                            rules={[
                                {
                                    required: true,
                                    message: "Please enter project name",
                                },
                                {
                                    min: 2,
                                    message:
                                        "Project name must be at least 2 characters",
                                },
                            ]}
                        >
                            <Input
                                size="large"
                                placeholder="Enter project name"
                                prefix={
                                    <InfoCircleOutlined className="text-gray-400" />
                                }
                            />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item
                            name="department"
                            label="Department"
                            rules={[
                                {
                                    required: true,
                                    message: "Please select department",
                                },
                            ]}
                        >
                            <Select
                                size="large"
                                placeholder="Select department"
                                onChange={handleDepartmentChange}
                                showSearch
                                optionFilterProp="children"
                            >
                                {departmentOptions.map((dept) => (
                                    <Option key={dept} value={dept}>
                                        {dept}
                                    </Option>
                                ))}
                            </Select>
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item
                    name="description"
                    label="Description"
                    rules={[
                        {
                            required: true,
                            message: "Please enter project description",
                        },
                    ]}
                >
                    <TextArea
                        rows={3}
                        placeholder="Enter project description"
                        maxLength={500}
                        showCount
                    />
                </Form.Item>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item
                            name="status"
                            label="Status"
                            rules={[
                                {
                                    required: true,
                                    message: "Please select project status",
                                },
                            ]}
                        >
                            {constantsLoading ? (
                                <div className="text-center py-2">
                                    <Spin size="small" />
                                </div>
                            ) : (
                                <Select
                                    size="large"
                                    placeholder="Select status"
                                    optionFilterProp="children"
                                >
                                    {projectStatuses.map((status) => (
                                        <Option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            <Tag color={status.color}>
                                                {status.label}
                                            </Tag>
                                        </Option>
                                    ))}
                                </Select>
                            )}
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item
                            name="target_deadline"
                            label="Target Deadline"
                        >
                            <DatePicker
                                size="large"
                                className="w-full"
                                format="YYYY-MM-DD"
                                placeholder="Select target deadline"
                                disabledDate={(current) =>
                                    current && current < dayjs().startOf("day")
                                }
                            />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item
                    name="handlers"
                    label="Project Handlers"
                    rules={[
                        {
                            required: true,
                            message: "Please select at least one handler",
                        },
                    ]}
                >
                    {loadingHandlers ? (
                        <div className="text-center py-4">
                            <Spin />
                            <div className="text-gray-500 mt-2">
                                Loading handlers...
                            </div>
                        </div>
                    ) : handlerOptions.length > 0 ? (
                        <Select
                            mode="multiple"
                            placeholder="Select project handlers"
                            optionFilterProp="label"
                            showSearch
                            allowClear
                            size="large"
                            maxTagCount="responsive"
                            options={handlerOptions.map((handler) => ({
                                label: `${handler.EMPLOYID} - ${handler.EMPNAME}`,
                                value: handler.EMPLOYID,
                            }))}
                        />
                    ) : (
                        <Empty
                            description="No handlers found for this department"
                            image={Empty.PRESENTED_IMAGE_SIMPLE}
                        />
                    )}
                </Form.Item>

                {/* Current Information Display
                <div className="mt-6 space-y-4">
                    {project?.proj_handler?.length > 0 && (
                        <div className="p-3 bg-blue-50 rounded border border-blue-200">
                            <p className="text-sm font-semibold text-blue-800 mb-2">
                                Current Handlers:
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {project.proj_handler.map((handler) => (
                                    <Tag
                                        key={handler.emp_id}
                                        color="blue"
                                        icon={
                                            <Avatar
                                                size={16}
                                                style={{
                                                    backgroundColor:
                                                        getColorFromString(
                                                            handler.emp_id
                                                        ),
                                                    marginRight: 4,
                                                }}
                                            >
                                                {handler.initials}
                                            </Avatar>
                                        }
                                    >
                                        {handler.full_name}
                                    </Tag>
                                ))}
                            </div>
                        </div>
                    )}

                    {project?.target_deadline && (
                        <div className="p-3 bg-green-50 rounded border border-green-200">
                            <p className="text-sm text-green-800">
                                <CalendarOutlined className="mr-2" />
                                Current Deadline:{" "}
                                <strong>
                                    {dayjs(project.target_deadline).format(
                                        "MMM DD, YYYY"
                                    )}
                                </strong>
                            </p>
                        </div>
                    )}
                    {currentStatus ? (
                        <div className="p-3 bg-purple-50 rounded border border-purple-200">
                            <p className="text-sm text-purple-800">
                                <TagOutlined className="mr-2" />
                                Current Status: {currentStatus.label}
                                <Tag
                                    color={currentStatus.color}
                                    className="ml-2"
                                >
                                    {currentStatus.label}
                                </Tag>
                            </p>
                        </div>
                    ) : (
                        <p>Status Unknown</p>
                    )}
                </div> */}
            </Form>
        </Drawer>
    );
}
