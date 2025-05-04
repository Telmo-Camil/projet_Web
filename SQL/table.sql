CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100),
  prenom VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  mot_de_passe VARCHAR(255),
  role ENUM('admin', 'gestionnaire', 'employe') NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE product (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  prix DECIMAL(10,2),
  quantite INT,
  categories_id INT,
  supplier_id INT,
  seuil_alerte INT DEFAULT 5,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (categories_id) REFERENCES categories(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier(id)
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL
);

CREATE TABLE supplier (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150),
  email VARCHAR(150),
  telephone VARCHAR(20),
  adresse TEXT
);

CREATE TABLE stock_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT,
  supplier_id INT,
  quantite INT,
  date_entree DATETIME DEFAULT CURRENT_TIMESTAMP,
  users_id INT,
  FOREIGN KEY (product_id) REFERENCES product(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier(id),
  FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE out_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT,
  quantite INT,
  date_out DATETIME DEFAULT CURRENT_TIMESTAMP,
  users_id INT,
  motif VARCHAR(255),
  FOREIGN KEY (product_id) REFERENCES product(id),
  FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(150) NOT NULL,
  prix DECIMAL(10,2) NOT NULL,
  quantite INT NOT NULL,
  categories_id INT,
  supplier_id INT,
  date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_livraison DATE NOT NULL,
  users_id INT,
  FOREIGN KEY (categories_id) REFERENCES categories(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier(id),
  FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100),
  contenu TEXT,
  date_generation DATETIME DEFAULT CURRENT_TIMESTAMP,
  users_id INT,
  FOREIGN KEY (users_id) REFERENCES users(id)
);

CREATE TABLE action_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  users_id INT,
  action TEXT,
  date_action DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (users_id) REFERENCES users(id)
);
