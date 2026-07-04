<?php
// firesight_api/config/audit_logger.php

function logAudit($conn, $actor_id, $actor_role, $action, $description = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $query = "INSERT INTO audit_logs (actor_id, actor_role, action, description, ip_address, user_agent)
                  VALUES (:actor_id, :actor_role, :action, :description, :ip, :ua)";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':actor_id', $actor_id);
        $stmt->bindParam(':actor_role', $actor_role);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':ua', $ua);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
        // huwag i-throw — audit log failure ay hindi dapat pumigil sa main action
    }
}