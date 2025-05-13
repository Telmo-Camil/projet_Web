CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  mot_de_passe VARCHAR(255) NOT NULL,
  role ENUM('admin', 'gestionnaire', 'employe') NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL
);

CREATE TABLE supplier (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  telephone VARCHAR(20),
  adresse TEXT
);

CREATE TABLE product (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  prix DECIMAL(10,2) NOT NULL,
  quantite INT NOT NULL DEFAULT 0,
  categories_id INT NOT NULL,
  supplier_id INT NOT NULL,
  seuil_alerte INT DEFAULT 5,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (categories_id) REFERENCES categories(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier(id)
);

CREATE TABLE stock_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  supplier_id INT NOT NULL,
  quantite INT NOT NULL,
  date_entree DATETIME DEFAULT CURRENT_TIMESTAMP,
  users_id INT NOT NULL,
  FOREIGN KEY (product_id) REFERENCES product(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier(id),
  FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE stock_sorties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    reason ENUM('vente', 'utilisation', 'perte') NOT NULL,
    date DATETIME NOT NULL,
    remaining_stock INT NOT NULL,
    users_id INT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES product(id),
    FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(150) NOT NULL,
  prix DECIMAL(10,2) NOT NULL,
  quantite INT NOT NULL,
  categories_id INT NOT NULL,
  supplier_id INT NOT NULL,
  date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_livraison DATE NOT NULL,
  users_id INT NOT NULL,
  status ENUM('en_attente', 'validee', 'livree', 'annulee') DEFAULT 'en_attente',
  COLUMN stock_updated BOOLEAN DEFAULT 0
  FOREIGN KEY (categories_id) REFERENCES categories(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier(id),
  FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    date_start DATE NOT NULL,
    date_end DATE NOT NULL,
    categories TEXT,
    format VARCHAR(10) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('generated', 'downloaded', 'archived') DEFAULT 'generated',
    users_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE action_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  users_id INT NOT NULL,
  action TEXT NOT NULL,
  date_action DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (users_id) REFERENCES users(id)
);

-- Création des index pour optimiser les performances
CREATE INDEX idx_product_category ON product(categories_id);
CREATE INDEX idx_product_supplier ON product(supplier_id);
CREATE INDEX idx_report_type ON reports(type);
CREATE INDEX idx_report_dates ON reports(date_start, date_end);
CREATE INDEX idx_stock_dates ON stock_sorties(date);
CREATE INDEX idx_orders_dates ON orders(date_commande, date_livraison);