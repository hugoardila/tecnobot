#!/bin/bash

# Script para reactivar la tienda de PDFs
# Uso: ./scripts/reactivate_pdf_store.sh

echo "🔄 Reactivando la tienda de PDFs..."

INDEX_FILE="index.html"

if [ ! -f "$INDEX_FILE" ]; then
    echo "❌ Error: No se encontró el archivo $INDEX_FILE"
    exit 1
fi

# Backup del archivo antes de modificar
BACKUP_FILE="${INDEX_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
cp "$INDEX_FILE" "$BACKUP_FILE"
echo "✅ Backup creado: $BACKUP_FILE"

# Crear archivo temporal
TEMP_FILE=$(mktemp)

# Procesar el archivo línea por línea
while IFS= read -r line || [ -n "$line" ]; do
    # 1. Reactivar comentario del CSS
    if [[ "$line" =~ "Tienda de PDFs temporalmente oculta" ]]; then
        echo "        /* Tienda de PDFs - ACTIVADA */"
        continue
    fi
    
    # 2. Reemplazar el bloque CSS del botón
    if [[ "$line" =~ "\.pdf-store-btn \{" ]]; then
        echo "        .pdf-store-btn {"
        echo "            position: fixed;"
        echo "            bottom: 20px;"
        echo "            right: 20px;"
        echo "            padding: 15px 25px;"
        echo "            background: linear-gradient(135deg, #10b981 0%, #059669 100%);"
        echo "            color: white;"
        echo "            border: none;"
        echo "            border-radius: 25px;"
        echo "            text-decoration: none;"
        echo "            font-weight: 600;"
        echo "            transition: all 0.3s ease;"
        echo "            display: flex;"
        echo "            align-items: center;"
        echo "            gap: 10px;"
        echo "            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4);"
        echo "            z-index: 1000;"
        continue
    fi
    
    # Saltar líneas comentadas del CSS
    if [[ "$line" =~ "display: none !important;" ]] || \
       [[ "$line" =~ "/\* position: fixed;" ]] || \
       [[ "$line" =~ "z-index: 1000; \*/" ]]; then
        continue
    fi
    
    # Saltar líneas entre .pdf-store-btn { y }
    if [[ "$line" =~ "bottom: 20px;" ]] || \
       [[ "$line" =~ "right: 20px;" ]] || \
       [[ "$line" =~ "padding: 15px 25px;" ]] || \
       [[ "$line" =~ "background: linear-gradient" ]] || \
       [[ "$line" =~ "color: white;" ]] || \
       [[ "$line" =~ "border: none;" ]] || \
       [[ "$line" =~ "border-radius: 25px;" ]] || \
       [[ "$line" =~ "text-decoration: none;" ]] || \
       [[ "$line" =~ "font-weight: 600;" ]] || \
       [[ "$line" =~ "transition: all 0.3s ease;" ]] || \
       [[ "$line" =~ "display: flex;" ]] || \
       [[ "$line" =~ "align-items: center;" ]] || \
       [[ "$line" =~ "gap: 10px;" ]] || \
       [[ "$line" =~ "box-shadow: 0 5px 20px" ]] || \
       [[ "$line" =~ "z-index: 1000;" ]] && [[ ! "$line" =~ "z-index: 1000; \*/" ]]; then
        continue
    fi
    
    # 3. Reactivar el hover del botón
    if [[ "$line" =~ "\.pdf-store-btn:hover \{" ]]; then
        echo "        .pdf-store-btn:hover {"
        echo "            transform: translateY(-3px);"
        echo "            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.6);"
        continue
    fi
    
    # Saltar líneas comentadas del hover
    if [[ "$line" =~ "/\* transform: translateY" ]] || \
       [[ "$line" =~ "/\* box-shadow: 0 8px 30px" ]] || \
       [[ "$line" =~ "box-shadow: 0 8px 30px.*\*/" ]]; then
        continue
    fi
    
    # 4. Reactivar el botón flotante en HTML
    if [[ "$line" =~ "Botón flotante.*TEMPORALMENTE OCULTO" ]]; then
        echo "    <!-- Botón flotante para ir a la tienda de PDFs -->"
        # Saltar las siguientes 4 líneas comentadas
        read -r
        read -r
        read -r
        read -r
        echo "    <a href=\"pdf_store.html\" class=\"pdf-store-btn\">"
        echo "        <i class=\"fas fa-book\"></i>"
        echo "        <span>📚 Tienda de PDFs</span>"
        echo "    </a>"
        continue
    fi
    
    # Saltar líneas del botón comentado
    if [[ "$line" =~ "<!-- <a href=\"pdf_store.html\"" ]] || \
       [[ "$line" =~ "<i class=\"fas fa-book\"></i>" ]] && [[ "$line" =~ "<!--" ]] || \
       [[ "$line" =~ "<span>📚 Tienda de PDFs</span>" ]] || \
       [[ "$line" =~ "</a> -->" ]]; then
        continue
    fi
    
    # 5. Reactivar la redirección en JavaScript
    if [[ "$line" =~ "Tienda de PDFs temporalmente en desarrollo" ]]; then
        echo "                        // Redirigir a la tienda de PDFs"
        echo "                        window.location.href = 'pdf_store.html';"
        # Saltar las siguientes 3 líneas (addMessage y comentario)
        read -r
        read -r
        read -r
        continue
    fi
    
    # Saltar líneas del addMessage y comentario
    if [[ "$line" =~ "addMessage\(currentLang === 'es'" ]] || \
       [[ "$line" =~ "La tienda de PDFs está en desarrollo" ]] || \
       [[ "$line" =~ "The PDF store is under development" ]] || \
       [[ "$line" =~ "window.location.href = 'pdf_store.html'; // Comentado temporalmente" ]]; then
        continue
    fi
    
    # Imprimir línea normal
    echo "$line"
    
done < "$INDEX_FILE" > "$TEMP_FILE"

# Reemplazar el archivo original
mv "$TEMP_FILE" "$INDEX_FILE"

echo ""
echo "✅ ¡Tienda de PDFs reactivada exitosamente!"
echo ""
echo "📋 Cambios realizados:"
echo "   ✓ CSS del botón restaurado"
echo "   ✓ Botón flotante visible"
echo "   ✓ Redirección a pdf_store.html activada"
echo ""
echo "💾 Backup guardado en: $BACKUP_FILE"
echo ""
echo "⚠️  Nota: Verifica que todo funcione correctamente antes de eliminar el backup."
echo "📝 Para revertir cambios: cp $BACKUP_FILE $INDEX_FILE"
