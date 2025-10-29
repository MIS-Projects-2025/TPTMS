import React from "react";
import { Modal, Input } from "antd";

const TaskNoteModal = ({ visible, noteText, onChange, onOk, onCancel }) => (
    <Modal
        title="Add Note"
        open={visible}
        onCancel={onCancel}
        onOk={onOk}
        okText="Add Note"
    >
        <Input.TextArea
            rows={4}
            value={noteText}
            onChange={(e) => onChange(e.target.value)}
            placeholder="Enter your note here..."
            maxLength={1000}
        />
    </Modal>
);

export default TaskNoteModal;
