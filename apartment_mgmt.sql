
CREATE DATABASE IF NOT EXISTS arnaut;
USE arnaut;

-- Table: buildings
CREATE TABLE buildings (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  address varchar(255) NOT NULL,
  is_archived tinyint(1) DEFAULT 0,
  created_at datetime DEFAULT current_timestamp(),
  updated_at datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: apartments
CREATE TABLE apartments (
  id int(11) NOT NULL AUTO_INCREMENT,
  building_id int(11) NOT NULL,
  unit_number varchar(50) NOT NULL,
  type varchar(50) NOT NULL,
  status enum('vacant','occupied') NOT NULL DEFAULT 'vacant',
  created_at datetime DEFAULT current_timestamp(),
  updated_at datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  is_archived tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY building_id (building_id),
  CONSTRAINT apartments_ibfk_1 FOREIGN KEY (building_id) REFERENCES buildings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: tenants
CREATE TABLE tenants (
  id varchar(50) NOT NULL,
  name varchar(100) NOT NULL,
  contact varchar(50) NOT NULL,
  apartment_id int(11) NOT NULL,
  move_in_date date NOT NULL,
  PRIMARY KEY (id),
  KEY apartment_id (apartment_id),
  CONSTRAINT tenants_ibfk_1 FOREIGN KEY (apartment_id) REFERENCES apartments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: maintenance
CREATE TABLE maintenance (
  id int(11) NOT NULL AUTO_INCREMENT,
  apartment_id int(11) NOT NULL,
  request_date date NOT NULL,
  description text NOT NULL,
  status enum('pending','in_progress','resolved') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (id),
  KEY apartment_id (apartment_id),
  CONSTRAINT maintenance_ibfk_1 FOREIGN KEY (apartment_id) REFERENCES apartments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: payments
CREATE TABLE payments (
  id int(11) NOT NULL AUTO_INCREMENT,
  tenant_id varchar(50) NOT NULL,
  amount decimal(10,2) NOT NULL,
  payment_date date NOT NULL,
  remarks text DEFAULT NULL,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  CONSTRAINT payments_ibfk_1 FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: users
CREATE TABLE users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(50) NOT NULL,
  password varchar(255) NOT NULL,
  role enum('admin','staff') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$rum9HSOW/O7Q6z0wuap2deAW2bGWdwgBuyd7T8kuSEQkeRX7dNGPi', 'admin');

-- Demo data for portfolio preview
INSERT INTO buildings (id, name, address, is_archived, created_at) VALUES
(1, 'Sunrise Residences', '12 Mabini Street, Cebu City', 0, '2026-01-08 09:15:00'),
(2, 'Harbor View Apartments', '88 Osmena Boulevard, Cebu City', 0, '2026-01-10 10:20:00'),
(3, 'Maple Court', '45 Ramos Street, Cebu City', 0, '2026-01-14 14:05:00'),
(4, 'Northpoint Flats', '21 Escario Street, Cebu City', 0, '2026-01-18 11:45:00');

INSERT INTO apartments (id, building_id, unit_number, type, status, created_at, is_archived) VALUES
(1, 1, '101', 'Studio', 'occupied', '2026-01-08 09:30:00', 0),
(2, 1, '102', '1 Bedroom', 'occupied', '2026-01-08 09:35:00', 0),
(3, 1, '201', '2 Bedroom', 'vacant', '2026-01-08 09:40:00', 0),
(4, 2, 'A1', 'Studio', 'occupied', '2026-01-10 10:35:00', 0),
(5, 2, 'A2', '1 Bedroom', 'occupied', '2026-01-10 10:40:00', 0),
(6, 2, 'B1', '2 Bedroom', 'vacant', '2026-01-10 10:45:00', 0),
(7, 3, '301', 'Studio', 'occupied', '2026-01-14 14:20:00', 0),
(8, 3, '302', '1 Bedroom', 'occupied', '2026-01-14 14:25:00', 0),
(9, 3, '401', '2 Bedroom', 'occupied', '2026-01-14 14:30:00', 0),
(10, 4, 'N-01', 'Studio', 'vacant', '2026-01-18 12:00:00', 0),
(11, 4, 'N-02', '1 Bedroom', 'occupied', '2026-01-18 12:05:00', 0),
(12, 4, 'N-03', '2 Bedroom', 'vacant', '2026-01-18 12:10:00', 0);

INSERT INTO tenants (id, name, contact, apartment_id, move_in_date) VALUES
('TEN-1001', 'Mia Santos', 'mia.santos@example.com', 1, '2026-02-01'),
('TEN-1002', 'Carlos Reyes', '0917-482-1145', 2, '2026-02-05'),
('TEN-1003', 'Angela Lim', 'angela.lim@example.com', 4, '2026-02-12'),
('TEN-1004', 'Nathan Cruz', '0928-775-4021', 5, '2026-02-20'),
('TEN-1005', 'Janelle Garcia', 'janelle.garcia@example.com', 7, '2026-03-01'),
('TEN-1006', 'Paolo Mendoza', '0916-338-9088', 8, '2026-03-08'),
('TEN-1007', 'Sofia Navarro', 'sofia.navarro@example.com', 9, '2026-03-15'),
('TEN-1008', 'Rafael Dela Cruz', '0935-610-2274', 11, '2026-04-01');

INSERT INTO maintenance (id, apartment_id, request_date, description, status) VALUES
(1, 1, '2026-04-02', 'Kitchen faucet has a slow leak under the sink.', 'resolved'),
(2, 4, '2026-04-10', 'Air conditioner is not cooling properly.', 'in_progress'),
(3, 5, '2026-04-14', 'Bedroom light fixture needs replacement.', 'resolved'),
(4, 8, '2026-04-28', 'Bathroom drain is slow after heavy use.', 'pending'),
(5, 9, '2026-05-03', 'Balcony door lock is loose.', 'in_progress'),
(6, 11, '2026-05-08', 'Water pressure drops in the evening.', 'pending'),
(7, 2, '2026-05-16', 'Smoke detector battery replacement requested.', 'resolved');

INSERT INTO payments (id, tenant_id, amount, payment_date, remarks) VALUES
(1, 'TEN-1001', 8500.00, '2026-03-01', 'March rent paid in full'),
(2, 'TEN-1002', 12000.00, '2026-03-03', 'March rent paid in full'),
(3, 'TEN-1003', 9000.00, '2026-03-05', 'March rent paid in full'),
(4, 'TEN-1004', 11500.00, '2026-03-08', 'March rent paid in full'),
(5, 'TEN-1005', 8500.00, '2026-04-01', 'April rent paid in full'),
(6, 'TEN-1006', 11000.00, '2026-04-02', 'April rent paid in full'),
(7, 'TEN-1007', 14500.00, '2026-04-03', 'April rent paid in full'),
(8, 'TEN-1001', 8500.00, '2026-04-05', 'April rent paid in full'),
(9, 'TEN-1002', 12000.00, '2026-04-06', 'April rent paid in full'),
(10, 'TEN-1003', 9000.00, '2026-04-07', 'April rent paid in full'),
(11, 'TEN-1004', 11500.00, '2026-04-09', 'April rent paid in full'),
(12, 'TEN-1008', 10500.00, '2026-05-01', 'May rent paid in full'),
(13, 'TEN-1005', 8500.00, '2026-05-02', 'May rent paid in full'),
(14, 'TEN-1006', 11000.00, '2026-05-04', 'May rent paid in full'),
(15, 'TEN-1007', 14500.00, '2026-05-06', 'May rent paid in full');
