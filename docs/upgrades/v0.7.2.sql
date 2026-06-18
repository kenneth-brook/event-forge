-- Event Forge v0.7.2-TC schema upgrade
-- App version: 0.7.2
-- Release channel: test-candidate
-- Schema version: 9

ALTER TABLE events
  ADD COLUMN event_cost VARCHAR(255) DEFAULT NULL AFTER external_url;

INSERT INTO eventforge_system (system_key, system_value)
VALUES ('schema_version', '9')
ON DUPLICATE KEY UPDATE system_value = VALUES(system_value);

INSERT INTO eventforge_system (system_key, system_value)
VALUES ('app_version', '0.7.2')
ON DUPLICATE KEY UPDATE system_value = VALUES(system_value);

INSERT INTO eventforge_system (system_key, system_value)
VALUES ('release_channel', 'test-candidate')
ON DUPLICATE KEY UPDATE system_value = VALUES(system_value);
