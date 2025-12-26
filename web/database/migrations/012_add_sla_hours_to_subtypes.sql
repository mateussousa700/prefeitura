USE prefeitura_app;

ALTER TABLE service_subtypes
  ADD COLUMN sla_hours INT UNSIGNED NOT NULL DEFAULT 72 AFTER name;

UPDATE service_subtypes
SET sla_hours = 72
WHERE sla_hours IS NULL OR sla_hours = 0;

UPDATE service_requests sr
LEFT JOIN service_subtypes ss ON ss.id = sr.service_subtype_id
SET sr.sla_due_at = DATE_ADD(sr.created_at, INTERVAL COALESCE(NULLIF(ss.sla_hours, 0), 72) HOUR)
WHERE sr.sla_due_at IS NULL;

DROP TRIGGER IF EXISTS trg_service_requests_no_sla_update;
DELIMITER $$
CREATE TRIGGER trg_service_requests_no_sla_update
BEFORE UPDATE ON service_requests
FOR EACH ROW
BEGIN
    IF NEW.sla_due_at <> OLD.sla_due_at THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SLA nao pode ser alterado.';
    END IF;
END$$
DELIMITER ;
