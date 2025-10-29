import React from "react";
import { Modal } from "antd";

const TaskHistoryModal = ({ visible, logs, onClose }) => (
    <Modal title="Task History" open={visible} onCancel={onClose} footer={null}>
        <div className="space-y-3">
            {logs.map((log, i) => (
                <div key={i} className="border-l-4 border-blue-500 pl-4 py-2">
                    <div className="font-semibold">{log.action_type}</div>
                    <div className="text-sm text-gray-600">
                        {log.description}
                    </div>
                    {log.old_status && log.new_status && (
                        <div className="text-xs text-gray-500 mt-1">
                            {log.old_status} → {log.new_status}
                        </div>
                    )}
                    <div className="text-xs text-gray-400 mt-1">
                        by {log.created_by} • {log.created_at}
                    </div>
                </div>
            ))}
        </div>
    </Modal>
);

export default TaskHistoryModal;
