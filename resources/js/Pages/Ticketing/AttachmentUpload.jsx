import React, { useState } from "react";
import { Upload, Modal, message } from "antd";
import {
    FileOutlined,
    FilePdfOutlined,
    FileWordOutlined,
    FilePptOutlined,
    FileExcelOutlined,
    FileImageOutlined,
    DeleteOutlined,
    EyeOutlined,
    DownloadOutlined,
    PaperClipOutlined,
} from "@ant-design/icons";

const AttachmentUpload = ({
    onFilesChange,
    multiple = true,
    maxCount = null,
    viewOnly = false,
    existingFiles = [],
}) => {
    const [fileList, setFileList] = useState([]);
    const [previewVisible, setPreviewVisible] = useState(false);
    const [previewImage, setPreviewImage] = useState("");

    const allowedTypes = [
        "image/png",
        "image/jpeg",
        "image/jpg",
        "image/gif",
        "application/pdf",
        "application/vnd.ms-powerpoint",
        "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        "application/msword",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/vnd.ms-excel",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    ];

    const beforeUpload = (file) => {
        if (!allowedTypes.includes(file.type)) {
            message.error(`${file.name} is not a supported file type`);
            return Upload.LIST_IGNORE;
        }
        if (file.size / 1024 / 1024 > 10) {
            message.error("File must be smaller than 10MB");
            return Upload.LIST_IGNORE;
        }
        return false; // prevent automatic upload
    };

    const handleChange = ({ fileList: newList }) => {
        const uniqueFiles = newList.filter(
            (file, index, self) =>
                index ===
                self.findIndex(
                    (f) => f.name === file.name && f.size === file.size,
                ),
        );
        setFileList(uniqueFiles);

        const files = uniqueFiles
            .map((f) => f.originFileObj || f)
            .filter((f) => f instanceof File);
        onFilesChange?.(files);
    };

    const handleRemove = (file) => {
        const newList = fileList.filter((f) => f.uid !== file.uid);
        setFileList(newList);

        const files = newList
            .map((f) => f.originFileObj || f)
            .filter((f) => f instanceof File);
        onFilesChange?.(files);
    };

    const handlePreview = (file, isExisting = false) => {
        if (file.type?.startsWith("image/")) {
            const url = isExisting
                ? file.url
                : URL.createObjectURL(file.originFileObj);
            setPreviewImage(url);
            setPreviewVisible(true);
        } else {
            handleDownload(file, isExisting);
        }
    };

    const handleDownload = (file, isExisting = false) => {
        const link = document.createElement("a");
        link.href = isExisting
            ? file.url
            : URL.createObjectURL(file.originFileObj);
        link.download = file.name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const getFileIcon = (fileType) => {
        if (fileType?.startsWith("image/"))
            return <FileImageOutlined className="text-xl text-blue-500" />;
        if (fileType === "application/pdf")
            return <FilePdfOutlined className="text-xl text-red-500" />;
        if (fileType?.includes("word"))
            return <FileWordOutlined className="text-xl text-blue-700" />;
        if (
            fileType?.includes("powerpoint") ||
            fileType?.includes("presentation")
        )
            return <FilePptOutlined className="text-xl text-orange-600" />;
        if (
            fileType?.includes("excel") ||
            fileType === "application/vnd.ms-excel" ||
            fileType ===
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        )
            return <FileExcelOutlined className="text-xl text-green-600" />;
        return <FileOutlined className="text-xl text-gray-500" />;
    };

    const formatFileSize = (bytes) => {
        if (!bytes) return "0 Bytes";
        const k = 1024;
        const sizes = ["Bytes", "KB", "MB"];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return (
            Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i]
        );
    };

    const renderFileItem = (file, isExisting = false, index) => {
        const isImage = file.type?.startsWith("image/");
        const fileUrl = isExisting
            ? file.url
            : file.originFileObj
              ? URL.createObjectURL(file.originFileObj)
              : null;

        return (
            <div
                key={isExisting ? `existing-${index}` : file.uid}
                className="card bg-base-100 border border-base-300 shadow-sm hover:shadow-md transition-shadow"
            >
                <div className="card-body p-3">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                            {isImage && fileUrl ? (
                                <div className="w-12 h-12 rounded-lg overflow-hidden border border-base-300 flex-shrink-0">
                                    <img
                                        src={fileUrl}
                                        alt={file.name}
                                        className="w-full h-full object-cover"
                                    />
                                </div>
                            ) : (
                                <div className="w-12 h-12 rounded-lg bg-base-200 border border-base-300 flex items-center justify-center flex-shrink-0">
                                    {getFileIcon(file.type)}
                                </div>
                            )}

                            <div className="flex-1 min-w-0">
                                <p className="font-semibold text-sm truncate text-base-content">
                                    {file.name}
                                </p>
                                <span className="text-xs text-base-content/60">
                                    {formatFileSize(file.size)}
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-1 flex-shrink-0">
                            <button
                                type="button"
                                className="btn btn-ghost btn-sm btn-circle"
                                onClick={() => handlePreview(file, isExisting)}
                                title={isImage ? "Preview" : "Download"}
                            >
                                <EyeOutlined className="text-base" />
                            </button>
                            {isExisting && !isImage && (
                                <button
                                    type="button"
                                    className="btn btn-ghost btn-sm btn-circle"
                                    onClick={() =>
                                        handleDownload(file, isExisting)
                                    }
                                    title="Download"
                                >
                                    <DownloadOutlined className="text-base" />
                                </button>
                            )}
                            {!viewOnly && !isExisting && (
                                <button
                                    type="button"
                                    className="btn btn-ghost btn-sm btn-circle hover:bg-error/10 hover:text-error"
                                    onClick={() => handleRemove(file)}
                                    title="Remove"
                                >
                                    <DeleteOutlined className="text-base" />
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    const displayFiles = viewOnly ? existingFiles : fileList;
    const hasFiles = displayFiles.length > 0;

    if (viewOnly && existingFiles.length === 0) {
        return (
            <div className="text-center py-8 text-base-content/60">
                <FileOutlined className="text-4xl mb-2" />
                <p className="text-sm">No attachments</p>
            </div>
        );
    }

    return (
        <>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {!viewOnly && (
                    <div className="order-2 lg:order-1">
                        <Upload
                            multiple={multiple}
                            maxCount={maxCount}
                            fileList={fileList}
                            onChange={handleChange}
                            beforeUpload={beforeUpload}
                            showUploadList={false}
                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx"
                        >
                            <div className="w-full border-2 border-dashed border-base-300 rounded-lg p-8 hover:border-primary hover:bg-base-200/50 transition-all cursor-pointer h-full flex items-center justify-center">
                                <div className="flex flex-col items-center gap-2">
                                    <PaperClipOutlined className="text-4xl text-base-content/40" />
                                    <p className="font-semibold text-base-content">
                                        Click or drag files to upload
                                    </p>
                                    <p className="text-xs text-base-content/60 mt-1">
                                        Supports: Images, PDF, Word, PowerPoint,
                                        Excel (Max 10MB)
                                    </p>
                                </div>
                            </div>
                        </Upload>
                    </div>
                )}

                {hasFiles && (
                    <div
                        className={`order-1 lg:order-2 ${viewOnly ? "lg:col-span-2" : ""}`}
                    >
                        <div className="mb-3">
                            <p className="text-xs text-base-content/60">
                                {displayFiles.length} file
                                {displayFiles.length > 1 ? "s" : ""}{" "}
                                {viewOnly ? "attached" : "ready to upload"}
                            </p>
                        </div>
                        <div className="space-y-2 max-h-96 overflow-y-auto pr-2">
                            {displayFiles.map((file, index) =>
                                renderFileItem(file, viewOnly, index),
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Image preview modal */}
            <Modal
                open={previewVisible}
                footer={null}
                onCancel={() => setPreviewVisible(false)}
                centered
            >
                <img
                    alt="Preview"
                    style={{ width: "100%" }}
                    src={previewImage}
                />
            </Modal>
        </>
    );
};

export default AttachmentUpload;
