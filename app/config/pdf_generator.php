<?php
// Generador de PDFs dinámicos
require_once __DIR__ . '/../vendor/autoload.php';

class PDFGenerator {
    
    /**
     * Genera un PDF básico usando HTML a PDF
     */
    public static function generateFromHTML($content, $filename) {
        // Usar mPDF para generar PDF real
        try {
            $html = "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>TECNOXPERT - Guía</title>
            <style>
                body {
                    font-family: 'Arial', sans-serif;
                    color: #333;
                    line-height: 1.6;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                }
                .content {
                    padding: 20px 0;
                }
                h2 {
                    color: #667eea;
                    border-bottom: 2px solid #667eea;
                    padding-bottom: 10px;
                    margin-top: 30px;
                }
                .code-block {
                    background: #f4f4f4;
                    border-left: 4px solid #667eea;
                    padding: 15px;
                    margin: 15px 0;
                    font-family: 'Courier New', monospace;
                    overflow-x: auto;
                }
                .footer {
                    position: fixed;
                    bottom: 0;
                    text-align: center;
                    width: 100%;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>TECNOXPERT</h1>
                <p>Soluciones Tecnológicas</p>
            </div>
            
            <div class='content'>
                $content
            </div>
            
            <div class='footer'>
                <p>TECNOXPERT - Calle 2 # 2-32, Pitalito, Huila | 3117024021 | admin@tecnoxpert.com</p>
                <p>© " . date('Y') . " TECNOXPERT. Todos los derechos reservados.</p>
            </div>
        </body>
        </html>";
        
            // Crear PDF con mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 20,
                'margin_right' => 20,
                'margin_top' => 20,
                'margin_bottom' => 20
            ]);
            
            $mpdf->WriteHTML($html);
            
            // Si se proporciona filename, guardar, sino retornar contenido
            if ($filename) {
                $mpdf->Output($filename, 'F');
                return true;
            }
            
            return $mpdf->Output('', 'S');
            
        } catch (Exception $e) {
            error_log("Error generando PDF: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Genera un PDF de tutorial personalizado
     */
    public static function generateTutorial($title, $sections, $filename = null) {
        $content = "<h2>$title</h2>";
        
        foreach ($sections as $section) {
            $content .= "<div class='section'>";
            $content .= "<h3>{$section['title']}</h3>";
            
            if (isset($section['text'])) {
                $content .= "<p>{$section['text']}</p>";
            }
            
            if (isset($section['list'])) {
                $content .= "<ul>";
                foreach ($section['list'] as $item) {
                    $content .= "<li>$item</li>";
                }
                $content .= "</ul>";
            }
            
            if (isset($section['code'])) {
                $content .= "<div class='code-block'>" . htmlspecialchars($section['code']) . "</div>";
            }
            
            $content .= "</div>";
        }
        
        return self::generateFromHTML($content, $filename);
    }
    
    /**
     * Guarda el HTML como PDF usando mPDF
     */
    public static function saveAsPDF($htmlContent, $outputPath) {
        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 20,
                'margin_right' => 20,
                'margin_top' => 20,
                'margin_bottom' => 20
            ]);
            
            $mpdf->WriteHTML($htmlContent);
            $mpdf->Output($outputPath, 'F');
            
            return file_exists($outputPath);
        } catch (Exception $e) {
            error_log("Error guardando PDF: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Genera PDF desde HTML string (usado desde chat)
     */
    public static function generatePDFFromHTMLString($htmlString, $outputPath) {
        try {
            // Configurar directorio temporal para mPDF dentro del proyecto
            $tempDir = __DIR__ . '/../tmp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
                chmod($tempDir, 0777);
            }
            
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 20,
                'margin_right' => 20,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'tempDir' => $tempDir
            ]);
            
            $mpdf->WriteHTML($htmlString);
            $mpdf->Output($outputPath, 'F');
            
            return file_exists($outputPath);
        } catch (Exception $e) {
            error_log("Error generando PDF desde HTML: " . $e->getMessage());
            return false;
        }
    }
}


