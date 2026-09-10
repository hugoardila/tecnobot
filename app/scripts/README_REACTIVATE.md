# Script de Reactivación de Tienda de PDFs

## 📋 Descripción
Este script reactiva automáticamente todas las funcionalidades de la tienda de PDFs que fueron ocultadas temporalmente durante el desarrollo.

## 🚀 Uso

### Desde la raíz del proyecto:
```bash
cd /opt/lampp/htdocs/tecnobot
./scripts/reactivate_pdf_store.sh
```

### Verificación de permisos:
Si el script no tiene permisos de ejecución:
```bash
chmod +x scripts/reactivate_pdf_store.sh
```

## ✨ Qué hace el script

1. **Crea un backup automático** del archivo `index.html` con timestamp
2. **Reactiva el CSS** del botón flotante "📚 Tienda de PDFs"
3. **Muestra el botón flotante** en la esquina inferior derecha
4. **Restaura la redirección** automática a `pdf_store.html` cuando se genera un PDF
5. **Elimina comentarios temporales** relacionados con la ocultación

## 📝 Cambios específicos que realiza

### CSS del botón:
- ✅ Restaura `display: flex` y todas las propiedades del botón
- ✅ Restaura el efecto hover
- ✅ Elimina `display: none !important`

### HTML:
- ✅ Descomenta el botón flotante completo
- ✅ Restaura el enlace a `pdf_store.html`

### JavaScript:
- ✅ Reemplaza el mensaje temporal por la redirección real
- ✅ Restaura `window.location.href = 'pdf_store.html'`
- ✅ Elimina el mensaje "en desarrollo"

## ⚠️ Importante

- **Backup automático**: El script crea un backup con formato `index.html.backup.YYYYMMDD_HHMMSS`
- **Verificación**: Revisa que todo funcione correctamente después de ejecutar
- **Reversión**: Puedes restaurar desde el backup si algo sale mal

## 🔄 Restaurar desde backup

Si necesitas revertir los cambios después de ejecutar el script:
```bash
# Listar backups disponibles
ls -la index.html.backup.*

# Restaurar desde un backup específico
cp index.html.backup.20241104_201500 index.html
```

## 📌 Notas adicionales

- El script modifica `index.html` directamente
- No afecta otros archivos del proyecto
- La funcionalidad queda completamente activa después de ejecutar
- El botón aparecerá en la esquina inferior derecha
- La redirección funcionará automáticamente al generar PDFs

## 🧪 Pruebas después de ejecutar

1. Verifica que el botón flotante aparezca en la página
2. Verifica que al hacer clic vaya a `pdf_store.html`
3. Genera un PDF desde el bot y verifica la redirección
4. Si todo funciona, puedes eliminar el backup (opcional)

## ❌ Si algo sale mal

```bash
# Restaurar el backup más reciente
cp index.html.backup.* index.html

# O restaurar uno específico
cp index.html.backup.20241104_201500 index.html
```

