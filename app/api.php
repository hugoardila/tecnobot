<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config/database.php';
require_once 'config/paypal.php';
require_once 'config/openai_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = new Database();
    
    switch ($method) {
        case 'GET':
            handleGet($action, $db);
            break;
        case 'POST':
            handlePost($action, $db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

function handleGet($action, $db) {
    switch ($action) {
        case 'pdfs':
            $categoryId = $_GET['category'] ?? null;
            $pdfs = $db->getAllPDFs($categoryId);
            echo json_encode(['success' => true, 'data' => $pdfs]);
            break;
            
        case 'categories':
            $categories = $db->getCategories();
            echo json_encode(['success' => true, 'data' => $categories]);
            break;
            
        case 'search':
            $topic = $_GET['topic'] ?? '';
            $description = $_GET['description'] ?? '';
            
            if (empty($topic) && empty($description)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tema o descripción requeridos']);
                return;
            }
            
            $similarPDFs = $db->findSimilarPDFs($topic, $description);
            echo json_encode(['success' => true, 'data' => $similarPDFs]);
            break;
            
        case 'pdf':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID requerido']);
                return;
            }
            $pdf = $db->getPDFById($id);
            if (!$pdf) {
                http_response_code(404);
                echo json_encode(['error' => 'PDF no encontrado']);
                return;
            }
            echo json_encode(['success' => true, 'data' => $pdf]);
            break;
            
        case 'download':
            $link = $_GET['link'] ?? null;
            if (!$link) {
                http_response_code(400);
                echo json_encode(['error' => 'Link de descarga requerido']);
                return;
            }
            
            $sale = $db->getSaleByDownloadLink($link);
            if (!$sale) {
                http_response_code(404);
                echo json_encode(['error' => 'Link inválido o expirado']);
                return;
            }
            
            $filePath = __DIR__ . '/' . $sale['file_path'];
            if (!file_exists($filePath)) {
                http_response_code(404);
                echo json_encode(['error' => 'Archivo no encontrado']);
                return;
            }
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            readfile($filePath);
            exit;
            
        case 'preview':
            $file = $_GET['file'] ?? null;
            if (!$file) {
                http_response_code(400);
                echo json_encode(['error' => 'Archivo requerido']);
                return;
            }
            
            // Solo permitir archivos dentro de pdfs/
            if (strpos($file, 'pdfs/') !== 0 || strpos($file, '..') !== false) {
                http_response_code(403);
                echo json_encode(['error' => 'Acceso denegado']);
                return;
            }
            
            $filePath = __DIR__ . '/' . $file;
            if (!file_exists($filePath)) {
                http_response_code(404);
                echo json_encode(['error' => 'Archivo no encontrado']);
                return;
            }
            
            // Validar que sea PDF
            if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'pdf') {
                http_response_code(400);
                echo json_encode(['error' => 'Solo se permiten archivos PDF']);
                return;
            }
            
            // Verificar que exista en la BD
            $pdfExists = $db->getConnection()->prepare("SELECT id FROM pdfs WHERE file_path = ?");
            $pdfExists->execute([$file]);
            if (!$pdfExists->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'PDF no disponible']);
                return;
            }
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            readfile($filePath);
            exit;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
    }
}

function handlePost($action, $db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'purchase':
            requirePurchase($data, $db);
            break;
            
        case 'custom-request':
            requireCustomRequest($data, $db);
            break;
            
        case 'generate-custom':
            generateCustomPDF($data, $db);
            break;
            
        case 'generate-from-chat':
            generateFromChat($data, $db);
            break;
            
        case 'create-paypal-order':
            createPayPalOrder($data, $db);
            break;
            
        case 'capture-paypal-order':
            capturePayPalOrder($data, $db);
            break;
            
        case 'chat':
            handleOpenAIChat($data);
            break;
            
        case 'generate-content':
            handleOpenAIGenerateContent($data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
    }
}

function requirePurchase($data, $db) {
    $required = ['pdf_id', 'buyer_email', 'buyer_name', 'transaction_id', 'amount'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        $downloadLink = $db->createSale(
            $data['pdf_id'],
            $data['buyer_email'],
            $data['buyer_name'],
            $data['transaction_id'],
            $data['amount'],
            $data['payment_method'] ?? 'manual'
        );
        
        echo json_encode([
            'success' => true,
            'download_link' => $downloadLink
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function requireCustomRequest($data, $db) {
    $required = ['name', 'email', 'topic', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        // Primero buscar si ya existe un PDF similar
        $similarPDFs = $db->findSimilarPDFs($data['topic'], $data['description'], 3);
        
        $requestId = $db->createCustomRequest(
            $data['name'],
            $data['email'],
            $data['topic'],
            $data['description']
        );
        
        // Enviar email de confirmación (implementar luego)
        sendConfirmationEmail($data['email'], $requestId);
        
        $response = [
            'success' => true,
            'request_id' => $requestId
        ];
        
        // Si hay PDFs similares, incluir recomendaciones
        if (!empty($similarPDFs)) {
            $response['has_similar'] = true;
            $response['similar_pdfs'] = $similarPDFs;
            $response['message'] = '¡Encontramos PDFs similares! Quizás ya tenemos lo que necesitas.';
        } else {
            $response['has_similar'] = false;
            $response['message'] = 'Solicitud recibida. Crearemos tu PDF personalizado pronto.';
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function generateCustomPDF($data, $db) {
    require_once 'config/pdf_generator.php';
    
    $required = ['request_id', 'title', 'sections'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        $htmlContent = PDFGenerator::generateTutorial($data['title'], $data['sections']);
        
        $filename = 'pdfs/custom_' . time() . '_' . $data['request_id'] . '.html';
        $filePath = __DIR__ . '/' . $filename;
        
        file_put_contents($filePath, $htmlContent);
        
        // Aquí agregarías el PDF a la base de datos
        
        echo json_encode([
            'success' => true,
            'file_path' => $filename
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function generateFromChat($data, $db) {
    require_once __DIR__ . '/config/pdf_generator.php';
    
    $required = ['title', 'description', 'category_id', 'content', 'file_path'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        // Generar PDF desde HTML usando mPDF
        $htmlContent = $data['content'];
        
        // Cambiar extensión de .html a .pdf
        $pdfPath = str_replace('.html', '.pdf', $data['file_path']);
        $filePath = __DIR__ . '/' . $pdfPath;
        $directory = dirname($filePath);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Generar PDF real usando mPDF
        $success = PDFGenerator::generatePDFFromHTMLString($htmlContent, $filePath);
        
        if (!$success) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al generar el PDF']);
            return;
        }
        
        // Precio por defecto para PDFs generados desde chat
        $defaultPrice = 15.00;
        
        // Agregar el PDF a la base de datos
        $pdfId = $db->addPDFToCatalog(
            $data['title'],
            $data['description'],
            $data['category_id'],
            $pdfPath,
            $defaultPrice,
            true // is_custom
        );
        
        echo json_encode([
            'success' => true,
            'pdf_id' => $pdfId,
            'price' => $defaultPrice,
            'message' => 'PDF generado y guardado exitosamente',
            'file_path' => $pdfPath
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createPayPalOrder($data, $db) {
    $required = ['pdf_id', 'amount'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        // Obtener información del PDF
        $pdf = $db->getPDFById($data['pdf_id']);
        if (!$pdf) {
            http_response_code(404);
            echo json_encode(['error' => 'PDF no encontrado']);
            return;
        }
        
        // Configurar PayPal
        $paypal = new PayPalConfig();
        
        // Crear orden
        $invoiceId = 'PDF_' . $data['pdf_id'] . '_' . time();
        $result = $paypal->createOrder(
            floatval($data['amount']),
            'USD',
            $pdf['title'],
            $invoiceId
        );
        
        echo json_encode([
            'success' => true,
            'order_id' => $result['order_id'],
            'approve_url' => $result['approve_url']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function capturePayPalOrder($data, $db) {
    $required = ['order_id', 'pdf_id'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        // Configurar PayPal
        $paypal = new PayPalConfig();
        
        // Capturar orden
        $result = $paypal->captureOrder($data['order_id']);
        
        if ($result['status'] === 'COMPLETED') {
            // Crear registro de venta
            $downloadLink = $db->createSale(
                $data['pdf_id'],
                $result['payer_email'],
                $result['payer_name'],
                $result['transaction_id'],
                $result['amount'],
                'paypal'
            );
            
            echo json_encode([
                'success' => true,
                'download_link' => $downloadLink,
                'message' => 'Pago completado exitosamente'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'El pago no fue completado']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function sendConfirmationEmail($email, $requestId) {
    // Implementar envío de email
    // Por ahora solo logging
    error_log("Email enviado a: $email - Request ID: $requestId");
}

function buildTecnoXpertSystemPrompt($language = 'es') {
    $isEnglish = strtolower((string)$language) === 'en';

    if ($isEnglish) {
        return <<<PROMPT
You are TecnoBOT, the business assistant for TECNOXPERT.

Your scope is limited exclusively to TECNOXPERT. You may only answer questions about the company, contact details, schedule, location, services, portfolio, quotations, support and commercial solutions provided by TECNOXPERT.

TECNOXPERT information:
- Company: TECNOXPERT
- Phone / WhatsApp: 3117024021
- Email: admin@tecnoxpert.com
- Address: Calle 2 # 2-32, Pitalito, Huila, Colombia
- Schedule: Monday to Friday 8:00 AM - 6:00 PM, Saturday 9:00 AM - 1:00 PM

Services and solutions:
1. Computer repair and maintenance
2. Mobile phone repair
3. Web design and corporate websites
4. Web and mobile application development
5. Payment systems
6. Inventory systems
7. CCTV installation and security solutions
8. Server support and infrastructure
9. Technical support for businesses
10. Automations and bots for WhatsApp, Telegram and social media
11. CMR WhatsApp for customer follow-up and sales workflow control
12. WhatsApp Commerce and catalog setup
13. Digital marketing with Meta Ads and Google Ads
14. Business consulting and tailor-made technology solutions

Portfolio references:
- Payment System
- Inventory System
- WhatsApp Bot
- Telegram Bot
- TecnoXpert Website
- Digital Marketing Campaigns
- Business CCTV System
- WhatsApp Commerce
- CMR WhatsApp
- Mobile Management App

Instructions:
- Answer only within the TECNOXPERT context.
- If the user asks about unrelated topics, politely explain that you only help with TECNOXPERT information, services, quotations, support and contact.
- Do not invent prices, delivery times or unavailable features. If the information is not confirmed, say that it requires quotation or direct validation.
- Guide the user toward the most suitable service.
- If the need is not clear, ask one or two short clarifying questions.
- Do not generate tutorials, PDFs, essays, homework, recipes, news or general knowledge answers outside TECNOXPERT.
- Keep the tone warm, clear, commercial and professional.
- Never say you are an AI model.
- Always answer in English.
PROMPT;
    }

    return <<<PROMPT
Eres TecnoBOT, el asistente comercial de TECNOXPERT.

Tu alcance esta limitado exclusivamente a TECNOXPERT. Solo puedes responder preguntas sobre la empresa, contacto, horario, ubicacion, servicios, portafolio, cotizaciones, soporte y soluciones comerciales ofrecidas por TECNOXPERT.

Informacion de TECNOXPERT:
- Empresa: TECNOXPERT
- Telefono / WhatsApp: 3117024021
- Email: admin@tecnoxpert.com
- Direccion: Calle 2 # 2-32, Pitalito, Huila, Colombia
- Horario: Lunes a viernes de 8:00 AM a 6:00 PM, sabados de 9:00 AM a 1:00 PM

Servicios y soluciones:
1. Reparacion y mantenimiento de computadores
2. Reparacion de telefonia movil
3. Diseno web y sitios web corporativos
4. Desarrollo de aplicaciones web y moviles
5. Sistemas de pagos
6. Sistemas de inventarios
7. Instalacion CCTV y soluciones de seguridad
8. Soporte a servidores e infraestructura
9. Soporte tecnico para empresas
10. Automatizaciones y bots para WhatsApp, Telegram y redes sociales
11. CMR WhatsApp para seguimiento de clientes y control comercial
12. WhatsApp Commerce y configuracion de catalogos
13. Marketing digital con Meta Ads y Google Ads
14. Asesoria empresarial y soluciones tecnologicas a la medida

Referencias del portafolio:
- Sistema de Pagos
- Sistema de Inventarios
- Bot de WhatsApp
- Bot de Telegram
- Sitio Web TecnoXpert
- Campanas de marketing digital
- Sistema CCTV empresarial
- WhatsApp Commerce
- CMR WhatsApp
- App movil de gestion

Instrucciones:
- Responde solo dentro del contexto de TECNOXPERT.
- Si el usuario pregunta algo ajeno a la empresa, responde con amabilidad que solo puedes ayudar con informacion de TECNOXPERT, sus servicios, soporte, cotizaciones y contacto.
- No inventes precios, tiempos de entrega ni funciones no confirmadas. Si no tienes ese dato, indica que se valida por cotizacion o por WhatsApp.
- Orienta al usuario hacia el servicio correcto.
- Si la necesidad no esta clara, haz una o dos preguntas cortas para entenderla mejor.
- No generes tutoriales, PDFs, tareas, recetas, noticias ni respuestas generales fuera del alcance de TECNOXPERT.
- Manten un tono cercano, claro, comercial y profesional.
- Nunca digas que eres un modelo de IA.
- Responde siempre en espanol.
PROMPT;
}

function sanitizeChatMessages($messages) {
    if (!is_array($messages)) {
        return [];
    }

    $cleaned = [];
    $recentMessages = array_slice($messages, -12);

    foreach ($recentMessages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = $message['role'] ?? '';
        $content = trim((string)($message['content'] ?? ''));

        if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
            continue;
        }

        $cleaned[] = [
            'role' => $role,
            'content' => $content
        ];
    }

    return $cleaned;
}

function fallbackTecnoBotResponse($messages, $language = 'es') {
    $lastMessage = '';
    foreach (array_reverse(sanitizeChatMessages($messages)) as $message) {
        if ($message['role'] === 'user') {
            $lastMessage = strtolower($message['content']);
            break;
        }
    }

    $contains = static function (array $terms) use ($lastMessage) {
        foreach ($terms as $term) {
            if (strpos($lastMessage, $term) !== false) {
                return true;
            }
        }
        return false;
    };

    if (strtolower((string)$language) === 'en') {
        if ($contains(['price', 'quote', 'cost'])) {
            return 'Prices depend on the scope. Tell me which service you need and the main requirement so the team can prepare a quotation.';
        }
        if ($contains(['contact', 'phone', 'whatsapp', 'email'])) {
            return "You can contact TECNOXPERT on WhatsApp at 3117024021 or by email at admin@tecnoxpert.com.";
        }
        if ($contains(['schedule', 'hours', 'open'])) {
            return 'Business hours are Monday to Friday from 8:00 AM to 6:00 PM and Saturday from 9:00 AM to 1:00 PM.';
        }
        if ($contains(['service', 'website', 'software', 'crm', 'bot', 'repair', 'camera', 'cctv'])) {
            return 'TECNOXPERT offers web development, software, CRM, automations, server support, CCTV, and computer or mobile repair. Which solution are you interested in?';
        }
        return 'I can help you with TECNOXPERT services, technical support, quotations and contact information. Tell me briefly what you need.';
    }

    if ($contains(['precio', 'cotiza', 'cuanto', 'cuánto', 'costo', 'vale'])) {
        return 'El precio depende del alcance. Cuéntame qué servicio necesitas y el requisito principal para orientarte y preparar una cotización.';
    }
    if ($contains(['contacto', 'telefono', 'teléfono', 'whatsapp', 'correo', 'email'])) {
        return 'Puedes comunicarte con TECNOXPERT por WhatsApp al 3117024021 o escribir a admin@tecnoxpert.com.';
    }
    if ($contains(['horario', 'atienden', 'abierto'])) {
        return 'Atendemos de lunes a viernes de 8:00 AM a 6:00 PM y los sábados de 9:00 AM a 1:00 PM.';
    }
    if ($contains(['servicio', 'pagina', 'página', 'web', 'software', 'aplicacion', 'aplicación', 'crm', 'cmr', 'bot', 'reparacion', 'reparación', 'camara', 'cámara', 'cctv', 'servidor'])) {
        return 'TECNOXPERT ofrece desarrollo web, software, CRM, automatizaciones, soporte a servidores, CCTV y reparación de computadores o celulares. ¿Cuál solución te interesa?';
    }
    return 'Puedo ayudarte con servicios de TECNOXPERT, soporte técnico, cotizaciones y datos de contacto. Cuéntame brevemente qué necesitas.';
}

/**
 * Maneja las llamadas conversacionales a OpenAI
 */
function handleOpenAIChat($data) {
    $required = ['messages', 'language'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        $apiKey = OpenAIConfig::getApiKey();
        
        $chatMessages = sanitizeChatMessages($data['messages']);
        $messages = array_merge(
            [['role' => 'system', 'content' => buildTecnoXpertSystemPrompt($data['language'] ?? 'es')]],
            $chatMessages
        );
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => OpenAIConfig::getModel(),
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => 350
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception($curlError !== '' ? $curlError : 'No fue posible conectar con OpenAI');
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            throw new Exception($errorData['error']['message'] ?? 'Error al llamar a OpenAI');
        }
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['error'])) {
            throw new Exception($responseData['error']['message']);
        }
        
        $reply = trim((string)($responseData['choices'][0]['message']['content'] ?? ''));
        if ($reply === '') {
            throw new Exception('OpenAI devolvio una respuesta vacia');
        }

        echo json_encode([
            'success' => true,
            'response' => $reply,
            'mode' => 'openai'
        ]);
        
    } catch (Exception $e) {
        error_log('TecnoBOT OpenAI temporalmente no disponible: ' . $e->getMessage());
        echo json_encode([
            'success' => true,
            'response' => fallbackTecnoBotResponse($data['messages'], $data['language'] ?? 'es'),
            'mode' => 'fallback'
        ]);
    }
}

/**
 * Maneja la generación de contenido para PDFs con OpenAI
 */
function handleOpenAIGenerateContent($data) {
    $required = ['title', 'description', 'category'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }
    
    try {
        $apiKey = OpenAIConfig::getApiKey();
        
        $systemPrompt = 'Eres un experto creador de contenido técnico. Genera contenido profesional, detallado y completo para PDFs educativos. El contenido debe ser extenso, bien estructurado, con múltiples secciones y subsecciones.';
        
        $userPrompt = "Genera un contenido completo y profesional para un PDF sobre el tema: \"{$data['title']}\"
        
Descripción: {$data['description']}
Categoría: {$data['category']}

El contenido debe incluir:
1. Una introducción detallada
2. Al menos 5 secciones principales con subsecciones
3. Ejemplos prácticos cuando sea relevante
4. Conclusión y próximos pasos

Formatea la respuesta en HTML con <h2> para secciones principales, <h3> para subsecciones, <p> para párrafos, <ul>/<ol> para listas, y <code> para código o comandos.

Genera al menos 2000 palabras de contenido de calidad.";
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => OpenAIConfig::getModel(),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 3000
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            throw new Exception($errorData['error']['message'] ?? 'Error al generar contenido con OpenAI');
        }
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['error'])) {
            throw new Exception($responseData['error']['message']);
        }
        
        echo json_encode([
            'success' => true,
            'content' => $responseData['choices'][0]['message']['content']
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

?>
