# TecnoBOT

Chatbot comercial de TECNOXPERT para orientar clientes sobre servicios, soporte, contacto y cotizaciones.

## Tecnologias

- PHP 8.2 y Apache
- OpenAI API para respuestas conversacionales
- SQLite para el catalogo local de PDFs
- PayPal para el flujo opcional de compra de PDFs
- Docker Compose

## Configuracion local

1. Copia `.env.example` como `.env`.
2. Completa solamente las variables de los servicios que vayas a utilizar.
3. Ejecuta `docker compose up --build`.
4. Abre `http://127.0.0.1:18082`.

El codigo no incluye claves, bases de datos, PDFs, archivos subidos, registros, respaldos ni dependencias generadas. Composer instala las dependencias declaradas en `app/composer.json` y `app/composer.lock`.

## Produccion

El repositorio es una copia sanitizada y portable. El despliegue productivo debe proporcionar las variables mediante un archivo `.env` protegido o un gestor de secretos. Nunca confirmes ese archivo en Git.

## Datos excluidos

- Credenciales de OpenAI y PayPal
- Base de datos `pdfs_store.db`
- PDFs y cargas de usuarios
- Directorios `vendor`, `tmp` y respaldos

## Flujo de desarrollo

1. Configura `.env` con valores de prueba.
2. Ejecuta `docker compose up --build`.
3. Comprueba la respuesta del bot y los endpoints desde el puerto local.
4. Valida los flujos de pago únicamente en sandbox antes de desplegar.

Las respuestas generadas por IA deben mantenerse bajo revisión del responsable del servicio y las credenciales deben llegar únicamente desde variables protegidas.
