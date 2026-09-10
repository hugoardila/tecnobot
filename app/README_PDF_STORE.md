# 📚 Sistema de Venta de PDFs - TECNOXPERT

Sistema completo para venta de PDFs educativos, tutoriales y documentos técnicos personalizados.

## 🎯 Características

### ✅ Implementado

- **Base de datos SQLite**: Almacenamiento de PDFs, categorías y ventas
- **API REST completa**: Endpoints para gestionar todo el sistema
- **Interfaz moderna**: Catálogo responsive con diseño profesional
- **Categorización**: 4 categorías predefinidas (Tutoriales, Documentación, Configuración, Casos de Uso)
- **Sistema de compra**: Gestión de ventas y descargas
- **Link de descarga**: Enlaces únicos con expiración de 7 días
- **Solicitud personalizada**: Formulario para PDFs bajo demanda

### 📋 Por Implementar

- **Integración de pagos**: PayPal/Stripe
- **Generación automática de PDFs**: Desde HTML a PDF real
- **Email automático**: Envío de links de descarga
- **Panel de administración**: Gestión completa de PDFs
- **Dashboard de ventas**: Estadísticas y reportes

## 📁 Estructura de Archivos

```
tecnobot/
├── index.html              # Bot conversacional (anterior)
├── pdf_store.html          # Tienda de PDFs (UI principal)
├── api.php                 # API REST backend
├── seed_data.php           # Datos de ejemplo
│
├── config/
│   ├── database.php        # Clase Database + SQLite
│   └── pdf_generator.php   # Generador de PDFs
│
├── pdfs/                   # Archivos PDF almacenados
│   ├── sample_ubuntu.html
│   ├── sample_marketing.html
│   └── ...
│
├── uploads/                # Subida de archivos
├── templates/              # Plantillas de PDF
└── pdfs_store.db           # Base de datos SQLite
```

## 🚀 Instalación y Uso

### 1. Requisitos

- PHP 7.4+
- Apache/LAMP
- Extensiones PHP: `pdo_sqlite`, `json`

### 2. Instalación

```bash
cd /opt/lampp/htdocs/tecnobot

# Los archivos ya están creados, solo inicializa la BD
php -r "require 'config/database.php'; new Database();"

# Inserta datos de ejemplo
php seed_data.php
```

### 3. Acceso

- **Tienda de PDFs**: `http://localhost/tecnobot/pdf_store.html`
- **API**: `http://localhost/tecnobot/api.php`

## 🔌 API Endpoints

### GET

#### Obtener todos los PDFs
```
GET /api.php?action=pdfs
GET /api.php?action=pdfs&category=1
```

#### Obtener categorías
```
GET /api.php?action=categories
```

#### Obtener PDF por ID
```
GET /api.php?action=pdf&id=1
```

#### Descargar PDF
```
GET /api.php?action=download&link=ABC123...
```

### POST

#### Procesar compra
```json
POST /api.php?action=purchase
{
  "pdf_id": 1,
  "buyer_email": "cliente@email.com",
  "buyer_name": "Juan Pérez",
  "transaction_id": "TRANS123",
  "amount": 15.00,
  "payment_method": "manual"
}
```

#### Solicitud personalizada
```json
POST /api.php?action=custom-request
{
  "name": "María García",
  "email": "maria@email.com",
  "topic": "Configurar servidor web",
  "description": "Necesito guía para configurar Apache con SSL"
}
```

## 🗄️ Base de Datos

### Tablas

- **pdf_categories**: Categorías de PDFs
- **pdfs**: Catálogo de PDFs disponibles
- **sales**: Registro de ventas
- **custom_requests**: Solicitudes personalizadas

### Estructura

```sql
pdfs:
  - id, title, description
  - category_id, file_path
  - price, cover_image
  - downloads_count, created_at

sales:
  - id, pdf_id, buyer_email, buyer_name
  - transaction_id, amount, status
  - download_link, expires_at

custom_requests:
  - id, customer_name, customer_email
  - topic, description, status
```

## 💰 Flujo de Venta

1. Cliente navega el catálogo
2. Selecciona un PDF y hace clic en "Comprar"
3. Completa formulario de compra
4. Sistema genera link único de descarga
5. Cliente recibe link por email (por implementar)
6. Link válido por 7 días
7. Descarga del PDF mediante el link

## 🎨 Personalización

### Agregar Categorías

Edita `config/database.php` en la función `initTables()`:

```php
INSERT OR IGNORE INTO pdf_categories (name, description, icon) VALUES
('Mi Categoría', 'Descripción', '🎯');
```

### Cambiar Precios

Desde la base de datos:

```sql
UPDATE pdfs SET price = 25.00 WHERE id = 1;
```

### Agregar PDFs Manualmente

```sql
INSERT INTO pdfs (title, description, category_id, price, file_path)
VALUES ('Mi PDF', 'Descripción', 1, 20.00, 'pdfs/mi_pdf.html');
```

## 🔐 Seguridad

- Validación de datos en servidor
- Links únicos con expiración
- SQL prepared statements
- CORS configurado
- Validación de archivos

## 📈 Próximas Mejoras

1. **Integración PayPal/Stripe**: Pagos automáticos
2. **PDFs reales**: Conversión HTML → PDF
3. **Email automático**: Envío de links
4. **Panel Admin**: CRUD completo
5. **Reviews**: Sistema de valoraciones
6. **Búsqueda**: Filtros avanzados
7. **Descuentos**: Cupones y promociones

## 📞 Soporte

TECNOXPERT
- 📱 Teléfono: 3117024021
- 📧 Email: admin@tecnoxpert.com
- 📍 Dirección: Calle 2 # 2-32, Pitalito, Huila

## 📄 Licencia

Propiedad de TECNOXPERT © 2024

---

**Nota**: Este sistema está en desarrollo activo. Algunas características pueden no estar completamente funcionales.

