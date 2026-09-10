<?php
// Script para insertar datos de ejemplo
require_once 'config/database.php';

$db = new Database();

// Crear algunos PDFs de ejemplo
$samplePDFs = [
    [
        'title' => 'Guía Completa: Configuración de Servidores Ubuntu',
        'description' => 'Tutorial paso a paso para configurar servidores Ubuntu desde cero. Incluye configuraciones de seguridad, firewall, y aplicaciones básicas.',
        'category_id' => 1,
        'price' => 15.00,
        'file_path' => 'pdfs/sample_ubuntu.pdf'
    ],
    [
        'title' => 'Marketing Digital: Estrategias para Principiantes',
        'description' => 'Aprende las bases del marketing digital, SEO, contenido en redes sociales y campañas efectivas para tu negocio.',
        'category_id' => 1,
        'price' => 20.00,
        'file_path' => 'pdfs/sample_marketing.pdf'
    ],
    [
        'title' => 'Seguridad Informática: Protege tu Negocio',
        'description' => 'Guía esencial de seguridad informática, prevención de ataques, copias de seguridad y mejores prácticas.',
        'category_id' => 2,
        'price' => 25.00,
        'file_path' => 'pdfs/sample_security.pdf'
    ],
    [
        'title' => 'Sistemas CCTV: Configuración y Mantenimiento',
        'description' => 'Manual completo para instalar y mantener sistemas de videovigilancia CCTV. Cables, cámaras, NVR y monitoreo.',
        'category_id' => 3,
        'price' => 18.00,
        'file_path' => 'pdfs/sample_cctv.pdf'
    ],
    [
        'title' => 'Desarrollo Web: De Cero a Profesional',
        'description' => 'Ruta de aprendizaje completa para convertirse en desarrollador web. HTML, CSS, JavaScript, frameworks y más.',
        'category_id' => 1,
        'price' => 30.00,
        'file_path' => 'pdfs/sample_webdev.pdf'
    ],
    [
        'title' => 'Automatización con Bots: Guía Práctica',
        'description' => 'Crea y configura bots de WhatsApp, Telegram y automatizaciones para tu negocio. Ejemplos prácticos incluidos.',
        'category_id' => 4,
        'price' => 22.00,
        'file_path' => 'pdfs/sample_bots.pdf'
    ]
];

try {
    $conn = $db->getConnection();
    
    foreach ($samplePDFs as $pdf) {
        $stmt = $conn->prepare("INSERT INTO pdfs (title, description, category_id, price, file_path) 
                                VALUES (:title, :description, :category_id, :price, :file_path)");
        $stmt->execute($pdf);
    }
    
    echo "✅ PDFs de ejemplo insertados correctamente\n";
    
    // Mostrar resumen
    $stmt = $conn->query("SELECT COUNT(*) as total FROM pdfs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Total de PDFs: {$result['total']}\n\n";
    
    // Mostrar por categoría
    $stmt = $conn->query("SELECT c.name, c.icon, COUNT(p.id) as count 
                          FROM pdf_categories c 
                          LEFT JOIN pdfs p ON c.id = p.category_id 
                          GROUP BY c.id 
                          ORDER BY c.name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📚 PDFs por categoría:\n";
    foreach ($categories as $cat) {
        echo "   {$cat['icon']} {$cat['name']}: {$cat['count']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

?>

