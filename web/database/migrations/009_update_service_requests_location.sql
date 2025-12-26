USE prefeitura_app;

ALTER TABLE service_requests
  ADD COLUMN neighborhood VARCHAR(120) DEFAULT NULL AFTER address,
  ADD COLUMN zip VARCHAR(12) DEFAULT NULL AFTER neighborhood;

UPDATE service_requests SET latitude = 0 WHERE latitude IS NULL;
UPDATE service_requests SET longitude = 0 WHERE longitude IS NULL;

ALTER TABLE service_requests
  MODIFY latitude DECIMAL(10,7) NOT NULL,
  MODIFY longitude DECIMAL(10,7) NOT NULL;

DROP TRIGGER IF EXISTS trg_service_requests_no_geo_update;
DELIMITER $$
CREATE TRIGGER trg_service_requests_no_geo_update
BEFORE UPDATE ON service_requests
FOR EACH ROW
BEGIN
    IF NEW.latitude <> OLD.latitude OR NEW.longitude <> OLD.longitude THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Latitude/Longitude nao podem ser alteradas.';
    END IF;
END$$
DELIMITER ;
