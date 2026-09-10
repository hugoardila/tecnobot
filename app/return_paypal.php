<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmando Pago - TECNOXPERT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 30px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .error {
            color: #ef4444;
        }
        
        .success {
            color: #10b981;
        }
        
        .btn {
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container" id="container">
        <div class="spinner"></div>
        <h1>Procesando pago...</h1>
        <p>Por favor espera mientras confirmamos tu pago</p>
    </div>
    
    <script>
        // Obtener parámetros de URL
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token') || urlParams.get('orderId');
        const pdfId = sessionStorage.getItem('paypal_pdf_id');
        
        if (!token || !pdfId) {
            showError('Error: Faltan datos de la transacción');
        } else {
            completePayment(token, pdfId);
        }
        
        async function completePayment(orderId, pdfId) {
            try {
                const response = await fetch('api.php?action=capture-paypal-order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        pdf_id: pdfId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess(result);
                    // Limpiar sessionStorage
                    sessionStorage.removeItem('paypal_pdf_id');
                } else {
                    showError(result.error || 'Error al procesar el pago');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error de conexión al procesar el pago');
            }
        }
        
        function showSuccess(result) {
            const container = document.getElementById('container');
            container.innerHTML = `
                <div style="font-size: 4rem; margin-bottom: 20px;">✅</div>
                <h1 class="success">¡Pago Exitoso!</h1>
                <p class="success">Tu pago ha sido procesado correctamente.</p>
                <p style="margin-top: 30px;">
                    📄 Descarga: <a href="api.php?action=download&link=${result.download_link}" target="_blank" style="color: #667eea;">Click aquí</a>
                </p>
                <p style="margin-top: 20px; font-size: 0.9rem; color: #999;">
                    El link estará disponible por 7 días
                </p>
                <a href="pdf_store.html" class="btn" style="margin-top: 30px;">
                    <i class="fas fa-shopping-bag"></i> Ver más PDFs
                </a>
            `;
        }
        
        function showError(message) {
            const container = document.getElementById('container');
            container.innerHTML = `
                <div style="font-size: 4rem; margin-bottom: 20px;">❌</div>
                <h1 class="error">Error en el Pago</h1>
                <p class="error">${message}</p>
                <p style="margin-top: 30px;">
                    Por favor, intenta nuevamente o contacta a soporte
                </p>
                <a href="pdf_store.html" class="btn" style="margin-top: 30px;">
                    <i class="fas fa-arrow-left"></i> Volver a la Tienda
                </a>
            `;
        }
    </script>
</body>
</html>

