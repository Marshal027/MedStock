CREATE DATABASE IF NOT EXISTS medical_store_db;
USE medical_store_db;

-- 1. SUPPLIER Table
CREATE TABLE SUPPLIER (
    supplier_ID INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address VARCHAR(255)
);

-- 2. MEDICINE Table
CREATE TABLE MEDICINE (
    med_Id INT AUTO_INCREMENT PRIMARY KEY,
    med_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    supplier_ID INT,
    FOREIGN KEY (supplier_ID) REFERENCES SUPPLIER(supplier_ID) ON DELETE SET NULL
);

-- 3. CUSTOMER Table
CREATE TABLE CUSTOMER (
    customer_Id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address VARCHAR(255)
);

-- 4. PURCHASE Table
CREATE TABLE PURCHASE (
    purchase_ID INT AUTO_INCREMENT PRIMARY KEY,
    supplier_ID INT,
    med_ID INT,
    purchase_date DATE NOT NULL,
    pur_quantity INT NOT NULL,
    unit_cost DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (supplier_ID) REFERENCES SUPPLIER(supplier_ID) ON DELETE CASCADE,
    FOREIGN KEY (med_ID) REFERENCES MEDICINE(med_Id) ON DELETE CASCADE
);

-- 5. SALES Table
CREATE TABLE SALES (
    sales_ID INT AUTO_INCREMENT PRIMARY KEY,
    customer_ID INT,
    med_ID INT,
    sales_date DATE NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (customer_ID) REFERENCES CUSTOMER(customer_Id) ON DELETE CASCADE,
    FOREIGN KEY (med_ID) REFERENCES MEDICINE(med_Id) ON DELETE CASCADE
);

-- Sample Data for Testing
INSERT INTO SUPPLIER (supplier_name, phone, address) VALUES
('Cipla Distributors', '9822011223', 'Panjim, Goa'),
('Sun Pharma Agencies', '9822044556', 'Mapusa, Goa'),
('Goa Medico Supplies', '9822077889', 'Margao, Goa');

INSERT INTO MEDICINE (med_name, category, price, stock, supplier_ID) VALUES
('Paracetamol 500mg', 'Analgesic', 25.00, 150, 1),
('Amoxicillin 250mg', 'Antibiotic', 120.00, 8, 2),
('Cetirizine 10mg', 'Antihistamine', 40.00, 65, 1),
('Insulin Glargine', 'Antidiabetic', 650.00, 5, 3),
('Vitamin D3 60K', 'Supplement', 180.00, 220, 2);

INSERT INTO CUSTOMER (customer_name, phone, address) VALUES
('Rohan Naik', '9123456780', 'Assagao, Goa'),
('Priya Dessai', '9123456781', 'Porvorim, Goa');

INSERT INTO PURCHASE (supplier_ID, med_ID, purchase_date, pur_quantity, unit_cost) VALUES
(1, 1, '2026-10-01', 350, 18.00),
(1, 3, '2026-10-03', 200, 28.00),
(2, 5, '2026-10-05', 600, 130.00),
(3, 4, '2026-10-08', 50, 520.00);

INSERT INTO SALES (customer_ID, med_ID, sales_date, quantity, total_price) VALUES
(1, 1, '2026-10-09', 4, 100.00),
(2, 5, '2026-10-09', 2, 360.00);