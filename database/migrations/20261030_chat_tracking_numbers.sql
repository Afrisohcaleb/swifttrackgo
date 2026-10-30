ALTER TABLE chat_conversations
    ADD COLUMN shipment_id BIGINT UNSIGNED NULL AFTER id,
    ADD UNIQUE KEY uq_chat_conversations_shipment (shipment_id),
    ADD CONSTRAINT fk_chat_conversations_shipment
        FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL;
