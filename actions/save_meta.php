<?php
require_once '../includes/db.php';

// Verifica se foi um POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Filtra os dados
    $categoria_id = filter_input(INPUT_POST, 'categoria_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Tratamento do valor (converte vírgula para ponto se necessário)
    $limite = $_POST['limite'];
    $limite = str_replace('.', '', $limite); // Remove ponto de milhar
    $limite = str_replace(',', '.', $limite); // Troca vírgula decimal por ponto
    $limite = floatval($limite);

    if ($categoria_id && $limite > 0) {
        try {
            // Tenta inserir ou atualizar se já existir (ON DUPLICATE KEY UPDATE)
            // Isso garante que se você mudar a meta da "Academia", ele atualiza em vez de criar outra.
            $sql = "INSERT INTO metas (categoria_id, valor_limite) 
                    VALUES (:cat_id, :limite) 
                    ON DUPLICATE KEY UPDATE valor_limite = :limite_update";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':cat_id', $categoria_id);
            $stmt->bindValue(':limite', $limite);
            $stmt->bindValue(':limite_update', $limite);
            $stmt->execute();

            // Sucesso
            header('Location: ../metas.php?status=success');
            exit;

        } catch (PDOException $e) {
            // Erro de Banco (Ex: chave duplicada estranha)
            header('Location: ../metas.php?status=error&msg=db_error');
            exit;
        }
    } else {
        // Dados inválidos
        header('Location: ../metas.php?status=error&msg=invalid_data');
        exit;
    }
} else {
    // Acesso direto proibido
    header('Location: ../metas.php');
    exit;
}