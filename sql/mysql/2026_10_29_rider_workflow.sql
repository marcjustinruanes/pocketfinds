-- Additive final-delivery verification records. Native shipment/order statuses remain VARCHAR.
CREATE TABLE IF NOT EXISTS delivery_verifications (
 shipment_id CHAR(36) PRIMARY KEY,
 pickup_verified_at DATETIME NULL, pickup_rider_id BIGINT NULL,
 otp_hash VARCHAR(255) NULL, otp_recipient_id BIGINT NULL,
 otp_sent_at DATETIME NULL, otp_expires_at DATETIME NULL,
 otp_attempts INT NOT NULL DEFAULT 0, otp_consumed_at DATETIME NULL,
 delivery_verified_at DATETIME NULL, return_requested_at DATETIME NULL,
 return_received_at DATETIME NULL, return_received_by BIGINT NULL,
 CONSTRAINT delivery_verifications_shipment_fk FOREIGN KEY (shipment_id) REFERENCES shipments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS delivery_attempts (
 id CHAR(36) PRIMARY KEY, shipment_id CHAR(36) NOT NULL, rider_id BIGINT NOT NULL,
 attempt_number INT NOT NULL, reason VARCHAR(60) NOT NULL, notes VARCHAR(1000) NULL,
 decision VARCHAR(40) NOT NULL DEFAULT 'pending', scheduled_at DATETIME NULL,
 attempted_at DATETIME NOT NULL,
 UNIQUE KEY delivery_attempt_number (shipment_id, attempt_number),
 CONSTRAINT delivery_attempts_shipment_fk FOREIGN KEY (shipment_id) REFERENCES shipments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS delivery_events (
 id CHAR(36) PRIMARY KEY, shipment_id CHAR(36) NOT NULL, actor_id BIGINT NOT NULL,
 action VARCHAR(40) NOT NULL, request_id CHAR(36) NOT NULL, payload_hash CHAR(64) NOT NULL,
 status VARCHAR(30) NOT NULL, result_error VARCHAR(255) NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY delivery_event_request (shipment_id, request_id),
 CONSTRAINT delivery_events_shipment_fk FOREIGN KEY (shipment_id) REFERENCES shipments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
