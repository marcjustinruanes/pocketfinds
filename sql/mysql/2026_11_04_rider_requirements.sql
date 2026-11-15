-- Additive mobile rider workflow. Existing website source is unchanged.
CREATE TABLE IF NOT EXISTS mobile_rider_identifiers (
 kind VARCHAR(24) NOT NULL, value VARCHAR(64) NOT NULL, rider_id BIGINT NOT NULL,
 PRIMARY KEY(kind,value), INDEX rider(rider_id)
);
CREATE TABLE IF NOT EXISTS mobile_rider_dispatch_points (
 hub_id BIGINT PRIMARY KEY, latitude DECIMAL(10,7) NOT NULL, longitude DECIMAL(10,7) NOT NULL
);
CREATE TABLE IF NOT EXISTS mobile_rider_presence (
 rider_id BIGINT PRIMARY KEY, latitude DECIMAL(10,7) NULL, longitude DECIMAL(10,7) NULL,
 online BOOLEAN NOT NULL DEFAULT 0, heartbeat_at DATETIME NULL
);
CREATE TABLE IF NOT EXISTS mobile_rider_offers (
 id CHAR(36) PRIMARY KEY, shipment_id CHAR(36) NOT NULL, leg VARCHAR(16) NOT NULL,
 rider_id BIGINT NOT NULL, state VARCHAR(24) NOT NULL DEFAULT 'offered',
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
 INDEX parcel_leg(shipment_id,leg), INDEX rider_state(rider_id,state)
);
CREATE TABLE IF NOT EXISTS mobile_rider_proofs (
 shipment_id CHAR(36) PRIMARY KEY, rider_id BIGINT NOT NULL, photo VARCHAR(255) NOT NULL,
 latitude DECIMAL(10,7) NOT NULL, longitude DECIMAL(10,7) NOT NULL,
 cod_amount DECIMAL(12,2) NOT NULL, recorded_at DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS mobile_rider_otp (
 shipment_id CHAR(36) PRIMARY KEY, code_encrypted TEXT NULL, locked_until DATETIME NULL,
 capture_token CHAR(64) NULL, capture_expires_at DATETIME NULL
);
CREATE TABLE IF NOT EXISTS mobile_rider_pay (
 shipment_id CHAR(36) NOT NULL, leg VARCHAR(16) NOT NULL, rider_id BIGINT NOT NULL,
 amount DECIMAL(12,2) NOT NULL, cod_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL, PRIMARY KEY(shipment_id,leg)
);
CREATE TABLE IF NOT EXISTS mobile_rider_remittances (
 id CHAR(36) PRIMARY KEY, rider_id BIGINT NOT NULL, company VARCHAR(255) NOT NULL,
 amount DECIMAL(12,2) NOT NULL, reference VARCHAR(150) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending', confirmed_by BIGINT NULL,
 created_at DATETIME NOT NULL, confirmed_at DATETIME NULL
);
CREATE TABLE IF NOT EXISTS mobile_rider_vehicle_requests (
 id CHAR(36) PRIMARY KEY, rider_id BIGINT NOT NULL, payload JSON NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending', reason VARCHAR(300) NULL,
 decided_by BIGINT NULL, created_at DATETIME NOT NULL, decided_at DATETIME NULL,
 INDEX rider_status(rider_id,status)
);
CREATE TABLE IF NOT EXISTS mobile_rider_audit (
 id CHAR(36) PRIMARY KEY, actor_id BIGINT NOT NULL, action VARCHAR(60) NOT NULL,
 subject_id VARCHAR(64) NOT NULL, old_value JSON NULL, new_value JSON NOT NULL,
 created_at DATETIME NOT NULL
);
