<?php
/**
 * Utilidades para procesamiento de imágenes
 * Convierte imágenes a formato WebP automáticamente
 */

/**
 * Convierte una imagen a formato WebP
 * 
 * @param string $source_path Ruta del archivo de imagen original
 * @param string $target_path Ruta donde guardar el archivo WebP (opcional, si no se proporciona reemplaza el original)
 * @param int $quality Calidad de compresión (0-100, por defecto 85)
 * @return string|false Ruta del archivo WebP creado, o false en caso de error
 */
function convertImageToWebP($source_path, $target_path = null, $quality = 80) {
    // Si no se proporciona ruta destino, usar la misma pero con extensión .webp
    if ($target_path === null) {
        $pathInfo = pathinfo($source_path);
        $target_path = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    }
    
    // Verificar que el archivo fuente existe
    if (!file_exists($source_path)) {
        error_log("Error: Archivo fuente no existe: $source_path");
        return false;
    }
    
    // Obtener información de la imagen
    $imageInfo = @getimagesize($source_path);
    if ($imageInfo === false) {
        error_log("Error: No se pudo leer información de la imagen: $source_path");
        return false;
    }
    
    $mimeType = $imageInfo['mime'];
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    // Crear recurso de imagen según el tipo
    $image = null;
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source_path);
            // Preservar transparencia en PNG
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($source_path);
            break;
        default:
            error_log("Error: Formato de imagen no soportado: $mimeType");
            return false;
    }
    
    if ($image === false) {
        error_log("Error: No se pudo crear recurso de imagen desde: $source_path");
        return false;
    }

    // --- RESIZE LOGIC (Max Width 1200px) ---
    $maxWidth = 1200;
    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = $height * $ratio;

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Handle transparency for resize
        if ($mimeType == 'image/png' || $mimeType == 'image/webp') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image); // Free original
        $image = $newImage;
    }
    // ---------------------------------------
    
    // Convertir y guardar como WebP
    $success = @imagewebp($image, $target_path, $quality);
    imagedestroy($image);
    
    if (!$success) {
        error_log("Error: No se pudo guardar imagen WebP en: $target_path");
        return false;
    }
    
    return $target_path;
}

/**
 * Procesa un archivo subido: lo mueve y lo convierte a WebP
 * 
 * @param array $uploaded_file Array de $_FILES
 * @param string $target_dir Directorio destino (ruta absoluta)
 * @param string $new_filename Nombre del archivo (sin extensión, se agregará .webp)
 * @return array|false Array con 'path' (ruta relativa) y 'absolute_path', o false en error
 */
function processUploadedImage($uploaded_file, $target_dir, $new_filename) {
    if (!isset($uploaded_file['tmp_name']) || $uploaded_file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $temp_path = $uploaded_file['tmp_name'];
    
    // Primero mover el archivo temporal a su ubicación
    $temp_target = $target_dir . $new_filename . '_temp';
    $extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    $temp_target_with_ext = $temp_target . '.' . $extension;
    
    // Ensure target directory exists
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            error_log("Error: No se pudo crear el directorio: $target_dir");
            return false;
        }
    }

    if (!move_uploaded_file($temp_path, $temp_target_with_ext)) {
        error_log("Error: No se pudo mover archivo temporal a: $temp_target_with_ext");
        return false;
    }
    
    // Convertir a WebP
    $webp_path = $target_dir . $new_filename . '.webp';
    $converted = convertImageToWebP($temp_target_with_ext, $webp_path);
    
    // Eliminar archivo temporal original
    if (file_exists($temp_target_with_ext)) {
        @unlink($temp_target_with_ext);
    }
    
    if ($converted === false) {
        error_log("Error: No se pudo convertir imagen a WebP");
        return false;
    }
    
    // Retornar información del archivo procesado
    // 'path' contiene solo el nombre del archivo (para concatenar con ruta relativa)
    // 'absolute_path' contiene la ruta absoluta completa
    return [
        'path' => basename($webp_path), // Solo el nombre del archivo (ej: "prod_123.webp")
        'absolute_path' => $webp_path   // Ruta absoluta completa
    ];
}

?>

