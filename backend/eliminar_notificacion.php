<?php
// --- INICIO: Habilitar errores para depuración ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // Cambiar a 0 en producción
ini_set('log_errors', 1); // Asegúrate de que los errores se registren
// Considera especificar un archivo de log: ini_set('error_log', '/ruta/a/tu/php-error.log');
// --- FIN: Depuración ---

session_start(); // Iniciar sesión para verificar autenticación

require_once 'cors.php';

// --- Establecer Content-Type JSON ---
// Hacer esto ANTES de cualquier posible salida JSON de error
header("Content-Type: application/json");

// --- Conexión DB y Autenticación ---
require_once 'db.php'; // Asegúrate que $pdo se define aquí

// Verifica la sesión
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401); // No autorizado
    // Usar die() para asegurar que no continúe
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

// --- Lógica de Eliminación ---

// Asegurarse que sea método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método no permitido
    die(json_encode(['success' => false, 'message' => 'Método no permitido. Se esperaba POST.']));
}

// Obtener datos del cuerpo JSON
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE); // Convierte a array asociativo

// Validar JSON y ID
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Solicitud incorrecta
    error_log("Invalid JSON received in eliminar_notificacion.php: " . json_last_error_msg() . " - Raw input: " . $inputJSON); // Log del error
    die(json_encode(['success' => false, 'message' => 'JSON inválido recibido.']));
}

$notificationId = $input['id'] ?? null;

// Validar el ID más estrictamente
if (!$notificationId || !filter_var($notificationId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    http_response_code(400);
    error_log("Invalid or missing notification ID in eliminar_notificacion.php: " . print_r($notificationId, true)); // Log del ID inválido
    die(json_encode(['success' => false, 'message' => 'ID de notificación inválido o no proporcionado']));
}
$notificationId = (int)$notificationId; // Asegurar que es un entero

try {
    // Preparar y ejecutar la eliminación usando sentencias preparadas
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id");
    // Vincular el parámetro de forma segura
    $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
    $stmt->execute();

    // Verificar si se eliminó alguna fila
    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        error_log("Notification deleted successfully. ID: " . $notificationId); // Log de éxito
        echo json_encode([
            'success' => true,
            'message' => 'Notificación eliminada correctamente',
            'deletedId' => $notificationId // Opcional: devolver el ID eliminado
        ]);
    } else {
        // No se encontró la notificación con ese ID (puede que ya estuviera eliminada)
        http_response_code(404); // No encontrado
        error_log("Notification not found for deletion. ID: " . $notificationId); // Log de no encontrado
        echo json_encode(['success' => false, 'message' => 'Notificación no encontrada con ID: ' . $notificationId]);
    }

} catch (PDOException $e) {
    // Error de base de datos
    http_response_code(500); // Error interno del servidor
    // Loggear el error real en el servidor (¡MUY IMPORTANTE!)
    error_log("Error al eliminar notificación (ID: $notificationId): " . $e->getMessage());
    // Enviar un mensaje genérico al cliente
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al eliminar la notificación.']);
} catch (Exception $e) {
    // Capturar cualquier otro error inesperado
    http_response_code(500);
    error_log("General Exception deleting notification ID $notificationId: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado.']);
}

exit(); // Terminar el script explícitamente
?>
