<?php
require_once '../includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM metas WHERE id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        
        header('Location: ../metas.php?status=deleted');
        exit;
    } catch (PDOException $e) {
        header('Location: ../metas.php?status=error');
        exit;
    }
} else {
    header('Location: ../metas.php');
    exit;
}