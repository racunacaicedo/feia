<?php
/**
 * GUARDAR INSCRIPCIONES
 * Sitio: https://feiaal.org/cursos/utm/emprendimiento.html
 * Archivo: save_inscription.php
 * Recibe los datos del formulario y los guarda en la base de datos
 */

require_once 'config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
}

// Configurar headers CORS
header('Access-Control-Allow-Origin: https://feiaal.org');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Obtener datos del POST
    $correo = cleanInput($_POST['correo'] ?? '');
    $apellidos = cleanInput($_POST['apellidos'] ?? '');
    $nombres = cleanInput($_POST['nombres'] ?? '');
    $ciudadDomicilio = cleanInput($_POST['ciudadDomicilio'] ?? '');
    $perfilParticipante = cleanInput($_POST['perfilParticipante'] ?? '');
    $facultadUtm = cleanInput($_POST['facultadUtm'] ?? null);
    $tieneEmprendimiento = cleanInput($_POST['tieneEmprendimiento'] ?? '');
    $descripcionEmprendimiento = cleanInput($_POST['descripcionEmprendimiento'] ?? null);
    $sitioWeb = cleanInput($_POST['sitioWeb'] ?? null);
    $aceptaTerminos = isset($_POST['aceptaTerminos']) && $_POST['aceptaTerminos'] === 'true';

    // Datos adicionales
    $ipInscripcion = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Log de datos recibidos (solo en debug)
    if (DEBUG_MODE) {
        logError("Nueva inscripción recibida de: $correo desde IP: $ipInscripcion");
    }

    // Validaciones básicas
    $errors = [];

    if (empty($correo)) {
        $errors[] = 'El correo es obligatorio';
    } elseif (!isValidEmail($correo)) {
        $errors[] = 'El correo no es válido';
    }

    if (empty($apellidos)) {
        $errors[] = 'Los apellidos son obligatorios';
    }

    if (empty($nombres)) {
        $errors[] = 'Los nombres son obligatorios';
    }

    if (empty($ciudadDomicilio)) {
        $errors[] = 'La ciudad de domicilio es obligatoria';
    }

    if (empty($perfilParticipante)) {
        $errors[] = 'Debe seleccionar un perfil de participante';
    }

    $perfilesValidos = ['emprendedor', 'empleado_publico', 'empleado_privado', 'academia', 'graduado_utm', 'otro'];
    if (!in_array($perfilParticipante, $perfilesValidos)) {
        $errors[] = 'Perfil de participante no válido';
    }

    if (empty($tieneEmprendimiento)) {
        $errors[] = 'Debe indicar si tiene emprendimiento';
    }

    if (!in_array($tieneEmprendimiento, ['si', 'no'])) {
        $errors[] = 'Valor de emprendimiento no válido';
    }

    if (!$aceptaTerminos) {
        $errors[] = 'Debe aceptar los términos y condiciones';
    }

    // Si hay errores, devolverlos
    if (!empty($errors)) {
        logError("Errores de validación para: $correo", $errors);
        jsonResponse(['success' => false, 'message' => 'Errores de validación', 'errors' => $errors], 400);
    }

    // Conectar a la base de datos
    $db = getDbConnection();

    // Verificar si el correo ya existe
    $checkStmt = $db->prepare("SELECT id FROM inscripciones WHERE correo = ?");
    $checkStmt->execute([$correo]);

    if ($checkStmt->rowCount() > 0) {
        logError("Intento de inscripción duplicada: $correo");
        jsonResponse(['success' => false, 'message' => 'Este correo ya está registrado'], 409);
    }

    // Verificar cupos disponibles
    $configStmt = $db->prepare("SELECT valor FROM configuracion_curso WHERE clave = 'cupos_maximos'");
    $configStmt->execute();
    $cuposMaximos = intval($configStmt->fetchColumn() ?? 30);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM inscripciones WHERE estado = 'activa'");
    $countStmt->execute();
    $inscritosActivos = intval($countStmt->fetchColumn());

    if ($inscritosActivos >= $cuposMaximos) {
        logError("Cupos agotados. Intento de inscripción: $correo");
        jsonResponse(['success' => false, 'message' => 'Lo sentimos, ya no hay cupos disponibles'], 409);
    }

    // Preparar la consulta de inserción
    $insertStmt = $db->prepare("
        INSERT INTO inscripciones (
            correo, apellidos, nombres, ciudad_domicilio, perfil_participante,
            facultad_utm, tiene_emprendimiento, descripcion_emprendimiento,
            sitio_web_fanpage, acepta_terminos, ip_inscripcion, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Ejecutar la inserción
    $result = $insertStmt->execute([
        $correo,
        $apellidos,
        $nombres,
        $ciudadDomicilio,
        $perfilParticipante,
        $facultadUtm,
        $tieneEmprendimiento,
        $descripcionEmprendimiento,
        $sitioWeb,
        $aceptaTerminos ? 1 : 0,
        $ipInscripcion,
        $userAgent
    ]);

    if ($result) {
        $inscripcionId = $db->lastInsertId();

        // Log de éxito
        logError("Inscripción exitosa: $correo (ID: $inscripcionId)");

        // Enviar notificación por email (opcional)
        try {
            enviarNotificacionInscripcion($correo, $nombres, $apellidos, $inscripcionId);
        } catch (Exception $e) {
            // No fallar la inscripción por problemas de email
            logError("Error enviando email de confirmación: " . $e->getMessage());
        }

        // Respuesta exitosa
        jsonResponse([
            'success' => true,
            'message' => 'Inscripción guardada exitosamente',
            'data' => [
                'id' => $inscripcionId,
                'correo' => $correo,
                'nombres_completos' => "$nombres $apellidos",
                'cupos_restantes' => $cuposMaximos - $inscritosActivos - 1,
                'fecha_inscripcion' => date('d/m/Y H:i')
            ]
        ]);
    } else {
        throw new Exception('Error al guardar la inscripción');
    }

} catch (PDOException $e) {
    logError("Error de base de datos al guardar inscripción: " . $e->getMessage(), [
        'correo' => $correo ?? 'N/A',
        'ip' => getClientIP()
    ]);

    if (DEBUG_MODE) {
        jsonResponse(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()], 500);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
    }

} catch (Exception $e) {
    logError("Error general al guardar inscripción: " . $e->getMessage(), [
        'correo' => $correo ?? 'N/A',
        'ip' => getClientIP()
    ]);

    jsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
}

/**
 * Función para enviar notificación de inscripción (opcional)
 */
function enviarNotificacionInscripcion($correo, $nombres, $apellidos, $inscripcionId) {
    $asunto = "Confirmación de Inscripción - Curso IA para Emprendedores";
    $mensaje = "
    <html>
    <head>
        <title>Confirmación de Inscripción</title>
    </head>
    <body>
        <h2>¡Inscripción Confirmada!</h2>
        <p>Estimado/a $nombres $apellidos,</p>
        <p>Tu inscripción al curso <strong>Inteligencia Artificial para Emprendedores</strong> ha sido procesada exitosamente.</p>

        <h3>Detalles de tu inscripción:</h3>
        <ul>
            <li><strong>ID de inscripción:</strong> $inscripcionId</li>
            <li><strong>Correo:</strong> $correo</li>
            <li><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</li>
        </ul>

        <h3>Información del Curso:</h3>
        <ul>
            <li><strong>Duración:</strong> 48 horas académicas</li>
            <li><strong>Modalidad:</strong> Virtual MOOC</li>
            <li><strong>Fechas:</strong> 15, 22, 29 de septiembre - 6, 13, 20 de octubre</li>
        </ul>

        <p>Pronto recibirás más información sobre el acceso a la plataforma y materiales del curso.</p>

        <p>Saludos cordiales,<br>
        <strong>Equipo del Curso IA para Emprendedores</strong><br>
        Universidad Técnica de Manabí + FEIA</p>
    </body>
    </html>
    ";

    $headers = [
        'From: noreply@feiaal.org',
        'Content-type: text/html; charset=utf-8',
        'X-Mailer: PHP/' . phpversion()
    ];

    // En ambiente real, configurar servidor SMTP
    // mail($correo, $asunto, $mensaje, implode("\r\n", $headers));

    // También notificar al admin
    $adminMessage = "Nueva inscripción:\n";
    $adminMessage .= "Nombre: $nombres $apellidos\n";
    $adminMessage .= "Correo: $correo\n";
    $adminMessage .= "ID: $inscripcionId\n";
    $adminMessage .= "Fecha: " . date('d/m/Y H:i') . "\n";

    // mail(ADMIN_EMAIL, "Nueva Inscripción - Curso IA", $adminMessage);
}

?>