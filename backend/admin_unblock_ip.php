<?php
// Endpoint deshabilitado por seguridad
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Not found']);
exit;
?>
