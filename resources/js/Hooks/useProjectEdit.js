import { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { message } from "antd";
import axios from "axios";
import dayjs from "dayjs";
import useProjectConstants from "@/Hooks/useProjectConstants";

export default function useProjectEdit(
    isOpen,
    project,
    form,
    onClose,
    mode = "edit"
) {
    const [loading, setLoading] = useState(false);
    const [handlerOptions, setHandlerOptions] = useState([]);
    const [loadingHandlers, setLoadingHandlers] = useState(false);
    const [departmentOptions, setDepartmentOptions] = useState([]);

    const {
        projectStatuses,
        loading: constantsLoading,
        error: constantsError,
        getStatusLabel,
        getStatusColor,
    } = useProjectConstants();

    useEffect(() => {
        if (isOpen) {
            fetchDepartments().then((departments) => {
                if (mode === "edit" && project) {
                    // Edit mode - populate form with project data
                    const currentHandlers = project.proj_handler || [];
                    fetchHandlers(project.department, currentHandlers);

                    const initialStatusValue =
                        projectStatuses?.find((s) => s.label === project.status)
                            ?.value || 1;

                    form.setFieldsValue({
                        name: project.name,
                        description: project.description,
                        department: project.department,
                        handlers: currentHandlers.map((h) => h.emp_id),
                        assigned_to: project.assigned_to?.emp_id || null,
                        target_deadline: project.target_deadline
                            ? dayjs(project.target_deadline)
                            : null,
                        status: initialStatusValue,
                    });
                } else {
                    // Create mode - set default values
                    form.resetFields();

                    // Set default department (first one) and load its handlers
                    if (departments && departments.length > 0) {
                        const defaultDepartment = departments[0];
                        form.setFieldsValue({
                            department: defaultDepartment,
                            status: 1, // Default status for new projects
                        });
                        // Load handlers for the default department
                        fetchHandlers(defaultDepartment, []);
                    }
                }
            });
        }
    }, [isOpen, project, projectStatuses, mode]);

    const fetchHandlers = async (department, currentHandlers = []) => {
        if (!department) {
            setHandlerOptions([]);
            return;
        }

        setLoadingHandlers(true);
        try {
            const res = await axios.get(`/api/projects/handlers/${department}`);
            if (res.data.success) {
                let handlers = res.data.handlers || [];
                currentHandlers.forEach((currentHandler) => {
                    const exists = handlers.find(
                        (h) => h.EMPLOYID === currentHandler.emp_id
                    );
                    if (!exists) {
                        handlers.push({
                            EMPLOYID: currentHandler.emp_id,
                            EMPNAME: currentHandler.full_name,
                        });
                    }
                });
                setHandlerOptions(handlers);
            } else {
                const fallbackHandlers = currentHandlers.map((h) => ({
                    EMPLOYID: h.emp_id,
                    EMPNAME: h.full_name,
                }));
                setHandlerOptions(fallbackHandlers);
            }
        } catch (error) {
            console.error("Error fetching handlers:", error);
            message.error("Failed to load handler options");
            const fallbackHandlers = currentHandlers.map((h) => ({
                EMPLOYID: h.emp_id,
                EMPNAME: h.full_name,
            }));
            setHandlerOptions(fallbackHandlers);
        } finally {
            setLoadingHandlers(false);
        }
    };

    const fetchDepartments = async () => {
        try {
            const res = await axios.get("/api/departments");
            if (res.data.success) {
                const departments = res.data.departments;
                setDepartmentOptions(departments);
                return departments; // Return departments for the promise chain
            }
            return []; // Return empty array if no success
        } catch (error) {
            console.error("Error fetching departments:", error);
            return []; // Return empty array on error
        }
    };

    const handleSubmit = async (values) => {
        setLoading(true);

        // Convert Day.js objects to string before sending
        const payload = {
            name: values.name,
            description: values.description,
            department: values.department,
            handler_ids: values.handlers || [],
            status: values.status,
            target_deadline:
                values.target_deadline && dayjs.isDayjs(values.target_deadline)
                    ? values.target_deadline.format("YYYY-MM-DD")
                    : values.target_deadline,
        };

        // console.log("Submitting payload:", payload);

        try {
            if (mode === "create") {
                // Create new project
                router.post(route("project.store"), payload, {
                    preserveState: true,
                    onSuccess: () => {
                        message.success("Project created successfully!");
                        handleClose();
                    },
                    onError: (errors) => {
                        console.error("Backend errors:", errors);
                        const errorMessage =
                            Object.values(errors).flat().join(", ") ||
                            "Failed to create project";
                        message.error(errorMessage);
                    },
                    onFinish: () => setLoading(false),
                });
            } else {
                // Update existing project
                router.patch(
                    route("project.update", { project: project.id }),
                    payload,
                    {
                        preserveState: true,
                        onSuccess: () => {
                            message.success("Project updated successfully!");
                            handleClose();
                        },
                        onError: (errors) => {
                            console.error("Backend errors:", errors);
                            const errorMessage =
                                Object.values(errors).flat().join(", ") ||
                                "Failed to update project";
                            message.error(errorMessage);
                        },
                        onFinish: () => setLoading(false),
                    }
                );
            }
        } catch (error) {
            console.error("Frontend error:", error);
            message.error(
                `An error occurred while ${
                    mode === "create" ? "creating" : "updating"
                } project`
            );
            setLoading(false);
        }
    };

    const handleClose = () => {
        form.resetFields();
        setHandlerOptions([]);
        onClose();
    };

    const handleDepartmentChange = (department) => {
        const selectedHandlerIds = form.getFieldValue("handlers") || [];
        const selectedHandlerObjects = handlerOptions
            .filter((h) => selectedHandlerIds.includes(h.EMPLOYID))
            .map((h) => ({
                emp_id: h.EMPLOYID,
                full_name: h.EMPNAME,
                initials: h.EMPNAME
                    ? h.EMPNAME.split(" ")
                          .map((n) => n[0])
                          .join("")
                          .toUpperCase()
                    : "??",
            }));

        fetchHandlers(department, selectedHandlerObjects);
        form.setFieldsValue({ handlers: selectedHandlerIds });
    };

    const getColorFromString = (str = "") => {
        const colors = [
            "#1890ff",
            "#52c41a",
            "#faad14",
            "#f5222d",
            "#722ed1",
            "#eb2f96",
            "#13c2c2",
            "#fa8c16",
        ];
        if (!str || typeof str !== "string") str = "default";
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    };

    return {
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
        mode,
    };
}
