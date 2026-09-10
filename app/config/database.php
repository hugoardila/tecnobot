<?php
// Configuración de base de datos para el sistema de PDFs
class Database {
    private $db;
    
    public function __construct($dbPath = __DIR__ . '/../pdfs_store.db') {
        try {
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initTables();
        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }
    
    private function initTables() {
        try {
            // Tabla de categorías de PDFs
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS pdf_categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    icon TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Tabla de PDFs
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS pdfs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    description TEXT,
                    category_id INTEGER,
                    file_path TEXT NOT NULL,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    cover_image TEXT,
                    is_custom BOOLEAN DEFAULT 0,
                    downloads_count INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES pdf_categories(id)
                )
            ");
            
            // Tabla de ventas
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sales (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    pdf_id INTEGER NOT NULL,
                    buyer_email TEXT NOT NULL,
                    buyer_name TEXT,
                    transaction_id TEXT UNIQUE,
                    payment_method TEXT,
                    amount DECIMAL(10,2) NOT NULL,
                    status TEXT DEFAULT 'pending',
                    download_link TEXT,
                    expires_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (pdf_id) REFERENCES pdfs(id)
                )
            ");
            
            // Tabla de solicitudes personalizadas
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS custom_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    customer_name TEXT NOT NULL,
                    customer_email TEXT NOT NULL,
                    topic TEXT NOT NULL,
                    description TEXT,
                    status TEXT DEFAULT 'pending',
                    pdf_id INTEGER,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (pdf_id) REFERENCES pdfs(id)
                )
            ");
            
            // Insertar categorías predeterminadas si no existen
            $this->db->exec("
                INSERT OR IGNORE INTO pdf_categories (id, name, description, icon) VALUES
                (1, 'Tutoriales', 'Guías paso a paso para aprender', '📚'),
                (2, 'Documentación Técnica', 'Información técnica detallada', '🔧'),
                (3, 'Guías de Configuración', 'Configuraciones y setup', '⚙️'),
                (4, 'Casos de Uso', 'Ejemplos y casos reales', '💼')
            ");
        } catch (PDOException $e) {
            // Si las tablas ya existen, ignorar el error
            // Solo registrar si es un error real
            if (strpos($e->getMessage(), 'already exists') === false) {
                error_log("Error en initTables: " . $e->getMessage());
            }
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function getAllPDFs($categoryId = null) {
        $sql = "SELECT p.*, c.name as category_name, c.icon as category_icon 
                FROM pdfs p 
                LEFT JOIN pdf_categories c ON p.category_id = c.id";
        
        if ($categoryId) {
            $sql .= " WHERE p.category_id = :category_id";
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        if ($categoryId) {
            $stmt->bindParam(':category_id', $categoryId);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCategories() {
        $stmt = $this->db->query("SELECT * FROM pdf_categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPDFById($id) {
        $stmt = $this->db->prepare("SELECT p.*, c.name as category_name FROM pdfs p 
                                     LEFT JOIN pdf_categories c ON p.category_id = c.id 
                                     WHERE p.id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createSale($pdfId, $buyerEmail, $buyerName, $transactionId, $amount, $paymentMethod = 'manual') {
        $downloadLink = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $this->db->prepare("INSERT INTO sales 
                                    (pdf_id, buyer_email, buyer_name, transaction_id, amount, payment_method, download_link, expires_at, status) 
                                    VALUES (:pdf_id, :buyer_email, :buyer_name, :transaction_id, :amount, :payment_method, :download_link, :expires_at, 'completed')");
        
        $stmt->bindParam(':pdf_id', $pdfId);
        $stmt->bindParam(':buyer_email', $buyerEmail);
        $stmt->bindParam(':buyer_name', $buyerName);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':payment_method', $paymentMethod);
        $stmt->bindParam(':download_link', $downloadLink);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        $stmt->execute();
        
        // Incrementar contador de descargas
        $this->db->exec("UPDATE pdfs SET downloads_count = downloads_count + 1 WHERE id = $pdfId");
        
        return $downloadLink;
    }
    
    public function createCustomRequest($name, $email, $topic, $description) {
        $stmt = $this->db->prepare("INSERT INTO custom_requests (customer_name, customer_email, topic, description) 
                                    VALUES (:name, :email, :topic, :description)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':topic', $topic);
        $stmt->bindParam(':description', $description);
        $stmt->execute();
        return $this->db->lastInsertId();
    }
    
    public function getSaleByDownloadLink($link) {
        $stmt = $this->db->prepare("SELECT s.*, p.file_path, p.title FROM sales s 
                                     JOIN pdfs p ON s.pdf_id = p.id 
                                     WHERE s.download_link = :link AND s.expires_at > datetime('now')");
        $stmt->bindParam(':link', $link);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca PDFs similares a un tema dado
     * Compara el topic/description con título y descripción de PDFs existentes
     */
    public function findSimilarPDFs($topic, $description = '', $limit = 5) {
        // Extraer palabras clave del topic y descripción
        $keywords = $this->extractKeywords($topic . ' ' . $description);
        
        if (empty($keywords)) {
            return [];
        }
        
        // Construir query de búsqueda
        $conditions = [];
        foreach ($keywords as $index => $keyword) {
            $conditions[] = "(p.title LIKE :keyword$index OR p.description LIKE :keyword$index)";
        }
        
        // Usar OR para encontrar si alguna palabra clave coincide (más flexible)
        $whereClause = implode(' OR ', $conditions);
        
        $sql = "SELECT p.*, c.name as category_name, c.icon as category_icon
                FROM pdfs p 
                LEFT JOIN pdf_categories c ON p.category_id = c.id 
                WHERE $whereClause
                ORDER BY downloads_count DESC, p.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind de parámetros
        foreach ($keywords as $index => $keyword) {
            $searchKeyword = '%' . $keyword . '%';
            $stmt->bindValue(":keyword$index", $searchKeyword);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Extrae palabras clave importantes de un texto
     * Elimina stop words y retorna las más relevantes
     */
    private function extractKeywords($text) {
        // Convertir a minúsculas y limpiar
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        
        // Palabras a ignorar (stop words en español)
        $stopWords = ['de', 'la', 'el', 'y', 'en', 'un', 'una', 'con', 'para', 'por', 'el', 'los', 'las', 'del', 'al', 'como', 'cuando', 'donde', 'que', 'qué', 'es', 'son', 'ser', 'estar', 'fue', 'fueron', 'muy', 'más', 'sí', 'no', 'también', 'tambien', 'pero', 'sin', 'otro', 'otros', 'cual', 'cuales', 'se', 'le', 'les', 'a', 'su', 'sus', 'este', 'esta', 'estos', 'estas'];
        
        // Separar en palabras
        $words = preg_split('/\s+/', $text);
        
        // Filtrar palabras válidas
        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            // Solo palabras de 3 o más caracteres que no sean stop words
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        // Retornar hasta 10 palabras clave más frecuentes
        $keywords = array_slice(array_unique($keywords), 0, 10);
        
        return $keywords;
    }
    
    /**
     * Agrega un PDF custom al catálogo (lo hace disponible para venta)
     */
    public function addPDFToCatalog($title, $description, $categoryId, $filePath, $price, $isCustom = true) {
        $stmt = $this->db->prepare("INSERT INTO pdfs (title, description, category_id, file_path, price, is_custom) 
                                    VALUES (:title, :description, :category_id, :file_path, :price, :is_custom)");
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category_id', $categoryId);
        $stmt->bindParam(':file_path', $filePath);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':is_custom', $isCustom);
        $stmt->execute();
        return $this->db->lastInsertId();
    }
}


