import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { FileSpreadsheet } from "lucide-react";

const ImportModal = ({ isOpen, onClose }) => {
    const [selectedFile, setSelectedFile] = useState(null);
    const [isUploading, setIsUploading] = useState(false);

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const validTypes = [
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                "application/vnd.ms-excel",
                "text/csv",
            ];

            if (validTypes.includes(file.type)) {
                setSelectedFile(file);
            } else {
                alert("Please select a valid Excel or CSV file");
                e.target.value = "";
            }
        }
    };

    const handleImport = () => {
        if (!selectedFile) {
            alert("Please select a file first");
            return;
        }

        setIsUploading(true);

        const formData = new FormData();
        formData.append("excel_file", selectedFile);

        router.post(route("project.import"), formData, {
            onSuccess: () => {
                setSelectedFile(null);
                setIsUploading(false);
                onClose();
            },
            onError: (errors) => {
                console.error("Import errors:", errors);
                setIsUploading(false);
            },
            onFinish: () => {
                setIsUploading(false);
            },
        });
    };

    const downloadTemplate = () => {
        window.open(route("project.template"), "_blank");
    };

    const resetForm = () => {
        setSelectedFile(null);
        setIsUploading(false);
    };

    const handleClose = () => {
        resetForm();
        onClose();
    };

    if (!isOpen) return null;

    return (
        <div className="modal modal-open">
            <div className="modal-box max-w-3xl">
                <h3 className="font-bold text-lg mb-4">
                    Import Projects from Excel
                </h3>

                <div className="space-y-4">
                    {/* Instructions */}
                    <div className="alert alert-info">
                        <div>
                            <h4 className="font-semibold">
                                Import Instructions:
                            </h4>
                            <ul className="text-sm mt-2 list-disc list-inside">
                                <li>
                                    Use the template file or ensure your Excel
                                    has the correct columns
                                </li>
                                <li>
                                    Required columns: PROJ_NAME, PROJ_DEPT,
                                    PROJ_STATUS
                                </li>
                                <li>
                                    Optional columns: PROJ_ID, PROJ_DESC,
                                    PROJ_REQUESTOR
                                </li>
                                <li>
                                    Status can be text (Pending, In Progress,
                                    Completed, etc.) or numeric (1-5)
                                </li>
                                <li>
                                    If PROJ_ID is provided, existing projects
                                    will be updated
                                </li>
                                <li>
                                    If PROJ_ID is empty, new projects will be
                                    created
                                </li>
                            </ul>
                        </div>
                    </div>

                    {/* Download Template */}
                    <div className="flex justify-center">
                        <button
                            className="btn btn-outline btn-sm"
                            onClick={downloadTemplate}
                        >
                            <FileSpreadsheet size={18} /> Download Template
                        </button>
                    </div>

                    {/* File Upload */}
                    <div className="form-control">
                        <label className="label">
                            <span className="label-text">
                                Select Excel/CSV File
                            </span>
                        </label>
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={handleFileChange}
                            className="file-input file-input-bordered w-full"
                            disabled={isUploading}
                        />
                        {selectedFile && (
                            <label className="label">
                                <span className="label-text-alt text-success">
                                    Selected: {selectedFile.name}
                                </span>
                            </label>
                        )}
                    </div>

                    {/* Status Mapping Reference */}
                    <div className="collapse collapse-arrow border border-base-300 bg-base-100">
                        <input type="checkbox" />
                        <div className="collapse-title text-sm font-medium">
                            Status Mapping Reference
                        </div>
                        <div className="collapse-content text-sm">
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <strong>Text Status</strong>
                                </div>
                                <div>
                                    <strong>Numeric Value</strong>
                                </div>
                                <div>Pending</div>
                                <div>1</div>
                                <div>On Hold</div>
                                <div>2</div>
                                <div>For Testing</div>
                                <div>3</div>
                                <div>Parallel Run</div>
                                <div>4</div>
                                <div>Deployed</div>
                                <div>5</div>
                                <div>Cancelled</div>
                                <div>6</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="modal-action">
                    <button
                        className="btn btn-ghost"
                        onClick={handleClose}
                        disabled={isUploading}
                    >
                        Cancel
                    </button>
                    <button
                        className="btn btn-primary flex items-center gap-2"
                        onClick={handleImport}
                        disabled={!selectedFile || isUploading}
                    >
                        {isUploading ? (
                            <>
                                <span className="loading loading-spinner loading-sm"></span>
                                Importing...
                            </>
                        ) : (
                            <>
                                <FileSpreadsheet size={18} />
                                Import Projects
                            </>
                        )}
                    </button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={handleClose} />
        </div>
    );
};

export default ImportModal;
