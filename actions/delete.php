<?php
require_once '../includes/db.php';

// --- NOVO: Captura as datas da URL para devolver depois ---
$data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS);
$data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS);

// Monta a string de parâmetros para o redirecionamento
$paramsRetorno = "";
if ($data_inicio && $data_fim) {
    $paramsRetorno = "&data_inicio={$data_inicio}&data_fim={$data_fim}";
}
// ----------------------------------------------------------

// Verifica se o ID foi passado
if (isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

    if ($id) {
        try {
            // 1. Pega os dados da transação
            $stmtGet = $pdo->prepare("SELECT * FROM transacoes WHERE id = :id");
            $stmtGet->execute([':id' => $id]);
            $transacao = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if ($transacao) {
                // Lógica de exclusão em cadeia (Parcelas)
                if ($transacao['parcelas_totais'] > 1) {
                    
                    // Remove (1/5) do nome para achar a raiz
                    $nomeBase = preg_replace('/\s*\(\d+\/\d+\)/', '', $transacao['descricao']);
                    $nomeBase = trim($nomeBase); 

                    $sql = "DELETE FROM transacoes 
                            WHERE descricao LIKE :descricao_pattern 
                            AND categoria_id = :categoria_id 
                            AND tipo = :tipo 
                            AND parcelas_totais = :parcelas_totais 
                            AND valor = :valor";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':descricao_pattern' => $nomeBase . '%',
                        ':categoria_id'      => $transacao['categoria_id'],
                        ':tipo'              => $transacao['tipo'],
                        ':parcelas_totais'   => $transacao['parcelas_totais'],
                        ':valor'             => $transacao['valor']
                    ]);

                } else {
                    // Exclusão Simples
                    $sql = "DELETE FROM transacoes WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':id', $id);
                    $stmt->execute();
                }

                // SUCESSO: Retorna mantendo as datas
                header("Location: ../index.php?status=deleted" . $paramsRetorno);
                exit;
            } else {
                // Não achou
                header("Location: ../index.php?status=error&msg=not_found" . $paramsRetorno);
                exit;
            }

        } catch (PDOException $e) {
            die("Erro ao excluir: " . $e->getMessage());
        }
    }
}

// Erro genérico
header("Location: ../index.php?status=error" . $paramsRetorno);
exit;
?>